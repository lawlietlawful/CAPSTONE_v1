<?php

namespace App\Providers;

use App\Models\Notification;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Per-request memo for Setting::get() (fresh for every request/app instance).
        $this->app->scoped('settings.memo', fn () => new \ArrayObject());
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Every "choose a password" form (staff Change Password, password
        // reset links, teacher/student activation): at least 8 characters
        // with both letters and numbers.
        \Illuminate\Validation\Rules\Password::defaults(fn () => \Illuminate\Validation\Rules\Password::min(8)->letters()->numbers());

        $this->configureRateLimiting();
        $this->auditSignIns();
        $this->composeNotificationBell();
        $this->composeNavBadges();
    }

    /**
     * Count pills on the counselor sidebar (see App\Support\NavBadges).
     * Composed here for the same reason as the bell: it is needed on every
     * page of the layout, so no single controller can own it.
     */
    private function composeNavBadges(): void
    {
        View::composer('partials.counselor-nav', function ($view) {
            $view->with('navBadges', \App\Support\NavBadges::for(Auth::user()));
        });
    }

    /**
     * The header bell (badge count + its dropdown preview) lives in all
     * three role layouts and is needed on every page, not just whichever
     * dashboard controller happened to compute it — a per-controller value
     * here would go stale the moment a page other than the dashboard loads
     * first.
     */
    protected function composeNotificationBell(): void
    {
        View::composer(['layouts.admin', 'layouts.counselor', 'layouts.teacher'], function ($view) {
            if (! Auth::check()) {
                $view->with(['unreadNotifications' => 0, 'recentNotifications' => collect()]);

                return;
            }

            $view->with([
                'unreadNotifications' => Notification::where('user_id', Auth::id())->where('is_read', false)->count(),
                'recentNotifications' => Notification::recentForBell(Auth::id()),
            ]);
        });
    }

    /**
     * Request limits for the API. Sign-ins (web and apps) are limited
     * separately by App\Support\LoginThrottle.
     */
    protected function configureRateLimiting(): void
    {
        // Applied to the whole api group via throttleApi() in bootstrap/app.php.
        // Keyed by authenticated user where possible, so a shared campus NAT
        // address doesn't throttle every teacher at once.
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        // Account activation (portal apps). Sign-ins themselves are limited by
        // App\Support\LoginThrottle. Two independent limits, both must pass:
        //   5/min per (ID, IP) — stops guessing one account's activation code.
        //   20/min per IP      — stops trying codes across many IDs.
        RateLimiter::for('activation', function (Request $request) {
            $id = strtolower((string) ($request->input('school_id') ?? $request->input('student_id_number')));

            return [
                Limit::perMinute(5)->by($id . '|' . $request->ip()),
                Limit::perMinute(20)->by($request->ip()),
            ];
        });
    }

    /**
     * Web sign-ins in the audit log. Failed attempts are recorded in
     * LoginRequest, which checks the password itself (so Laravel's Failed
     * event never fires); the portal apps record their own in AuthController.
     */
    private function auditSignIns(): void
    {
        \Illuminate\Support\Facades\Event::listen(\Illuminate\Auth\Events\Login::class, function ($event) {
            if ($event->guard === 'web') {
                \App\Models\AuditLog::record('auth.login', null, 'Signed in', $event->user);
            }
        });
        // Lockouts are recorded by App\Support\LoginThrottle, once per pause,
        // for the web login and the portal apps alike.
    }
}
