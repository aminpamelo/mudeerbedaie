<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2: the timetable's governing identity moves from the TikTok Shop to the
 * creator account. Uniqueness flips accordingly:
 *
 *   OLD  (platform_account_id, time_slot_id, day_of_week, is_template, schedule_date)
 *   NEW  (live_account_id,     time_slot_id, day_of_week, is_template, schedule_date)
 *
 * This enforces the confirmed rules: a single creator account cannot be live in
 * two overlapping slots (even across different shops), while ANY number of
 * accounts may be live for the same shop at the same time (shop is no longer in
 * the key). The platform_account_id FK keeps its supporting indexes
 * (lsa_platform_day_idx / lsa_platform_date_idx), so dropping the old unique is
 * safe on MySQL (no errno 1553). NULL live_account_id rows (legacy, unresolved)
 * are treated as distinct by both MySQL and SQLite, so they never collide.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('live_schedule_assignments')) {
            return;
        }

        Schema::table('live_schedule_assignments', function (Blueprint $table) {
            $table->dropUnique('lsa_unique_assignment');
        });

        Schema::table('live_schedule_assignments', function (Blueprint $table) {
            $table->unique(
                ['live_account_id', 'time_slot_id', 'day_of_week', 'is_template', 'schedule_date'],
                'lsa_unique_account_assignment'
            );
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('live_schedule_assignments')) {
            return;
        }

        Schema::table('live_schedule_assignments', function (Blueprint $table) {
            $table->dropUnique('lsa_unique_account_assignment');
        });

        // Restoring the platform-led unique can fail if the demoted shop axis now
        // holds rows that collide under the old key; guard so down() stays safe.
        $duplicates = DB::table('live_schedule_assignments')
            ->selectRaw('platform_account_id, time_slot_id, day_of_week, is_template, schedule_date, COUNT(*) as c')
            ->groupBy('platform_account_id', 'time_slot_id', 'day_of_week', 'is_template', 'schedule_date')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if (! $duplicates) {
            Schema::table('live_schedule_assignments', function (Blueprint $table) {
                $table->unique(
                    ['platform_account_id', 'time_slot_id', 'day_of_week', 'is_template', 'schedule_date'],
                    'lsa_unique_assignment'
                );
            });
        }
    }
};
