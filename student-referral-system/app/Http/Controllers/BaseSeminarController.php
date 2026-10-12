<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use App\Models\Course;
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
 *
 * Create and edit are modals (index page and the seminar's own page), so
 * there are no standalone create/edit actions.
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
        Seminar::markPastAsCompleted();

        $query = Seminar::query()->withCount([
            'students',
            'students as attended_count' => fn ($q) => $q->where('student_seminars.status', 'attended'),
            'students as missed_count' => fn ($q) => $q->where('student_seminars.status', 'missed'),
        ]);

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

        // What's still ahead first (soonest at the top), then everything
        // that's over (most recent first) — oldest-first used to push the
        // upcoming seminars onto later pages behind years of history.
        $seminars = $query
            ->orderByRaw("CASE WHEN status IN ('upcoming', 'ongoing') THEN 0 ELSE 1 END")
            ->orderByRaw("CASE WHEN status IN ('upcoming', 'ongoing') THEN TO_DAYS(date) ELSE -TO_DAYS(date) END")
            ->orderBy('time')
            ->paginate(15)
            ->withQueryString();

        $totalSeminars = Seminar::count();
        $upcomingSessions = Seminar::where('status', 'upcoming')->count();
        $rosterEntries = StudentSeminar::count();

        // Of the students whose attendance has been taken (attended or
        // missed — excused and still-pending don't count against it).
        $attendedTotal = StudentSeminar::where('status', 'attended')->count();
        $attendanceTaken = $attendedTotal + StudentSeminar::where('status', 'missed')->count();
        $attendanceRate = $attendanceTaken > 0 ? (int) round($attendedTotal / $attendanceTaken * 100) : null;

        // Colours come from the page's CSS (one class per status), so the
        // calendar, its legend and the cards all use the same palette.
        $calendarEvents = Seminar::withCount('students')->get()->map(function ($seminar) {
            return [
                'id' => $seminar->id,
                'title' => $seminar->title,
                'start' => \Carbon\Carbon::parse($seminar->date)->format('Y-m-d') . 'T' . \Carbon\Carbon::parse($seminar->time)->format('H:i:s'),
                'url' => route("{$this->prefix}.seminars.show", $seminar->id),
                'classNames' => ['sem-ev', 'sem-ev-' . $seminar->status],
                'extendedProps' => [
                    'status' => ucfirst($seminar->status),
                    'venue' => $seminar->venue,
                    'required' => (bool) $seminar->is_required,
                    'enrolled' => $seminar->students_count,
                    'capacity' => $seminar->max_participants,
                ],
            ];
        });

        return view("{$this->prefix}.seminars.index", compact(
            'seminars',
            'totalSeminars',
            'upcomingSessions',
            'rosterEntries',
            'attendanceRate',
            'attendanceTaken',
            'calendarEvents'
        ) + ['courseOptions' => $this->courseOptions()]);
    }

    public function store(Request $request)
    {
        Seminar::create($this->validatedSeminar($request));

        return redirect()->route("{$this->prefix}.seminars.index")->with('success', 'Seminar created successfully.');
    }

    public function show(Request $request, Seminar $seminar)
    {
        if (Seminar::markPastAsCompleted() > 0) {
            $seminar->refresh();
        }

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

        return view("{$this->prefix}.seminars.show", compact('seminar', 'availableStudents', 'participants')
            + ['courseOptions' => $this->courseOptions()]);
    }

    public function update(Request $request, Seminar $seminar)
    {
        $seminar->update($this->validatedSeminar($request, $seminar));

        // Back to wherever the edit modal was opened (the index or the
        // seminar's own page).
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

        if ($seminar->status === 'cancelled') {
            return redirect()->back()->with('error', 'This seminar is cancelled — students can\'t be assigned to it.');
        }

        // Students already enrolled would violate the students_seminars
        // unique(student_id, seminar_id) constraint if re-attached — drop
        // them instead of letting attach() throw a 500.
        $alreadyEnrolledIds = $seminar->students()->whereIn('students.id', $request->student_ids)->pluck('students.id')->all();
        $newStudentIds = array_diff($request->student_ids, $alreadyEnrolledIds);

        if (empty($newStudentIds)) {
            return redirect()->back()->with('error', 'The selected student(s) are already enrolled in this seminar.');
        }

        // Respect the seminar's capacity, matching the auto-assign job. Only
        // enforced when a limit is set (max_participants is nullable = no cap).
        if ($seminar->max_participants) {
            $remaining = max(0, $seminar->max_participants - $seminar->students()->count());
            $incoming = count($newStudentIds);

            if ($incoming > $remaining) {
                return redirect()->back()->with('error',
                    "Cannot assign {$incoming} student(s) — only {$remaining} slot(s) remaining (capacity {$seminar->max_participants}).");
            }
        }

        $seminar->students()->attach($newStudentIds, ['status' => 'enrolled', 'assigned_by' => 'manual']);

        // "You are required to attend…" only makes sense for a required
        // seminar that hasn't happened yet — never for a past one being
        // filled in after the fact.
        $smsSent = 0;
        $sendSms = $seminar->is_required
            && $seminar->status !== 'completed'
            && ! $seminar->date->isBefore(today());

        if ($sendSms) {
            $students = Student::whereIn('id', $newStudentIds)->get();
            $dateFormatted = \Carbon\Carbon::parse($seminar->date)->format('M d, Y');
            $timeFormatted = \Carbon\Carbon::parse($seminar->time)->format('h:i A');

            foreach ($students as $student) {
                if ($student->parent_contact) {
                    $msgParent = "MU Advisory: Your child, {$student->first_name}, is required to attend a seminar: '{$seminar->title}' on {$dateFormatted} at {$timeFormatted} ({$seminar->venue}). Please ensure their attendance.";
                    $smsSent += (int) $smsService->sendSms($student->parent_contact, $msgParent, $student->id, $student->parent_name, 'parent');
                }

                if ($student->student_contact) {
                    $msgStudent = "MU Advisory: You are required to attend a seminar: '{$seminar->title}' on {$dateFormatted} at {$timeFormatted} ({$seminar->venue}). Failure to attend may result in sanctions.";
                    $smsSent += (int) $smsService->sendSms($student->student_contact, $msgStudent, $student->id, $student->first_name . ' ' . $student->last_name, 'student');
                }
            }
        }

        $assigned = count($newStudentIds);
        $message = $assigned . ' student' . ($assigned === 1 ? '' : 's') . ' assigned successfully';
        if (count($alreadyEnrolledIds) > 0) {
            $message .= ' (' . count($alreadyEnrolledIds) . ' already enrolled and skipped)';
        }
        $message .= '.';
        if ($sendSms) {
            $message .= $smsSent > 0
                ? " SMS sent to {$smsSent} contact" . ($smsSent === 1 ? '' : 's') . '.'
                : ' No SMS sent — no contact numbers on file.';
        }

        return redirect()->back()->with('success', $message);
    }

    public function updateAttendance(Request $request, Seminar $seminar)
    {
        $request->validate([
            'attendance' => 'required|array',
            'attendance.*.status' => ['required', Rule::in(Seminar::ATTENDANCE_STATUSES)],
            'attendance.*.remarks' => 'nullable|string|max:255',
        ]);

        // Only rows actually on this roster, with their current state.
        $current = $seminar->students()
            ->whereIn('students.id', array_keys($request->attendance))
            ->get()
            ->keyBy('id');

        foreach ($request->attendance as $studentId => $data) {
            $enrolled = $current->get($studentId);
            if (! $enrolled) {
                continue;
            }

            // Keep the original check-in time for someone who was already
            // marked attended (e.g. by QR) — re-saving the roster must not
            // move it, or the 30-day effectiveness tracker (which counts
            // from attended_at) keeps restarting.
            $attendedAt = $data['status'] === 'attended'
                ? ($enrolled->pivot->status === 'attended' && $enrolled->pivot->attended_at ? $enrolled->pivot->attended_at : now())
                : null;

            $seminar->students()->updateExistingPivot($studentId, [
                'status' => $data['status'],
                'remarks' => $data['remarks'] ?? null,
                'attended_at' => $attendedAt,
            ]);
        }

        return redirect()->back()->with('success', 'Seminar attendance updated successfully.');
    }

    /**
     * Take a student off the roster (e.g. assigned by mistake). Someone
     * marked Attended is kept — that's a record of a session they sat
     * through, and the effectiveness tracker relies on it.
     */
    public function removeStudent(Seminar $seminar, Student $student)
    {
        $enrollment = $seminar->students()->where('students.id', $student->id)->first();

        if (! $enrollment) {
            return redirect()->back()->with('error', "{$student->first_name} {$student->last_name} isn't on this seminar's roster.");
        }

        if ($enrollment->pivot->status === 'attended') {
            return redirect()->back()->with('error',
                "{$student->first_name} {$student->last_name} is marked Attended, so they can't be removed. Change their attendance first if that was a mistake.");
        }

        $seminar->students()->detach($student->id);

        return redirect()->back()->with('success', "{$student->first_name} {$student->last_name} was removed from the roster.");
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

    /** Catalog courses for the Target Course picker, with their education level. */
    protected function courseOptions()
    {
        return Course::orderBy('name')->get(['name', 'education_level']);
    }

    /**
     * Validate the create/edit form and return only the seminar's own
     * columns. Target Course must be a catalog course (students are matched
     * on its exact name — free text like "BSIT" matched nobody), or the
     * value the seminar already has, so editing an older seminar never
     * forces its target to change. Target Year must belong to that course's
     * education level. On edit, the capacity can't drop below the roster.
     */
    protected function validatedSeminar(Request $request, ?Seminar $seminar = null): array
    {
        $courses = $this->courseOptions();
        $allowedCourses = $courses->pluck('name')->push($seminar?->target_course)->filter()->unique()->values()->all();

        $data = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'date' => 'required|date',
            'time' => 'required|date_format:H:i,H:i:s',
            'venue' => 'required|string|max:255',
            'speaker' => 'nullable|string|max:255',
            'trigger_reason' => ['required', Rule::in(array_keys(Seminar::TRIGGER_REASONS))],
            'is_required' => 'required|boolean',
            'target_course' => ['nullable', 'string', Rule::in($allowedCourses)],
            'target_grade_level' => ['nullable', 'string', Rule::in(array_merge(Course::COLLEGE_YEAR_LEVELS, Course::BASIC_ED_GRADE_LEVELS))],
            'max_participants' => 'nullable|integer|min:1',
            'status' => ['required', Rule::in(Seminar::STATUSES)],
        ], [
            'target_course.in' => 'Choose a target course from the catalog.',
            'target_grade_level.in' => 'Choose a target year/grade from the list.',
            'max_participants.min' => 'Max capacity must be at least 1, or left blank for unlimited.',
            'trigger_reason.in' => 'Choose an ML trigger reason from the list.',
        ]);

        $course = $courses->firstWhere('name', $data['target_course'] ?? null);
        if ($course && ! empty($data['target_grade_level'])) {
            $levels = $course->education_level === 'Basic Education' ? Course::BASIC_ED_GRADE_LEVELS : Course::COLLEGE_YEAR_LEVELS;
            if (! in_array($data['target_grade_level'], $levels, true)) {
                throw ValidationException::withMessages([
                    'target_grade_level' => "{$data['target_grade_level']} isn't a level in {$course->name} ({$course->education_level}).",
                ]);
            }
        }

        if ($seminar && ! empty($data['max_participants'])) {
            $rosterSize = $seminar->students()->count();
            if ($data['max_participants'] < $rosterSize) {
                throw ValidationException::withMessages([
                    'max_participants' => "{$rosterSize} students are already on the roster — max capacity can't be lower than that.",
                ]);
            }
        }

        return $data;
    }
}
