<?php

namespace App\Http\Controllers\Traits;

use App\Models\Funnel;
use App\Models\FunnelAffiliate;
use Illuminate\Http\Request;

trait ResolvesAffiliateId
{
    protected function resolveAffiliateId(Request $request, Funnel $funnel): ?int
    {
        if (! $funnel->isAffiliateEnabled()) {
            return null;
        }

        // Check query param first
        $refCode = $request->input('ref');
        if ($refCode) {
            $affiliate = FunnelAffiliate::where('ref_code', $refCode)->active()->first();
            if ($affiliate && $affiliate->funnels()->where('funnels.id', $funnel->id)->wherePivot('status', 'approved')->exists()) {
                // Store in cookie for future visits
                $cookieKey = "affiliate_ref_{$funnel->id}";
                cookie()->queue($cookieKey, $affiliate->id, 60 * 24 * 30);
                session([$cookieKey => $affiliate->id]);

                return $affiliate->id;
            }
        }

        // Check cookie/session
        $cookieKey = "affiliate_ref_{$funnel->id}";
        $affiliateId = $request->cookie($cookieKey) ?: session($cookieKey);
        if ($affiliateId) {
            $affiliate = FunnelAffiliate::where('id', $affiliateId)->active()->first();
            if ($affiliate) {
                return $affiliate->id;
            }
        }

        return null;
    }
}
