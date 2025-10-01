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
        Schema::create('package_purchases', function (Blueprint $table) {
            $table->id();
            $table->string('purchase_number')->unique();
            $table->foreignId('package_id')->constrained('packages')->onDelete('cascade');

            // Customer information
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('guest_email')->nullable(); // For guest purchases

            // Financial details
            $table->decimal('amount_paid', 10, 2);
            $table->decimal('original_amount', 10, 2); // Package price at time of purchase
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->string('currency', 3)->default('MYR');
            $table->string('coupon_code')->nullable();

            // Status tracking
            $table->enum('status', ['pending', 'processing', 'completed', 'failed', 'refunded', 'cancelled'])
                ->default('pending');

            // Integration with existing order systems
            $table->foreignId('product_order_id')->nullable()->constrained('product_orders')->onDelete('set null');

            // Payment details
            $table->string('stripe_payment_intent_id')->nullable();
            $table->string('stripe_payment_method_id')->nullable();
            $table->string('payment_method')->default('stripe'); // stripe, manual, bank_transfer, etc.

            // Timestamps
            $table->timestamp('purchased_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('refunded_at')->nullable();

            // Stock management tracking
            $table->boolean('stock_allocated')->default(false); // Whether stock has been reserved
            $table->boolean('stock_deducted')->default(false); // Whether stock has been actually deducted
            $table->json('stock_snapshot')->nullable(); // Snapshot of stock levels at purchase time

            // Additional data
            $table->json('package_snapshot')->nullable(); // Snapshot of package contents at purchase time
            $table->json('metadata')->nullable(); // Additional purchase metadata
            $table->text('customer_notes')->nullable();
            $table->text('admin_notes')->nullable();

            $table->timestamps();

            // Indexes
            $table->index(['status', 'created_at']);
            $table->index(['user_id', 'status']);
            $table->index('package_id');
            $table->index('product_order_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('package_purchases');
    }
};
