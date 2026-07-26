<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Strip the MySQL "ON UPDATE CURRENT_TIMESTAMP" footgun from set-once business
 * timestamps in the live-host domain. Laravel's schema builder gives the FIRST
 * `$table->timestamp()` column of a table `DEFAULT CURRENT_TIMESTAMP ON UPDATE
 * CURRENT_TIMESTAMP` on MySQL — so ANY row UPDATE silently rewrites that column
 * to now(). That is why live_sessions.scheduled_start_at (a schedule ANCHOR) kept
 * drifting to "now" on every verify/auto-verify, and it corrupts other set-once
 * anchors the same way. SQLite has no such behaviour, so this is MySQL-only and
 * tests never surfaced it.
 *
 * These columns must hold the moment the thing happened/was scheduled and never
 * auto-change. (fetched_at on the stats tables is intentionally "last fetched"
 * and is left alone.)
 */
return new class extends Migration
{
    /**
     * @var array<string, string>
     */
    private array $anchors = [
        'live_sessions' => 'scheduled_start_at',
        'live_session_gmv_adjustments' => 'adjusted_at',
        'payslip_sessions' => 'included_at',
        'payslips' => 'generated_at',
        'tiktok_report_imports' => 'uploaded_at',
    ];

    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return; // SQLite has no ON UPDATE CURRENT_TIMESTAMP behaviour.
        }

        foreach ($this->anchors as $table => $column) {
            if (Schema::hasColumn($table, $column)) {
                DB::statement("ALTER TABLE `{$table}` MODIFY `{$column}` TIMESTAMP NULL DEFAULT NULL");
            }
        }
    }

    public function down(): void
    {
        // Intentionally irreversible: restoring ON UPDATE CURRENT_TIMESTAMP would
        // re-introduce the corruption. The columns remain plain nullable timestamps.
    }
};
