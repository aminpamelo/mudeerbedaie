<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cekbot_bot_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cekbot_session_id')->unique()->constrained('cekbot_sessions')->cascadeOnDelete();
            $table->boolean('bot_enabled')->default(false);
            $table->boolean('reply_to_groups')->default(false);
            $table->text('welcome_message')->nullable();
            $table->text('default_reply')->nullable();
            // Fasa 4 — AI fallback
            $table->boolean('ai_enabled')->default(false);
            $table->text('ai_system_prompt')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cekbot_bot_settings');
    }
};
