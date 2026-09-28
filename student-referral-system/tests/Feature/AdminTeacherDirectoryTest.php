<?php

namespace Tests\Feature;

use App\Models\BehavioralReport;
use App\Models\Referral;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Admin\TeacherController@index: the teacher directory's pagination and its
 * Engagement column, which is derived (not stored) from each teacher's most
 * recent activity — a behavioral report filed or a referral raised,
 * whichever is more recent:
 *   - within 7 days   -> "highly_active" (green, "Highly Active")
 *   - within 30 days  -> "active"        (amber, "Active")
 *   - older, or never -> "inactive"      (red,   "Inactive")
 */
class AdminTeacherDirectoryTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->counselor()->create();
    }

    private function backdate(BehavioralReport|Referral $model, \DateTimeInterface $when): void
    {
        $model->created_at = $when;
        $model->save();
    }

    // ── Pagination ──────────────────────────────────────────────────────

    public function test_the_directory_shows_ten_teachers_per_page(): void
    {
        User::factory()->teacher()->count(11)->create();

        $response = $this->actingAs($this->admin())->get(route('admin.teachers.index'));

        $response->assertOk();
        $this->assertCount(10, $response->viewData('teachers'));
        $this->assertTrue($response->viewData('teachers')->hasPages());
    }

    public function test_a_second_page_is_reachable_and_shows_the_remainder(): void
    {
        User::factory()->teacher()->count(11)->create();

        $response = $this->actingAs($this->admin())
            ->get(route('admin.teachers.index', ['page' => 2]));

        $response->assertOk();
        $this->assertCount(1, $response->viewData('teachers'));
    }

    public function test_pagination_links_are_not_shown_when_everything_fits_on_one_page(): void
    {
        User::factory()->teacher()->count(3)->create();

        $html = $this->actingAs($this->admin())
            ->get(route('admin.teachers.index'))
            ->getContent();

        // hasPages() is false for <= per_page rows, so the links() partial
        // is never rendered at all — no empty pager bar left behind.
        $this->assertStringNotContainsString('rel="next"', $html);
    }

    // ── Engagement ────────────────────────────────────────────────────────

    public function test_a_teacher_active_within_seven_days_is_highly_active(): void
    {
        $teacher = User::factory()->teacher()->create();
        $this->backdate(BehavioralReport::factory()->create(['reported_by' => $teacher->id]), now()->subDays(3));

        $html = $this->actingAs($this->admin())->get(route('admin.teachers.index'))->getContent();

        $this->assertStringContainsString('Highly Active', $html);
        $this->assertStringNotContainsString('ti-moon', $html);
    }

    public function test_a_teacher_active_eight_to_thirty_days_ago_is_active(): void
    {
        $teacher = User::factory()->teacher()->create();
        $this->backdate(BehavioralReport::factory()->create(['reported_by' => $teacher->id]), now()->subDays(15));

        $html = $this->actingAs($this->admin())->get(route('admin.teachers.index'))->getContent();

        // "Active" alone is a substring of "Highly Active", so this checks
        // the amber tier's distinguishing icon class rather than the text.
        $this->assertStringContainsString('ti-activity', $html);
        $this->assertStringNotContainsString('Highly Active', $html);
    }

    public function test_a_teacher_with_no_activity_at_all_is_inactive(): void
    {
        User::factory()->teacher()->create();

        $html = $this->actingAs($this->admin())->get(route('admin.teachers.index'))->getContent();

        $this->assertStringContainsString('Inactive', $html);
    }

    public function test_a_teacher_inactive_for_over_thirty_days_is_inactive_not_active(): void
    {
        $teacher = User::factory()->teacher()->create();
        $this->backdate(BehavioralReport::factory()->create(['reported_by' => $teacher->id]), now()->subDays(45));

        $html = $this->actingAs($this->admin())->get(route('admin.teachers.index'))->getContent();

        $this->assertStringContainsString('Inactive', $html);
        $this->assertStringNotContainsString('ti-activity', $html);
        $this->assertStringNotContainsString('Highly Active', $html);
    }

    public function test_engagement_takes_the_more_recent_of_a_report_or_a_referral(): void
    {
        $teacher = User::factory()->teacher()->create();
        // The report is stale, but a referral three days ago keeps them highly active.
        $this->backdate(BehavioralReport::factory()->create(['reported_by' => $teacher->id]), now()->subDays(60));
        $this->backdate(Referral::factory()->create(['referred_by' => $teacher->id]), now()->subDays(3));

        $html = $this->actingAs($this->admin())->get(route('admin.teachers.index'))->getContent();

        $this->assertStringContainsString('Highly Active', $html);
    }

    public function test_each_teachers_engagement_is_based_on_only_their_own_activity(): void
    {
        // Guards the reported_by/referred_by scoping itself: a fresh report
        // filed by a *different* teacher must not make this one look active.
        $teacher = User::factory()->teacher()->create();
        $other = User::factory()->teacher()->create();
        $this->backdate(BehavioralReport::factory()->create(['reported_by' => $other->id]), now()->subDays(1));

        $html = $this->actingAs($this->admin())->get(route('admin.teachers.index'))->getContent();

        // Both rows render; the point is only $other is highly active.
        $this->assertSame(1, substr_count($html, 'Highly Active'));
    }
}
