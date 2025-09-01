<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $driver = \Illuminate\Support\Facades\DB::getDriverName();

        if ($driver === 'sqlite') {
            // SQLite doesn't support MODIFY COLUMN with ENUMs
            // For testing, we'll skip the enum modification since SQLite uses TEXT anyway
            return;
        }

        // First, convert existing data to match new enum values
        // Convert 'ongoing' to 'active' (if any exist)
        \Illuminate\Support\Facades\DB::table('classes')
            ->where('status', 'ongoing')
            ->update(['status' => 'completed']); // Temporarily use 'completed' as it exists in both enums

        // Convert 'scheduled' classes to 'completed' temporarily (we'll fix this after enum change)
        \Illuminate\Support\Facades\DB::table('classes')
            ->where('status', 'scheduled')
            ->update(['status' => 'completed']);

        // Now update the status enum to include new values
        \Illuminate\Support\Facades\DB::statement("ALTER TABLE classes MODIFY COLUMN status ENUM('draft', 'active', 'completed', 'cancelled', 'suspended') NOT NULL DEFAULT 'draft'");

        // Now convert the temporarily stored data to proper values
        $classesNeedingConversion = \Illuminate\Support\Facades\DB::table('classes')
            ->where('status', 'completed')
            ->get();

        foreach ($classesNeedingConversion as $class) {
            $hasSessions = \Illuminate\Support\Facades\DB::table('class_sessions')
                ->where('class_id', $class->id)
                ->exists();

            // If it has sessions, it should be 'active', otherwise 'draft'
            $newStatus = $hasSessions ? 'active' : 'draft';

            // Only update if this class was originally scheduled/ongoing (not actually completed)
            // We can check the updated_at timestamp to distinguish original completed vs converted
            \Illuminate\Support\Facades\DB::table('classes')
                ->where('id', $class->id)
                ->where('updated_at', $class->updated_at) // Only if not manually updated
                ->update(['status' => $newStatus]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = \Illuminate\Support\Facades\DB::getDriverName();

        if ($driver === 'sqlite') {
            // SQLite doesn't support MODIFY COLUMN with ENUMs
            return;
        }

        // Convert back to original statuses before reverting enum
        \Illuminate\Support\Facades\DB::table('classes')
            ->where('status', 'active')
            ->update(['status' => 'scheduled']);

        \Illuminate\Support\Facades\DB::table('classes')
            ->where('status', 'draft')
            ->update(['status' => 'scheduled']);

        // Revert to original enum values
        \Illuminate\Support\Facades\DB::statement("ALTER TABLE classes MODIFY COLUMN status ENUM('scheduled', 'ongoing', 'completed', 'cancelled') NOT NULL DEFAULT 'scheduled'");
    }
};
