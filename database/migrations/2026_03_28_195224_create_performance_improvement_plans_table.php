<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('performance_improvement_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('initiated_by')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('performance_review_id')->nullable()->constrained('performance_reviews')->nullOnDelete();
            $table->text('reason');
            $table->date('start_date');
            $table->date('end_date');
            $table->enum('status', ['active', 'extended', 'completed_improved', 'completed_not_improved', 'cancelled'])->default('active');
            $table->text('outcome_notes')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('performance_improvement_plans');
    }
};
