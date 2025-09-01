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
        Schema::create('enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->onDelete('cascade');
            $table->foreignId('course_id')->constrained('courses')->onDelete('cascade');
            $table->foreignId('enrolled_by')->constrained('users'); // Admin/Teacher who enrolled the student
            $table->enum('status', ['enrolled', 'active', 'completed', 'dropped', 'suspended', 'pending'])->default('enrolled');
            $table->date('enrollment_date');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->date('completion_date')->nullable();
            $table->decimal('enrollment_fee', 8, 2)->nullable(); // Fee at time of enrollment
            $table->text('notes')->nullable();
            $table->json('progress_data')->nullable(); // Store progress tracking data
            $table->timestamps();

            // Ensure a student can't be enrolled in the same course multiple times (unless completed/dropped)
            $table->unique(['student_id', 'course_id', 'status'], 'unique_active_enrollment');
            $table->index(['student_id', 'status']);
            $table->index(['course_id', 'status']);
            $table->index('enrollment_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('enrollments');
    }
};
