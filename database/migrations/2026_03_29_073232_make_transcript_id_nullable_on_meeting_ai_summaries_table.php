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
        Schema::dropIfExists('meeting_ai_summaries');

        Schema::create('meeting_ai_summaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meeting_id')->constrained('meetings')->cascadeOnDelete();
            $table->foreignId('transcript_id')->nullable()->constrained('meeting_transcripts')->nullOnDelete();
            $table->text('summary');
            $table->json('key_points')->nullable();
            $table->json('suggested_tasks')->nullable();
            $table->enum('status', ['processing', 'completed', 'reviewed', 'failed'])->default('processing');
            $table->foreignId('reviewed_by')->nullable()->constrained('employees')->nullOnDelete();
            $table->datetime('reviewed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('meeting_ai_summaries');

        Schema::create('meeting_ai_summaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meeting_id')->constrained('meetings')->cascadeOnDelete();
            $table->foreignId('transcript_id')->constrained('meeting_transcripts')->cascadeOnDelete();
            $table->text('summary');
            $table->json('key_points')->nullable();
            $table->json('suggested_tasks')->nullable();
            $table->enum('status', ['processing', 'completed', 'reviewed', 'failed'])->default('processing');
            $table->foreignId('reviewed_by')->nullable()->constrained('employees')->nullOnDelete();
            $table->datetime('reviewed_at')->nullable();
            $table->timestamps();
        });
    }
};
