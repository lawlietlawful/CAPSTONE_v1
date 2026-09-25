<?php

namespace Tests\Feature;

use App\Models\BehavioralReport;
use App\Models\Referral;
use App\Models\RiskAssessment;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Lists should let the counselor act, not just look: the Reports list shows
 * whether a referral exists and offers one-click "create referral", and
 * At-Risk rows without an open referral offer one-click "Open referral".
 */
class RowActionsTest extends TestCase
{
    use RefreshDatabase;

    private function counselor(): User
    {
        return User::factory()->counselor()->create();
    }

    private function assess(Student $s, string $level = 'high', float $score = 90): RiskAssessment
    {
        return RiskAssessment::create(['student_id' => $s->id, 'risk_score' => $score, 'risk_level' => $level, 'assessed_at' => now()]);
    }

    private function reportsPage(User $viewer)
    {
        return $this->actingAs($viewer)->get(route('counselor.behavioral-reports.index'));
    }

    // ── Reports list ─────────────────────────────────────────────────────

    public function test_an_open_report_without_a_referral_offers_one_click_create(): void
    {
        $report = BehavioralReport::factory()->create(['status' => 'pending']);

        $html = $this->reportsPage($this->counselor())->getContent();

        $this->assertSame(1, substr_count($html, 'data-quick-refer'));
        $this->assertStringContainsString(route('counselor.behavioral-reports.refer', $report->id), $html);
        $this->assertStringNotContainsString('data-linked-referral', $html);
    }

    public function test_a_report_with_a_referral_shows_the_referral_and_no_create_button(): void
    {
        $report = BehavioralReport::factory()->create(['status' => 'pending']);
        $referral = Referral::factory()->create(['behavioral_report_id' => $report->id, 'student_id' => $report->student_id, 'status' => 'in_progress']);

        $html = $this->reportsPage($this->counselor())->getContent();

        $this->assertSame(1, substr_count($html, 'data-linked-referral'));
        $this->assertStringContainsString(route('counselor.referrals.show', $referral->id), $html);
        $this->assertStringContainsString('#' . $referral->id, $html);
        $this->assertStringContainsString('In Progress', $html);
        $this->assertStringNotContainsString('data-quick-refer', $html);
    }

    public function test_a_resolved_report_without_a_referral_offers_neither(): void
    {
        BehavioralReport::factory()->create(['status' => 'resolved']);

        $html = $this->reportsPage($this->counselor())->getContent();

        $this->assertStringNotContainsString('data-quick-refer', $html);
        $this->assertStringNotContainsString('data-linked-referral', $html);
    }

    public function test_the_list_marks_each_row_correctly_when_reports_differ(): void
    {
        $linked = BehavioralReport::factory()->create(['status' => 'reviewed']);
        Referral::factory()->create(['behavioral_report_id' => $linked->id, 'student_id' => $linked->student_id]);
        BehavioralReport::factory()->create(['status' => 'pending']);
        BehavioralReport::factory()->create(['status' => 'resolved']);

        $html = $this->reportsPage($this->counselor())->getContent();

        $this->assertSame(1, substr_count($html, 'data-linked-referral'));
        $this->assertSame(1, substr_count($html, 'data-quick-refer'));
    }

    public function test_the_list_loads_the_linked_referral_up_front_not_one_query_per_row(): void
    {
        foreach (range(1, 4) as $i) {
            $r = BehavioralReport::factory()->create();
            Referral::factory()->create(['behavioral_report_id' => $r->id, 'student_id' => $r->student_id]);
        }

        $page = $this->reportsPage($this->counselor());

        $this->assertTrue($page->viewData('reports')->first()->relationLoaded('escalatedReferral'));
    }

    public function test_the_row_form_creates_a_referral_for_that_report(): void
    {
        \Illuminate\Support\Facades\Http::fake(['*/predict' => \Illuminate\Support\Facades\Http::response(['risk_level' => 'moderate', 'risk_score' => 55, 'recommended_seminar_tag' => 'general'], 200), '*' => \Illuminate\Support\Facades\Http::response([], 200)]);
        $c = $this->counselor();
        $report = BehavioralReport::factory()->create(['status' => 'pending']);

        $this->actingAs($c)->post(route('counselor.behavioral-reports.refer', $report->id))->assertSessionHas('success');

        $this->assertSame($c->id, Referral::where('behavioral_report_id', $report->id)->sole()->counselor_id);
        $this->assertStringContainsString('data-linked-referral', $this->reportsPage($c)->getContent());
    }

    public function test_the_admin_report_list_keeps_its_own_issue_referral_link(): void
    {
        $super = User::factory()->create(['role' => 'super_admin']);
        BehavioralReport::factory()->create(['status' => 'pending']);

        $html = $this->actingAs($super)->get(route('admin.behavioral-reports.index'))->getContent();

        $this->assertStringContainsString('Issue Referral', $html);
    }

    // ── At-Risk rows ─────────────────────────────────────────────────────

    public function test_high_and_moderate_students_without_an_open_referral_get_the_button(): void
    {
        $high = Student::factory()->create();
        $this->assess($high, 'high', 90);
        $moderate = Student::factory()->create();
        $this->assess($moderate, 'moderate', 55);

        $html = $this->actingAs($this->counselor())->get(route('admin.risk.index'))->getContent();

        $this->assertSame(2, substr_count($html, 'data-quick-refer'));
        $this->assertStringContainsString('quickRefer(', $html);
        $this->assertStringContainsString('x-ref="quickAction"', $html);
    }

    public function test_the_button_is_absent_when_a_referral_is_open_or_the_student_is_low_risk(): void
    {
        $covered = Student::factory()->create();
        $this->assess($covered, 'high', 90);
        Referral::factory()->create(['student_id' => $covered->id, 'status' => 'pending']);
        $low = Student::factory()->create();
        $this->assess($low, 'low', 15);
        $closed = Student::factory()->create();
        $this->assess($closed, 'moderate', 55);
        Referral::factory()->create(['student_id' => $closed->id, 'status' => 'resolved']);

        $html = $this->actingAs($this->counselor())->get(route('admin.risk.index'))->getContent();

        $this->assertSame(1, substr_count($html, 'data-quick-refer'), 'only the student whose referral is closed still lacks an open one');
    }

    public function test_the_button_is_not_offered_to_the_super_admin(): void
    {
        $this->assess(Student::factory()->create(), 'high', 90);
        $super = User::factory()->create(['role' => 'super_admin']);

        $this->assertStringNotContainsString('data-quick-refer', $this->actingAs($super)->get(route('admin.risk.index'))->getContent());
    }

    public function test_a_name_with_an_apostrophe_cannot_break_the_row_script(): void
    {
        $this->assess(Student::factory()->create(['first_name' => 'Ana', 'last_name' => "O'Brien"]), 'high', 90);

        $html = $this->actingAs($this->counselor())->get(route('admin.risk.index'))->getContent();

        // The name reaches the script as escaped JSON, never as a raw quote inside the handler.
        $needle = 'O' . chr(92) . 'u0027Brien, Ana';
        $this->assertMatchesRegularExpression('/@click="quickRefer' . chr(92) . '(' . chr(92) . 'd+, ' . preg_quote("'" . $needle . "'", '/') . chr(92) . ')"/', $html);
    }

    public function test_the_click_sends_exactly_what_the_bulk_action_needs_and_opens_a_referral_for_me(): void
    {
        $c = $this->counselor();
        $s = Student::factory()->create();
        $a = $this->assess($s, 'high', 88.5);

        // What the row's JavaScript submits: the one selected id, the action, and the signed-in counselor.
        $this->actingAs($c)->post(route('admin.risk.bulkAction'), [
            'assessment_ids' => [$a->id], 'action' => 'assign_counselor', 'assign_counselor_id' => $c->id,
        ])->assertSessionHas('success');

        $referral = Referral::where('student_id', $s->id)->sole();
        $this->assertSame($c->id, $referral->counselor_id);
        $this->assertSame('high', $referral->priority);

        // ...and the row no longer offers the button.
        $this->assertStringNotContainsString('data-quick-refer', $this->actingAs($c)->get(route('admin.risk.index'))->getContent());
    }
}
