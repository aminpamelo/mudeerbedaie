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
        Schema::create('product_orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->unique();
            $table->foreignId('customer_id')->nullable()->constrained('users')->onDelete('set null'); // Registered customer
            $table->string('guest_email')->nullable(); // For guest orders
            $table->enum('status', [
                'draft', 'pending', 'confirmed', 'processing',
                'partially_shipped', 'shipped', 'delivered',
                'cancelled', 'refunded', 'returned',
            ])->default('pending');
            $table->enum('order_type', ['retail', 'wholesale', 'b2b'])->default('retail');
            $table->string('currency', 3)->default('MYR');

            // Financial details
            $table->decimal('subtotal', 10, 2)->default(0);
            $table->decimal('shipping_cost', 10, 2)->default(0);
            $table->decimal('tax_amount', 10, 2)->default(0);
            $table->decimal('total_amount', 10, 2)->default(0);
            $table->string('coupon_code')->nullable();
            $table->decimal('discount_amount', 10, 2)->default(0);

            // Dates
            $table->timestamp('order_date')->useCurrent();
            $table->date('required_delivery_date')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            // Notes and metadata
            $table->text('customer_notes')->nullable();
            $table->text('internal_notes')->nullable();
            $table->json('metadata')->nullable(); // Additional order data

            $table->timestamps();

            // Indexes for performance
            $table->index(['status', 'order_date']);
            $table->index(['customer_id', 'status']);
            $table->index(['order_date', 'status']);
            $table->index('guest_email');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_orders');
    }
};
