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
 * The "Needs your attention" strip on the dashboards. The rule these tests
 * protect: every tile's number equals the total on the page it links to.
 */
class DashboardAttentionTilesTest extends TestCase
{
    use RefreshDatabase;

    private function counselor(): User
    {
        return User::factory()->counselor()->create();
    }

    private function superAdmin(): User
    {
        return User::factory()->create(['role' => 'super_admin']);
    }

    private function assess(Student $s, string $level = 'high', float $score = 90, $at = null): RiskAssessment
    {
        return RiskAssessment::create(['student_id' => $s->id, 'risk_score' => $score, 'risk_level' => $level, 'assessed_at' => $at ?? now()]);
    }

    private function tiles(User $viewer, string $route = 'counselor.dashboard'): array
    {
        return collect($this->actingAs($viewer)->get(route($route))->viewData('attentionTiles'))->keyBy('key')->all();
    }

    /** A realistic mixed caseload for one counselor. */
    private function caseload(User $me): void
    {
        $other = $this->counselor();

        // Overdue follow-up (mine, open case) + one on a closed case that must not count.
        $open = Referral::factory()->create(['status' => 'in_progress', 'counselor_id' => $me->id]);
        Intervention::factory()->create(['referral_id' => $open->id, 'counselor_id' => $me->id, 'follow_up_date' => today()->subDays(4), 'outcome' => null]);
        $closed = Referral::factory()->create(['status' => 'resolved', 'counselor_id' => $me->id]);
        Intervention::factory()->create(['referral_id' => $closed->id, 'counselor_id' => $me->id, 'follow_up_date' => today()->subDays(4), 'outcome' => null]);

        // Unassigned open referral, and one owned by someone else.
        Referral::factory()->create(['status' => 'pending', 'counselor_id' => null]);
        Referral::factory()->create(['status' => 'pending', 'counselor_id' => $other->id]);

        // High risk with nothing open (in my scope: no referral at all).
        $this->assess(Student::factory()->create(), 'high', 90);

        // Rising and stale, both with an open referral of mine.
        $rising = Student::factory()->create();
        $this->assess($rising, 'moderate', 45, now()->subDays(3));
        $this->assess($rising, 'high', 75);
        Referral::factory()->create(['student_id' => $rising->id, 'status' => 'pending', 'counselor_id' => $me->id]);

        $stale = Student::factory()->create();
        $this->assess($stale, 'high', 88, now()->subDays(45));
        Referral::factory()->create(['student_id' => $stale->id, 'status' => 'in_progress', 'counselor_id' => $me->id]);

        // Someone else's student: must not count for me.
        $theirs = Student::factory()->create();
        $this->assess($theirs, 'high', 91, now()->subDays(60));
        Referral::factory()->create(['student_id' => $theirs->id, 'status' => 'pending', 'counselor_id' => $other->id]);
    }

    public function test_the_counselor_dashboard_offers_the_tiles_in_urgency_order(): void
    {
        $tiles = $this->tiles($this->counselor());

        $this->assertSame(['safety', 'overdue', 'unassigned', 'reports', 'no_referral', 'rising', 'stale'], array_keys($tiles));
    }

    public function test_the_counselor_counts_follow_the_caseload(): void
    {
        $me = $this->counselor();
        $this->caseload($me);

        $tiles = $this->tiles($me);

        $this->assertSame(1, $tiles['overdue']['count'], 'the closed case follow-up is not overdue');
        $this->assertSame(1, $tiles['unassigned']['count']);
        $this->assertSame(1, $tiles['no_referral']['count']);
        $this->assertSame(1, $tiles['rising']['count']);
        $this->assertSame(1, $tiles['stale']['count'], "another counselor's stale student is not mine");
    }

    public function test_every_tile_count_equals_the_total_on_the_page_it_links_to(): void
    {
        $me = $this->counselor();
        $this->caseload($me);
        $tiles = $this->tiles($me);

        $followUps = $this->actingAs($me)->get($tiles['overdue']['url'])->viewData('overdue')->total();
        $this->assertSame($tiles['overdue']['count'], $followUps);

        $referrals = $this->actingAs($me)->get($tiles['unassigned']['url'])->viewData('referrals')->total();
        $this->assertSame($tiles['unassigned']['count'], $referrals);

        foreach (['no_referral', 'rising', 'stale'] as $key) {
            $risk = $this->actingAs($me)->get($tiles[$key]['url'])->viewData('assessments')->total();
            $this->assertSame($tiles[$key]['count'], $risk, "tile '{$key}' must match the At-Risk list it opens");
        }
    }

    public function test_tile_links_carry_the_my_students_scope_for_a_counselor_only(): void
    {
        $tiles = $this->tiles($this->counselor());

        foreach (['no_referral', 'rising', 'stale'] as $key) {
            $this->assertStringContainsString('scope=mine', $tiles[$key]['url']);
            $this->assertStringContainsString('attention=' . $key, $tiles[$key]['url']);
        }
        $this->assertStringContainsString('assignment=unassigned', $tiles['unassigned']['url']);
        $this->assertStringContainsString('followups', $tiles['overdue']['url']);
    }

    public function test_the_admin_dashboard_is_school_wide_and_has_no_follow_up_tile(): void
    {
        $me = $this->counselor();
        $this->caseload($me);

        $tiles = $this->tiles($this->superAdmin(), 'admin.dashboard');

        $this->assertSame(['safety', 'unassigned', 'reports', 'no_referral', 'rising', 'stale'], array_keys($tiles));
        $this->assertSame(2, $tiles['stale']['count'], 'both stale students, whoever owns them');
        $this->assertStringNotContainsString('scope=mine', $tiles['stale']['url']);
        $this->assertStringContainsString('counselor_id=unassigned', $tiles['unassigned']['url']);
    }

    public function test_the_admin_tile_counts_match_the_pages_they_open(): void
    {
        $this->caseload($this->counselor());
        $admin = $this->superAdmin();
        $tiles = $this->tiles($admin, 'admin.dashboard');

        $this->assertSame($tiles['unassigned']['count'], $this->actingAs($admin)->get($tiles['unassigned']['url'])->viewData('referrals')->total());
        foreach (['no_referral', 'rising', 'stale'] as $key) {
            $this->assertSame($tiles[$key]['count'], $this->actingAs($admin)->get($tiles[$key]['url'])->viewData('assessments')->total(), $key);
        }
    }

    public function test_with_nothing_to_do_the_card_says_all_clear_and_lists_no_rows_on_both_dashboards(): void
    {
        foreach ([
            $this->actingAs($this->counselor())->get(route('counselor.dashboard'))->getContent(),
            $this->actingAs($this->superAdmin())->get(route('admin.dashboard'))->getContent(),
        ] as $html) {
            $this->assertStringContainsString('data-attention-clear', $html);
            $this->assertStringContainsString('All clear', $html);
            $this->assertStringContainsString('data-attention-card', $html, 'the card stays so the layout does not jump');
            $this->assertStringNotContainsString('data-attention="', $html, 'no grey zero rows');
        }
    }

    public function test_only_tiles_with_something_to_do_are_shown_most_urgent_first(): void
    {
        $me = $this->counselor();
        Referral::factory()->create(['status' => 'pending', 'counselor_id' => null]);           // unassigned
        $this->assess(Student::factory()->create(), 'high', 90);                                 // no open referral
        $html = $this->actingAs($me)->get(route('counselor.dashboard'))->getContent();

        $this->assertStringContainsString('Needs your attention', $html);
        $this->assertStringNotContainsString('All clear', $html);
        foreach (['unassigned', 'no_referral'] as $key) {
            $this->assertStringContainsString('data-attention="' . $key . '"', $html);
        }
        foreach (['overdue', 'reports', 'rising', 'stale'] as $key) {
            $this->assertStringNotContainsString('data-attention="' . $key . '"', $html, "{$key} is 0, so it is hidden");
        }
        $this->assertLessThan(strpos($html, 'data-attention="no_referral"'), strpos($html, 'data-attention="unassigned"'), 'urgency order is kept');
    }

    public function test_a_tile_appears_the_moment_its_count_goes_above_zero_and_disappears_at_zero(): void
    {
        $me = $this->counselor();
        $report = BehavioralReport::factory()->create(['status' => 'pending']);

        $this->assertStringContainsString('data-attention="reports"', $this->actingAs($me)->get(route('counselor.dashboard'))->getContent());

        $report->update(['status' => 'reviewed']);

        $html = $this->actingAs($me)->get(route('counselor.dashboard'))->getContent();
        $this->assertStringNotContainsString('data-attention="reports"', $html);
        $this->assertStringContainsString('All clear', $html);
    }

    public function test_the_admin_strip_shows_its_tiles_when_there_is_work(): void
    {
        Referral::factory()->create(['status' => 'pending', 'counselor_id' => null]);

        $html = $this->actingAs($this->superAdmin())->get(route('admin.dashboard'))->getContent();

        $this->assertStringContainsString('Needs your attention', $html);
        $this->assertStringContainsString('data-attention="unassigned"', $html);
    }

    public function test_the_counselor_attention_card_is_its_own_card_beside_upcoming_follow_ups(): void
    {
        Referral::factory()->create(['status' => 'pending', 'counselor_id' => null]);

        $html = $this->actingAs($this->counselor())->get(route('counselor.dashboard'))->getContent();

        $this->assertSame(1, substr_count($html, 'data-attention-card'));
        $this->assertStringNotContainsString('data-attention-strip', $html);
        $upcoming = strpos($html, 'Upcoming Follow-ups');
        $card = strpos($html, 'data-attention-card');
        $this->assertGreaterThan($upcoming, $card, 'the card follows Upcoming Follow-ups in the same row');
        $this->assertGreaterThan(strpos($html, 'Risk Distribution'), $upcoming, 'and both sit below the widgets column');

        // Same grid row: nothing but the wrapper div between the follow-ups card's end and this card.
        $between = substr($html, $upcoming, $card - $upcoming);
        $this->assertStringNotContainsString('<!-- ', $between);
        $this->assertStringNotContainsString('Risk Distribution', $between);
    }

    public function test_the_attention_card_is_not_inside_the_risk_distribution_card(): void
    {
        Referral::factory()->create(['status' => 'pending', 'counselor_id' => null]);

        foreach ([
            $this->actingAs($this->counselor())->get(route('counselor.dashboard'))->getContent(),
            $this->actingAs($this->superAdmin())->get(route('admin.dashboard'))->getContent(),
        ] as $html) {
            $distribution = strpos($html, 'Risk Distribution');
            $card = strpos($html, 'data-attention-card');
            $this->assertNotFalse($distribution);
            $this->assertNotFalse($card);
            $this->assertGreaterThan($distribution, $card);
            // The distribution card's closing markup comes before the attention card opens.
            $this->assertStringContainsString('HIGH', substr($html, $distribution, 2500));
            $segment = substr($html, $distribution, $card - $distribution);
            $this->assertGreaterThan(0, substr_count($segment, '</div>'));
        }
    }

    public function test_the_admin_attention_card_sits_under_risk_distribution_before_seminars(): void
    {
        Referral::factory()->create(['status' => 'pending', 'counselor_id' => null]);

        $html = $this->actingAs($this->superAdmin())->get(route('admin.dashboard'))->getContent();

        $this->assertSame(1, substr_count($html, 'data-attention-card'));
        $this->assertGreaterThan(strpos($html, 'Risk Distribution'), strpos($html, 'data-attention-card'));
        $this->assertLessThan(strpos($html, 'Upcoming Seminars'), strpos($html, 'data-attention-card'));
    }

    public function test_the_service_still_reports_every_tile_including_zeros(): void
    {
        // Hiding happens in the view; the data (and its consistency tests) stay complete.
        $tiles = $this->tiles($this->counselor());

        $this->assertSame(['safety', 'overdue', 'unassigned', 'reports', 'no_referral', 'rising', 'stale'], array_keys($tiles));
        $this->assertSame(0, array_sum(array_column($tiles, 'count')));
    }

    public function test_the_strip_is_part_of_the_polled_refresh_partial(): void
    {
        Referral::factory()->create(['status' => 'pending', 'counselor_id' => null]);

        $html = $this->actingAs($this->counselor())->get(route('counselor.dashboard.refresh'))->getContent();

        $this->assertStringContainsString('data-attention="unassigned"', $html);
    }

    // ── The filters the tiles rely on ────────────────────────────────────

    public function test_counselor_referrals_can_be_filtered_to_unassigned_open_cases(): void
    {
        $me = $this->counselor();
        $waiting = Referral::factory()->create(['status' => 'pending', 'counselor_id' => null]);
        Referral::factory()->create(['status' => 'resolved', 'counselor_id' => null]);
        Referral::factory()->create(['status' => 'pending', 'counselor_id' => $me->id]);

        $ids = $this->actingAs($me)->get(route('counselor.referrals.index', ['assignment' => 'unassigned']))
            ->viewData('referrals')->pluck('id')->all();

        $this->assertSame([$waiting->id], $ids);
    }

    public function test_counselor_referrals_can_be_filtered_to_my_own(): void
    {
        $me = $this->counselor();
        Referral::factory()->create(['status' => 'pending', 'counselor_id' => null]);
        $mine = Referral::factory()->create(['status' => 'pending', 'counselor_id' => $me->id]);

        $ids = $this->actingAs($me)->get(route('counselor.referrals.index', ['assignment' => 'mine']))
            ->viewData('referrals')->pluck('id')->all();

        $this->assertSame([$mine->id], $ids);
    }

    public function test_without_an_assignment_filter_the_referral_list_is_unchanged(): void
    {
        $me = $this->counselor();
        Referral::factory()->create(['status' => 'pending', 'counselor_id' => null]);
        Referral::factory()->create(['status' => 'pending', 'counselor_id' => $me->id]);
        Referral::factory()->create(['status' => 'pending', 'counselor_id' => $this->counselor()->id]);

        $this->assertSame(2, $this->actingAs($me)->get(route('counselor.referrals.index'))->viewData('referrals')->total());
    }

    public function test_admin_referrals_can_be_filtered_to_unassigned_and_the_dropdown_offers_it(): void
    {
        $admin = $this->superAdmin();
        $waiting = Referral::factory()->create(['status' => 'in_progress', 'counselor_id' => null]);
        Referral::factory()->create(['status' => 'resolved', 'counselor_id' => null]);
        Referral::factory()->create(['status' => 'pending', 'counselor_id' => $this->counselor()->id]);

        $page = $this->actingAs($admin)->get(route('admin.referrals.index', ['counselor_id' => 'unassigned']));

        $this->assertSame([$waiting->id], $page->viewData('referrals')->pluck('id')->all());
        $this->assertStringContainsString('value="unassigned" selected', preg_replace('/\s+/', ' ', $page->getContent()));
    }

    public function test_the_follow_ups_page_ignores_follow_ups_on_a_closed_case(): void
    {
        $me = $this->counselor();
        $closed = Referral::factory()->create(['status' => 'cancelled']);
        Intervention::factory()->create(['referral_id' => $closed->id, 'counselor_id' => $me->id, 'follow_up_date' => today()->subDays(3), 'outcome' => null]);
        $open = Referral::factory()->create(['status' => 'in_progress']);
        Intervention::factory()->create(['referral_id' => $open->id, 'counselor_id' => $me->id, 'follow_up_date' => today()->subDays(3), 'outcome' => null]);

        $page = $this->actingAs($me)->get(route('counselor.interventions.followups'));

        $this->assertSame(1, $page->viewData('overdue')->total());
    }

    public function test_the_follow_ups_page_ignores_a_superseded_follow_up(): void
    {
        $me = $this->counselor();
        $referral = Referral::factory()->create(['status' => 'in_progress']);
        Intervention::factory()->create(['referral_id' => $referral->id, 'counselor_id' => $me->id, 'follow_up_date' => today()->subDays(6), 'outcome' => null]);
        Intervention::factory()->create(['referral_id' => $referral->id, 'counselor_id' => $me->id, 'follow_up_date' => null, 'outcome' => 'improving']);

        $this->assertSame(0, $this->actingAs($me)->get(route('counselor.interventions.followups'))->viewData('overdue')->total());
    }
}
