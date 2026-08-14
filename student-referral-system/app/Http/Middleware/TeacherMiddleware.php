<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class TeacherMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        if (auth()->check() && auth()->user()->isTeacher()) {
            return $next($request);
        }

        abort(403, 'Access denied. Teachers only.');
    }
}
