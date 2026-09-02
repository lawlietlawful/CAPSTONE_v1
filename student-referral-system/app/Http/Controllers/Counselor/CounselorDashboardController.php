<?php

namespace App\Http\Controllers\Counselor;

use App\Http\Controllers\Controller;
use App\Models\Referral;
use App\Models\Intervention;
use App\Models\RiskAssessment;
use App\Models\Seminar;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class CounselorDashboardController extends Controller
{
    public function index()
    {
        $counselorId = auth()->id();

        // 1. Pending Referrals — this counselor's assigned cases, plus
        // unclaimed ones (counselor_id is null until someone assigns them).
        $pendingReferralsCount = Referral::where('status', 'pending')
            ->where(function ($q) use ($counselorId) {
                $q->where('counselor_id', $counselorId)->orWhereNull('counselor_id');
            })
            ->count();
        $recentPendingReferrals = Referral::with(['student', 'referredBy', 'riskAssessment'])
            ->where('status', 'pending')
            ->where(function ($q) use ($counselorId) {
                $q->where('counselor_id', $counselorId)->orWhereNull('counselor_id');
            })
            ->latest()
            ->take(5)
            ->get();

        // 2. Upcoming Follow-ups — interventions always belong to whoever
        // logged them, so this is scoped strictly to the current counselor.
        $upcomingInterventionsCount = Intervention::where('counselor_id', $counselorId)
            ->whereNotNull('follow_up_date')
            ->whereDate('follow_up_date', '>=', Carbon::today())
            ->count();

        $upcomingInterventions = Intervention::with('referral.student')
            ->where('counselor_id', $counselorId)
            ->whereNotNull('follow_up_date')
            ->whereDate('follow_up_date', '>=', Carbon::today())
            ->orderBy('follow_up_date')
            ->take(5)
            ->get();

        // 2b. Overdue Follow-ups — a follow_up_date that has already passed.
        // Only the LATEST intervention per referral is considered: if the
        // counselor logged a newer session after the old due date, that old
        // follow-up has effectively been superseded, not dropped. Without
        // this, a case that was actually followed up on would still show as
        // overdue forever because its original follow_up_date never changes.
        $latestInterventionIdsPerReferral = Intervention::where('counselor_id', $counselorId)
            ->whereIn('id', function ($sub) use ($counselorId) {
                $sub->selectRaw('(SELECT i2.id FROM interventions i2 WHERE i2.referral_id = interventions.referral_id AND i2.counselor_id = ? ORDER BY i2.created_at DESC, i2.id DESC LIMIT 1)', [$counselorId])
                    ->from('interventions')
                    ->where('counselor_id', $counselorId)
                    ->groupBy('referral_id');
            })
            ->pluck('id');

        $overdueInterventionsCount = Intervention::whereIn('id', $latestInterventionIdsPerReferral)
            ->whereNotNull('follow_up_date')
            ->whereDate('follow_up_date', '<', Carbon::today())
            ->count();

        $overdueInterventions = Intervention::with('referral.student')
            ->whereIn('id', $latestInterventionIdsPerReferral)
            ->whereNotNull('follow_up_date')
            ->whereDate('follow_up_date', '<', Carbon::today())
            ->orderBy('follow_up_date')
            ->take(5)
            ->get();

        // 3. Active Seminars — school-wide events, not owned by a specific
        // counselor, so this intentionally stays unscoped.
        $activeSeminarsCount = Seminar::where('status', 'upcoming')
            ->orWhere('status', 'ongoing')
            ->count();
        $upcomingSeminars = Seminar::where('status', 'upcoming')
            ->orWhere('status', 'ongoing')
            ->orderBy('date')
            ->take(3)
            ->get();
        $ongoingSeminarsCount = Seminar::where('status', 'ongoing')->count();

        // 4. Quick stat: this counselor's own completed interventions this month.
        $completedInterventionsThisMonth = Intervention::where('counselor_id', $counselorId)
            ->whereNotNull('outcome')
            ->whereMonth('intervention_date', Carbon::now()->month)
            ->whereYear('intervention_date', Carbon::now()->year)
            ->count();

        // ── Stat-card context chips (mirrors the Admin dashboard's pattern
        // of a small delta/context pill under each number) ────────────────
        $newPendingToday = Referral::where('status', 'pending')
            ->where(function ($q) use ($counselorId) {
                $q->where('counselor_id', $counselorId)->orWhereNull('counselor_id');
            })
            ->whereDate('created_at', Carbon::today())
            ->count();

        $interventionsDueThisWeek = Intervention::where('counselor_id', $counselorId)
            ->whereNotNull('follow_up_date')
            ->whereBetween('follow_up_date', [Carbon::today(), Carbon::today()->endOfWeek()])
            ->count();

        $completedLastMonth = Intervention::where('counselor_id', $counselorId)
            ->whereNotNull('outcome')
            ->whereMonth('intervention_date', Carbon::now()->subMonthNoOverflow()->month)
            ->whereYear('intervention_date', Carbon::now()->subMonthNoOverflow()->year)
            ->count();

        // ── Risk Distribution — scoped to students this counselor is
        // actually handling (assigned to them, or unclaimed and awaiting
        // assignment), not the whole school like Admin's version.
        $myStudentIds = Referral::where(function ($q) use ($counselorId) {
                $q->where('counselor_id', $counselorId)->orWhereNull('counselor_id');
            })
            ->distinct()
            ->pluck('student_id');

        $latestRiskIds = DB::table('risk_assessments')
            ->whereIn('student_id', $myStudentIds)
            ->select(DB::raw('MAX(id) as id'))
            ->groupBy('student_id')
            ->pluck('id');

        $riskCounts = RiskAssessment::whereIn('id', $latestRiskIds)
            ->select('risk_level', DB::raw('count(*) as total'))
            ->groupBy('risk_level')
            ->pluck('total', 'risk_level')
            ->toArray();

        $totalAssessed = array_sum($riskCounts) ?: 1;

        $riskDistribution = [
            'low'          => $riskCounts['low']      ?? 0,
            'moderate'     => $riskCounts['moderate']  ?? 0,
            'high'         => $riskCounts['high']      ?? 0,
            'low_pct'      => round((($riskCounts['low']      ?? 0) / $totalAssessed) * 100),
            'moderate_pct' => round((($riskCounts['moderate'] ?? 0) / $totalAssessed) * 100),
            'high_pct'     => round((($riskCounts['high']     ?? 0) / $totalAssessed) * 100),
        ];

        return view('counselor.dashboard.index', compact(
            'pendingReferralsCount', 'recentPendingReferrals',
            'upcomingInterventionsCount', 'upcomingInterventions',
            'overdueInterventionsCount', 'overdueInterventions',
            'activeSeminarsCount', 'upcomingSeminars', 'ongoingSeminarsCount',
            'completedInterventionsThisMonth',
            'newPendingToday', 'interventionsDueThisWeek', 'completedLastMonth',
            'riskDistribution'
        ));
    }
}
