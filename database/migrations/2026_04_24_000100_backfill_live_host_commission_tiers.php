<?php

use App\Services\LiveHost\BackfillTiersFromFlatRates;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Backfill production `live_host_platform_commission_rates` into the
     * new `live_host_platform_commission_tiers` rail using the
     * zero-override strategy (see BackfillTiersFromFlatRates for the full
     * rationale): each active flat rate becomes an open-ended Tier 1 row
     * whose `internal_percent` mirrors the old rate and whose
     * `l1_percent`/`l2_percent` are 0.00 until an admin configures real
     * schedules via the UI.
     *
     * Guarded on both tables existing so the migration is safe to replay
     * on environments where either rail hasn't been created yet, and the
     * service itself is idempotent on the unique
     * (user_id, platform_id, tier_number, effective_from) constraint.
     */
    public function up(): void
    {
        if (! Schema::hasTable('live_host_platform_commission_rates')) {
            return;
        }

        if (! Schema::hasTable('live_host_platform_commission_tiers')) {
            return;
        }

        app(BackfillTiersFromFlatRates::class)->run();
    }

    /**
     * Reverse the migrations.
     *
     * Intentionally a no-op. Once backfilled tier rows may have been
     * edited by admins through the UI (adjusting l1/l2 percents, tier
     * bands, effective windows), so we can't safely distinguish
     * "inserted by backfill" from "authored by admin" at rollback time.
     * Deleting rows here would risk losing real commission configuration.
     */
    public function down(): void
    {
        // no-op: see docblock above
    }
};
