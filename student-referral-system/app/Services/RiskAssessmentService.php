<?php

namespace App\Services;

use App\Models\Student;
use App\Models\User;
use App\Models\Referral;
use App\Models\BehavioralReport;
use App\Models\RiskAssessment;
use App\Models\Seminar;
use App\Models\StudentSeminar;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RiskAssessmentService
{
    /**
     * Headers for every call to the ML engine. The X-API-Key is only sent when
     * ML_ENGINE_KEY is configured; the engine enforces it only when it has the
     * same key set, so an unkeyed local setup keeps working unchanged.
     */
    public static function mlHeaders(): array
    {
        $key = config('services.ml.key');

        return $key ? ['X-API-Key' => $key] : [];
    }

    protected function predictUrl(): string
    {
        return config('services.ml.url') . '/predict';
    }

    /**
     * Process a referral: Call ML API, create Risk Assessment, and Auto-assign Seminar.
     *
     * @param  bool  $syncPriority  When true (default), the referral's priority
     *   is derived from the ML risk level — the normal flow for a referral filed
     *   with a placeholder priority. Pass false when the caller has already set
     *   the priority deliberately (e.g. an auto-escalated behavioral report is
     *   high-priority by school policy) so the assessment can't downgrade it.
     */
    public function assessAndAssignSeminar(Student $student, Referral $referral, bool $syncPriority = true)
    {
        $features = $this->featuresForReferral($student, $referral);

        $mlData = $this->predict($features);
        if ($mlData === null) {
            return false;
        }

        $mlData = $this->applyPolicyOverride($mlData, $features['previous_referrals_count']);
        $this->recordAssessment($student, $referral, $features, $mlData, $syncPriority);

        // Immediate auto-assignment has been removed.
        // Seminars will be bulk-assigned 1 day prior via Scheduled Job.

        return true;
    }

    /**
     * The feature vector for a referral, matching how the model was trained:
     * every count describes the student's history BEFORE this referral.
     *
     * The current referral is excluded from `previous_referrals_count` — it was
     * previously counted (this method runs after the row is inserted), which
     * inflated the feature by one and disagreed with
     * `days_since_last_referral`, which has always excluded it.
     */
    public function featuresForReferral(Student $student, Referral $referral): array
    {
        $behavioralReports = BehavioralReport::where('student_id', $student->id);

        // For an auto-escalated referral, the report that triggered it is the
        // *current* incident, not history — exclude it too.
        if ($referral->behavioral_report_id) {
            $behavioralReports->where('id', '!=', $referral->behavioral_report_id);
        }

        return [
            'previous_referrals_count' => Referral::where('student_id', $student->id)
                ->where('id', '!=', $referral->id)
                ->count(),
            'behavioral_reports_count' => $behavioralReports->count(),
            'concern_type_encoded'     => self::encodeConcernType($referral->concern_type ?? 'other'),
            'days_since_last_referral' => $this->getDaysSinceLastReferral($student->id, $referral->id),
            'referral_reason'          => $referral->reason,
        ];
    }

    /**
     * POST the feature vector to the ML engine. Returns the decoded response,
     * or null when the engine is unreachable or replies with a non-2xx (it
     * returns 503 when its models aren't loaded).
     */
    public function predict(array $features): ?array
    {
        try {
            $response = Http::withHeaders(self::mlHeaders())
                ->timeout(5)
                ->post($this->predictUrl(), $features);

            if (!$response->successful()) {
                Log::error("ML API returned error: " . $response->body());
                return null;
            }

            return $response->json();
        } catch (\Exception $e) {
            Log::error("Error during ML risk assessment: " . $e->getMessage());
            return null;
        }
    }

    /**
     * STRICT SCHOOL POLICY OVERRIDE — a student with a long referral history is
     * escalated regardless of what the model predicts for this single incident.
     *
     * The floors are fixed constants, not rand(): identical input must produce
     * an identical stored risk_score, or the assessment isn't reproducible.
     */
    public function applyPolicyOverride(array $mlData, int $previousReferrals): array
    {
        $score = (float) ($mlData['risk_score'] ?? 0);

        if ($previousReferrals >= 5) {
            $mlData['risk_level'] = 'high';
            $mlData['risk_score'] = max($score, 85.0);
        } elseif ($previousReferrals >= 3 && ($mlData['risk_level'] ?? null) === 'low') {
            $mlData['risk_level'] = 'moderate';
            $mlData['risk_score'] = max($score, 55.0);
        }

        return $mlData;
    }

    /**
     * Persist a RiskAssessment from an already-obtained prediction and link it
     * to the referral. Split out from assessAndAssignSeminar() so the
     * auto-escalation path can reuse the prediction it already made for the
     * behavioral report's severity, instead of calling the ML engine twice with
     * two different feature vectors.
     *
     * @param  bool  $syncPriority  When true, the referral's priority is derived
     *   from the ML risk level. Pass false when the caller set the priority
     *   deliberately (an auto-escalated report is high-priority by policy) so
     *   the assessment can't silently downgrade it.
     */
    public function recordAssessment(
        Student $student,
        Referral $referral,
        array $features,
        array $mlData,
        bool $syncPriority = true
    ): RiskAssessment {
        $assessment = $this->persistAssessment($student, $features, $mlData, 'referral');

        $updates = ['risk_assessment_id' => $assessment->id];

        if ($syncPriority) {
            $updates['priority'] = $mlData['risk_level'] == 'high'
                ? 'high'
                : ($mlData['risk_level'] == 'moderate' ? 'moderate' : 'low');
        }

        $referral->update($updates);

        return $assessment;
    }

    /**
     * Record a RiskAssessment for a behavioral report that did NOT escalate
     * into a referral — there's nothing to link the assessment to, but the
     * risk signal must not simply vanish because no referral exists to hang
     * it on. Without this, a student accumulating several non-escalating
     * "Medium" reports stayed invisible to the Watchlist, Risk Distribution,
     * and the At-Risk filter forever, since all three only ever read a
     * student's LATEST RiskAssessment.
     *
     * Takes the SAME $features/$mlData the caller already computed for the
     * report's severity grade — no second ML call, for the same reason
     * recordAssessment() above was split out from assessAndAssignSeminar():
     * two predictions from two feature vectors can disagree with each other.
     */
    public function recordAssessmentForReport(Student $student, array $features, array $mlData): RiskAssessment
    {
        return $this->persistAssessment($student, $features, $mlData, 'report');
    }

    /**
     * Re-run a student's prediction with no new incident driving it — just an
     * updated days_since_last_referral — so a long stretch with no new
     * referrals/reports can lower a stale score the same way a new incident
     * would raise one. Without this, risk_level never changes on its own:
     * it's only ever set when a NEW referral/report triggers a fresh
     * assessment, so a student flagged 'high' months ago still shows 'high'
     * today even after a spotless year.
     *
     * Returns null (never throws) for a student with nothing to refresh yet
     * ("Not Assessed" is already an honest, correct state — there's no prior
     * profile to carry forward) or when the ML engine is unreachable, so the
     * scheduled command can just count failures and retry next run.
     */
    public function reassessOverTime(Student $student): ?RiskAssessment
    {
        $lastAssessment = $student->latestRiskAssessment;
        if (! $lastAssessment) {
            return null;
        }

        // A counselor's manual review outranks the automated recheck for a
        // while — otherwise the next scheduled run would silently undo it.
        if ($this->hasActiveOverride($student)) {
            return $lastAssessment;
        }

        $features = $this->featuresForPeriodicRecheck($student, $lastAssessment);

        $mlData = $this->predict($features);
        if ($mlData === null) {
            return null;
        }

        $mlData = $this->applyPolicyOverride($mlData, $features['previous_referrals_count']);

        return $this->persistAssessment($student, $features, $mlData, 'recheck');
    }

    /**
     * Same profile as the student's last real assessment — concern type and
     * the original incident text carried over unchanged, since there's no
     * new incident to describe — but with days_since_last_referral (the one
     * feature that naturally changes with the mere passage of time) and the
     * referral/report counts (cheap to refresh, and correct if anything
     * changed without triggering its own assessment) brought up to date.
     */
    private function featuresForPeriodicRecheck(Student $student, RiskAssessment $lastAssessment): array
    {
        return [
            'previous_referrals_count' => Referral::where('student_id', $student->id)->count(),
            'behavioral_reports_count' => BehavioralReport::where('student_id', $student->id)->count(),
            'concern_type_encoded'     => $lastAssessment->concern_type_encoded,
            'days_since_last_referral' => $this->getDaysSinceLastReferral($student->id),
            'referral_reason'          => $this->reassessmentReason($lastAssessment->risk_factors['reason'] ?? ''),
        ];
    }

    /**
     * Marks the carried-over incident text as coming from an automated
     * recheck rather than a fresh complaint — mirrors the
     * "[AUTO-ESCALATED ...]" marker BehavioralReportService already uses for
     * the same reason (transparency in the UI). Idempotent: a student
     * re-checked several times in a row (each time carrying forward the
     * previous recheck's already-prefixed text) doesn't accumulate the
     * marker over and over.
     */
    private function reassessmentReason(string $originalReason): string
    {
        $prefix = '[Automated re-check] ';

        return str_starts_with($originalReason, $prefix) ? $originalReason : $prefix . $originalReason;
    }

    private function persistAssessment(Student $student, array $features, array $mlData, string $source): RiskAssessment
    {
        $riskFactors = [
            'source' => $source,
            'reason' => $features['referral_reason'],
            'recommended_seminar_tag' => $mlData['recommended_seminar_tag'] ?? 'general',
        ];

        $level = $mlData['risk_level'];
        $score = $mlData['risk_score'];

        // Only the LATEST assessment is ever displayed, so without this a
        // mild new incident silently replaced a serious open case: the score
        // dropped, the recommended seminar changed, and the trend arrow
        // turned green while a knife-threat referral was still unresolved.
        $held = $this->openCaseAssessment($student);
        if ($held && (float) $held->risk_score > (float) $score) {
            $riskFactors['held_by_referral_id'] = $held->referral?->id;
            $riskFactors['ml_risk_level'] = $level;
            $riskFactors['ml_risk_score'] = $score;
            $riskFactors['recommended_seminar_tag'] = $held->risk_factors['recommended_seminar_tag'] ?? $riskFactors['recommended_seminar_tag'];

            $level = $held->risk_level;
            $score = $held->risk_score;
        }

        return RiskAssessment::create([
            'student_id'               => $student->id,
            'previous_referrals_count' => $features['previous_referrals_count'],
            'behavioral_reports_count' => $features['behavioral_reports_count'],
            'concern_type_encoded'     => $features['concern_type_encoded'],
            'days_since_last_referral' => $features['days_since_last_referral'],
            'risk_score'               => $score,
            'risk_level'               => $level,
            // Model casts risk_factors as 'array' — Eloquent handles the JSON
            // encoding on save. Passing a pre-encoded string here would
            // double-encode it, corrupting every future read.
            'risk_factors' => $riskFactors,
            'assessed_at' => now(),
        ]);
    }

    /**
     * The highest-scoring assessment behind one of this student's still-open
     * (pending / in-progress) referrals, or null. While such a case is open,
     * the student's risk can't read lower than it did when it was filed; once
     * the referral is resolved or cancelled it stops holding the score up
     * and the next assessment reflects the student's actual situation.
     *
     * Called before the referral being assessed is linked to its own
     * assessment, so it never holds itself up.
     */
    private function openCaseAssessment(Student $student): ?RiskAssessment
    {
        // A manual override is a counselor's later judgement: cases assessed
        // BEFORE it no longer hold the score up, or the override would be
        // undone by the very next automated assessment.
        $overrideId = RiskAssessment::where('student_id', $student->id)
            ->get(['id', 'risk_factors'])
            ->filter(fn ($a) => ($a->risk_factors['source'] ?? null) === 'override')
            ->max('id');

        return RiskAssessment::query()
            ->whereHas('referral', fn ($q) => $q
                ->where('student_id', $student->id)
                ->whereIn('status', ['pending', 'in_progress']))
            ->when($overrideId, fn ($q) => $q->where('id', '>', $overrideId))
            ->with('referral')
            ->orderByDesc('risk_score')
            ->first();
    }

    /** Days a manual override shields a student from the scheduled automated recheck. */
    public const OVERRIDE_SHIELD_DAYS = 30;

    /** Score band the ML engine uses for each level, and the value an override lands on when the current score is outside it. */
    private const LEVEL_BANDS = [
        'low'      => ['min' => 10, 'max' => 30, 'default' => 20.0],
        'moderate' => ['min' => 40, 'max' => 65, 'default' => 52.5],
        'high'     => ['min' => 70, 'max' => 95, 'default' => 82.5],
    ];

    public function hasActiveOverride(Student $student): bool
    {
        $latest = $student->latestRiskAssessment;

        return $latest
            && ($latest->risk_factors['source'] ?? null) === 'override'
            && $latest->assessed_at->gt(now()->subDays(self::OVERRIDE_SHIELD_DAYS));
    }

    /**
     * A counselor's manual judgement of a student's risk, recorded as a new
     * latest assessment so the change, the reason and who made it stay in the
     * history — the previous assessments are never edited or deleted.
     * Throws when the student has never been assessed (nothing to override).
     */
    public function overrideAssessment(Student $student, string $level, string $note, User $by): RiskAssessment
    {
        $latest = $student->latestRiskAssessment;
        if (! $latest) {
            throw new \DomainException('This student has no risk assessment to override.');
        }

        $band = self::LEVEL_BANDS[$level];
        $score = ($latest->risk_score >= $band['min'] && $latest->risk_score <= $band['max'])
            ? (float) $latest->risk_score
            : $band['default'];

        $tag = $latest->risk_factors['recommended_seminar_tag'] ?? 'general';
        if ($level === 'low') {
            $tag = 'orientation';
        } elseif ($tag === 'orientation') {
            $tag = 'general';
        }

        return RiskAssessment::create([
            'student_id'               => $student->id,
            'previous_referrals_count' => $latest->previous_referrals_count,
            'behavioral_reports_count' => $latest->behavioral_reports_count,
            'concern_type_encoded'     => $latest->concern_type_encoded,
            'days_since_last_referral' => $latest->days_since_last_referral,
            'risk_score'               => $score,
            'risk_level'               => $level,
            'risk_factors'             => [
                'source'                  => 'override',
                'reason'                  => $latest->risk_factors['reason'] ?? null,
                'recommended_seminar_tag' => $tag,
                'override'                => [
                    'by_id'          => $by->id,
                    'by_name'        => $by->name,
                    'note'           => $note,
                    'previous_level' => $latest->risk_level,
                    'previous_score' => (float) $latest->risk_score,
                ],
            ],
            'assessed_at'              => now(),
        ]);
    }

    /** Human label for the integer concern code stored on an assessment (inverse of encodeConcernType). */
    public static function concernTypeLabel(?int $code): string
    {
        return match ($code) {
            1 => 'Academic',
            2 => 'Behavioral',
            3 => 'Emotional',
            4 => 'Family',
            5 => 'Peer conflict',
            6 => 'Attendance',
            default => 'Other / general',
        };
    }

    /**
     * Encode the concern type string to an integer for the ML model. Must stay
     * in sync with CONCERN_TYPES in ml_engine/generate_dataset.py.
     *
     * Public + static because BehavioralReportService encodes the concern type
     * of an incident before any referral exists to read it from.
     */
    public static function encodeConcernType(string $type): int
    {
        return match($type) {
            'academic'      => 1,
            'behavioral'    => 2,
            'emotional'     => 3,
            'family'        => 4,
            'peer_conflict' => 5,
            'attendance'    => 6,
            default         => 0,
        };
    }

    /**
     * Calculate the number of days since the student's last referral, or
     * their most recent one before $excludeReferralId when computing
     * features for that same referral (it doesn't count as "history" for
     * itself). Omit $excludeReferralId for a periodic recheck, where there
     * is no "current" referral to exclude.
     *
     * Carbon 3's diffInDays() returns a float (e.g. 4.926 days). The (int) cast
     * is explicit rather than implicit: PHP 8.1+ deprecates the lossy implicit
     * conversion, and PHP 9 will make it a TypeError. Truncating (not rounding)
     * is deliberate — it counts *whole elapsed days*, which is what the ML model
     * was trained on and what every existing risk_assessments row already holds.
     */
    private function getDaysSinceLastReferral(int $studentId, ?int $excludeReferralId = null): int
    {
        $last = Referral::where('student_id', $studentId)
                        ->when($excludeReferralId, fn ($q) => $q->where('id', '!=', $excludeReferralId))
                        ->latest()
                        ->first();

        if (!$last) return 999;

        return (int) $last->created_at->diffInDays(now());
    }
}
