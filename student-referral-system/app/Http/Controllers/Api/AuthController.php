<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Student;
use App\Models\User;
use App\Support\LoginLocked;
use App\Support\LoginThrottle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    /**
     * Student portal login. The "email" field accepts either the student's
     * school email or their student ID number (mirrored into users.username),
     * matching the single identifier field the Flutter login screen sends.
     */
    public function login(Request $request)
    {
        $user = $this->portalSignIn($request, 'student', 'Student Portal', 'Student ID');
        if ($user instanceof JsonResponse) {
            return $user;
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
            'student_id_number' => ['required', 'string', 'max:255'],
            'activation_code' => ['required', 'string', 'max:255'],
            'new_password' => ['required', 'string', 'max:255', 'confirmed', Password::defaults()],
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

        // After the credential check, so it can't be used to probe accounts.
        if ($user->isDeactivated()) {
            return response()->json(['message' => User::DEACTIVATED_MESSAGE], 403);
        }

        $user->update([
            'password' => Hash::make($request->input('new_password')),
            'account_activated_at' => now(),
            'activation_code' => null, // one-time — consumed on activation
        ]);
        AuditLog::record('auth.login', null, 'Activated their account and signed in to the Student Portal', $user);

        $token = $user->createToken('student-portal')->plainTextToken;

        return response()->json([
            'token' => $token,
            'student' => $this->studentPayload($student, $user),
        ]);
    }

    /**
     * Teacher mobile login. Teachers sign in with their Employee ID (mirrored
     * into users.username) — or their email — plus the password they chose at
     * activation.
     */
    public function teacherLogin(Request $request)
    {
        $user = $this->portalSignIn($request, 'teacher', 'Teacher Portal', 'Employee ID');
        if ($user instanceof JsonResponse) {
            return $user;
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
            'school_id' => ['required', 'string', 'max:255'],
            'activation_code' => ['required', 'string', 'max:255'],
            'new_password' => ['required', 'string', 'max:255', 'confirmed', Password::defaults()],
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

        // After the credential check, so it can't be used to probe accounts.
        if ($user->isDeactivated()) {
            return response()->json(['message' => User::DEACTIVATED_MESSAGE], 403);
        }

        $user->update([
            'password' => Hash::make($request->input('new_password')),
            'account_activated_at' => now(),
            'activation_code' => null, // one-time — consumed on activation
        ]);
        AuditLog::record('auth.login', null, 'Activated their account and signed in to the Teacher Portal', $user);

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

    /**
     * The sign-in both portal apps share, with the web login's protections
     * (LoginThrottle): escalating pauses, a per-device limit, and the same
     * answer — in the same time — whether the ID is unknown, not yet
     * activated (its password is a random placeholder) or the password is
     * wrong. Each attempt goes to the activity log.
     *
     * Returns the user, or the error response to send back.
     */
    private function portalSignIn(Request $request, string $role, string $portal, string $idLabel): User|JsonResponse
    {
        $request->validate([
            'email' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
        ]);

        $identifier = trim($request->input('email'));
        $throttle = LoginThrottle::for($identifier, $request->ip());

        try {
            $throttle->ensureNotLocked();

            $user = User::where('role', $role)
                ->where(function ($q) use ($identifier) {
                    $q->where('email', $identifier)
                      ->orWhere('username', $identifier);
                })
                ->first();

            // An account that was never activated is refused like a wrong
            // password, even if its placeholder password somehow matched.
            if (! LoginThrottle::checkPassword($user, (string) $request->input('password')) || ! $user->isActivated()) {
                AuditLog::record('auth.failed', $user, "Failed {$portal} sign-in attempt for {$identifier}", null);
                $throttle->failed();

                return response()->json([
                    'message' => "Invalid {$idLabel} or password. Signing in for the first time? Activate your account first.",
                ], 401);
            }
        } catch (LoginLocked $locked) {
            return response()->json(['message' => $locked->getMessage(), 'retry_after' => $locked->seconds], 429)
                ->header('Retry-After', (string) $locked->seconds);
        }

        // After the credential check, so it can't be used to probe accounts.
        if ($user->isDeactivated()) {
            AuditLog::record('auth.failed', $user, "{$portal} sign-in attempt by deactivated account {$user->name} ({$identifier})", null);

            return response()->json(['message' => User::DEACTIVATED_MESSAGE], 403);
        }

        $throttle->succeeded();
        AuditLog::record('auth.login', null, "Signed in to the {$portal}", $user);

        return $user;
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
