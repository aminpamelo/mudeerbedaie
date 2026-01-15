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
        Schema::table('live_schedules', function (Blueprint $table) {
            $table->foreignId('live_host_id')->nullable()->after('is_active')->constrained('users')->nullOnDelete();
            $table->text('remarks')->nullable()->after('live_host_id');
            $table->foreignId('created_by')->nullable()->after('remarks')->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('live_schedules', function (Blueprint $table) {
            $table->dropForeign(['live_host_id']);
            $table->dropForeign(['created_by']);
            $table->dropColumn(['live_host_id', 'remarks', 'created_by']);
        });
    }
};
