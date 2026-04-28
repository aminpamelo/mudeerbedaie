<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The original unique key on (platform_account_id, time_slot_id, day_of_week,
 * is_template) blocks any second non-template row for the same platform/slot/
 * day-of-week regardless of schedule_date — so a slot scheduled on one
 * Saturday could never be scheduled on the next. Replace it with a key that
 * also includes schedule_date. Template uniqueness (NULL schedule_date) is
 * enforced at the application layer via FormRequest validation, since SQL
 * UNIQUE indexes treat NULLs as distinct.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('live_schedule_assignments')) {
            return;
        }

        if ($this->indexExists('live_schedule_assignments', 'unique_template_slot')) {
            Schema::table('live_schedule_assignments', function (Blueprint $table) {
                $table->dropUnique('unique_template_slot');
            });
        }

        if (! $this->indexExists('live_schedule_assignments', 'lsa_unique_assignment')) {
            Schema::table('live_schedule_assignments', function (Blueprint $table) {
                $table->unique(
                    ['platform_account_id', 'time_slot_id', 'day_of_week', 'is_template', 'schedule_date'],
                    'lsa_unique_assignment'
                );
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('live_schedule_assignments')) {
            return;
        }

        if ($this->indexExists('live_schedule_assignments', 'lsa_unique_assignment')) {
            Schema::table('live_schedule_assignments', function (Blueprint $table) {
                $table->dropUnique('lsa_unique_assignment');
            });
        }

        if (! $this->indexExists('live_schedule_assignments', 'unique_template_slot')) {
            Schema::table('live_schedule_assignments', function (Blueprint $table) {
                $table->unique(
                    ['platform_account_id', 'time_slot_id', 'day_of_week', 'is_template'],
                    'unique_template_slot'
                );
            });
        }
    }

    private function indexExists(string $table, string $index): bool
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            $rows = DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$index]);

            return ! empty($rows);
        }

        $rows = DB::select("PRAGMA index_list(`{$table}`)");
        foreach ($rows as $row) {
            if (($row->name ?? null) === $index) {
                return true;
            }
        }

        return false;
    }
};
