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
        // Check if column exists before adding (idempotent migration)
        if (! Schema::hasColumn('classes', 'auto_schedule_notifications')) {
            Schema::table('classes', function (Blueprint $table) {
                $table->boolean('auto_schedule_notifications')->default(false)->after('notes');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('classes', 'auto_schedule_notifications')) {
            Schema::table('classes', function (Blueprint $table) {
                $table->dropColumn('auto_schedule_notifications');
            });
        }
    }
};
