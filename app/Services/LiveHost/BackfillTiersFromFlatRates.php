<?php

namespace App\Services\LiveHost;

use App\Models\LiveHostPlatformCommissionRate;
use App\Models\LiveHostPlatformCommissionTier;

class BackfillTiersFromFlatRates
{
    /**
     * Backfill a single open-ended Tier 1 row for every active
     * `live_host_platform_commission_rates` row so that commission
     * computation under the new tier-based rail continues to pay each
     * host their historical rate. Uses the zero-override strategy:
     * `internal_percent` preserves the flat commission rate, but
     * `l1_percent` and `l2_percent` are hardcoded to 0.00 — uplines
     * receive no overrides until an admin explicitly configures real
     * tier schedules through the UI. This is the safest transition
     * because the override system becomes opt-in per-host rather than
     * silently paying the old flat overrides against the new rail.
     *
     * Idempotent: the unique constraint
     * (user_id, platform_id, tier_number, effective_from) lets us skip
     * any rate row whose matching Tier 1 record already exists. Running
     * the service twice produces no duplicates and returns 0 on the
     * second run.
     *
     * Returns the number of tier rows inserted.
     */
    public function run(): int
    {
        $created = 0;

        LiveHostPlatformCommissionRate::query()
            ->where('is_active', true)
            ->orderBy('id')
            ->each(function (LiveHostPlatformCommissionRate $rate) use (&$created): void {
                if ($rate->effective_from === null) {
                    return;
                }

                // Match Eloquent's date-cast serialization (YYYY-MM-DD 00:00:00
                // on SQLite) by passing a Carbon instance truncated to the day,
                // so the exists-check and the subsequent insert share an
                // identical binding for the tier table's `date` column.
                $effectiveFromDate = $rate->effective_from->copy()->startOfDay();
                $effectiveToDate = $rate->effective_to?->copy()->startOfDay();

                $exists = LiveHostPlatformCommissionTier::query()
                    ->where('user_id', $rate->user_id)
                    ->where('platform_id', $rate->platform_id)
                    ->where('tier_number', 1)
                    ->whereDate('effective_from', $effectiveFromDate->toDateString())
                    ->exists();

                if ($exists) {
                    return;
                }

                LiveHostPlatformCommissionTier::query()->create([
                    'user_id' => $rate->user_id,
                    'platform_id' => $rate->platform_id,
                    'tier_number' => 1,
                    'min_gmv_myr' => 0,
                    'max_gmv_myr' => null,
                    'internal_percent' => $rate->commission_rate_percent,
                    'l1_percent' => 0,
                    'l2_percent' => 0,
                    'effective_from' => $effectiveFromDate,
                    'effective_to' => $effectiveToDate,
                    'is_active' => (bool) $rate->is_active,
                ]);

                $created++;
            });

        return $created;
    }
}
