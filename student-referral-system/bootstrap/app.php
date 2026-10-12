<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Applies `throttle:api` to every route in routes/api.php. The limiter is
        // defined in AppServiceProvider::configureRateLimiting(). Without this the
        // API group carried no rate limiting at all.
        $middleware->throttleApi();

        // X-Frame-Options, nosniff, Referrer-Policy… on every response.
        $middleware->append(\App\Http\Middleware\SecurityHeaders::class);

        // A deactivated account loses any session it still has open — see
        // EnsureAccountIsActive.
        $middleware->web(append: [\App\Http\Middleware\EnsureAccountIsActive::class]);

        // Changing a password (or the super admin resetting it) signs that
        // account out of every other session; then a reset account is kept
        // on Change Password until it picks its own.
        $middleware->web(append: [
            \Illuminate\Session\Middleware\AuthenticateSession::class,
            \App\Http\Middleware\EnsurePasswordIsChanged::class,
        ]);
        $middleware->api(append: [\App\Http\Middleware\EnsureAccountIsActive::class]);

        $middleware->alias([
            'admin'       => \App\Http\Middleware\AdminMiddleware::class,
            'counselor'   => \App\Http\Middleware\CounselorMiddleware::class,
            'super_admin' => \App\Http\Middleware\SuperAdminMiddleware::class,
            'role'        => \App\Http\Middleware\RoleMiddleware::class,
            'teacher'     => \App\Http\Middleware\TeacherMiddleware::class,
            'student'     => \App\Http\Middleware\StudentMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
