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
        Schema::create('return_refunds', function (Blueprint $table) {
            $table->id();
            $table->string('refund_number')->unique();
            $table->foreignId('order_id')->nullable()->constrained('product_orders')->nullOnDelete();
            $table->foreignId('package_id')->nullable()->constrained('packages')->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();

            // Return details
            $table->date('return_date');
            $table->text('reason')->nullable();
            $table->decimal('refund_amount', 10, 2)->default(0);

            // Action and decision
            $table->enum('action', ['pending', 'approved', 'rejected'])->default('pending');
            $table->text('action_reason')->nullable(); // Why approved or rejected
            $table->timestamp('action_date')->nullable();

            // Tracking and refund details
            $table->string('tracking_number')->nullable();
            $table->string('account_number')->nullable(); // Bank account for refund
            $table->string('account_holder_name')->nullable();
            $table->string('bank_name')->nullable();

            // Current status after action
            $table->enum('status', [
                'pending_review',
                'approved_pending_return',
                'item_received',
                'refund_processing',
                'refund_completed',
                'rejected',
                'cancelled'
            ])->default('pending_review');

            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            // Indexes
            $table->index('return_date');
            $table->index('action');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('return_refunds');
    }
};
