<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('training_program_id')->constrained('training_programs')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees');
            $table->foreignId('enrolled_by')->constrained('users');
            $table->string('status')->default('enrolled'); // enrolled, attended, absent, cancelled
            $table->timestamp('attendance_confirmed_at')->nullable();
            $table->text('feedback')->nullable();
            $table->integer('feedback_rating')->nullable(); // 1-5
            $table->string('certificate_path')->nullable();
            $table->timestamps();

            $table->unique(['training_program_id', 'employee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_enrollments');
    }
};
