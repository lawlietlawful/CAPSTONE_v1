<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CounselorMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        if (auth()->check() && auth()->user()->isCounselor()) {
            return $next($request);
        }

        abort(403, 'Access denied. Guidance Counselors only.');
    }
}
