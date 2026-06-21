<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // A session locale (set by the storefront language toggle) always wins so
        // the toggle works for everyone without mutating a user's saved profile.
        // Authenticated users otherwise fall back to their profile locale; guests
        // default to Malay (the public storefront's primary language).
        if ($request->user()) {
            $locale = session('locale', $request->user()->locale ?? config('app.locale'));
        } else {
            $locale = session('locale', 'ms');
        }

        // Validate locale is supported
        if (! in_array($locale, ['en', 'ms'])) {
            $locale = config('app.locale');
        }

        App::setLocale($locale);

        return $next($request);
    }
}
