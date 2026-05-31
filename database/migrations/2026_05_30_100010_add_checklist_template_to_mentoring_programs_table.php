<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The reusable task checklist seeded onto every mentee enrolled into the
     * program. Stored as JSON on the program; copied into per-mentee rows at
     * enrolment so each mentee's progress is tracked independently.
     */
    public function up(): void
    {
        Schema::table('live_host_mentoring_programs', function (Blueprint $table) {
            if (! Schema::hasColumn('live_host_mentoring_programs', 'checklist_template')) {
                $table->json('checklist_template')->nullable()->after('description');
            }
        });
    }

    public function down(): void
    {
        Schema::table('live_host_mentoring_programs', function (Blueprint $table) {
            if (Schema::hasColumn('live_host_mentoring_programs', 'checklist_template')) {
                $table->dropColumn('checklist_template');
            }
        });
    }
};
