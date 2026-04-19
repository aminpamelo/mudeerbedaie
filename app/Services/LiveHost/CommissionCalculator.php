<?php

namespace App\Services\LiveHost;

use App\Models\LiveSession;

class CommissionCalculator
{
    /**
     * Compute commission for a single live session.
     *
     * Formula (per design doc §5.1):
     *   net_gmv        = gmv_amount + gmv_adjustment
     *   gmv_commission = net_gmv × host_platform_rate_percent
     *   per_live_rate  = missed ? 0 : host.per_live_rate_myr
     *   session_total  = gmv_commission + per_live_rate
     *
     * Stateless pure calculation — returns an array. Persistence of the
     * snapshot (Task 12) is handled by a separate method that wraps this.
     *
     * @return array{
     *     net_gmv: float,
     *     platform_rate_percent: float,
     *     gmv_commission: float,
     *     per_live_rate: float,
     *     session_total: float,
     *     warnings: array<int, string>
     * }
     */
    public function forSession(LiveSession $session): array
    {
        $warnings = [];

        $netGmv = (float) ($session->gmv_amount ?? 0)
            + (float) ($session->gmv_adjustment ?? 0);

        $host = $session->liveHost;
        $platformId = $session->platformAccount?->platform_id;

        $rate = $host?->platformCommissionRates
            ->firstWhere('platform_id', $platformId);

        // Only flag a missing platform rate when there is actual GMV to
        // compute against. A zero-GMV session with no rate produces zero
        // commission either way, so surfacing a warning would be noise.
        if (! $rate && $netGmv > 0) {
            $warnings[] = 'missing_platform_rate';
        }

        $ratePercent = (float) ($rate?->commission_rate_percent ?? 0);
        $gmvCommission = round($netGmv * $ratePercent / 100, 2);

        $profile = $host?->commissionProfile;
        $isMissed = $session->status === 'missed';
        $perLiveRate = ($isMissed || ! $profile)
            ? 0.0
            : round((float) $profile->per_live_rate_myr, 2);

        return [
            'net_gmv' => round($netGmv, 2),
            'platform_rate_percent' => $ratePercent,
            'gmv_commission' => $gmvCommission,
            'per_live_rate' => $perLiveRate,
            'session_total' => round($gmvCommission + $perLiveRate, 2),
            'warnings' => $warnings,
        ];
    }
}
