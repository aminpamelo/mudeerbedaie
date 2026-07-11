<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-mentee, per-month KPI targets: how many videos and how many live
     * sessions the host is expected to hit that month. Both nullable — a null
     * target means "no target set" and the KPI simply doesn't count toward the
     * month's Overall score. Adding nullable columns works on MySQL + SQLite
     * with a plain add, so no driver branching is needed here.
     */
    public function up(): void
    {
        Schema::table('live_host_mentee_monthly_scores', function (Blueprint $table) {
            $table->unsignedSmallInteger('video_target')->nullable()->after('attitude_score');
            $table->unsignedSmallInteger('live_target')->nullable()->after('video_target');
        });
    }

    public function down(): void
    {
        Schema::table('live_host_mentee_monthly_scores', function (Blueprint $table) {
            $table->dropColumn(['video_target', 'live_target']);
        });
    }
};
