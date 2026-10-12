<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The super admin's read-only view of the audit log (see AuditLog).
 */
class ActivityLogController extends Controller
{
    public function index(Request $request): View
    {
        $logs = AuditLog::query()
            ->when($request->filled('group') && array_key_exists($request->group, AuditLog::GROUPS),
                fn ($q) => $q->where('action', 'like', $request->group . '.%'))
            ->when($request->filled('user_id'), fn ($q) => $q->where('user_id', $request->user_id))
            ->when($request->filled('search'), function ($q) use ($request) {
                $like = '%' . $request->search . '%';
                $q->where(fn ($w) => $w->where('description', 'like', $like)->orWhere('user_name', 'like', $like)->orWhere('ip_address', 'like', $like));
            })
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        $staff = User::whereIn('role', ['super_admin', 'admin'])->orderBy('name')->get(['id', 'name']);
        $failedToday = AuditLog::where('action', 'auth.failed')->where('created_at', '>=', today())->count();

        return view('admin.activity.index', compact('logs', 'staff', 'failedToday'));
    }
}
