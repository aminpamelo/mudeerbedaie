<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Expand monthly performance into KPI metrics:
     *  - levels gain a monthly sales target (Sales KPI = actual ÷ target).
     *  - the single 0–100 "score" becomes the Attitude KPI; a sales_quantity
     *    column records the raw monthly sales count. Overall is computed, not stored.
     *
     * renameColumn + addColumn are split into separate Schema::table calls so the
     * change is safe on both MySQL and SQLite.
     */
    public function up(): void
    {
        Schema::table('live_host_mentoring_levels', function (Blueprint $table) {
            $table->unsignedInteger('monthly_sales_target')->nullable()->after('min_attendance_pct');
        });

        Schema::table('live_host_mentee_monthly_scores', function (Blueprint $table) {
            $table->renameColumn('score', 'attitude_score');
        });

        Schema::table('live_host_mentee_monthly_scores', function (Blueprint $table) {
            $table->unsignedInteger('sales_quantity')->nullable()->after('attitude_score');
        });
    }

    public function down(): void
    {
        Schema::table('live_host_mentee_monthly_scores', function (Blueprint $table) {
            $table->dropColumn('sales_quantity');
        });

        Schema::table('live_host_mentee_monthly_scores', function (Blueprint $table) {
            $table->renameColumn('attitude_score', 'score');
        });

        Schema::table('live_host_mentoring_levels', function (Blueprint $table) {
            $table->dropColumn('monthly_sales_target');
        });
    }
};
