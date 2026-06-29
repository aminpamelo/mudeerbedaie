<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_sales_pages', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->string('slug')->unique();

            // Generation brief
            $table->text('prompt')->nullable();
            $table->string('target_audience')->nullable();
            $table->string('tone')->nullable();
            $table->string('model')->nullable();

            // Working draft (raw full-page HTML)
            $table->longText('html')->nullable();
            $table->longText('custom_css')->nullable();
            $table->longText('custom_js')->nullable();

            // SEO / social
            $table->string('meta_title')->nullable();
            $table->string('meta_description', 500)->nullable();
            $table->foreignId('og_image_media_id')->nullable()->constrained('media')->nullOnDelete();

            // Generation lifecycle
            $table->enum('generation_status', ['idle', 'processing', 'failed'])->default('idle');
            $table->text('generation_error')->nullable();

            // Publishing
            $table->enum('status', ['draft', 'published', 'archived'])->default('draft');
            $table->unsignedBigInteger('published_version_id')->nullable();
            $table->timestamp('published_at')->nullable();

            // Optional bridge to the funnel engine (checkout / custom domains)
            $table->unsignedBigInteger('funnel_id')->nullable();
            $table->unsignedBigInteger('funnel_step_id')->nullable();

            $table->softDeletes();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index('generation_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_sales_pages');
    }
};
