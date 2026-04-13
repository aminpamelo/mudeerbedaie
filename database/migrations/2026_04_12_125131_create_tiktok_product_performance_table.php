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
        if (Schema::hasTable('tiktok_product_performance')) {
            return;
        }

        Schema::create('tiktok_product_performance', function (Blueprint $table) {
            $table->id();
            $table->foreignId('platform_account_id')->constrained('platform_accounts')->cascadeOnDelete();
            $table->string('tiktok_product_id');
            $table->bigInteger('impressions')->default(0);
            $table->bigInteger('clicks')->default(0);
            $table->bigInteger('orders')->default(0);
            $table->decimal('gmv', 15, 2)->default(0);
            $table->bigInteger('buyers')->default(0);
            $table->decimal('conversion_rate', 8, 4)->default(0);
            $table->json('raw_response')->nullable();
            $table->timestamp('fetched_at');
            $table->timestamps();
            $table->index(['platform_account_id', 'tiktok_product_id'], 'tpp_account_product_idx');
            $table->index('fetched_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tiktok_product_performance');
    }
};
