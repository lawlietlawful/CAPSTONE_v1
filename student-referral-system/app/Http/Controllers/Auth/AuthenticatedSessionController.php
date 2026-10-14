<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        $user = $request->user();
        if ($user->isAdmin()) {   // counselors and the super admin
            $request->session()->flash('last_sign_in', $this->previousSignIn($user));
        }

        $role = $user->role;

        return match($role) {
            'super_admin' => redirect()->intended('/admin/dashboard'),
            'admin'       => redirect()->intended('/counselor/dashboard'),
            'teacher'     => redirect()->intended('/teacher/dashboard'),
            'student'     => redirect()->intended('/student/dashboard'),
            default       => redirect()->intended('/dashboard'),
        };
    }

    /**
     * When (and from where) this account last signed in before now, and
     * how many wrong passwords were tried on it since — shown once after
     * sign-in so staff can spot use of their account that wasn't them.
     */
    private function previousSignIn(User $user): array
    {
        // The newest auth.login is the one just recorded (Login listener).
        $previous = AuditLog::where('user_id', $user->id)->where('action', 'auth.login')
            ->latest('id')->skip(1)->first(['created_at', 'ip_address']);

        $failed = AuditLog::where('action', 'auth.failed')
            ->where('subject_type', 'User')->where('subject_id', $user->id)
            ->when($previous, fn ($q) => $q->where('created_at', '>=', $previous->created_at))
            ->count();

        return [
            'at'     => $previous?->created_at->toIso8601String(),
            'ip'     => $previous?->ip_address,
            'failed' => $failed,
        ];
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
