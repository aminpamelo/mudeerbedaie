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
        // Stripe customers table
        Schema::create('stripe_customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('stripe_customer_id')->unique();
            $table->json('metadata')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'stripe_customer_id']);
        });

        // Payment methods table
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('stripe_customer_id')->nullable()->constrained('stripe_customers')->onDelete('cascade');
            $table->enum('type', ['stripe_card', 'bank_transfer']);
            $table->string('stripe_payment_method_id')->nullable();
            $table->json('card_details')->nullable(); // For Stripe card details
            $table->json('bank_details')->nullable(); // For manual bank transfer details
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['user_id', 'type']);
            $table->index('stripe_payment_method_id');
        });

        // Payments table
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('payment_method_id')->nullable()->constrained('payment_methods')->onDelete('set null');
            $table->string('stripe_payment_intent_id')->nullable()->unique();
            $table->string('stripe_charge_id')->nullable();
            $table->enum('status', [
                'pending',
                'processing',
                'succeeded',
                'failed',
                'cancelled',
                'refunded',
                'partially_refunded',
                'requires_action',
                'requires_payment_method',
            ])->default('pending');
            $table->enum('type', ['stripe_card', 'bank_transfer']);
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('MYR');
            $table->decimal('stripe_fee', 8, 2)->nullable();
            $table->decimal('net_amount', 10, 2)->nullable();
            $table->text('description')->nullable();
            $table->json('stripe_metadata')->nullable();
            $table->json('failure_reason')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('notes')->nullable(); // For admin notes on manual payments
            $table->string('receipt_url')->nullable();
            $table->timestamps();

            $table->index(['invoice_id', 'status']);
            $table->index(['user_id', 'status']);
            $table->index(['type', 'status']);
            $table->index('stripe_payment_intent_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
        Schema::dropIfExists('payment_methods');
        Schema::dropIfExists('stripe_customers');
    }
};
