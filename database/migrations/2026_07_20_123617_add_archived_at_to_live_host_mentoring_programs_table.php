<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Archiving retires a program (reversibly) — it drops out of the desk list
     * and its mentees' performance is hidden in the Pocket app — without
     * touching any data. Orthogonal to the lifecycle `status`. Works on both
     * MySQL and SQLite (plain nullable timestamp).
     */
    public function up(): void
    {
        Schema::table('live_host_mentoring_programs', function (Blueprint $table): void {
            if (! Schema::hasColumn('live_host_mentoring_programs', 'archived_at')) {
                $table->timestamp('archived_at')->nullable()->after('ends_at');
                $table->index('archived_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('live_host_mentoring_programs', function (Blueprint $table): void {
            if (Schema::hasColumn('live_host_mentoring_programs', 'archived_at')) {
                $table->dropIndex(['archived_at']);
                $table->dropColumn('archived_at');
            }
        });
    }
};
