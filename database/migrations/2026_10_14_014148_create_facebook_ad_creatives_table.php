<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The creative behind each ad (thumbnail, copy, format, asset) for the creative scoreboard.
        Schema::create('facebook_ad_creatives', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facebook_ad_account_id')->constrained()->cascadeOnDelete();
            $table->string('ad_id', 40);
            $table->string('creative_id', 40)->nullable();
            $table->string('asset_key', 120)->nullable();
            $table->string('format', 20)->nullable();
            $table->string('status', 30)->nullable();
            $table->string('title')->nullable();
            $table->text('body')->nullable();
            $table->string('cta', 40)->nullable();
            $table->string('permalink', 500)->nullable();
            $table->text('thumbnail_url')->nullable();
            $table->timestamps();

            $table->unique('ad_id', 'fb_ad_creatives_ad_unique');
            $table->index('facebook_ad_account_id', 'fb_ad_creatives_account_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('facebook_ad_creatives');
    }
};
