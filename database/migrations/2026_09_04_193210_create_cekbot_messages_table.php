<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cekbot_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cekbot_conversation_id')->constrained('cekbot_conversations')->cascadeOnDelete();
            $table->foreignId('cekbot_session_id')->constrained('cekbot_sessions')->cascadeOnDelete();
            $table->string('waha_message_id')->nullable();
            $table->string('direction'); // in | out
            $table->boolean('from_me')->default(false);
            $table->string('type')->default('text'); // text | image | video | document | audio | ...
            $table->text('body')->nullable();
            $table->string('media_url')->nullable();
            $table->string('media_mime')->nullable();
            $table->string('ack')->nullable(); // WAHA ack/status label
            $table->json('payload')->nullable();
            $table->foreignId('sent_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('sent_at')->nullable(); // message timestamp from WAHA
            $table->timestamps();

            $table->index(['cekbot_conversation_id', 'id']);
            $table->index('waha_message_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cekbot_messages');
    }
};
