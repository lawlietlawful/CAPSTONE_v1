<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\BehavioralReport;
use App\Models\Student;
use App\Models\User;
use App\Services\BehavioralReportService;

abstract class AbstractBehavioralReportController extends Controller
{
    public function __construct(protected BehavioralReportService $reportService)
    {
    }

    /**
     * 'admin' or 'counselor': which view set and route family this
     * controller serves. Everything else is shared, so a fix (a filter, the
     * export, the refer action) lands in one place instead of drifting
     * between two copies the way it used to.
     */
    abstract protected function area(): string;

    /**
     * Every index filter, applied in one place. Shared by index() and
     * export() — export() used to carry its own shorter copy that never
     * learned about the search box, so a searched-for export still
     * contained every report.
     */
    private function filteredQuery(Request $request)
    {
        $query = BehavioralReport::with(['student', 'reportedBy'])->latest();

        if ($request->filled('severity')) {
            $query->where('severity', $request->severity);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('incident_type')) {
            $query->where('incident_type', $request->incident_type);
        }

        // Search by student name / ID
        if ($request->filled('search')) {
            $query->whereHas('student', fn ($q) => $q->matchingSearch($request->search));
        }

        // Filter by reported_by (Teacher)
        if ($request->filled('reported_by_id')) {
            $query->where('reported_by', $request->reported_by_id);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('incident_date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('incident_date', '<=', $request->date_to);
        }

        return $query;
    }

    public function index(Request $request)
    {
        $reports = $this->filteredQuery($request)->paginate(10)->appends($request->query());

        // Summary stats
        $totalReports   = BehavioralReport::count();
        $pendingCount   = BehavioralReport::where('status', 'pending')->count();
        $reviewedCount  = BehavioralReport::where('status', 'reviewed')->count();
        $resolvedCount  = BehavioralReport::where('status', 'resolved')->count();

        // Get distinct incident types for filter dropdown
        $incidentTypes = BehavioralReport::select('incident_type')
            ->distinct()
            ->orderBy('incident_type')
            ->pluck('incident_type');

        // Get teachers for filter dropdown
        $teachers = User::where('role', 'teacher')->orderBy('name')->get();


        return view($this->area() . '.behavioral-reports.index', compact(
            'reports', 'totalReports', 'pendingCount', 'reviewedCount', 'resolvedCount', 'incidentTypes', 'teachers'
        ));
    }

    public function show(BehavioralReport $behavioral_report)
    {
        $behavioral_report->load(['student', 'reportedBy', 'escalatedReferral']);

        // The AI Risk Assessment panel already cites a count of this
        // student's prior reports — this turns that number into something
        // the counselor/admin can actually inspect instead of taking on faith.
        $otherReportsQuery = BehavioralReport::where('student_id', $behavioral_report->student_id)
            ->where('id', '!=', $behavioral_report->id);
        $otherReportsCount = $otherReportsQuery->count();
        $otherReports = $otherReportsQuery->latest('incident_date')->take(5)->get();

        return view($this->area() . '.behavioral-reports.show', compact('behavioral_report', 'otherReports', 'otherReportsCount'));
    }

    public function print(BehavioralReport $behavioral_report)
    {
        $behavioral_report->load(['student', 'reportedBy', 'escalatedReferral']);

        return view($this->area() . '.behavioral-reports.print', compact('behavioral_report'));
    }

    public function updateStatus(Request $request, BehavioralReport $behavioral_report)
    {
        $request->validate([
            'status' => 'required|in:pending,reviewed,resolved',
            'counselor_notes' => 'nullable|string',
        ]);

        $this->reportService->updateStatus($behavioral_report, $request->status, $request->counselor_notes);

        return redirect()->back()->with('success', 'Report updated successfully.');
    }

    /**
     * Open a referral for a report that did not auto-escalate.
     */
    public function refer(Request $request, BehavioralReport $behavioral_report)
    {
        $data = $request->validate([
            'counselor_id' => ['nullable', \Illuminate\Validation\Rule::exists('users', 'id')->where('role', 'admin')],
        ]);

        // A counselor files it to themselves by default; a super admin leaves it unassigned unless chosen.
        $counselorId = $data['counselor_id'] ?? (auth()->user()->role === 'admin' ? auth()->id() : null);

        try {
            $referral = $this->reportService->referManually($behavioral_report, $request->user(), $counselorId ? (int) $counselorId : null);
        } catch (\DomainException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', "Referral #{$referral->id} created from this report.");
    }

    public function bulkAction(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:behavioral_reports,id',
            'action' => 'required|in:mark_reviewed,mark_resolved'
        ]);

        $status = $request->action === 'mark_reviewed' ? 'reviewed' : 'resolved';

        BehavioralReport::whereIn('id', $request->ids)->get()->each(
            fn (BehavioralReport $report) => $this->reportService->updateStatus($report, $status, $report->counselor_notes)
        );

        return redirect()->back()->with('success', count($request->ids) . ' reports have been marked as ' . $status . '.');
    }

    public function export(Request $request)
    {
        $reports = $this->filteredQuery($request)->get();

        $headers = [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="behavioral_reports_' . date('Y-m-d') . '.csv"',
            'Pragma'              => 'no-cache',
            'Cache-Control'       => 'must-revalidate, post-check=0, pre-check=0',
            'Expires'             => '0',
        ];

        $columns = ['ID', 'Student Name', 'Student ID', 'Incident Type', 'Severity', 'Status', 'Reported By', 'Incident Date', 'Description'];

        // fputcsv quotes/escapes properly. The hand-built string this
        // replaced never escaped embedded double-quotes and rewrote commas
        // in the description to semicolons, corrupting the text it exported.
        $callback = function () use ($reports, $columns) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns);

            foreach ($reports as $report) {
                fputcsv($file, [
                    $report->id,
                    $report->student ? $report->student->last_name . ', ' . $report->student->first_name : 'N/A',
                    $report->student->student_id_number ?? 'N/A',
                    $report->incident_type,
                    $report->severity,
                    $report->status,
                    $report->reportedBy->name ?? 'N/A',
                    $report->incident_date,
                    $report->description,
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
