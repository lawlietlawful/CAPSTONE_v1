<?php

namespace App\Http\Requests\Auth;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    /** Wrong passwords allowed for one account from one device before a pause. */
    public const MAX_ATTEMPTS = 5;

    /** How long each pause lasts: longer every time the guessing carries on (within an hour). */
    public const LOCK_SECONDS = [60, 300, 900];

    /**
     * Failed sign-ins allowed from one device across *all* accounts in 15
     * minutes — stops one machine trying a password on account after account.
     * Generous because a whole school can share one IP address (a seminar's
     * worth of students checking in, with the odd typo, stays well under it).
     */
    public const DEVICE_MAX_FAILURES = 50;

    public const DEVICE_WINDOW_SECONDS = 900;

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
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        $identifier = trim((string) $this->input('email'));

        $user = User::where('email', $identifier)
            ->orWhere('username', $identifier)
            ->first();

        // Always check a hash, even for an account that doesn't exist, so the
        // response takes as long either way and can't reveal which IDs are real.
        $passwordMatches = Hash::check((string) $this->input('password'), $user?->password ?? $this->dummyHash());

        if (! $user || ! $passwordMatches) {
            // The identifier tried — never the password. When the account
            // exists it's the subject, so its owner can be told on next sign-in.
            AuditLog::record('auth.failed', $user, "Failed sign-in attempt for {$identifier}", null);

            $this->recordFailure();

            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        // Checked only after the password matches, so this message can't be
        // used to probe which accounts exist.
        if ($user->isDeactivated()) {
            AuditLog::record('auth.failed', $user, "Sign-in attempt by deactivated account {$user->name} ({$identifier})", null);

            throw ValidationException::withMessages([
                'email' => User::DEACTIVATED_MESSAGE,
            ]);
        }

        RateLimiter::clear($this->throttleKey());
        RateLimiter::clear($this->strikesKey());

        // No "remember me": a sign-in lasts until sign-out or the session
        // times out, so a shared school PC doesn't stay signed in for months.
        Auth::login($user);
    }

    /**
     * Ensure the login request is not rate limited.
     *
     * @throws ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if ($until = Cache::get($this->lockKey())) {
            $this->throwLocked(max(1, $until - now()->getTimestamp()));
        }

        if (RateLimiter::tooManyAttempts($this->deviceKey(), self::DEVICE_MAX_FAILURES)) {
            $this->throwLocked(RateLimiter::availableIn($this->deviceKey()), device: true);
        }
    }

    /**
     * Count a wrong password; pause this account on this device once it
     * reaches MAX_ATTEMPTS, and the whole device once it reaches
     * DEVICE_MAX_FAILURES. Each pause is announced (Lockout → activity log)
     * once, not on every attempt made while it lasts.
     *
     * @throws ValidationException
     */
    private function recordFailure(): void
    {
        $deviceFailures = RateLimiter::hit($this->deviceKey(), self::DEVICE_WINDOW_SECONDS);

        if ($deviceFailures === self::DEVICE_MAX_FAILURES) {
            AuditLog::record('auth.lockout', null,
                'Sign-ins from ' . $this->ip() . ' paused after ' . self::DEVICE_MAX_FAILURES . ' failed attempts across accounts', null);
        }

        if (RateLimiter::hit($this->throttleKey(), self::DEVICE_WINDOW_SECONDS) < self::MAX_ATTEMPTS) {
            return;
        }

        $strikes = RateLimiter::hit($this->strikesKey(), 3600);
        $seconds = self::LOCK_SECONDS[min($strikes, count(self::LOCK_SECONDS)) - 1];

        Cache::put($this->lockKey(), now()->getTimestamp() + $seconds, $seconds);
        RateLimiter::clear($this->throttleKey());

        event(new Lockout($this));

        $this->throwLocked($seconds);
    }

    /** @throws ValidationException */
    private function throwLocked(int $seconds, bool $device = false): never
    {
        // The login page counts this down and re-enables the button at zero.
        session()->flash('login_locked_until', now()->getTimestamp() + $seconds);

        $minutes = (int) ceil($seconds / 60);
        $wait = $seconds < 60 ? "{$seconds} seconds" : ($minutes === 1 ? '1 minute' : "{$minutes} minutes");

        throw ValidationException::withMessages([
            'email' => ($device ? 'Too many failed sign-ins from this device.' : 'Too many sign-in attempts.')
                . " Please try again in {$wait}.",
        ]);
    }

    /**
     * A hash of nothing in particular, made with the current settings so
     * checking it costs the same as checking a real password.
     */
    private function dummyHash(): string
    {
        return Cache::rememberForever(
            'login.dummy-hash.' . config('hashing.bcrypt.rounds'),
            fn () => Hash::make(Str::random(40)),
        );
    }

    /**
     * Get the rate limiting throttle key for the request.
     */
    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('email')).'|'.$this->ip());
    }

    private function lockKey(): string
    {
        return 'login-lock:' . $this->throttleKey();
    }

    private function strikesKey(): string
    {
        return 'login-strikes:' . $this->throttleKey();
    }

    private function deviceKey(): string
    {
        return 'login-device:' . $this->ip();
    }
}
