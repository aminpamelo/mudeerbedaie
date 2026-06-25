<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Distinguish checklist tasks copied from the program template ('template')
     * from tasks a mentor keys in for one specific mentee ('custom'), so the
     * mentee checklist can present a dedicated "Individual tasks" section.
     */
    public function up(): void
    {
        Schema::table('live_host_mentee_checklist_items', function (Blueprint $table) {
            $table->string('source', 20)->default('template')->after('mentee_id');
        });
    }

    public function down(): void
    {
        Schema::table('live_host_mentee_checklist_items', function (Blueprint $table) {
            $table->dropColumn('source');
        });
    }
};
