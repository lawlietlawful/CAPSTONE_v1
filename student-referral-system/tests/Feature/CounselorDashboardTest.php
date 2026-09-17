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
 * The counselor dashboard's access gate, its "mine or unclaimed" scoping
 * rules (pending referrals, risk distribution, the watchlist), and the
 * per-widget business logic (only the latest intervention per referral
 * counts toward overdue, today vs. upcoming vs. overdue partition the
 * follow-up list without overlap, recent activity merges two sources).
 * None of this had any test coverage before — it's exactly the kind of
 * logic that breaks silently on the next refactor, which is how the
 * admin.referrals.index link regression covered below happened in the
 * first place.
 */
class CounselorDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_counselor_cannot_load_the_dashboard(): void
    {
        $teacher = User::factory()->teacher()->create();

        $this->actingAs($teacher)->get(route('counselor.dashboard'))
            ->assertForbidden();
    }

    public function test_counselor_can_load_the_dashboard(): void
    {
        $counselor = User::factory()->counselor()->create();

        $this->actingAs($counselor)->get(route('counselor.dashboard'))
            ->assertOk();
    }

    public function test_pending_referrals_links_point_to_the_scoped_counselor_view(): void
    {
        $counselor = User::factory()->counselor()->create();

        $response = $this->actingAs($counselor)->get(route('counselor.dashboard'));

        $response->assertOk();
        $response->assertSee(route('counselor.referrals.index'), false);
        $response->assertDontSee(route('admin.referrals.index'), false);
    }

    // ── High-Risk Watchlist ─────────────────────────────────────────────
    //
    // The watchlist used to collapse RiskAssessment down to just the bare
    // Student, discarding risk_score/assessed_at/risk_factors on the way to
    // the view — the one widget framed as "AI-powered" showed less than the
    // plain referral list above it. It now keeps the full assessment.

    /** An unclaimed referral for $student, putting them in the counselor's "mine or unclaimed" scope. */
    private function putInScope(Student $student): void
    {
        Referral::factory()->create(['student_id' => $student->id, 'counselor_id' => null]);
    }

    public function test_watchlist_shows_the_risk_score_and_reason_for_an_in_scope_high_risk_student(): void
    {
        $counselor = User::factory()->counselor()->create();
        $student = Student::factory()->create();
        $this->putInScope($student);

        RiskAssessment::create([
            'student_id' => $student->id,
            'risk_score' => 87,
            'risk_level' => 'high',
            'risk_factors' => ['reason' => 'Three referrals this semester'],
            'assessed_at' => now(),
        ]);

        $response = $this->actingAs($counselor)->get(route('counselor.dashboard'));

        $response->assertOk();
        $response->assertViewHas('watchlistAssessments', function ($assessments) use ($student) {
            return $assessments->count() === 1
                && $assessments->first()->student->id === $student->id
                && (float) $assessments->first()->risk_score === 87.0
                && $assessments->first()->risk_factors['reason'] === 'Three referrals this semester';
        });
        $response->assertSee('Three referrals this semester');
    }

    public function test_watchlist_excludes_moderate_and_low_risk_students(): void
    {
        $counselor = User::factory()->counselor()->create();
        $student = Student::factory()->create();
        $this->putInScope($student);

        RiskAssessment::create([
            'student_id' => $student->id,
            'risk_score' => 40,
            'risk_level' => 'moderate',
            'assessed_at' => now(),
        ]);

        $response = $this->actingAs($counselor)->get(route('counselor.dashboard'));

        $response->assertViewHas('watchlistAssessments', fn ($assessments) => $assessments->isEmpty());
    }

    public function test_watchlist_uses_only_the_latest_assessment_per_student(): void
    {
        $counselor = User::factory()->counselor()->create();
        $student = Student::factory()->create();
        $this->putInScope($student);

        // An old 'high' assessment has since been superseded by a newer 'low' one.
        RiskAssessment::create([
            'student_id' => $student->id, 'risk_score' => 90, 'risk_level' => 'high',
            'assessed_at' => now()->subWeek(),
        ]);
        RiskAssessment::create([
            'student_id' => $student->id, 'risk_score' => 10, 'risk_level' => 'low',
            'assessed_at' => now(),
        ]);

        $response = $this->actingAs($counselor)->get(route('counselor.dashboard'));

        $response->assertViewHas('watchlistAssessments', fn ($assessments) => $assessments->isEmpty());
    }

    public function test_watchlist_excludes_students_outside_this_counselors_scope(): void
    {
        $counselor = User::factory()->counselor()->create();
        $otherCounselor = User::factory()->counselor()->create();
        $student = Student::factory()->create();

        // Assigned to a different counselor, not unclaimed — outside $counselor's scope.
        Referral::factory()->create(['student_id' => $student->id, 'counselor_id' => $otherCounselor->id]);

        RiskAssessment::create([
            'student_id' => $student->id,
            'risk_score' => 95,
            'risk_level' => 'high',
            'assessed_at' => now(),
        ]);

        $response = $this->actingAs($counselor)->get(route('counselor.dashboard'));

        $response->assertViewHas('watchlistAssessments', fn ($assessments) => $assessments->isEmpty());
    }

    // ── Upcoming Follow-ups ───────────────────────────────────────────────
    //
    // $upcomingInterventions used to be fetched by the controller and never
    // rendered anywhere in the view. It now backs the "Upcoming Follow-ups"
    // section, filtered to strictly AFTER today so it doesn't duplicate
    // "Today's Itinerary".

    private function intervention(int $counselorId, ?string $followUpDate): Intervention
    {
        $referral = Referral::factory()->create(['counselor_id' => $counselorId]);

        return Intervention::create([
            'referral_id' => $referral->id,
            'counselor_id' => $counselorId,
            'intervention_type' => 'Counseling Session',
            'description' => 'Test session',
            'intervention_date' => now(),
            'follow_up_date' => $followUpDate,
        ]);
    }

    public function test_upcoming_follow_ups_excludes_todays_interventions(): void
    {
        $counselor = User::factory()->counselor()->create();
        $this->intervention($counselor->id, now()->toDateString());

        $response = $this->actingAs($counselor)->get(route('counselor.dashboard'));

        $response->assertViewHas('upcomingInterventions', fn ($items) => $items->isEmpty());
    }

    public function test_upcoming_follow_ups_includes_future_interventions(): void
    {
        $counselor = User::factory()->counselor()->create();
        $tomorrow = $this->intervention($counselor->id, now()->addDay()->toDateString());

        $response = $this->actingAs($counselor)->get(route('counselor.dashboard'));

        $response->assertOk();
        $response->assertViewHas('upcomingInterventions', function ($items) use ($tomorrow) {
            return $items->count() === 1 && $items->first()->id === $tomorrow->id;
        });
    }

    public function test_upcoming_follow_ups_excludes_another_counselors_interventions(): void
    {
        $counselor = User::factory()->counselor()->create();
        $otherCounselor = User::factory()->counselor()->create();
        $this->intervention($otherCounselor->id, now()->addDay()->toDateString());

        $response = $this->actingAs($counselor)->get(route('counselor.dashboard'));

        $response->assertViewHas('upcomingInterventions', fn ($items) => $items->isEmpty());
    }

    // ── Pending Referrals scoping ─────────────────────────────────────────

    public function test_pending_referrals_include_mine_and_unclaimed_only(): void
    {
        $counselor = User::factory()->counselor()->create();
        $otherCounselor = User::factory()->counselor()->create();

        $mine = Referral::factory()->create(['status' => 'pending', 'counselor_id' => $counselor->id]);
        $unclaimed = Referral::factory()->create(['status' => 'pending', 'counselor_id' => null]);
        Referral::factory()->create(['status' => 'pending', 'counselor_id' => $otherCounselor->id]); // excluded
        Referral::factory()->create(['status' => 'resolved', 'counselor_id' => null]); // excluded — not pending

        $response = $this->actingAs($counselor)->get(route('counselor.dashboard'));

        $response->assertViewHas('pendingReferralsCount', 2);
        $response->assertViewHas('recentPendingReferrals', function ($referrals) use ($mine, $unclaimed) {
            return $referrals->pluck('id')->sort()->values()->all()
                === collect([$mine->id, $unclaimed->id])->sort()->values()->all();
        });
    }

    // ── Overdue: only the LATEST intervention per referral counts ────────

    public function test_overdue_is_superseded_by_a_newer_intervention_on_the_same_referral(): void
    {
        $counselor = User::factory()->counselor()->create();
        $referral = Referral::factory()->create(['counselor_id' => $counselor->id]);

        Intervention::create([
            'referral_id' => $referral->id, 'counselor_id' => $counselor->id,
            'intervention_type' => 'Counseling Session', 'description' => 'First session',
            'intervention_date' => now()->subDays(10), 'follow_up_date' => now()->subDays(5)->toDateString(),
        ]);
        // Logged after the fact — supersedes the old follow-up date.
        Intervention::create([
            'referral_id' => $referral->id, 'counselor_id' => $counselor->id,
            'intervention_type' => 'Counseling Session', 'description' => 'Follow-up session, resolved',
            'intervention_date' => now(), 'follow_up_date' => null,
        ]);

        $response = $this->actingAs($counselor)->get(route('counselor.dashboard'));

        $response->assertViewHas('overdueInterventionsCount', 0);
        $response->assertViewHas('overdueInterventions', fn ($items) => $items->isEmpty());
    }

    public function test_overdue_counts_a_referrals_only_intervention_when_it_is_overdue(): void
    {
        $counselor = User::factory()->counselor()->create();
        $referral = Referral::factory()->create(['counselor_id' => $counselor->id]);

        $overdue = Intervention::create([
            'referral_id' => $referral->id, 'counselor_id' => $counselor->id,
            'intervention_type' => 'Counseling Session', 'description' => 'Session',
            'intervention_date' => now()->subDays(10), 'follow_up_date' => now()->subDays(3)->toDateString(),
        ]);

        $response = $this->actingAs($counselor)->get(route('counselor.dashboard'));

        $response->assertViewHas('overdueInterventionsCount', 1);
        $response->assertViewHas('overdueInterventions', fn ($items) => $items->pluck('id')->all() === [$overdue->id]);
    }

    public function test_overdue_excludes_another_counselors_interventions(): void
    {
        $counselor = User::factory()->counselor()->create();
        $otherCounselor = User::factory()->counselor()->create();
        $this->intervention($otherCounselor->id, now()->subDays(3)->toDateString());

        $response = $this->actingAs($counselor)->get(route('counselor.dashboard'));

        $response->assertViewHas('overdueInterventionsCount', 0);
    }

    // ── Today's Itinerary ─────────────────────────────────────────────────

    public function test_todays_itinerary_shows_only_todays_interventions_for_this_counselor(): void
    {
        $counselor = User::factory()->counselor()->create();
        $otherCounselor = User::factory()->counselor()->create();

        $today = $this->intervention($counselor->id, now()->toDateString());
        $this->intervention($counselor->id, now()->addDay()->toDateString()); // tomorrow — excluded
        $this->intervention($counselor->id, now()->subDay()->toDateString()); // yesterday — excluded
        $this->intervention($otherCounselor->id, now()->toDateString()); // another counselor — excluded

        $response = $this->actingAs($counselor)->get(route('counselor.dashboard'));

        $response->assertViewHas('todaysInterventions', fn ($items) => $items->pluck('id')->all() === [$today->id]);
    }

    // ── Risk Distribution ─────────────────────────────────────────────────

    public function test_risk_distribution_counts_latest_assessment_per_in_scope_student_only(): void
    {
        $counselor = User::factory()->counselor()->create();
        $otherCounselor = User::factory()->counselor()->create();

        // Two in-scope high-risk students.
        foreach (range(1, 2) as $i) {
            $student = Student::factory()->create();
            $this->putInScope($student);
            RiskAssessment::create(['student_id' => $student->id, 'risk_score' => 90, 'risk_level' => 'high', 'assessed_at' => now()]);
        }

        // One in-scope student whose risk has since improved to 'low' — only the latest counts.
        $improved = Student::factory()->create();
        $this->putInScope($improved);
        RiskAssessment::create(['student_id' => $improved->id, 'risk_score' => 85, 'risk_level' => 'high', 'assessed_at' => now()->subWeek()]);
        RiskAssessment::create(['student_id' => $improved->id, 'risk_score' => 15, 'risk_level' => 'low', 'assessed_at' => now()]);

        // One high-risk student entirely outside this counselor's scope.
        $outOfScope = Student::factory()->create();
        Referral::factory()->create(['student_id' => $outOfScope->id, 'counselor_id' => $otherCounselor->id]);
        RiskAssessment::create(['student_id' => $outOfScope->id, 'risk_score' => 99, 'risk_level' => 'high', 'assessed_at' => now()]);

        $response = $this->actingAs($counselor)->get(route('counselor.dashboard'));

        $response->assertViewHas('riskDistribution', function ($dist) {
            // round() returns float, so compare percentages loosely (67.0 == 67).
            return $dist['high'] === 2 && $dist['low'] === 1 && $dist['moderate'] === 0
                && $dist['high_pct'] == 67 && $dist['low_pct'] == 33;
        });
    }

    // ── Recent Activity ───────────────────────────────────────────────────

    public function test_recent_activity_merges_referrals_and_behavioral_reports_newest_first(): void
    {
        $counselor = User::factory()->counselor()->create();

        $olderReferral = Referral::factory()->create(['counselor_id' => $counselor->id]);
        $olderReferral->forceFill(['created_at' => now()->subHours(2)])->save();

        $newerReport = BehavioralReport::factory()->create();
        $newerReport->forceFill(['created_at' => now()->subHour()])->save();

        $response = $this->actingAs($counselor)->get(route('counselor.dashboard'));

        $response->assertViewHas('recentActivity', function ($activity) {
            return $activity->count() === 2
                && $activity->first()->type === 'behavioral_report'
                && $activity->last()->type === 'referral';
        });
    }

    public function test_recent_activity_referral_half_excludes_another_counselors_referrals(): void
    {
        $counselor = User::factory()->counselor()->create();
        $otherCounselor = User::factory()->counselor()->create();
        Referral::factory()->create(['counselor_id' => $otherCounselor->id]);

        $response = $this->actingAs($counselor)->get(route('counselor.dashboard'));

        $response->assertViewHas('recentActivity', fn ($activity) => $activity->isEmpty());
    }

    /**
     * Documents current behavior, not a design endorsement: unlike the
     * referral half, the behavioral-report half of this feed is NOT scoped
     * to the counselor — every counselor sees every behavioral report here.
     * If that's ever intentionally scoped to match the referral half, this
     * test is the one that should start failing.
     */
    public function test_recent_activity_behavioral_report_half_is_not_scoped_to_counselor(): void
    {
        $counselor = User::factory()->counselor()->create();
        BehavioralReport::factory()->create(); // filed against no particular counselor

        $response = $this->actingAs($counselor)->get(route('counselor.dashboard'));

        $response->assertViewHas('recentActivity', function ($activity) {
            return $activity->count() === 1 && $activity->first()->type === 'behavioral_report';
        });
    }
}
