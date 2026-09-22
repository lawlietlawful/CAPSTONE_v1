<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\BehavioralReport;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Mirrors Admin\NotificationController (same Notification model, same
 * read/redirect behavior) but under the 'teacher' middleware, since a
 * teacher can't reach the admin-guarded routes. Teachers have received real
 * notifications since before this page existed — report-escalation,
 * referral-status-change, and report-status-change alerts
 * (NotificationService) — but had no web page to see them; only the mobile
 * app's API surfaced them.
 */
class NotificationController extends Controller
{
    public function index(): View
    {
        $notifications = Notification::where('user_id', auth()->id())
            ->latest()
            ->paginate(20);

        $unreadCount = Notification::where('user_id', auth()->id())
            ->where('is_read', false)
            ->count();

        return view('teacher.notifications.index', compact('notifications', 'unreadCount'));
    }

    public function show(Notification $notification): RedirectResponse
    {
        abort_unless($notification->user_id === auth()->id(), 403);

        if (! $notification->is_read) {
            $notification->markAsRead();
        }

        // report_status is the one notification type that references a
        // behavioral report instead of a referral, and teachers do have a
        // single-report show page (unlike referrals, which only have a
        // list) — so it deep-links there instead of falling back below.
        if ($notification->reference_type === 'behavioral_report' && $notification->reference_id) {
            $report = BehavioralReport::find($notification->reference_id);

            if ($report) {
                return redirect()->route('teacher.behavioral-reports.show', $report->id);
            }
        }

        // Teachers have no single-referral show page (only the list), unlike
        // Admin/Counselor — every other notification type produced for a
        // teacher (report_escalated, referral_status) references a referral,
        // so the list is the closest place that makes sense to land on.
        return redirect()->route('teacher.referrals.index');
    }

    public function markAllRead(Request $request): RedirectResponse|JsonResponse
    {
        Notification::where('user_id', auth()->id())
            ->where('is_read', false)
            ->update(['is_read' => true, 'read_at' => now()]);

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return back()->with('success', 'All notifications marked as read.');
    }

    /**
     * Polled by the header bell every few seconds so a new notification
     * shows up without the viewer having to refresh.
     */
    public function poll(): JsonResponse
    {
        return response()->json([
            'unread_count'  => Notification::where('user_id', auth()->id())->where('is_read', false)->count(),
            'notifications' => Notification::recentForBell(auth()->id()),
        ]);
    }
}
