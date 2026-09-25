<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Referral;
use App\Models\RiskAssessment;
use App\Models\BehavioralReport;
use App\Models\StudentSeminar;
use App\Models\User;
use App\Models\Student;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;

class AnalyticsController extends Controller
{
    public function index(Request $request)
    {
        return view('admin.analytics.index', $this->buildAnalyticsData($request));
    }

    public function exportPdf(Request $request)
    {
        $data = $this->buildAnalyticsData($request);
        $data['rangeLabel'] = $this->rangeLabel($data['dateRange'], $data['startDate'], $data['endDate']);
        $data['generatedAt'] = Carbon::now()->format('F d, Y \a\t h:i A');

        $pdf = Pdf::loadView('admin.analytics.pdf', $data)->setPaper('a4', 'portrait');

        $filename = 'analytics-report-' . Carbon::now()->format('Y-m-d') . '.pdf';

        return $pdf->download($filename);
    }

    /**
     * Shared data builder used by both the on-screen dashboard and the PDF
     * export, so the two always agree on the same numbers for the same filter.
     */
    private function buildAnalyticsData(Request $request): array
    {
        $dateRange = $request->get('date_range', 'all_time');
        [$startDate, $endDate, $semesterConfigured] = $this->resolveDateRange($request, $dateRange);

        // 1. KPIs
        $totalReferrals = Referral::when($startDate, fn ($q) => $q->whereBetween('created_at', [$startDate, $endDate]))
            ->count();

        $highRiskStudents = RiskAssessment::where('risk_level', 'high')
            ->when($startDate, fn ($q) => $q->whereBetween('assessed_at', [$startDate, $endDate]))
            ->count();

        // "Resolved within the period" is measured by when it was resolved, not when it was filed.
        $totalResolved = Referral::where('status', 'resolved')
            ->when($startDate, fn ($q) => $q->whereBetween('resolved_at', [$startDate, $endDate]))
            ->count();

        // Top Concern Type
        $topConcern = Referral::select('concern_type', DB::raw('count(*) as total'))
            ->when($startDate, fn ($q) => $q->whereBetween('created_at', [$startDate, $endDate]))
            ->groupBy('concern_type')
            ->orderByDesc('total')
            ->first();
        $topConcernType = $topConcern ? ucfirst($topConcern->concern_type) : 'N/A';

        // Average Resolution Time (days between filing and resolution),
        // measured against when the referral was actually resolved.
        $avgResolutionDays = Referral::where('status', 'resolved')
            ->whereNotNull('resolved_at')
            ->when($startDate, fn ($q) => $q->whereBetween('resolved_at', [$startDate, $endDate]))
            ->selectRaw('AVG(DATEDIFF(resolved_at, created_at)) as avg_days')
            ->value('avg_days');
        $avgResolutionDays = $avgResolutionDays !== null ? round((float) $avgResolutionDays, 1) : null;

        // Seminar Intervention Effectiveness — pre/post risk score comparison
        // tracked by the seminars:track-effectiveness scheduled command,
        // 30 days after a student attends an assigned seminar.
        $effectivenessRows = StudentSeminar::whereNotNull('effectiveness')
            ->when($startDate, fn ($q) => $q->whereBetween('attended_at', [$startDate, $endDate]))
            ->select('effectiveness', DB::raw('count(*) as total'))
            ->groupBy('effectiveness')
            ->pluck('total', 'effectiveness');

        $seminarEffectiveness = [
            'improved' => (int) ($effectivenessRows['improved'] ?? 0),
            'no_change' => (int) ($effectivenessRows['no_change'] ?? 0),
            'worse' => (int) ($effectivenessRows['worse'] ?? 0),
        ];
        $seminarEffectivenessTotal = array_sum($seminarEffectiveness);
        $seminarEffectivenessPct = $seminarEffectivenessTotal > 0
            ? round(($seminarEffectiveness['improved'] / $seminarEffectivenessTotal) * 100)
            : null;

        // 2. Charts Data
        // Monthly Referral Trend (Last 6 Months) — always a fixed 6-month rolling
        // window, independent of the date filter above.
        $monthlyReferrals = [];
        $months = [];
        for ($i = 5; $i >= 0; $i--) {
            // startOfMonth first: subMonths() from the 29th-31st overflows into the wrong month.
            $date = Carbon::now()->startOfMonth()->subMonths($i);
            $monthName = $date->format('M Y');
            $months[] = $monthName;
            $count = Referral::whereYear('created_at', $date->year)
                ->whereMonth('created_at', $date->month)
                ->count();
            $monthlyReferrals[] = $count;
        }
        $trendChartData = [
            'labels' => $months,
            'data' => $monthlyReferrals
        ];

        // Concern Categories Distribution
        $concerns = Referral::select('concern_type', DB::raw('count(*) as total'))
            ->when($startDate, fn ($q) => $q->whereBetween('created_at', [$startDate, $endDate]))
            ->groupBy('concern_type')
            ->get();
        $concernChartData = [
            'labels' => $concerns->pluck('concern_type')->map(fn($c) => ucfirst($c))->toArray(),
            'data' => $concerns->pluck('total')->toArray()
        ];

        // Risk Level Distribution
        $risks = RiskAssessment::select('risk_level', DB::raw('count(*) as total'))
            ->when($startDate, fn ($q) => $q->whereBetween('assessed_at', [$startDate, $endDate]))
            ->groupBy('risk_level')
            ->get();
        $riskChartData = [
            'labels' => $risks->pluck('risk_level')->toArray(),
            'data' => $risks->pluck('total')->toArray()
        ];

        // Incident Severity (Behavioral Reports)
        $severities = BehavioralReport::select('severity', DB::raw('count(*) as total'))
            ->when($startDate, fn ($q) => $q->whereBetween('incident_date', [$startDate, $endDate]))
            ->groupBy('severity')
            ->get();
        $severityChartData = [
            'labels' => $severities->pluck('severity')->map(fn($s) => ucfirst($s))->toArray(),
            'data' => $severities->pluck('total')->toArray()
        ];

        // Concern Type × Risk Level Breakdown — cross-tab of each referral's
        // concern type against the risk level of its own associated
        // assessment (referrals.risk_assessment_id), not just any assessment
        // for that student.
        $concernRiskRows = Referral::join('risk_assessments', 'referrals.risk_assessment_id', '=', 'risk_assessments.id')
            ->when($startDate, fn ($q) => $q->whereBetween('referrals.created_at', [$startDate, $endDate]))
            ->select('referrals.concern_type', 'risk_assessments.risk_level', DB::raw('count(*) as total'))
            ->groupBy('referrals.concern_type', 'risk_assessments.risk_level')
            ->get();

        $concernRiskMatrix = [];
        foreach ($concernRiskRows as $row) {
            $concernRiskMatrix[$row->concern_type][$row->risk_level] = (int) $row->total;
        }
        uasort($concernRiskMatrix, fn ($a, $b) => array_sum($b) <=> array_sum($a));

        // 3. Insight Tables
        // Top Referring Teachers
        $topTeachers = User::where('role', 'teacher')
            ->withCount(['referralsReferred' => function ($q) use ($startDate, $endDate) {
                if ($startDate) {
                    $q->whereBetween('created_at', [$startDate, $endDate]);
                }
            }])
            ->orderByDesc('referrals_referred_count')
            ->take(10)
            ->get();

        // Referrals by Course (Year & Section)
        $topCourses = Student::select(
                DB::raw("CONCAT(course, ' ', grade_level, '-', section) as course_name"),
                DB::raw('count(referrals.id) as referral_count')
            )
            ->join('referrals', 'students.id', '=', 'referrals.student_id')
            ->when($startDate, fn ($q) => $q->whereBetween('referrals.created_at', [$startDate, $endDate]))
            ->groupBy('course', 'grade_level', 'section')
            ->orderByDesc('referral_count')
            ->take(10)
            ->get();

        return compact(
            'totalReferrals', 'highRiskStudents', 'totalResolved', 'topConcernType',
            'avgResolutionDays', 'seminarEffectiveness', 'seminarEffectivenessTotal', 'seminarEffectivenessPct',
            'concernRiskMatrix',
            'trendChartData', 'concernChartData', 'riskChartData', 'severityChartData',
            'topTeachers', 'topCourses',
            'dateRange', 'semesterConfigured', 'startDate', 'endDate'
        ) + [
            'customStartDate' => $request->get('start_date'),
            'customEndDate' => $request->get('end_date'),
        ];
    }

    /**
     * Resolve the selected date_range option into a [start, end] Carbon pair.
     * Returns [null, null, ...] for 'all_time' so callers can skip filtering
     * entirely via when($startDate, ...) rather than filtering on a null range.
     */
    private function resolveDateRange(Request $request, string $dateRange): array
    {
        $semesterConfigured = Setting::get('semester_start_date') && Setting::get('semester_end_date');

        switch ($dateRange) {
            case 'today':
                return [Carbon::today()->startOfDay(), Carbon::today()->endOfDay(), $semesterConfigured];

            case 'this_week':
                return [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek(), $semesterConfigured];

            case 'this_month':
                return [Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth(), $semesterConfigured];

            case 'last_month':
                return [
                    Carbon::now()->subMonthNoOverflow()->startOfMonth(),
                    Carbon::now()->subMonthNoOverflow()->endOfMonth(),
                    $semesterConfigured,
                ];

            case 'this_semester':
                if ($semesterConfigured) {
                    return [
                        Carbon::parse(Setting::get('semester_start_date'))->startOfDay(),
                        Carbon::parse(Setting::get('semester_end_date'))->endOfDay(),
                        $semesterConfigured,
                    ];
                }
                // Not configured yet — fall back to all-time rather than
                // silently guessing semester boundaries.
                return [null, null, $semesterConfigured];

            case 'custom':
                $start = $request->get('start_date');
                $end = $request->get('end_date');
                if ($start && $end) {
                    return [Carbon::parse($start)->startOfDay(), Carbon::parse($end)->endOfDay(), $semesterConfigured];
                }
                return [null, null, $semesterConfigured];

            default: // all_time
                return [null, null, $semesterConfigured];
        }
    }

    /**
     * Human-readable description of the active filter, shown on the PDF report.
     */
    private function rangeLabel(string $dateRange, ?Carbon $startDate, ?Carbon $endDate): string
    {
        $labels = [
            'today' => 'Today',
            'this_week' => 'This Week',
            'this_month' => 'This Month',
            'last_month' => 'Last Month',
            'this_semester' => 'This Semester',
            'custom' => 'Custom Range',
        ];

        $label = $labels[$dateRange] ?? 'All Time';

        if ($startDate && $endDate) {
            $label .= ' (' . $startDate->format('M d, Y') . ' – ' . $endDate->format('M d, Y') . ')';
        }

        return $label;
    }
}
