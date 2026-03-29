<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('attendance_penalties')) {
            return;
        }

        Schema::create('attendance_penalties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('attendance_log_id')->constrained('attendance_logs')->cascadeOnDelete();
            $table->enum('penalty_type', ['late_arrival', 'early_departure', 'absent_without_leave']);
            $table->integer('penalty_minutes')->default(0);
            $table->integer('month');
            $table->integer('year');
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['employee_id', 'year', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_penalties');
    }
};
