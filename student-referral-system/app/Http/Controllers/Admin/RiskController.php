<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Referral;
use App\Models\RiskAssessment;
use App\Models\Student;
use Illuminate\Support\Facades\DB;

class RiskController extends Controller
{
    public function index(Request $request)
    {
        // Get the IDs of the latest risk assessments for each student
        $latestRiskIds = DB::table('risk_assessments')
            ->select(DB::raw('MAX(id) as id'))
            ->groupBy('student_id')
            ->pluck('id');

        // "My Students" (?scope=mine): narrows the whole page — summary
        // counts included — to students in the viewing counselor's own
        // referral scope (assigned to them, or unclaimed), the same "mine or
        // unclaimed" rule the counselor dashboard's own Watchlist uses. Only
        // meaningful for a counselor; a super_admin has no personal scope to
        // narrow to, so the param is a no-op for them.
        $scopedToMe = $request->get('scope') === 'mine' && auth()->user()->role === 'admin';
        if ($scopedToMe) {
            $myStudentIds = Referral::where(function ($q) {
                    $q->where('counselor_id', auth()->id())->orWhereNull('counselor_id');
                })
                ->distinct()
                ->pluck('student_id');

            $latestRiskIds = DB::table('risk_assessments')
                ->whereIn('student_id', $myStudentIds)
                ->select(DB::raw('MAX(id) as id'))
                ->groupBy('student_id')
                ->pluck('id');
        }

        $query = RiskAssessment::with(['student' => function($q) {
            $q->with(['referrals' => function($r) {
                $r->whereIn('status', ['pending', 'in_progress']);
            }]);
        }])->whereIn('id', $latestRiskIds);

        // Filter by Risk Level
        if ($request->filled('risk_level')) {
            $query->where('risk_level', $request->risk_level);
        }

        // Search by student name or ID
        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('student', function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('student_id_number', 'like', "%{$search}%");
            });
        }

        // Sort logic
        $sort = $request->input('sort', 'risk_score');
        $dir = $request->input('dir', 'desc');

        $allowedSorts = ['risk_score', 'previous_referrals_count', 'behavioral_reports_count', 'assessed_at'];
        if (in_array($sort, $allowedSorts)) {
            $query->orderBy($sort, $dir === 'asc' ? 'asc' : 'desc');
        } else {
            $query->orderBy('risk_score', 'desc');
        }

        $assessments = $query->paginate(20)->appends($request->query());

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

        $counselors = \App\Models\User::where('role', 'admin')->orderBy('name')->get();

        return view('admin.risk.index', compact(
            'assessments', 'totalAssessed', 'highRiskCount', 'moderateRiskCount', 'lowRiskCount', 'counselors', 'scopedToMe'
        ));
    }

    public function show($id)
    {
        // Get the student and their risk assessment history
        $student = Student::with(['riskAssessments' => function($q) {
            $q->latest('assessed_at');
        }, 'referrals' => function($q) {
            $q->latest();
        }, 'behavioralReports'])->findOrFail($id);

        $latestAssessment = $student->riskAssessments->first();

        // Check if there are no assessments
        if (!$latestAssessment) {
            return redirect()->route('admin.risk.index')->with('error', 'No risk assessment found for this student.');
        }

        $counselors = \App\Models\User::where('role', 'admin')->orderBy('name')->get();

        return view('admin.risk.show', compact('student', 'latestAssessment', 'counselors'));
    }

    public function export(Request $request)
    {
        $latestRiskIds = DB::table('risk_assessments')->select(DB::raw('MAX(id) as id'))->groupBy('student_id')->pluck('id');

        // Mirrors index()'s ?scope=mine — an export taken from the "My
        // Students" view must not silently include the whole school.
        if ($request->get('scope') === 'mine' && auth()->user()->role === 'admin') {
            $myStudentIds = Referral::where(function ($q) {
                    $q->where('counselor_id', auth()->id())->orWhereNull('counselor_id');
                })
                ->distinct()
                ->pluck('student_id');

            $latestRiskIds = DB::table('risk_assessments')
                ->whereIn('student_id', $myStudentIds)
                ->select(DB::raw('MAX(id) as id'))
                ->groupBy('student_id')
                ->pluck('id');
        }

        $query = RiskAssessment::with(['student'])->whereIn('id', $latestRiskIds);

        if ($request->filled('risk_level')) {
            $query->where('risk_level', $request->risk_level);
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('student', function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")->orWhere('last_name', 'like', "%{$search}%")->orWhere('student_id_number', 'like', "%{$search}%");
            });
        }

        $query->orderBy('risk_score', 'desc');
        $assessments = $query->get();

        $filename = "at_risk_students_export_" . date('Y-m-d_H-i') . ".csv";
        $headers = [
            "Content-type"        => "text/csv",
            "Content-Disposition" => "attachment; filename=$filename",
            "Pragma"              => "no-cache",
            "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
            "Expires"             => "0"
        ];
        $columns = ['Student ID', 'Student Name', 'Course & Year', 'Risk Level', 'Risk Score', 'Referrals', 'Incidents', 'Factors', 'Last Assessed'];

        $callback = function() use($assessments, $columns) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns);
            foreach ($assessments as $assessment) {
                $row['Student ID'] = $assessment->student->student_id_number;
                $row['Student Name'] = $assessment->student->last_name . ', ' . $assessment->student->first_name;
                $row['Course & Year'] = ($assessment->student->course ?? $assessment->student->grade_level) . ' - ' . $assessment->student->section;
                $row['Risk Level'] = ucfirst($assessment->risk_level);
                $row['Risk Score'] = number_format($assessment->risk_score, 1);
                $row['Referrals'] = $assessment->previous_referrals_count;
                $row['Incidents'] = $assessment->behavioral_reports_count;
                $row['Factors'] = implode(', ', $this->factorsToArray($assessment->risk_factors));
                $row['Last Assessed'] = $assessment->assessed_at->format('Y-m-d H:i');
                fputcsv($file, array($row['Student ID'], $row['Student Name'], $row['Course & Year'], $row['Risk Level'], $row['Risk Score'], $row['Referrals'], $row['Incidents'], $row['Factors'], $row['Last Assessed']));
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function bulkAction(Request $request)
    {
        $request->validate([
            'assessment_ids' => 'required|array',
            'assessment_ids.*' => 'exists:risk_assessments,id',
            'action' => 'required|in:export_selected,assign_counselor',
            'assign_counselor_id' => 'required_if:action,assign_counselor|nullable|exists:users,id'
        ]);

        $ids = $request->assessment_ids;
        $action = $request->action;

        if ($action === 'export_selected') {
            return $this->exportSelected($ids);
        } elseif ($action === 'assign_counselor') {
            // For assigning counselor, we need to create referrals for these at-risk students if they don't have one
            // Or just attach counselor_id to RiskAssessment if we added it, but RiskAssessment doesn't have counselor_id.
            // Let's create a pending referral for them.
            $assessments = RiskAssessment::whereIn('id', $ids)->get();
            $count = 0;
            foreach ($assessments as $assessment) {
                \App\Models\Referral::firstOrCreate(
                    [
                        'student_id' => $assessment->student_id,
                        'status' => 'pending',
                        'referral_type' => 'Automated Risk Alert'
                    ],
                    [
                        'referred_by' => auth()->id(),
                        'counselor_id' => $request->assign_counselor_id,
                        'reason' => 'Automatically referred due to High Risk Assessment score (' . number_format($assessment->risk_score, 1) . ').',
                        'priority' => $assessment->risk_level === 'high' ? 'high' : 'moderate',
                    ]
                );
                $count++;
            }
            return redirect()->back()->with('success', "Successfully created/assigned referrals for $count at-risk students to the selected counselor.");
        }
        
        return redirect()->back();
    }

    public function exportSelected(array $ids)
    {
        $assessments = RiskAssessment::with(['student'])->whereIn('id', $ids)->get();

        $filename = "at_risk_export_selected_" . date('Y-m-d_H-i') . ".csv";
        $headers = [
            "Content-type"        => "text/csv",
            "Content-Disposition" => "attachment; filename=$filename",
            "Pragma"              => "no-cache",
            "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
            "Expires"             => "0"
        ];
        $columns = ['Student ID', 'Student Name', 'Course & Year', 'Risk Level', 'Risk Score', 'Referrals', 'Incidents', 'Factors', 'Last Assessed'];

        $callback = function() use($assessments, $columns) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns);
            foreach ($assessments as $assessment) {
                $row['Student ID'] = $assessment->student->student_id_number;
                $row['Student Name'] = $assessment->student->last_name . ', ' . $assessment->student->first_name;
                $row['Course & Year'] = ($assessment->student->course ?? $assessment->student->grade_level) . ' - ' . $assessment->student->section;
                $row['Risk Level'] = ucfirst($assessment->risk_level);
                $row['Risk Score'] = number_format($assessment->risk_score, 1);
                $row['Referrals'] = $assessment->previous_referrals_count;
                $row['Incidents'] = $assessment->behavioral_reports_count;
                $row['Factors'] = implode(', ', $this->factorsToArray($assessment->risk_factors));
                $row['Last Assessed'] = $assessment->assessed_at->format('Y-m-d H:i');
                fputcsv($file, array($row['Student ID'], $row['Student Name'], $row['Course & Year'], $row['Risk Level'], $row['Risk Score'], $row['Referrals'], $row['Incidents'], $row['Factors'], $row['Last Assessed']));
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
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
