<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * This migration fixes the MySQL auto-update behavior on the expires_at column.
     * By default, MySQL/MariaDB sets the first TIMESTAMP column to auto-update
     * on every UPDATE statement, which was causing magic links to expire immediately
     * after the first use.
     */
    public function up(): void
    {
        // Only run for MySQL/MariaDB - SQLite doesn't have this issue
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        // Remove the ON UPDATE CURRENT_TIMESTAMP behavior from expires_at
        // This ensures expires_at only changes when explicitly set
        DB::statement('ALTER TABLE payment_method_tokens MODIFY COLUMN expires_at TIMESTAMP NOT NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No need to reverse - the original behavior was a bug
    }
};
