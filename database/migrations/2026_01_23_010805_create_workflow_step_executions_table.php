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
        Schema::create('workflow_step_executions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enrollment_id')->constrained('workflow_enrollments')->cascadeOnDelete();
            $table->foreignId('step_id')->constrained('workflow_steps')->cascadeOnDelete();
            $table->enum('status', ['pending', 'processing', 'completed', 'failed', 'skipped'])->default('pending');
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->json('result')->nullable();
            $table->integer('attempts')->default(0);
            $table->timestamp('created_at')->useCurrent();

            $table->index('enrollment_id');
            $table->index('step_id');
            $table->index('status');
            $table->index('scheduled_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workflow_step_executions');
    }
};
