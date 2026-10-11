<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ends any session a deactivated account still has open. The sign-in paths
 * refuse a deactivated account, and deactivating revokes its API tokens, but
 * a web session that was already logged in would otherwise keep working
 * until the user happened to log out.
 *
 * Web: logged out and sent back to the login page with the reason.
 * API (portal apps): 401 with the reason, which the apps already treat as
 * "session over, back to the login screen".
 */
class EnsureAccountIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $api = $request->is('api/*');
        $user = $api ? $request->user('sanctum') : $request->user();

        if (! $user || ! $user->isDeactivated()) {
            return $next($request);
        }

        if ($api) {
            $token = $user->currentAccessToken();
            if ($token instanceof \Laravel\Sanctum\PersonalAccessToken) {
                $token->delete();
            }

            return response()->json(['message' => User::DEACTIVATED_MESSAGE], 401);
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->withErrors(['email' => User::DEACTIVATED_MESSAGE]);
    }
}
