<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('facebook_ad_insights', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facebook_ad_account_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('campaign_id')->nullable();
            $table->string('campaign_name')->nullable();
            $table->decimal('spend', 12, 2)->default(0);
            $table->unsignedBigInteger('impressions')->default(0);
            $table->unsignedInteger('clicks')->default(0);
            $table->unsignedBigInteger('reach')->nullable();
            $table->decimal('cpm', 10, 2)->nullable();
            $table->decimal('cpc', 10, 2)->nullable();
            $table->decimal('ctr', 8, 4)->nullable();
            $table->timestamps();

            $table->unique(['facebook_ad_account_id', 'date', 'campaign_id'], 'fb_ad_insights_account_date_campaign_unique');
            $table->index(['facebook_ad_account_id', 'date'], 'fb_ad_insights_account_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('facebook_ad_insights');
    }
};
