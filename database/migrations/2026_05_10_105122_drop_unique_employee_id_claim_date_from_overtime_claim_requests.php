<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Drop the (employee_id, claim_date) unique constraint.
     *
     * The DB-level unique blocks resubmission after a cancellation or
     * rejection. Uniqueness is now enforced in application code, scoped
     * to active statuses only (pending, approved).
     */
    public function up(): void
    {
        Schema::table('overtime_claim_requests', function (Blueprint $table) {
            $table->dropUnique(['employee_id', 'claim_date']);
        });
    }

    public function down(): void
    {
        Schema::table('overtime_claim_requests', function (Blueprint $table) {
            $table->unique(['employee_id', 'claim_date']);
        });
    }
};
