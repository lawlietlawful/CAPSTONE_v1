# CAPSTONE_v1

Capstone workspace for the Automated Student Referral and Predictive Intervention Analytics system.

## Projects

- `student-referral-system` - Laravel backend and web dashboard.
- `StudentPortal` - Flutter student mobile/web portal.
- `TeacherPortal` - Flutter teacher portal for adviser and professor workflows.

## Dependency Files

- Laravel/PHP dependencies are declared in `student-referral-system/composer.json` and locked in `student-referral-system/composer.lock`.
- Node/Vite dependencies are declared in `student-referral-system/package.json` and locked in `student-referral-system/package-lock.json`.
- Flutter dependencies are declared in `StudentPortal/pubspec.yaml`, `StudentPortal/pubspec.lock`, `TeacherPortal/pubspec.yaml`, and `TeacherPortal/pubspec.lock`.

## Local Setup

Use `student-referral-system/.env.example` as the template for local Laravel configuration. The real `.env` file is intentionally ignored because it contains local secrets.
