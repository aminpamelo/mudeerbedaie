<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Audit trail for manual HR adjustments to an overtime request's hours.
     * Additive nullable columns — safe on both MySQL and SQLite.
     */
    public function up(): void
    {
        Schema::table('overtime_requests', function (Blueprint $table) {
            $table->foreignId('adjusted_by')->nullable()->after('approved_at')->constrained('users')->nullOnDelete();
            $table->datetime('adjusted_at')->nullable()->after('adjusted_by');
            $table->text('adjustment_reason')->nullable()->after('adjusted_at');
        });
    }

    public function down(): void
    {
        Schema::table('overtime_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('adjusted_by');
            $table->dropColumn(['adjusted_at', 'adjustment_reason']);
        });
    }
};
