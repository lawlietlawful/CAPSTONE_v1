<?php

namespace Tests\Feature;

use App\Models\BehavioralReport;
use App\Models\Referral;
use App\Models\RiskAssessment;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\BuildsTeacherScenario;
use Tests\TestCase;

/**
 * Guards the mobile Teacher API: authentication, the teacher-only gate, the
 * advised-student ownership checks, input validation, and pagination — the
 * boundaries we have been verifying by hand on every change.
 */
class TeacherPortalApiTest extends TestCase
{
    use RefreshDatabase;
    use BuildsTeacherScenario;

    // ── Authentication & role gate ────────────────────────────────────────

    public function test_unauthenticated_request_is_rejected(): void
    {
        $this->getJson('/api/teacher/dashboard')->assertUnauthorized(); // 401
    }

    public function test_non_teacher_is_forbidden(): void
    {
        Sanctum::actingAs(User::factory()->student()->create());

        $this->getJson('/api/teacher/dashboard')->assertForbidden(); // 403
    }

    public function test_teacher_can_load_dashboard(): void
    {
        [$teacher] = $this->teacherAdvising();
        Sanctum::actingAs($teacher);

        $this->getJson('/api/teacher/dashboard')
            ->assertOk()
            ->assertJsonStructure([
                'total_students', 'my_referrals', 'pending_referrals', 'recent_referrals',
            ]);
    }

    // ── Pagination ────────────────────────────────────────────────────────

    public function test_reports_list_is_paginated(): void
    {
        [$teacher, $student] = $this->teacherAdvising();
        BehavioralReport::factory()->count(25)->create([
            'reported_by' => $teacher->id,
            'student_id'  => $student->id,
        ]);
        Sanctum::actingAs($teacher);

        $this->getJson('/api/teacher/behavioral-reports')
            ->assertOk()
            ->assertJsonCount(20, 'reports')          // default page size
            ->assertJsonPath('meta.total', 25)
            ->assertJsonPath('meta.has_more', true);
    }

    public function test_per_page_is_clamped(): void
    {
        [$teacher] = $this->teacherAdvising();
        Sanctum::actingAs($teacher);

        $this->getJson('/api/teacher/behavioral-reports?per_page=9999')
            ->assertOk()
            ->assertJsonPath('meta.per_page', 50); // capped
    }

    // ── Filing a report: advised-student ownership + validation ───────────

    public function test_teacher_can_file_report_on_advised_student(): void
    {
        [$teacher, $student] = $this->teacherAdvising();
        $this->fakeMlEngine('high');
        Sanctum::actingAs($teacher);

        $this->postJson('/api/teacher/behavioral-reports', [
            'student_id'    => $student->id,
            'incident_type' => 'Disciplinary Incident',
            'incident_date' => now()->toDateString(),
            'description'   => 'Disrupted the class.',
        ])->assertCreated(); // 201

        $this->assertDatabaseHas('behavioral_reports', [
            'student_id'  => $student->id,
            'reported_by' => $teacher->id,
        ]);
    }

    public function test_teacher_cannot_file_report_on_non_advised_student(): void
    {
        [$teacher] = $this->teacherAdvising();
        // A student in a course this teacher does not teach.
        $stranger = Student::factory()->inCourse('BSED')->create();
        Sanctum::actingAs($teacher);

        $this->postJson('/api/teacher/behavioral-reports', [
            'student_id'    => $stranger->id,
            'incident_type' => 'Disciplinary Incident',
            'incident_date' => now()->toDateString(),
            'description'   => 'Should be blocked.',
        ])->assertForbidden(); // 403

        $this->assertDatabaseCount('behavioral_reports', 0);
    }

    public function test_invalid_incident_type_is_rejected(): void
    {
        [$teacher, $student] = $this->teacherAdvising();
        Sanctum::actingAs($teacher);

        $this->postJson('/api/teacher/behavioral-reports', [
            'student_id'    => $student->id,
            'incident_type' => 'Vandalism', // not in BehavioralReport::INCIDENT_TYPES
            'incident_date' => now()->toDateString(),
            'description'   => 'Unknown type.',
        ])->assertStatus(422);

        $this->assertDatabaseCount('behavioral_reports', 0);
    }

    // ── Reading a report: ownership ───────────────────────────────────────

    public function test_teacher_can_view_own_report(): void
    {
        [$teacher, $student] = $this->teacherAdvising();
        $report = BehavioralReport::factory()->create([
            'reported_by' => $teacher->id,
            'student_id'  => $student->id,
        ]);
        Sanctum::actingAs($teacher);

        $this->getJson("/api/teacher/behavioral-reports/{$report->id}")
            ->assertOk()
            ->assertJsonPath('report.id', $report->id);
    }

    public function test_teacher_cannot_view_another_teachers_report(): void
    {
        [$teacherA, $student] = $this->teacherAdvising();
        $report = BehavioralReport::factory()->create([
            'reported_by' => $teacherA->id,
            'student_id'  => $student->id,
        ]);

        $teacherB = User::factory()->teacher()->create();
        Sanctum::actingAs($teacherB);

        $this->getJson("/api/teacher/behavioral-reports/{$report->id}")
            ->assertForbidden(); // 403
    }

    // ── Filing a referral: advised-student ownership ──────────────────────

    public function test_teacher_can_file_referral_on_advised_student(): void
    {
        [$teacher, $student] = $this->teacherAdvising();
        $this->fakeMlEngine('moderate');
        Sanctum::actingAs($teacher);

        $this->postJson('/api/teacher/referrals', [
            'student_id'    => $student->id,
            'referral_type' => 'Misconduct',
            'reason'        => 'Repeated disruption.',
        ])->assertCreated();

        $this->assertDatabaseHas('referrals', [
            'student_id'  => $student->id,
            'referred_by' => $teacher->id,
        ]);
    }

    public function test_teacher_cannot_file_referral_on_non_advised_student(): void
    {
        [$teacher] = $this->teacherAdvising();
        $stranger = Student::factory()->inCourse('BSED')->create();
        Sanctum::actingAs($teacher);

        $this->postJson('/api/teacher/referrals', [
            'student_id'    => $stranger->id,
            'referral_type' => 'Misconduct',
            'reason'        => 'Should be blocked.',
        ])->assertForbidden();

        $this->assertDatabaseCount('referrals', 0);
    }

    public function test_other_specify_referral_requires_text(): void
    {
        [$teacher, $student] = $this->teacherAdvising();
        Sanctum::actingAs($teacher);

        // referral_type "Other" without referral_type_other must fail validation.
        $this->postJson('/api/teacher/referrals', [
            'student_id'    => $student->id,
            'referral_type' => 'Other',
            'reason'        => 'Missing the specify field.',
        ])->assertStatus(422);

        $this->assertDatabaseCount('referrals', 0);
    }

    // ── My Students roster + detail ───────────────────────────────────────

    public function test_roster_returns_advised_students_with_risk_and_summary(): void
    {
        [$teacher, $student] = $this->teacherAdvising();
        RiskAssessment::create([
            'student_id'  => $student->id,
            'risk_level'  => 'high',
            'risk_score'  => 88,
            'assessed_at' => now(),
        ]);
        Sanctum::actingAs($teacher);

        $this->getJson('/api/teacher/roster')
            ->assertOk()
            ->assertJsonPath('students.0.id', $student->id)
            ->assertJsonPath('students.0.risk_level', 'high')
            ->assertJsonPath('summary.high', 1);
    }

    public function test_student_detail_merges_reports_and_referrals_into_a_timeline(): void
    {
        [$teacher, $student] = $this->teacherAdvising();
        BehavioralReport::factory()->create(['student_id' => $student->id, 'reported_by' => $teacher->id]);
        Referral::factory()->create(['student_id' => $student->id, 'referred_by' => $teacher->id]);
        Sanctum::actingAs($teacher);

        $this->getJson("/api/teacher/students/{$student->id}")
            ->assertOk()
            ->assertJsonPath('student.id', $student->id)
            ->assertJsonCount(2, 'timeline'); // one report + one referral
    }

    public function test_teacher_cannot_view_a_non_advised_students_detail(): void
    {
        [$teacher] = $this->teacherAdvising();
        $stranger = Student::factory()->inCourse('BSED')->create();
        Sanctum::actingAs($teacher);

        $this->getJson("/api/teacher/students/{$stranger->id}")
            ->assertForbidden(); // 403 — can only monitor your own advisees
    }

    // ── Referral outcome detail ───────────────────────────────────────────

    public function test_pending_referral_detail_shows_the_filed_step_as_current(): void
    {
        [$teacher, $student] = $this->teacherAdvising();
        $referral = Referral::factory()->create([
            'referred_by' => $teacher->id,
            'student_id'  => $student->id,
            'status'      => 'pending',
        ]);
        Sanctum::actingAs($teacher);

        $this->getJson("/api/teacher/referrals/{$referral->id}")
            ->assertOk()
            ->assertJsonPath('journey.0.key', 'filed')
            ->assertJsonPath('journey.0.state', 'current')
            ->assertJsonPath('journey.2.key', 'resolved')
            ->assertJsonPath('journey.2.state', 'upcoming');
    }

    public function test_resolved_referral_detail_exposes_journey_and_counselor_notes(): void
    {
        [$teacher, $student] = $this->teacherAdvising();
        $referral = Referral::factory()->create([
            'referred_by'     => $teacher->id,
            'student_id'      => $student->id,
            'status'          => 'resolved',
            'resolved_at'     => now(),
            'counselor_notes' => 'Met with the student; arranged tutoring.',
        ]);
        Sanctum::actingAs($teacher);

        $this->getJson("/api/teacher/referrals/{$referral->id}")
            ->assertOk()
            ->assertJsonPath('referral.counselor_notes', 'Met with the student; arranged tutoring.')
            ->assertJsonPath('journey.0.state', 'done')       // filed
            ->assertJsonPath('journey.1.state', 'done')       // under review
            ->assertJsonPath('journey.2.state', 'current');   // resolved
    }

    public function test_teacher_cannot_view_another_teachers_referral(): void
    {
        [$teacherA, $student] = $this->teacherAdvising();
        $referral = Referral::factory()->create([
            'referred_by' => $teacherA->id,
            'student_id'  => $student->id,
        ]);
        $teacherB = User::factory()->teacher()->create();
        Sanctum::actingAs($teacherB);

        $this->getJson("/api/teacher/referrals/{$referral->id}")
            ->assertForbidden(); // 403
    }

    // ── Search & filter ───────────────────────────────────────────────────

    public function test_referrals_search_filters_by_student_name(): void
    {
        [$teacher, $alice] = $this->teacherAdvising([
            'first_name' => 'Alice', 'last_name' => 'Adams',
        ]);
        $bob = Student::factory()->create(['first_name' => 'Bob', 'last_name' => 'Baker']);
        Referral::factory()->create(['referred_by' => $teacher->id, 'student_id' => $alice->id]);
        Referral::factory()->create(['referred_by' => $teacher->id, 'student_id' => $bob->id]);
        Sanctum::actingAs($teacher);

        $this->getJson('/api/teacher/referrals?search=Alice')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('referrals.0.student_name', 'Adams, Alice');
    }

    public function test_referrals_status_filter_returns_only_that_status(): void
    {
        [$teacher, $student] = $this->teacherAdvising();
        Referral::factory()->create(['referred_by' => $teacher->id, 'student_id' => $student->id, 'status' => 'pending']);
        Referral::factory()->create(['referred_by' => $teacher->id, 'student_id' => $student->id, 'status' => 'resolved']);
        Sanctum::actingAs($teacher);

        $this->getJson('/api/teacher/referrals?status=resolved')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('referrals.0.status', 'resolved');
    }

    public function test_reports_search_filters_by_student_name(): void
    {
        [$teacher, $alice] = $this->teacherAdvising([
            'first_name' => 'Alice', 'last_name' => 'Adams',
        ]);
        $bob = Student::factory()->create(['first_name' => 'Bob', 'last_name' => 'Baker']);
        BehavioralReport::factory()->create(['reported_by' => $teacher->id, 'student_id' => $alice->id]);
        BehavioralReport::factory()->create(['reported_by' => $teacher->id, 'student_id' => $bob->id]);
        Sanctum::actingAs($teacher);

        $this->getJson('/api/teacher/behavioral-reports?search=Baker')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('reports.0.student_name', 'Baker, Bob');
    }
}
