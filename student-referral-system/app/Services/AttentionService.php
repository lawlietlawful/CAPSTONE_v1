<?php

namespace App\Services;

use App\Models\BehavioralReport;
use App\Models\Intervention;
use App\Models\Referral;
use App\Models\RiskAssessment;
use Carbon\Carbon;

/**
 * The "Needs attention" tiles at the top of the dashboards. Every count uses
 * the SAME rule as the page its tile links to (the At-Risk page's attention
 * filters, the Follow-ups page, the Referrals "unassigned" filter), so the
 * number on a tile always matches the list you land on.
 */
class AttentionService
{
    /**
     * @param  int|null  $counselorId  A counselor's dashboard: risk counts are
     *   limited to that counselor's own scope (the At-Risk "My Students" view)
     *   and their own overdue follow-ups are included. Null = the school-wide
     *   view used by the super admin.
     * @return array<int, array{key:string,label:string,hint:string,count:int,icon:string,tone:string,url:string}>
     */
    public function tiles(?int $counselorId): array
    {
        $tiles = [];
        $scope = $counselorId !== null ? ['scope' => 'mine'] : [];
        $latest = RiskAssessment::latestIds($counselorId);
        $risk = fn (string $key) => RiskAssessment::whereIn('id', $latest)->needsAttention($key)->count();

        // Most urgent of all: an open case where the student's own words name
        // violence, a weapon or a threat. Never buried under a Moderate score.
        $tiles[] = [
            'key'   => 'safety',
            'label' => 'Safety flags',
            'hint'  => 'Open cases mentioning violence, a weapon or a threat',
            'count' => $risk('safety'),
            'icon'  => 'ti-alert-octagon',
            'tone'  => 'red',
            'url'   => route('admin.risk.index', $scope + ['attention' => 'safety']),
        ];

        if ($counselorId !== null) {
            $tiles[] = [
                'key'   => 'overdue',
                'label' => 'Overdue follow-ups',
                'hint'  => 'Sessions past their follow-up date',
                'count' => Intervention::activeFollowUp()
                    ->where('interventions.counselor_id', $counselorId)
                    ->whereDate('interventions.follow_up_date', '<', Carbon::today())
                    ->count(),
                'icon'  => 'ti-calendar-exclamation',
                'tone'  => 'red',
                'url'   => route('counselor.interventions.followups'),
            ];
        }

        $tiles[] = [
            'key'   => 'unassigned',
            'label' => 'Unassigned referrals',
            'hint'  => 'Open cases nobody owns yet',
            'count' => Referral::unassignedOpen()->count(),
            'icon'  => 'ti-user-question',
            'tone'  => 'red',
            'url'   => $counselorId !== null
                ? route('counselor.referrals.index', ['assignment' => 'unassigned'])
                : route('admin.referrals.index', ['counselor_id' => 'unassigned']),
        ];

        $tiles[] = [
            'key'   => 'reports',
            'label' => 'Reports to review',
            'hint'  => 'Behavioral reports nobody has looked at yet',
            'count' => BehavioralReport::where('status', 'pending')->count(),
            'icon'  => 'ti-message-report',
            'tone'  => 'amber',
            'url'   => $counselorId !== null
                ? route('counselor.behavioral-reports.index', ['status' => 'pending'])
                : route('admin.behavioral-reports.index', ['status' => 'pending']),
        ];

        $tiles[] = [
            'key'   => 'no_referral',
            'label' => 'No open referral',
            'hint'  => 'High/moderate risk, nothing in progress',
            'count' => $risk('no_referral'),
            'icon'  => 'ti-file-off',
            'tone'  => 'amber',
            'url'   => route('admin.risk.index', $scope + ['attention' => 'no_referral']),
        ];

        $tiles[] = [
            'key'   => 'rising',
            'label' => 'Rising risk',
            'hint'  => 'Score up ' . RiskAssessment::RISING_POINTS . '+ points since last assessment',
            'count' => $risk('rising'),
            'icon'  => 'ti-trending-up',
            'tone'  => 'amber',
            'url'   => route('admin.risk.index', $scope + ['attention' => 'rising']),
        ];

        $tiles[] = [
            'key'   => 'stale',
            'label' => 'Reassessment due',
            'hint'  => 'High/moderate, not assessed in ' . RiskAssessment::STALE_DAYS . '+ days',
            'count' => $risk('stale'),
            'icon'  => 'ti-hourglass',
            'tone'  => 'blue',
            'url'   => route('admin.risk.index', $scope + ['attention' => 'stale']),
        ];

        return $tiles;
    }
}
