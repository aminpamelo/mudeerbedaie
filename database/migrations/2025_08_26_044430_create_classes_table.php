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
        Schema::create('classes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained('courses')->onDelete('cascade');
            $table->foreignId('teacher_id')->constrained('teachers')->onDelete('cascade');
            $table->string('title');
            $table->text('description')->nullable();
            $table->dateTime('date_time');
            $table->integer('duration_minutes')->default(60);
            $table->enum('class_type', ['individual', 'group'])->default('group');
            $table->integer('max_capacity')->nullable();
            $table->string('location')->nullable();
            $table->string('meeting_url')->nullable();
            $table->decimal('teacher_rate', 8, 2)->default(0);
            $table->enum('rate_type', ['per_class', 'per_student', 'per_session'])->default('per_class');
            $table->enum('commission_type', ['percentage', 'fixed'])->default('fixed');
            $table->decimal('commission_value', 8, 2)->default(0);
            $table->enum('status', ['scheduled', 'ongoing', 'completed', 'cancelled'])->default('scheduled');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('classes');
    }
};
