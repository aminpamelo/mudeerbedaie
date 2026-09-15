<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cekbot_flows', function (Blueprint $table) {
            // AI-driven conversation (natural language + intent + confirm) vs the
            // deterministic numbered-menu fallback. On by default.
            $table->boolean('use_ai')->default(true)->after('is_active');
            $table->text('ai_instructions')->nullable()->after('confirmation_message');
        });
    }

    public function down(): void
    {
        Schema::table('cekbot_flows', function (Blueprint $table) {
            $table->dropColumn(['use_ai', 'ai_instructions']);
        });
    }
};
