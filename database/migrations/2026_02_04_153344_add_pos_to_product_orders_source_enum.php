<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE product_orders MODIFY source ENUM('manual', 'platform_import', 'cart', 'api', 'funnel', 'tiktok_shop', 'pos') DEFAULT 'manual'");
        }
        // SQLite uses string type, no constraint change needed
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::table('product_orders')->where('source', 'pos')->update(['source' => 'manual']);
            DB::statement("ALTER TABLE product_orders MODIFY source ENUM('manual', 'platform_import', 'cart', 'api', 'funnel', 'tiktok_shop') DEFAULT 'manual'");
        }
    }
};
