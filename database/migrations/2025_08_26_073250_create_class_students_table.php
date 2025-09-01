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
        Schema::create('class_students', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_id')->constrained('classes')->onDelete('cascade');
            $table->foreignId('student_id')->constrained('students')->onDelete('cascade');
            $table->timestamp('enrolled_at')->default(now());
            $table->timestamp('left_at')->nullable();
            $table->enum('status', ['active', 'transferred', 'quit', 'completed'])->default('active');
            $table->text('reason')->nullable();
            $table->timestamps();

            $table->unique(['class_id', 'student_id', 'enrolled_at'], 'class_student_enrollment_unique');
            $table->index(['class_id', 'status']);
            $table->index(['student_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('class_students');
    }
};
