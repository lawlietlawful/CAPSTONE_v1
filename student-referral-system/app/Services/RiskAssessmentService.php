<?php

namespace App\Services;

use App\Models\Student;
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
        $assessment = RiskAssessment::create([
            'student_id'               => $student->id,
            'previous_referrals_count' => $features['previous_referrals_count'],
            'behavioral_reports_count' => $features['behavioral_reports_count'],
            'concern_type_encoded'     => $features['concern_type_encoded'],
            'days_since_last_referral' => $features['days_since_last_referral'],
            'risk_score'               => $mlData['risk_score'],
            'risk_level'               => $mlData['risk_level'],
            // Model casts risk_factors as 'array' — Eloquent handles the JSON
            // encoding on save. Passing a pre-encoded string here would
            // double-encode it, corrupting every future read.
            'risk_factors' => [
                'reason' => $features['referral_reason'],
                'recommended_seminar_tag' => $mlData['recommended_seminar_tag'] ?? 'general',
            ],
            'assessed_at' => now(),
        ]);

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
     * Run an ML Risk Assessment automatically triggered by behavioral reports or other backend changes.
     */
    public function assessWithoutReferral(Student $student, $reason = "Automated Assessment triggered by backend updates")
    {
        // No current referral to exclude here, so every referral IS history.
        $features = [
            'previous_referrals_count' => Referral::where('student_id', $student->id)->count(),
            'behavioral_reports_count' => BehavioralReport::where('student_id', $student->id)->count(),
            'concern_type_encoded'     => 0, // 'other' default for automated assessments
            'days_since_last_referral' => $this->getDaysSinceLastReferral($student->id, 0),
            'referral_reason'          => $reason,
        ];

        $mlData = $this->predict($features);
        if ($mlData === null) {
            return false;
        }

        $mlData = $this->applyPolicyOverride($mlData, $features['previous_referrals_count']);

        return RiskAssessment::create([
            'student_id'               => $student->id,
            'previous_referrals_count' => $features['previous_referrals_count'],
            'behavioral_reports_count' => $features['behavioral_reports_count'],
            'concern_type_encoded'     => $features['concern_type_encoded'],
            'days_since_last_referral' => $features['days_since_last_referral'],
            'risk_score'               => $mlData['risk_score'],
            'risk_level'               => $mlData['risk_level'],
            'risk_factors' => [
                'reason' => $reason,
                'recommended_seminar_tag' => $mlData['recommended_seminar_tag'] ?? 'general',
            ],
            'assessed_at' => now(),
        ]);
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
     * Calculate the number of days since the student's last referral.
     *
     * Carbon 3's diffInDays() returns a float (e.g. 4.926 days). The (int) cast
     * is explicit rather than implicit: PHP 8.1+ deprecates the lossy implicit
     * conversion, and PHP 9 will make it a TypeError. Truncating (not rounding)
     * is deliberate — it counts *whole elapsed days*, which is what the ML model
     * was trained on and what every existing risk_assessments row already holds.
     */
    private function getDaysSinceLastReferral(int $studentId, int $currentReferralId): int
    {
        $last = Referral::where('student_id', $studentId)
                        ->where('id', '!=', $currentReferralId)
                        ->latest()
                        ->first();

        if (!$last) return 999;

        return (int) $last->created_at->diffInDays(now());
    }
}
