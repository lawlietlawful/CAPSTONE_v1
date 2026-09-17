<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\StudentController;
use App\Http\Controllers\Admin\SeminarController;
use App\Http\Controllers\Admin\SmsLogController;
use App\Http\Controllers\Counselor\CounselorDashboardController;
use App\Http\Controllers\Admin\ReferralController as AdminReferralController;
use App\Http\Controllers\Counselor\ReferralController;
use App\Http\Controllers\Counselor\InterventionController;
use App\Http\Controllers\Teacher\TeacherDashboardController;

use App\Http\Controllers\Teacher\BehavioralReportController;

// ─── Root ────────────────────────────────────────────────────
Route::get('/', function () {
    return redirect('/login');
});

// ─── Auth (Breeze) ───────────────────────────────────────────
require __DIR__.'/auth.php';

// ─── Post-login Role Redirect ────────────────────────────────
Route::get('/dashboard', function () {
    return match(auth()->user()->role) {
        'super_admin' => redirect()->route('admin.dashboard'),
        'admin'       => redirect()->route('counselor.dashboard'),
        'teacher'     => redirect()->route('teacher.dashboard'),
        'student'     => redirect()->route('student.dashboard'),
        default       => redirect('/login'),
    };
})->middleware('auth')->name('dashboard');

// ─── Admin Routes ─────────────────────────────────────────────
Route::prefix('admin')
    ->middleware(['auth', 'admin'])
    ->name('admin.')
    ->group(function () {

    // Overview Dashboard
    Route::get('/dashboard', [AdminDashboardController::class, 'index'])
         ->name('dashboard');

    // Students
    Route::get('/students/import/template', [StudentController::class, 'downloadImportTemplate'])
         ->name('students.import.template');
    Route::post('/students/import/preview', [StudentController::class, 'previewImport'])
         ->name('students.import.preview');
    Route::post('/students/import/commit', [StudentController::class, 'commitImport'])
         ->name('students.import.commit');
    Route::get('/students/import/errors/{importId}', [StudentController::class, 'downloadImportErrors'])
         ->name('students.import.errors');
    Route::get('/students/import/codes/{importId}', [StudentController::class, 'downloadImportCodes'])
         ->name('students.import.codes');
    Route::resource('students', StudentController::class);
    Route::post('/students/{student}/activation-code', [StudentController::class, 'regenerateActivationCode'])
         ->name('students.activation-code');

    // Referrals (admin management)
    Route::get('/referrals/export', [AdminReferralController::class, 'export'])
         ->name('referrals.export');
    Route::post('/referrals/bulk-action', [AdminReferralController::class, 'bulkAction'])
         ->name('referrals.bulkAction');
    Route::get('/referrals', [AdminReferralController::class, 'index'])
         ->name('referrals.index');
    Route::get('/referrals/create', [AdminReferralController::class, 'create'])
         ->name('referrals.create');
    Route::post('/referrals', [AdminReferralController::class, 'store'])
         ->name('referrals.store');
    Route::get('/referrals/{referral}', [AdminReferralController::class, 'show'])
         ->name('referrals.show');
    Route::get('/referrals/{referral}/edit', [AdminReferralController::class, 'edit'])
         ->name('referrals.edit');
    Route::put('/referrals/{referral}', [AdminReferralController::class, 'update'])
         ->name('referrals.update');
    Route::delete('/referrals/{referral}', [AdminReferralController::class, 'destroy'])
         ->name('referrals.destroy');
    Route::patch('/referrals/{referral}/status', [AdminReferralController::class, 'updateStatus'])
         ->name('referrals.updateStatus');

    // At-Risk Students
    Route::get('/risk/export', [\App\Http\Controllers\Admin\RiskController::class, 'export'])
         ->name('risk.export');
    Route::post('/risk/bulk-action', [\App\Http\Controllers\Admin\RiskController::class, 'bulkAction'])
         ->name('risk.bulkAction');
    Route::get('/risk', [\App\Http\Controllers\Admin\RiskController::class, 'index'])
         ->name('risk.index');
    Route::get('/risk/{student}', [\App\Http\Controllers\Admin\RiskController::class, 'show'])
         ->name('risk.show');



    // Analytics
    Route::get('/analytics', [\App\Http\Controllers\Admin\AnalyticsController::class, 'index'])
         ->name('analytics.index');
    Route::get('/analytics/export-pdf', [\App\Http\Controllers\Admin\AnalyticsController::class, 'exportPdf'])
         ->name('analytics.export-pdf');

    // SMS Logs
    Route::get('/sms-logs', [SmsLogController::class, 'index'])
         ->name('sms-logs.index');

    // Seminars
    // Create and edit are handled by modals (on the index page and the
    // seminar detail page), so the standalone create/edit routes are excluded.
    Route::resource('seminars', SeminarController::class)->except(['create', 'edit']);
    Route::post('seminars/{seminar}/assign', [SeminarController::class, 'assignStudents'])
         ->name('seminars.assign');
    Route::patch('seminars/{seminar}/attendance', [SeminarController::class, 'updateAttendance'])
         ->name('seminars.attendance');
    Route::get('seminars/{seminar}/print', [SeminarController::class, 'printRoster'])
         ->name('seminars.print');
    Route::get('seminars/{seminar}/export-roster', [SeminarController::class, 'exportRoster'])
         ->name('seminars.export');

    // Behavioral Reports (read-only monitoring)
    Route::get('/behavioral-reports/export', [\App\Http\Controllers\Admin\BehavioralReportController::class, 'export'])
         ->name('behavioral-reports.export');
    Route::post('/behavioral-reports/bulk-action', [\App\Http\Controllers\Admin\BehavioralReportController::class, 'bulkAction'])
         ->name('behavioral-reports.bulkAction');
    Route::get('/behavioral-reports', [\App\Http\Controllers\Admin\BehavioralReportController::class, 'index'])
         ->name('behavioral-reports.index');
    Route::get('/behavioral-reports/{behavioral_report}/print', [\App\Http\Controllers\Admin\BehavioralReportController::class, 'print'])
         ->name('behavioral-reports.print');
    Route::get('/behavioral-reports/{behavioral_report}', [\App\Http\Controllers\Admin\BehavioralReportController::class, 'show'])
         ->name('behavioral-reports.show');
    Route::patch('/behavioral-reports/{behavioral_report}/status', [\App\Http\Controllers\Admin\BehavioralReportController::class, 'updateStatus'])
         ->name('behavioral-reports.updateStatus');

    // Teachers
    Route::get('/teachers/export', [\App\Http\Controllers\Admin\TeacherController::class, 'export'])
         ->name('teachers.export');
    Route::get('/teachers/{teacher}/print', [\App\Http\Controllers\Admin\TeacherController::class, 'print'])
         ->name('teachers.print');
    Route::get('/teachers', [\App\Http\Controllers\Admin\TeacherController::class, 'index'])
         ->name('teachers.index');
    Route::get('/teachers/{teacher}', [\App\Http\Controllers\Admin\TeacherController::class, 'show'])
         ->name('teachers.show');
    Route::put('/teachers/{teacher}', [\App\Http\Controllers\Admin\TeacherController::class, 'update'])
         ->name('teachers.update');
    Route::put('/teachers/{teacher}/assignments', [\App\Http\Controllers\Admin\TeacherController::class, 'updateAssignments'])
         ->name('teachers.assignments.update');

    // Courses & Sections (catalog used by Student records and Teacher assignments)
    Route::get('/courses', [\App\Http\Controllers\Admin\CourseController::class, 'index'])
         ->name('courses.index');
    Route::post('/courses', [\App\Http\Controllers\Admin\CourseController::class, 'store'])
         ->name('courses.store');
    Route::put('/courses/{course}', [\App\Http\Controllers\Admin\CourseController::class, 'update'])
         ->name('courses.update');
    Route::delete('/courses/{course}', [\App\Http\Controllers\Admin\CourseController::class, 'destroy'])
         ->name('courses.destroy');
    Route::post('/courses/{course}/sections', [\App\Http\Controllers\Admin\CourseController::class, 'storeSection'])
         ->name('courses.sections.store');
    Route::put('/course-sections/{courseSection}', [\App\Http\Controllers\Admin\CourseController::class, 'updateSection'])
         ->name('courses.sections.update');
    Route::delete('/course-sections/{courseSection}', [\App\Http\Controllers\Admin\CourseController::class, 'destroySection'])
         ->name('courses.sections.destroy');

    // User Management
    Route::resource('users', UserController::class);
    Route::post('/users/{user}/reset-password', [UserController::class, 'resetPassword'])
         ->name('users.reset-password');
    Route::post('/users/{user}/activation-code', [UserController::class, 'regenerateActivationCode'])
         ->name('users.activation-code');

    // ─── Super Admin Only ───────────────────────────────────────
    Route::middleware(['super_admin'])->group(function () {

        // Settings
        Route::get('/settings', [\App\Http\Controllers\Admin\SettingController::class, 'index'])
             ->name('settings.index');
        Route::post('/settings', [\App\Http\Controllers\Admin\SettingController::class, 'update'])
             ->name('settings.update');

        // ML Engine Retrain
        Route::post('/ml/retrain', [\App\Http\Controllers\Admin\MLController::class, 'retrain'])
             ->name('ml.retrain');
        Route::post('/ml/upload-csv', [\App\Http\Controllers\Admin\MLController::class, 'uploadCsv'])
             ->name('ml.upload-csv');
    });
});

// ─── Guidance Counselor Routes ────────────────────────────────
Route::prefix('counselor')
    ->middleware(['auth', 'counselor'])
    ->name('counselor.')
    ->group(function () {

    Route::get('/dashboard', [CounselorDashboardController::class, 'index'])
         ->name('dashboard');

    Route::get('referrals/{referral}/print', [ReferralController::class, 'print'])->name('referrals.print');
    Route::post('referrals/{referral}/log-parent-contact', [ReferralController::class, 'logParentContact'])->name('referrals.logParentContact');
    Route::resource('referrals', ReferralController::class);
    Route::patch('referrals/{referral}/status',
        [ReferralController::class, 'updateStatus'])
         ->name('referrals.updateStatus');

    Route::resource('interventions', InterventionController::class);

    // Behavioral Reports (Read-Only)
    Route::get('/behavioral-reports/export', [\App\Http\Controllers\Counselor\BehavioralReportController::class, 'export'])
         ->name('behavioral-reports.export');
    Route::post('/behavioral-reports/bulk-action', [\App\Http\Controllers\Counselor\BehavioralReportController::class, 'bulkAction'])
         ->name('behavioral-reports.bulkAction');
    Route::get('/behavioral-reports', [\App\Http\Controllers\Counselor\BehavioralReportController::class, 'index'])
         ->name('behavioral-reports.index');
    Route::get('/behavioral-reports/{behavioral_report}/print', [\App\Http\Controllers\Counselor\BehavioralReportController::class, 'print'])
         ->name('behavioral-reports.print');
    Route::get('/behavioral-reports/{behavioral_report}', [\App\Http\Controllers\Counselor\BehavioralReportController::class, 'show'])
         ->name('behavioral-reports.show');
    Route::patch('/behavioral-reports/{behavioral_report}/status', [\App\Http\Controllers\Counselor\BehavioralReportController::class, 'updateStatus'])
         ->name('behavioral-reports.updateStatus');

    // Seminars
    Route::resource('seminars', \App\Http\Controllers\Counselor\SeminarController::class);
    Route::post('seminars/{seminar}/assign', [\App\Http\Controllers\Counselor\SeminarController::class, 'assignStudents'])
         ->name('seminars.assign');
    Route::patch('seminars/{seminar}/attendance', [\App\Http\Controllers\Counselor\SeminarController::class, 'updateAttendance'])
         ->name('seminars.attendance');
    Route::get('seminars/{seminar}/print', [\App\Http\Controllers\Counselor\SeminarController::class, 'printRoster'])
         ->name('seminars.print');
    Route::get('seminars/{seminar}/export-roster', [\App\Http\Controllers\Counselor\SeminarController::class, 'exportRoster'])
         ->name('seminars.export');

    // Messages
    Route::get('/messages', [\App\Http\Controllers\Counselor\MessageController::class, 'index'])->name('messages.index');
    Route::get('/messages/{message}', [\App\Http\Controllers\Counselor\MessageController::class, 'show'])->name('messages.show');
    Route::post('/messages', [\App\Http\Controllers\Counselor\MessageController::class, 'store'])->name('messages.store');
    Route::delete('/messages/{message}', [\App\Http\Controllers\Counselor\MessageController::class, 'destroy'])->name('messages.destroy');
});

// ─── Teacher Routes ───────────────────────────────────────────
Route::prefix('teacher')
    ->middleware(['auth', 'teacher'])
    ->name('teacher.')
    ->group(function () {

    Route::get('/dashboard', [TeacherDashboardController::class, 'index'])
         ->name('dashboard');



    // Only the actions the controller actually implements. The full resource
    // exposed edit/update/destroy URLs that 500 on a missing controller method.
    Route::resource('behavioral-reports', BehavioralReportController::class)->only(['index', 'create', 'store', 'show']);
    Route::resource('referrals', \App\Http\Controllers\Teacher\ReferralController::class)->only(['index', 'create', 'store']);
});

// ─── Student Routes ───────────────────────────────────────────
Route::prefix('student')
    ->middleware(['auth', 'student'])
    ->name('student.')
    ->group(function () {

    Route::get('/dashboard', function () {
        return view('student.dashboard');
    })->name('dashboard');

    Route::get('/seminars/{seminar}/checkin', [\App\Http\Controllers\Student\SeminarCheckinController::class, 'checkin'])
         ->name('seminars.checkin');
});
