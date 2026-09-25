<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Referral;
use App\Models\RiskAssessment;
use App\Models\Student;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class RiskController extends Controller
{
    private const CSV_COLUMNS = ['Student ID', 'Student Name', 'Course & Year', 'Risk Level', 'Risk Score', 'Referrals', 'Incidents', 'Factors', 'Last Assessed'];

    private const SORTS = ['risk_score', 'previous_referrals_count', 'behavioral_reports_count', 'assessed_at'];

    /**
     * IDs of each student's LATEST assessment. "Latest" is the highest id
     * everywhere on this page (list, counts, detail page) — the detail page
     * used to order by assessed_at instead, so the two could disagree about
     * a student's current risk level.
     *
     * "My Students" (?scope=mine) narrows this — summary counts included —
     * to students in the viewing counselor's own referral scope: assigned to
     * them, unclaimed, or with no referral at all yet (a student flagged only
     * through behavioral reports is as unclaimed as it gets). Only meaningful
     * for a counselor; a super_admin has no personal scope, so it's a no-op.
     */
    private function latestAssessmentIds(Request $request)
    {
        $query = DB::table('risk_assessments')
            ->select(DB::raw('MAX(id) as id'))
            ->groupBy('student_id');

        if ($request->get('scope') === 'mine' && auth()->user()->role === 'admin') {
            $query->where(function ($q) {
                $q->whereNotIn('student_id', Referral::select('student_id'))
                  ->orWhereIn('student_id', Referral::where(function ($r) {
                      $r->where('counselor_id', auth()->id())->orWhereNull('counselor_id');
                  })->select('student_id'));
            });
        }

        return $query->pluck('id');
    }

    /**
     * Every list filter and the sort, applied in one place. Shared by
     * index() and export() so an export always matches the screen it was
     * taken from.
     */
    private function filteredQuery(Request $request, $latestIds)
    {
        $query = RiskAssessment::with('student')->whereIn('id', $latestIds);

        if ($request->filled('risk_level')) {
            $query->where('risk_level', $request->risk_level);
        }

        if ($request->filled('search')) {
            $like = '%' . trim($request->search) . '%';
            $query->whereHas('student', function ($q) use ($like) {
                $q->where(function ($w) use ($like) {
                    $w->where('first_name', 'like', $like)
                      ->orWhere('last_name', 'like', $like)
                      ->orWhere('student_id_number', 'like', $like)
                      // So "Maria Santos" / "Santos, Maria" find the student.
                      ->orWhereRaw("CONCAT(first_name, ' ', last_name) like ?", [$like])
                      ->orWhereRaw("CONCAT(last_name, ' ', first_name) like ?", [$like])
                      ->orWhereRaw("CONCAT(last_name, ', ', first_name) like ?", [$like]);
                });
            });
        }

        $sort = in_array($request->input('sort'), self::SORTS, true) ? $request->input('sort') : 'risk_score';
        $dir = $request->input('dir') === 'asc' ? 'asc' : 'desc';

        // The id tiebreak keeps equal values in a stable order across pages.
        return $query->orderBy($sort, $dir)->orderByDesc('id');
    }

    public function index(Request $request)
    {
        $latestRiskIds = $this->latestAssessmentIds($request);

        $assessments = $this->filteredQuery($request, $latestRiskIds)
            ->with(['student.referrals' => fn ($r) => $r->whereIn('status', ['pending', 'in_progress'])])
            ->paginate(20)
            ->appends($request->query());

        // Fetch previous assessments for trend indicators
        $studentIds = $assessments->pluck('student_id');
        $assessmentIds = $assessments->pluck('id');

        if ($studentIds->isNotEmpty()) {
            $previousAssessments = RiskAssessment::whereIn('student_id', $studentIds)
                ->whereNotIn('id', $assessmentIds)
                ->orderBy('id', 'desc')
                ->get()
                ->groupBy('student_id');

            foreach ($assessments as $assessment) {
                $assessment->setRelation('previousAssessment', $previousAssessments->get($assessment->student_id)?->first());
            }
        }

        // Summary counts based on latest assessments
        $totalAssessed = count($latestRiskIds);
        $highRiskCount = RiskAssessment::whereIn('id', $latestRiskIds)->where('risk_level', 'high')->count();
        $moderateRiskCount = RiskAssessment::whereIn('id', $latestRiskIds)->where('risk_level', 'moderate')->count();
        $lowRiskCount = RiskAssessment::whereIn('id', $latestRiskIds)->where('risk_level', 'low')->count();

        $counselors = User::where('role', 'admin')->orderBy('name')->get();
        $scopedToMe = $request->get('scope') === 'mine' && auth()->user()->role === 'admin';

        return view('admin.risk.index', compact(
            'assessments', 'totalAssessed', 'highRiskCount', 'moderateRiskCount', 'lowRiskCount', 'counselors', 'scopedToMe'
        ));
    }

    public function show($id)
    {
        // Get the student and their risk assessment history
        $student = Student::with(['riskAssessments' => function($q) {
            $q->orderByDesc('id');
        }, 'referrals' => function($q) {
            $q->latest();
        }, 'behavioralReports'])->findOrFail($id);

        $latestAssessment = $student->riskAssessments->first();

        // Check if there are no assessments
        if (!$latestAssessment) {
            return redirect()->route('admin.risk.index')->with('error', 'No risk assessment found for this student.');
        }

        $counselors = User::where('role', 'admin')->orderBy('name')->get();

        return view('admin.risk.show', compact('student', 'latestAssessment', 'counselors'));
    }

    public function export(Request $request)
    {
        $assessments = $this->filteredQuery($request, $this->latestAssessmentIds($request))->get();

        return $this->streamCsv($assessments, 'at_risk_students_export_');
    }

    public function bulkAction(Request $request)
    {
        $request->validate([
            'assessment_ids' => 'required|array',
            'assessment_ids.*' => 'exists:risk_assessments,id',
            'action' => 'required|in:export_selected,assign_counselor',
            // Must be an actual counselor — a bare exists:users,id also
            // accepted a teacher's or student's id.
            'assign_counselor_id' => ['required_if:action,assign_counselor', 'nullable', Rule::exists('users', 'id')->where('role', 'admin')],
        ]);

        if ($request->action === 'export_selected') {
            return $this->exportSelected($request->assessment_ids);
        }

        return $this->assignCounselor($request->assessment_ids, (int) $request->assign_counselor_id);
    }

    /**
     * Put the selected students in front of a counselor. Never duplicates
     * work: a student who already has an open referral gets THAT referral
     * (re)assigned; only a student with none gets a new one. Assessments
     * that are no longer a student's latest (a stale tab) are skipped, so an
     * old high score can't spawn a referral after the student's risk dropped.
     */
    private function assignCounselor(array $ids, int $counselorId)
    {
        $latestIds = DB::table('risk_assessments')->select(DB::raw('MAX(id) as id'))->groupBy('student_id')->pluck('id');

        $selected = RiskAssessment::whereIn('id', $ids)->get();
        $assessments = $selected->whereIn('id', $latestIds);
        $outdated = $selected->count() - $assessments->count();

        $created = $reassigned = $unchanged = 0;

        foreach ($assessments as $assessment) {
            $open = Referral::where('student_id', $assessment->student_id)
                ->whereIn('status', ['pending', 'in_progress'])
                ->latest('id')
                ->first();

            if ($open) {
                if ($open->counselor_id === $counselorId) {
                    $unchanged++;
                } else {
                    $open->update(['counselor_id' => $counselorId]);
                    $reassigned++;
                }
                continue;
            }

            $level = $assessment->risk_level;
            $referral = Referral::create([
                'student_id'         => $assessment->student_id,
                'referred_by'        => auth()->id(),
                'counselor_id'       => $counselorId,
                'referral_type'      => 'Automated Risk Alert',
                'concern_type'       => Referral::concernTypeFor('Automated Risk Alert'),
                'reason'             => 'Automatically referred due to ' . ucfirst($level) . ' Risk Assessment score (' . number_format($assessment->risk_score, 1) . ').',
                'priority'           => in_array($level, ['high', 'moderate'], true) ? $level : 'low',
                'status'             => 'pending',
                'risk_assessment_id' => $assessment->id,
            ]);

            app(NotificationService::class)->newPendingReferral($referral);
            $created++;
        }

        $parts = [];
        if ($created)    $parts[] = "$created new referral(s) created";
        if ($reassigned) $parts[] = "$reassigned existing open referral(s) reassigned";
        if ($unchanged)  $parts[] = "$unchanged already with this counselor";
        if ($outdated)   $parts[] = "$outdated skipped (assessment no longer the latest — refresh the page)";

        if (! $created && ! $reassigned) {
            return redirect()->back()->with('error', 'Nothing changed: ' . ($parts ? implode('; ', $parts) : 'no eligible students selected') . '.');
        }

        return redirect()->back()->with('success', ucfirst(implode('; ', $parts)) . '.');
    }

    public function exportSelected(array $ids)
    {
        $assessments = RiskAssessment::with('student')->whereIn('id', $ids)->orderByDesc('risk_score')->get();

        return $this->streamCsv($assessments, 'at_risk_export_selected_');
    }

    private function streamCsv($assessments, string $filenamePrefix)
    {
        $headers = [
            "Content-type"        => "text/csv",
            "Content-Disposition" => 'attachment; filename="' . $filenamePrefix . date('Y-m-d_H-i') . '.csv"',
            "Pragma"              => "no-cache",
            "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
            "Expires"             => "0"
        ];

        $callback = function () use ($assessments) {
            $file = fopen('php://output', 'w');
            fputcsv($file, self::CSV_COLUMNS);
            foreach ($assessments as $assessment) {
                $student = $assessment->student;
                fputcsv($file, array_map([$this, 'csvSafe'], [
                    $student->student_id_number,
                    $student->last_name . ', ' . $student->first_name,
                    ($student->course ?? $student->grade_level) . ' - ' . $student->section,
                    ucfirst($assessment->risk_level),
                    number_format($assessment->risk_score, 1),
                    $assessment->previous_referrals_count,
                    $assessment->behavioral_reports_count,
                    implode(', ', $this->factorsToArray($assessment->risk_factors)),
                    $assessment->assessed_at->format('Y-m-d H:i'),
                ]));
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Spreadsheet apps run a cell starting with = + - @ (or tab/CR) as a
     * formula, so a student name like =HYPERLINK(...) would execute when the
     * export is opened. A leading apostrophe makes it plain text.
     */
    private function csvSafe($value)
    {
        if (is_string($value) && $value !== '' && strpbrk($value[0], "=+-@\t\r") !== false) {
            return "'" . $value;
        }

        return $value;
    }

    /**
     * Normalize risk_factors into a plain array for display.
     *
     * The model casts risk_factors as 'array', so it's normally already
     * decoded here. This also tolerates historical rows that were
     * accidentally double-JSON-encoded by an older version of
     * RiskAssessmentService (fixed, but existing rows may still be affected)
     * by attempting one extra decode rather than fatally erroring on implode().
     */
    private function factorsToArray($riskFactors): array
    {
        if (is_array($riskFactors)) {
            return $riskFactors;
        }

        if (is_string($riskFactors)) {
            $decoded = json_decode($riskFactors, true);
            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }
}
