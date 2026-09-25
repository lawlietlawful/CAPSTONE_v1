<?php

namespace Tests\Feature;

use App\Models\BehavioralReport;
use App\Models\Intervention;
use App\Models\Referral;
use App\Models\RiskAssessment;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The four stat cards follow the work: reports to review -> pending referrals
 * -> follow-ups due -> students at risk. Each card's number equals the total
 * on the list it links to. They replaced "Total Students", "Upcoming
 * Interventions" and "Reports Today", which were mostly zeros or not actionable.
 */
class DashboardCardsTest extends TestCase
{
    use RefreshDatabase;

    private function counselor(): User
    {
        return User::factory()->counselor()->create();
    }

    private function page(User $c)
    {
        return $this->actingAs($c)->get(route('counselor.dashboard'));
    }

    private function card(string $html, string $key): string
    {
        preg_match('#<a href="[^"]*" data-stat="' . $key . '".*?</a>#s', $html, $m);

        return preg_replace('/\s+/', ' ', strip_tags($m[0] ?? ''));
    }

    private function assess(Student $s, string $level, float $score = 60): RiskAssessment
    {
        return RiskAssessment::create(['student_id' => $s->id, 'risk_score' => $score, 'risk_level' => $level, 'assessed_at' => now()]);
    }

    public function test_the_cards_appear_in_workflow_order_and_the_old_ones_are_gone(): void
    {
        $html = $this->page($this->counselor())->getContent();

        $positions = array_map(fn ($k) => strpos($html, 'data-stat="' . $k . '"'), ['reports', 'referrals', 'followups', 'at-risk']);
        $this->assertNotContains(false, $positions);
        $sorted = $positions;
        sort($sorted);
        $this->assertSame($sorted, $positions);

        $this->assertStringNotContainsString('Total Students', $html);
        $this->assertStringNotContainsString('Reports Today', $html);
        $this->assertStringNotContainsString('Upcoming Interventions', $html);
    }

    public function test_reports_card_counts_pending_reports_and_new_today(): void
    {
        $c = $this->counselor();
        BehavioralReport::factory()->count(2)->create(['status' => 'pending', 'created_at' => now()]);
        BehavioralReport::factory()->create(['status' => 'pending', 'created_at' => now()->subDays(3)]);
        BehavioralReport::factory()->create(['status' => 'reviewed']);

        $card = $this->card($this->page($c)->getContent(), 'reports');

        $this->assertStringContainsString('3 Reports to Review', $card);
        $this->assertStringContainsString('2 new today', $card);
    }

    public function test_referrals_card_counts_pending_referrals_in_my_scope(): void
    {
        $c = $this->counselor();
        Referral::factory()->create(['status' => 'pending', 'counselor_id' => $c->id, 'created_at' => now()]);
        Referral::factory()->create(['status' => 'pending', 'counselor_id' => null, 'created_at' => now()->subDays(2)]);
        Referral::factory()->create(['status' => 'pending', 'counselor_id' => $this->counselor()->id]); // someone else's
        Referral::factory()->create(['status' => 'in_progress', 'counselor_id' => $c->id]);

        $card = $this->card($this->page($c)->getContent(), 'referrals');

        $this->assertStringContainsString('2 Pending Referrals', $card);
        $this->assertStringContainsString('1 new today', $card);
    }

    public function test_follow_ups_card_counts_today_plus_overdue_and_flags_the_overdue(): void
    {
        $c = $this->counselor();
        foreach ([today()->subDays(4), today()] as $date) {
            $r = Referral::factory()->create(['status' => 'in_progress', 'counselor_id' => $c->id]);
            Intervention::factory()->create(['referral_id' => $r->id, 'counselor_id' => $c->id, 'follow_up_date' => $date, 'outcome' => null]);
        }
        $later = Referral::factory()->create(['status' => 'in_progress', 'counselor_id' => $c->id]);
        Intervention::factory()->create(['referral_id' => $later->id, 'counselor_id' => $c->id, 'follow_up_date' => today()->addDays(3), 'outcome' => null]);
        $closed = Referral::factory()->create(['status' => 'resolved', 'counselor_id' => $c->id]);
        Intervention::factory()->create(['referral_id' => $closed->id, 'counselor_id' => $c->id, 'follow_up_date' => today()->subDay(), 'outcome' => null]);

        $card = $this->card($this->page($c)->getContent(), 'followups');

        $this->assertStringContainsString('2 Follow-ups Due', $card, 'overdue + today; not the later one or the closed case');
        $this->assertStringContainsString('1 overdue', $card);
    }

    public function test_follow_ups_card_shows_the_week_ahead_when_nothing_is_overdue(): void
    {
        $c = $this->counselor();
        $r = Referral::factory()->create(['status' => 'in_progress', 'counselor_id' => $c->id]);
        Intervention::factory()->create(['referral_id' => $r->id, 'counselor_id' => $c->id, 'follow_up_date' => today(), 'outcome' => null]);

        $card = $this->card($this->page($c)->getContent(), 'followups');

        $this->assertStringContainsString('1 Follow-ups Due', $card);
        $this->assertStringNotContainsString('overdue', $card);
        $this->assertStringContainsString('more this week', $card);
    }

    public function test_at_risk_card_counts_high_and_moderate_in_my_scope_and_shows_safety_flags(): void
    {
        $c = $this->counselor();
        $this->assess(Student::factory()->create(), 'high', 90);
        $this->assess(Student::factory()->create(), 'moderate', 55);
        $this->assess(Student::factory()->create(), 'low', 10);
        $theirs = Student::factory()->create();
        $this->assess($theirs, 'high', 91);
        Referral::factory()->create(['student_id' => $theirs->id, 'counselor_id' => $this->counselor()->id]);
        $flagged = Student::factory()->create();
        $this->assess($flagged, 'moderate', 61);
        Referral::factory()->create(['student_id' => $flagged->id, 'counselor_id' => $c->id, 'status' => 'pending', 'reason' => 'He threatened to stab a classmate.']);

        $card = $this->card($this->page($c)->getContent(), 'at-risk');

        $this->assertStringContainsString('3 Students at Risk', $card, 'the other counselor is not mine and the low student is not at risk');
        $this->assertStringContainsString('1 safety flag', $card);
        $this->assertStringNotContainsString('safety flags', $card);
    }

    public function test_at_risk_card_falls_back_to_the_high_risk_count_without_safety_flags(): void
    {
        $c = $this->counselor();
        $this->assess(Student::factory()->create(), 'high', 90);
        $this->assess(Student::factory()->create(), 'moderate', 55);

        $card = $this->card($this->page($c)->getContent(), 'at-risk');

        $this->assertStringContainsString('2 Students at Risk', $card);
        $this->assertStringContainsString('1 high risk', $card);
        $this->assertStringNotContainsString('safety', $card);
    }

    public function test_every_card_links_to_the_list_whose_total_it_shows(): void
    {
        $c = $this->counselor();
        BehavioralReport::factory()->count(2)->create(['status' => 'pending']);
        Referral::factory()->count(2)->create(['status' => 'pending', 'counselor_id' => $c->id]);
        $r = Referral::factory()->create(['status' => 'in_progress', 'counselor_id' => $c->id]);
        Intervention::factory()->create(['referral_id' => $r->id, 'counselor_id' => $c->id, 'follow_up_date' => today()->subDays(2), 'outcome' => null]);
        $this->assess(Student::factory()->create(), 'high', 90);
        $page = $this->page($c);

        $this->assertStringContainsString(route('counselor.behavioral-reports.index', ['status' => 'pending']), $page->getContent());
        $this->assertSame(2, $this->actingAs($c)->get(route('counselor.behavioral-reports.index', ['status' => 'pending']))->viewData('reports')->total());

        $this->assertSame($page->viewData('pendingReferralsCount'), $this->actingAs($c)->get(route('counselor.referrals.index', ['status' => 'pending']))->viewData('referrals')->total());

        $followUps = $this->actingAs($c)->get(route('counselor.interventions.followups'));
        $this->assertSame($page->viewData('followUpsDueCount'), $followUps->viewData('overdue')->total() + $followUps->viewData('dueToday')->count());

        $this->assertSame($page->viewData('atRiskCount'), $this->actingAs($c)->get(route('admin.risk.index', ['scope' => 'mine']))->viewData('assessments')->total());
    }

    public function test_the_cards_are_part_of_the_polled_refresh_partial(): void
    {
        BehavioralReport::factory()->create(['status' => 'pending']);

        $html = $this->actingAs($this->counselor())->get(route('counselor.dashboard.refresh'))->getContent();

        foreach (['reports', 'referrals', 'followups', 'at-risk'] as $key) {
            $this->assertStringContainsString('data-stat="' . $key . '"', $html);
        }
        $this->assertStringContainsString('1 Reports to Review', $this->card($html, 'reports'));
    }
}
