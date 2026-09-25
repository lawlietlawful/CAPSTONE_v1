<?php

namespace Tests\Feature;

use App\Models\BehavioralReport;
use App\Models\Intervention;
use App\Models\Referral;
use App\Models\RiskAssessment;
use App\Models\Student;
use App\Models\User;
use App\Support\NavBadges;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The counselor's sidebar: ordered by the work (check risk, review reports,
 * work referrals, log interventions, look students up), with count pills, and
 * identical on every page. It used to be copied into two layouts, so the
 * links (and where "Behavioral Reports" led) depended on the page you were on.
 */
class CounselorSidebarTest extends TestCase
{
    use RefreshDatabase;

    private function counselor(): User
    {
        return User::factory()->counselor()->create();
    }

    private function nav(User $viewer, string $url): string
    {
        $html = $this->actingAs($viewer)->get($url)->getContent();
        preg_match('#<nav.*?</nav>#s', $html, $m);

        return $m[0] ?? '';
    }

    private function hrefs(string $nav): array
    {
        preg_match_all('#<a href="([^"]+)"#', $nav, $m);

        return array_map(fn ($u) => parse_url($u, PHP_URL_PATH), $m[1]);
    }

    // ── Order and content ────────────────────────────────────────────────

    public function test_the_links_follow_the_order_of_the_work(): void
    {
        $paths = $this->hrefs($this->nav($this->counselor(), route('counselor.dashboard')));

        $this->assertSame([
            '/counselor/dashboard',
            '/admin/risk',
            '/counselor/behavioral-reports',
            '/counselor/referrals',
            '/counselor/interventions',
            '/admin/students',
            '/admin/teachers',
            '/admin/users',
            '/admin/courses',
            '/counselor/seminars',
        ], $paths);
    }

    public function test_the_section_headings_are_main_counseling_services_records_and_management(): void
    {
        $nav = $this->nav($this->counselor(), route('counselor.dashboard'));

        $positions = array_map(fn ($h) => strpos($nav, $h), ['Main', 'Counseling Services', 'Records', 'Management']);

        $this->assertNotContains(false, $positions);
        $sorted = $positions;
        sort($sorted);
        $this->assertSame($sorted, $positions, 'headings appear in this order');
    }

    // ── One sidebar, whichever layout the page uses ──────────────────────

    public function test_the_sidebar_is_identical_on_counselor_and_admin_layout_pages(): void
    {
        $c = $this->counselor();
        $s = Student::factory()->create();
        RiskAssessment::create(['student_id' => $s->id, 'risk_score' => 80, 'risk_level' => 'high', 'assessed_at' => now()]);

        $reference = $this->hrefs($this->nav($c, route('counselor.dashboard')));   // layouts.counselor

        foreach ([route('admin.risk.index'), route('admin.students.index'), route('admin.students.show', $s->id), route('admin.risk.show', $s->id)] as $url) {
            $this->assertSame($reference, $this->hrefs($this->nav($c, $url)), "sidebar on {$url}");
        }
    }

    public function test_behavioral_reports_leads_to_the_counselor_screen_from_every_page(): void
    {
        $c = $this->counselor();

        foreach ([route('counselor.dashboard'), route('admin.risk.index'), route('admin.students.index')] as $url) {
            $this->assertContains('/counselor/behavioral-reports', $this->hrefs($this->nav($c, $url)), $url);
            $this->assertNotContains('/admin/behavioral-reports', $this->hrefs($this->nav($c, $url)), $url);
        }
    }

    public function test_the_active_link_is_highlighted_on_both_layouts(): void
    {
        $c = $this->counselor();

        $risk = $this->nav($c, route('admin.risk.index'));
        $this->assertMatchesRegularExpression('#href="[^"]*/admin/risk"\s+class="[^"]*\bactive\b#', $risk);

        $dash = $this->nav($c, route('counselor.dashboard'));
        $this->assertMatchesRegularExpression('#href="[^"]*/counselor/dashboard"\s+class="[^"]*\bactive\b#', $dash);
    }

    public function test_the_super_admin_keeps_their_own_sidebar(): void
    {
        $super = User::factory()->create(['role' => 'super_admin']);

        $nav = $this->nav($super, route('admin.dashboard'));

        $this->assertStringContainsString('System', $nav);
        $this->assertStringContainsString('Analytics', $nav);
        $this->assertStringContainsString('SMS Logs', $nav);
        $this->assertStringNotContainsString('Records', $nav);
        $this->assertStringNotContainsString('data-nav-badge', $nav);
        $this->assertSame([], NavBadges::for($super));
    }

    // ── Badges ───────────────────────────────────────────────────────────

    public function test_with_nothing_waiting_there_are_no_badges(): void
    {
        $this->assertStringNotContainsString('data-nav-badge', $this->nav($this->counselor(), route('counselor.dashboard')));
    }

    public function test_each_badge_shows_its_count_on_its_own_link(): void
    {
        $c = $this->counselor();
        BehavioralReport::factory()->count(3)->create(['status' => 'pending']);
        Referral::factory()->count(2)->create(['status' => 'pending', 'counselor_id' => $c->id]);
        $overdueReferral = Referral::factory()->create(['status' => 'in_progress', 'counselor_id' => $c->id]);
        Intervention::factory()->create(['referral_id' => $overdueReferral->id, 'counselor_id' => $c->id, 'follow_up_date' => today()->subDays(2), 'outcome' => null]);
        $flagged = Student::factory()->create();
        RiskAssessment::create(['student_id' => $flagged->id, 'risk_score' => 61, 'risk_level' => 'moderate', 'assessed_at' => now()]);
        Referral::factory()->create(['student_id' => $flagged->id, 'status' => 'pending', 'counselor_id' => $c->id, 'reason' => 'He threatened to stab a classmate.']);

        $nav = preg_replace('/\s+/', ' ', $this->nav($c, route('counselor.dashboard')));

        $this->assertMatchesRegularExpression('#/admin/risk"[^>]*> <i[^>]*></i> At-Risk Students <span[^>]*data-nav-badge> 1 </span>#', $nav);
        $this->assertMatchesRegularExpression('#/counselor/behavioral-reports"[^>]*> <i[^>]*></i> Behavioral Reports <span[^>]*data-nav-badge> 3 </span>#', $nav);
        $this->assertMatchesRegularExpression('#/counselor/referrals"[^>]*> <i[^>]*></i> Referrals <span[^>]*data-nav-badge> 3 </span>#', $nav);
        $this->assertMatchesRegularExpression('#/counselor/interventions"[^>]*> <i[^>]*></i> Interventions <span[^>]*data-nav-badge> 1 </span>#', $nav);
    }

    public function test_badges_appear_on_admin_layout_pages_too(): void
    {
        $c = $this->counselor();
        BehavioralReport::factory()->count(2)->create(['status' => 'pending']);

        $this->assertStringContainsString('data-nav-badge', $this->nav($c, route('admin.students.index')));
        $this->assertStringContainsString('data-nav-badge', $this->nav($c, route('admin.risk.index')));
    }

    public function test_a_large_count_is_capped(): void
    {
        BehavioralReport::factory()->count(105)->create(['status' => 'pending']);

        $this->assertStringContainsString('99+', $this->nav($this->counselor(), route('counselor.dashboard')));
    }

    public function test_a_badge_disappears_when_the_work_is_done(): void
    {
        $c = $this->counselor();
        $report = BehavioralReport::factory()->create(['status' => 'pending']);
        $this->assertStringContainsString('data-nav-badge', $this->nav($c, route('counselor.dashboard')));

        $report->update(['status' => 'reviewed']);

        $this->assertStringNotContainsString('data-nav-badge', $this->nav($c, route('counselor.dashboard')));
    }

    public function test_the_badge_numbers_always_equal_the_dashboard_they_mirror(): void
    {
        $c = $this->counselor();
        BehavioralReport::factory()->count(4)->create(['status' => 'pending']);
        Referral::factory()->count(3)->create(['status' => 'pending', 'counselor_id' => null]);
        Referral::factory()->create(['status' => 'pending', 'counselor_id' => $this->counselor()->id]); // someone else's: not counted
        $r = Referral::factory()->create(['status' => 'in_progress', 'counselor_id' => $c->id]);
        Intervention::factory()->count(1)->create(['referral_id' => $r->id, 'counselor_id' => $c->id, 'follow_up_date' => today()->subDay(), 'outcome' => null]);
        $s = Student::factory()->create();
        RiskAssessment::create(['student_id' => $s->id, 'risk_score' => 60, 'risk_level' => 'moderate', 'assessed_at' => now()]);
        Referral::factory()->create(['student_id' => $s->id, 'status' => 'pending', 'counselor_id' => $c->id, 'reason' => 'Brought a knife to school.']);

        $badges = NavBadges::for($c);
        $dash = $this->actingAs($c)->get(route('counselor.dashboard'));
        $tiles = collect($dash->viewData('attentionTiles'))->keyBy('key');

        $this->assertSame($tiles['safety']['count'], $badges['risk']);
        $this->assertSame($tiles['reports']['count'], $badges['reports']);
        $this->assertSame($tiles['overdue']['count'], $badges['interventions']);
        $this->assertSame($dash->viewData('pendingReferralsCount'), $badges['referrals']);
    }

    public function test_only_the_counselor_role_gets_badges(): void
    {
        $this->assertSame([], NavBadges::for(User::factory()->teacher()->create()));
        $this->assertSame([], NavBadges::for(null));
        $this->assertSame(['risk', 'reports', 'referrals', 'interventions'], array_keys(NavBadges::for($this->counselor())));
    }
}
