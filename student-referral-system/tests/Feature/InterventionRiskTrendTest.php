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
 * The system's whole premise is an ML-driven risk score, but nothing
 * previously answered "did this intervention actually help?" The Details
 * page now compares the risk score AT THE TIME of the referral (a frozen
 * historical snapshot) against the student's current latest assessment,
 * which may come from a later referral or scheduled reassessment.
 */
class InterventionRiskTrendTest extends TestCase
{
    use RefreshDatabase;

    private function counselor(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function assessment(Student $student, float $score, string $level = 'moderate'): RiskAssessment
    {
        return RiskAssessment::create([
            'student_id'                => $student->id,
            'previous_referrals_count'  => 1,
            'behavioral_reports_count'  => 0,
            'concern_type_encoded'      => 1,
            'days_since_last_referral'  => 10,
            'risk_score'                => $score,
            'risk_level'                => $level,
            'risk_factors'              => ['reason' => 'test'],
            'assessed_at'               => now(),
        ]);
    }

    public function test_no_card_when_the_referral_was_never_assessed(): void
    {
        $counselor = $this->counselor();
        $referral = Referral::factory()->create(['risk_assessment_id' => null]);
        $intervention = Intervention::factory()->create(['referral_id' => $referral->id, 'counselor_id' => $counselor->id]);

        $page = $this->actingAs($counselor)->get(route('counselor.interventions.show', $intervention->id));

        $page->assertDontSee('Risk Trend');
    }

    public function test_shows_no_new_assessment_when_nothing_newer_exists(): void
    {
        $counselor = $this->counselor();
        $student = Student::factory()->create();
        $assessment = $this->assessment($student, 80);
        $referral = Referral::factory()->create(['student_id' => $student->id, 'risk_assessment_id' => $assessment->id]);
        $intervention = Intervention::factory()->create(['referral_id' => $referral->id, 'counselor_id' => $counselor->id]);

        $page = $this->actingAs($counselor)->get(route('counselor.interventions.show', $intervention->id));

        $page->assertSee('Risk Trend');
        $page->assertSee('No newer AI assessment yet');
        $page->assertSee('80');
    }

    public function test_shows_improved_when_the_latest_score_is_lower(): void
    {
        $counselor = $this->counselor();
        $student = Student::factory()->create();
        $original = $this->assessment($student, 85, 'high');
        $referral = Referral::factory()->create(['student_id' => $student->id, 'risk_assessment_id' => $original->id]);
        $intervention = Intervention::factory()->create(['referral_id' => $referral->id, 'counselor_id' => $counselor->id]);

        // A newer assessment (e.g. from a later referral) with a lower score.
        $this->assessment($student, 40, 'low');

        $page = $this->actingAs($counselor)->get(route('counselor.interventions.show', $intervention->id));

        $page->assertSee('Risk Improved');
        $page->assertSee('85');
        $page->assertSee('40');
    }

    public function test_shows_worsened_when_the_latest_score_is_higher(): void
    {
        $counselor = $this->counselor();
        $student = Student::factory()->create();
        $original = $this->assessment($student, 30, 'low');
        $referral = Referral::factory()->create(['student_id' => $student->id, 'risk_assessment_id' => $original->id]);
        $intervention = Intervention::factory()->create(['referral_id' => $referral->id, 'counselor_id' => $counselor->id]);

        $this->assessment($student, 75, 'moderate');

        $page = $this->actingAs($counselor)->get(route('counselor.interventions.show', $intervention->id));

        $page->assertSee('Risk Worsened');
    }

    public function test_shows_unchanged_when_the_score_is_identical(): void
    {
        $counselor = $this->counselor();
        $student = Student::factory()->create();
        $original = $this->assessment($student, 50, 'moderate');
        $referral = Referral::factory()->create(['student_id' => $student->id, 'risk_assessment_id' => $original->id]);
        $intervention = Intervention::factory()->create(['referral_id' => $referral->id, 'counselor_id' => $counselor->id]);

        $this->assessment($student, 50, 'moderate');

        $page = $this->actingAs($counselor)->get(route('counselor.interventions.show', $intervention->id));

        $page->assertSee('Risk Unchanged');
    }
}
