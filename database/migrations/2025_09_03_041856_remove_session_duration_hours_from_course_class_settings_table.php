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
        // Before removing the hours column, convert any existing hours to minutes
        DB::statement('
            UPDATE course_class_settings 
            SET session_duration_minutes = session_duration_minutes + (session_duration_hours * 60)
            WHERE session_duration_hours > 0
        ');

        Schema::table('course_class_settings', function (Blueprint $table) {
            $table->dropColumn('session_duration_hours');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('course_class_settings', function (Blueprint $table) {
            $table->integer('session_duration_hours')->default(0)->after('sessions_per_month');
        });
    }
};
