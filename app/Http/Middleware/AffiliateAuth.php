<?php

namespace App\Http\Middleware;

use App\Models\FunnelAffiliate;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AffiliateAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $affiliateId = session('affiliate_id');

        if (! $affiliateId) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }

            return redirect()->route('affiliate.spa', ['any' => 'login']);
        }

        $affiliate = FunnelAffiliate::find($affiliateId);

        if (! $affiliate || ! $affiliate->isActive()) {
            session()->forget('affiliate_id');

            if ($request->expectsJson()) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }

            return redirect()->route('affiliate.spa', ['any' => 'login']);
        }

        $request->merge(['affiliate' => $affiliate]);

        return $next($request);
    }
}
