<?php

namespace Tests\Feature;

use App\Models\BehavioralReport;
use App\Models\Student;
use App\Models\User;
use App\Services\BehavioralReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsTeacherScenario;
use Tests\TestCase;

/**
 * A school with exactly one counselor account always saw every new referral
 * land as "Unassigned" until that counselor manually claimed it — pointless
 * busywork, since there was never anyone else it could go to. Both referral
 * creation paths (ReferralService::create() for teacher/counselor/admin-filed
 * referrals, and BehavioralReportService's auto-escalation) now auto-assign
 * the sole counselor via User::soleCounselorId(), which only ever returns a
 * value when there's exactly one — the moment a second counselor exists, new
 * referrals go back to genuinely unassigned rather than guessing.
 */
class SoleCounselorAutoAssignTest extends TestCase
{
    use RefreshDatabase;
    use BuildsTeacherScenario;

    private function soleCounselor(): User
    {
        return User::factory()->create(['role' => 'admin', 'name' => 'Sole Counselor']);
    }

    public function test_a_teacher_filed_referral_auto_assigns_the_sole_counselor(): void
    {
        $this->fakeMlEngine('moderate', 50);
        $counselor = $this->soleCounselor();
        [$teacher, $student] = $this->teacherAdvising();

        $this->actingAs($teacher)->post(route('teacher.referrals.store'), [
            'student_id'    => $student->id,
            'referral_type' => 'Poor academic performance',
            'reason'        => 'Failing three subjects this quarter.',
        ]);

        $referral = $student->referrals()->firstOrFail();
        $this->assertSame($counselor->id, $referral->counselor_id);
    }

    public function test_an_explicit_counselor_pick_at_creation_still_wins(): void
    {
        $this->fakeMlEngine('moderate', 50);
        $this->soleCounselor();
        $otherCounselor = User::factory()->create(['role' => 'admin']);
        $admin = User::factory()->create(['role' => 'super_admin']);
        $student = Student::factory()->create();

        $this->actingAs($admin)->post(route('admin.referrals.store'), [
            'student_id'    => $student->id,
            'referral_type' => 'Absences',
            'reason'        => 'Missed several days without an excuse letter.',
            'counselor_id'  => $otherCounselor->id,
        ]);

        $referral = $student->referrals()->firstOrFail();
        $this->assertSame($otherCounselor->id, $referral->counselor_id);
    }

    public function test_no_auto_assignment_when_there_are_two_counselors(): void
    {
        $this->fakeMlEngine('moderate', 50);
        User::factory()->create(['role' => 'admin']);
        User::factory()->create(['role' => 'admin']);
        [$teacher, $student] = $this->teacherAdvising();

        $this->actingAs($teacher)->post(route('teacher.referrals.store'), [
            'student_id'    => $student->id,
            'referral_type' => 'Poor academic performance',
            'reason'        => 'Failing three subjects this quarter.',
        ]);

        $referral = $student->referrals()->firstOrFail();
        $this->assertNull($referral->counselor_id);
    }

    public function test_no_auto_assignment_when_there_are_zero_counselors(): void
    {
        $this->fakeMlEngine('moderate', 50);
        [$teacher, $student] = $this->teacherAdvising();

        $this->actingAs($teacher)->post(route('teacher.referrals.store'), [
            'student_id'    => $student->id,
            'referral_type' => 'Poor academic performance',
            'reason'        => 'Failing three subjects this quarter.',
        ]);

        $referral = $student->referrals()->firstOrFail();
        $this->assertNull($referral->counselor_id);
    }

    public function test_an_auto_escalated_referral_also_auto_assigns_the_sole_counselor(): void
    {
        $counselor = $this->soleCounselor();
        $this->fakeMlEngine('high', 90);
        [$teacher, $student] = $this->teacherAdvising();

        $report = app(BehavioralReportService::class)->create($teacher, [
            'student_id'    => $student->id,
            'incident_type' => 'Disciplinary Incident',
            'incident_date' => now()->toDateString(),
            'location'      => 'Room 101',
            'description'   => 'Serious incident requiring escalation.',
        ]);

        $this->assertNotNull($report->escalatedReferral);
        $this->assertSame($counselor->id, $report->escalatedReferral->counselor_id);
    }
}
