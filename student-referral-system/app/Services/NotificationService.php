<?php

namespace App\Services;

use App\Models\BehavioralReport;
use App\Models\Intervention;
use App\Models\Notification;
use App\Models\Referral;
use App\Models\User;

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

    /**
     * Tell the teacher who filed a behavioral report that a counselor changed
     * its status. Mirrors referralStatusChanged() for the same reason: the
     * filer otherwise has no way to know their report was looked at.
     */
    public function reportStatusChanged(BehavioralReport $report): void
    {
        $filer = $report->reportedBy;
        if (! $filer || $filer->role !== 'teacher') {
            return;
        }

        $phrase = match ($report->status) {
            'reviewed' => 'has been reviewed by Guidance',
            'resolved' => 'has been resolved',
            default    => "was updated to {$report->status}",
        };

        $name = $report->student?->full_name ?? 'a student';

        $this->notify(
            $filer->id,
            'Behavioral report update',
            "Your \"{$report->incident_type}\" report on {$name} {$phrase}.",
            'report_status',
            'behavioral_report',
            $report->id,
        );
    }

    /**
     * Tell counselors a referral needs review: whoever it was assigned to at
     * creation, or every counselor if it's unclaimed. The filer is excluded
     * even when they're a counselor themselves — no point telling someone
     * about the referral they just filed.
     */
    public function newPendingReferral(Referral $referral): void
    {
        $name = $referral->student?->full_name ?? 'A student';

        User::where('role', 'admin')
            ->where('id', '!=', $referral->referred_by)
            ->when($referral->counselor_id, fn ($q) => $q->where('id', $referral->counselor_id))
            ->get()
            ->each(fn (User $counselor) => $this->notify(
                $counselor->id,
                'New referral needs review',
                "{$name} was referred for {$referral->referral_type_label}.",
                'referral_pending',
                'referral',
                $referral->id,
            ));
    }

    /**
     * Tell the logging counselor a scheduled follow-up is due today or has
     * gone overdue. The caller (SendInterventionFollowUpReminders) gates this
     * with follow_up_notified_at so the same record is only ever notified
     * once, not daily for as long as it stays overdue.
     */
    public function interventionFollowUpDue(Intervention $intervention): void
    {
        $name = $intervention->referral?->student?->full_name ?? 'a student';
        $dueDate = $intervention->follow_up_date->format('M d, Y');
        $isOverdue = $intervention->follow_up_date->lt(now()->startOfDay());

        $this->notify(
            $intervention->counselor_id,
            $isOverdue ? 'Follow-up overdue' : 'Follow-up due today',
            "Your scheduled follow-up for {$name} ({$intervention->intervention_type}, {$dueDate}) " . ($isOverdue ? 'is overdue.' : 'is due today.'),
            'intervention_followup',
            'intervention',
            $intervention->id,
        );
    }
}
