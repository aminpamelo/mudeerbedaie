<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // State of the ad-level "Monitor Iklan" sync, per ad account.
        Schema::table('facebook_ad_accounts', function (Blueprint $table) {
            $table->timestamp('monitor_synced_at')->nullable()->after('account_status');
            $table->text('monitor_sync_error')->nullable()->after('monitor_synced_at');
            $table->decimal('daily_budget', 12, 2)->nullable()->after('monitor_sync_error');
            $table->unsignedInteger('lifetime_budgets')->default(0)->after('daily_budget');
            $table->json('campaign_budgets')->nullable()->after('lifetime_budgets');
        });
    }

    public function down(): void
    {
        Schema::table('facebook_ad_accounts', function (Blueprint $table) {
            $table->dropColumn(['monitor_synced_at', 'monitor_sync_error', 'daily_budget', 'lifetime_budgets', 'campaign_budgets']);
        });
    }
};
