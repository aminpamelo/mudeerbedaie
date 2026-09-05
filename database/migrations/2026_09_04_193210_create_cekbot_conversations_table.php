<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cekbot_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cekbot_session_id')->constrained('cekbot_sessions')->cascadeOnDelete();
            $table->string('chat_id'); // WAHA chat id, e.g. 60123456789@c.us
            $table->string('name')->nullable();
            $table->boolean('is_group')->default(false);
            $table->unsignedInteger('unread_count')->default(0);
            $table->timestamp('last_message_at')->nullable();
            $table->text('last_message_preview')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->unique(['cekbot_session_id', 'chat_id'], 'cekbot_conv_session_chat_unique');
            $table->index('last_message_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cekbot_conversations');
    }
};
