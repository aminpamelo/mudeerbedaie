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
            // For testing, we'll skip this migration since SQLite uses TEXT anyway
            return;
        }

        // MySQL/PostgreSQL: Update the status enum to include new values
        \Illuminate\Support\Facades\DB::statement("ALTER TABLE class_sessions MODIFY COLUMN status ENUM('scheduled', 'ongoing', 'completed', 'cancelled', 'no_show', 'rescheduled') NOT NULL DEFAULT 'scheduled'");
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

        // MySQL/PostgreSQL: Revert to original enum values
        \Illuminate\Support\Facades\DB::statement("ALTER TABLE class_sessions MODIFY COLUMN status ENUM('scheduled', 'completed', 'cancelled') NOT NULL DEFAULT 'scheduled'");
    }
};
