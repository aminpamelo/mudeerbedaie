<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // For SQLite, we need to recreate the column since it doesn't support ALTER COLUMN
        // For MySQL/PostgreSQL, this would be a simple enum modification

        if (DB::getDriverName() === 'sqlite') {
            // SQLite approach: create new column, copy data, drop old, rename new
            Schema::table('class_timetables', function (Blueprint $table) {
                $table->string('recurrence_pattern_new')->default('weekly')->after('weekly_schedule');
            });

            // Copy data from old column to new
            DB::statement('UPDATE class_timetables SET recurrence_pattern_new = recurrence_pattern');

            Schema::table('class_timetables', function (Blueprint $table) {
                $table->dropColumn('recurrence_pattern');
            });

            Schema::table('class_timetables', function (Blueprint $table) {
                $table->renameColumn('recurrence_pattern_new', 'recurrence_pattern');
            });
        } else {
            // MySQL approach
            DB::statement("ALTER TABLE class_timetables MODIFY recurrence_pattern ENUM('weekly', 'bi_weekly', 'monthly') DEFAULT 'weekly'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Change any 'monthly' back to 'weekly' before removing the option
        DB::table('class_timetables')
            ->where('recurrence_pattern', 'monthly')
            ->update(['recurrence_pattern' => 'weekly']);

        if (DB::getDriverName() === 'sqlite') {
            Schema::table('class_timetables', function (Blueprint $table) {
                $table->string('recurrence_pattern_old')->default('weekly')->after('weekly_schedule');
            });

            DB::statement('UPDATE class_timetables SET recurrence_pattern_old = recurrence_pattern');

            Schema::table('class_timetables', function (Blueprint $table) {
                $table->dropColumn('recurrence_pattern');
            });

            Schema::table('class_timetables', function (Blueprint $table) {
                $table->renameColumn('recurrence_pattern_old', 'recurrence_pattern');
            });
        } else {
            DB::statement("ALTER TABLE class_timetables MODIFY recurrence_pattern ENUM('weekly', 'bi_weekly') DEFAULT 'weekly'");
        }
    }
};
