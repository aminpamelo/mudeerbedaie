<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tiktok_shop_performance_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('platform_account_id')->constrained('platform_accounts')->cascadeOnDelete();
            $table->bigInteger('total_orders')->default(0);
            $table->decimal('total_gmv', 15, 2)->default(0);
            $table->bigInteger('total_buyers')->default(0);
            $table->bigInteger('total_video_views')->default(0);
            $table->bigInteger('total_product_impressions')->default(0);
            $table->decimal('conversion_rate', 8, 4)->default(0);
            $table->json('raw_response')->nullable();
            $table->timestamp('fetched_at');
            $table->timestamps();
            $table->index(['platform_account_id', 'fetched_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tiktok_shop_performance_snapshots');
    }
};
