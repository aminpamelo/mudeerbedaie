<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_sales_page_media', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ai_sales_page_id')->constrained('ai_sales_pages')->cascadeOnDelete();
            $table->foreignId('media_id')->constrained('media')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['ai_sales_page_id', 'media_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_sales_page_media');
    }
};
