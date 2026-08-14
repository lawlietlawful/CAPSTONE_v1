<?php

namespace App\Services;

use App\Models\Referral;
use App\Models\Student;
use App\Models\User;

class ReferralService
{
    public function __construct(
        protected RiskAssessmentService $riskService,
        protected SmsService $smsService,
    ) {
    }

    /**
     * File a guidance referral on behalf of a teacher: persist it, run the ML
     * risk assessment (which sets the final priority), and notify the parent.
     *
     * Shared by the Teacher web form and the Teacher mobile API so the
     * assessment/notification behaviour stays identical across both.
     *
     * @param  User   $reporter  The teacher filing the referral.
     * @param  array  $data      Validated input: student_id, referral_type,
     *                           referral_type_other (nullable), concern_type
     *                           (nullable), reason.
     */
    public function create(User $reporter, array $data): Referral
    {
        $student = Student::findOrFail($data['student_id']);

        $referral = Referral::create([
            'student_id'          => $student->id,
            'referred_by'         => $reporter->id,
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

        return $referral;
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
