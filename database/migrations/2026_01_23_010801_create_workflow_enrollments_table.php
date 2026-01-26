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
        Schema::create('workflow_enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('current_step_id')->nullable()->constrained('workflow_steps')->nullOnDelete();
            $table->enum('status', ['active', 'completed', 'paused', 'failed', 'exited'])->default('active');
            $table->timestamp('entered_at')->useCurrent();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('exited_at')->nullable();
            $table->string('exit_reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('workflow_id');
            $table->index('student_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workflow_enrollments');
    }
};
