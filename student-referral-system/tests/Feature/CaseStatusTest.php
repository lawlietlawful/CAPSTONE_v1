<?php

namespace Tests\Feature;

use App\Models\BehavioralReport;
use App\Models\Intervention;
use App\Models\Referral;
use App\Models\RiskAssessment;
use App\Models\Student;
use App\Models\User;
use App\Support\CaseStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * One visible case status per student, so nobody has to read the referral,
 * report and intervention statuses separately to know if anything is open.
 */
class CaseStatusTest extends TestCase
{
    use RefreshDatabase;

    private function counselor(): User
    {
        return User::factory()->counselor()->create();
    }

    private function caseOf(Student $s): array
    {
        return CaseStatus::for($s->fresh());
    }

    private function open(Student $s, string $status = 'pending', ?int $counselorId = 0): Referral
    {
        return Referral::factory()->create([
            'student_id' => $s->id, 'status' => $status, 'counselor_id' => $counselorId === 0 ? $this->counselor()->id : $counselorId,
        ]);
    }

    public function test_a_student_with_nothing_on_file_has_no_case(): void
    {
        $this->assertSame('none', $this->caseOf(Student::factory()->create())['key']);
    }

    public function test_an_unreviewed_report_without_a_referral_is_called_out(): void
    {
        $s = Student::factory()->create();
        BehavioralReport::factory()->create(['student_id' => $s->id, 'status' => 'pending']);

        $state = $this->caseOf($s);

        $this->assertSame('report_waiting', $state['key']);
        $this->assertNull($state['referral_id']);
        $this->assertStringContainsString('no referral', $state['detail']);
    }

    public function test_reviewed_reports_alone_do_not_count_as_waiting(): void
    {
        $s = Student::factory()->create();
        BehavioralReport::factory()->create(['student_id' => $s->id, 'status' => 'reviewed']);

        $this->assertSame('none', $this->caseOf($s)['key']);
    }

    public function test_an_open_referral_nobody_owns_is_unassigned(): void
    {
        $s = Student::factory()->create();
        $r = $this->open($s, 'pending', null);

        $state = $this->caseOf($s);

        $this->assertSame('unassigned', $state['key']);
        $this->assertSame('red', $state['tone']);
        $this->assertSame($r->id, $state['referral_id']);
    }

    public function test_an_assigned_pending_referral_is_open_not_started(): void
    {
        $s = Student::factory()->create();
        $this->open($s, 'pending');

        $this->assertSame('pending', $this->caseOf($s)['key']);
    }

    public function test_an_in_progress_referral_shows_the_next_follow_up(): void
    {
        $s = Student::factory()->create();
        $c = $this->counselor();
        $r = $this->open($s, 'in_progress', $c->id);
        Intervention::factory()->create(['referral_id' => $r->id, 'counselor_id' => $c->id, 'follow_up_date' => today()->addDays(5), 'outcome' => null]);

        $state = $this->caseOf($s);

        $this->assertSame('in_progress', $state['key']);
        $this->assertStringContainsString(today()->addDays(5)->format('M j, Y'), $state['detail']);
    }

    public function test_an_in_progress_referral_with_no_follow_up_says_so(): void
    {
        $s = Student::factory()->create();
        $this->open($s, 'in_progress');

        $this->assertStringContainsString('No follow-up scheduled', $this->caseOf($s)['detail']);
    }

    public function test_a_passed_follow_up_makes_it_overdue(): void
    {
        $s = Student::factory()->create();
        $c = $this->counselor();
        $r = $this->open($s, 'in_progress', $c->id);
        Intervention::factory()->create(['referral_id' => $r->id, 'counselor_id' => $c->id, 'follow_up_date' => today()->subDays(3), 'outcome' => null]);

        $state = $this->caseOf($s);

        $this->assertSame('follow_up_overdue', $state['key']);
        $this->assertStringContainsString('1 follow-up is past due', $state['detail']);
    }

    public function test_a_superseded_or_resolved_follow_up_is_not_overdue(): void
    {
        $s = Student::factory()->create();
        $c = $this->counselor();
        $r = $this->open($s, 'in_progress', $c->id);
        Intervention::factory()->create(['referral_id' => $r->id, 'counselor_id' => $c->id, 'follow_up_date' => today()->subDays(8), 'outcome' => null]);
        Intervention::factory()->create(['referral_id' => $r->id, 'counselor_id' => $c->id, 'follow_up_date' => null, 'outcome' => 'improving']);

        $this->assertSame('in_progress', $this->caseOf($s)['key']);
    }

    public function test_unassigned_outranks_overdue_which_outranks_in_progress_and_pending(): void
    {
        $s = Student::factory()->create();
        $c = $this->counselor();
        $owned = $this->open($s, 'in_progress', $c->id);
        Intervention::factory()->create(['referral_id' => $owned->id, 'counselor_id' => $c->id, 'follow_up_date' => today()->subDays(2), 'outcome' => null]);
        $this->assertSame('follow_up_overdue', $this->caseOf($s)['key']);

        $this->open($s, 'pending', null);
        $this->assertSame('unassigned', $this->caseOf($s)['key']);
    }

    public function test_several_open_referrals_are_counted_and_the_newest_is_linked(): void
    {
        $s = Student::factory()->create();
        $this->open($s, 'pending');
        $newest = $this->open($s, 'in_progress');

        $state = $this->caseOf($s);

        $this->assertStringContainsString('2 open referrals', $state['detail']);
        $this->assertSame($newest->id, $state['referral_id']);
    }

    public function test_a_student_whose_referrals_are_all_resolved_is_closed_with_the_date(): void
    {
        $s = Student::factory()->create();
        Referral::factory()->create(['student_id' => $s->id, 'status' => 'resolved', 'resolved_at' => now()->subDays(2)]);

        $state = $this->caseOf($s);

        $this->assertSame('closed', $state['key']);
        $this->assertSame('green', $state['tone']);
        $this->assertStringContainsString(now()->subDays(2)->format('M j, Y'), $state['detail']);
    }

    public function test_a_cancelled_last_referral_is_closed_and_says_cancelled(): void
    {
        $s = Student::factory()->create();
        Referral::factory()->create(['student_id' => $s->id, 'status' => 'cancelled']);

        $state = $this->caseOf($s);

        $this->assertSame('closed', $state['key']);
        $this->assertStringContainsString('cancelled', $state['detail']);
    }

    public function test_a_closed_case_still_mentions_unreviewed_reports(): void
    {
        $s = Student::factory()->create();
        Referral::factory()->create(['student_id' => $s->id, 'status' => 'resolved', 'resolved_at' => now()]);
        BehavioralReport::factory()->create(['student_id' => $s->id, 'status' => 'pending']);

        $this->assertStringContainsString('1 report is still unreviewed', $this->caseOf($s)['detail']);
    }

    public function test_it_does_not_mix_up_two_students(): void
    {
        $a = Student::factory()->create();
        $b = Student::factory()->create();
        $this->open($a, 'pending', null);

        $this->assertSame('unassigned', $this->caseOf($a)['key']);
        $this->assertSame('none', $this->caseOf($b)['key']);
    }

    public function test_the_student_page_and_the_risk_profile_both_show_it(): void
    {
        $c = $this->counselor();
        $s = Student::factory()->create();
        $r = $this->open($s, 'in_progress', $c->id);
        RiskAssessment::create(['student_id' => $s->id, 'risk_score' => 70, 'risk_level' => 'high', 'assessed_at' => now()]);

        $student = $this->actingAs($c)->get(route('admin.students.show', $s->id))->getContent();
        $risk = $this->actingAs($c)->get(route('admin.risk.show', $s->id))->getContent();

        foreach ([$student, $risk] as $html) {
            $this->assertStringContainsString('data-case-status="in_progress"', $html);
            $this->assertStringContainsString('View referral #' . $r->id, $html);
        }
    }

    public function test_the_page_shows_no_referral_link_when_there_is_no_case(): void
    {
        $c = $this->counselor();
        $s = Student::factory()->create();

        $html = $this->actingAs($c)->get(route('admin.students.show', $s->id))->getContent();

        $this->assertStringContainsString('data-case-status="none"', $html);
        $this->assertStringNotContainsString('View referral #', $html);
    }
}
