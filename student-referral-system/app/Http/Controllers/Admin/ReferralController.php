<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Referral;
use App\Models\Student;
use App\Models\User;

class ReferralController extends Controller
{
    public function index(Request $request)
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
                    $query->whereMonth('created_at', now()->subMonth()->month)->whereYear('created_at', now()->subMonth()->year);
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

        $referrals = $query->paginate(10)->appends($request->query());

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

    public function store(Request $request)
    {
        $request->validate([
            'student_id'          => 'required|exists:students,id',
            'referral_type'       => 'required|in:' . implode(',', Referral::REFERRAL_TYPES),
            'referral_type_other' => 'nullable|required_if:referral_type,Other|string|max:255',
            'reason'              => 'required|string',
            'priority'            => 'required|in:low,moderate,high',
            'counselor_id'        => 'nullable|exists:users,id',
        ]);

        Referral::create([
            'student_id'          => $request->student_id,
            'referred_by'         => auth()->id(),
            'counselor_id'        => $request->counselor_id,
            'referral_type'       => $request->referral_type,
            'referral_type_other' => $request->referral_type_other,
            'reason'              => $request->reason,
            'priority'            => $request->priority,
            'status'              => 'pending',
        ]);

        return redirect()->route('admin.referrals.index')
            ->with('success', 'Referral submitted successfully.');
    }

    public function show(Referral $referral)
    {
        $referral->load(['student', 'referredBy', 'counselor', 'interventions', 'smsLogs', 'behavioralReport']);
        $counselors = User::where('role', 'admin')->orderBy('name')->get();

        return view('admin.referrals.show', compact('referral', 'counselors'));
    }

    public function updateStatus(Request $request, Referral $referral)
    {
        $request->validate([
            'status'          => 'required|in:pending,in_progress,resolved,cancelled',
            'counselor_id'    => 'nullable|exists:users,id',
            'counselor_notes' => 'nullable|string',
        ]);

        $data = [
            'status'          => $request->status,
            'counselor_notes' => $request->counselor_notes,
        ];

        if ($request->filled('counselor_id')) {
            $data['counselor_id'] = $request->counselor_id;
        }

        if ($request->status === 'resolved') {
            $data['resolved_at'] = now();
        }

        $referral->update($data);

        return redirect()->back()->with('success', 'Referral status updated successfully.');
    }
    public function export(Request $request)
    {
        $query = Referral::with(['student', 'referredBy', 'counselor'])->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('priority')) {
            $query->where('priority', $request->priority);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('student', function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('student_id_number', 'like', "%{$search}%");
            });
        }

        $referrals = $query->get();

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
            $data = ['status' => $action];
            if ($action === 'resolved') {
                $data['resolved_at'] = now();
            }
            Referral::whereIn('id', $ids)->update($data);
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

    public function edit(Referral $referral)
    {
        $referral->load('student');
        $counselors = User::where('role', 'admin')->orderBy('name')->get();
        return view('admin.referrals.edit', compact('referral', 'counselors'));
    }

    public function update(Request $request, Referral $referral)
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

        $data = $request->only(['referral_type', 'referral_type_other', 'reason', 'priority', 'status', 'counselor_id']);
        
        if ($request->status === 'resolved' && $referral->status !== 'resolved') {
            $data['resolved_at'] = now();
        } elseif ($request->status !== 'resolved') {
            $data['resolved_at'] = null;
        }

        $referral->update($data);

        return redirect()->route('admin.referrals.index')->with('success', 'Referral updated successfully.');
    }

    public function destroy(Referral $referral)
    {
        $referral->delete();
        return redirect()->route('admin.referrals.index')->with('success', 'Referral deleted successfully.');
    }
}
