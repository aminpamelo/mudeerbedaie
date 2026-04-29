<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cms_content_platform_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('content_id')->constrained('contents')->cascadeOnDelete();
            $table->foreignId('platform_id')->constrained('cms_platforms')->cascadeOnDelete();
            $table->string('status')->default('pending'); // pending|posted|skipped
            $table->string('post_url')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->foreignId('assignee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->text('caption_variant')->nullable();           // reserved for v2
            $table->string('external_post_id')->nullable();        // reserved for v2 API
            $table->string('sync_status')->nullable();             // reserved for v2 API
            $table->json('stats')->nullable();
            $table->timestamps();

            $table->unique(['content_id', 'platform_id']);
            $table->index(['status', 'assignee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_content_platform_posts');
    }
};
