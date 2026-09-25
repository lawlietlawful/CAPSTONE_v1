<?php

namespace Tests\Feature;

use App\Models\Intervention;
use App\Models\Referral;
use App\Models\RiskAssessment;
use App\Models\SmsLog;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Findings from the Dashboard audit: the Admin dashboard could 500 on a
 * resolved referral with no resolved_at, month labels overflowed on the
 * 29th-31st, the Counselor follow-up widgets ignored case status, and the two
 * dashboards and the At-Risk page disagreed about who counts as at risk.
 */
class DashboardAccuracyTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        return User::factory()->create(['role' => 'super_admin']);
    }

    private function counselor(): User
    {
        return User::factory()->counselor()->create();
    }

    private function assess(Student $s, string $level = 'high', float $score = 90, $at = null): RiskAssessment
    {
        return RiskAssessment::create(['student_id' => $s->id, 'risk_score' => $score, 'risk_level' => $level, 'assessed_at' => $at ?? now()]);
    }

    private function counselorDash(User $c)
    {
        return $this->actingAs($c)->get(route('counselor.dashboard'));
    }

    private function followUp(User $c, string $referralStatus = 'in_progress', array $extra = []): Intervention
    {
        $referral = Referral::factory()->create(['status' => $referralStatus]);

        return Intervention::factory()->create(array_merge([
            'referral_id' => $referral->id, 'counselor_id' => $c->id, 'outcome' => null, 'follow_up_date' => today()->subDays(3),
        ], $extra));
    }

    // ── Admin dashboard ──────────────────────────────────────────────────

    public function test_a_resolved_referral_without_resolved_at_does_not_crash_the_admin_dashboard(): void
    {
        Referral::factory()->create(['status' => 'resolved', 'resolved_at' => null]);

        $this->actingAs($this->superAdmin())->get(route('admin.dashboard'))->assertOk()->assertSee('marked as');
    }

    public function test_an_sms_marked_sent_without_sent_at_does_not_crash_the_admin_dashboard(): void
    {
        $student = Student::factory()->create();
        SmsLog::create(['student_id' => $student->id, 'recipient_name' => 'P', 'recipient_number' => '0917', 'recipient_type' => 'parent', 'message' => 'm', 'status' => 'sent', 'sent_at' => null]);

        $this->actingAs($this->superAdmin())->get(route('admin.dashboard'))->assertOk()->assertSee('SMS sent to parent');
    }

    public function test_admin_month_labels_are_the_last_six_months_on_the_31st(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 31)->setTime(10, 0));

        $labels = $this->actingAs($this->superAdmin())->get(route('admin.dashboard'))->viewData('monthLabels')->all();

        $this->assertSame(['May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct'], $labels);
    }

    public function test_analytics_month_labels_are_the_last_six_months_on_the_31st(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 31)->setTime(10, 0));

        $labels = $this->actingAs($this->superAdmin())->get(route('admin.analytics.index'))->viewData('trendChartData')['labels'];

        $this->assertSame(['May 2026', 'Jun 2026', 'Jul 2026', 'Aug 2026', 'Sep 2026', 'Oct 2026'], $labels);
    }

    public function test_the_activity_feed_does_not_call_a_now_low_student_high_risk(): void
    {
        $s = Student::factory()->create();
        $this->assess($s, 'high', 90, now()->subDays(20));
        $this->assess($s, 'low', 15, now());

        $html = $this->actingAs($this->superAdmin())->get(route('admin.dashboard'))->getContent();

        $this->assertStringNotContainsString('flagged as <strong>High Risk', $html);
    }

    public function test_the_activity_feed_still_shows_a_student_who_is_high_risk_now(): void
    {
        $this->assess(Student::factory()->create(), 'high', 90);

        $html = $this->actingAs($this->superAdmin())->get(route('admin.dashboard'))->getContent();

        $this->assertStringContainsString('flagged as <strong>High Risk', $html);
    }

    public function test_new_flags_today_counts_students_not_assessments(): void
    {
        $s = Student::factory()->create();
        $this->assess($s, 'high', 90);
        $this->assess($s, 'high', 92);

        $this->assertSame(0, $this->actingAs($this->superAdmin())->get(route('admin.dashboard'))->viewData('newFlagsToday'), 'the second high assessment is not a NEW flag');
    }

    public function test_a_student_who_just_became_high_is_a_new_flag(): void
    {
        $up = Student::factory()->create();
        $this->assess($up, 'moderate', 55, now()->subDay());
        $this->assess($up, 'high', 80);

        $first = Student::factory()->create();
        $this->assess($first, 'high', 90);

        $alreadyHigh = Student::factory()->create();
        $this->assess($alreadyHigh, 'high', 85, now()->subDay());
        $this->assess($alreadyHigh, 'high', 88);

        $this->assertSame(2, $this->actingAs($this->superAdmin())->get(route('admin.dashboard'))->viewData('newFlagsToday'));
    }

    public function test_the_two_dashboards_count_the_same_active_students(): void
    {
        Student::factory()->create(['status' => 'active']);
        Student::factory()->create(['status' => 'graduated']);
        Student::factory()->create(['status' => 'transferred']);

        $admin = $this->actingAs($this->superAdmin())->get(route('admin.dashboard'))->viewData('totalStudents');
        $counselor = $this->counselorDash($this->counselor())->viewData('totalStudents');

        $this->assertSame(1, $admin);
        $this->assertSame($admin, $counselor);
    }

    // ── Counselor follow-ups ─────────────────────────────────────────────

    public function test_an_active_overdue_follow_up_is_listed(): void
    {
        $c = $this->counselor();
        $this->followUp($c);

        $page = $this->counselorDash($c);

        $this->assertSame(1, $page->viewData('overdueInterventionsCount'));
        $this->assertCount(1, $page->viewData('overdueInterventions'));
    }

    public function test_follow_ups_on_a_closed_case_are_not_overdue_today_or_upcoming(): void
    {
        $c = $this->counselor();
        foreach (['resolved', 'cancelled'] as $status) {
            $this->followUp($c, $status, ['follow_up_date' => today()->subDays(5)]);
            $this->followUp($c, $status, ['follow_up_date' => today()]);
            $this->followUp($c, $status, ['follow_up_date' => today()->addDays(4)]);
        }

        $page = $this->counselorDash($c);

        $this->assertSame(0, $page->viewData('overdueInterventionsCount'));
        $this->assertSame(0, $page->viewData('upcomingInterventionsCount'));
        $this->assertCount(0, $page->viewData('todaysInterventions'));
        $this->assertSame(0, $page->viewData('interventionsDueThisWeek'));
    }

    public function test_a_session_marked_resolved_is_not_overdue(): void
    {
        $c = $this->counselor();
        $this->followUp($c, 'in_progress', ['outcome' => 'resolved']);

        $this->assertSame(0, $this->counselorDash($c)->viewData('overdueInterventionsCount'));
    }

    public function test_a_session_with_an_improving_outcome_still_counts_as_overdue(): void
    {
        $c = $this->counselor();
        $this->followUp($c, 'in_progress', ['outcome' => 'improving']);

        $this->assertSame(1, $this->counselorDash($c)->viewData('overdueInterventionsCount'));
    }

    public function test_a_follow_up_superseded_by_a_newer_session_is_not_upcoming_today_or_overdue(): void
    {
        $c = $this->counselor();
        $referral = Referral::factory()->create(['status' => 'in_progress']);
        foreach ([today()->subDays(2), today(), today()->addDays(5)] as $date) {
            Intervention::factory()->create(['referral_id' => $referral->id, 'counselor_id' => $c->id, 'follow_up_date' => $date, 'outcome' => null]);
            Intervention::factory()->create(['referral_id' => $referral->id, 'counselor_id' => $c->id, 'follow_up_date' => null, 'outcome' => 'improving']);
        }

        $page = $this->counselorDash($c);

        $this->assertSame(0, $page->viewData('overdueInterventionsCount'));
        $this->assertSame(0, $page->viewData('upcomingInterventionsCount'));
        $this->assertCount(0, $page->viewData('todaysInterventions'));
    }

    public function test_the_newest_session_carrying_a_follow_up_is_what_counts(): void
    {
        $c = $this->counselor();
        $referral = Referral::factory()->create(['status' => 'in_progress']);
        Intervention::factory()->create(['referral_id' => $referral->id, 'counselor_id' => $c->id, 'follow_up_date' => today()->subDays(9), 'outcome' => null]);
        Intervention::factory()->create(['referral_id' => $referral->id, 'counselor_id' => $c->id, 'follow_up_date' => today()->addDays(6), 'outcome' => null]);

        $page = $this->counselorDash($c);

        $this->assertSame(0, $page->viewData('overdueInterventionsCount'), 'the old due date was superseded');
        $this->assertSame(1, $page->viewData('upcomingInterventionsCount'));
    }

    public function test_todays_and_upcoming_follow_ups_appear_in_their_own_lists(): void
    {
        $c = $this->counselor();
        $this->followUp($c, 'pending', ['follow_up_date' => today()]);
        $this->followUp($c, 'pending', ['follow_up_date' => today()->addDays(3)]);

        $page = $this->counselorDash($c);

        $this->assertCount(1, $page->viewData('todaysInterventions'));
        $this->assertCount(1, $page->viewData('upcomingInterventions'));
        $this->assertSame(2, $page->viewData('upcomingInterventionsCount'), 'the card counts today plus later');
    }

    public function test_another_counselors_follow_ups_are_not_shown(): void
    {
        $mine = $this->counselor();
        $this->followUp($this->counselor());

        $this->assertSame(0, $this->counselorDash($mine)->viewData('overdueInterventionsCount'));
    }

    // ── One definition of "at risk" ──────────────────────────────────────

    public function test_the_watchlist_and_distribution_match_the_at_risk_pages_my_students_view(): void
    {
        $c = $this->counselor();
        $other = $this->counselor();

        $noReferral = Student::factory()->create();
        $this->assess($noReferral, 'high', 90);
        $mine = Student::factory()->create();
        $this->assess($mine, 'moderate', 55);
        Referral::factory()->create(['student_id' => $mine->id, 'counselor_id' => $c->id]);
        $theirs = Student::factory()->create();
        $this->assess($theirs, 'high', 91);
        Referral::factory()->create(['student_id' => $theirs->id, 'counselor_id' => $other->id]);

        $dash = $this->counselorDash($c);
        $risk = $this->actingAs($c)->get(route('admin.risk.index', ['scope' => 'mine']));

        $this->assertSame($risk->viewData('totalAssessed'), array_sum(array_intersect_key($dash->viewData('riskDistribution'), array_flip(['low', 'moderate', 'high']))));
        $this->assertSame([$noReferral->id], $dash->viewData('watchlistAssessments')->pluck('student_id')->all());
        $this->assertSame(1, $risk->viewData('highRiskCount'));
    }

    public function test_graduated_and_transferred_students_are_left_out_of_every_risk_view(): void
    {
        $c = $this->counselor();
        foreach (['graduated', 'transferred', 'inactive'] as $status) {
            $this->assess(Student::factory()->create(['status' => $status]), 'high', 90);
        }
        $active = Student::factory()->create(['status' => 'active']);
        $this->assess($active, 'high', 90);

        $this->assertSame(1, $this->actingAs($c)->get(route('admin.risk.index'))->viewData('totalAssessed'));
        $this->assertSame(1, $this->actingAs($this->superAdmin())->get(route('admin.dashboard'))->viewData('atRiskCount'));
        $this->assertSame([$active->id], $this->counselorDash($c)->viewData('watchlistAssessments')->pluck('student_id')->all());
        $this->assertSame(1, $this->actingAs($c)->get(route('admin.students.index'))->viewData('atRiskStudents'));
    }

    public function test_latest_ids_keeps_only_each_students_newest_assessment(): void
    {
        $s = Student::factory()->create();
        $old = $this->assess($s, 'high', 90, now()->subDays(3));
        $new = $this->assess($s, 'low', 10);

        $ids = RiskAssessment::latestIds();

        $this->assertTrue($ids->contains($new->id));
        $this->assertFalse($ids->contains($old->id));
    }

    public function test_latest_ids_can_include_inactive_students_when_asked(): void
    {
        $gone = Student::factory()->create(['status' => 'graduated']);
        $a = $this->assess($gone);

        $this->assertFalse(RiskAssessment::latestIds()->contains($a->id));
        $this->assertTrue(RiskAssessment::latestIds(null, false)->contains($a->id));
    }

    // ── Auto-refresh script ──────────────────────────────────────────────

    public function test_the_dashboard_refresh_script_guards_against_a_login_redirect(): void
    {
        $html = $this->counselorDash($this->counselor())->getContent();

        $this->assertStringContainsString('r.redirected', $html);
        $this->assertStringContainsString('window.location.reload()', $html);
    }

    public function test_the_refresh_endpoint_redirects_a_logged_out_visitor_instead_of_serving_the_dashboard(): void
    {
        $this->get(route('counselor.dashboard.refresh'))->assertRedirect(route('login'));
    }
}
