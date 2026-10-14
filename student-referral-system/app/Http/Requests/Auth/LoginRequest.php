<?php

namespace App\Http\Requests\Auth;

use App\Models\AuditLog;
use App\Models\User;
use App\Support\LoginLocked;
use App\Support\LoginThrottle;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // Not all accounts log in with an email — students and teachers
            // sign in with their Student/Employee ID (see authenticate()).
            'email' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required'    => 'Enter your email or ID.',
            'password.required' => 'Enter your password.',
        ];
    }

    /**
     * Attempt to authenticate the request's credentials.
     *
     * Looks the identifier up against both the email and username columns,
     * the same way the mobile-app API login does (AuthController::login /
     * teacherLogin), since student/teacher accounts are frequently created
     * with no email at all and log in with their Student/Employee ID.
     *
     * Wrong passwords pause the account, and a device, the same way as in
     * the portal apps — see LoginThrottle.
     *
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $identifier = trim((string) $this->input('email'));
        $throttle = LoginThrottle::for($identifier, $this->ip());

        try {
            $throttle->ensureNotLocked();

            $user = User::where('email', $identifier)
                ->orWhere('username', $identifier)
                ->first();

            if (! LoginThrottle::checkPassword($user, (string) $this->input('password'))) {
                // The identifier tried — never the password. When the account
                // exists it's the subject, so its owner can be told on next sign-in.
                AuditLog::record('auth.failed', $user, "Failed sign-in attempt for {$identifier}", null);

                $throttle->failed();

                throw ValidationException::withMessages([
                    'email' => trans('auth.failed'),
                ]);
            }
        } catch (LoginLocked $locked) {
            // The login page counts this down and re-enables the button at zero.
            session()->flash('login_locked_until', now()->getTimestamp() + $locked->seconds);

            throw ValidationException::withMessages(['email' => $locked->getMessage()]);
        }

        // Checked only after the password matches, so this message can't be
        // used to probe which accounts exist.
        if ($user->isDeactivated()) {
            AuditLog::record('auth.failed', $user, "Sign-in attempt by deactivated account {$user->name} ({$identifier})", null);

            throw ValidationException::withMessages([
                'email' => User::DEACTIVATED_MESSAGE,
            ]);
        }

        $throttle->succeeded();

        // No "remember me": a sign-in lasts until sign-out or the session
        // times out, so a shared school PC doesn't stay signed in for months.
        Auth::login($user);
    }
}
