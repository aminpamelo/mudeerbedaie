<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cekbot_bot_settings', function (Blueprint $table) {
            $table->json('business_hours')->nullable()->after('checks_enabled');
            $table->text('away_message')->nullable()->after('default_reply');
        });
    }

    public function down(): void
    {
        Schema::table('cekbot_bot_settings', function (Blueprint $table) {
            $table->dropColumn(['business_hours', 'away_message']);
        });
    }
};
