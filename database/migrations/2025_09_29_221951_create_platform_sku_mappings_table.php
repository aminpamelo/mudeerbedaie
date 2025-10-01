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
        Schema::create('platform_sku_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('platform_id')->constrained()->onDelete('cascade');
            $table->foreignId('platform_account_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_variant_id')->nullable()->constrained()->onDelete('cascade');

            // Platform SKU information
            $table->string('platform_sku')->index();
            $table->string('platform_product_name')->nullable();
            $table->string('platform_variation_name')->nullable();

            // Mapping configuration
            $table->boolean('is_active')->default(true);
            $table->json('mapping_metadata')->nullable(); // Store additional mapping info

            // Audit trail
            $table->timestamp('last_used_at')->nullable();
            $table->integer('usage_count')->default(0);

            $table->timestamps();

            // Unique constraint to prevent duplicate mappings
            $table->unique(['platform_id', 'platform_account_id', 'platform_sku'], 'unique_platform_sku');

            // Indexes for performance
            $table->index(['product_id', 'product_variant_id']);
            $table->index(['platform_account_id', 'is_active']);
            $table->index(['last_used_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('platform_sku_mappings');
    }
};
