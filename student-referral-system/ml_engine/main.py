import hmac
import os
import shutil
from typing import List, Optional

import joblib
import numpy as np
import pandas as pd
from fastapi import BackgroundTasks, Depends, FastAPI, Header, HTTPException
from pydantic import BaseModel
from sklearn.model_selection import StratifiedKFold, cross_val_predict

# Model/vectorizer factories live in train_model.py so the offline trainer and
# the /retrain endpoint can never build different pipelines.
from train_model import (
    LABEL_MAP,
    NUMERIC_COLS,
    new_risk_model,
    new_text_model,
    new_vectorizer,
    stack_features,
)

app = FastAPI(title="Student Risk Assessment API", version="4.0")

base_dir = os.path.dirname(__file__)
model_path = os.path.join(base_dir, "risk_model.pkl")
vectorizer_path = os.path.join(base_dir, "vectorizer.pkl")
text_model_path = os.path.join(base_dir, "text_model.pkl")

model = None        # stage 2: RandomForest over [numeric | text probs]
vectorizer = None   # TF-IDF
text_model = None   # stage 1: LogisticRegression over TF-IDF


def load_models():
    global model, vectorizer, text_model
    try:
        model = joblib.load(model_path)
        vectorizer = joblib.load(vectorizer_path)
        text_model = joblib.load(text_model_path)
        print("Model, vectorizer and text model loaded successfully.")
    except Exception as e:
        print(f"Warning: Could not load models. Error: {e}")
        model = vectorizer = text_model = None


load_models()


# ── Authentication ───────────────────────────────────────────────────────────
# /predict and /retrain were completely unauthenticated. That is tolerable while
# the engine is bound to 127.0.0.1, but the moment it is started with
# --host 0.0.0.0 (e.g. so a phone on the same Wi-Fi can reach Laravel) anyone on
# the network can score students or OVERWRITE the trained model via /retrain.
#
# Set ML_ENGINE_KEY on the engine and ML_ENGINE_KEY in Laravel's .env to the same
# value. Left unset, the engine stays open — the existing localhost-only
# development setup keeps working with no changes.
API_KEY = os.environ.get("ML_ENGINE_KEY", "").strip()

if API_KEY:
    print("API key enforcement ENABLED (X-API-Key required).")
else:
    print("WARNING: ML_ENGINE_KEY unset — /predict and /retrain are unauthenticated. "
          "Safe only while bound to 127.0.0.1.")


def require_api_key(x_api_key: Optional[str] = Header(default=None, alias="X-API-Key")):
    if not API_KEY:
        return  # Unkeyed development setup.

    # compare_digest: constant-time, so a wrong key can't be recovered by timing.
    if not x_api_key or not hmac.compare_digest(x_api_key, API_KEY):
        raise HTTPException(status_code=403, detail="Invalid or missing X-API-Key.")


class StudentProfile(BaseModel):
    previous_referrals_count: int
    behavioral_reports_count: int
    concern_type_encoded: int
    days_since_last_referral: int
    referral_reason: str = ""


class TrainingRecord(BaseModel):
    previous_referrals_count: int
    behavioral_reports_count: int
    concern_type_encoded: int
    days_since_last_referral: int
    referral_reason: str
    risk_level: str  # 'low', 'moderate', 'high'


class RetrainPayload(BaseModel):
    records: List[TrainingRecord]


risk_decoder = {0: "low", 1: "moderate", 2: "high"}


@app.get("/")
def read_root():
    return {
        "status": "online" if model is not None else "degraded",
        "models_loaded": model is not None,
        "message": "ML Risk Assessment API v4.0 (stacked NLP + history)",
    }


@app.post("/predict", dependencies=[Depends(require_api_key)])
def predict_risk(student: StudentProfile):
    # Fail LOUD. Previously this returned {"error": ...} with HTTP 200, so the
    # Laravel caller saw `$response->successful() === true` and carried on with
    # missing keys. A 503 makes RiskAssessmentService log and abort cleanly.
    if model is None or vectorizer is None or text_model is None:
        raise HTTPException(
            status_code=503,
            detail="Models not loaded. Run `python train_model.py` first.",
        )

    numeric = np.array([[
        student.previous_referrals_count,
        student.behavioral_reports_count,
        student.concern_type_encoded,
        student.days_since_last_referral,
    ]])

    # Stage 1: what does the narrative say?  Stage 2: weigh it against history.
    text_probs = text_model.predict_proba(vectorizer.transform([student.referral_reason]))
    features = stack_features(numeric, text_probs)

    prediction_idx = int(model.predict(features)[0])
    risk_level = risk_decoder[prediction_idx]

    # Risk score (0-100): confidence-scaled band for the predicted class.
    probabilities = model.predict_proba(features)[0]
    if risk_level == "low":
        base_score = 10 + (probabilities[0] * 20)   # 10 - 30
    elif risk_level == "moderate":
        base_score = 40 + (probabilities[1] * 25)   # 40 - 65
    else:
        base_score = 70 + (probabilities[2] * 25)   # 70 - 95

    # Rule-based engine: which seminar should this student be routed to?
    recommended_seminar_tag = "general"
    reason_lower = student.referral_reason.lower()

    if risk_level == "high":
        if student.concern_type_encoded == 6 or "absence" in reason_lower or "truancy" in reason_lower:
            recommended_seminar_tag = "attendance_intervention"
        elif student.concern_type_encoded == 1 or "academic" in reason_lower or "failing" in reason_lower:
            recommended_seminar_tag = "academic_recovery"
        elif "bully" in reason_lower or "aggressive" in reason_lower or student.concern_type_encoded == 5:
            recommended_seminar_tag = "anti_bullying"
        else:
            recommended_seminar_tag = "values_formation"

    elif risk_level == "moderate":
        if student.concern_type_encoded == 6 or "absence" in reason_lower:
            recommended_seminar_tag = "attendance_intervention"
        elif "peer" in reason_lower or "bully" in reason_lower or student.concern_type_encoded == 5:
            recommended_seminar_tag = "anti_bullying"
        elif "study" in reason_lower or "math" in reason_lower or "assignment" in reason_lower or student.concern_type_encoded == 1:
            recommended_seminar_tag = "academic_recovery"
        else:
            recommended_seminar_tag = "values_formation"

    else:
        recommended_seminar_tag = "orientation"

    return {
        "risk_level": risk_level,
        "risk_score": round(float(base_score), 2),
        "recommended_seminar_tag": recommended_seminar_tag,
    }


def _backup_artifacts():
    """Retraining overwrites the shipped model. Keep the last good copy."""
    for path in (model_path, vectorizer_path, text_model_path):
        if os.path.exists(path):
            shutil.copy2(path, path + ".bak")


def perform_retraining(records: List[TrainingRecord]):
    global model, vectorizer, text_model
    print(f"Starting retraining with {len(records)} records...")

    df = pd.DataFrame([r.model_dump() for r in records])
    df["referral_reason"] = df["referral_reason"].fillna("").astype(str)
    y = df["risk_level"].str.lower().map(LABEL_MAP)

    if y.isna().any():
        print("Retraining aborted: unknown risk_level values present.")
        return
    if y.nunique() < 3:
        # A single-class or two-class fit would silently cripple the model.
        print(f"Retraining aborted: need all 3 classes, got {sorted(y.unique())}.")
        return

    y = y.to_numpy()
    _backup_artifacts()

    new_vec = new_vectorizer()
    X_text = new_vec.fit_transform(df["referral_reason"])

    # Same out-of-fold discipline as train_model.py: stage 2 must never see
    # stage 1's in-sample predictions, or it will over-trust the text.
    n_splits = min(5, int(pd.Series(y).value_counts().min()))
    if n_splits < 2:
        print("Retraining aborted: too few examples of some class for CV.")
        return

    cv = StratifiedKFold(n_splits=n_splits, shuffle=True, random_state=42)
    oof = cross_val_predict(new_text_model(), X_text, y, cv=cv, method="predict_proba")

    new_text = new_text_model()
    new_text.fit(X_text, y)

    new_risk = new_risk_model()
    new_risk.fit(stack_features(df[NUMERIC_COLS].to_numpy(), oof), y)

    joblib.dump(new_vec, vectorizer_path)
    joblib.dump(new_text, text_model_path)
    joblib.dump(new_risk, model_path)

    print("Retraining completed and models saved.")
    load_models()


@app.post("/retrain", dependencies=[Depends(require_api_key)])
def retrain_api(payload: RetrainPayload, background_tasks: BackgroundTasks):
    if len(payload.records) < 10:
        return {"error": "Need at least 10 records to retrain the model."}

    background_tasks.add_task(perform_retraining, payload.records)
    return {"message": "Retraining started in the background. The models will be updated automatically."}
