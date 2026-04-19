<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('live_sessions', function (Blueprint $table) {
            $table->foreignId('live_schedule_assignment_id')
                ->nullable()
                ->after('live_schedule_id')
                ->constrained('live_schedule_assignments')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('live_sessions', function (Blueprint $table) {
            $table->dropForeign(['live_schedule_assignment_id']);
            $table->dropColumn('live_schedule_assignment_id');
        });
    }
};
