<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BehavioralReport;
use App\Models\Referral;
use App\Services\BehavioralReportService;
use App\Services\ReferralService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class TeacherPortalController extends Controller
{
    /**
     * Home screen counters + the teacher's five most recent referrals.
     * Mirrors Teacher\TeacherDashboardController exactly.
     */
    public function dashboard(Request $request)
    {
        $teacher = $request->user();

        $recentReferrals = Referral::with(['student', 'counselor', 'riskAssessment'])
            ->where('referred_by', $teacher->id)
            ->latest()
            ->take(5)
            ->get()
            ->map(fn ($referral) => $this->referralPayload($referral));

        return response()->json([
            'total_students'    => $teacher->advisedStudentsQuery()->count(),
            'my_referrals'      => Referral::where('referred_by', $teacher->id)->count(),
            'pending_referrals' => Referral::where('referred_by', $teacher->id)
                ->where('status', 'pending')->count(),
            'recent_referrals'  => $recentReferrals,
        ]);
    }

    /**
     * The teacher's advised students (active, within their course/section
     * assignments) for the mobile student picker. Reuses the exact same
     * fail-closed query the web dashboard uses, so a teacher with no
     * assignments simply gets an empty list.
     */
    public function students(Request $request)
    {
        $students = $request->user()->advisedStudentsQuery()
            ->orderBy('last_name')
            ->get()
            ->map(fn ($s) => [
                'id' => $s->id,
                'first_name' => $s->first_name,
                'last_name' => $s->last_name,
                'education_level' => $s->education_level,
                'grade_level' => $s->grade_level,
                'strand' => $s->strand,
                'section' => $s->section,
                'school_id' => $s->student_id_number,
            ]);

        return response()->json(['students' => $students]);
    }

    /**
     * The teacher's in-app notifications (newest first) + an unread count for
     * the header bell. Populated by NotificationService when a report escalates
     * or a counselor changes the status of a referral the teacher filed.
     */
    public function notifications(Request $request)
    {
        $notifications = $request->user()->notifications()
            ->latest()
            ->limit(50)
            ->get()
            ->map(fn ($n) => [
                'id'             => $n->id,
                'title'          => $n->title,
                'message'        => $n->message,
                'type'           => $n->type,
                'reference_type' => $n->reference_type,
                'reference_id'   => $n->reference_id,
                'is_read'        => (bool) $n->is_read,
                'created_at'     => $n->created_at?->toIso8601String(),
            ]);

        return response()->json([
            'unread_count'  => $request->user()->notifications()->where('is_read', false)->count(),
            'notifications' => $notifications,
        ]);
    }

    /** Mark one of the teacher's notifications as read. */
    public function markNotificationRead(Request $request, int $id)
    {
        $notification = $request->user()->notifications()->whereKey($id)->first();
        if ($notification && ! $notification->is_read) {
            $notification->markAsRead();
        }

        return response()->json(['success' => true]);
    }

    /** Mark all of the teacher's notifications as read. */
    public function markAllNotificationsRead(Request $request)
    {
        $request->user()->notifications()->where('is_read', false)->update([
            'is_read' => true,
            'read_at' => now(),
        ]);

        return response()->json(['success' => true]);
    }

    /** Self-service password change from the Profile screen. */
    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => ['required', 'string'],
            'new_password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = $request->user();

        if (!Hash::check($request->input('current_password'), $user->password)) {
            return response()->json([
                'message' => 'Current password is incorrect.',
            ], 422);
        }

        $user->update([
            'password' => Hash::make($request->input('new_password')),
        ]);

        return response()->json(['message' => 'Password updated successfully.', 'success' => true]);
    }

    /**
     * The "My Students" roster: the teacher's advised students, each with their
     * current ML risk level and open-case counts, plus a summary tally by risk
     * band for the header. This is the monitoring view — it surfaces the
     * predictive risk analytics to the teacher who can act on it early.
     */
    public function roster(Request $request)
    {
        $students = $request->user()->advisedStudentsQuery()
            ->with('latestRiskAssessment')
            ->withCount([
                'referrals as open_referrals_count' => fn ($q) => $q->whereIn('status', ['pending', 'in_progress']),
                'behavioralReports as reports_count',
            ])
            ->orderBy('last_name')
            ->get();

        $summary = ['high' => 0, 'moderate' => 0, 'low' => 0, 'not_assessed' => 0];
        foreach ($students as $s) {
            $level = $s->latestRiskAssessment?->risk_level ?? 'not_assessed';
            $summary[$level] = ($summary[$level] ?? 0) + 1;
        }

        return response()->json([
            'students' => $students->map(fn ($s) => $this->rosterStudentPayload($s)),
            'summary'  => $summary,
        ]);
    }

    /**
     * A single advised student's full picture: current risk, a merged timeline
     * of every behavioral report and referral about them, and the seminars
     * they've been assigned. Enforces that the student is within this teacher's
     * advised set (a teacher can only monitor their own students).
     */
    public function showStudent(Request $request, int $id)
    {
        $student = $request->user()->advisedStudentsQuery()
            ->with('latestRiskAssessment')
            ->whereKey($id)
            ->first();

        if (! $student) {
            return response()->json(['message' => 'This student is not in your advisory.'], 403);
        }

        // Merged, newest-first timeline of the student's whole case history.
        $reports = $student->behavioralReports()->latest()->get()->map(fn ($r) => [
            'kind'       => 'report',
            'id'         => $r->id,
            'title'      => $r->incident_type,
            'detail'     => $r->description,
            'status'     => $r->status,
            'severity'   => $r->severity,
            'escalated'  => $r->escalatedReferral()->exists(),
            'date'       => optional($r->incident_date)->toDateString() ?? $r->created_at?->toDateString(),
            'created_at' => $r->created_at?->toIso8601String(),
        ]);

        $referrals = $student->referrals()->with('riskAssessment')->latest()->get()->map(fn ($r) => [
            'kind'       => 'referral',
            'id'         => $r->id,
            'title'      => $r->referral_type_label,
            'detail'     => $r->display_reason,
            'status'     => $r->status,
            'priority'   => $r->priority,
            'auto'       => $r->behavioral_report_id !== null,
            'date'       => $r->created_at?->toDateString(),
            'created_at' => $r->created_at?->toIso8601String(),
        ]);

        $timeline = $reports->concat($referrals)
            ->sortByDesc('created_at')
            ->values();

        $seminars = $student->seminars()->get()->map(fn ($s) => [
            'id'             => $s->id,
            'title'          => $s->title,
            'description'    => $s->description,
            'status'         => $s->pivot->status,
            // The seminar's own lifecycle (upcoming/ongoing/completed/cancelled),
            // distinct from the student's enrollment status above — a session can
            // be "completed" while the student's pivot is still stuck on
            // "enrolled" because nobody recorded attendance.
            'session_status' => $s->status,
            'date'           => $s->date ? \Illuminate\Support\Carbon::parse($s->date)->toDateString() : null,
            'time'           => $s->time,
            'venue'          => $s->venue,
            'speaker'        => $s->speaker,
            'is_required'    => (bool) $s->is_required,
            'trigger_reason' => $s->trigger_reason,
        ]);

        return response()->json([
            'student'  => $this->rosterStudentPayload($student) + [
                'first_name' => $student->first_name,
                'last_name'  => $student->last_name,
                'course'     => $student->course,
            ],
            'timeline' => $timeline,
            'seminars' => $seminars,
        ]);
    }

    /**
     * Upcoming/ongoing seminars matching a recommended-intervention tag, for
     * when a student hasn't been assigned one yet. Mirrors the lookup
     * Counselor\ReferralController uses to suggest seminars on a referral.
     */
    public function matchingSeminars(Request $request)
    {
        $request->validate(['tag' => 'required|string']);

        $seminars = \App\Models\Seminar::where('trigger_reason', $request->tag)
            ->whereIn('status', ['upcoming', 'ongoing'])
            ->orderBy('date')
            ->orderBy('time')
            ->get()
            ->map(fn ($s) => [
                'id'          => $s->id,
                'title'       => $s->title,
                'description' => $s->description,
                'status'      => $s->status,
                'date'        => $s->date ? \Illuminate\Support\Carbon::parse($s->date)->toDateString() : null,
                'time'        => $s->time,
                'venue'       => $s->venue,
                'speaker'     => $s->speaker,
                'is_required' => (bool) $s->is_required,
            ]);

        return response()->json(['seminars' => $seminars]);
    }

    /**
     * Shape a Student for the roster / student header. Expects
     * `latestRiskAssessment` loaded and the two *_count aggregates present.
     */
    private function rosterStudentPayload($student): array
    {
        $factors = $student->latestRiskAssessment?->risk_factors ?? [];

        return [
            'id'                      => $student->id,
            'name'                    => $student->last_name . ', ' . $student->first_name,
            'school_id'               => $student->student_id_number,
            'education_level'         => $student->education_level,
            'year_level'              => $student->grade_level,
            'strand'                  => $student->strand,
            'section'                 => $student->section,
            'risk_level'              => $student->latestRiskAssessment?->risk_level ?? 'not_assessed',
            'risk_score'              => $student->latestRiskAssessment?->risk_score,
            'risk_reason'             => $factors['reason'] ?? null,
            'recommended_seminar_tag' => $factors['recommended_seminar_tag'] ?? null,
            'open_referrals'          => $student->open_referrals_count ?? 0,
            'reports_count'           => $student->reports_count ?? 0,
        ];
    }

    /**
     * The teacher's own submitted behavioral reports (newest first) for the
     * "My Reports" history screen, plus the fixed incident-type vocabulary.
     * Serving the types from the server keeps the mobile chip list from drifting
     * out of sync with BehavioralReport::INCIDENT_TYPES, which the escalation
     * rules depend on.
     */
    public function reports(Request $request)
    {
        $query = BehavioralReport::with(['student', 'escalatedReferral'])
            ->where('reported_by', $request->user()->id);

        $this->applyStudentSearch($query, $request->query('search'));
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        if ($incidentType = $request->query('incident_type')) {
            $query->where('incident_type', $incidentType);
        }
        if ($severity = $request->query('severity')) {
            $query->where('severity', $severity);
        }
        $this->applyDateRange($query, 'incident_date', $request->query('date_from'), $request->query('date_to'));

        $paginator = $query
            // created_at is the semantic order; id is a stable tiebreaker so two
            // reports filed in the same second can't reorder between pages.
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($this->perPage($request))
            ->withQueryString(); // keep search/status across page links

        return response()->json([
            'reports'        => collect($paginator->items())
                ->map(fn ($report) => $this->reportPayload($report)),
            'incident_types' => BehavioralReport::incidentTypeValues(),
            'meta'           => $this->paginationMeta($paginator),
        ]);
    }

    /**
     * File a new behavioral report from the mobile app. Goes through the same
     * shared service as the web form (ML severity + auto-escalation), so the
     * two clients behave identically.
     */
    public function storeReport(Request $request, BehavioralReportService $service)
    {
        $data = $request->validate([
            'student_id'    => 'required|exists:students,id',
            // Same fixed vocabulary as the web form. The escalation rules key off
            // these exact strings, so a mobile client posting a typo would file a
            // report that silently never escalates.
            'incident_type' => 'required|in:' . implode(',', BehavioralReport::incidentTypeValues()),
            'incident_date' => 'required|date',
            'location'      => 'nullable|string|max:100',
            'description'   => 'required|string',
        ]);

        $teacher = $request->user();

        // Defense in depth: a mobile client could POST any student_id, so
        // confirm the student is actually within this teacher's advised set
        // before letting them file a report on that student.
        $isAdvised = $teacher->advisedStudentsQuery()
            ->whereKey($data['student_id'])
            ->exists();

        if (! $isAdvised) {
            return response()->json([
                'message' => 'You are not assigned to this student.',
            ], 403);
        }

        $report = $service->create($teacher, $data);

        return response()->json([
            'success'   => true,
            'message'   => "Report submitted. AI Assessed Severity: {$report->severity}.",
            'escalated' => $report->escalatedReferral !== null,
            'report'    => $this->reportPayload($report),
        ], 201);
    }

    /**
     * A single behavioral report, including the referral it escalated into (if
     * any). Mirrors Teacher\BehavioralReportController@show, including its
     * ownership check — a teacher may only read reports they filed.
     */
    public function showReport(Request $request, int $id)
    {
        $report = BehavioralReport::with(['student', 'escalatedReferral.counselor'])
            ->findOrFail($id);

        if ($report->reported_by !== $request->user()->id) {
            return response()->json(['message' => 'This report is not yours.'], 403);
        }

        $payload = $this->reportPayload($report);
        $payload['escalated_referral'] = $report->escalatedReferral
            ? $this->referralPayload($report->escalatedReferral)
            : null;

        return response()->json(['report' => $payload]);
    }

    /**
     * The teacher's own referrals (newest first) plus the pending counter and
     * the fixed referral-type vocabulary. Serving the types from the server
     * keeps the mobile radio list from drifting out of sync with
     * Referral::REFERRAL_TYPES.
     */
    public function referrals(Request $request)
    {
        $teacher = $request->user();

        $query = Referral::with(['student', 'counselor', 'riskAssessment'])
            ->where('referred_by', $teacher->id);

        $this->applyStudentSearch($query, $request->query('search'));
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        if ($referralType = $request->query('referral_type')) {
            $query->where('referral_type', $referralType);
        }
        if ($priority = $request->query('priority')) {
            $query->where('priority', $priority);
        }
        $this->applyDateRange($query, 'created_at', $request->query('date_from'), $request->query('date_to'));

        $paginator = $query
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($this->perPage($request))
            ->withQueryString();

        return response()->json([
            'referrals'     => collect($paginator->items())
                ->map(fn ($referral) => $this->referralPayload($referral)),
            // pending_count is the whole-history total for the header, not just
            // this page — so it stays a dedicated query.
            'pending_count' => Referral::where('referred_by', $teacher->id)
                ->where('status', 'pending')->count(),
            'types'         => Referral::REFERRAL_TYPES,
            'meta'          => $this->paginationMeta($paginator),
        ]);
    }

    /**
     * File a new guidance referral from the mobile app. Goes through the same
     * shared service as the web form (ML risk assessment + parent SMS), so the
     * two clients behave identically.
     */
    public function storeReferral(Request $request, ReferralService $service)
    {
        $data = $request->validate([
            'student_id'          => 'required|exists:students,id',
            'referral_type'       => 'required|in:' . implode(',', Referral::REFERRAL_TYPES),
            'referral_type_other' => 'nullable|required_if:referral_type,Other|string|max:255',
            'reason'              => 'required|string',
        ]);

        $teacher = $request->user();

        // Defense in depth: a mobile client could POST any student_id, so
        // confirm the student is actually within this teacher's advised set.
        $isAdvised = $teacher->advisedStudentsQuery()
            ->whereKey($data['student_id'])
            ->exists();

        if (! $isAdvised) {
            return response()->json([
                'message' => 'You are not assigned to this student.',
            ], 403);
        }

        $referral = $service->create($teacher, $data);
        $referral->load(['student', 'counselor', 'riskAssessment']);

        return response()->json([
            'success'  => true,
            'message'  => 'Referral submitted. The AI has assessed the student and assigned an intervention if required.',
            'referral' => $this->referralPayload($referral),
        ], 201);
    }

    /**
     * A single referral the teacher filed, with its status journey and the
     * counselor's notes — so the teacher can see what Guidance did after they
     * referred the student. Ownership enforced: a teacher only sees referrals
     * they filed.
     */
    public function showReferral(Request $request, int $id)
    {
        $referral = Referral::with(['student', 'counselor', 'riskAssessment'])
            ->where('referred_by', $request->user()->id)
            ->whereKey($id)
            ->first();

        if (! $referral) {
            return response()->json(['message' => 'This referral is not yours.'], 403);
        }

        $payload = $this->referralPayload($referral) + [
            'counselor_notes' => $referral->counselor_notes,
        ];

        return response()->json([
            'referral' => $payload,
            'journey'  => $this->referralJourney($referral),
        ]);
    }

    /**
     * The status journey of a referral as an ordered list of steps, each marked
     * done / current / upcoming. Only two transitions carry a timestamp (filed,
     * resolved); "under review" has no stored time, so its date is null.
     */
    private function referralJourney(Referral $referral): array
    {
        $filedAt    = $referral->created_at?->toIso8601String();
        $resolvedAt = $referral->resolved_at?->toIso8601String();

        // A cancelled referral branches off after being filed.
        if ($referral->status === 'cancelled') {
            return [
                ['key' => 'filed', 'label' => 'Filed', 'state' => 'done', 'date' => $filedAt],
                ['key' => 'cancelled', 'label' => 'Cancelled', 'state' => 'current',
                 'date' => $referral->updated_at?->toIso8601String()],
            ];
        }

        return [
            [
                'key'   => 'filed',
                'label' => 'Filed',
                'state' => $referral->status === 'pending' ? 'current' : 'done',
                'date'  => $filedAt,
            ],
            [
                'key'   => 'in_progress',
                'label' => 'Under review by Guidance',
                'state' => match ($referral->status) {
                    'pending'     => 'upcoming',
                    'in_progress' => 'current',
                    default       => 'done',
                },
                'date'  => null,
            ],
            [
                'key'   => 'resolved',
                'label' => 'Resolved',
                'state' => $referral->status === 'resolved' ? 'current' : 'upcoming',
                'date'  => $resolvedAt,
            ],
        ];
    }

    /**
     * The page size for a list endpoint: `per_page` from the query, defaulted
     * and clamped so a client can't request an unbounded page (which would
     * defeat the point of paginating) or a nonsensical one.
     */
    private function perPage(Request $request, int $default = 20, int $max = 50): int
    {
        $requested = (int) $request->query('per_page', $default);

        return max(1, min($requested, $max));
    }

    /**
     * Constrain a report/referral query to rows whose student matches the search
     * term (first name, last name, or full name). No-op for a blank term.
     */
    private function applyStudentSearch($query, ?string $term): void
    {
        $term = trim((string) $term);
        if ($term === '') {
            return;
        }

        $query->whereHas('student', function ($q) use ($term) {
            $like = '%' . $term . '%';
            $q->where('first_name', 'like', $like)
                ->orWhere('last_name', 'like', $like)
                ->orWhereRaw("CONCAT(first_name, ' ', last_name) like ?", [$like])
                ->orWhereRaw("CONCAT(last_name, ' ', first_name) like ?", [$like]);
        });
    }

    /**
     * Inclusive date-range filter on the given column. Either bound may be
     * omitted for an open-ended range. Dates are compared by day only (not
     * time-of-day), matching what a date-picker in the client would send.
     */
    private function applyDateRange($query, string $column, ?string $from, ?string $to): void
    {
        if ($from) {
            $query->whereDate($column, '>=', $from);
        }
        if ($to) {
            $query->whereDate($column, '<=', $to);
        }
    }

    /**
     * Pagination metadata for the mobile client: enough to render "X of Y" and
     * to drive infinite scroll (`has_more`), without leaking query internals.
     */
    private function paginationMeta(\Illuminate\Contracts\Pagination\LengthAwarePaginator $paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'last_page'    => $paginator->lastPage(),
            'per_page'     => $paginator->perPage(),
            'total'        => $paginator->total(),
            'has_more'     => $paginator->hasMorePages(),
        ];
    }

    /**
     * Shape a Referral for the mobile app. Expects `student`, `counselor` and
     * `riskAssessment` to be loaded.
     */
    private function referralPayload(Referral $referral): array
    {
        return [
            'id'                  => $referral->id,
            'student_id'          => $referral->student_id,
            'student_name'        => $referral->student
                ? $referral->student->last_name . ', ' . $referral->student->first_name
                : null,
            'referral_type'       => $referral->referral_type,
            'referral_type_other' => $referral->referral_type_other,
            // Pre-rendered "Other — <text>" so the client never re-implements it.
            'referral_type_label' => $referral->referral_type_label,
            // Cleaned of the internal "[AUTO-ESCALATED ...]" marker — see
            // is_auto_escalated/escalated_from_report_id for that signal instead.
            'reason'              => $referral->display_reason,
            'priority'            => $referral->priority,
            'status'              => $referral->status,
            'counselor_name'      => $referral->counselor?->name,
            'risk_level'          => $referral->riskAssessment?->risk_level,
            'is_auto_escalated'   => $referral->is_auto_escalated,
            'escalated_from_report_id' => $referral->behavioral_report_id,
            'created_at'          => $referral->created_at?->toIso8601String(),
            'resolved_at'         => $referral->resolved_at?->toIso8601String(),
        ];
    }

    /**
     * Shape a BehavioralReport for the mobile app. Expects `student` and
     * `escalatedReferral` to be loaded (eager-loaded in reports(); set on the
     * model by the service in storeReport()).
     */
    private function reportPayload(BehavioralReport $report): array
    {
        return [
            'id'            => $report->id,
            'student_id'    => $report->student_id,
            'student_name'  => $report->student
                ? $report->student->last_name . ', ' . $report->student->first_name
                : null,
            'incident_type' => $report->incident_type,
            'severity'      => $report->severity,
            'status'        => $report->status,
            'incident_date' => optional($report->incident_date)->toDateString(),
            'location'      => $report->location,
            'description'   => $report->description,
            'escalated'     => $report->escalatedReferral !== null,
            'created_at'    => $report->created_at?->toIso8601String(),
        ];
    }
}
