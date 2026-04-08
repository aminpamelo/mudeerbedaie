<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $tables = [
            'overtime_requests',
            'overtime_claim_requests',
            'leave_requests',
            'claim_requests',
            'office_exit_permissions',
        ];

        foreach ($tables as $tableName) {
            if (Schema::hasTable($tableName) && ! Schema::hasColumn($tableName, 'current_approval_tier')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->unsignedInteger('current_approval_tier')->default(1)->after('status');
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tables = [
            'overtime_requests',
            'overtime_claim_requests',
            'leave_requests',
            'claim_requests',
            'office_exit_permissions',
        ];

        foreach ($tables as $tableName) {
            if (Schema::hasTable($tableName) && Schema::hasColumn($tableName, 'current_approval_tier')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->dropColumn('current_approval_tier');
                });
            }
        }
    }
};
