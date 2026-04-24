<?php

namespace App\Services\LiveHost;

use App\Models\LiveHostPlatformCommissionTier;
use App\Models\Platform;
use App\Models\User;
use Carbon\CarbonInterface;

class CommissionTierResolver
{
    /**
     * Resolve the commission tier that applies to a host on a platform for a
     * given monthly GMV amount at a point in time. GMV boundaries are
     * min-inclusive / max-exclusive, and a `null` max_gmv_myr denotes an
     * open-ended top tier with no upper bound. The effective window requires
     * `effective_from <= asOf <= (effective_to ?? asOf)`, and `is_active = true`
     * is checked independently — an inactive row inside its window will not
     * match.
     *
     * Returns `null` when no tier matches the (host, platform, GMV, asOf)
     * combination; callers treat this as zero commission. The
     * `orderByDesc('tier_number')` tiebreaker is a safety net for overlapping
     * configurations — Task 11's FormRequest validation prevents overlaps at
     * the UI layer, so in practice at most one row matches.
     */
    public function resolveTier(User $host, Platform $platform, float $monthlyGmv, CarbonInterface $asOf): ?LiveHostPlatformCommissionTier
    {
        return LiveHostPlatformCommissionTier::query()
            ->where('user_id', $host->id)
            ->where('platform_id', $platform->id)
            ->where('is_active', true)
            ->where('effective_from', '<=', $asOf->toDateString())
            ->where(function ($q) use ($asOf) {
                $q->whereNull('effective_to')->orWhere('effective_to', '>=', $asOf->toDateString());
            })
            ->where('min_gmv_myr', '<=', $monthlyGmv)
            ->where(function ($q) use ($monthlyGmv) {
                $q->whereNull('max_gmv_myr')->orWhere('max_gmv_myr', '>', $monthlyGmv);
            })
            ->orderByDesc('tier_number')
            ->first();
    }

    /**
     * Check whether the given host has ANY active commission tier row
     * configured for the given platform at a point in time — ignoring GMV
     * boundaries entirely. This lets callers distinguish "host has a
     * schedule but GMV is below floor" from "host has no schedule at all",
     * which is important for diagnostic reasons in commission calculations.
     *
     * Same filtering as `resolveTier()` on (user_id, platform_id, is_active,
     * effective_from/to), but NOT on min_gmv_myr / max_gmv_myr.
     */
    public function hasAnyActiveTier(User $host, Platform $platform, CarbonInterface $asOf): bool
    {
        return LiveHostPlatformCommissionTier::query()
            ->where('user_id', $host->id)
            ->where('platform_id', $platform->id)
            ->where('is_active', true)
            ->where('effective_from', '<=', $asOf->toDateString())
            ->where(function ($q) use ($asOf) {
                $q->whereNull('effective_to')->orWhere('effective_to', '>=', $asOf->toDateString());
            })
            ->exists();
    }
}
