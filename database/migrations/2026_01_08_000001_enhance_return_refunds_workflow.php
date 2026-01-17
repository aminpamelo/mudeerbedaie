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
        // Add workflow enhancement fields to return_refunds
        Schema::table('return_refunds', function (Blueprint $table) {
            // Staff assignment
            $table->foreignId('assigned_to')->nullable()->after('processed_by')->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at')->nullable()->after('assigned_to');

            // SLA tracking
            $table->enum('priority', ['low', 'medium', 'high', 'urgent'])->default('medium')->after('status');
            $table->timestamp('due_date')->nullable()->after('priority');
            $table->boolean('sla_breached')->default(false)->after('due_date');

            // Multi-level approval
            $table->decimal('approval_threshold', 10, 2)->nullable()->after('refund_amount');
            $table->boolean('requires_level2_approval')->default(false)->after('action');
            $table->foreignId('level1_approved_by')->nullable()->after('requires_level2_approval')->constrained('users')->nullOnDelete();
            $table->timestamp('level1_approved_at')->nullable()->after('level1_approved_by');
            $table->foreignId('level2_approved_by')->nullable()->after('level1_approved_at')->constrained('users')->nullOnDelete();
            $table->timestamp('level2_approved_at')->nullable()->after('level2_approved_by');

            // Partial refund support
            $table->boolean('is_partial_refund')->default(false)->after('refund_amount');
            $table->decimal('original_order_amount', 10, 2)->nullable()->after('is_partial_refund');
        });

        // Create return_refund_items table for partial refunds
        Schema::create('return_refund_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('return_refund_id')->constrained('return_refunds')->cascadeOnDelete();
            $table->foreignId('order_item_id')->nullable()->constrained('product_order_items')->nullOnDelete();
            $table->string('product_name');
            $table->string('variant_name')->nullable();
            $table->string('sku')->nullable();
            $table->integer('quantity_ordered')->default(0);
            $table->integer('quantity_returned')->default(0);
            $table->decimal('unit_price', 10, 2)->default(0);
            $table->decimal('refund_amount', 10, 2)->default(0);
            $table->enum('condition', ['unopened', 'opened', 'damaged', 'defective', 'other'])->default('unopened');
            $table->text('condition_notes')->nullable();
            $table->boolean('approved')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('return_refund_items');

        Schema::table('return_refunds', function (Blueprint $table) {
            $table->dropForeign(['assigned_to']);
            $table->dropForeign(['level1_approved_by']);
            $table->dropForeign(['level2_approved_by']);

            $table->dropColumn([
                'assigned_to',
                'assigned_at',
                'priority',
                'due_date',
                'sla_breached',
                'approval_threshold',
                'requires_level2_approval',
                'level1_approved_by',
                'level1_approved_at',
                'level2_approved_by',
                'level2_approved_at',
                'is_partial_refund',
                'original_order_amount',
            ]);
        });
    }
};
