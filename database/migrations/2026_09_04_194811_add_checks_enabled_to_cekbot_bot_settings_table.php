<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cekbot_bot_settings', function (Blueprint $table) {
            $table->boolean('checks_enabled')->default(false)->after('reply_to_groups');
        });
    }

    public function down(): void
    {
        Schema::table('cekbot_bot_settings', function (Blueprint $table) {
            $table->dropColumn('checks_enabled');
        });
    }
};
