<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 1: the creator Account/Nickname becomes the governing reference for
 * the timetable. Both the schedule slot and the materialised LiveSession gain
 * a nullable live_account_id FK. Nullable for now so existing rows survive;
 * the backfill command populates it, after which the FormRequest layer (and a
 * later migration) tighten it toward required.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('live_schedule_assignments')
            && ! Schema::hasColumn('live_schedule_assignments', 'live_account_id')) {
            Schema::table('live_schedule_assignments', function (Blueprint $table) {
                $table->foreignId('live_account_id')->nullable()
                    ->after('live_host_platform_account_id')
                    ->constrained('live_accounts', 'id')
                    ->nullOnDelete();
            });
        }

        if (Schema::hasTable('live_sessions')
            && ! Schema::hasColumn('live_sessions', 'live_account_id')) {
            Schema::table('live_sessions', function (Blueprint $table) {
                $table->foreignId('live_account_id')->nullable()
                    ->after('live_host_platform_account_id')
                    ->constrained('live_accounts', 'id')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('live_schedule_assignments', 'live_account_id')) {
            Schema::table('live_schedule_assignments', function (Blueprint $table) {
                $table->dropConstrainedForeignId('live_account_id');
            });
        }
        if (Schema::hasColumn('live_sessions', 'live_account_id')) {
            Schema::table('live_sessions', function (Blueprint $table) {
                $table->dropConstrainedForeignId('live_account_id');
            });
        }
    }
};
