<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * After the super admin resets a staff password to a temporary one, keep
 * that user on Change Password until they choose their own — the temporary
 * password was seen by someone else.
 */
class EnsurePasswordIsChanged
{
    private const ALLOWED = ['account.password.edit', 'password.update', 'logout'];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->must_change_password || $request->routeIs(...self::ALLOWED)) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Choose a new password before continuing.'], 403);
        }

        return redirect()->route('account.password.edit');
    }
}
