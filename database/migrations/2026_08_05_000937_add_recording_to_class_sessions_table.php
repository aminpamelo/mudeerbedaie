<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A per-session recording link (e.g. a YouTube/Vimeo/Drive URL) that enrolled
     * students can watch after the session. Visibility is gated by enrolment, not
     * by this column alone.
     */
    public function up(): void
    {
        Schema::table('class_sessions', function (Blueprint $table): void {
            $table->string('recording_url')->nullable()->after('teacher_notes');
            $table->timestamp('recording_available_at')->nullable()->after('recording_url');
        });
    }

    public function down(): void
    {
        Schema::table('class_sessions', function (Blueprint $table): void {
            $table->dropColumn(['recording_url', 'recording_available_at']);
        });
    }
};
