<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Services\AttentionService;
use App\Models\Referral;
use App\Models\RiskAssessment;
use App\Models\Seminar;
use App\Models\SmsLog;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AdminDashboardController extends Controller
{
    public function index()
    {
        if (auth()->user()->role === 'admin') {
            return redirect()->route('counselor.dashboard');
        }

        // ── Stat Cards ────────────────────────────────────────────

        $totalStudents = Student::where('status', 'active')->count();

        $newStudentsThisWeek = Student::where('status', 'active')
            ->where('created_at', '>=', now()->startOfWeek())
            ->count();

        $latestRiskIds = RiskAssessment::latestIds();

        $atRiskCount = RiskAssessment::whereIn('id', $latestRiskIds)
            ->where('risk_level', 'high')
            ->count();

        // Students NEWLY flagged high today: their current assessment is high
        // and was made today, and the one before it (if any) wasn't high. This
        // used to count every high assessment made today, so one student
        // re-assessed twice was "2 new flags" and one who was already high
        // yesterday counted again.
        $newFlagsToday = RiskAssessment::whereIn('id', $latestRiskIds)
            ->where('risk_level', 'high')
            ->whereDate('assessed_at', today())
            ->whereRaw("coalesce((select prev.risk_level from risk_assessments prev where prev.student_id = risk_assessments.student_id and prev.id < risk_assessments.id order by prev.id desc limit 1), 'none') <> 'high'")
            ->count();

        $pendingReferrals = Referral::where('status', 'pending')->count();

        $awaitingAction = Referral::whereIn('status', ['pending', 'in_progress'])->count();

        $smsSentThisMonth = SmsLog::where('status', 'sent')
            ->whereMonth('sent_at', now()->month)
            ->whereYear('sent_at', now()->year)
            ->count();

        $totalSmsThisMonth = SmsLog::whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();

        $smsDeliveryRate = $totalSmsThisMonth > 0
            ? round(($smsSentThisMonth / $totalSmsThisMonth) * 100)
            : 0;

        // ── Recent Referrals ─────────────────────────────────────

        $recentReferrals = Referral::with(['student', 'referredBy'])
            ->latest()
            ->take(6)
            ->get();

        // ── Risk Distribution ────────────────────────────────────

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

        // ── AI Predictive Insights ───────────────────────────────
        
        $highRiskAssessments = RiskAssessment::whereIn('id', $latestRiskIds)
            ->where('risk_level', 'high')
            ->get();

        $totalHighRisk = $highRiskAssessments->count() ?: 1;
        $aiInsightsRaw = [
            'Frequent Referrals' => 0,
            'Behavioral Concerns' => 0,
            'Academic Concerns' => 0,
            'Peer/Emotional Conflicts' => 0,
        ];

        foreach ($highRiskAssessments as $assessment) {
            if ($assessment->previous_referrals_count >= 3) $aiInsightsRaw['Frequent Referrals']++;
            if ($assessment->behavioral_reports_count >= 1 || $assessment->concern_type_encoded == 2) $aiInsightsRaw['Behavioral Concerns']++;
            if ($assessment->concern_type_encoded == 1) $aiInsightsRaw['Academic Concerns']++;
            if (in_array($assessment->concern_type_encoded, [3, 5])) $aiInsightsRaw['Peer/Emotional Conflicts']++;
        }

        $aiPredictiveInsights = collect($aiInsightsRaw)->map(function ($c) use ($totalHighRisk) {
            return round(($c / $totalHighRisk) * 100);
        })->filter(function($pct) { return $pct > 0; })->sortDesc()->take(3)->toArray();

        // ── Upcoming Seminars ────────────────────────────────────

        $upcomingSeminars = Seminar::where('date', '>=', today())
            ->whereIn('status', ['upcoming', 'ongoing'])
            ->orderBy('date')
            ->take(3)
            ->get();

        // ── Monthly Referral Chart (last 6 months) ───────────────

        $months         = collect();
        $monthLabels    = collect();
        $monthlyReferrals = collect();
        $monthlyResolved  = collect();

        for ($i = 5; $i >= 0; $i--) {
            // startOfMonth first: subMonths() from the 29th-31st overflows into the wrong month.
            $month = now()->startOfMonth()->subMonths($i);
            $monthLabels->push($month->format('M'));

            $monthlyReferrals->push(
                Referral::whereMonth('created_at', $month->month)
                        ->whereYear('created_at', $month->year)
                        ->count()
            );

            $monthlyResolved->push(
                Referral::where('status', 'resolved')
                        ->whereMonth('resolved_at', $month->month)
                        ->whereYear('resolved_at', $month->year)
                        ->count()
            );
        }

        // ── Recent Activity Feed ─────────────────────────────────

        $recentActivities = $this->getRecentActivities();

        // "Needs attention" strip - school-wide (no counselor scope).
        $attentionTiles = app(AttentionService::class)->tiles(null);

        return view('admin.dashboard', compact(
            'attentionTiles',
            'totalStudents',
            'newStudentsThisWeek',
            'atRiskCount',
            'newFlagsToday',
            'pendingReferrals',
            'awaitingAction',
            'smsSentThisMonth',
            'smsDeliveryRate',
            'recentReferrals',
            'riskDistribution',
            'aiPredictiveInsights',
            'upcomingSeminars',
            'monthLabels',
            'monthlyReferrals',
            'monthlyResolved',
            'recentActivities'
        ));
    }

    // ── Activity Feed Builder ────────────────────────────────────

    private function getRecentActivities(): array
    {
        $activities = [];

        // High-risk flags — students who are high risk NOW (their latest
        // assessment). The feed used to list any historical high assessment,
        // so a student who has since dropped to Low was still "flagged".
        $riskFlags = RiskAssessment::with('student')
            ->whereIn('id', RiskAssessment::latestIds())
            ->where('risk_level', 'high')
            ->latest('assessed_at')
            ->take(3)
            ->get();

        foreach ($riskFlags as $flag) {
            $activities[] = [
                'type'    => 'risk',
                'message' => "<strong>" . e($flag->student->full_name) . "</strong> flagged as <strong>High Risk</strong> by the ML system",
                'time'    => $flag->assessed_at->diffForHumans(),
                'sort'    => $flag->assessed_at,
            ];
        }

        // SMS sent
        // sent_at can be missing on a row marked sent; fall back to created_at
        // rather than letting one bad row take the whole dashboard down.
        $smsLogs = SmsLog::with('student')
            ->where('status', 'sent')
            ->orderByRaw('coalesce(sent_at, created_at) desc')
            ->take(2)
            ->get();

        foreach ($smsLogs as $sms) {
            $when = $sms->sent_at ?? $sms->created_at;
            $activities[] = [
                'type'    => 'sms',
                'message' => "SMS sent to parent of <strong>" . e($sms->student?->full_name) . "</strong> regarding referral",
                'time'    => $when->diffForHumans(),
                'sort'    => $when,
            ];
        }

        // Resolved referrals
        // A resolved referral can have no resolved_at (resolved by an older
        // code path that skipped the bookkeeping): use updated_at instead of
        // crashing the dashboard on ->diffForHumans() of null.
        $resolved = Referral::with(['student', 'counselor'])
            ->where('status', 'resolved')
            ->orderByRaw('coalesce(resolved_at, updated_at) desc')
            ->take(2)
            ->get();

        foreach ($resolved as $ref) {
            $counselorName = $ref->counselor?->name ?? 'the counselor';
            $when = $ref->resolved_at ?? $ref->updated_at;
            $activities[] = [
                'type'    => 'resolved',
                'message' => "<strong>" . e($ref->student->full_name) . "</strong> referral marked as <strong>Resolved</strong> by " . e($counselorName),
                'time'    => $when->diffForHumans(),
                'sort'    => $when,
            ];
        }

        // Seminars added
        $seminars = Seminar::latest()->take(1)->get();
        foreach ($seminars as $sem) {
            $activities[] = [
                'type'    => 'seminar',
                'message' => "New seminar <strong>\"" . e($sem->title) . "\"</strong> scheduled for {$sem->date->format('M d')}",
                'time'    => $sem->created_at->diffForHumans(),
                'sort'    => $sem->created_at,
            ];
        }

        // Sort all by most recent
        usort($activities, fn($a, $b) => $b['sort'] <=> $a['sort']);

        return array_slice($activities, 0, 6);
    }
}
