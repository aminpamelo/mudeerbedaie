<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * When an admin reset the bot's memory of this chat (for re-testing a
     * flow); the bot ignores messages before it.
     */
    public function up(): void
    {
        Schema::table('cekbot_conversations', function (Blueprint $table) {
            $table->timestamp('context_reset_at')->nullable()->after('handed_over_by');
        });
    }

    public function down(): void
    {
        Schema::table('cekbot_conversations', function (Blueprint $table) {
            $table->dropColumn('context_reset_at');
        });
    }
};
