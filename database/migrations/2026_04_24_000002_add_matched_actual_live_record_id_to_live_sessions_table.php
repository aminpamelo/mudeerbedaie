<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('live_sessions', function (Blueprint $table) {
            $table->foreignId('matched_actual_live_record_id')
                ->nullable()
                ->unique()
                ->after('live_host_platform_account_id')
                ->constrained('actual_live_records')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('live_sessions', function (Blueprint $table) {
            $table->dropForeign(['matched_actual_live_record_id']);
            $table->dropUnique(['matched_actual_live_record_id']);
            $table->dropColumn('matched_actual_live_record_id');
        });
    }
};
