<?php

declare(strict_types=1);

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
        Schema::create('pending_platform_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('platform_id')->constrained()->cascadeOnDelete();
            $table->foreignId('platform_account_id')->constrained()->cascadeOnDelete();

            // TikTok Product Data
            $table->string('platform_product_id');
            $table->string('platform_sku')->nullable()->index();
            $table->string('name', 500);
            $table->text('description')->nullable();

            // Pricing
            $table->decimal('price', 12, 2)->nullable();
            $table->decimal('original_price', 12, 2)->nullable();
            $table->string('currency', 10)->default('MYR');

            // Product Details
            $table->string('category_id')->nullable();
            $table->string('category_name', 500)->nullable();
            $table->string('brand')->nullable();

            // Images
            $table->text('main_image_url')->nullable();
            $table->json('images')->nullable();

            // Variants (for variable products)
            $table->json('variants')->nullable();

            // Stock Info
            $table->integer('quantity')->default(0);

            // Matching Suggestions
            $table->foreignId('suggested_product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->foreignId('suggested_variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->decimal('match_confidence', 5, 2)->nullable();
            $table->string('match_reason')->nullable();

            // Status
            $table->enum('status', ['pending', 'linked', 'created', 'ignored'])->default('pending')->index();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();

            // Full API Response
            $table->json('raw_data')->nullable();

            // Timestamps
            $table->timestamp('fetched_at')->nullable();
            $table->timestamps();

            // Unique constraint - one TikTok product per account
            $table->unique(['platform_account_id', 'platform_product_id'], 'unique_platform_product');
        });

        // Add product sync fields to platform_accounts if not exists
        if (! Schema::hasColumn('platform_accounts', 'last_product_sync_at')) {
            Schema::table('platform_accounts', function (Blueprint $table) {
                $table->timestamp('last_product_sync_at')->nullable()->after('last_order_sync_at');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pending_platform_products');

        if (Schema::hasColumn('platform_accounts', 'last_product_sync_at')) {
            Schema::table('platform_accounts', function (Blueprint $table) {
                $table->dropColumn('last_product_sync_at');
            });
        }
    }
};
