<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meeting_transcripts', function (Blueprint $table) {
            $table->string('operation_name')->nullable()->after('status');
            $table->string('gcs_uri')->nullable()->after('operation_name');
            $table->text('error_message')->nullable()->after('gcs_uri');
            $table->unsignedInteger('poll_attempts')->default(0)->after('error_message');
            $table->datetime('started_at')->nullable()->after('poll_attempts');
        });
    }

    public function down(): void
    {
        Schema::table('meeting_transcripts', function (Blueprint $table) {
            $table->dropColumn(['operation_name', 'gcs_uri', 'error_message', 'poll_attempts', 'started_at']);
        });
    }
};
