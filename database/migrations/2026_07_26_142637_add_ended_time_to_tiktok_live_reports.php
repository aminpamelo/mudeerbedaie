<?php

use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Persist the live's END time. The TikTok sync captured start + duration but
 * discarded the end timestamp, so ended_time was null everywhere and every
 * consumer had to re-derive it (launched_time + duration) — which some forgot,
 * shrinking linked live cards on the calendar. Add the column to
 * tiktok_live_reports (actual_live_records already has it) and backfill both from
 * duration so the end is a real, stored field going forward.
 */
return new class extends Migration
{
    public function up(): void
    {
        // CRITICAL (MySQL): actual_live_records.launched_time was created as
        // TIMESTAMP ... ON UPDATE CURRENT_TIMESTAMP (Laravel's default for the
        // first timestamp column on MySQL). That silently rewrites launched_time
        // to now() on ANY row UPDATE — so the ended_time backfill below (or any
        // future write) would corrupt every live's real start time. Strip the
        // auto-default/auto-update first. SQLite has no such behaviour (nothing to
        // fix, and tests never surfaced this — the MySQL/SQLite trap).
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE actual_live_records MODIFY launched_time TIMESTAMP NULL DEFAULT NULL');
        }

        if (! Schema::hasColumn('tiktok_live_reports', 'ended_time')) {
            Schema::table('tiktok_live_reports', function (Blueprint $table) {
                $table->dateTime('ended_time')->nullable()->after('launched_time');
            });
        }

        // Backfill ended_time = launched_time + duration_seconds where derivable.
        // Done in PHP (Carbon) so it behaves identically on MySQL and SQLite — no
        // driver-specific date arithmetic. launched_time is preserved explicitly as
        // belt-and-suspenders against any residual auto-update behaviour.
        foreach (['actual_live_records', 'tiktok_live_reports'] as $table) {
            DB::table($table)
                ->whereNull('ended_time')
                ->whereNotNull('launched_time')
                ->where('duration_seconds', '>', 0)
                ->orderBy('id')
                ->chunkById(500, function ($rows) use ($table) {
                    foreach ($rows as $row) {
                        DB::table($table)->where('id', $row->id)->update([
                            'launched_time' => $row->launched_time,
                            'ended_time' => CarbonImmutable::parse($row->launched_time)
                                ->addSeconds((int) $row->duration_seconds)
                                ->format('Y-m-d H:i:s'),
                        ]);
                    }
                });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('tiktok_live_reports', 'ended_time')) {
            Schema::table('tiktok_live_reports', function (Blueprint $table) {
                $table->dropColumn('ended_time');
            });
        }
    }
};
