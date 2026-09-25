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

    /**
     * Description text naming physical violence, a weapon, or an explicit
     * threat to harm someone. Checked independently of the ML severity grade
     * and the incident_type list above, because the grade is influenced by
     * the student's history (previous_referrals_count, behavioral_reports_
     * count) — a first-ever incident can still be genuinely dangerous, and
     * "wait and see if this becomes a pattern" is the wrong call for this
     * category specifically. Also fires even if the ML engine never ran
     * (reassess() picks up the RiskAssessment once it's back — see the
     * $existing branch there, the same fallback already used for
     * CRITICAL_INCIDENT_TYPES).
     *
     * Narrowed from the full SEVERE phrase pool in
     * ml_engine/generate_dataset.py to violence/weapons/threats specifically
     * — that pool also covers truancy, substance use, and theft, which are
     * serious but not this kind of immediate-safety urgent.
     *
     * Extended beyond that pool to cover bullying, choking/strangling,
     * bladed objects and explosives named without the word "weapon", and
     * sexual violence/harassment — all squarely "a threat to harm someone"
     * even though the original SEVERE pool didn't phrase them that way.
     * Deliberately left out anything with a common innocuous double-meaning
     * in this context: "kick"/"kicked" ("kicked out of class"), "shoot"/
     * "shot" ("shoot hoops", "gave it a shot"), "bit"/"bite" (collides with
     * the word "bit"), "burn" ("burned out"), "killing" ("killing it" as
     * praise), and "abuse"/"abusive" (collides with "substance abuse", a
     * real and separate concern type that shouldn't get pulled into a
     * violence escalation). Self-harm and
     * suicide risk are an intentionally separate concern from *this* list —
     * "a threat to harm someone [else]" — and would need their own
     * escalation path, not a keyword bolted on here.
     *
     * The Cebuano/Bisaya list stays close to generate_dataset.py's own
     * vocabulary rather than added terms I'm less certain of the exact
     * usage/spelling for — worth a native speaker's review if more should
     * be added.
     */
    private const VIOLENCE_KEYWORDS = [
        // English — physical violence
        'punch', 'punched', 'punching', 'hit', 'stab', 'stabbed', 'stabbing',
        'slap', 'slapped', 'slapping', 'shove', 'shoved', 'shoving',
        'beat up', 'beating up', 'beaten', 'choke', 'choked', 'choking',
        'strangle', 'strangled', 'strangling', 'assault', 'assaulted',
        'attack', 'attacked', 'attacking', 'fight', 'fought', 'hurt someone',
        'aggressive',
        // English — bullying
        'bully', 'bullied', 'bullying',
        // English — weapons / explosives
        'weapon', 'knife', 'blade', 'razor', 'gun', 'bomb', 'explosive',
        // English — threats
        'threat', 'threaten', 'threatened', 'threatening', 'kill',
        // English — sexual violence / harassment
        'rape', 'raped', 'raping', 'molest', 'molested', 'molesting',
        'sexual assault', 'harass', 'harassed', 'harassing', 'harassment',
        // Cebuano / Bisaya
        'nanumbag', 'sumbag', 'panumbag', 'hinagiban', 'naghulga', 'hulga',
        'pamunal', 'sinumbagay', 'lubaay', 'pangaway', 'abangan', 'suntok',
        'patyon', 'dunggab', 'dunggabon',
    ];

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
     * Persist a counselor's status/notes change on a behavioral report and
     * tell the reporting teacher, mirroring ReferralService::updateStatus() —
     * without this the filer has no way to know their report was looked at.
     */
    public function updateStatus(BehavioralReport $report, string $status, ?string $counselorNotes): BehavioralReport
    {
        $statusChanged = $report->status !== $status;

        $report->update([
            'status'          => $status,
            'counselor_notes' => $counselorNotes,
        ]);

        // Only when the status actually moved — saving just an internal
        // note, or bulk-marking a report that's already in that state, used
        // to re-send "your report was reviewed" every time (ReferralService
        // has always guarded the same way).
        if ($statusChanged) {
            $this->notificationService->reportStatusChanged($report);
        }

        return $report;
    }

    /**
     * Keep an escalated report's own status in step with what happened to
     * the referral it turned into — otherwise resolving (or cancelling) the
     * referral leaves the originating report sitting "Pending" in the
     * reports queue forever, permanently showing as unaddressed work that's
     * actually already been handled.
     *
     * No notification here: ReferralService::updateStatus() already tells
     * the filing teacher about the referral via referralStatusChanged() — a
     * second notification about the same underlying case would be redundant.
     */
    public function syncStatusFromReferral(BehavioralReport $report, string $referralStatus): void
    {
        $status = match ($referralStatus) {
            'resolved', 'cancelled' => 'resolved',
            'in_progress'           => 'reviewed',
            default                 => 'pending',
        };

        if ($report->status !== $status) {
            $report->update(['status' => $status]);
        }
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
        // Independent of severity/incident_type: fires even for an Unassessed
        // report (the model never ran) and even when history alone would have
        // graded this "first offense" as Low/Medium.
        $hasViolentLanguage = $this->containsViolentLanguage($report->description);

        // An Unassessed report is never treated as severe on the ML grade alone
        // — the model never ran, so we have no grounds there. It still
        // escalates if its incident_type is independently critical, its
        // description names violence/a weapon/a threat, and reports:reassess
        // will re-check it later regardless.
        $shouldEscalate = in_array($severity, ['High', 'Critical'])
            || in_array($report->incident_type, self::CRITICAL_INCIDENT_TYPES)
            || $hasViolentLanguage;

        if (! $shouldEscalate) {
            // Not escalating doesn't mean "no risk signal" — without this, a
            // student accumulating several non-escalating "Medium" reports
            // stayed invisible to the Watchlist, Risk Distribution, and the
            // At-Risk filter forever, since all three only read a student's
            // LATEST RiskAssessment, and only an escalated report ever
            // created one. Reuses the SAME prediction already made for the
            // severity grade above — no second ML call.
            if ($mlData !== null) {
                $assessment = $this->riskService->recordAssessmentForReport(
                    $student,
                    $features,
                    $this->riskService->applyPolicyOverride($mlData, $features['previous_referrals_count'])
                );

                // The only path back to this assessment for a report that
                // never escalates — there's no referral to reach it through.
                $report->update(['risk_assessment_id' => $assessment->id]);
            }

            return null;
        }

        $referralType = $this->referralTypeForIncident($report->incident_type);

        // Make the reason for an otherwise-unremarkable-looking escalation
        // legible to the counselor: without this, a "Medium severity, first
        // offense" report showing up as a high-priority referral looks like a
        // mistake rather than a deliberate safety-net decision.
        $reasonPrefix = "[AUTO-ESCALATED from Behavioral Report #{$report->id}]";
        if ($hasViolentLanguage && ! in_array($severity, ['High', 'Critical']) && ! in_array($report->incident_type, self::CRITICAL_INCIDENT_TYPES)) {
            $reasonPrefix .= ' [Flagged: description names violence/a weapon/a threat]';
        }

        $referral = Referral::create([
            'student_id'           => $student->id,
            'referred_by'          => $reporter->id,
            'behavioral_report_id' => $report->id,
            'counselor_id'         => User::soleCounselorId(),
            'referral_type'        => $referralType,
            // Must be set explicitly: the column is NOT NULL DEFAULT 'other', so
            // omitting it silently filed every escalated referral as 'other',
            // mis-grouping it in analytics and feeding the ML the wrong feature.
            'concern_type'         => Referral::concernTypeFor($referralType),
            'reason'               => "{$reasonPrefix} " . $report->description,
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

            $assessment = $this->riskService->recordAssessment(
                $student,
                $referral,
                $features,
                $mlData,
                syncPriority: false
            );

            // Reachable via escalatedReferral->riskAssessment too, but the
            // report show page reads it directly — keeps that page's query
            // the same regardless of whether the report escalated.
            $report->update(['risk_assessment_id' => $assessment->id]);
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
        $this->notificationService->newPendingReferral($referral);

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
                $existing->refresh();
            }

            // Keep the report's own link in sync with the referral's — the
            // show page reads risk_assessment_id directly off the report.
            if ($report->risk_assessment_id !== $existing->risk_assessment_id) {
                $report->update(['risk_assessment_id' => $existing->risk_assessment_id]);
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
    /**
     * A counselor's decision to open a case for a report that did NOT
     * auto-escalate (a Medium report used to be a dead end: no referral, so
     * nothing to attach interventions to). Goes through ReferralService so it
     * gets the same ML assessment, parent notice and counselor notification
     * as any other referral, linked to the report so the two stay in sync.
     * A still-pending report becomes "reviewed" (the counselor has acted on it).
     *
     * @throws \DomainException when the report already has a referral or is resolved
     */
    public function referManually(BehavioralReport $report, User $by, ?int $counselorId = null): Referral
    {
        $existing = $report->escalatedReferral;
        if ($existing) {
            throw new \DomainException("This report is already linked to Referral #{$existing->id}.");
        }
        if ($report->status === 'resolved') {
            throw new \DomainException('This report is already resolved. Reopen it before filing a referral.');
        }

        $student = $report->student;
        if (! $student) {
            throw new \DomainException('This report has no student on file.');
        }

        $type = $this->referralTypeForIncident($report->incident_type);

        $referral = app(ReferralService::class)->create($by, [
            'student_id'           => $student->id,
            'behavioral_report_id' => $report->id,
            'referral_type'        => $type,
            'referral_type_other'  => $type === 'Other' ? $report->incident_type : null,
            'concern_type'         => Referral::concernTypeFor($type),
            'reason'               => "[From Behavioral Report #{$report->id}] " . $report->description,
            'counselor_id'         => $counselorId,
        ]);

        if ($report->status === 'pending') {
            $this->updateStatus($report, 'reviewed', $report->counselor_notes);
        }

        return $referral;
    }

    protected function referralTypeForIncident(?string $incidentType): string
    {
        return match ($incidentType) {
            'Academic Failure'      => 'Poor academic performance',
            'Truancy'               => 'Absences',
            'Disciplinary Incident' => 'Misconduct',
            default                 => 'Other',
        };
    }

    /**
     * Whether the description names physical violence, a weapon, or an
     * explicit threat to harm someone — see VIOLENCE_KEYWORDS. Matched on
     * word boundaries (not raw substring containment) so e.g. English "hit"
     * doesn't fire on an unrelated word that happens to contain it.
     */
    private function containsViolentLanguage(string $description): bool
    {
        foreach (self::VIOLENCE_KEYWORDS as $keyword) {
            if (preg_match('/\b' . preg_quote($keyword, '/') . '\b/iu', $description) === 1) {
                return true;
            }
        }

        return false;
    }
}
