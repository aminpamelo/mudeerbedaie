<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meeting_transcripts', function (Blueprint $table) {
            $table->renameColumn('gcs_uri', 'provider_reference');
        });

        Schema::table('meeting_transcripts', function (Blueprint $table) {
            $table->string('provider', 32)->default('assemblyai')->after('language');
        });
    }

    public function down(): void
    {
        Schema::table('meeting_transcripts', function (Blueprint $table) {
            $table->dropColumn('provider');
        });

        Schema::table('meeting_transcripts', function (Blueprint $table) {
            $table->renameColumn('provider_reference', 'gcs_uri');
        });
    }
};
