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
        if (Schema::hasTable('tiktok_affiliate_orders')) {
            return;
        }

        Schema::create('tiktok_affiliate_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('platform_account_id')->constrained('platform_accounts')->cascadeOnDelete();
            $table->foreignId('tiktok_creator_id')->nullable()->constrained('tiktok_creators')->nullOnDelete();
            $table->string('tiktok_order_id')->index();
            $table->string('creator_user_id')->nullable();
            $table->string('tiktok_product_id')->nullable();
            $table->string('order_status')->nullable();
            $table->decimal('order_amount', 15, 2)->default(0);
            $table->decimal('commission_amount', 15, 2)->default(0);
            $table->decimal('commission_rate', 8, 4)->default(0);
            $table->string('collaboration_type')->nullable();
            $table->timestamp('order_created_at')->nullable();
            $table->json('raw_response')->nullable();
            $table->timestamps();
            $table->unique(['platform_account_id', 'tiktok_order_id'], 'tao_account_order_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tiktok_affiliate_orders');
    }
};
