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
        if (Schema::hasTable('payroll_items')) {
            return;
        }

        Schema::create('payroll_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_run_id')->constrained('payroll_runs')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('salary_component_id')->nullable()->constrained('salary_components')->nullOnDelete();
            $table->string('component_code');
            $table->string('component_name');
            $table->enum('type', ['earning', 'deduction', 'employer_contribution']);
            $table->decimal('amount', 10, 2);
            $table->boolean('is_statutory')->default(false);
            $table->timestamps();

            $table->index(['payroll_run_id', 'employee_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payroll_items');
    }
};
