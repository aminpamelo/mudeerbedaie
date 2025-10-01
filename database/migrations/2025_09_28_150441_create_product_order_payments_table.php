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
        Schema::create('product_order_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('product_orders')->onDelete('cascade');

            // Payment method and provider
            $table->enum('payment_method', ['credit_card', 'debit_card', 'bank_transfer', 'cash', 'fpx', 'grabpay', 'boost']);
            $table->string('payment_provider')->nullable(); // stripe, fpx, etc.

            // Amount details
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('MYR');

            // Status and references
            $table->enum('status', ['pending', 'processing', 'completed', 'failed', 'cancelled', 'refunded']);
            $table->string('transaction_id')->nullable();
            $table->string('reference_number')->nullable();
            $table->string('gateway_response_id')->nullable(); // Provider's transaction ID

            // Timestamps
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('refunded_at')->nullable();

            // Additional data
            $table->text('failure_reason')->nullable();
            $table->json('gateway_response')->nullable(); // Full gateway response
            $table->json('metadata')->nullable();

            $table->timestamps();

            // Indexes
            $table->index(['order_id', 'status']);
            $table->index(['payment_method', 'status']);
            $table->index('transaction_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_order_payments');
    }
};
