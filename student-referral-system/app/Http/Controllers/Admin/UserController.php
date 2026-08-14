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
    public function index()
    {
        $users = User::where('role', '!=', 'student')
                    ->with('teacherAssignments')
                    ->latest()
                    ->paginate(10);
        $courseCombos = $this->courseCombos();
        return view('admin.users.index', compact('users', 'courseCombos'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $courseCombos = $this->courseCombos();

        return view('admin.users.create', compact('courseCombos'));
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
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $user = User::with('teacherAssignments')->findOrFail($id);
        // We will just redirect to edit for now, or display a show view if needed.
        return view('admin.users.show', compact('user'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $user = User::findOrFail($id);
        $courseCombos = $this->courseCombos();
        $existingAssignments = $user->teacherAssignments()
            ->get(['course', 'grade_level', 'section']);

        return view('admin.users.edit', compact('user', 'courseCombos', 'existingAssignments'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateUserRequest $request, string $id)
    {
        $user = User::findOrFail($id);

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
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $user = User::findOrFail($id);
        
        if ($user->id === auth()->id()) {
            return redirect()->route('admin.users.index')->with('error', 'You cannot delete yourself.');
        }

        $user->delete();

        return redirect()->route('admin.users.index')->with('success', 'User deleted successfully.');
    }
}
