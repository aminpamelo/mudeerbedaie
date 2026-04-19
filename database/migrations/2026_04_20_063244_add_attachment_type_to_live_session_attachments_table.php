<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds a nullable `attachment_type` string so hosts can flag specific
     * uploads (e.g. a TikTok Shop backend screenshot used for GMV
     * verification). Existing rows stay NULL and are treated as generic.
     */
    public function up(): void
    {
        Schema::table('live_session_attachments', function (Blueprint $table) {
            $table->string('attachment_type', 50)->nullable()->after('file_size');
            $table->index('attachment_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('live_session_attachments', function (Blueprint $table) {
            $table->dropIndex(['attachment_type']);
            $table->dropColumn('attachment_type');
        });
    }
};
