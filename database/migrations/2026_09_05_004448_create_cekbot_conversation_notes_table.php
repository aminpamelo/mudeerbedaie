<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cekbot_conversation_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cekbot_conversation_id')->constrained('cekbot_conversations')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('body');
            $table->timestamps();

            $table->index('cekbot_conversation_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cekbot_conversation_notes');
    }
};
