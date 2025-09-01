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
        // Orders table (replaces invoices)
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->unique(); // Auto-generated order number
            $table->foreignId('enrollment_id')->constrained()->onDelete('cascade');
            $table->foreignId('student_id')->constrained()->onDelete('cascade');
            $table->foreignId('course_id')->constrained()->onDelete('cascade');
            $table->string('stripe_invoice_id')->unique(); // Stripe invoice ID
            $table->string('stripe_charge_id')->nullable(); // Stripe charge ID
            $table->string('stripe_payment_intent_id')->nullable(); // Stripe payment intent ID
            $table->decimal('amount', 10, 2); // Order total amount
            $table->string('currency', 3)->default('MYR'); // Currency code
            $table->enum('status', ['pending', 'paid', 'failed', 'refunded', 'void'])->default('pending');
            $table->date('period_start'); // Billing period start
            $table->date('period_end'); // Billing period end
            $table->enum('billing_reason', [
                'subscription_create',
                'subscription_cycle',
                'subscription_update',
                'subscription_threshold',
                'manual',
            ])->default('subscription_cycle');
            $table->timestamp('paid_at')->nullable(); // When payment was successful
            $table->timestamp('failed_at')->nullable(); // When payment failed
            $table->json('failure_reason')->nullable(); // Payment failure details
            $table->string('receipt_url')->nullable(); // Stripe receipt URL
            $table->decimal('stripe_fee', 8, 2)->nullable(); // Stripe processing fee
            $table->decimal('net_amount', 10, 2)->nullable(); // Amount after fees
            $table->json('metadata')->nullable(); // Additional data
            $table->timestamps();

            // Indexes for performance
            $table->index(['enrollment_id', 'status']);
            $table->index(['student_id', 'status']);
            $table->index(['course_id', 'status']);
            $table->index(['status', 'created_at']);
            $table->index('stripe_invoice_id');
            $table->index('order_number');
        });

        // Order items table for detailed line items
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->onDelete('cascade');
            $table->string('description'); // Line item description
            $table->integer('quantity')->default(1); // Quantity
            $table->decimal('unit_price', 8, 2); // Price per unit
            $table->decimal('total_price', 8, 2); // Total for this line item
            $table->string('stripe_line_item_id')->nullable(); // Stripe line item ID
            $table->json('metadata')->nullable(); // Additional line item data
            $table->timestamps();

            $table->index('order_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
    }
};
