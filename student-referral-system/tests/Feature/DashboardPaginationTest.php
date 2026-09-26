<?php

namespace Tests\Feature;

use App\Models\Referral;
use App\Models\RiskAssessment;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The Pending Referrals and High-Risk Watchlist cards show three rows at a
 * time. The page lives in the URL (?pending_page, ?watch_page), survives the
 * dashboard's 20-second refresh, and is clamped so an emptied page falls back.
 */
class DashboardPaginationTest extends TestCase
{
    use RefreshDatabase;

    private function counselor(): User
    {
        return User::factory()->counselor()->create();
    }

    /** $n pending referrals in the counselor's scope, newest first by id (older ones have older timestamps). */
    private function pending(int $n, ?int $counselorId = null): array
    {
        $ids = [];
        for ($i = 0; $i < $n; $i++) {
            $ids[] = Referral::factory()->create([
                'status' => 'pending', 'counselor_id' => $counselorId, 'created_at' => now()->subMinutes($n - $i),
            ])->id;
        }

        return array_reverse($ids); // newest first, the order the card shows
    }

    private function highs(int $n): array
    {
        $ids = [];
        for ($i = 0; $i < $n; $i++) {
            $s = Student::factory()->create();
            RiskAssessment::create(['student_id' => $s->id, 'risk_score' => 95 - $i, 'risk_level' => 'high', 'assessed_at' => now()]);
            $ids[] = $s->id;
        }

        return $ids; // highest score first
    }

    private function dash(User $c, array $query = [])
    {
        return $this->actingAs($c)->get(route('counselor.dashboard', $query));
    }

    // ── Pending Referrals ────────────────────────────────────────────────

    public function test_pending_referrals_show_three_per_page_newest_first(): void
    {
        $c = $this->counselor();
        $ids = $this->pending(7);

        $p1 = $this->dash($c)->viewData('recentPendingReferrals');
        $p2 = $this->dash($c, ['pending_page' => 2])->viewData('recentPendingReferrals');
        $p3 = $this->dash($c, ['pending_page' => 3])->viewData('recentPendingReferrals');

        $this->assertSame(array_slice($ids, 0, 3), $p1->pluck('id')->all());
        $this->assertSame(array_slice($ids, 3, 3), $p2->pluck('id')->all());
        $this->assertSame(array_slice($ids, 6, 3), $p3->pluck('id')->all());
        $this->assertSame(7, $p1->total());
        $this->assertSame(3, $p1->lastPage());
    }

    public function test_the_pending_card_only_pages_my_scope(): void
    {
        $c = $this->counselor();
        $this->pending(2, $c->id);
        $this->pending(2, null);
        $this->pending(3, $this->counselor()->id); // someone else's

        $page = $this->dash($c);

        $this->assertSame(4, $page->viewData('recentPendingReferrals')->total());
        $this->assertSame(4, $page->viewData('pendingReferralsCount'), 'the stat card still counts every pending referral');
    }

    public function test_a_page_past_the_end_or_a_junk_page_is_clamped(): void
    {
        $c = $this->counselor();
        $this->pending(7);

        $this->assertSame(3, $this->dash($c, ['pending_page' => 99])->viewData('recentPendingReferrals')->currentPage());
        $this->assertCount(1, $this->dash($c, ['pending_page' => 99])->viewData('recentPendingReferrals'), 'never an empty page');
        foreach ([0, -4, 'abc', ''] as $junk) {
            $this->assertSame(1, $this->dash($c, ['pending_page' => $junk])->viewData('recentPendingReferrals')->currentPage(), 'page ' . json_encode($junk));
        }
    }

    public function test_an_empty_card_still_renders_its_empty_state_with_no_pager(): void
    {
        $html = $this->dash($this->counselor())->getContent();

        $this->assertStringContainsString('No high-risk students flagged.', $html);
        $this->assertStringNotContainsString('data-card-pager', $html);
    }

    // ── Watchlist ────────────────────────────────────────────────────────

    public function test_the_watchlist_shows_three_per_page(): void
    {
        $c = $this->counselor();
        $ids = $this->highs(5);

        $p1 = $this->dash($c)->viewData('watchlistAssessments');
        $p2 = $this->dash($c, ['watch_page' => 2])->viewData('watchlistAssessments');

        $this->assertSame(array_slice($ids, 0, 3), $p1->pluck('student_id')->all());
        $this->assertSame(array_slice($ids, 3, 2), $p2->pluck('student_id')->all());
        $this->assertSame(5, $p1->total());
        $this->assertSame(2, $p1->lastPage());
    }

    public function test_the_watchlist_clamps_a_bad_page(): void
    {
        $c = $this->counselor();
        $this->highs(4);

        $this->assertSame(2, $this->dash($c, ['watch_page' => 50])->viewData('watchlistAssessments')->currentPage());
        $this->assertSame(1, $this->dash($c, ['watch_page' => 'x'])->viewData('watchlistAssessments')->currentPage());
    }

    // ── The two cards are independent but remember each other ────────────

    public function test_each_card_keeps_its_own_page_and_the_pagers_carry_the_others(): void
    {
        $c = $this->counselor();
        $this->pending(7);
        $this->highs(7);

        $page = $this->dash($c, ['pending_page' => 2, 'watch_page' => 3]);
        $html = $page->getContent();

        $this->assertSame(2, $page->viewData('recentPendingReferrals')->currentPage());
        $this->assertSame(3, $page->viewData('watchlistAssessments')->currentPage());

        preg_match('#data-card-pager="Pending referrals".*?</nav>#s', $html, $pending);
        preg_match('#data-card-pager="Watchlist".*?</nav>#s', $html, $watch);
        $this->assertStringContainsString('watch_page=3', html_entity_decode($pending[0]), "the pending pager keeps the watchlist's page");
        $this->assertStringContainsString('pending_page=2', html_entity_decode($watch[0]), "the watchlist pager keeps the pending page");
    }

    // ── The pager itself ─────────────────────────────────────────────────

    public function test_the_pager_shows_range_numbers_and_disabled_previous_on_page_one(): void
    {
        $this->pending(7);

        $html = $this->dash($this->counselor())->getContent();
        preg_match('#data-card-pager="Pending referrals".*?</nav>#s', $html, $m);
        $pager = preg_replace('/\s+/', ' ', $m[0]);

        $this->assertStringContainsString('1&ndash;3 of 7', preg_replace('/\s+/', ' ', $html));
        $this->assertStringContainsString('aria-current="page">1</span>', $pager);
        $this->assertStringContainsString('aria-disabled="true"', $pager, 'previous is disabled on page one');
        $this->assertStringContainsString('rel="next"', $pager);
        $this->assertStringNotContainsString('rel="prev"', $pager);
    }

    public function test_the_last_page_disables_next_and_shows_the_remainder(): void
    {
        $this->pending(7);

        $html = $this->dash($this->counselor(), ['pending_page' => 3])->getContent();
        preg_match('#data-card-pager="Pending referrals".*?</nav>#s', $html, $m);

        $this->assertStringContainsString('7&ndash;7 of 7', preg_replace('/\s+/', ' ', $html));
        $this->assertStringContainsString('rel="prev"', $m[0]);
        $this->assertStringNotContainsString('rel="next"', $m[0]);
    }

    public function test_three_or_fewer_items_get_no_pager(): void
    {
        $c = $this->counselor();
        $this->pending(3);
        $this->highs(2);

        $this->assertStringNotContainsString('data-card-pager', $this->dash($c)->getContent());
    }

    public function test_many_pages_collapse_to_a_current_over_last_indicator(): void
    {
        $this->pending(16); // 6 pages

        $html = $this->dash($this->counselor(), ['pending_page' => 2])->getContent();
        preg_match('#data-card-pager="Pending referrals".*?</nav>#s', $html, $m);

        $this->assertStringContainsString('2 / 6', preg_replace('/\s+/', ' ', $m[0]));
        $this->assertStringNotContainsString('aria-label="Page 5"', $m[0]);
    }

    public function test_pager_links_point_at_the_dashboard_not_the_refresh_endpoint(): void
    {
        $this->pending(7);

        $html = $this->dash($this->counselor())->getContent();
        preg_match('#data-card-pager="Pending referrals".*?</nav>#s', $html, $m);

        $this->assertStringContainsString(route('counselor.dashboard') . '?pending_page=2', html_entity_decode($m[0]));
        $this->assertStringNotContainsString('/refresh', $m[0]);
        $this->assertStringContainsString('data-dash-page', $m[0]);
    }

    // ── The 20-second refresh keeps the page ─────────────────────────────

    public function test_the_refresh_endpoint_honours_the_requested_pages(): void
    {
        $c = $this->counselor();
        $ids = $this->pending(7);

        $page = $this->actingAs($c)->get(route('counselor.dashboard.refresh', ['pending_page' => 2]));

        $page->assertOk();
        $this->assertSame(array_slice($ids, 3, 3), $page->viewData('recentPendingReferrals')->pluck('id')->all());
        $this->assertStringContainsString('data-card-pager="Pending referrals"', $page->getContent());

        // Its own pager links go back to the dashboard URL, never to the refresh endpoint.
        preg_match_all('#href="([^"]*pending_page=[^"]*)"#', $page->getContent(), $links);
        $this->assertNotEmpty($links[1]);
        foreach ($links[1] as $href) {
            $this->assertStringStartsWith(route('counselor.dashboard'), html_entity_decode($href));
            $this->assertStringNotContainsString('/refresh', $href);
        }
    }

    public function test_risk_distribution_sits_in_the_left_column_to_balance_the_two_columns(): void
    {
        $html = $this->dash($this->counselor())->getContent();

        $itinerary = strpos($html, "Today's Itinerary");
        $distribution = strpos($html, 'Risk Distribution');
        $watchlist = strpos($html, 'High-Risk Watchlist');
        $activity = strpos($html, 'Recent Activity');

        // Left column: Pending Referrals, Today's Itinerary, Risk Distribution. Right: Watchlist, Recent Activity.
        $this->assertNotFalse($itinerary);
        $this->assertGreaterThan($itinerary, $distribution, 'under the itinerary');
        $this->assertLessThan($watchlist, $distribution, 'before the right column starts');
        $this->assertLessThan($activity, $watchlist);
        $this->assertSame(1, substr_count($html, '</i> Risk Distribution'), 'the card is still rendered exactly once (an HTML comment also names it)');
    }

    public function test_the_dashboard_script_remembers_the_page_and_pages_in_place(): void
    {
        $html = $this->dash($this->counselor())->getContent();

        $this->assertStringContainsString('refreshUrl + search', $html);
        $this->assertStringContainsString('let query = window.location.search', $html);
        $this->assertStringContainsString("a[data-dash-page]", $html);
        $this->assertStringContainsString('history.replaceState', $html);
        $this->assertStringContainsString('loadBody(query)', $html, 'the timed refresh re-requests the current page');
    }

    public function test_the_refresh_still_guards_against_a_login_redirect(): void
    {
        $html = $this->dash($this->counselor())->getContent();

        $this->assertStringContainsString('r.redirected', $html);
        $this->assertStringContainsString('window.location.reload()', $html);
    }

    public function test_a_hostile_page_value_is_treated_as_a_number_and_clamped(): void
    {
        $c = $this->counselor();
        $this->pending(4); // 2 pages

        $page = $this->dash($c, ['pending_page' => '2; DROP TABLE referrals', 'watch_page' => ['x' => 1]]);

        $page->assertOk();
        $this->assertSame(2, $page->viewData('recentPendingReferrals')->currentPage(), 'read as the number 2, the rest ignored');
        $this->assertSame(1, $page->viewData('watchlistAssessments')->currentPage());
        $this->assertSame(4, Referral::count(), 'nothing was touched');
    }
}
