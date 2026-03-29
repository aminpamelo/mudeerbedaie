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
        if (Schema::hasTable('meeting_transcripts')) {
            return;
        }

        Schema::create('meeting_transcripts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meeting_id')->constrained('meetings')->cascadeOnDelete();
            $table->foreignId('recording_id')->constrained('meeting_recordings')->cascadeOnDelete();
            $table->longText('content');
            $table->string('language')->default('en');
            $table->enum('status', ['processing', 'completed', 'failed'])->default('processing');
            $table->datetime('processed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('meeting_transcripts');
    }
};
