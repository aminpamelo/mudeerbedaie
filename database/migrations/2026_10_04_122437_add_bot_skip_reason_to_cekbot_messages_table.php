<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Why the bot stayed silent on an inbound message (shown in the inbox).
     */
    public function up(): void
    {
        Schema::table('cekbot_messages', function (Blueprint $table) {
            $table->string('bot_skip_reason', 40)->nullable()->after('ack');
        });
    }

    public function down(): void
    {
        Schema::table('cekbot_messages', function (Blueprint $table) {
            $table->dropColumn('bot_skip_reason');
        });
    }
};
