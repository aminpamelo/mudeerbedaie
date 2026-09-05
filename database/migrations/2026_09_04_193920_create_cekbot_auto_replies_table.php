<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cekbot_auto_replies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cekbot_session_id')->constrained('cekbot_sessions')->cascadeOnDelete();
            $table->string('name');
            $table->string('match_type')->default('contains'); // contains | exact | starts | regex
            $table->json('keywords'); // array of trigger strings
            $table->text('reply_body');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('priority')->default(100); // lower = evaluated first
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['cekbot_session_id', 'is_active', 'priority']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cekbot_auto_replies');
    }
};
