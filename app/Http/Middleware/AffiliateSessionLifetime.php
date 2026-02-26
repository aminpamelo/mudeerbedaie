<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AffiliateSessionLifetime
{
    /**
     * Extend session lifetime to 30 days for affiliate routes.
     */
    public function handle(Request $request, Closure $next): Response
    {
        config(['session.lifetime' => 43200]); // 30 days

        return $next($request);
    }
}
