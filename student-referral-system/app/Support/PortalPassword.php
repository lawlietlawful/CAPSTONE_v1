<?php

namespace App\Support;

use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

/**
 * Password change from the Teacher/Student Portal apps, held to the same
 * rules as the website: the same strength rule (Password::defaults()), the
 * account's other phones signed out, and an entry in the activity log.
 */
class PortalPassword
{
    public static function change(Request $request, string $portal): JsonResponse
    {
        $request->validate([
            'current_password' => ['required', 'string', 'max:255'],
            'new_password' => ['required', 'string', 'max:255', 'confirmed', Password::defaults()],
        ]);

        $user = $request->user();

        if (! Hash::check($request->input('current_password'), $user->password)) {
            return response()->json([
                'message' => 'Current password is incorrect.',
            ], 422);
        }

        $user->update([
            'password' => Hash::make($request->input('new_password')),
        ]);

        // Sign out every other device; this one stays signed in.
        $user->tokens()->whereKeyNot($user->currentAccessToken()->getKey())->delete();

        AuditLog::record('user.password_changed', $user, "Changed their password in the {$portal}");

        return response()->json(['message' => 'Password updated successfully.', 'success' => true]);
    }
}
