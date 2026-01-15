<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('live_sessions', function (Blueprint $table) {
            $table->foreignId('live_host_id')
                ->nullable()
                ->after('live_schedule_id')
                ->constrained('users')
                ->nullOnDelete();
            $table->integer('duration_minutes')
                ->nullable()
                ->after('actual_end_at')
                ->comment('Auto-calculated from actual times');
            $table->string('image_path')
                ->nullable()
                ->after('duration_minutes')
                ->comment('Screenshot/thumbnail of the live session');
            $table->text('remarks')
                ->nullable()
                ->after('image_path');
            $table->timestamp('uploaded_at')
                ->nullable()
                ->after('remarks')
                ->comment('When the live host uploaded the session details');
            $table->foreignId('uploaded_by')
                ->nullable()
                ->after('uploaded_at')
                ->constrained('users')
                ->nullOnDelete();

            // Add indexes for common queries
            $table->index('live_host_id');
            $table->index('uploaded_at');
        });

        // Update status enum - need to recreate with new values
        // For SQLite compatibility, we'll add columns and migrate data if needed
        // The enum modification will be handled by the model casting
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
