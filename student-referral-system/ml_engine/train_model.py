"""
Train the student risk-assessment model.

ARCHITECTURE (two-stage / stacked)
----------------------------------
    Stage 1 (NLP):  TF-IDF(referral_reason) -> LogisticRegression -> P(low/mod/high)
    Stage 2 (risk): RandomForest([4 numeric history features | 3 stage-1 probs])

Why stacked rather than one flat forest over [numerics | 300 TF-IDF columns]?
Because a Random Forest samples a subset of features at each split. With 300
sparse text columns and only 4 numeric ones, the student's history was almost
never offered as a split candidate and got drowned out (70.3% -> 78.5% when
switched to stacking, with the numeric feature-importance share rising to ~49%).
The two-stage form also states the design plainly: the NLP model reads the
narrative, and the forest weighs that judgement against the student's history.

LEAKAGE CONTROLS
----------------
  * The train/test split happens FIRST. The vectorizer and stage-1 model are
    fitted on the training split only.
  * Stage 2 is trained on OUT-OF-FOLD stage-1 probabilities (cross_val_predict).
    Training it on stage-1's own in-sample predictions would let stage 2 trust a
    signal that looks far cleaner than it will be at inference time.

The previous version of this script derived the label from a closed-form rule
over the numeric features and then sampled the text from a phrase list keyed by
that label. Text predicted the label perfectly, the forest ignored the numerics,
and a student with 8 prior referrals could score "low" by writing
"career advice". See generate_dataset.py for the honest DGP that replaced it.

Artifacts: vectorizer.pkl, text_model.pkl, risk_model.pkl, metrics.json
"""

import json
import os

import joblib
import numpy as np
import pandas as pd
from sklearn.ensemble import RandomForestClassifier
from sklearn.feature_extraction.text import TfidfVectorizer
from sklearn.linear_model import LogisticRegression
from sklearn.metrics import (
    accuracy_score,
    classification_report,
    confusion_matrix,
    f1_score,
)
from sklearn.model_selection import (
    StratifiedKFold,
    cross_val_predict,
    cross_val_score,
    train_test_split,
)

SEED = 42
NUMERIC_COLS = [
    "previous_referrals_count",
    "behavioral_reports_count",
    "concern_type_encoded",
    "days_since_last_referral",
]
LABEL_MAP = {"low": 0, "moderate": 1, "high": 2}
CLASS_NAMES = ["low", "moderate", "high"]

BASE_DIR = os.path.dirname(__file__)
DATA_PATH = os.path.join(BASE_DIR, "data", "training_data.csv")


# ── Model factories (imported by main.py's /retrain so the two paths can
#    never build different pipelines) ──────────────────────────────────────
def new_vectorizer() -> TfidfVectorizer:
    # Bigrams catch "not attended", "peer conflict". min_df=2 drops typo tokens,
    # so they become out-of-vocabulary — which is what real teacher phrasing
    # looks like at inference time.
    return TfidfVectorizer(
        max_features=300,
        ngram_range=(1, 2),
        min_df=2,
        sublinear_tf=True,
        stop_words="english",
    )


def new_text_model() -> LogisticRegression:
    """Stage 1. Linear beats an RF here: TF-IDF is high-dim and sparse."""
    return LogisticRegression(max_iter=2000, C=2.0, random_state=SEED)


def new_risk_model() -> RandomForestClassifier:
    """Stage 2. Only 7 dense features, so the forest can actually use them."""
    return RandomForestClassifier(
        n_estimators=400,
        max_depth=14,
        min_samples_leaf=2,
        class_weight="balanced_subsample",
        random_state=SEED,
        n_jobs=-1,
    )


def stack_features(numeric: np.ndarray, text_probs: np.ndarray) -> np.ndarray:
    """Feature order for stage 2. main.py MUST build the row the same way."""
    return np.hstack([numeric, text_probs])


def load_data() -> pd.DataFrame:
    if not os.path.exists(DATA_PATH):
        raise SystemExit(
            f"Dataset not found at {DATA_PATH}\nRun:  python generate_dataset.py"
        )
    df = pd.read_csv(DATA_PATH)
    df["referral_reason"] = df["referral_reason"].fillna("").astype(str)
    return df


def main():
    df = load_data()
    y = df["risk_level"].map(LABEL_MAP).to_numpy()

    print(f"Loaded {len(df)} rows from {os.path.relpath(DATA_PATH, BASE_DIR)}")
    print("Class balance:", {k: int((y == v).sum()) for k, v in LABEL_MAP.items()})
    print()

    # Split FIRST — nothing about the test set may touch the vectorizer.
    idx_train, idx_test = train_test_split(
        np.arange(len(df)), test_size=0.2, random_state=SEED, stratify=y
    )
    train, test = df.iloc[idx_train], df.iloc[idx_test]
    y_train, y_test = y[idx_train], y[idx_test]

    vectorizer = new_vectorizer()
    Xt_train = vectorizer.fit_transform(train["referral_reason"])  # fit on TRAIN only
    Xt_test = vectorizer.transform(test["referral_reason"])

    Xn_train = train[NUMERIC_COLS].to_numpy()
    Xn_test = test[NUMERIC_COLS].to_numpy()

    cv = StratifiedKFold(n_splits=5, shuffle=True, random_state=SEED)

    # Stage 1 — out-of-fold probabilities feed stage 2; the refit model ships.
    oof_probs = cross_val_predict(
        new_text_model(), Xt_train, y_train, cv=cv, method="predict_proba"
    )
    text_model = new_text_model()
    text_model.fit(Xt_train, y_train)
    test_probs = text_model.predict_proba(Xt_test)

    # Stage 2
    risk_model = new_risk_model()
    risk_model.fit(stack_features(Xn_train, oof_probs), y_train)
    pred = risk_model.predict(stack_features(Xn_test, test_probs))

    acc = accuracy_score(y_test, pred)
    macro_f1 = f1_score(y_test, pred, average="macro")

    # ── Baseline + ablations: is each feature group pulling its weight? ──
    majority = int(pd.Series(y_train).mode()[0])
    baseline_acc = float((y_test == majority).mean())

    numeric_only = new_risk_model()
    numeric_only.fit(Xn_train, y_train)
    num_acc = accuracy_score(y_test, numeric_only.predict(Xn_test))

    text_only_acc = accuracy_score(y_test, text_model.predict(Xt_test))

    print("Held-out results (20% stratified test split):")
    print(f"  {'majority-class baseline':<26} accuracy={baseline_acc * 100:5.2f}%")
    print(f"  {'numeric history only':<26} accuracy={num_acc * 100:5.2f}%")
    print(f"  {'referral text only (NLP)':<26} accuracy={text_only_acc * 100:5.2f}%")
    print(f"  {'STACKED (final model)':<26} accuracy={acc * 100:5.2f}%   macro-F1={macro_f1:.3f}")

    cv_scores = cross_val_score(
        new_risk_model(), stack_features(Xn_train, oof_probs), y_train, cv=cv, scoring="accuracy"
    )
    print(f"\n5-fold CV on train (stage 2): {cv_scores.mean() * 100:.2f}% (+/- {cv_scores.std() * 100:.2f})")

    print("\nPer-class performance:")
    print(classification_report(y_test, pred, target_names=CLASS_NAMES, digits=3))

    cm = confusion_matrix(y_test, pred)
    print("Confusion matrix (rows = actual, cols = predicted):")
    print(f"{'':>10}" + "".join(f"{c:>10}" for c in CLASS_NAMES))
    for name, row in zip(CLASS_NAMES, cm):
        print(f"{name:>10}" + "".join(f"{v:>10}" for v in row))

    imp = risk_model.feature_importances_
    numeric_share, text_share = float(imp[:4].sum()), float(imp[4:].sum())
    print(f"\nFeature-importance share: numeric={numeric_share * 100:.1f}%  text={text_share * 100:.1f}%")
    for col, i in zip(NUMERIC_COLS, imp[:4]):
        print(f"  {col:<28} {i:.4f}")

    joblib.dump(vectorizer, os.path.join(BASE_DIR, "vectorizer.pkl"))
    joblib.dump(text_model, os.path.join(BASE_DIR, "text_model.pkl"))
    joblib.dump(risk_model, os.path.join(BASE_DIR, "risk_model.pkl"))

    metrics = {
        "seed": SEED,
        "architecture": "stacked: TF-IDF -> LogisticRegression -> RandomForest(numeric + text_probs)",
        "n_rows": int(len(df)),
        "test_size": 0.2,
        "class_balance": {k: int((y == v).sum()) for k, v in LABEL_MAP.items()},
        "held_out": {
            "baseline_majority_class": round(baseline_acc, 4),
            "numeric_only": round(float(num_acc), 4),
            "text_only": round(float(text_only_acc), 4),
            "stacked_accuracy": round(float(acc), 4),
            "stacked_macro_f1": round(float(macro_f1), 4),
        },
        "cv_accuracy_mean": round(float(cv_scores.mean()), 4),
        "cv_accuracy_std": round(float(cv_scores.std()), 4),
        "confusion_matrix": cm.tolist(),
        "class_names": CLASS_NAMES,
        "feature_importance_share": {
            "numeric": round(numeric_share, 4),
            "text": round(text_share, 4),
        },
        "vectorizer_vocab_size": int(len(vectorizer.vocabulary_)),
    }
    with open(os.path.join(BASE_DIR, "metrics.json"), "w", encoding="utf-8") as fh:
        json.dump(metrics, fh, indent=2)

    print("\nSaved vectorizer.pkl, text_model.pkl, risk_model.pkl, metrics.json")


if __name__ == "__main__":
    main()
