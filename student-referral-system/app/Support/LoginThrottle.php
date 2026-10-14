<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * Sign-in limits shared by the web login and the Teacher/Student Portal apps,
 * so guessing is no easier through one door than another:
 *
 *  - MAX_ATTEMPTS wrong passwords for one account from one device pause it
 *    there, for longer each time the guessing carries on (LOCK_SECONDS).
 *  - DEVICE_MAX_FAILURES failed sign-ins from one device across all accounts
 *    pause that device — one machine trying a password on account after
 *    account. Generous, since a whole school can share one IP address.
 *
 * Each pause is written to the activity log once, when it starts.
 */
class LoginThrottle
{
    public const MAX_ATTEMPTS = 5;

    public const LOCK_SECONDS = [60, 300, 900];

    public const DEVICE_MAX_FAILURES = 50;

    public const DEVICE_WINDOW_SECONDS = 900;

    private function __construct(private string $identifier, private string $ip) {}

    public static function for(string $identifier, ?string $ip): self
    {
        return new self(Str::transliterate(Str::lower(trim($identifier))), (string) $ip);
    }

    /**
     * Check a password without revealing whether the account exists: an
     * unknown account still costs one hash check, so both take as long.
     */
    public static function checkPassword(?User $user, string $password): bool
    {
        $matches = Hash::check($password, $user?->password ?? self::dummyHash());

        return $user !== null && $matches;
    }

    /** @throws LoginLocked */
    public function ensureNotLocked(): void
    {
        if ($until = Cache::get($this->lockKey())) {
            throw new LoginLocked(max(1, $until - now()->getTimestamp()));
        }

        if (RateLimiter::tooManyAttempts($this->deviceKey(), self::DEVICE_MAX_FAILURES)) {
            throw new LoginLocked(RateLimiter::availableIn($this->deviceKey()), device: true);
        }
    }

    /**
     * Count a failed sign-in. Throws when this failure starts a pause.
     *
     * @throws LoginLocked
     */
    public function failed(): void
    {
        if (RateLimiter::hit($this->deviceKey(), self::DEVICE_WINDOW_SECONDS) === self::DEVICE_MAX_FAILURES) {
            AuditLog::record('auth.lockout', null,
                "Sign-ins from {$this->ip} paused after " . self::DEVICE_MAX_FAILURES . ' failed attempts across accounts', null);
        }

        if (RateLimiter::hit($this->accountKey(), self::DEVICE_WINDOW_SECONDS) < self::MAX_ATTEMPTS) {
            return;
        }

        $strikes = RateLimiter::hit($this->strikesKey(), 3600);
        $seconds = self::LOCK_SECONDS[min($strikes, count(self::LOCK_SECONDS)) - 1];

        Cache::put($this->lockKey(), now()->getTimestamp() + $seconds, $seconds);
        RateLimiter::clear($this->accountKey());

        AuditLog::record('auth.lockout', null,
            "Sign-in locked after too many attempts for {$this->identifier}", null);

        throw new LoginLocked($seconds);
    }

    /** A successful sign-in clears this account's count — but never the device's. */
    public function succeeded(): void
    {
        RateLimiter::clear($this->accountKey());
        RateLimiter::clear($this->strikesKey());
    }

    /**
     * A hash of nothing in particular, made with the current settings so
     * checking it costs the same as checking a real password.
     */
    private static function dummyHash(): string
    {
        return Cache::rememberForever(
            'login.dummy-hash.' . config('hashing.bcrypt.rounds'),
            fn () => Hash::make(Str::random(40)),
        );
    }

    private function accountKey(): string
    {
        return $this->identifier . '|' . $this->ip;
    }

    private function lockKey(): string
    {
        return 'login-lock:' . $this->accountKey();
    }

    private function strikesKey(): string
    {
        return 'login-strikes:' . $this->accountKey();
    }

    private function deviceKey(): string
    {
        return 'login-device:' . $this->ip;
    }
}
