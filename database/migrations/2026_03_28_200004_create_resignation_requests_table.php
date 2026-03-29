<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('resignation_requests')) {
            return;
        }

        Schema::create('resignation_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees');
            $table->date('submitted_date');
            $table->text('reason');
            $table->integer('notice_period_days');
            $table->date('last_working_date');
            $table->date('requested_last_date')->nullable();
            $table->string('status')->default('pending'); // pending, approved, rejected, withdrawn, completed
            $table->foreignId('approved_by')->nullable()->constrained('employees');
            $table->timestamp('approved_at')->nullable();
            $table->date('final_last_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resignation_requests');
    }
};
