<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('overtime_requests')) {
            return;
        }

        Schema::create('overtime_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->date('requested_date');
            $table->time('start_time');
            $table->time('end_time');
            $table->decimal('estimated_hours', 4, 1);
            $table->decimal('actual_hours', 4, 1)->nullable();
            $table->text('reason');
            $table->enum('status', ['pending', 'approved', 'rejected', 'completed', 'cancelled']);
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->datetime('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->decimal('replacement_hours_earned', 5, 1)->nullable();
            $table->decimal('replacement_hours_used', 5, 1)->default(0);
            $table->timestamps();

            $table->index(['employee_id', 'status']);
            $table->index('requested_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('overtime_requests');
    }
};
