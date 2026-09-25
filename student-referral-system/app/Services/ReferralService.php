<?php

namespace App\Services;

use App\Models\Intervention;
use App\Models\Referral;
use App\Models\Student;
use App\Models\User;

class ReferralService
{
    public function __construct(
        protected RiskAssessmentService $riskService,
        protected SmsService $smsService,
        protected NotificationService $notificationService,
        protected BehavioralReportService $reportService,
    ) {
    }

    /**
     * File a guidance referral on behalf of a teacher: persist it, run the ML
     * risk assessment (which sets the final priority), and notify the parent.
     *
     * Shared by the Teacher web form and the Teacher mobile API so the
     * assessment/notification behaviour stays identical across both.
     *
     * @param  User   $reporter  The person filing the referral (teacher,
     *                           counselor, or admin).
     * @param  array  $data      Validated input: student_id, referral_type,
     *                           referral_type_other (nullable), concern_type
     *                           (nullable), reason, counselor_id (nullable —
     *                           lets a counselor/admin pre-assign themselves
     *                           or a colleague at creation time; otherwise
     *                           falls back to the sole counselor, if there
     *                           is exactly one — see User::soleCounselorId()).
     */
    public function create(User $reporter, array $data): Referral
    {
        $student = Student::findOrFail($data['student_id']);

        $referral = Referral::create([
            'student_id'          => $student->id,
            'referred_by'         => $reporter->id,
            'behavioral_report_id' => $data['behavioral_report_id'] ?? null,
            'counselor_id'        => $data['counselor_id'] ?? User::soleCounselorId(),
            'referral_type'       => $data['referral_type'],
            'referral_type_other' => $data['referral_type_other'] ?? null,
            // concern_type is a separate, broader categorization used for
            // analytics grouping. Not every referral form asks for it
            // directly, so fall back to a value derived from referral_type.
            'concern_type'        => ($data['concern_type'] ?? null) ?: Referral::concernTypeFor($data['referral_type']),
            'reason'              => $data['reason'],
            // Placeholder pending ML evaluation — assessAndAssignSeminar()
            // overwrites this from the predicted risk level below.
            'priority'            => 'moderate',
            'status'              => 'pending',
        ]);

        // Runs the Python API, creates the RiskAssessment, and syncs the
        // referral's priority from the predicted risk level. syncPriority is
        // left at its default (true): unlike an auto-escalated behavioral
        // report, a teacher-filed referral has no deliberate priority to keep.
        $this->riskService->assessAndAssignSeminar($student, $referral);

        $this->notifyParent($student, $referral);
        $this->notificationService->newPendingReferral($referral);

        return $referral;
    }

    /**
     * Apply a status change to a referral: resolved_at bookkeeping and the
     * filed-teacher notification. Shared by Admin and Counselor's
     * updateStatus() actions (and the bulk-action equivalent) so they can't
     * independently drift apart the way they already had — Counselor's copy
     * had already been fixed to clear resolved_at on reopen and to notify
     * the filing teacher, while Admin's separate copy had neither.
     */
    public function updateStatus(Referral $referral, string $status, ?int $counselorId, ?string $counselorNotes): Referral
    {
        $data = [
            'status'          => $status,
            'counselor_notes' => $counselorNotes,
        ];

        if ($counselorId) {
            $data['counselor_id'] = $counselorId;
        }

        if ($status === 'resolved' && $referral->status !== 'resolved') {
            $data['resolved_at'] = now();
        } elseif ($status !== 'resolved') {
            $data['resolved_at'] = null;
        }

        $statusChanged = $referral->status !== $status;

        $referral->update($data);

        if ($statusChanged) {
            $this->notificationService->referralStatusChanged($referral->fresh());

            if ($referral->behavioralReport) {
                $this->reportService->syncStatusFromReferral($referral->behavioralReport, $status);
            }

            if ($status === 'resolved') {
                $this->resolveOpenInterventions($referral);
            }
        }

        return $referral->fresh();
    }

    /**
     * Resolving a referral directly — from the referral's own page, not
     * through an intervention's own "Resolved" outcome — used to leave any
     * intervention that was never individually evaluated sitting at "Not
     * yet evaluated" forever, next to a referral that now says the case is
     * closed. Only touches interventions with NO outcome recorded yet:
     * one that already has improving/no_change/worsening is a real,
     * session-specific historical assessment, not a placeholder, and
     * overwriting it to "resolved" would falsify that history. Only fires
     * on 'resolved': a 'cancelled' referral's intervention work wasn't
     * necessarily successful, so forcing that outcome there would
     * misrepresent what actually happened.
     */
    protected function resolveOpenInterventions(Referral $referral): void
    {
        Intervention::where('referral_id', $referral->id)
            ->whereNull('outcome')
            ->update(['outcome' => 'resolved']);
    }

    /**
     * Text the parent that their child has been referred to guidance. Silently
     * skipped when no contact number is on file.
     */
    protected function notifyParent(Student $student, Referral $referral): void
    {
        if (empty($student->parent_contact)) {
            return;
        }

        $message = "MU Guidance: Your child {$student->first_name} has been referred to the guidance office by their teacher. Reason: {$referral->referral_type_label}.";

        $this->smsService->sendSms(
            $student->parent_contact,
            $message,
            $student->id,
            $student->parent_name ?? 'Parent',
            'parent',
            $referral->id
        );
    }

}
