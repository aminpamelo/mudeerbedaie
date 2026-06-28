<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('folder_id')->nullable()->constrained('media_folders')->nullOnDelete();
            $table->string('title')->nullable();
            $table->string('alt_text')->nullable();
            $table->string('original_filename');
            $table->string('file_name');
            $table->string('file_path');
            $table->string('disk')->default('public');
            $table->string('mime_type');
            $table->enum('type', ['image', 'video'])->default('image');
            $table->unsignedBigInteger('file_size')->default(0);
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->unsignedInteger('duration')->nullable();
            $table->json('tags')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('uploader_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['type', 'created_at']);
            $table->index('folder_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media');
    }
};
