<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class PasswordController extends Controller
{
    /**
     * Update the user's password.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validateWithBag('updatePassword', [
            'current_password' => ['required', 'current_password'],
            'password' => ['required', Password::defaults(), 'confirmed', 'different:current_password'],
        ], [
            'password.different' => 'Choose a password different from your current one.',
        ]);

        $wasForced = (bool) $request->user()->must_change_password;

        // Every other session of this account is signed out by
        // AuthenticateSession; this one stays signed in.
        $request->user()->update([
            'password' => Hash::make($validated['password']),
            'must_change_password' => false,
        ]);

        \App\Models\AuditLog::record('user.password_changed', $request->user(),
            $wasForced ? 'Replaced their temporary password' : 'Changed their password');

        if ($wasForced) {
            return redirect()->route('dashboard')->with('success', 'Password changed. Welcome back!');
        }

        return back()->with('status', 'password-updated')->with('success', 'Password changed. Any other signed-in sessions were signed out.');
    }
}
