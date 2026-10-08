<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per TikTok shop per day, straight from the Analytics API's
     * shop performance (granularity 1D) — the same figures as Seller Center.
     */
    public function up(): void
    {
        Schema::create('tiktok_shop_daily_performances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('platform_account_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->decimal('gmv', 14, 2)->default(0);
            $table->decimal('gmv_live', 14, 2)->default(0);
            $table->decimal('gmv_video', 14, 2)->default(0);
            $table->decimal('gmv_product_card', 14, 2)->default(0);
            $table->unsignedInteger('orders')->default(0);
            $table->unsignedInteger('sku_orders')->default(0);
            $table->unsignedInteger('units_sold')->default(0);
            $table->unsignedInteger('buyers')->default(0);
            $table->unsignedInteger('product_page_views')->default(0);
            $table->unsignedBigInteger('product_impressions')->default(0);
            $table->decimal('avg_order_value', 12, 2)->default(0);
            $table->decimal('refunds', 14, 2)->default(0);
            $table->unsignedInteger('cancellations_and_returns')->default(0);
            $table->string('currency', 8)->default('MYR');
            $table->timestamp('fetched_at')->nullable();
            $table->timestamps();

            $table->unique(['platform_account_id', 'date'], 'tt_shop_daily_account_date_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tiktok_shop_daily_performances');
    }
};
