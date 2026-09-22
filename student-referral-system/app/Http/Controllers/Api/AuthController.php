<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    /**
     * Student portal login. The "email" field accepts either the student's
     * school email or their student ID number (mirrored into users.username),
     * matching the single identifier field the Flutter login screen sends.
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|string',
            'password' => 'required|string',
        ]);

        $identifier = trim($request->input('email'));

        $user = User::where('role', 'student')
            ->where(function ($q) use ($identifier) {
                $q->where('email', $identifier)
                  ->orWhere('username', $identifier);
            })
            ->first();

        if (!$user) {
            return response()->json([
                'message' => 'Invalid Student ID or password.',
            ], 401);
        }

        if (!$user->isActivated()) {
            return response()->json([
                'message' => 'Please activate your account first using your Student ID and activation code.',
            ], 403);
        }

        if (!Hash::check($request->input('password'), $user->password)) {
            return response()->json([
                'message' => 'Invalid Student ID or password.',
            ], 401);
        }

        $student = $user->student;
        if (!$student) {
            return response()->json([
                'message' => 'No student profile is linked to this account.',
            ], 401);
        }

        $token = $user->createToken('student-portal')->plainTextToken;

        return response()->json([
            'token' => $token,
            'student' => $this->studentPayload($student, $user),
        ]);
    }

    /**
     * First-time account activation, mirroring the teacher flow. The admin
     * creates the student with a locked password and a one-time code (stored
     * hashed); the student proves identity with their Student ID + that
     * code, chooses their own password here, and is signed in immediately.
     *
     * This used to check Student ID + birthdate instead of a code. Birthdates
     * are not a real secret in a school context (known/guessable alongside a
     * near-sequential Student ID), so an attacker could have raced a student
     * to activate their own account first. See User::generateActivationCode().
     */
    public function activate(Request $request)
    {
        $request->validate([
            'student_id_number' => ['required', 'string'],
            'activation_code' => ['required', 'string'],
            'new_password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $mismatch = response()->json([
            'message' => "The Student ID or activation code you entered doesn't match our records.",
        ], 422);

        $student = Student::where('student_id_number', trim($request->input('student_id_number')))->first();
        if (!$student) {
            return $mismatch;
        }

        $user = $student->user;
        if (!$user || $user->role !== 'student') {
            return $mismatch;
        }

        if ($user->isActivated()) {
            return response()->json([
                'message' => 'This account has already been activated. Please log in instead.',
            ], 422);
        }

        $code = User::canonicalActivationCode($request->input('activation_code'));
        if (!$user->activation_code || !Hash::check($code, $user->activation_code)) {
            return $mismatch;
        }

        $user->update([
            'password' => Hash::make($request->input('new_password')),
            'account_activated_at' => now(),
            'activation_code' => null, // one-time — consumed on activation
        ]);

        $token = $user->createToken('student-portal')->plainTextToken;

        return response()->json([
            'token' => $token,
            'student' => $this->studentPayload($student, $user),
        ]);
    }

    /**
     * Teacher mobile login. Teachers sign in with their Employee ID (mirrored
     * into users.username) — or their email — plus the password they chose at
     * activation. Accounts must be activated first (see teacherActivate).
     */
    public function teacherLogin(Request $request)
    {
        $request->validate([
            'email' => 'required|string',
            'password' => 'required|string',
        ]);

        $identifier = trim($request->input('email'));

        $user = User::where('role', 'teacher')
            ->where(function ($q) use ($identifier) {
                $q->where('email', $identifier)
                  ->orWhere('username', $identifier);
            })
            ->first();

        if (!$user) {
            return response()->json([
                'message' => 'Invalid Employee ID or password.',
            ], 401);
        }

        // Mirrors the student flow: an un-activated teacher is told to activate
        // first rather than being handed a generic "wrong password".
        if (!$user->isActivated()) {
            return response()->json([
                'message' => 'Please activate your account first using your Employee ID and activation code.',
            ], 403);
        }

        if (!Hash::check($request->input('password'), $user->password)) {
            return response()->json([
                'message' => 'Invalid Employee ID or password.',
            ], 401);
        }

        $token = $user->createToken('teacher-portal')->plainTextToken;

        return response()->json([
            'token' => $token,
            'teacher' => $this->teacherPayload($user),
        ]);
    }

    /**
     * First-time teacher activation, mirroring the student flow. The admin
     * creates the teacher with a locked password and a one-time code (stored
     * hashed); the teacher proves identity with their Employee ID + that code,
     * chooses their own password here, and is signed in immediately.
     */
    public function teacherActivate(Request $request)
    {
        $request->validate([
            'school_id' => ['required', 'string'],
            'activation_code' => ['required', 'string'],
            'new_password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $mismatch = response()->json([
            'message' => "The Employee ID or activation code you entered doesn't match our records.",
        ], 422);

        $user = User::where('role', 'teacher')
            ->where('username', trim($request->input('school_id')))
            ->first();

        if (!$user) {
            return $mismatch;
        }

        if ($user->isActivated()) {
            return response()->json([
                'message' => 'This account has already been activated. Please log in instead.',
            ], 422);
        }

        $code = User::canonicalActivationCode($request->input('activation_code'));
        if (!$user->activation_code || !Hash::check($code, $user->activation_code)) {
            return $mismatch;
        }

        $user->update([
            'password' => Hash::make($request->input('new_password')),
            'account_activated_at' => now(),
            'activation_code' => null, // one-time — consumed on activation
        ]);

        $token = $user->createToken('teacher-portal')->plainTextToken;

        return response()->json([
            'token' => $token,
            'teacher' => $this->teacherPayload($user),
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out', 'success' => true]);
    }

    private function studentPayload(Student $student, User $user): array
    {
        return [
            'id' => $student->id,
            'first_name' => $student->first_name,
            'last_name' => $student->last_name,
            'email' => $user->email,
            'grade_level' => $student->grade_level,
            'section' => $student->section,
            'school_id' => $student->student_id_number,
        ];
    }

    private function teacherPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'username' => $user->username,
        ];
    }
}
