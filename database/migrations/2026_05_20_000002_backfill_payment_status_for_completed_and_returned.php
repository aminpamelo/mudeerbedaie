<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // The original payment_status backfill (2026_05_20_000001) did not
        // account for two `product_orders.status` values that imply payment
        // had already cleared:
        //   - `completed`  (added for TikTok orders — fulfilled, paid earlier)
        //   - `returned`   (returns happen after payment)
        // Backfill those rows that still carry the default 'pending'.
        DB::table('product_orders')
            ->where('status', 'completed')
            ->where('payment_status', 'pending')
            ->update(['payment_status' => 'paid']);

        DB::table('product_orders')
            ->where('status', 'returned')
            ->where('payment_status', 'pending')
            ->update(['payment_status' => 'refunded']);
    }

    public function down(): void
    {
        // No-op: we cannot reliably distinguish rows we updated here from
        // those that were 'paid'/'refunded' before this migration ran.
    }
};
