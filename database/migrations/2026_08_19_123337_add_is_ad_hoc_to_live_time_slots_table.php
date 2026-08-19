<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ad-hoc slots hold a one-off custom start/end time for a single session
     * assignment. They are hidden from the perpetual-slot pickers, the calendar
     * scaffolds and the Time Slots management page so a bespoke time never turns
     * into a recurring +Assign row.
     */
    public function up(): void
    {
        Schema::table('live_time_slots', function (Blueprint $table) {
            $table->boolean('is_ad_hoc')->default(false)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('live_time_slots', function (Blueprint $table) {
            $table->dropColumn('is_ad_hoc');
        });
    }
};
