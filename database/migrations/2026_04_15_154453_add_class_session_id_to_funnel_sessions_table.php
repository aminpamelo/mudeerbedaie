<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('funnel_sessions', function (Blueprint $table) {
            $table->foreignId('class_session_id')->nullable()->after('metadata')->constrained('class_sessions')->nullOnDelete();
            $table->index('class_session_id');
        });

        // Backfill from JSON metadata
        DB::table('funnel_sessions')
            ->whereNotNull('metadata')
            ->whereNull('class_session_id')
            ->orderBy('id')
            ->each(function ($row) {
                $metadata = json_decode($row->metadata, true);

                if (! empty($metadata['class_session_id'])) {
                    DB::table('funnel_sessions')
                        ->where('id', $row->id)
                        ->update(['class_session_id' => $metadata['class_session_id']]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('funnel_sessions', function (Blueprint $table) {
            $table->dropForeign(['class_session_id']);
            $table->dropIndex(['class_session_id']);
            $table->dropColumn('class_session_id');
        });
    }
};
