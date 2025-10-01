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
        Schema::create('product_cart_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cart_id')->constrained('product_carts')->onDelete('cascade');
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_variant_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('warehouse_id')->nullable()->constrained()->onDelete('set null'); // Preferred fulfillment warehouse
            $table->integer('quantity');
            $table->decimal('unit_price', 10, 2);
            $table->decimal('total_price', 10, 2);
            $table->json('product_snapshot')->nullable(); // Store product details at time of adding
            $table->timestamps();

            // Ensure unique product/variant per cart
            $table->unique(['cart_id', 'product_id', 'product_variant_id']);

            // Indexes for performance
            $table->index(['cart_id', 'created_at']);
            $table->index(['product_id', 'product_variant_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_cart_items');
    }
};
