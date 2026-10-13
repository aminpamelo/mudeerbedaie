<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row = one ad's performance on one day (Monitor Iklan source of truth).
        Schema::create('facebook_ad_level_insights', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facebook_ad_account_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('campaign_id', 40);
            $table->string('campaign_name')->nullable();
            $table->string('objective', 40)->nullable();
            $table->string('adset_id', 40)->nullable();
            $table->string('adset_name')->nullable();
            $table->string('ad_id', 40);
            $table->string('ad_name')->nullable();
            $table->decimal('spend', 12, 2)->default(0);
            $table->unsignedInteger('impressions')->default(0);
            $table->unsignedInteger('reach')->default(0);
            $table->unsignedInteger('clicks')->default(0);
            $table->unsignedInteger('link_clicks')->default(0);
            $table->unsignedInteger('landing_page_views')->default(0);
            $table->unsignedInteger('video_views')->default(0);
            $table->unsignedInteger('thruplays')->default(0);
            $table->unsignedInteger('purchases')->default(0);
            $table->decimal('purchase_value', 12, 2)->default(0);
            $table->timestamps();

            $table->unique(['facebook_ad_account_id', 'date', 'ad_id'], 'fb_ad_level_account_date_ad_unique');
            $table->index(['date', 'facebook_ad_account_id'], 'fb_ad_level_date_account_idx');
            $table->index('campaign_id', 'fb_ad_level_campaign_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('facebook_ad_level_insights');
    }
};
