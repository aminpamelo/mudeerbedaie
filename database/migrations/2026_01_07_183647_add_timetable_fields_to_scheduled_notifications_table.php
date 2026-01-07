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
        Schema::table('scheduled_notifications', function (Blueprint $table) {
            // Make session_id nullable since timetable-based notifications don't have sessions yet
            $table->foreignId('session_id')->nullable()->change();

            // Add timetable slot fields
            $table->date('scheduled_session_date')->nullable()->after('session_id');
            $table->time('scheduled_session_time')->nullable()->after('scheduled_session_date');

            // Add index for efficient querying
            $table->index(['class_id', 'scheduled_session_date', 'scheduled_session_time'], 'scheduled_notifications_timetable_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('scheduled_notifications', function (Blueprint $table) {
            $table->dropIndex('scheduled_notifications_timetable_index');
            $table->dropColumn(['scheduled_session_date', 'scheduled_session_time']);
        });
    }
};
