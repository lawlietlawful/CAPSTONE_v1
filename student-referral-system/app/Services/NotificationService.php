<?php

namespace App\Services;

use App\Models\BehavioralReport;
use App\Models\Notification;
use App\Models\Referral;

/**
 * Creates in-app notifications. Centralised so the wording of each event type
 * lives in one place and the two producers (escalation, counselor status
 * changes) can't drift.
 */
class NotificationService
{
    public function notify(
        int $userId,
        string $title,
        string $message,
        string $type,
        ?string $referenceType = null,
        ?int $referenceId = null,
    ): Notification {
        return Notification::create([
            'user_id'        => $userId,
            'title'          => $title,
            'message'        => $message,
            'type'           => $type,
            'reference_type' => $referenceType,
            'reference_id'   => $referenceId,
            'is_read'        => false,
        ]);
    }

    /**
     * Tell the reporting teacher their behavioral report was escalated into a
     * guidance referral. Most useful on the delayed path (reports:reassess),
     * where the escalation happens well after the teacher filed the report.
     */
    public function reportEscalated(BehavioralReport $report, Referral $referral): void
    {
        $name = $report->student?->full_name ?? 'a student';

        $this->notify(
            $report->reported_by,
            'Report escalated to Guidance',
            "Your \"{$report->incident_type}\" report on {$name} was escalated to a guidance referral.",
            'report_escalated',
            'referral',
            $referral->id,
        );
    }

    /**
     * Tell the teacher who filed a referral that its status changed (a counselor
     * picked it up, resolved it, or cancelled it). No-op for the referral's own
     * filer acting on it, or when the filer isn't a teacher.
     */
    public function referralStatusChanged(Referral $referral): void
    {
        $filer = $referral->referredBy;
        if (! $filer || $filer->role !== 'teacher') {
            return;
        }

        $phrase = match ($referral->status) {
            'in_progress' => 'is now being reviewed by Guidance',
            'resolved'    => 'has been resolved',
            'cancelled'   => 'was cancelled',
            default       => "was updated to {$referral->status}",
        };

        $name = $referral->student?->full_name ?? 'a student';

        $this->notify(
            $filer->id,
            'Referral update',
            "Your referral for {$name} {$phrase}.",
            'referral_status',
            'referral',
            $referral->id,
        );
    }
}
