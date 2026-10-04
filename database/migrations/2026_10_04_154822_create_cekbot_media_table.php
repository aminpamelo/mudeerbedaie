<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Shared Cekbot media library: images/videos (e.g. testimonials) the flow
     * AI can send mid-conversation, referenced by a short key.
     */
    public function up(): void
    {
        Schema::create('cekbot_media', function (Blueprint $table) {
            $table->id();
            $table->string('key', 60)->unique();
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->string('type', 10);
            $table->string('path');
            $table->string('mime', 100)->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cekbot_media');
    }
};
