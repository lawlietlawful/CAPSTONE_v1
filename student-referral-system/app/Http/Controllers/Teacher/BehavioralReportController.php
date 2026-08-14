<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\BehavioralReport;
use App\Services\BehavioralReportService;

class BehavioralReportController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $reports = BehavioralReport::with('student')
                    ->where('reported_by', auth()->id())
                    ->latest()
                    ->paginate(15);
                    
        $students = $this->advisoryStudents()->get();

        return view('teacher.behavioral-reports.index', compact('reports', 'students'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $students = $this->advisoryStudents()->get();
        return view('teacher.behavioral-reports.create', compact('students'));
    }

    /**
     * Active students within the current teacher's course/grade/section
     * assignments (matching Teacher\ReferralController and
     * TeacherDashboardController), ordered by last name.
     */
    private function advisoryStudents()
    {
        return auth()->user()->advisedStudentsQuery()->orderBy('last_name');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request, BehavioralReportService $service)
    {
        $data = $request->validate([
            'student_id' => 'required|exists:students,id',
            // Locked to the fixed vocabulary: the escalation rules key off these
            // exact strings, so an unrecognised value would file a report that
            // silently never escalates.
            'incident_type' => 'required|in:' . implode(',', BehavioralReport::incidentTypeValues()),
            'incident_date' => 'required|date',
            'location' => 'nullable|string|max:100',
            'description' => 'required|string',
        ]);

        // ML severity assessment, persistence, and auto-escalation all live in
        // the shared service so the web form and the mobile API behave the same.
        $report = $service->create($request->user(), $data);

        $message = "Report submitted successfully. AI Assessed Severity: {$report->severity}.";
        if ($report->escalatedReferral) {
            $message .= ' It has been automatically escalated to a Guidance Referral.';
        }

        return redirect()->route('teacher.behavioral-reports.index')->with('success', $message);
    }

    /**
     * Display the specified resource.
     */
    public function show(BehavioralReport $behavioral_report)
    {
        // Must authorize that the teacher owns this report
        if ($behavioral_report->reported_by !== auth()->id()) {
            abort(403);
        }

        $behavioral_report->load('escalatedReferral');

        return view('teacher.behavioral-reports.show', compact('behavioral_report'));
    }
}
