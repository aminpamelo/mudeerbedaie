<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * This migration adds 'tiktok_shop' to the source enum for TikTok Shop order sync.
     */
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            // Update source enum to include 'tiktok_shop'
            DB::statement("ALTER TABLE product_orders MODIFY source ENUM('manual', 'platform_import', 'cart', 'api', 'funnel', 'tiktok_shop') DEFAULT 'manual'");
        } else {
            // For SQLite (and other drivers), change to string which accepts any value
            // SQLite doesn't enforce enum constraints anyway
            Schema::table('product_orders', function (Blueprint $table) {
                $table->string('source')->default('manual')->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            // First update any 'tiktok_shop' values to 'platform_import' (closest equivalent)
            DB::table('product_orders')->where('source', 'tiktok_shop')->update(['source' => 'platform_import']);

            // Revert to original enum without 'tiktok_shop'
            DB::statement("ALTER TABLE product_orders MODIFY source ENUM('manual', 'platform_import', 'cart', 'api', 'funnel') DEFAULT 'manual'");
        }
        // For SQLite, no action needed as string type remains compatible
    }
};
