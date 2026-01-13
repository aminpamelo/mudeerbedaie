<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * MySQL TIMESTAMP columns have automatic ON UPDATE CURRENT_TIMESTAMP behavior
     * that is very difficult to fully remove. The definitive fix is to change
     * the column to DATETIME, which does not have this behavior.
     */
    public function up(): void
    {
        // Only run for MySQL/MariaDB - SQLite doesn't have this issue
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        // Change expires_at from TIMESTAMP to DATETIME to completely eliminate
        // any MySQL auto-update timestamp behavior
        DB::statement('ALTER TABLE payment_method_tokens MODIFY COLUMN expires_at DATETIME NOT NULL');

        // Also fix last_used_at to be DATETIME to prevent any future issues
        DB::statement('ALTER TABLE payment_method_tokens MODIFY COLUMN last_used_at DATETIME NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Only run for MySQL/MariaDB
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        // Convert back to TIMESTAMP if needed (not recommended)
        DB::statement('ALTER TABLE payment_method_tokens MODIFY COLUMN expires_at TIMESTAMP NOT NULL');
        DB::statement('ALTER TABLE payment_method_tokens MODIFY COLUMN last_used_at TIMESTAMP NULL');
    }
};
