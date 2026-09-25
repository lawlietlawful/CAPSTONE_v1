<?php

namespace App\Support;

use App\Models\Intervention;
use App\Models\Student;
use Carbon\Carbon;

/**
 * One plain-language answer to "where does this student's case stand?".
 *
 * The app tracks three separate things that each have their own "resolved":
 * the referral, the behavioral report and the intervention outcome. Code keeps
 * them in step, but a counselor looking at a student shouldn't have to read
 * three status columns to know whether anything is open. The REFERRAL is the
 * case; reports follow it (see BehavioralReportService::syncStatusFromReferral)
 * and interventions are the work done on it.
 *
 * States, most urgent first (the first that applies wins):
 *   unassigned         an open referral nobody owns
 *   follow_up_overdue  an open referral with a follow-up date that has passed
 *   in_progress        an open referral being worked
 *   pending            an open referral not started
 *   report_waiting     no referral, but a report nobody has reviewed
 *   closed             referrals exist and every one is resolved/cancelled
 *   none               nothing on file
 */
class CaseStatus
{
    /**
     * @return array{key:string,label:string,tone:string,icon:string,detail:string,referral_id:?int}
     */
    public static function for(Student $student): array
    {
        $referrals = $student->relationLoaded('referrals') ? $student->referrals : $student->referrals()->get();
        $reports = $student->relationLoaded('behavioralReports') ? $student->behavioralReports : $student->behavioralReports()->get();

        $open = $referrals->whereIn('status', ['pending', 'in_progress']);
        $pendingReports = $reports->where('status', 'pending')->count();

        if ($open->isNotEmpty()) {
            $openIds = $open->pluck('id');
            $followUps = Intervention::activeFollowUp()->whereIn('interventions.referral_id', $openIds);

            $overdue = (clone $followUps)->whereDate('interventions.follow_up_date', '<', Carbon::today())->count();
            $next = (clone $followUps)->whereDate('interventions.follow_up_date', '>=', Carbon::today())->min('interventions.follow_up_date');

            $lead = $open->sortByDesc('id')->first();
            $suffix = $open->count() > 1 ? " ({$open->count()} open referrals)" : '';

            if ($open->whereNull('counselor_id')->isNotEmpty()) {
                return self::state('unassigned', 'Open - no counselor yet', 'red', 'ti-user-question',
                    'An open referral has no counselor assigned.' . $suffix, $lead->id);
            }

            if ($overdue > 0) {
                return self::state('follow_up_overdue', 'In progress - follow-up overdue', 'red', 'ti-calendar-exclamation',
                    $overdue . ($overdue === 1 ? ' follow-up is' : ' follow-ups are') . ' past due.' . $suffix, $lead->id);
            }

            if ($open->where('status', 'in_progress')->isNotEmpty()) {
                $detail = $next ? 'Next follow-up ' . Carbon::parse($next)->format('M j, Y') . '.' : 'No follow-up scheduled.';

                return self::state('in_progress', 'In progress', 'blue', 'ti-loader', $detail . $suffix, $lead->id);
            }

            return self::state('pending', 'Open - not started', 'amber', 'ti-clock', 'A referral is waiting to be worked.' . $suffix, $lead->id);
        }

        if ($referrals->isEmpty()) {
            if ($pendingReports > 0) {
                return self::state('report_waiting', 'Report awaiting review', 'amber', 'ti-message-report',
                    $pendingReports . ($pendingReports === 1 ? ' behavioral report has' : ' behavioral reports have') . ' not been reviewed, and there is no referral.', null);
            }

            return self::state('none', 'No case on file', 'gray', 'ti-circle-dashed', 'No referrals yet.', null);
        }

        $last = $referrals->sortByDesc('id')->first();
        $detail = $last->status === 'resolved'
            ? 'Last referral resolved' . ($last->resolved_at ? ' ' . $last->resolved_at->format('M j, Y') : '') . '.'
            : 'Last referral was cancelled.';
        if ($pendingReports > 0) {
            $detail .= ' ' . $pendingReports . ($pendingReports === 1 ? ' report is' : ' reports are') . ' still unreviewed.';
        }

        return self::state('closed', 'Closed', 'green', 'ti-circle-check', $detail, $last->id);
    }

    private static function state(string $key, string $label, string $tone, string $icon, string $detail, ?int $referralId): array
    {
        return ['key' => $key, 'label' => $label, 'tone' => $tone, 'icon' => $icon, 'detail' => $detail, 'referral_id' => $referralId];
    }
}
