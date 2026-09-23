<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ReferralService;
use Illuminate\Http\Request;
use App\Models\Referral;
use App\Models\Student;
use App\Models\User;

class ReferralController extends Controller
{
    /**
     * Every index filter, applied in one place. Shared by index() and
     * export() — export() used to carry its own shorter copy that silently
     * dropped counselor_id and date_range, so a filtered export contained
     * rows the screen wasn't showing.
     */
    private function filteredQuery(Request $request)
    {
        $query = Referral::with(['student', 'referredBy', 'counselor'])->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('priority')) {
            $query->where('priority', $request->priority);
        }

        if ($request->filled('counselor_id')) {
            $query->where('counselor_id', $request->counselor_id);
        }

        if ($request->filled('date_range')) {
            switch ($request->date_range) {
                case 'today':
                    $query->whereDate('created_at', today());
                    break;
                case 'this_week':
                    $query->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]);
                    break;
                case 'this_month':
                    $query->whereMonth('created_at', now()->month)->whereYear('created_at', now()->year);
                    break;
                case 'last_month':
                    // NoOverflow: plain subMonth() on the 29th-31st lands in the
                    // *current* month (Oct 31 - 1 month = Oct 1), so "last
                    // month" silently searched this month instead.
                    $lastMonth = now()->subMonthNoOverflow();
                    $query->whereMonth('created_at', $lastMonth->month)->whereYear('created_at', $lastMonth->year);
                    break;
            }
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('student', function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('student_id_number', 'like', "%{$search}%");
            });
        }

        return $query;
    }

    public function index(Request $request)
    {
        $referrals = $this->filteredQuery($request)->paginate(10)->appends($request->query());

        $pendingCount    = Referral::where('status', 'pending')->count();
        $inProgressCount = Referral::where('status', 'in_progress')->count();
        $resolvedCount   = Referral::where('status', 'resolved')->count();
        $totalCount      = Referral::count();

        $counselors = \App\Models\User::where('role', 'admin')->orderBy('name')->get();
        $students = \App\Models\Student::where('status', 'active')->orderBy('last_name')->get();

        return view('admin.referrals.index', compact(
            'referrals',
            'pendingCount',
            'inProgressCount',
            'resolvedCount',
            'totalCount',
            'counselors',
            'students'
        ));
    }

    public function create(Request $request)
    {
        $students = Student::where('status', 'active')->orderBy('last_name')->get();
        $counselors = User::where('role', 'admin')->orderBy('name')->get();

        $prefillStudent = $request->get('student_id');
        $prefillReason = $request->get('reason');

        return view('admin.referrals.create', compact('students', 'counselors', 'prefillStudent', 'prefillReason'));
    }

    public function store(Request $request, ReferralService $referralService)
    {
        $request->validate([
            'student_id'          => 'required|exists:students,id',
            'referral_type'       => 'required|in:' . implode(',', Referral::REFERRAL_TYPES),
            'referral_type_other' => 'nullable|required_if:referral_type,Other|string|max:255',
            'reason'              => 'required|string',
            'counselor_id'        => 'nullable|exists:users,id',
        ]);

        // Goes through the same service the teacher-filed flow uses, so a
        // referral an admin files by hand gets the same ML risk assessment
        // and seminar recommendation instead of silently skipping both (the
        // previous raw Referral::create() here never ran either).
        $referralService->create(auth()->user(), $request->only([
            'student_id', 'referral_type', 'referral_type_other', 'reason', 'counselor_id',
        ]));

        return redirect()->route('admin.referrals.index')
            ->with('success', 'Referral submitted successfully.');
    }

    public function show(Referral $referral)
    {
        $referral->load(['student', 'referredBy', 'counselor', 'interventions', 'smsLogs', 'behavioralReport']);
        $counselors = User::where('role', 'admin')->orderBy('name')->get();

        return view('admin.referrals.show', compact('referral', 'counselors'));
    }

    public function updateStatus(Request $request, Referral $referral, ReferralService $referralService)
    {
        $request->validate([
            'status'          => 'required|in:pending,in_progress,resolved,cancelled',
            'counselor_id'    => 'nullable|exists:users,id',
            'counselor_notes' => 'nullable|string',
        ]);

        $referralService->updateStatus(
            $referral,
            $request->status,
            $request->counselor_id,
            $request->counselor_notes
        );

        return redirect()->back()->with('success', 'Referral status updated successfully.');
    }
    public function export(Request $request)
    {
        $referrals = $this->filteredQuery($request)->get();

        $filename = "referrals_export_" . date('Y-m-d_H-i') . ".csv";
        $headers = [
            "Content-type"        => "text/csv",
            "Content-Disposition" => "attachment; filename=$filename",
            "Pragma"              => "no-cache",
            "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
            "Expires"             => "0"
        ];

        $columns = ['ID', 'Student ID', 'Student Name', 'Referral Type', 'Referred By', 'Priority', 'Status', 'Counselor', 'Date'];

        $callback = function() use($referrals, $columns) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns);

            foreach ($referrals as $referral) {
                $row['ID'] = $referral->id;
                $row['Student ID'] = $referral->student->student_id_number;
                $row['Student Name'] = $referral->student->last_name . ', ' . $referral->student->first_name;
                $row['Referral Type'] = $referral->referral_type_label;
                $row['Referred By'] = $referral->referredBy ? $referral->referredBy->name : 'N/A';
                $row['Priority'] = ucfirst($referral->priority);
                $row['Status'] = ucfirst(str_replace('_', ' ', $referral->status));
                $row['Counselor'] = $referral->counselor ? $referral->counselor->name : 'Unassigned';
                $row['Date'] = $referral->created_at->format('Y-m-d H:i');

                fputcsv($file, array($row['ID'], $row['Student ID'], $row['Student Name'], $row['Referral Type'], $row['Referred By'], $row['Priority'], $row['Status'], $row['Counselor'], $row['Date']));
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function bulkAction(Request $request)
    {
        $request->validate([
            'referral_ids' => 'required|array',
            'referral_ids.*' => 'exists:referrals,id',
            'action' => 'required|in:resolved,in_progress,pending,delete,export_selected,assign_counselor',
            'assign_counselor_id' => 'required_if:action,assign_counselor|nullable|exists:users,id'
        ]);

        $ids = $request->referral_ids;
        $action = $request->action;

        if ($action === 'delete') {
            Referral::whereIn('id', $ids)->delete();
            return redirect()->back()->with('success', 'Selected referrals deleted successfully.');
        } elseif ($action === 'export_selected') {
            return $this->exportSelected($ids);
        } elseif ($action === 'assign_counselor') {
            Referral::whereIn('id', $ids)->update(['counselor_id' => $request->assign_counselor_id]);
            return redirect()->back()->with('success', 'Counselor assigned to selected referrals successfully.');
        } else {
            // Per-row via the shared service (not a single mass UPDATE): each
            // referral needs its OWN old-status check for resolved_at and its
            // own notification to whoever filed it — a blanket query can't
            // tell a referral that was already 'resolved' apart from one that
            // wasn't, and mass updates don't fire the events a notification
            // would need anyway.
            $referralService = app(ReferralService::class);
            foreach (Referral::whereIn('id', $ids)->get() as $referral) {
                $referralService->updateStatus($referral, $action, null, null);
            }

            return redirect()->back()->with('success', 'Status of selected referrals updated to ' . ucfirst(str_replace('_', ' ', $action)) . '.');
        }
    }

    public function exportSelected(array $ids)
    {
        $referrals = Referral::with(['student', 'referredBy', 'counselor'])->whereIn('id', $ids)->latest()->get();

        $filename = "referrals_export_selected_" . date('Y-m-d_H-i') . ".csv";
        $headers = [
            "Content-type"        => "text/csv",
            "Content-Disposition" => "attachment; filename=$filename",
            "Pragma"              => "no-cache",
            "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
            "Expires"             => "0"
        ];

        $columns = ['ID', 'Student ID', 'Student Name', 'Referral Type', 'Referred By', 'Priority', 'Status', 'Counselor', 'Date'];

        $callback = function() use($referrals, $columns) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns);

            foreach ($referrals as $referral) {
                $row['ID'] = $referral->id;
                $row['Student ID'] = $referral->student->student_id_number;
                $row['Student Name'] = $referral->student->last_name . ', ' . $referral->student->first_name;
                $row['Referral Type'] = $referral->referral_type_label;
                $row['Referred By'] = $referral->referredBy ? $referral->referredBy->name : 'N/A';
                $row['Priority'] = ucfirst($referral->priority);
                $row['Status'] = ucfirst(str_replace('_', ' ', $referral->status));
                $row['Counselor'] = $referral->counselor ? $referral->counselor->name : 'Unassigned';
                $row['Date'] = $referral->created_at->format('Y-m-d H:i');

                fputcsv($file, array($row['ID'], $row['Student ID'], $row['Student Name'], $row['Referral Type'], $row['Referred By'], $row['Priority'], $row['Status'], $row['Counselor'], $row['Date']));
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function update(Request $request, Referral $referral, ReferralService $referralService)
    {
        // Legacy referrals may carry a referral_type from before the fixed
        // option list existed (e.g. "Attendance", "Automated Risk Alert").
        // Allow saving the form unchanged without tripping the `in:` rule —
        // only a deliberate change away from that legacy value must match
        // the current fixed list.
        $allowedTypes = Referral::REFERRAL_TYPES;
        if (! in_array($referral->referral_type, $allowedTypes)) {
            $allowedTypes[] = $referral->referral_type;
        }

        $request->validate([
            'referral_type'       => 'required|in:' . implode(',', $allowedTypes),
            'referral_type_other' => 'nullable|required_if:referral_type,Other|string|max:255',
            'reason'              => 'required|string',
            'priority'            => 'required|in:low,moderate,high',
            'status'              => 'required|in:pending,in_progress,resolved,cancelled',
            'counselor_id'        => 'nullable|exists:users,id',
        ]);

        // Everything except the status is a plain field edit...
        $referral->update($request->only(['referral_type', 'referral_type_other', 'reason', 'priority', 'counselor_id']));

        // ...but the status goes through the shared service like every other
        // status change (dropdown, bulk action, Counselor pages). Saving it
        // directly here skipped the filing teacher's notification, the linked
        // behavioral report's status sync, and resolving never-evaluated
        // interventions. counselor_notes is passed through unchanged so it
        // isn't wiped.
        $referralService->updateStatus($referral, $request->status, null, $referral->counselor_notes);

        return redirect()->route('admin.referrals.index')->with('success', 'Referral updated successfully.');
    }

    public function destroy(Referral $referral)
    {
        $referral->delete();
        return redirect()->route('admin.referrals.index')->with('success', 'Referral deleted successfully.');
    }
}
