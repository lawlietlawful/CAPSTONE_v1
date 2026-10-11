<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Course;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = User::where('role', '!=', 'student')
            ->with('teacherAssignments')
            // What a delete would erase or orphan (see recordsOnFile()) — the
            // page only offers Delete when all four are zero.
            ->withCount(['behavioralReports', 'referralsReferred', 'interventions', 'referralsCounselor'])
            ->latest();

        if ($request->input('status') === 'active') {
            $query->whereNull('deactivated_at');
        } elseif ($request->input('status') === 'deactivated') {
            $query->whereNotNull('deactivated_at');
        }

        if (auth()->user()->role !== 'super_admin') {
            $query->where('role', 'teacher');
        } elseif (in_array($request->input('role'), ['super_admin', 'admin', 'teacher'], true)) {
            // Role filter is only meaningful to a super admin — a counselor
            // only ever sees teachers.
            $query->where('role', $request->input('role'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('username', 'like', "%{$search}%");
            });
        }

        $users = $query->paginate(10)->appends($request->query());
        $courseCombos = $this->courseCombos();
        return view('admin.users.index', compact('users', 'courseCombos'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreUserRequest $request)
    {
        $isTeacher = $request->role === 'teacher';

        $attrs = [
            'name'  => $request->name,
            'email' => $request->email,
            'role'  => $request->role,
        ];

        $plainCode = null;
        if ($isTeacher) {
            // Teacher accounts are created locked: an unusable random password
            // and a one-time activation code (stored hashed). The teacher sets
            // their real password by activating in the mobile app.
            $plainCode = User::generateActivationCode();
            $attrs['username'] = $request->username;
            $attrs['password'] = Hash::make(Str::random(40));
            $attrs['account_activated_at'] = null;
            $attrs['activation_code'] = Hash::make(User::canonicalActivationCode($plainCode));
        } else {
            // Admin / counselor are web users with a password set here and no
            // activation step.
            $attrs['password'] = Hash::make($request->password);
            $attrs['account_activated_at'] = now();
        }

        $user = User::create($attrs);

        if ($isTeacher) {
            $user->syncTeacherAssignments($request->input('assignments', []));

            return redirect()->route('admin.users.index')
                ->with('success', 'Teacher account created. Share the activation code below with them.')
                ->with('activation_code', [
                    'name' => $user->name, 'school_id' => $user->username, 'code' => $plainCode,
                ]);
        }

        return redirect()->route('admin.users.index')->with('success', 'User created successfully.');
    }

    /**
     * Issue a fresh activation code for a teacher who hasn't activated yet
     * (e.g. they lost the original). Codes are one-time and stored hashed, so
     * this is the only way to recover one.
     */
    public function regenerateActivationCode(string $id)
    {
        $user = User::findOrFail($id);

        if ($user->role !== 'teacher') {
            return back()->with('error', 'Activation codes apply to teacher accounts only.');
        }
        if ($user->isActivated()) {
            return back()->with('error', "{$user->name}'s account is already activated — reset the password instead.");
        }

        $plainCode = User::generateActivationCode();
        $user->update(['activation_code' => Hash::make(User::canonicalActivationCode($plainCode))]);

        return back()
            ->with('success', 'New activation code generated.')
            ->with('activation_code', [
                'name' => $user->name, 'school_id' => $user->username, 'code' => $plainCode,
            ]);
    }

    /**
     * The canonical (course, grade_level, section) combinations defined in
     * the Courses & Sections catalog, used to populate the assignment
     * dropdowns. Sourcing from the catalog (not the students table) lets an
     * admin assign a teacher to a brand-new course/section that has no
     * enrolled students yet.
     */
    private function courseCombos()
    {
        return Course::picklist();
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateUserRequest $request, string $id)
    {
        $user = User::findOrFail($id);
        if (auth()->user()->role !== 'super_admin' && $user->role !== 'teacher') {
            abort(403, 'Unauthorized action.');
        }

        $data = [
            'name' => $request->name,
            'email' => $request->email,
            'role' => $request->role,
        ];

        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        $user->update($data);

        if ($request->role === 'teacher') {
            $user->syncTeacherAssignments($request->input('assignments', []));
        } else {
            // Role changed away from teacher — an ex-teacher shouldn't retain
            // course assignments that no longer apply.
            $user->teacherAssignments()->delete();
        }

        return redirect()->route('admin.users.index')->with('success', 'User updated successfully.');
    }

    /**
     * Reset the specified user's password to default.
     */
    public function resetPassword(string $id)
    {
        $user = User::findOrFail($id);
        if (auth()->user()->role !== 'super_admin' && $user->role !== 'teacher') {
            abort(403, 'Unauthorized action.');
        }

        // Teachers don't have admin-set passwords — resetting one means putting
        // the account back to "needs activation" with a fresh code, so the
        // teacher re-activates and chooses a new password themselves.
        if ($user->role === 'teacher') {
            $plainCode = User::generateActivationCode();
            $user->update([
                'password'             => Hash::make(Str::random(40)),
                'account_activated_at' => null,
                'activation_code'      => Hash::make(User::canonicalActivationCode($plainCode)),
            ]);
            $user->tokens()->delete(); // sign out any existing mobile sessions

            return redirect()->route('admin.users.index')
                ->with('success', "{$user->name}'s account was reset. Share the new activation code below.")
                ->with('activation_code', [
                    'name' => $user->name, 'school_id' => $user->username, 'code' => $plainCode,
                ]);
        }

        // Admin / counselor: a standard default password.
        $user->update([
            'password' => Hash::make('password123')
        ]);

        return redirect()->route('admin.users.index')->with('success', "Password for {$user->name} has been reset to 'password123'.");
    }

    /**
     * Permanently delete an account — only one with nothing on file (e.g. created
     * by mistake). Every report, referral and intervention a user filed is ON
     * DELETE CASCADE, and a counselor's assigned referrals would silently become
     * unassigned, so an account with any of that must be deactivated instead.
     */
    public function destroy(string $id)
    {
        $user = User::findOrFail($id);
        if (auth()->user()->role !== 'super_admin' && $user->role !== 'teacher') {
            abort(403, 'Unauthorized action.');
        }

        if ($user->id === auth()->id()) {
            return redirect()->route('admin.users.index')->with('error', 'You cannot delete yourself.');
        }

        $onFile = $this->recordsOnFile($user);
        if ($onFile !== []) {
            return redirect()->route('admin.users.index')->with('error',
                "{$user->name} has " . implode(', ', $onFile) . ' on file, which deleting would erase. Deactivate the account instead.');
        }

        $user->delete();

        return redirect()->route('admin.users.index')->with('success', 'User deleted successfully.');
    }

    /**
     * Switch an account off without touching anything they filed: they can't
     * sign in anywhere (web or the portal apps), any open portal session is
     * revoked now, an open web session ends on its next request
     * (EnsureAccountIsActive), and they drop out of every assignment and
     * messaging list. Reversible with reactivate().
     */
    public function deactivate(string $id)
    {
        $user = User::findOrFail($id);
        if (auth()->user()->role !== 'super_admin' && $user->role !== 'teacher') {
            abort(403, 'Unauthorized action.');
        }
        if ($user->role === 'student') {
            return back()->with('error', 'Student accounts are managed from the Students page.');
        }
        if ($user->id === auth()->id()) {
            return back()->with('error', 'You cannot deactivate your own account.');
        }
        if ($user->isDeactivated()) {
            return back()->with('error', "{$user->name}'s account is already deactivated.");
        }

        $user->update(['deactivated_at' => now()]);
        $user->tokens()->delete(); // sign out of the portal apps right away

        return back()->with('success', "{$user->name}'s account was deactivated. Everything they filed is kept.");
    }

    /** Undo deactivate(): the same account, with all its history, signs in again. */
    public function reactivate(string $id)
    {
        $user = User::findOrFail($id);
        if (auth()->user()->role !== 'super_admin' && $user->role !== 'teacher') {
            abort(403, 'Unauthorized action.');
        }
        if (! $user->isDeactivated()) {
            return back()->with('error', "{$user->name}'s account is already active.");
        }

        $user->update(['deactivated_at' => null]);

        return back()->with('success', "{$user->name}'s account was reactivated.");
    }

    /**
     * What this user has on file that a delete would erase or orphan, as
     * human-readable counts ("3 behavioral reports"); empty when nothing.
     * Shared by destroy() and the index page, which only offers Delete when
     * this is empty.
     */
    public static function recordsOnFile(User $user): array
    {
        $counts = [
            'behavioral report' => $user->behavioral_reports_count ?? $user->behavioralReports()->count(),
            'referral' => $user->referrals_referred_count ?? $user->referralsReferred()->count(),
            'intervention record' => $user->interventions_count ?? $user->interventions()->count(),
            'assigned referral' => $user->referrals_counselor_count ?? $user->referralsCounselor()->count(),
        ];

        $parts = [];
        foreach ($counts as $label => $n) {
            if ($n > 0) {
                $parts[] = $n . ' ' . $label . ($n === 1 ? '' : 's');
            }
        }

        return $parts;
    }
}
