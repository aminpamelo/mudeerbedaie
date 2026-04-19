<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add the two new reason columns first — these are safe on both drivers.
        Schema::table('live_sessions', function (Blueprint $table) {
            $table->string('missed_reason_code', 32)->nullable()->after('remarks');
            $table->text('missed_reason_note')->nullable()->after('missed_reason_code');
        });

        // Widen the status enum to include 'missed'. MySQL needs an explicit
        // ALTER with the new enum list; SQLite doesn't enforce enums so the
        // values are already accepted and no schema change is required.
        if (DB::getDriverName() === 'mysql') {
            DB::statement(
                "ALTER TABLE live_sessions MODIFY status ENUM('scheduled','live','ended','cancelled','missed') NOT NULL DEFAULT 'scheduled'"
            );
        }
    }

    public function down(): void
    {
        // Remap any 'missed' rows back to 'cancelled' so narrowing the enum
        // doesn't fail. Then drop the new columns.
        if (Schema::hasTable('live_sessions')) {
            DB::table('live_sessions')->where('status', 'missed')->update(['status' => 'cancelled']);
        }

        if (DB::getDriverName() === 'mysql') {
            DB::statement(
                "ALTER TABLE live_sessions MODIFY status ENUM('scheduled','live','ended','cancelled') NOT NULL DEFAULT 'scheduled'"
            );
        }

        Schema::table('live_sessions', function (Blueprint $table) {
            $table->dropColumn(['missed_reason_code', 'missed_reason_note']);
        });
    }
};
