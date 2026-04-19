<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Task 23: session slots (live_schedule_assignments) must carry the
 * creator-identity pivot id so materialised LiveSessions can inherit it.
 * Keeping it nullable lets existing rows survive the migration; the Store
 * FormRequest enforces required-on-create, the Update request keeps it
 * nullable so legacy rows can still be edited.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('live_schedule_assignments', function (Blueprint $table) {
            $table->foreignId('live_host_platform_account_id')->nullable()
                ->after('platform_account_id')
                ->constrained('live_host_platform_account', 'id')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('live_schedule_assignments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('live_host_platform_account_id');
        });
    }
};
