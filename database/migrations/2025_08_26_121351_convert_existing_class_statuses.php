<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Convert existing class statuses to match new enum values
        // This migration should run AFTER the enum update migration

        // Convert 'ongoing' to 'active'
        \Illuminate\Support\Facades\DB::table('classes')
            ->where('status', 'ongoing')
            ->update(['status' => 'active']);

        // Convert 'scheduled' classes to 'active' if they have sessions, otherwise to 'draft'
        $scheduledClasses = \Illuminate\Support\Facades\DB::table('classes')
            ->where('status', 'scheduled')
            ->get();

        foreach ($scheduledClasses as $class) {
            $hasSessions = \Illuminate\Support\Facades\DB::table('class_sessions')
                ->where('class_id', $class->id)
                ->exists();

            $newStatus = $hasSessions ? 'active' : 'draft';

            \Illuminate\Support\Facades\DB::table('classes')
                ->where('id', $class->id)
                ->update(['status' => $newStatus]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Convert back to original statuses
        // This is a best-effort rollback

        // Convert 'active' back to 'scheduled' (most were originally scheduled)
        \Illuminate\Support\Facades\DB::table('classes')
            ->where('status', 'active')
            ->update(['status' => 'scheduled']);

        // Convert 'draft' back to 'scheduled'
        \Illuminate\Support\Facades\DB::table('classes')
            ->where('status', 'draft')
            ->update(['status' => 'scheduled']);

        // Note: We can't perfectly restore 'ongoing' status as we don't track which were originally ongoing
    }
};
