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

    public function test_an_open_report_without_a_referral_says_so_and_offers_no_button(): void
    {
        $report = BehavioralReport::factory()->create(['status' => 'pending']);

        $html = $this->reportsPage($this->counselor())->getContent();

        $this->assertSame(1, substr_count($html, 'data-no-referral'));
        $this->assertStringContainsString('No referral', $html);
        $this->assertStringNotContainsString('data-quick-refer', $html);
        $this->assertStringNotContainsString('Create referral', $html, 'creating one is done on the report page');
        $this->assertStringNotContainsString(route('counselor.behavioral-reports.refer', $report->id), $html, 'no form posts to the refer route from the list');
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
        $this->assertStringNotContainsString('data-no-referral', $html);
    }

    public function test_a_resolved_report_without_a_referral_just_says_no_referral(): void
    {
        BehavioralReport::factory()->create(['status' => 'resolved']);

        $html = $this->reportsPage($this->counselor())->getContent();

        $this->assertStringNotContainsString('data-quick-refer', $html);
        $this->assertStringNotContainsString('data-linked-referral', $html);
        $this->assertSame(1, substr_count($html, 'data-no-referral'));
    }

    public function test_the_list_marks_each_row_correctly_when_reports_differ(): void
    {
        $linked = BehavioralReport::factory()->create(['status' => 'reviewed']);
        Referral::factory()->create(['behavioral_report_id' => $linked->id, 'student_id' => $linked->student_id]);
        BehavioralReport::factory()->create(['status' => 'pending']);
        BehavioralReport::factory()->create(['status' => 'resolved']);

        $html = $this->reportsPage($this->counselor())->getContent();

        $this->assertSame(1, substr_count($html, 'data-linked-referral'));
        $this->assertSame(2, substr_count($html, 'data-no-referral'));
        $this->assertSame(0, substr_count($html, 'data-quick-refer'));
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

    public function test_a_referral_is_still_created_from_the_report_page_and_then_shows_in_the_list(): void
    {
        \Illuminate\Support\Facades\Http::fake(['*/predict' => \Illuminate\Support\Facades\Http::response(['risk_level' => 'moderate', 'risk_score' => 55, 'recommended_seminar_tag' => 'general'], 200), '*' => \Illuminate\Support\Facades\Http::response([], 200)]);
        $c = $this->counselor();
        $report = BehavioralReport::factory()->create(['status' => 'pending']);

        $this->actingAs($c)->post(route('counselor.behavioral-reports.refer', $report->id))->assertSessionHas('success');

        $this->assertSame($c->id, Referral::where('behavioral_report_id', $report->id)->sole()->counselor_id);
        $this->assertStringContainsString('data-linked-referral', $this->reportsPage($c)->getContent());
    }

    public function test_the_report_page_is_where_a_referral_is_created(): void
    {
        $c = $this->counselor();
        $report = BehavioralReport::factory()->create(['status' => 'pending']);

        $list = $this->reportsPage($c)->getContent();
        $page = $this->actingAs($c)->get(route('counselor.behavioral-reports.show', $report->id))->getContent();

        $this->assertStringNotContainsString('Create referral', $list);
        $this->assertStringContainsString('Create referral from this report', $page);
        $this->assertStringContainsString('name="counselor_id"', $page, 'with a choice of who it is assigned to');
    }

    public function test_the_admin_report_list_keeps_its_own_issue_referral_link(): void
    {
        $super = User::factory()->create(['role' => 'super_admin']);
        BehavioralReport::factory()->create(['status' => 'pending']);

        $html = $this->actingAs($super)->get(route('admin.behavioral-reports.index'))->getContent();

        $this->assertStringContainsString('Issue Referral', $html);
    }

    // ── At-Risk rows ─────────────────────────────────────────────────────

    public function test_at_risk_rows_offer_only_details_no_one_click_referral(): void
    {
        // A referral is opened deliberately from the profile's Refer form (which warns about an
        // open case) or the bulk Assign Counselor action - not by a stray click in a list row.
        $high = Student::factory()->create();
        $this->assess($high, 'high', 90);
        $moderate = Student::factory()->create();
        $this->assess($moderate, 'moderate', 55);

        foreach ([$this->counselor(), User::factory()->create(['role' => 'super_admin'])] as $viewer) {
            $html = $this->actingAs($viewer)->get(route('admin.risk.index'))->getContent();

            $this->assertStringNotContainsString('data-quick-refer', $html);
            $this->assertStringNotContainsString('quickRefer', $html);
            $this->assertStringNotContainsString('x-ref="quickAction"', $html);
            $this->assertSame(2, substr_count($html, '</i> Details'), 'each row still has its Details button');
        }
    }

    public function test_the_bulk_assign_action_still_opens_referrals_for_selected_students(): void
    {
        $c = $this->counselor();
        $s = Student::factory()->create();
        $a = $this->assess($s, 'high', 88.5);

        $this->actingAs($c)->post(route('admin.risk.bulkAction'), [
            'assessment_ids' => [$a->id], 'action' => 'assign_counselor', 'assign_counselor_id' => $c->id,
        ])->assertSessionHas('success');

        $referral = Referral::where('student_id', $s->id)->sole();
        $this->assertSame($c->id, $referral->counselor_id);
        $this->assertSame('high', $referral->priority);
    }

    public function test_the_at_risk_page_still_offers_bulk_assign_and_the_profile_refer_form(): void
    {
        $s = Student::factory()->create();
        $this->assess($s, 'high', 90);
        $c = $this->counselor();

        $this->assertStringContainsString('Assign Counselor', $this->actingAs($c)->get(route('admin.risk.index'))->getContent());
        $this->assertStringContainsString('Refer Student to Guidance', $this->actingAs($c)->get(route('admin.risk.show', $s->id))->getContent());
    }
}
