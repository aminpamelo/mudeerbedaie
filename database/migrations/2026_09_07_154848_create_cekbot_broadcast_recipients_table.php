<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cekbot_broadcast_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cekbot_broadcast_id')->constrained('cekbot_broadcasts')->cascadeOnDelete();
            $table->foreignId('cekbot_conversation_id')->constrained('cekbot_conversations')->cascadeOnDelete();
            $table->string('status')->default('pending'); // pending | sent | failed
            $table->text('error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['cekbot_broadcast_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cekbot_broadcast_recipients');
    }
};
