<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A rule with a strategy only governs campaigns tagged with that strategy.
        Schema::table('facebook_ad_rules', function (Blueprint $table) {
            $table->foreignId('facebook_ad_strategy_id')->nullable()->after('facebook_ad_account_id')->constrained()->cascadeOnDelete();
        });

        Schema::table('facebook_ad_launches', function (Blueprint $table) {
            $table->foreignId('facebook_ad_strategy_id')->nullable()->after('funnel_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('facebook_ad_rules', function (Blueprint $table) {
            $table->dropConstrainedForeignId('facebook_ad_strategy_id');
        });

        Schema::table('facebook_ad_launches', function (Blueprint $table) {
            $table->dropConstrainedForeignId('facebook_ad_strategy_id');
        });
    }
};
