<?php

namespace Tests\Feature;

use App\Models\BehavioralReport;
use App\Models\Referral;
use App\Models\RiskAssessment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * An auto-escalated referral keeps priority 'high' by policy regardless of
 * what the ML model scores (BehavioralReportService::maybeEscalate()) — so a
 * "Low Risk" AI badge next to a "High Priority" badge is expected, not a
 * bug. But the referral page showed both with zero explanation, which reads
 * as self-contradictory. Referral::escalation_caveat explains the mismatch;
 * these tests lock down when it should (and shouldn't) appear.
 */
class ReferralEscalationCaveatTest extends TestCase
{
    use RefreshDatabase;

    private function counselor(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function riskAssessment(string $level, float $score): RiskAssessment
    {
        return RiskAssessment::create([
            'student_id'               => \App\Models\Student::factory()->create()->id,
            'previous_referrals_count' => 0,
            'behavioral_reports_count' => 0,
            'concern_type_encoded'     => 1,
            'days_since_last_referral' => 0,
            'risk_score'               => $score,
            'risk_level'               => $level,
            'risk_factors'             => [],
            'assessed_at'              => now(),
        ]);
    }

    public function test_no_caveat_for_a_directly_filed_referral(): void
    {
        $referral = Referral::factory()->create([
            'behavioral_report_id' => null,
            'priority'             => 'high',
            'risk_assessment_id'   => $this->riskAssessment('low', 20)->id,
        ]);

        $this->assertNull($referral->escalation_caveat);
    }

    public function test_no_caveat_when_the_ai_score_agrees_it_is_high(): void
    {
        $report = BehavioralReport::factory()->create();
        $referral = Referral::factory()->create([
            'behavioral_report_id' => $report->id,
            'priority'             => 'high',
            'risk_assessment_id'   => $this->riskAssessment('high', 90)->id,
        ]);

        $this->assertNull($referral->escalation_caveat);
    }

    public function test_caveat_names_the_violence_flag_when_that_is_why_it_escalated(): void
    {
        $report = BehavioralReport::factory()->create(['incident_type' => 'Disciplinary Incident']);
        $referral = Referral::factory()->create([
            'behavioral_report_id' => $report->id,
            'priority'             => 'high',
            'risk_assessment_id'   => $this->riskAssessment('low', 21)->id,
            'reason'               => '[AUTO-ESCALATED from Behavioral Report #' . $report->id . '] [Flagged: description names violence/a weapon/a threat] Naghulga sa iyang klasmeyt.',
        ]);

        $this->assertStringContainsString('violence, a weapon, or a threat', $referral->escalation_caveat);
    }

    public function test_caveat_names_the_critical_incident_type_when_that_is_why_it_escalated(): void
    {
        $report = BehavioralReport::factory()->create(['incident_type' => 'Academic Failure']);
        $referral = Referral::factory()->create([
            'behavioral_report_id' => $report->id,
            'priority'             => 'high',
            'risk_assessment_id'   => $this->riskAssessment('moderate', 55)->id,
            'reason'               => '[AUTO-ESCALATED from Behavioral Report #' . $report->id . '] Failing three subjects.',
        ]);

        $this->assertStringContainsString('Academic Failure', $referral->escalation_caveat);
    }

    public function test_generic_caveat_when_escalated_with_no_risk_assessment_on_file(): void
    {
        $report = BehavioralReport::factory()->create(['incident_type' => 'Disciplinary Incident']);
        $referral = Referral::factory()->create([
            'behavioral_report_id' => $report->id,
            'priority'             => 'high',
            'risk_assessment_id'   => null,
            'reason'               => '[AUTO-ESCALATED from Behavioral Report #' . $report->id . '] High severity incident.',
        ]);

        $this->assertNotNull($referral->escalation_caveat);
        $this->assertStringContainsString('not this AI score', $referral->escalation_caveat);
    }

    public function test_show_page_displays_the_caveat_when_applicable(): void
    {
        $counselor = $this->counselor();
        $report = BehavioralReport::factory()->create(['incident_type' => 'Disciplinary Incident']);
        $referral = Referral::factory()->create([
            'behavioral_report_id' => $report->id,
            'priority'             => 'high',
            'risk_assessment_id'   => $this->riskAssessment('low', 21)->id,
            'reason'               => '[AUTO-ESCALATED from Behavioral Report #' . $report->id . '] [Flagged: description names violence/a weapon/a threat] Naghulga sa iyang klasmeyt.',
        ]);

        $this->actingAs($counselor)->get(route('counselor.referrals.show', $referral->id))
            ->assertSee('not this AI score');
    }

    public function test_show_page_displays_the_caveat_only_once(): void
    {
        // It used to appear both next to the compact header badge and again
        // in the full "AI Risk Assessment" sidebar card — the same sentence
        // twice on one page. Kept only in the sidebar card, where a
        // counselor confused by the score would actually look for it.
        $counselor = $this->counselor();
        $report = BehavioralReport::factory()->create(['incident_type' => 'Disciplinary Incident']);
        $referral = Referral::factory()->create([
            'behavioral_report_id' => $report->id,
            'priority'             => 'high',
            'risk_assessment_id'   => $this->riskAssessment('low', 21)->id,
            'reason'               => '[AUTO-ESCALATED from Behavioral Report #' . $report->id . '] [Flagged: description names violence/a weapon/a threat] Naghulga sa iyang klasmeyt.',
        ]);

        $response = $this->actingAs($counselor)->get(route('counselor.referrals.show', $referral->id));

        $this->assertSame(1, substr_count($response->getContent(), 'not this AI score'));
    }

    public function test_show_page_does_not_display_a_caveat_for_a_normal_referral(): void
    {
        $counselor = $this->counselor();
        $referral = Referral::factory()->create([
            'behavioral_report_id' => null,
            'priority'             => 'high',
            'risk_assessment_id'   => $this->riskAssessment('high', 90)->id,
        ]);

        $this->actingAs($counselor)->get(route('counselor.referrals.show', $referral->id))
            ->assertDontSee('not this AI score');
    }
}
