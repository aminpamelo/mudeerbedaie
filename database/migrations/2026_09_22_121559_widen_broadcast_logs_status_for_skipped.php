<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * broadcast_logs.status is an enum (pending/sent/failed); widen it to a
     * plain string so a "skipped" status (undeliverable placeholder emails)
     * can be recorded. Dual-driver for MySQL (production) + SQLite (tests).
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE broadcast_logs MODIFY status VARCHAR(20) NOT NULL DEFAULT 'pending'");

            return;
        }

        // SQLite: rebuild the column to drop the enum CHECK constraint. The
        // (broadcast_id, status) index must be dropped and recreated around it.
        Schema::table('broadcast_logs', function (Blueprint $table) {
            $table->dropIndex(['broadcast_id', 'status']);
        });

        Schema::table('broadcast_logs', function (Blueprint $table) {
            $table->string('status_tmp', 20)->default('pending');
        });

        DB::table('broadcast_logs')->update(['status_tmp' => DB::raw('status')]);

        Schema::table('broadcast_logs', function (Blueprint $table) {
            $table->dropColumn('status');
        });

        Schema::table('broadcast_logs', function (Blueprint $table) {
            $table->renameColumn('status_tmp', 'status');
        });

        Schema::table('broadcast_logs', function (Blueprint $table) {
            $table->index(['broadcast_id', 'status']);
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::table('broadcast_logs')->where('status', 'skipped')->update(['status' => 'failed']);
            DB::statement("ALTER TABLE broadcast_logs MODIFY status ENUM('pending','sent','failed') NOT NULL DEFAULT 'pending'");
        }
    }
};
