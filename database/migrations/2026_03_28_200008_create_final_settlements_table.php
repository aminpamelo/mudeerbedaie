<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('final_settlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees');
            $table->foreignId('resignation_request_id')->nullable()->constrained('resignation_requests');
            $table->decimal('prorated_salary', 10, 2)->default(0);
            $table->decimal('leave_encashment', 10, 2)->default(0);
            $table->decimal('leave_encashment_days', 5, 1)->default(0);
            $table->decimal('other_earnings', 10, 2)->default(0);
            $table->decimal('other_deductions', 10, 2)->default(0);
            $table->decimal('epf_employee', 8, 2)->default(0);
            $table->decimal('epf_employer', 8, 2)->default(0);
            $table->decimal('socso_employee', 8, 2)->default(0);
            $table->decimal('eis_employee', 8, 2)->default(0);
            $table->decimal('pcb_amount', 8, 2)->default(0);
            $table->decimal('total_gross', 10, 2)->default(0);
            $table->decimal('total_deductions', 10, 2)->default(0);
            $table->decimal('net_amount', 10, 2)->default(0);
            $table->string('status')->default('draft'); // draft, calculated, approved, paid
            $table->text('notes')->nullable();
            $table->string('pdf_path')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('final_settlements');
    }
};
