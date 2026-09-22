<?php

namespace Tests\Feature;

use App\Models\Referral;
use App\Models\RiskAssessment;
use App\Models\Student;
use App\Services\RiskAssessmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsTeacherScenario;
use Tests\TestCase;

/**
 * Risk scores used to only ever move UP: they're set once when a referral or
 * behavioral report is filed, and nothing ever re-checks them afterward — a
 * student flagged 'high' stays 'high' forever, even after a spotless year,
 * because the ML model's own days_since_last_referral feature never gets a
 * chance to reflect that. This locks down the periodic recheck that fixes
 * that, and the "students:reassess-risk" command that runs it on a schedule.
 */
class StudentRiskReassessmentTest extends TestCase
{
    use RefreshDatabase;
    use BuildsTeacherScenario;

    private function assessedStudent(string $riskLevel = 'high', int $daysAgo = 20): Student
    {
        $student = Student::factory()->create();
        Referral::factory()->create(['student_id' => $student->id, 'created_at' => now()->subDays($daysAgo)]);

        RiskAssessment::create([
            'student_id' => $student->id,
            'previous_referrals_count' => 1,
            'behavioral_reports_count' => 0,
            'concern_type_encoded' => RiskAssessmentService::encodeConcernType('behavioral'),
            'days_since_last_referral' => $daysAgo,
            'risk_score' => 90,
            'risk_level' => $riskLevel,
            'risk_factors' => ['reason' => 'Original incident: punched a classmate.'],
            'assessed_at' => now()->subDays($daysAgo),
        ]);

        return $student;
    }

    // ── RiskAssessmentService::reassessOverTime ─────────────────────────────

    public function test_a_never_assessed_student_returns_null(): void
    {
        $student = Student::factory()->create();

        $result = app(RiskAssessmentService::class)->reassessOverTime($student);

        $this->assertNull($result);
        $this->assertDatabaseCount('risk_assessments', 0);
    }

    public function test_reassessment_carries_forward_the_concern_type_and_marks_the_reason(): void
    {
        $this->fakeMlEngine('low', 15);
        $student = $this->assessedStudent(daysAgo: 30);

        $assessment = app(RiskAssessmentService::class)->reassessOverTime($student);

        $this->assertNotNull($assessment);
        // concern_type_encoded isn't int-cast on the model, so the DB driver
        // may hand it back as a numeric string — the value matters, not the
        // PHP type of however it round-tripped through the database.
        $this->assertEquals(RiskAssessmentService::encodeConcernType('behavioral'), $assessment->concern_type_encoded);
        $this->assertSame('[Automated re-check] Original incident: punched a classmate.', $assessment->risk_factors['reason']);
    }

    public function test_reassessment_refreshes_days_since_last_referral(): void
    {
        $this->fakeMlEngine('low', 15);
        $student = $this->assessedStudent(daysAgo: 30);

        $assessment = app(RiskAssessmentService::class)->reassessOverTime($student);

        // The referral itself is 30 days old; the recheck must reflect that
        // current gap, not the stale value frozen in the old assessment.
        $this->assertSame(30, $assessment->days_since_last_referral);
    }

    public function test_reassessment_does_not_double_prefix_an_already_rechecked_reason(): void
    {
        $this->fakeMlEngine('low', 15);
        $student = $this->assessedStudent(daysAgo: 30);

        $first = app(RiskAssessmentService::class)->reassessOverTime($student);
        $student->refresh();
        $second = app(RiskAssessmentService::class)->reassessOverTime($student);

        $this->assertSame(
            '[Automated re-check] Original incident: punched a classmate.',
            $second->risk_factors['reason']
        );
    }

    public function test_the_policy_override_still_applies_on_a_periodic_recheck(): void
    {
        // The model itself says 'low' this time, but 5+ referrals on file
        // means school policy keeps the student flagged regardless.
        $this->fakeMlEngine('low', 10);
        $student = $this->assessedStudent(daysAgo: 30);
        Referral::factory()->count(4)->create(['student_id' => $student->id]);

        $assessment = app(RiskAssessmentService::class)->reassessOverTime($student);

        $this->assertSame('high', $assessment->risk_level);
    }

    public function test_reassessment_returns_null_when_the_ml_engine_is_down(): void
    {
        $this->failMlEngine();
        $student = $this->assessedStudent(daysAgo: 30);

        $result = app(RiskAssessmentService::class)->reassessOverTime($student);

        $this->assertNull($result);
        $this->assertDatabaseCount('risk_assessments', 1); // only the original — no bad row written
    }

    // ── The students:reassess-risk command ──────────────────────────────────

    public function test_the_command_skips_students_whose_assessment_is_not_yet_stale(): void
    {
        $this->fakeMlEngine('low', 15);
        $this->assessedStudent(daysAgo: 3); // fresh — well under the 14-day threshold

        $this->artisan('students:reassess-risk')
            ->expectsOutputToContain('No stale risk assessments to refresh.')
            ->assertSuccessful();

        $this->assertDatabaseCount('risk_assessments', 1);
    }

    public function test_the_command_reassesses_a_student_past_the_staleness_threshold(): void
    {
        $this->fakeMlEngine('low', 15);
        $this->assessedStudent(daysAgo: 30);

        $this->artisan('students:reassess-risk')->assertSuccessful();

        $this->assertDatabaseCount('risk_assessments', 2);
    }

    public function test_the_command_stops_and_fails_when_the_ml_engine_is_unreachable(): void
    {
        $this->failMlEngine();
        $this->assessedStudent(daysAgo: 30);

        $this->artisan('students:reassess-risk')
            ->expectsOutputToContain('ML engine unreachable')
            ->assertFailed();

        // No bad/partial row written for the failed student.
        $this->assertDatabaseCount('risk_assessments', 1);
    }

    public function test_the_command_respects_the_limit_option(): void
    {
        $this->fakeMlEngine('low', 15);
        $this->assessedStudent(daysAgo: 30);
        $this->assessedStudent(daysAgo: 30);
        $this->assessedStudent(daysAgo: 30);

        $this->artisan('students:reassess-risk', ['--limit' => 1])->assertSuccessful();

        // 3 original + only 1 new recheck, since the limit capped it.
        $this->assertDatabaseCount('risk_assessments', 4);
    }
}
