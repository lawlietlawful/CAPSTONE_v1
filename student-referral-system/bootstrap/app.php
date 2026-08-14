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

        $middleware->alias([
            'admin'      => \App\Http\Middleware\AdminMiddleware::class,
            'counselor'  => \App\Http\Middleware\CounselorMiddleware::class,
            'role'       => \App\Http\Middleware\RoleMiddleware::class,
            'teacher'    => \App\Http\Middleware\TeacherMiddleware::class,
            'student'    => \App\Http\Middleware\StudentMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
