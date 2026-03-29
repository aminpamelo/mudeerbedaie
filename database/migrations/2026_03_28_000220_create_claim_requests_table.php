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
        if (Schema::hasTable('claim_requests')) {
            return;
        }

        Schema::create('claim_requests', function (Blueprint $table) {
            $table->id();
            $table->string('claim_number')->unique();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('claim_type_id')->constrained('claim_types')->cascadeOnDelete();
            $table->decimal('amount', 10, 2);
            $table->decimal('approved_amount', 10, 2)->nullable();
            $table->date('claim_date');
            $table->text('description');
            $table->string('receipt_path')->nullable();
            $table->enum('status', ['draft', 'pending', 'approved', 'rejected', 'paid'])->default('draft');
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('employees')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('rejected_reason')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->string('paid_reference')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'status']);
            $table->index(['claim_type_id', 'status']);
            $table->index('claim_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('claim_requests');
    }
};
