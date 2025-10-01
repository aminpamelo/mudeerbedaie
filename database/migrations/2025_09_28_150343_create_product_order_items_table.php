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
        Schema::create('product_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('product_orders')->onDelete('cascade');
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_variant_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('warehouse_id')->nullable()->constrained()->onDelete('set null'); // Assigned warehouse

            // Product details (snapshot at order time)
            $table->string('product_name');
            $table->string('variant_name')->nullable();
            $table->string('sku');
            $table->json('product_snapshot'); // Complete product/variant details

            // Quantities
            $table->integer('quantity_ordered');
            $table->integer('quantity_fulfilled')->default(0);
            $table->integer('quantity_cancelled')->default(0);
            $table->integer('quantity_returned')->default(0);

            // Pricing
            $table->decimal('unit_price', 10, 2);
            $table->decimal('total_price', 10, 2);
            $table->decimal('unit_cost', 10, 2)->nullable(); // For profit calculations

            // Status
            $table->enum('status', [
                'pending', 'confirmed', 'reserved', 'picked',
                'packed', 'shipped', 'delivered', 'cancelled', 'returned',
            ])->default('pending');

            $table->timestamps();

            // Indexes for performance
            $table->index(['order_id', 'status']);
            $table->index(['product_id', 'product_variant_id']);
            $table->index(['warehouse_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_order_items');
    }
};
