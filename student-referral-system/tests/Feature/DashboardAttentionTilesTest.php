<?php

namespace Tests\Feature;

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

    public function test_the_counselor_dashboard_shows_the_six_tiles_in_urgency_order(): void
    {
        $tiles = $this->tiles($this->counselor());

        $this->assertSame(['overdue', 'unassigned', 'reports', 'no_referral', 'rising', 'stale'], array_keys($tiles));
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

        $this->assertSame(['unassigned', 'reports', 'no_referral', 'rising', 'stale'], array_keys($tiles));
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

    public function test_the_strip_renders_on_both_dashboards_and_says_all_clear_when_empty(): void
    {
        $html = $this->actingAs($this->counselor())->get(route('counselor.dashboard'))->getContent();
        $this->assertStringContainsString('Needs your attention', $html);
        $this->assertStringContainsString('All clear', $html);
        foreach (['overdue', 'unassigned', 'reports', 'no_referral', 'rising', 'stale'] as $key) {
            $this->assertStringContainsString('data-attention="' . $key . '"', $html);
        }

        $admin = $this->actingAs($this->superAdmin())->get(route('admin.dashboard'))->getContent();
        $this->assertStringContainsString('Needs your attention', $admin);
    }

    public function test_the_strip_does_not_say_all_clear_when_something_needs_attention(): void
    {
        Referral::factory()->create(['status' => 'pending', 'counselor_id' => null]);

        $html = $this->actingAs($this->counselor())->get(route('counselor.dashboard'))->getContent();

        $this->assertStringNotContainsString('All clear', $html);
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
