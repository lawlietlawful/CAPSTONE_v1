<?php

namespace App\Support;

use App\Models\BehavioralReport;
use App\Models\Intervention;
use App\Models\Referral;
use App\Models\RiskAssessment;
use App\Models\User;
use Carbon\Carbon;

/**
 * The small count pills on the counselor sidebar, so you can see what is
 * waiting without opening each page. Each number is the SAME rule as the
 * dashboard item it mirrors (a test keeps them equal):
 *
 *   risk          students with a safety flag        (Needs-attention "Safety flags")
 *   reports       behavioral reports nobody reviewed (Needs-attention "Reports to review")
 *   referrals     pending referrals, mine or unclaimed (dashboard "Pending Referrals")
 *   interventions overdue follow-ups                 (Needs-attention "Overdue follow-ups")
 */
class NavBadges
{
    /** @return array{risk:int,reports:int,referrals:int,interventions:int} empty for anyone but the counselor role */
    public static function for(?User $user): array
    {
        if (! $user || $user->role !== 'admin') {
            return [];
        }

        return [
            'risk' => RiskAssessment::whereIn('id', RiskAssessment::latestIds($user->id))->needsAttention('safety')->count(),
            'reports' => BehavioralReport::where('status', 'pending')->count(),
            'referrals' => Referral::where('status', 'pending')
                ->where(fn ($q) => $q->where('counselor_id', $user->id)->orWhereNull('counselor_id'))
                ->count(),
            'interventions' => Intervention::activeFollowUp()
                ->where('interventions.counselor_id', $user->id)
                ->whereDate('interventions.follow_up_date', '<', Carbon::today())
                ->count(),
        ];
    }
}
