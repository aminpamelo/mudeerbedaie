<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cekbot_bot_settings', function (Blueprint $table) {
            $table->boolean('test_mode')->default(false)->after('bot_enabled');
            $table->json('test_numbers')->nullable()->after('test_mode');
        });
    }

    public function down(): void
    {
        Schema::table('cekbot_bot_settings', function (Blueprint $table) {
            $table->dropColumn(['test_mode', 'test_numbers']);
        });
    }
};
