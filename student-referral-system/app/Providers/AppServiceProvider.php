<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureRateLimiting();
    }

    /**
     * The web login is already throttled by Breeze (LoginRequest::ensureIsNotRateLimited).
     * The API login endpoints were not throttled at all — gatherMiddleware() on
     * api/teacher/login returned only ['api'] — leaving them open to unlimited
     * password guessing against a known email address.
     */
    protected function configureRateLimiting(): void
    {
        // Applied to the whole api group via throttleApi() in bootstrap/app.php.
        // Keyed by authenticated user where possible, so a shared campus NAT
        // address doesn't throttle every teacher at once.
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        // Login endpoints. Two independent limits, both must pass:
        //   5/min per (email, IP) — stops guessing one account's password.
        //   20/min per IP         — stops spraying one password across many
        //                           emails, which the per-email limit misses.
        RateLimiter::for('login', function (Request $request) {
            $email = strtolower((string) $request->input('email'));

            return [
                Limit::perMinute(5)->by($email . '|' . $request->ip()),
                Limit::perMinute(20)->by($request->ip()),
            ];
        });
    }
}
