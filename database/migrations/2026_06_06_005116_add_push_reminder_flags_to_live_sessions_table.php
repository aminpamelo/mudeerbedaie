<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dedupe flags for Live Host Pocket push reminders. The reminder commands stamp
 * these once a notification has been sent so repeated scheduler runs do not
 * re-notify the host. Plain nullable timestamps — portable across MySQL and
 * SQLite with no driver branching needed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('live_sessions', function (Blueprint $table) {
            $table->timestamp('reminder_15m_sent_at')->nullable()->after('scheduled_start_at');
            $table->timestamp('recap_reminder_sent_at')->nullable()->after('uploaded_at');
        });
    }

    public function down(): void
    {
        Schema::table('live_sessions', function (Blueprint $table) {
            $table->dropColumn(['reminder_15m_sent_at', 'recap_reminder_sent_at']);
        });
    }
};
