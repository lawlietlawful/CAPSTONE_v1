<?php

namespace App\Services;

use App\Models\BehavioralReport;
use App\Models\Referral;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class BehavioralReportService
{
    /**
     * Recorded when the ML engine was unreachable at filing time. The report is
     * kept (a teacher mid-class must never lose their work) but it is NOT graded
     * — `php artisan reports:reassess` finishes the job once the engine is back.
     */
    public const SEVERITY_UNASSESSED = 'Unassessed';

    /**
     * Incident types serious enough to escalate on their own, regardless of what
     * the model says — and, crucially, even when the model never ran.
     *
     * 'Failing Grade' is a legacy value: no form emits it (the Academic Failure
     * option is *labelled* "Academic Failure / Failing Grade") and no row in the
     * database carries it. Retained so imported/legacy data still escalates.
     */
    public const CRITICAL_INCIDENT_TYPES = ['Academic Failure', 'Failing Grade'];

    public function __construct(
        protected RiskAssessmentService $riskService,
        protected SmsService $smsService,
        protected NotificationService $notificationService,
    ) {
    }

    /**
     * File a behavioral report on behalf of a teacher: run the ML severity
     * assessment, persist the report, and auto-escalate it to a guidance
     * referral (with a parent SMS) when the incident is serious enough.
     *
     * Shared by the Teacher web form and the Teacher mobile API so the
     * assessment/escalation behaviour stays identical across both.
     *
     * @param  User   $reporter  The teacher filing the report.
     * @param  array  $data      Validated input: student_id, incident_type,
     *                           incident_date, location (nullable), description.
     * @return BehavioralReport  With the `escalatedReferral` relation set to
     *                           the created Referral, or null if it didn't
     *                           warrant escalation.
     */
    public function create(User $reporter, array $data): BehavioralReport
    {
        $student = Student::findOrFail($data['student_id']);

        // Gathered BEFORE the report is inserted, so the counts describe the
        // student's history rather than including this very incident.
        $features = $this->historyFeatures($student, $data);

        // ONE prediction, reused for both the severity grade and (if it
        // escalates) the referral's stored RiskAssessment. Previously this path
        // hit the ML engine twice, with two different feature vectors, so the
        // severity that triggered the escalation and the risk level recorded
        // against the referral could disagree.
        $mlData = $this->riskService->predict($features);
        $severity = $this->severityFrom($mlData);

        $report = BehavioralReport::create([
            'student_id'    => $student->id,
            'reported_by'   => $reporter->id,
            'incident_type' => $data['incident_type'],
            'severity'      => $severity,
            'incident_date' => $data['incident_date'],
            'location'      => $data['location'] ?? null,
            'description'   => $data['description'],
            'status'        => 'pending', // Awaiting guidance action
        ]);

        if ($mlData === null) {
            // Loud, and actionable: this is the signal that a report escaped
            // triage. RiskAssessmentService::predict() has already logged why.
            Log::warning(
                "BehavioralReport #{$report->id} filed while the ML engine was unreachable; "
                . 'recorded as Unassessed. Run `php artisan reports:reassess` to grade it.',
                ['report_id' => $report->id, 'student_id' => $student->id]
            );
        }

        $referral = $this->maybeEscalate($report, $student, $reporter, $severity, $features, $mlData);
        $report->setRelation('escalatedReferral', $referral);

        return $report;
    }

    /**
     * The feature vector describing the student's history before this incident.
     *
     * `concern_type_encoded` is derived from the incident type — it used to be
     * hardcoded to 2 ('behavioral'), so a Truancy report was scored as a
     * discipline case and an Academic Failure as a behavioural one.
     */
    protected function historyFeatures(Student $student, array $data): array
    {
        $daysSinceLastReferral = 999;
        $lastReferral = $student->referrals()->latest()->first();
        if ($lastReferral) {
            // Whole elapsed days, matching RiskAssessmentService — Carbon 3
            // returns a float here, and the ML model expects an integer count.
            $daysSinceLastReferral = (int) $lastReferral->created_at->diffInDays(now());
        }

        $concernType = Referral::concernTypeFor(
            $this->referralTypeForIncident($data['incident_type'])
        );

        return [
            'previous_referrals_count' => $student->referrals()->count(),
            'behavioral_reports_count' => $student->behavioralReports()->count(),
            'concern_type_encoded'     => RiskAssessmentService::encodeConcernType($concernType),
            'days_since_last_referral' => $daysSinceLastReferral,
            'referral_reason'          => $data['description'],
        ];
    }

    /**
     * Map the ML risk level onto the database severity.
     *
     * When the engine is unreachable ($mlData === null) the report is recorded
     * as UNASSESSED rather than 'Low'. Grading an ungraded incident 'Low' made a
     * serious incident look harmless, skipped escalation, and left no trace that
     * the model never ran. `php artisan reports:reassess` picks these up once
     * the engine is back.
     */
    protected function severityFrom(?array $mlData): string
    {
        if ($mlData === null) {
            return self::SEVERITY_UNASSESSED;
        }

        return match ($mlData['risk_level'] ?? 'low') {
            'high'     => 'High',
            'moderate' => 'Medium',
            default    => 'Low',
        };
    }

    /**
     * Escalate serious reports (High/Critical severity, or an academic-failure
     * incident) into a high-priority referral, record the risk assessment, and
     * text the parent. Returns the created Referral, or null when the report
     * didn't warrant escalation.
     *
     * @param  array       $features  The feature vector already used to grade
     *                                the report's severity.
     * @param  array|null  $mlData    That prediction, or null if the engine was
     *                                unreachable (no assessment is recorded).
     */
    protected function maybeEscalate(
        BehavioralReport $report,
        Student $student,
        User $reporter,
        string $severity,
        array $features,
        ?array $mlData
    ): ?Referral {
        // An Unassessed report is never treated as severe here — the model never
        // ran, so we have no grounds. It still escalates if its incident_type is
        // independently critical, and reports:reassess will re-check it later.
        $shouldEscalate = in_array($severity, ['High', 'Critical'])
            || in_array($report->incident_type, self::CRITICAL_INCIDENT_TYPES);

        if (! $shouldEscalate) {
            return null;
        }

        $referralType = $this->referralTypeForIncident($report->incident_type);

        $referral = Referral::create([
            'student_id'           => $student->id,
            'referred_by'          => $reporter->id,
            'behavioral_report_id' => $report->id,
            'referral_type'        => $referralType,
            // Must be set explicitly: the column is NOT NULL DEFAULT 'other', so
            // omitting it silently filed every escalated referral as 'other',
            // mis-grouping it in analytics and feeding the ML the wrong feature.
            'concern_type'         => Referral::concernTypeFor($referralType),
            'reason'               => "[AUTO-ESCALATED from Behavioral Report #{$report->id}] " . $report->description,
            'priority'             => 'high',
            'status'               => 'pending',
        ]);

        // Reuse the prediction made for the severity grade instead of calling
        // the ML engine a second time. `syncPriority: false` keeps the 'high'
        // priority set above — an auto-escalated incident is urgent by policy,
        // regardless of what the ML model scores the student's overall risk at.
        if ($mlData !== null) {
            $mlData = $this->riskService->applyPolicyOverride(
                $mlData,
                $features['previous_referrals_count']
            );

            $this->riskService->recordAssessment(
                $student,
                $referral,
                $features,
                $mlData,
                syncPriority: false
            );
        }

        if ($student->parent_contact) {
            // Never surface "Severity: Unassessed" to a parent — an internal
            // triage state is meaningless (and alarming) to them. Omit it.
            $severityNote = $severity === self::SEVERITY_UNASSESSED
                ? ''
                : " (Severity: {$severity})";

            $message = "MU Advisory: Your child, {$student->first_name} {$student->last_name}, has been flagged for '{$report->incident_type}'{$severityNote}. This has been escalated to Guidance. Please contact them immediately.";

            $this->smsService->sendSms(
                $student->parent_contact,
                $message,
                $student->id,
                $student->parent_name ?? 'Parent',
                'parent',
                // Link the notification to the referral it was sent about, so
                // the case's SMS history is traceable in the guidance UI.
                $referral->id
            );
        }

        // Persistent in-app notification for the teacher — the record survives
        // the submission sheet, and (on the reports:reassess path) tells them
        // about an escalation that happened well after they filed the report.
        $this->notificationService->reportEscalated($report, $referral);

        return $referral;
    }

    /**
     * Re-run the assessment for a report that was filed while the ML engine was
     * down. Grades it, and escalates it now if the model says it is serious and
     * it hasn't already been escalated.
     *
     * Returns false when the engine is still unreachable, so the caller can
     * report how many reports remain unassessed.
     */
    public function reassess(BehavioralReport $report): bool
    {
        $student = $report->student;
        $reporter = $report->reportedBy;
        if (! $student || ! $reporter) {
            return false;
        }

        // The report — and possibly a referral escalated from it — already
        // exist, so a naive recount would include them. The features must
        // describe the student's history BEFORE this incident, as at filing time.
        $existing = $report->escalatedReferral()->first();

        if ($existing) {
            // featuresForReferral() already excludes the referral itself and the
            // behavioral report that triggered it.
            $features = $this->riskService->featuresForReferral($student, $existing);
            // Score the incident narrative, not the "[AUTO-ESCALATED ...]" prefix.
            $features['referral_reason'] = $report->description;
        } else {
            $features = $this->historyFeatures($student, [
                'incident_type' => $report->incident_type,
                'description'   => $report->description,
            ]);
            // Exclude this report from its own history.
            $features['behavioral_reports_count'] = max(0, $features['behavioral_reports_count'] - 1);
        }

        $mlData = $this->riskService->predict($features);
        if ($mlData === null) {
            return false;
        }

        $severity = $this->severityFrom($mlData);
        $report->update(['severity' => $severity]);

        // Already escalated (its incident_type was critical, so it escalated even
        // without a grade)? The referral exists but carries no RiskAssessment —
        // attach one now. Priority stays 'high': escalation is a policy decision.
        if ($existing) {
            if (! $existing->risk_assessment_id) {
                $this->riskService->recordAssessment(
                    $student,
                    $existing,
                    $features,
                    $this->riskService->applyPolicyOverride($mlData, $features['previous_referrals_count']),
                    syncPriority: false
                );
            }

            return true;
        }

        // Not escalated at filing time. Now that we have a grade, it may warrant
        // it — this is the case the old silent 'Low' quietly buried.
        $this->maybeEscalate($report, $student, $reporter, $severity, $features, $mlData);

        return true;
    }

    /**
     * Best-guess Referral::REFERRAL_TYPES value for an auto-escalated
     * referral, derived from the behavioral report's incident_type.
     */
    protected function referralTypeForIncident(?string $incidentType): string
    {
        return match ($incidentType) {
            'Academic Failure'      => 'Poor academic performance',
            'Truancy'               => 'Absences',
            'Disciplinary Incident' => 'Misconduct',
            default                 => 'Other',
        };
    }
}
