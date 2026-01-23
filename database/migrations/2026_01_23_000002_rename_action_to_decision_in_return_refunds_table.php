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
        Schema::table('return_refunds', function (Blueprint $table) {
            $table->renameColumn('action', 'decision');
            $table->renameColumn('action_reason', 'decision_reason');
            $table->renameColumn('action_date', 'decision_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('return_refunds', function (Blueprint $table) {
            $table->renameColumn('decision', 'action');
            $table->renameColumn('decision_reason', 'action_reason');
            $table->renameColumn('decision_date', 'action_date');
        });
    }
};
