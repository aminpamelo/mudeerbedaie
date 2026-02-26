<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
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
            // For MySQL, modify the enum to include 'funnel'
            DB::statement("ALTER TABLE product_orders MODIFY source ENUM('manual', 'platform_import', 'cart', 'api', 'funnel') DEFAULT 'manual'");
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
            // Revert to original enum without 'funnel'
            // First update any 'funnel' values to 'manual'
            DB::table('product_orders')->where('source', 'funnel')->update(['source' => 'manual']);
            DB::statement("ALTER TABLE product_orders MODIFY source ENUM('manual', 'platform_import', 'cart', 'api') DEFAULT 'manual'");
        }
        // For SQLite, no action needed as string type remains compatible
    }
};
