<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\StudentPortalController;
use App\Http\Controllers\Api\TeacherPortalController;

// Credential-checking endpoints get a stricter limiter than the rest of the API
// (5/min per email+IP, 20/min per IP) — see AppServiceProvider. Without it these
// were open to unlimited password guessing.
Route::middleware('throttle:login')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/activate', [AuthController::class, 'activate']);
    Route::post('/teacher/login', [AuthController::class, 'teacherLogin']);
    Route::post('/teacher/activate', [AuthController::class, 'teacherActivate']);
});

// Logout only needs a valid token — it works for any authenticated role
// (student or teacher), since it just revokes the caller's current token.
Route::middleware('auth:sanctum')->post('/logout', [AuthController::class, 'logout']);

Route::middleware(['auth:sanctum', 'student'])->group(function () {
    Route::prefix('student')->group(function () {
        Route::get('/profile', [StudentPortalController::class, 'profile']);
        Route::get('/seminars', [StudentPortalController::class, 'seminars']);
        Route::get('/notifications', [StudentPortalController::class, 'notifications']);
        Route::get('/risk-level', [StudentPortalController::class, 'riskLevel']);
        Route::get('/referrals', [StudentPortalController::class, 'referrals']);

        Route::post('/notifications/read/{id}', [StudentPortalController::class, 'markNotificationRead']);
        Route::post('/notifications/read-all', [StudentPortalController::class, 'markAllNotificationsRead']);
        Route::post('/change-password', [StudentPortalController::class, 'changePassword']);
    });
});

Route::middleware(['auth:sanctum', 'teacher'])->group(function () {
    Route::prefix('teacher')->group(function () {
        Route::get('/dashboard', [TeacherPortalController::class, 'dashboard']);
        Route::get('/students', [TeacherPortalController::class, 'students']);

        // "My Students" monitoring view: roster with risk, and per-student detail.
        Route::get('/roster', [TeacherPortalController::class, 'roster']);
        Route::get('/students/{id}', [TeacherPortalController::class, 'showStudent'])
            ->whereNumber('id');

        Route::get('/behavioral-reports', [TeacherPortalController::class, 'reports']);
        Route::post('/behavioral-reports', [TeacherPortalController::class, 'storeReport']);
        // Constrained to digits so it can never shadow a future literal segment
        // (e.g. /behavioral-reports/summary).
        Route::get('/behavioral-reports/{id}', [TeacherPortalController::class, 'showReport'])
            ->whereNumber('id');

        Route::get('/referrals', [TeacherPortalController::class, 'referrals']);
        Route::post('/referrals', [TeacherPortalController::class, 'storeReferral']);
        Route::get('/referrals/{id}', [TeacherPortalController::class, 'showReferral'])
            ->whereNumber('id');

        Route::get('/seminars/matching', [TeacherPortalController::class, 'matchingSeminars']);
        Route::post('/change-password', [TeacherPortalController::class, 'changePassword']);

        Route::get('/notifications', [TeacherPortalController::class, 'notifications']);
        Route::post('/notifications/{id}/read', [TeacherPortalController::class, 'markNotificationRead'])
            ->whereNumber('id');
        Route::post('/notifications/read-all', [TeacherPortalController::class, 'markAllNotificationsRead']);
    });
});
