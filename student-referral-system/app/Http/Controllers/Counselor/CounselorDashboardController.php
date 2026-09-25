<?php

namespace App\Http\Controllers\Counselor;

use App\Http\Controllers\Controller;
use App\Models\Referral;
use App\Services\AttentionService;
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

        // 2. Follow-ups — interventions always belong to whoever logged them,
        // so every follow-up widget is scoped strictly to the current
        // counselor AND to follow-ups that still call for action (see
        // Intervention::scopeActiveFollowUp: not on a closed case, not a
        // resolved session, not superseded by a newer session).
        $myFollowUps = fn () => Intervention::activeFollowUp()->where('interventions.counselor_id', $counselorId);

        $upcomingInterventionsCount = $myFollowUps()
            ->whereDate('interventions.follow_up_date', '>=', Carbon::today())
            ->count();

        // Strictly AFTER today — today's own follow-ups are covered by the
        // "Today's Itinerary" section further down, so this list would
        // otherwise duplicate them. This collection used to be fetched and
        // never rendered anywhere; it now backs the "Upcoming Follow-ups"
        // section below.
        $upcomingInterventions = $myFollowUps()->with('referral.student')
            ->whereDate('interventions.follow_up_date', '>', Carbon::today())
            ->orderBy('interventions.follow_up_date')
            ->take(5)
            ->get();

        // 2b. Overdue Follow-ups — a follow_up_date that has already passed
        // and is still active (a newer session on the same referral has NOT
        // superseded it, the case is still open, the session is not resolved).
        $overdueInterventionsCount = $myFollowUps()
            ->whereDate('interventions.follow_up_date', '<', Carbon::today())
            ->count();

        $overdueInterventions = $myFollowUps()->with('referral.student')
            ->whereDate('interventions.follow_up_date', '<', Carbon::today())
            ->orderBy('interventions.follow_up_date')
            ->take(5)
            ->get();

        // 3. Total Students — ACTIVE students, the same definition the Admin
        // dashboard uses (graduated/transferred/inactive students are not
        // someone the office is currently working with).
        $totalStudents = Student::where('status', 'active')->count();
        $newStudentsThisWeek = Student::where('status', 'active')
            ->whereDate('created_at', '>=', Carbon::today()->startOfWeek())
            ->count();

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

        $interventionsDueThisWeek = $myFollowUps()
            ->whereBetween('interventions.follow_up_date', [Carbon::today(), Carbon::today()->endOfWeek()])
            ->count();

        $completedLastMonth = Intervention::where('counselor_id', $counselorId)
            ->whereNotNull('outcome')
            ->whereMonth('intervention_date', Carbon::now()->subMonthNoOverflow()->month)
            ->whereYear('intervention_date', Carbon::now()->subMonthNoOverflow()->year)
            ->count();

        // ── Risk Distribution — scoped to students this counselor is
        // actually handling (assigned to them, or unclaimed and awaiting
        // assignment), not the whole school like Admin's version.
        // Same definition as the At-Risk page's "My Students" view (active
        // students; assigned to me, unclaimed, or with no referral yet), so
        // the watchlist's "View all" link lands on a page with the same count.
        $latestRiskIds = RiskAssessment::latestIds($counselorId);

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
        $todaysInterventions = $myFollowUps()->with('referral.student')
            ->whereDate('interventions.follow_up_date', Carbon::today())
            ->orderBy('interventions.follow_up_date')
            ->get();

        // ── NEW: High-Risk Watchlist ─────────────────────────────────────
        // Students in this counselor's purview whose LATEST assessment is
        // 'high' ($latestRiskIds, computed above for Risk Distribution, is
        // already exactly this scope — no need to recompute it). Keeps the
        // full assessment (not just the student) so the widget can show the
        // score, when it was flagged, and why — not just a bare name.
        // A student with a safety flag (an open case naming violence, a weapon
        // or a threat) belongs here even when the model scored them Moderate,
        // and goes first; the rest are high-risk students by score.
        $safetyFlags = \App\Support\SafetyFlags::forStudents();
        $watchlistAssessments = RiskAssessment::with('student')
            ->whereIn('id', $latestRiskIds)
            ->where(fn ($q) => $q->where('risk_level', 'high')->orWhereIn('student_id', array_keys($safetyFlags)))
            ->get()
            ->sortByDesc(fn ($a) => (isset($safetyFlags[$a->student_id]) ? 1000 : 0) + (float) $a->risk_score)
            ->take(5)
            ->values();

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

        // "Needs your attention" - each row counts by the same rule as the page it links to.
        $attentionTiles = app(AttentionService::class)->tiles($counselorId);

        // Stat cards, in the order the work flows: reports -> referrals -> follow-ups -> risk.
        $reportsToReviewCount = BehavioralReport::where('status', 'pending')->count();
        $reportsNewToday = BehavioralReport::where('status', 'pending')->whereDate('created_at', Carbon::today())->count();

        // Follow-ups due = today's plus anything overdue (same "still needs action" rule as the lists).
        $followUpsDueCount = $myFollowUps()
            ->whereDate('interventions.follow_up_date', '<=', Carbon::today())
            ->count();

        $atRiskCount = ($riskCounts['high'] ?? 0) + ($riskCounts['moderate'] ?? 0);
        $safetyFlagCount = collect($attentionTiles)->firstWhere('key', 'safety')['count'] ?? 0;

        return compact(
            'attentionTiles', 'safetyFlags',
            'reportsToReviewCount', 'reportsNewToday', 'followUpsDueCount', 'atRiskCount', 'safetyFlagCount',
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
