<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Attendance;
use App\Models\BehavioralReport;
use App\Models\Referral;
use App\Models\Course;
use Illuminate\Support\Facades\DB;

class TeacherController extends Controller
{
    public function index(Request $request)
    {
        $query = User::where('role', 'teacher');

        // Search by name, email, or username
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('username', 'like', "%{$search}%");
            });
        }

        // Handle sorting
        $sort = $request->input('sort', 'name_asc');
        switch ($sort) {
            case 'name_desc':
                $query->orderBy('name', 'desc');
                break;
            case 'newest':
                $query->orderBy('created_at', 'desc');
                break;
            case 'oldest':
                $query->orderBy('created_at', 'asc');
                break;
            case 'name_asc':
            default:
                $query->orderBy('name', 'asc');
                break;
        }

        // Eager-load counts for activity stats
        $query->withCount([
            'behavioralReports',
        ]);

        $teachers = $query->paginate(15)->appends($request->query());

        // Engagement reflects the teacher's most recent participation —
        // either a behavioral report filed or a referral raised.
        foreach ($teachers as $teacher) {
            $teacher->engagement_status = $this->engagementStatusFor($teacher);
        }

        // Summary stats
        $totalTeachers = User::where('role', 'teacher')->count();
        $totalBehavioralReports = BehavioralReport::count();

        return view('admin.teachers.index', compact(
            'teachers', 'totalTeachers', 'totalBehavioralReports'
        ));
    }

    /**
     * Engagement status from the teacher's most recent participation — either
     * a behavioral report they filed or a referral they raised. Fresh activity
     * (<= 7 days) is "highly_active", within 30 days "active", and anything
     * older — or no activity at all — is "inactive".
     */
    private function engagementStatusFor(User $teacher): string
    {
        $latestReport = BehavioralReport::where('reported_by', $teacher->id)->latest()->first()?->created_at;
        $latestReferral = Referral::where('referred_by', $teacher->id)->latest()->first()?->created_at;

        $latest = null;
        foreach ([$latestReport, $latestReferral] as $date) {
            if ($date && (! $latest || $date->gt($latest))) {
                $latest = $date;
            }
        }

        if (! $latest) {
            return 'inactive';
        }

        $daysSince = abs($latest->diffInDays(now()));

        if ($daysSince <= 7) {
            return 'highly_active';
        }
        if ($daysSince <= 30) {
            return 'active';
        }

        return 'inactive';
    }

    public function show(User $teacher)
    {
        // Ensure only teacher profiles are viewable here
        if ($teacher->role !== 'teacher') {
            abort(404);
        }

        // Course assignments (adviser/professor scope) shown on the profile,
        // plus the catalog that powers the "Edit Assignments" modal.
        $teacher->load('teacherAssignments');
        $courseCombos = Course::picklist();

        // Recent behavioral reports filed by this teacher
        $recentReports = BehavioralReport::with('student')
            ->where('reported_by', $teacher->id)
            ->latest()
            ->take(10)
            ->get();

        // Stats
        $totalReports = BehavioralReport::where('reported_by', $teacher->id)->count();

        // Engagement reflects the teacher's most recent participation
        // (behavioral report filed or referral raised).
        $teacher->engagement_status = $this->engagementStatusFor($teacher);

        return view('admin.teachers.show', compact(
            'teacher', 'recentReports', 'totalReports', 'courseCombos'
        ));
    }

    /**
     * Update a teacher's account details (name/username/email/password) from
     * the "Edit Details" modal on the directory. Deliberately does NOT touch
     * role or course assignments — role stays 'teacher', and assignments are
     * managed via their own modal on the profile.
     */
    public function update(Request $request, User $teacher)
    {
        if ($teacher->role !== 'teacher') {
            abort(404);
        }

        $validated = $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'username' => ['nullable', 'string', 'max:255', 'unique:users,username,' . $teacher->id],
            'email'    => ['nullable', 'string', 'email', 'max:255', 'unique:users,email,' . $teacher->id],
            'password' => ['nullable', 'string', 'min:8'],
        ]);

        $teacher->name = $validated['name'];
        $teacher->username = $validated['username'] ?: null;
        $teacher->email = $validated['email'] ?: null;

        if (! empty($validated['password'])) {
            $teacher->password = \Illuminate\Support\Facades\Hash::make($validated['password']);
        }

        $teacher->save();

        return redirect()->route('admin.teachers.index')
            ->with('success', "Details updated for {$teacher->name}.");
    }

    /**
     * Update just this teacher's course assignments (from the profile modal).
     * Keeps the teacher's account fields untouched — assignment editing is a
     * focused action, distinct from editing the user account in the Users
     * module. Reuses the same sync logic as the Users form via the model.
     */
    public function updateAssignments(Request $request, User $teacher)
    {
        if ($teacher->role !== 'teacher') {
            abort(404);
        }

        $validated = $request->validate([
            'assignments'                => ['nullable', 'array'],
            'assignments.*.course'       => ['nullable', 'string', 'max:255'],
            'assignments.*.grade_level'  => ['nullable', 'string', 'max:255'],
            'assignments.*.section'      => ['nullable', 'string', 'max:255'],
        ]);

        $teacher->syncTeacherAssignments($validated['assignments'] ?? []);

        return redirect()->route('admin.teachers.show', $teacher->id)
            ->with('success', "Course assignments updated for {$teacher->name}.");
    }

    public function print(User $teacher)
    {
        if ($teacher->role !== 'teacher') {
            abort(404);
        }

        $totalReports = BehavioralReport::where('reported_by', $teacher->id)->count();

        $recentReports = BehavioralReport::with('student')
            ->where('reported_by', $teacher->id)
            ->latest()
            ->take(50)
            ->get();
            
        $latestReport = BehavioralReport::where('reported_by', $teacher->id)->latest()->first();
        $engagementStatus = match ($this->engagementStatusFor($teacher)) {
            'highly_active' => 'Highly Active',
            'active'        => 'Active',
            default         => 'Inactive',
        };

        return view('admin.teachers.print', compact(
            'teacher', 'totalReports',
            'recentReports', 'engagementStatus', 'latestReport'
        ));
    }

    public function export()
    {
        $teachers = User::where('role', 'teacher')->get();

        $teacherIds = $teachers->pluck('id');
            
        $reportsCount = BehavioralReport::select('reported_by', DB::raw('count(*) as total'))
            ->whereIn('reported_by', $teacherIds)
            ->groupBy('reported_by')
            ->pluck('total', 'reported_by');

        $filename = "teacher_directory_" . date('Y-m-d_H-i-s') . ".csv";

        $headers = array(
            "Content-type"        => "text/csv",
            "Content-Disposition" => "attachment; filename=$filename",
            "Pragma"              => "no-cache",
            "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
            "Expires"             => "0"
        );

        $columns = array('Teacher Name', 'Email', 'Employee ID', 'Reports Filed', 'Joined Date');

        $callback = function() use($teachers, $columns, $reportsCount) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns);
            
            $now = now();
            
            foreach ($teachers as $teacher) {
                $repFiled = $reportsCount[$teacher->id] ?? 0;
                
                fputcsv($file, array(
                    $teacher->name,
                    $teacher->email,
                    $teacher->username ?? 'N/A',
                    $repFiled,
                    $teacher->created_at->format('Y-m-d')
                ));
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
