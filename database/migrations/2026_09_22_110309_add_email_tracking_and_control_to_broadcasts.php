<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add open/click tracking to delivery logs and widen the broadcast status
     * column so campaigns can be paused/cancelled. The status column is an enum
     * originally, so it is converted to a plain string to support the new
     * values on both MySQL (production) and SQLite (development/tests).
     */
    public function up(): void
    {
        Schema::table('broadcast_logs', function (Blueprint $table) {
            $table->timestamp('opened_at')->nullable()->after('sent_at');
            $table->timestamp('clicked_at')->nullable()->after('opened_at');
            $table->unsignedInteger('open_count')->default(0)->after('clicked_at');
            $table->unsignedInteger('click_count')->default(0)->after('open_count');
            $table->string('tracking_token', 40)->nullable()->after('click_count');
        });

        $this->widenBroadcastStatus();
    }

    public function down(): void
    {
        Schema::table('broadcast_logs', function (Blueprint $table) {
            $table->dropColumn(['opened_at', 'clicked_at', 'open_count', 'click_count', 'tracking_token']);
        });

        if (DB::getDriverName() === 'mysql') {
            DB::table('broadcasts')->whereIn('status', ['paused', 'cancelled'])->update(['status' => 'sent']);
            DB::statement("ALTER TABLE broadcasts MODIFY status ENUM('draft','scheduled','sending','sent','failed') NOT NULL DEFAULT 'draft'");
        }
    }

    private function widenBroadcastStatus(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE broadcasts MODIFY status VARCHAR(30) NOT NULL DEFAULT 'draft'");

            return;
        }

        // SQLite: enum() creates a CHECK constraint, so rebuild the column as a
        // plain string by copying through a temporary column.
        Schema::table('broadcasts', function (Blueprint $table) {
            $table->string('status_tmp', 30)->default('draft');
        });

        DB::table('broadcasts')->update(['status_tmp' => DB::raw('status')]);

        Schema::table('broadcasts', function (Blueprint $table) {
            $table->dropColumn('status');
        });

        Schema::table('broadcasts', function (Blueprint $table) {
            $table->renameColumn('status_tmp', 'status');
        });
    }
};
