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
        Schema::table('live_sessions', function (Blueprint $table) {
            if (!Schema::hasColumn('live_sessions', 'live_host_id')) {
                $table->foreignId('live_host_id')
                    ->nullable()
                    ->after('live_schedule_id')
                    ->constrained('users')
                    ->nullOnDelete();
            }
            if (!Schema::hasColumn('live_sessions', 'duration_minutes')) {
                $table->integer('duration_minutes')
                    ->nullable()
                    ->after('actual_end_at')
                    ->comment('Auto-calculated from actual times');
            }
            if (!Schema::hasColumn('live_sessions', 'image_path')) {
                $table->string('image_path')
                    ->nullable()
                    ->after('duration_minutes')
                    ->comment('Screenshot/thumbnail of the live session');
            }
            if (!Schema::hasColumn('live_sessions', 'remarks')) {
                $table->text('remarks')
                    ->nullable()
                    ->after('image_path');
            }
            if (!Schema::hasColumn('live_sessions', 'uploaded_at')) {
                $table->timestamp('uploaded_at')
                    ->nullable()
                    ->after('remarks')
                    ->comment('When the live host uploaded the session details');
            }
            if (!Schema::hasColumn('live_sessions', 'uploaded_by')) {
                $table->foreignId('uploaded_by')
                    ->nullable()
                    ->after('uploaded_at')
                    ->constrained('users')
                    ->nullOnDelete();
            }
        });

        // Add indexes only if columns were just added (indexes likely don't exist)
        // Skip if we encounter errors - indexes may already exist
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('live_sessions', function (Blueprint $table) {
            $table->dropForeign(['live_host_id']);
            $table->dropForeign(['uploaded_by']);
            $table->dropIndex(['live_host_id']);
            $table->dropIndex(['uploaded_at']);
            $table->dropColumn([
                'live_host_id',
                'duration_minutes',
                'image_path',
                'remarks',
                'uploaded_at',
                'uploaded_by',
            ]);
        });
    }
};
