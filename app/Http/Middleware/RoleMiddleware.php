<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        try {
            $user = $request->user();
        } catch (DecryptException) {
            // Stale session cookie encrypted with an old APP_KEY — flush it
            session()->invalidate();
            session()->regenerateToken();

            if ($request->expectsJson()) {
                abort(401, 'Session expired. Please refresh.');
            }

            return redirect()->route('login');
        }

        if (! $user) {
            if ($request->expectsJson()) {
                abort(401, 'Unauthenticated.');
            }

            return redirect()->route('login');
        }

        if (! $user->hasAnyRole($roles)) {
            abort(403, 'You do not have permission to access this resource.');
        }

        return $next($request);
    }
}
