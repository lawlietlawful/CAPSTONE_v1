<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Support\PortalPassword;

class StudentPortalController extends Controller
{
    /**
     * Resolve the Student profile linked to the authenticated (Sanctum) user.
     * The student/guidance middleware already guarantees role === 'student'.
     */
    private function student(Request $request)
    {
        return $request->user()->student()->firstOrFail();
    }

    public function profile(Request $request)
    {
        $student = $this->student($request);
        $user = $request->user();

        return response()->json([
            'student' => [
                'id' => $student->id,
                'first_name' => $student->first_name,
                'last_name' => $student->last_name,
                'email' => $user->email,
                'education_level' => $student->education_level,
                'grade_level' => $student->grade_level,
                'strand' => $student->strand,
                'section' => $student->section,
                'school_id' => $student->student_id_number,
            ],
        ]);
    }

    public function riskLevel(Request $request)
    {
        $student = $this->student($request);
        $latest = $student->riskAssessments()->latest('assessed_at')->first();

        if (!$latest) {
            return response()->json(['has_assessment' => false]);
        }

        return response()->json([
            'has_assessment' => true,
            'risk_level' => $latest->risk_level,
            'risk_score' => (float) $latest->risk_score,
            'assessed_at' => $latest->assessed_at?->toDateString(),
        ]);
    }

    public function referrals(Request $request)
    {
        $student = $this->student($request);

        $referrals = $student->referrals()
            ->with('referredBy')
            ->latest()
            ->get()
            ->map(function ($referral) {
                return [
                    'id' => $referral->id,
                    'concern_type' => $referral->concern_type,
                    'reason' => $referral->reason,
                    'priority' => $referral->priority,
                    'status' => $referral->status,
                    'referred_by_name' => $referral->referredBy->name ?? 'N/A',
                    'created_at' => $referral->created_at?->toIso8601String(),
                ];
            });

        $active = $referrals->firstWhere(
            fn ($r) => in_array($r['status'], ['pending', 'in_progress'], true)
        );

        return response()->json([
            'has_active_referral' => $active !== null,
            'active_referral' => $active,
            'all_referrals' => $referrals->values(),
        ]);
    }

    public function seminars(Request $request)
    {
        $student = $this->student($request);

        $seminars = $student->seminars()->orderBy('date')->orderBy('time')->get();

        $format = function ($seminar) {
            return [
                'id' => $seminar->id,
                'title' => $seminar->title,
                'description' => $seminar->description,
                'date' => optional($seminar->date)->toDateString(),
                'time' => $seminar->time,
                'venue' => $seminar->venue,
                'speaker' => $seminar->speaker,
                'is_required' => (bool) $seminar->is_required,
                'assigned_by' => $seminar->pivot->assigned_by,
                'status' => $seminar->pivot->status,
                // The seminar's own lifecycle, so the app can tell a
                // cancelled session from one the student attended/missed.
                'session_status' => $seminar->status,
                'attended_at' => optional($seminar->pivot->attended_at)->toIso8601String(),
            ];
        };

        // "Required" = still to attend: enrolled in a seminar that hasn't
        // happened and wasn't cancelled. Everything else — attended, missed,
        // excused, cancelled, or past without attendance taken — is history.
        $stillAhead = fn ($seminar) => $seminar->pivot->status === 'enrolled'
            && in_array($seminar->status, ['upcoming', 'ongoing'], true)
            && ! $seminar->date->isBefore(today());

        $required = $seminars->filter($stillAhead)->map($format)->values();
        $completed = $seminars->reject($stillAhead)->sortByDesc('date')->map($format)->values();

        return response()->json([
            'required' => $required,
            'completed' => $completed,
        ]);
    }

    public function notifications(Request $request)
    {
        $user = $request->user();

        $notifications = $user->notifications()
            ->latest()
            ->get()
            ->map(function ($n) {
                return [
                    'id' => $n->id,
                    'title' => $n->title,
                    'message' => $n->message,
                    'type' => $n->type,
                    'is_read' => (bool) $n->is_read,
                    'created_at' => $n->created_at?->toIso8601String(),
                ];
            });

        return response()->json([
            'unread_count' => $notifications->where('is_read', false)->count(),
            'notifications' => $notifications,
        ]);
    }

    public function markNotificationRead(Request $request, int $id)
    {
        $notification = $request->user()->notifications()->where('id', $id)->first();

        if ($notification && !$notification->is_read) {
            $notification->markAsRead();
        }

        return response()->json(['message' => 'Marked as read', 'success' => true]);
    }

    public function markAllNotificationsRead(Request $request)
    {
        $request->user()->notifications()->where('is_read', false)->get()->each->markAsRead();

        return response()->json(['message' => 'Marked as read', 'success' => true]);
    }

    public function changePassword(Request $request)
    {
        return PortalPassword::change($request, 'Student Portal');
    }
}
