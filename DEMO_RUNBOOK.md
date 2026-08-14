# Demo Runbook — Student Referral & Predictive Intervention System

**Misamis University Capstone**
Automated Student Referral and Predictive Intervention Analytics with SMS-based Parent–Teacher–Guidance Communication.

> Keep this file open in a second window during the demo. Every command below is
> exact. Follow the startup sequence **in order** — each service depends on the
> one before it.

---

## 0. What you are running (the 30-second architecture)

| Piece | What it is | Port |
|---|---|---|
| **MySQL** (XAMPP) | The database (`student_referral_db`) | 3306 |
| **Laravel web app** | Admin / Counselor / Teacher **web** portals + the mobile **API** | 8000 |
| **ML engine** (Python FastAPI) | Predicts student risk level from history + referral text | 8001 |
| **Teacher Portal** (Flutter) | The mobile app teachers use to log incidents & referrals | (Chrome) |
| **Student Portal** (Flutter) | The mobile app students use to view referrals & seminars | (Chrome) |

The data flow you are demonstrating:
**Teacher logs an incident → ML grades its severity → serious ones auto-escalate to a Guidance referral → the parent is notified by SMS.**

---

## 1. Pre-flight (do this the night before, once)

Run each check. If any fails, fix it before demo day — not during.

```powershell
# 1. Flutter toolchain is healthy
flutter doctor

# 2. Python has the ML dependencies
cd "d:\CAPSTONE PROJECT\student-referral-system\ml_engine"
python -c "import fastapi, uvicorn, sklearn, pandas, numpy, joblib; print('ML deps OK')"

# 3. The trained model files exist (do NOT delete these)
#    risk_model.pkl, text_model.pkl, vectorizer.pkl
dir "d:\CAPSTONE PROJECT\student-referral-system\ml_engine\*.pkl"

# 4. PHP dependencies installed
cd "d:\CAPSTONE PROJECT\student-referral-system"
php artisan --version
```

> ⚠️ **Do NOT click "Retrain AI" in the admin panel before or during the demo.**
> It retrains on the system's own output and degrades the model. The current
> model is good — leave it alone.

---

## 2. Startup sequence (demo day — follow in order)

### Step 1 — Start MySQL
Open **XAMPP Control Panel → Start "MySQL"**. (Apache is **not** needed — Laravel runs its own server.)

Verify:
```powershell
cd "d:\CAPSTONE PROJECT\student-referral-system"
php artisan migrate:status
```
✅ You should see a list of migrations, all `Ran`. ❌ A connection error means MySQL isn't up.

### Step 2 — Start the ML engine
```powershell
cd "d:\CAPSTONE PROJECT\student-referral-system\ml_engine"
python -m uvicorn main:app --host 127.0.0.1 --port 8001
```
Leave this window open. Verify in a **new** terminal:
```powershell
curl http://127.0.0.1:8001/
```
✅ Expect: `{"status":"online","models_loaded":true, ...}`
❌ `models_loaded:false` → the `.pkl` files are missing; run `python train_model.py` in the same folder.

### Step 3 — Start the Laravel app (web + API)
```powershell
cd "d:\CAPSTONE PROJECT\student-referral-system"
php artisan serve
```
Leave this window open. It serves **both** the web portals and the mobile API at `http://localhost:8000`. Verify by opening `http://localhost:8000/login` in a browser.

### Step 4 — Start the Teacher Portal (the app you built)
```powershell
cd "d:\CAPSTONE PROJECT\TeacherPortal"
flutter run -d chrome --no-web-resources-cdn
```
Chrome opens automatically. In Chrome, **open DevTools (F12) → click the device-toolbar icon (Ctrl+Shift+M) → pick a phone size** so it looks like a phone.

> 🔴 **The `--no-web-resources-cdn` flag is mandatory.** Without it the app shows
> a blank white screen (it tries to download its renderer from Google and fails
> offline). This flag makes it run fully offline — which is what you want in a
> panel room with flaky Wi-Fi.

### Step 5 (optional) — Start the Student Portal
Only if you're demoing the student side. Use a **separate terminal**:
```powershell
cd "d:\CAPSTONE PROJECT\StudentPortal"
flutter run -d chrome --no-web-resources-cdn
```

---

## 3. Login credentials

All **web** portal and **Teacher Portal** accounts use the password **`password`**.

| Role | Login | Password | Where |
|---|---|---|---|
| **Teacher** | School ID `T-2024-001` (or `teacher@school.com`) | `password` | Teacher Portal (mobile) + web |
| Admin | `admin@school.com` | `password` | Web only |
| Guidance Counselor | `counselor@school.com` | `password` | Web only |
| **Student** | Student ID `2023-042` (Sherylle) | `password` | Student Portal (mobile) |

> **Teacher activation demo (optional):** the Teacher Portal now mirrors the
> student flow — teachers sign in with a **School ID** and activate on first use.
> To show it: in the admin web portal, **User Management → Add New User → role
> Teacher**, enter a School ID, and save — an **activation code** pops up. Then in
> the Teacher Portal tap **"First time here? Activate your account"**, enter that
> School ID + code, and set a password. The existing `T-2024-001` account is
> already activated, so the main demo needs no setup.

> The Student Portal logs in with the **Student ID number**, not an email.
> `2023-042` is **Sherylle Saramosing — the same student the teacher demo files
> an incident on**, so the teacher and student sides tell one coherent story.
> (Other student accounts use the password each student set at activation; ask me
> to reset another to a known value if you need it.)

---

## 4. The known-good demo path (rehearse this exact sequence)

This is the "money shot" — it shows the whole pipeline end to end in under a minute.

**In the Teacher Portal (mobile):**

1. **Log in** as `teacher@school.com` / `password`.
2. You land on the **Home** dashboard (student count, referrals filed, pending).
3. Tap the **"Log Incident"** button (bottom-right).
4. **Select student** → choose **"Saramosing, Sherylle Dawn"** — the one student this teacher advises.
5. **What happened?** → tap **"Academic Failure"**.
   *(Academic Failure always escalates — guaranteed, regardless of what the AI scores. This makes the demo deterministic.)*
6. **Description** → type anything, e.g. *"Failed three consecutive major exams."*
7. Tap **Submit Report**. Wait ~2 seconds (the AI is scoring it).
8. **The result sheet appears** and this is what you narrate:
   - **AI-assessed severity** badge (High) — *"the ML engine graded this, the teacher never picks severity."*
   - **"Escalated to Guidance"** notice — *"serious incidents auto-escalate to a referral."*
   - **"the parent has been notified by SMS"** — *"and the parent gets an automatic text."*
9. Tap **Done**.
10. Go to the **Referrals** tab → the new referral is at the top with an **"Auto"** badge and **High** priority.
11. Go to the **Reports** tab → the report is there with an **"Escalated"** badge.

**Optional — show the Guidance side (web):** log in at `http://localhost:8000/login` as `counselor@school.com` → the same referral is now in the counselor's queue with the AI risk assessment attached.

> **Why this proves your thesis:** one teacher action triggered ML grading,
> policy-based escalation, a database referral, an ML risk assessment, and a
> parent SMS — the full "predictive intervention + SMS communication" loop.

### The AI is real — a talking point if they probe it
If a panelist asks *"is the AI actually doing anything?"*, log a second incident on the **same student** with type **"Disciplinary Incident"** and a mild description — it will grade **lower** and may **not** escalate. Same student, different input, different outcome = the model is genuinely reading the situation, not hard-coded.

> 💬 **No real texts are sent.** The SMS runs in **simulated mode** (no API key
> configured), so parent notifications are logged, not delivered. Safe to demo
> repeatedly.

---

## 5. Troubleshooting (the problems we actually hit)

| Symptom | Cause | Fix |
|---|---|---|
| **Flutter app is a blank white screen** | Missing the CDN flag | Stop it, re-run with `--no-web-resources-cdn` |
| **`[Errno 10048] ... bind on 8001`** | The ML engine is **already running** | Not an error — just use the running one. Check with `curl http://127.0.0.1:8001/`. Only start a new one if that fails. |
| **App says "Unable to connect"** | Laravel (`:8000`) isn't running | Start Step 3 |
| **Incident submits but severity is "Pending AI" / "Unassessed"** | ML engine (`:8001`) is down | Start Step 2; then it self-corrects, or run `php artisan reports:reassess` |
| **Login fails with correct password** | Typo / caps-lock (`Password` ≠ `password`) | It's all-lowercase `password` |
| **`Failed to bind ... :8000` (Laravel)** | A stale `php artisan serve` is still holding the port | Close old terminals, or in PowerShell: `Get-NetTCPConnection -LocalPort 8000 -State Listen \| ForEach-Object { Stop-Process -Id $_.OwningProcess -Force }` |
| **Flutter port 5055/random stuck after closing** | The `dart` process outlives the terminal | `Get-NetTCPConnection -LocalPort <port> -State Listen \| ForEach-Object { Stop-Process -Id $_.OwningProcess -Force }` |

**Golden rule if anything misbehaves mid-demo:** the dependency order is
**MySQL → ML engine → Laravel → Flutter app.** Restart from the lowest broken
layer upward.

---

## 6. After the demo

Just close the terminal windows (Ctrl+C in each, then close). Stop MySQL from the
XAMPP panel. Nothing needs cleanup.

If you ran the demo several times and want the referral list tidy again for the
next run, ask me to remove the demo referrals — they're safe to delete and I know
exactly which ones they are.

---

## 7. One-glance startup checklist (print this)

```
[ ] XAMPP → MySQL started            → php artisan migrate:status shows "Ran"
[ ] ML engine: uvicorn ... :8001     → curl :8001 shows models_loaded:true
[ ] Laravel: php artisan serve       → localhost:8000/login opens
[ ] Teacher Portal: flutter run -d chrome --no-web-resources-cdn  → app paints
[ ] Chrome DevTools → device toolbar → phone size
[ ] Test login: teacher@school.com / password
[ ] Rehearse the happy path once before the panel arrives
```
