<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A two-way comment thread on a single host-logged video
 * (live_host_mentee_daily_videos). Staff (PIC/admin/assistant) leave feedback
 * the host reads in the Pocket; the host can reply back. `author_role` is
 * denormalized at write time so the thread renders correctly even if the
 * author's role changes later.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('live_host_mentee_video_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('video_id')
                ->constrained('live_host_mentee_daily_videos')
                ->cascadeOnDelete();
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->string('author_role', 20)->default('staff');
            $table->text('body');
            $table->timestamps();

            $table->index(['video_id', 'created_at'], 'lhmvc_video_created_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('live_host_mentee_video_comments');
    }
};
