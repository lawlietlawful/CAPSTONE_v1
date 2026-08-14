<?php

namespace App\Http\Controllers\Counselor;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\BehavioralReport;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\Response;

class BehavioralReportController extends Controller
{
    public function index(Request $request)
    {
        $query = BehavioralReport::with(['student', 'reportedBy'])->latest();

        // Filter by severity
        if ($request->filled('severity')) {
            $query->where('severity', $request->severity);
        }

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter by incident type
        if ($request->filled('incident_type')) {
            $query->where('incident_type', $request->incident_type);
        }

        // Search by student name
        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('student', function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('student_id_number', 'like', "%{$search}%");
            });
        }

        // Filter by reported_by (Teacher)
        if ($request->filled('reported_by_id')) {
            $query->where('reported_by', $request->reported_by_id);
        }

        // Date range
        if ($request->filled('date_from')) {
            $query->whereDate('incident_date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('incident_date', '<=', $request->date_to);
        }

        $reports = $query->paginate(20)->appends($request->query());

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

        // Analytics Data
        $severityChartData = BehavioralReport::selectRaw('severity, count(*) as count')
            ->groupBy('severity')
            ->pluck('count', 'severity')
            ->toArray();

        $statusChartData = BehavioralReport::selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        return view('counselor.behavioral-reports.index', compact(
            'reports', 'totalReports', 'pendingCount', 'reviewedCount', 'resolvedCount', 'incidentTypes', 'teachers', 'severityChartData', 'statusChartData'
        ));
    }

    public function show(BehavioralReport $behavioral_report)
    {
        $behavioral_report->load(['student', 'reportedBy', 'escalatedReferral']);

        return view('counselor.behavioral-reports.show', compact('behavioral_report'));
    }

    public function print(BehavioralReport $behavioral_report)
    {
        $behavioral_report->load(['student', 'reportedBy']);
        
        return view('counselor.behavioral-reports.print', compact('behavioral_report'));
    }

    public function updateStatus(Request $request, BehavioralReport $behavioral_report)
    {
        $request->validate([
            'status' => 'required|in:pending,reviewed,resolved',
            'counselor_notes' => 'nullable|string',
        ]);

        $behavioral_report->update([
            'status' => $request->status,
            'counselor_notes' => $request->counselor_notes,
        ]);

        return redirect()->back()->with('success', 'Report updated successfully.');
    }

    public function bulkAction(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:behavioral_reports,id',
            'action' => 'required|in:mark_reviewed,mark_resolved'
        ]);

        $status = $request->action === 'mark_reviewed' ? 'reviewed' : 'resolved';

        BehavioralReport::whereIn('id', $request->ids)->update([
            'status' => $status
        ]);

        return redirect()->back()->with('success', count($request->ids) . ' reports have been marked as ' . $status . '.');
    }

    public function export(Request $request)
    {
        $query = BehavioralReport::with(['student', 'reportedBy'])->latest();

        if ($request->filled('severity')) $query->where('severity', $request->severity);
        if ($request->filled('status')) $query->where('status', $request->status);
        if ($request->filled('incident_type')) $query->where('incident_type', $request->incident_type);
        if ($request->filled('reported_by_id')) $query->where('reported_by', $request->reported_by_id);
        if ($request->filled('date_from')) $query->whereDate('incident_date', '>=', $request->date_from);
        if ($request->filled('date_to')) $query->whereDate('incident_date', '<=', $request->date_to);

        $reports = $query->get();

        $csvData = "ID,Student Name,Student ID,Incident Type,Severity,Status,Reported By,Incident Date,Description\n";
        
        foreach ($reports as $report) {
            $studentName = $report->student ? $report->student->last_name . ', ' . $report->student->first_name : 'N/A';
            $studentId = $report->student ? $report->student->student_id_number : 'N/A';
            $reporterName = $report->reportedBy ? $report->reportedBy->name : 'N/A';
            
            $desc = str_replace(["\r", "\n", ","], [" ", " ", ";"], $report->description);
            
            $csvData .= "{$report->id},\"{$studentName}\",\"{$studentId}\",\"{$report->incident_type}\",\"{$report->severity}\",\"{$report->status}\",\"{$reporterName}\",\"{$report->incident_date}\",\"{$desc}\"\n";
        }

        return Response::make($csvData, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="behavioral_reports_' . date('Y-m-d') . '.csv"',
        ]);
    }
}
