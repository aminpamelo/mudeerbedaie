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
        if (Schema::hasColumn('platform_accounts', 'last_affiliate_sync_at')) {
            return;
        }

        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            Schema::table('platform_accounts', function (Blueprint $table) {
                $table->timestamp('last_affiliate_sync_at')->nullable()->after('last_inventory_sync_at');
                $table->timestamp('last_analytics_sync_at')->nullable()->after('last_affiliate_sync_at');
                $table->timestamp('last_finance_sync_at')->nullable()->after('last_analytics_sync_at');
            });
        } else {
            Schema::table('platform_accounts', function (Blueprint $table) {
                $table->timestamp('last_affiliate_sync_at')->nullable();
                $table->timestamp('last_analytics_sync_at')->nullable();
                $table->timestamp('last_finance_sync_at')->nullable();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('platform_accounts', function (Blueprint $table) {
            $table->dropColumn(['last_affiliate_sync_at', 'last_analytics_sync_at', 'last_finance_sync_at']);
        });
    }
};
