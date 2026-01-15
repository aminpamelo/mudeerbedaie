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
        Schema::table('live_time_slots', function (Blueprint $table) {
            $table->foreignId('platform_account_id')
                ->nullable()
                ->after('id')
                ->constrained()
                ->onDelete('cascade');
            $table->integer('day_of_week')
                ->nullable()
                ->after('platform_account_id')
                ->comment('0=Sunday, 1=Monday, ..., 6=Saturday. Null means applies to all days.');
            $table->foreignId('created_by')
                ->nullable()
                ->after('sort_order')
                ->constrained('users')
                ->nullOnDelete();
            $table->enum('status', ['active', 'inactive', 'draft'])
                ->default('active')
                ->after('created_by');

            // Index for common queries
            $table->index(['platform_account_id', 'day_of_week', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('live_time_slots', function (Blueprint $table) {
            $table->dropForeign(['platform_account_id']);
            $table->dropForeign(['created_by']);
            $table->dropIndex(['platform_account_id', 'day_of_week', 'is_active']);
            $table->dropColumn(['platform_account_id', 'day_of_week', 'created_by', 'status']);
        });
    }
};
