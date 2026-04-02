<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Strategy:
     *  - MySQL: ALTER COLUMN directly (avoids renameColumn on enum which needs Doctrine DBAL)
     *  - SQLite: rename → add text → copy data → drop old (SQLite cannot modify columns)
     */
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            // Snapshot existing values before altering the column
            $rows = DB::table('employees')->whereNotNull('employment_type')->get(['id', 'employment_type']);

            // Change the column type from enum to text in one statement
            DB::statement('ALTER TABLE employees MODIFY employment_type TEXT NULL');

            // Re-encode any plain string values (old enum) as JSON arrays
            foreach ($rows as $row) {
                $value = $row->employment_type;
                // If it's already a valid JSON array, leave it; otherwise wrap it
                $decoded = json_decode($value, true);
                if (! is_array($decoded)) {
                    DB::table('employees')->where('id', $row->id)->update([
                        'employment_type' => json_encode([$value]),
                    ]);
                }
            }
        } else {
            // SQLite path: rename → add text → copy → drop
            Schema::table('employees', function (Blueprint $table) {
                $table->renameColumn('employment_type', 'employment_type_old');
            });

            Schema::table('employees', function (Blueprint $table) {
                $table->text('employment_type')->nullable()->after('employment_type_old');
            });

            DB::table('employees')->whereNotNull('employment_type_old')->get(['id', 'employment_type_old'])->each(function ($row) {
                $decoded = json_decode($row->employment_type_old, true);
                DB::table('employees')->where('id', $row->id)->update([
                    'employment_type' => is_array($decoded) ? $row->employment_type_old : json_encode([$row->employment_type_old]),
                ]);
            });

            Schema::table('employees', function (Blueprint $table) {
                $table->dropColumn('employment_type_old');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            $rows = DB::table('employees')->whereNotNull('employment_type')->get(['id', 'employment_type']);

            DB::statement("ALTER TABLE employees MODIFY employment_type ENUM('full_time','part_time','contract','intern') NULL");

            foreach ($rows as $row) {
                $values = json_decode($row->employment_type, true);
                $first = is_array($values) ? ($values[0] ?? null) : null;
                if ($first) {
                    DB::table('employees')->where('id', $row->id)->update([
                        'employment_type' => $first,
                    ]);
                }
            }
        } else {
            Schema::table('employees', function (Blueprint $table) {
                $table->renameColumn('employment_type', 'employment_type_json');
            });

            Schema::table('employees', function (Blueprint $table) {
                $table->string('employment_type')->nullable()->after('employment_type_json');
            });

            DB::table('employees')->whereNotNull('employment_type_json')->get(['id', 'employment_type_json'])->each(function ($row) {
                $values = json_decode($row->employment_type_json, true);
                DB::table('employees')->where('id', $row->id)->update([
                    'employment_type' => $values[0] ?? null,
                ]);
            });

            Schema::table('employees', function (Blueprint $table) {
                $table->dropColumn('employment_type_json');
            });
        }
    }
};
