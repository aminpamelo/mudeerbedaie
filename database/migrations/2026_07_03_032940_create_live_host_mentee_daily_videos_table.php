<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A mentee's daily video log — the host records each video they made that
     * day (a mandatory daily KPI). Not an upload: the host provides a title and
     * an optional link to the video. Multiple videos per day are allowed, so no
     * unique key — daily compliance is simply "at least one video logged".
     * Mentee-scoped and lightweight; the PIC watches compliance from the Desk.
     */
    public function up(): void
    {
        Schema::create('live_host_mentee_daily_videos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mentee_id')->constrained('live_host_mentees')->cascadeOnDelete();
            $table->date('video_date');
            $table->string('title');
            $table->string('link')->nullable();
            $table->foreignId('logged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['mentee_id', 'video_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('live_host_mentee_daily_videos');
    }
};
