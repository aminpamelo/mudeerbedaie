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
        Schema::create('package_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('package_id')->constrained('packages')->onDelete('cascade');

            // Polymorphic relationship to products or courses
            $table->morphs('itemable'); // Creates itemable_type and itemable_id

            // Product-specific fields (ignored for courses)
            $table->integer('quantity')->default(1); // Quantity of product (always 1 for courses)
            $table->foreignId('product_variant_id')->nullable()->constrained('product_variants')->onDelete('cascade');
            $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->onDelete('set null');

            // Pricing overrides
            $table->decimal('custom_price', 10, 2)->nullable(); // Override item price in package
            $table->decimal('original_price', 10, 2); // Store original price for reference

            // Display and ordering
            $table->integer('sort_order')->default(0);
            $table->boolean('is_featured')->default(false); // Highlight this item in package display
            $table->text('package_description')->nullable(); // Custom description for this item in package

            $table->timestamps();

            // Indexes
            $table->index(['package_id', 'sort_order']);
            $table->unique(['package_id', 'itemable_type', 'itemable_id', 'product_variant_id'], 'package_item_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('package_items');
    }
};
