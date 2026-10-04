<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cekbot keys onto the shared Media Library: each row names a library
     * image/video (e.g. a testimonial) with a short key the flow AI sends by.
     */
    public function up(): void
    {
        Schema::create('cekbot_media', function (Blueprint $table) {
            $table->id();
            $table->string('key', 60)->unique();
            $table->foreignId('media_id')->constrained('media')->cascadeOnDelete();
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cekbot_media');
    }
};
