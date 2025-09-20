<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('enrollments', function (Blueprint $table) {
            $table->string('academic_status')->default('active')->after('status');
        });

        // Migrate existing data from status to academic_status
        DB::table('enrollments')->update([
            'academic_status' => DB::raw("
                CASE
                    WHEN status IN ('enrolled', 'active', 'pending') THEN 'active'
                    WHEN status = 'completed' THEN 'completed'
                    WHEN status = 'dropped' THEN 'withdrawn'
                    WHEN status = 'suspended' THEN 'suspended'
                    ELSE 'active'
                END
            "),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('enrollments', function (Blueprint $table) {
            $table->dropColumn('academic_status');
        });
    }
};
