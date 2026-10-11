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

    // ── Pending Activation summary card ──────────────────────────────────

    public function test_the_pending_activation_card_counts_teachers_with_no_activation_timestamp(): void
    {
        User::factory()->teacher()->count(2)->create(); // account_activated_at null by default
        User::factory()->teacher()->create(['account_activated_at' => now()]);

        $response = $this->actingAs($this->admin())->get(route('admin.teachers.index'));

        $response->assertOk();
        $this->assertSame(2, $response->viewData('pendingActivationCount'));
        $this->assertStringContainsString('Pending Activation', $response->getContent());
    }

    public function test_the_pending_activation_card_is_a_whole_directory_count_not_scoped_to_the_current_page(): void
    {
        // 12 unactivated teachers: more than one page (10/page), so the card
        // must still report the full count, not just what's on page 1.
        User::factory()->teacher()->count(12)->create();

        $response = $this->actingAs($this->admin())->get(route('admin.teachers.index'));

        $this->assertSame(12, $response->viewData('pendingActivationCount'));
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

    // ── Status filter ─────────────────────────────────────────────────────
    // Ties the "Pending Activation" summary card to an actual filter instead
    // of being just a count you then have to scan the table for by hand.

    public function test_status_pending_shows_only_unactivated_teachers(): void
    {
        $pending = User::factory()->teacher()->create(['name' => 'Pending Teacher']);
        $activated = User::factory()->teacher()->create(['name' => 'Activated Teacher', 'account_activated_at' => now()]);

        $response = $this->actingAs($this->admin())
            ->get(route('admin.teachers.index', ['status' => 'pending']));

        $names = $response->viewData('teachers')->pluck('name');
        $this->assertTrue($names->contains('Pending Teacher'));
        $this->assertFalse($names->contains('Activated Teacher'));
    }

    public function test_status_activated_shows_only_activated_teachers(): void
    {
        $pending = User::factory()->teacher()->create(['name' => 'Pending Teacher']);
        $activated = User::factory()->teacher()->create(['name' => 'Activated Teacher', 'account_activated_at' => now()]);

        $response = $this->actingAs($this->admin())
            ->get(route('admin.teachers.index', ['status' => 'activated']));

        $names = $response->viewData('teachers')->pluck('name');
        $this->assertTrue($names->contains('Activated Teacher'));
        $this->assertFalse($names->contains('Pending Teacher'));
    }

    public function test_no_status_param_shows_everyone(): void
    {
        User::factory()->teacher()->create(['name' => 'Pending Teacher']);
        User::factory()->teacher()->create(['name' => 'Activated Teacher', 'account_activated_at' => now()]);

        $names = $this->actingAs($this->admin())
            ->get(route('admin.teachers.index'))
            ->viewData('teachers')->pluck('name');

        $this->assertTrue($names->contains('Pending Teacher'));
        $this->assertTrue($names->contains('Activated Teacher'));
    }

    public function test_the_pending_activation_card_links_to_the_filtered_view(): void
    {
        $html = $this->actingAs($this->admin())
            ->get(route('admin.teachers.index'))
            ->getContent();

        $this->assertStringContainsString(route('admin.teachers.index', ['status' => 'pending']), $html);
    }

    // ── CSV export ──────────────────────────────────────────────────────
    // Used to report on behavioral reports alone — Referrals Filed and
    // Engagement, both shown on the directory/profile/print PDF, were
    // missing from the one surface meant to be taken offline.

    public function test_the_csv_export_includes_referrals_filed_and_engagement(): void
    {
        $teacher = User::factory()->teacher()->create(['name' => 'Carlos Ramos']);
        $this->backdate(
            \App\Models\Referral::factory()->count(2)->create(['referred_by' => $teacher->id])->first(),
            now()->subDays(3),
        );

        $response = $this->actingAs($this->admin())->get(route('admin.teachers.export'));
        $response->assertOk();

        $csv = $response->streamedContent();
        $this->assertStringContainsString('Referrals Filed', $csv);
        $this->assertStringContainsString('Engagement', $csv);

        $row = collect(explode("\n", $csv))->first(fn ($line) => str_contains($line, 'Carlos Ramos'));
        $this->assertNotNull($row);
        $fields = str_getcsv($row);
        // Teacher Name, Email, Employee ID, Reports Filed, Referrals Filed, Engagement, Joined Date
        $this->assertSame('2', $fields[4]);
        $this->assertSame('Highly Active', $fields[5]);
    }

    public function test_the_csv_export_reports_inactive_for_a_teacher_with_no_activity(): void
    {
        User::factory()->teacher()->create(['name' => 'Quiet Teacher']);

        $csv = $this->actingAs($this->admin())
            ->get(route('admin.teachers.export'))
            ->streamedContent();

        $row = collect(explode("\n", $csv))->first(fn ($line) => str_contains($line, 'Quiet Teacher'));
        $fields = str_getcsv($row);
        $this->assertSame('0', $fields[3]);
        $this->assertSame('0', $fields[4]);
        $this->assertSame('Inactive', $fields[5]);
    }
}
