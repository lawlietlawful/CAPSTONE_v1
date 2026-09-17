<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\Referral;
use App\Models\RiskAssessment;
use App\Models\Seminar;
use App\Models\SmsLog;
use App\Models\Notification;
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

        $atRiskCount = RiskAssessment::whereIn('id', function ($query) {
                $query->select(DB::raw('MAX(id)'))
                      ->from('risk_assessments')
                      ->groupBy('student_id');
            })
            ->where('risk_level', 'high')
            ->count();

        $newFlagsToday = RiskAssessment::where('risk_level', 'high')
            ->whereDate('assessed_at', today())
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

        $latestRiskIds = DB::table('risk_assessments')
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
            $month = now()->subMonths($i);
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

        // ── Unread Notifications ─────────────────────────────────

        $unreadNotifications = Notification::where('user_id', auth()->id())
            ->where('is_read', false)
            ->count();

        return view('admin.dashboard', compact(
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
            'recentActivities',
            'unreadNotifications'
        ));
    }

    // ── Activity Feed Builder ────────────────────────────────────

    private function getRecentActivities(): array
    {
        $activities = [];

        // High-risk flags
        $riskFlags = RiskAssessment::with('student')
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
        $smsLogs = SmsLog::with('student')
            ->where('status', 'sent')
            ->latest('sent_at')
            ->take(2)
            ->get();

        foreach ($smsLogs as $sms) {
            $activities[] = [
                'type'    => 'sms',
                'message' => "SMS sent to parent of <strong>" . e($sms->student?->full_name) . "</strong> regarding referral",
                'time'    => $sms->sent_at->diffForHumans(),
                'sort'    => $sms->sent_at,
            ];
        }

        // Resolved referrals
        $resolved = Referral::with(['student', 'counselor'])
            ->where('status', 'resolved')
            ->latest('resolved_at')
            ->take(2)
            ->get();

        foreach ($resolved as $ref) {
            $counselorName = $ref->counselor?->name ?? 'the counselor';
            $activities[] = [
                'type'    => 'resolved',
                'message' => "<strong>" . e($ref->student->full_name) . "</strong> referral marked as <strong>Resolved</strong> by " . e($counselorName),
                'time'    => $ref->resolved_at->diffForHumans(),
                'sort'    => $ref->resolved_at,
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
