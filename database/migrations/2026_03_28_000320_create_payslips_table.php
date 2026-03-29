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
        if (Schema::hasTable('hr_payslips')) {
            return;
        }

        Schema::create('hr_payslips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_run_id')->constrained('payroll_runs')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->integer('month');
            $table->integer('year');
            $table->decimal('gross_salary', 10, 2);
            $table->decimal('total_deductions', 10, 2);
            $table->decimal('net_salary', 10, 2);
            $table->decimal('epf_employee', 8, 2)->default(0);
            $table->decimal('epf_employer', 8, 2)->default(0);
            $table->decimal('socso_employee', 8, 2)->default(0);
            $table->decimal('socso_employer', 8, 2)->default(0);
            $table->decimal('eis_employee', 8, 2)->default(0);
            $table->decimal('eis_employer', 8, 2)->default(0);
            $table->decimal('pcb_amount', 8, 2)->default(0);
            $table->integer('unpaid_leave_days')->default(0);
            $table->decimal('unpaid_leave_deduction', 8, 2)->default(0);
            $table->string('pdf_path')->nullable();
            $table->timestamps();

            $table->unique(['employee_id', 'month', 'year']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hr_payslips');
    }
};
