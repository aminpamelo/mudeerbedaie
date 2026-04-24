<?php

namespace App\Services\LiveHost;

use App\Models\LiveHostPlatformCommissionTier;
use App\Models\Platform;
use App\Models\User;
use Carbon\CarbonInterface;

class CommissionTierResolver
{
    public function resolveTier(User $host, Platform $platform, float $monthlyGmv, CarbonInterface $asOf): ?LiveHostPlatformCommissionTier
    {
        return LiveHostPlatformCommissionTier::query()
            ->where('user_id', $host->id)
            ->where('platform_id', $platform->id)
            ->where('is_active', true)
            ->whereDate('effective_from', '<=', $asOf)
            ->where(function ($q) use ($asOf) {
                $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $asOf);
            })
            ->where('min_gmv_myr', '<=', $monthlyGmv)
            ->where(function ($q) use ($monthlyGmv) {
                $q->whereNull('max_gmv_myr')->orWhere('max_gmv_myr', '>', $monthlyGmv);
            })
            ->orderByDesc('tier_number')
            ->first();
    }
}
