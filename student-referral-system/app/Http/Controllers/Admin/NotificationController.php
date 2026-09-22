<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Intervention;
use App\Models\Notification;
use App\Models\Referral;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Shared between super_admin and counselor (like Teachers, Courses, Risk):
 * each sees only their own notifications, so there's no scoping difference
 * that would need a separate counselor.* route the way Referrals/Seminars
 * did.
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

        return view('admin.notifications.index', compact('notifications', 'unreadCount'));
    }

    /**
     * Opening a notification marks it read (mirrors Counselor\MessageController
     * @show, which does the same on a GET view) and sends the user on to
     * whatever it's about.
     */
    public function show(Notification $notification): RedirectResponse
    {
        abort_unless($notification->user_id === auth()->id(), 403);

        if (! $notification->is_read) {
            $notification->markAsRead();
        }

        return redirect()->to($this->referenceUrl($notification));
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
     * (e.g. a referral filed while this page is already open) shows up
     * without the viewer having to refresh.
     */
    public function poll(): JsonResponse
    {
        return response()->json([
            'unread_count'  => Notification::where('user_id', auth()->id())->where('is_read', false)->count(),
            'notifications' => Notification::recentForBell(auth()->id()),
        ]);
    }

    /**
     * Where a notification actually points. 'referral' and 'intervention' are
     * the only reference types produced today (NotificationService); a type
     * this doesn't recognise, or a reference that's since been deleted, falls
     * back to the notifications list itself rather than 404ing.
     */
    private function referenceUrl(Notification $notification): string
    {
        if ($notification->reference_type === 'referral' && $notification->reference_id) {
            $referral = Referral::find($notification->reference_id);

            if ($referral) {
                return auth()->user()->role === 'super_admin'
                    ? route('admin.referrals.show', $referral->id)
                    : route('counselor.referrals.show', $referral->id);
            }
        }

        if ($notification->reference_type === 'intervention' && $notification->reference_id) {
            $intervention = Intervention::find($notification->reference_id);

            // Interventions are counselor-only (no admin.* equivalent) —
            // this notification is only ever sent to the logging counselor.
            if ($intervention) {
                return route('counselor.interventions.show', $intervention->id);
            }
        }

        return route('admin.notifications.index');
    }
}
