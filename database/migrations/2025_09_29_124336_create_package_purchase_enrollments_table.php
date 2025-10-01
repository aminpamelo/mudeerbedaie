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
        Schema::create('package_purchase_enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('package_purchase_id')->constrained('package_purchases')->onDelete('cascade');
            $table->foreignId('enrollment_id')->constrained('enrollments')->onDelete('cascade');
            $table->foreignId('course_id')->constrained('courses')->onDelete('cascade');
            $table->foreignId('student_id')->constrained('students')->onDelete('cascade');

            // Track enrollment creation details
            $table->enum('enrollment_status', ['created', 'failed', 'cancelled'])->default('created');
            $table->timestamp('enrolled_at')->nullable();
            $table->text('enrollment_notes')->nullable();

            $table->timestamps();

            // Indexes and unique constraints
            $table->unique(['package_purchase_id', 'enrollment_id'], 'package_purchase_enrollment_unique');
            $table->index(['package_purchase_id', 'course_id']);
            $table->index(['student_id', 'course_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('package_purchase_enrollments');
    }
};
