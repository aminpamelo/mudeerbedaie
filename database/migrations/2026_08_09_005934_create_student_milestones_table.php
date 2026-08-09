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
        Schema::create('student_milestones', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('class_student_id')->constrained('class_students')->cascadeOnDelete();
            $table->string('title');
            $table->timestamp('achieved_at')->useCurrent();
            $table->foreignId('awarded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type'); // attendance, syllabus, custom
            $table->timestamps();

            $table->index(['class_student_id', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_milestones');
    }
};
