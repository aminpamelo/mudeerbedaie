<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('platform_accounts', 'last_live_analytics_sync_at')) {
            return;
        }

        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            Schema::table('platform_accounts', function (Blueprint $table) {
                $table->timestamp('last_live_analytics_sync_at')->nullable()->after('last_analytics_sync_at');
            });
        } else {
            Schema::table('platform_accounts', function (Blueprint $table) {
                $table->timestamp('last_live_analytics_sync_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::table('platform_accounts', function (Blueprint $table) {
            $table->dropColumn('last_live_analytics_sync_at');
        });
    }
};
