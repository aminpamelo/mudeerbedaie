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
        // Get locale from authenticated user
        if ($request->user()) {
            $locale = $request->user()->locale ?? config('app.locale');
        } else {
            // For guests, use session or default
            $locale = session('locale', config('app.locale'));
        }

        // Validate locale is supported
        if (! in_array($locale, ['en', 'ms'])) {
            $locale = config('app.locale');
        }

        App::setLocale($locale);

        return $next($request);
    }
}
