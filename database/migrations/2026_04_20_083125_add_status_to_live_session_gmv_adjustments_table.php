<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add a `status` column to live_session_gmv_adjustments so the
     * OrderRefundReconciler can propose adjustments that do NOT yet affect
     * the session's cached gmv_adjustment aggregate until a PIC approves.
     *
     * Existing rows default to 'approved' so Task 15's manual PIC flow keeps
     * working without change. Also makes `adjusted_by` nullable so
     * system-generated (auto-proposed) rows can be persisted without a user.
     */
    public function up(): void
    {
        Schema::table('live_session_gmv_adjustments', function (Blueprint $table) {
            $table->string('status')->default('approved')->after('reason');
            $table->index('status');
        });

        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE live_session_gmv_adjustments MODIFY adjusted_by BIGINT UNSIGNED NULL');
        } else {
            Schema::table('live_session_gmv_adjustments', function (Blueprint $table) {
                $table->unsignedBigInteger('adjusted_by')->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        Schema::table('live_session_gmv_adjustments', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropColumn('status');
        });

        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE live_session_gmv_adjustments MODIFY adjusted_by BIGINT UNSIGNED NOT NULL');
        } else {
            Schema::table('live_session_gmv_adjustments', function (Blueprint $table) {
                $table->unsignedBigInteger('adjusted_by')->nullable(false)->change();
            });
        }
    }
};
