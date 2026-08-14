<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Seminar;
use App\Models\Student;
use App\Models\StudentSeminar;
use App\Services\SmsService;

/**
 * Shared seminar management logic for both the Admin and Guidance Counselor
 * modules. The two roles manage seminars identically; they differ only in
 * their route/view namespace, supplied by the concrete subclass via $prefix.
 * Keeping this in one place prevents the two sides from silently drifting
 * apart (which is how the roster-export Student ID bug crept into one copy).
 */
abstract class BaseSeminarController extends Controller
{
    /**
     * Route- and view-name prefix for the concrete role, e.g. 'admin' or
     * 'counselor'. Both namespaces mirror each other exactly.
     */
    protected string $prefix = 'admin';

    public function index(Request $request)
    {
        $query = Seminar::query();

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('venue', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('is_required')) {
            $query->where('is_required', $request->is_required);
        }

        $seminars = $query->orderBy('date', 'asc')->orderBy('time', 'asc')->paginate(15)->withQueryString();

        $totalSeminars = Seminar::count();
        $upcomingSessions = Seminar::where('status', 'upcoming')->count();
        $totalStudentsEnrolled = StudentSeminar::where('status', 'enrolled')->count();

        $calendarEvents = Seminar::all()->map(function ($seminar) {
            return [
                'id' => $seminar->id,
                'title' => $seminar->title,
                'start' => \Carbon\Carbon::parse($seminar->date)->format('Y-m-d') . 'T' . \Carbon\Carbon::parse($seminar->time)->format('H:i:s'),
                'url' => route("{$this->prefix}.seminars.show", $seminar->id),
                'color' => $seminar->status === 'completed' ? '#10B981' : ($seminar->status === 'upcoming' ? '#3B82F6' : '#F59E0B'),
            ];
        });

        return view("{$this->prefix}.seminars.index", compact(
            'seminars',
            'totalSeminars',
            'upcomingSessions',
            'totalStudentsEnrolled',
            'calendarEvents'
        ));
    }

    public function create()
    {
        return view("{$this->prefix}.seminars.create");
    }

    public function store(Request $request)
    {
        $request->validate($this->seminarRules());

        Seminar::create($request->all());

        return redirect()->route("{$this->prefix}.seminars.index")->with('success', 'Seminar created successfully.');
    }

    public function show(Request $request, Seminar $seminar)
    {
        $seminar->load('students');

        // The assignable list is narrowed to the seminar's target audience
        // when one is set, so "Target Course / Year" actually scopes who can
        // be enrolled instead of being a purely cosmetic label. Blank targets
        // mean "open to all".
        $availableStudents = Student::where('status', 'active')
            ->whereNotIn('id', $seminar->students->pluck('id'))
            ->when($seminar->target_course, fn ($q) => $q->where('course', $seminar->target_course))
            ->when($seminar->target_grade_level, fn ($q) => $q->where('grade_level', $seminar->target_grade_level))
            ->orderBy('last_name')
            ->get();

        $query = $seminar->students();

        if ($request->has('search_roster') && !empty($request->search_roster)) {
            $search = $request->search_roster;
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('course', 'like', "%{$search}%");
            });
        }

        $participants = $query->paginate(10)->withQueryString();

        return view("{$this->prefix}.seminars.show", compact('seminar', 'availableStudents', 'participants'));
    }

    public function edit(Seminar $seminar)
    {
        return view("{$this->prefix}.seminars.edit", compact('seminar'));
    }

    public function update(Request $request, Seminar $seminar)
    {
        $request->validate($this->seminarRules());

        $seminar->update($request->all());

        // Redirect back to wherever the edit was launched from (the index edit
        // modal or the seminar's own detail page) instead of always bouncing
        // to the list.
        return redirect()->back()->with('success', 'Seminar updated successfully.');
    }

    public function destroy(Seminar $seminar)
    {
        $seminar->delete();

        return redirect()->route("{$this->prefix}.seminars.index")->with('success', 'Seminar deleted successfully.');
    }

    public function assignStudents(Request $request, Seminar $seminar, SmsService $smsService)
    {
        $request->validate([
            'student_ids' => 'required|array',
            'student_ids.*' => 'exists:students,id',
        ]);

        // Respect the seminar's capacity, matching the auto-assign job. Only
        // enforced when a limit is set (max_participants is nullable = no cap).
        if ($seminar->max_participants) {
            $remaining = max(0, $seminar->max_participants - $seminar->students()->count());
            $incoming = count($request->student_ids);

            if ($incoming > $remaining) {
                return redirect()->back()->with('error',
                    "Cannot assign {$incoming} student(s) — only {$remaining} slot(s) remaining (capacity {$seminar->max_participants}).");
            }
        }

        $seminar->students()->attach($request->student_ids, ['status' => 'enrolled', 'assigned_by' => 'manual']);

        if ($seminar->is_required) {
            $students = Student::whereIn('id', $request->student_ids)->get();
            $dateFormatted = \Carbon\Carbon::parse($seminar->date)->format('M d, Y');

            foreach ($students as $student) {
                if ($student->parent_contact) {
                    $msgParent = "MU Advisory: Your child, {$student->first_name}, is required to attend a seminar: '{$seminar->title}' on {$dateFormatted} at {$seminar->time} ({$seminar->venue}). Please ensure their attendance.";
                    $smsService->sendSms($student->parent_contact, $msgParent, $student->id, $student->parent_name, 'parent');
                }

                if ($student->student_contact) {
                    $msgStudent = "MU Advisory: You are required to attend a seminar: '{$seminar->title}' on {$dateFormatted} at {$seminar->time} ({$seminar->venue}). Failure to attend may result in sanctions.";
                    $smsService->sendSms($student->student_contact, $msgStudent, $student->id, $student->first_name . ' ' . $student->last_name, 'student');
                }
            }
        }

        return redirect()->back()->with('success', 'Students assigned successfully. SMS notifications sent.');
    }

    public function updateAttendance(Request $request, Seminar $seminar)
    {
        $request->validate([
            'attendance' => 'required|array',
            'attendance.*.status' => 'required|in:enrolled,attended,missed',
            'attendance.*.remarks' => 'nullable|string',
        ]);

        foreach ($request->attendance as $studentId => $data) {
            $seminar->students()->updateExistingPivot($studentId, [
                'status' => $data['status'],
                'remarks' => $data['remarks'] ?? null,
                'attended_at' => $data['status'] === 'attended' ? now() : null,
            ]);
        }

        return redirect()->back()->with('success', 'Seminar attendance updated successfully.');
    }

    public function printRoster(Seminar $seminar)
    {
        $seminar->load('students');

        return view("{$this->prefix}.seminars.print_roster", compact('seminar'));
    }

    public function exportRoster(Seminar $seminar)
    {
        $fileName = 'seminar_roster_' . $seminar->id . '_' . date('Ymd') . '.csv';
        $students = $seminar->students;

        $headers = [
            "Content-type"        => "text/csv",
            "Content-Disposition" => "attachment; filename=$fileName",
            "Pragma"              => "no-cache",
            "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
            "Expires"             => "0"
        ];

        $columns = ['ID Number', 'Last Name', 'First Name', 'Course/Grade', 'Status', 'Pre-Risk Score', 'Post-Risk Score', 'Effectiveness'];

        $callback = function () use ($students, $columns) {
            $file = fopen('php://output', 'w');
            // Add BOM for Excel UTF-8 compatibility
            fputs($file, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($file, $columns);

            foreach ($students as $student) {
                $row = [
                    $student->student_id_number ?? 'N/A',
                    $student->last_name,
                    $student->first_name,
                    $student->course ?? $student->grade_level,
                    strtoupper($student->pivot->status),
                    $student->pivot->pre_risk_score ?? 'N/A',
                    $student->pivot->post_risk_score ?? 'N/A',
                    ucfirst(str_replace('_', ' ', $student->pivot->effectiveness ?? 'Pending'))
                ];
                fputcsv($file, $row);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Shared validation rules for creating/updating a seminar.
     */
    protected function seminarRules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'date' => 'required|date',
            'time' => 'required',
            'venue' => 'required|string|max:255',
            'speaker' => 'nullable|string|max:255',
            'is_required' => 'boolean',
            'target_grade_level' => 'nullable|string|max:50',
            'target_course' => 'nullable|string|max:100',
            'max_participants' => 'nullable|integer|min:1',
            'status' => 'required|in:upcoming,ongoing,completed,cancelled',
        ];
    }
}
