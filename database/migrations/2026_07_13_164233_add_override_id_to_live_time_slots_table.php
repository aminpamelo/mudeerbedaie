<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tags a live_time_slots row as belonging to a slot override (null = the normal
 * perpetual slot). Override slots carry their own day_of_week + times and are
 * keyed to a creator account via the override, not a shop.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('live_time_slots', function (Blueprint $table) {
            $table->foreignId('override_id')->nullable()->after('platform_account_id')
                ->constrained('live_time_slot_overrides')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('live_time_slots', function (Blueprint $table) {
            $table->dropForeign(['override_id']);
            $table->dropColumn('override_id');
        });
    }
};
