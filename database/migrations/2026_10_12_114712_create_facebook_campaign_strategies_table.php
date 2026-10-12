<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('facebook_campaign_strategies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facebook_ad_account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('facebook_ad_strategy_id')->constrained()->cascadeOnDelete();
            $table->string('campaign_id', 40);
            $table->string('campaign_name')->nullable();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Facebook campaign ids are globally unique — one strategy per campaign.
            $table->unique('campaign_id', 'fb_campaign_strategies_campaign_unique');
            $table->index('facebook_ad_strategy_id', 'fb_campaign_strategies_strategy_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('facebook_campaign_strategies');
    }
};
