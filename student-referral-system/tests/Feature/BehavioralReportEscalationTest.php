<?php

namespace Tests\Feature;

use App\Models\BehavioralReport;
use App\Models\Referral;
use App\Services\BehavioralReportService;
use App\Services\ReferralService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\Concerns\BuildsTeacherScenario;
use Tests\TestCase;

/**
 * Locks down the escalation and priority-sync behaviour that the whole
 * intervention pipeline hinges on — the logic we have repeatedly verified by
 * hand. The ML engine is faked so each risk level is exercised deterministically.
 */
class BehavioralReportEscalationTest extends TestCase
{
    use RefreshDatabase;
    use BuildsTeacherScenario;

    private function fileReport(array $overrides = []): BehavioralReport
    {
        [$teacher, $student] = $this->teacherAdvising();

        return app(BehavioralReportService::class)->create($teacher, array_merge([
            'student_id'    => $student->id,
            'incident_type' => 'Disciplinary Incident',
            'incident_date' => now()->toDateString(),
            'location'      => 'Room 101',
            'description'   => 'Test incident.',
        ], $overrides));
    }

    // ── Escalation ────────────────────────────────────────────────────────

    public function test_high_severity_report_escalates_into_a_referral(): void
    {
        $this->fakeMlEngine('high');

        $report = $this->fileReport();

        $this->assertSame('High', $report->severity);
        $this->assertNotNull($report->escalatedReferral, 'A high-severity report must escalate.');
        $this->assertDatabaseHas('referrals', [
            'behavioral_report_id' => $report->id,
            'priority'             => 'high',
        ]);
    }

    public function test_escalated_report_links_directly_to_its_risk_assessment(): void
    {
        // The report's own risk_assessment_id, not just
        // escalatedReferral->riskAssessment — a non-escalated report has no
        // referral to go through, so the report needs its own direct link.
        $this->fakeMlEngine('high');

        $report = $this->fileReport();

        $this->assertNotNull($report->risk_assessment_id);
        $this->assertSame(
            $report->escalatedReferral->risk_assessment_id,
            $report->risk_assessment_id,
            'The report and its escalated referral must point at the same assessment.'
        );
        $this->assertSame('high', $report->riskAssessment->risk_level);
    }

    public function test_low_severity_non_critical_report_does_not_escalate(): void
    {
        $this->fakeMlEngine('low');

        $report = $this->fileReport(['incident_type' => 'Disciplinary Incident']);

        $this->assertSame('Low', $report->severity);
        $this->assertNull($report->escalatedReferral, 'A low-severity discipline note must not escalate.');
        $this->assertDatabaseCount('referrals', 0);
    }

    // ── Risk visibility for non-escalated reports ──────────────────────────
    //
    // A report that doesn't escalate still gets an ML grade, but with no
    // referral to hang a RiskAssessment on, that signal used to vanish
    // entirely — the Watchlist, Risk Distribution, and the At-Risk filter all
    // read a student's LATEST RiskAssessment, so a student accumulating
    // several non-escalating "Medium" reports stayed invisible everywhere.

    public function test_non_escalated_report_still_records_a_risk_assessment(): void
    {
        $this->fakeMlEngine('moderate');

        $report = $this->fileReport(['incident_type' => 'Disciplinary Incident']);

        $this->assertNull($report->escalatedReferral, 'Sanity check: this case must not escalate.');
        $this->assertDatabaseHas('risk_assessments', [
            'student_id' => $report->student_id,
            'risk_level' => 'moderate',
        ]);
        // The direct link is the ONLY way back to it — there's no referral
        // to reach it through.
        $this->assertNotNull($report->risk_assessment_id);
        $this->assertSame('moderate', $report->riskAssessment->risk_level);
    }

    public function test_non_escalated_report_risk_assessment_reuses_the_single_prediction(): void
    {
        // Regression: recording a second assessment must not mean a second
        // ML call with a different feature vector than the one that graded
        // the report's severity.
        $this->fakeMlEngine('low');

        $this->fileReport();

        $predictCalls = Http::recorded(
            fn ($request) => Str::contains($request->url(), '/predict')
        );
        $this->assertCount(1, $predictCalls, 'Grading + recording a non-escalated report must hit /predict exactly once.');
    }

    public function test_unassessed_report_does_not_record_a_risk_assessment(): void
    {
        // No prediction exists to record — there's nothing to store, and
        // reports:reassess will grade (and record, if warranted) it later.
        $this->failMlEngine();

        $report = $this->fileReport();

        $this->assertSame('Unassessed', $report->severity);
        $this->assertDatabaseCount('risk_assessments', 0);
    }

    public function test_reassess_records_a_risk_assessment_for_a_report_that_still_does_not_escalate(): void
    {
        [$teacher, $student] = $this->teacherAdvising();
        $report = BehavioralReport::factory()->create([
            'student_id'    => $student->id,
            'reported_by'   => $teacher->id,
            'incident_type' => 'Disciplinary Incident',
            'severity'      => BehavioralReportService::SEVERITY_UNASSESSED,
        ]);

        $this->fakeMlEngine('low');
        $this->artisan('reports:reassess')->assertSuccessful();

        $report->refresh();
        $this->assertSame('Low', $report->severity);
        $this->assertNull($report->escalatedReferral, 'Sanity check: Low severity must not escalate.');
        $this->assertDatabaseHas('risk_assessments', [
            'student_id' => $student->id,
            'risk_level' => 'low',
        ]);
    }

    public function test_academic_failure_escalates_even_when_ml_scores_it_low(): void
    {
        // Academic Failure is a critical incident type: it escalates on its own,
        // regardless of the model's opinion.
        $this->fakeMlEngine('low');

        $report = $this->fileReport(['incident_type' => 'Academic Failure']);

        $this->assertNotNull($report->escalatedReferral, 'Academic Failure must always escalate.');
    }

    public function test_violent_language_escalates_even_when_ml_scores_it_low(): void
    {
        // A first-time incident with a clean history can legitimately score
        // low/moderate from the model — but a description naming physical
        // violence must not sit un-escalated just because there's no pattern
        // yet. The severity DISPLAYED stays honest (whatever the model said);
        // only the escalation decision is overridden.
        $this->fakeMlEngine('low');

        $report = $this->fileReport(['description' => 'He punched a classmate in the hallway.']);

        $this->assertSame('Low', $report->severity, 'The AI grade itself is left alone — only escalation is overridden.');
        $this->assertNotNull($report->escalatedReferral, 'Violent language must escalate regardless of the ML grade.');
        $this->assertSame('high', $report->escalatedReferral->priority);
        $this->assertStringContainsString('Flagged: description names violence', $report->escalatedReferral->reason);
    }

    public function test_violent_language_in_bisaya_escalates_even_when_ml_scores_it_low(): void
    {
        // Real reports are written in Cebuano/Bisaya as often as English —
        // see ml_engine/generate_dataset.py's SEVERE phrase pool.
        $this->fakeMlEngine('low');

        $report = $this->fileReport(['description' => 'Nanumbag og klasmeyt sulod sa classroom.']);

        $this->assertNotNull($report->escalatedReferral, 'Bisaya violent language must escalate too, not just English.');
    }

    public function test_violent_language_escalates_even_when_ml_is_down(): void
    {
        // This check is text-only and needs no ML prediction, so it must not
        // wait for reports:reassess to catch it later — an unreachable engine
        // is exactly when a fast, independent safety net matters most.
        $this->failMlEngine();

        $report = $this->fileReport(['description' => 'Threatened to bring a weapon to school tomorrow.']);

        $this->assertSame('Unassessed', $report->severity);
        $this->assertNotNull($report->escalatedReferral, 'Violent language must escalate even without an ML grade.');
        $this->assertSame('high', $report->escalatedReferral->priority);
    }

    public function test_word_boundary_prevents_false_positives_on_unrelated_words(): void
    {
        // Regression: naive substring matching would fire on "hit" inside an
        // unrelated word like a surname. Word-boundary matching must not.
        $this->fakeMlEngine('low');

        $report = $this->fileReport(['description' => "Discussed course selection with the student, last name Whitfield."]);

        $this->assertNull($report->escalatedReferral, 'A surname containing "hit" must not trigger the violence check.');
    }

    public function test_escalated_referral_derives_concern_type_not_other(): void
    {
        // Regression: the escalation path once left concern_type at its 'other'
        // default, mis-grouping the case and feeding the ML the wrong feature.
        $this->fakeMlEngine('high');

        $report = $this->fileReport(['incident_type' => 'Academic Failure']);

        $this->assertSame('academic', $report->escalatedReferral->concern_type);
        $this->assertSame('Poor academic performance', $report->escalatedReferral->referral_type);
    }

    public function test_escalation_makes_exactly_one_ml_prediction(): void
    {
        // Regression: the escalation path once called the ML engine twice, with
        // two different feature vectors, so the severity that triggered the
        // escalation could disagree with the risk level recorded on the referral.
        $this->fakeMlEngine('high');

        $this->fileReport();

        $predictCalls = Http::recorded(
            fn ($request) => Str::contains($request->url(), '/predict')
        );
        $this->assertCount(1, $predictCalls, 'A single escalation must hit /predict exactly once.');
    }

    // ── Priority sync ─────────────────────────────────────────────────────

    public function test_escalated_referral_priority_is_not_downgraded_by_ml(): void
    {
        // Academic Failure escalates as a critical type even though the model
        // says 'moderate'. The referral is high-priority by policy and must stay
        // high — the ML risk level (moderate) must NOT overwrite it.
        $this->fakeMlEngine('moderate');

        $report = $this->fileReport(['incident_type' => 'Academic Failure']);
        $referral = $report->escalatedReferral;

        $this->assertSame('high', $referral->priority, 'Policy-set priority must survive the assessment.');
        $this->assertSame('moderate', $referral->riskAssessment->risk_level, 'The assessment still records the ML risk level.');
    }

    public function test_teacher_filed_referral_priority_follows_ml_risk_level(): void
    {
        [$teacher, $student] = $this->teacherAdvising();
        $this->fakeMlEngine('moderate');

        $referral = app(ReferralService::class)->create($teacher, [
            'student_id'    => $student->id,
            'referral_type' => 'Misconduct',
            'reason'        => 'Repeated disruption.',
        ]);

        // Unlike an escalated report, a teacher-filed referral has no deliberate
        // priority, so the ML risk level drives it.
        $this->assertSame('moderate', $referral->fresh()->priority);
    }

    public function test_five_prior_referrals_forces_high_priority_by_policy(): void
    {
        [$teacher, $student] = $this->teacherAdvising();

        // Five prior referrals for this student.
        Referral::factory()->count(5)->create([
            'student_id'  => $student->id,
            'referred_by' => $teacher->id,
        ]);

        // Model says 'low', but the strict-policy override escalates a student
        // with >= 5 prior referrals to high regardless.
        $this->fakeMlEngine('low');

        $referral = app(ReferralService::class)->create($teacher, [
            'student_id'    => $student->id,
            'referral_type' => 'Misconduct',
            'reason'        => 'Another incident.',
        ]);

        $this->assertSame('high', $referral->fresh()->priority);

        // And the recorded feature must exclude the current referral: 5 priors,
        // not 6.
        $this->assertSame(5, $referral->fresh()->riskAssessment->previous_referrals_count);
    }

    // ── ML engine unavailable ─────────────────────────────────────────────

    public function test_report_is_unassessed_not_low_when_ml_is_down(): void
    {
        // Regression: a report filed while the engine is unreachable was once
        // silently graded 'Low' and never escalated. It must be 'Unassessed'.
        $this->failMlEngine();

        $report = $this->fileReport(['incident_type' => 'Disciplinary Incident']);

        $this->assertSame('Unassessed', $report->severity);
        $this->assertNull($report->escalatedReferral, 'An ungraded discipline note must not escalate.');
    }

    public function test_critical_type_still_escalates_when_ml_is_down(): void
    {
        $this->failMlEngine();

        $report = $this->fileReport(['incident_type' => 'Academic Failure']);

        $this->assertSame('Unassessed', $report->severity);
        $this->assertNotNull($report->escalatedReferral, 'A critical type escalates even ungraded.');
        // No prediction was possible, so no risk assessment is attached, but the
        // referral is still high-priority by policy.
        $this->assertNull($report->escalatedReferral->risk_assessment_id);
        $this->assertSame('high', $report->escalatedReferral->priority);
        $this->assertDatabaseCount('risk_assessments', 0);
    }

    public function test_reassess_command_grades_and_escalates_a_down_report(): void
    {
        // A report that was filed while the engine was down (severity Unassessed,
        // never escalated). Built directly so the whole test uses a single ML
        // fake — Http::fake() is first-match-wins, so a down-then-up sequence in
        // one test would keep serving the first (down) stub.
        [$teacher, $student] = $this->teacherAdvising();
        $report = BehavioralReport::factory()->create([
            'student_id'    => $student->id,
            'reported_by'   => $teacher->id,
            'incident_type' => 'Disciplinary Incident',
            'severity'      => BehavioralReportService::SEVERITY_UNASSESSED,
        ]);

        // Engine is back; the recovery command grades and escalates it.
        $this->fakeMlEngine('high');
        $this->artisan('reports:reassess')->assertSuccessful();

        $report->refresh();
        $this->assertSame('High', $report->severity);
        $this->assertNotNull($report->escalatedReferral, 'A now-High report must escalate on reassess.');
        // Reassess also attaches the risk assessment it couldn't record earlier.
        $this->assertNotNull($report->escalatedReferral->risk_assessment_id);
        $this->assertSame($report->escalatedReferral->risk_assessment_id, $report->risk_assessment_id);
    }

    public function test_reassess_backfills_the_reports_link_for_a_critical_type_escalated_while_down(): void
    {
        // Exercises the OTHER reassess branch: already escalated (critical
        // incident_type escalated it even ungraded), referral exists but has
        // no risk_assessment_id yet. Built directly via factories, like the
        // down-report test above, rather than fileReport()+failMlEngine()
        // then fakeMlEngine() in the same test — Http::fake() is
        // first-match-wins, so a down-then-up sequence wouldn't actually
        // switch the stub.
        [$teacher, $student] = $this->teacherAdvising();
        $report = BehavioralReport::factory()->create([
            'student_id'    => $student->id,
            'reported_by'   => $teacher->id,
            'incident_type' => 'Academic Failure',
            'severity'      => BehavioralReportService::SEVERITY_UNASSESSED,
        ]);
        Referral::factory()->create([
            'student_id'           => $student->id,
            'referred_by'          => $teacher->id,
            'behavioral_report_id' => $report->id,
            'referral_type'        => 'Poor academic performance',
            'concern_type'         => 'academic',
            'priority'             => 'high',
        ]);
        $this->assertNull($report->risk_assessment_id, 'Sanity check: nothing to link yet.');

        $this->fakeMlEngine('moderate');
        $this->artisan('reports:reassess')->assertSuccessful();

        $report->refresh();
        $this->assertNotNull($report->risk_assessment_id);
        $this->assertSame(
            $report->escalatedReferral->risk_assessment_id,
            $report->risk_assessment_id,
            'The report must be backfilled with the same assessment now attached to its referral.'
        );
    }
}
