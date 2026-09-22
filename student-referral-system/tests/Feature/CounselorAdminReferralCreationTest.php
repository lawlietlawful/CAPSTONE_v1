<?php

namespace Tests\Feature;

use App\Models\RiskAssessment;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsTeacherScenario;
use Tests\TestCase;

/**
 * Admin\ReferralController::store() and Counselor\ReferralController::store()
 * used to build the Referral directly (Referral::create([...])) instead of
 * going through ReferralService::create() — the same service the teacher-filed
 * flow uses. Effect: a referral filed by a counselor or admin never got an ML
 * risk assessment, never got a seminar recommendation, and its concern_type
 * was left null (always displayed as "Other" regardless of referral type).
 * Both controllers now route through the shared service, and the manual
 * "Priority" field was removed from their forms — the AI decides it now,
 * matching the teacher-filed flow, instead of letting a manually-picked value
 * silently skip the ML step it should have triggered.
 */
class CounselorAdminReferralCreationTest extends TestCase
{
    use RefreshDatabase;
    use BuildsTeacherScenario;

    private function counselor(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function superAdmin(): User
    {
        return User::factory()->create(['role' => 'super_admin']);
    }

    public function test_counselor_created_referral_gets_an_ml_risk_assessment(): void
    {
        $this->fakeMlEngine('high', 88, 'academic_support');
        $counselor = $this->counselor();
        $student = Student::factory()->create();

        $this->actingAs($counselor)->post(route('counselor.referrals.store'), [
            'student_id'    => $student->id,
            'referral_type' => 'Poor academic performance',
            'reason'        => 'Failing three subjects this quarter.',
        ])->assertRedirect(route('counselor.referrals.index'));

        $referral = $student->referrals()->firstOrFail();
        $this->assertSame('academic', $referral->concern_type);
        $this->assertSame('high', $referral->priority);
        $this->assertNotNull($referral->riskAssessment);
        $this->assertEquals(88, $referral->riskAssessment->risk_score);
    }

    public function test_admin_created_referral_gets_an_ml_risk_assessment(): void
    {
        $this->fakeMlEngine('moderate', 55, 'attendance_intervention');
        $admin = $this->superAdmin();
        $student = Student::factory()->create();

        $this->actingAs($admin)->post(route('admin.referrals.store'), [
            'student_id'    => $student->id,
            'referral_type' => 'Absences',
            'reason'        => 'Missed four days without an excuse letter.',
        ])->assertRedirect(route('admin.referrals.index'));

        $referral = $student->referrals()->firstOrFail();
        $this->assertSame('attendance', $referral->concern_type);
        $this->assertSame('moderate', $referral->priority);
        $this->assertNotNull($referral->riskAssessment);
    }

    public function test_counselor_can_pre_assign_a_counselor_at_creation_time(): void
    {
        $this->fakeMlEngine('low');
        $filer = $this->counselor();
        $assignee = $this->counselor();
        $student = Student::factory()->create();

        $this->actingAs($filer)->post(route('counselor.referrals.store'), [
            'student_id'    => $student->id,
            'referral_type' => 'Tardiness',
            'reason'        => 'Late again.',
            'counselor_id'  => $assignee->id,
        ]);

        $referral = $student->referrals()->firstOrFail();
        $this->assertSame($assignee->id, $referral->counselor_id);
    }

    public function test_priority_field_is_no_longer_accepted_from_the_request(): void
    {
        $this->fakeMlEngine('low', 12);
        $counselor = $this->counselor();
        $student = Student::factory()->create();

        // Even if a client still sends a manual "priority", it must not
        // override the AI-assessed one.
        $this->actingAs($counselor)->post(route('counselor.referrals.store'), [
            'student_id'    => $student->id,
            'referral_type' => 'Tardiness',
            'reason'        => 'Late again.',
            'priority'      => 'high',
        ]);

        $referral = $student->referrals()->firstOrFail();
        $this->assertSame('low', $referral->priority);
    }

    public function test_ml_engine_being_down_does_not_block_referral_creation(): void
    {
        $this->failMlEngine();
        $counselor = $this->counselor();
        $student = Student::factory()->create();

        $this->actingAs($counselor)->post(route('counselor.referrals.store'), [
            'student_id'    => $student->id,
            'referral_type' => 'Tardiness',
            'reason'        => 'Late again.',
        ])->assertRedirect(route('counselor.referrals.index'));

        $referral = $student->referrals()->firstOrFail();
        // concern_type is derived locally from referral_type, independent of the ML call.
        $this->assertSame('attendance', $referral->concern_type);
        $this->assertNull($referral->riskAssessment);
    }

    // ── Validation-failure UX ──────────────────────────────────────────────
    // The "New Referral" modal used to close silently on a server-side
    // validation failure (e.g. "Other" type chosen without specifying it),
    // with no error shown anywhere. It now redirects back with the errors in
    // the session, which the view uses to reopen the modal.

    public function test_invalid_referral_submission_returns_validation_errors(): void
    {
        $this->fakeMlEngine('low');
        $counselor = $this->counselor();
        $student = Student::factory()->create();

        $response = $this->actingAs($counselor)->post(route('counselor.referrals.store'), [
            'student_id'    => $student->id,
            'referral_type' => 'Other',
            // referral_type_other deliberately omitted — required_if should fire.
            'reason'        => 'Something else.',
        ]);

        $response->assertSessionHasErrors('referral_type_other');
        $this->assertSame(0, $student->referrals()->count());
    }
}
