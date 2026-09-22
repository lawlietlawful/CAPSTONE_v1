<?php

namespace App\Http\Controllers\Counselor;

use App\Http\Controllers\Controller;
use App\Models\Referral;
use App\Models\Intervention;
use App\Models\RiskAssessment;
use App\Models\Seminar;
use App\Models\Student;
use App\Models\BehavioralReport;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class CounselorDashboardController extends Controller
{
    public function index()
    {
        return view('counselor.dashboard.index', $this->dashboardData());
    }

    /**
     * Polled by the dashboard every few seconds (same idea as the header
     * bell's poll endpoint) so a referral filed elsewhere shows up in the
     * widgets themselves — not just the notification — without a manual
     * refresh. Returns just the content partial, not the full layout.
     */
    public function refresh()
    {
        return view('counselor.dashboard.partials.body', $this->dashboardData());
    }

    private function dashboardData(): array
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

        // Strictly AFTER today — today's own follow-ups are covered by the
        // "Today's Itinerary" section further down, so this list would
        // otherwise duplicate them. This collection used to be fetched and
        // never rendered anywhere; it now backs the "Upcoming Follow-ups"
        // section below.
        $upcomingInterventions = Intervention::with('referral.student')
            ->where('counselor_id', $counselorId)
            ->whereNotNull('follow_up_date')
            ->whereDate('follow_up_date', '>', Carbon::today())
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
            ->selectRaw('MAX(id) as id')
            ->groupBy('referral_id')
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

        // 3. Total Students this counselor oversees
        $totalStudents = Student::count();
        $newStudentsThisWeek = Student::whereDate('created_at', '>=', Carbon::today()->startOfWeek())->count();

        // 4. Behavioral Reports Today
        $behavioralReportsToday = BehavioralReport::whereDate('created_at', Carbon::today())->count();
        $behavioralReportsThisWeek = BehavioralReport::whereDate('created_at', '>=', Carbon::today()->startOfWeek())->count();

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

        // ── NEW: Today's Itinerary ───────────────────────────────────────
        $todaysInterventions = Intervention::with('referral.student')
            ->where('counselor_id', $counselorId)
            ->whereNotNull('follow_up_date')
            ->whereDate('follow_up_date', Carbon::today())
            ->orderBy('follow_up_date')
            ->get();

        // ── NEW: High-Risk Watchlist ─────────────────────────────────────
        // Students in this counselor's purview whose LATEST assessment is
        // 'high' ($latestRiskIds, computed above for Risk Distribution, is
        // already exactly this scope — no need to recompute it). Keeps the
        // full assessment (not just the student) so the widget can show the
        // score, when it was flagged, and why — not just a bare name.
        $watchlistAssessments = RiskAssessment::with('student')
            ->whereIn('id', $latestRiskIds)
            ->where('risk_level', 'high')
            ->orderByDesc('assessed_at')
            ->take(5)
            ->get();

        // ── NEW: Recent Activity Stream ──────────────────────────────────
        // Combine the most recent referrals and behavioral reports
        $recentReferralActivity = Referral::with(['student', 'referredBy'])
            ->where(function ($q) use ($counselorId) {
                $q->where('counselor_id', $counselorId)->orWhereNull('counselor_id');
            })
            ->latest()
            ->take(5)
            ->get()
            ->map(function($r) {
                return (object)[
                    'type' => 'referral',
                    'date' => $r->created_at,
                    'title' => 'New Referral Filed',
                    'description' => ($r->referredBy->name ?? 'System') . ' referred ' . $r->student->first_name . ' ' . $r->student->last_name,
                    'url' => route('counselor.referrals.show', $r->id)
                ];
            });

        $recentBehavioralActivity = BehavioralReport::with(['student', 'reportedBy'])
            ->latest()
            ->take(5)
            ->get()
            ->map(function($b) {
                return (object)[
                    'type' => 'behavioral_report',
                    'date' => $b->created_at,
                    'title' => 'Behavioral Report Submitted',
                    'description' => ($b->reportedBy->name ?? 'A teacher') . ' reported ' . $b->student->first_name . ' ' . $b->student->last_name,
                    'url' => route('counselor.behavioral-reports.show', $b->id)
                ];
            });

        $recentActivity = $recentReferralActivity->concat($recentBehavioralActivity)
            ->sortByDesc('date')
            ->take(5)
            ->values();

        return compact(
            'pendingReferralsCount', 'recentPendingReferrals',
            'upcomingInterventionsCount', 'upcomingInterventions',
            'overdueInterventionsCount', 'overdueInterventions',
            'totalStudents', 'newStudentsThisWeek',
            'behavioralReportsToday', 'behavioralReportsThisWeek',
            'newPendingToday', 'interventionsDueThisWeek',
            'riskDistribution', 'todaysInterventions', 'watchlistAssessments', 'recentActivity'
        );
    }
}
