<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Current-affairs headlines captured from news feeds, scored by AI for how
     * well they suit an Islamic commentary article, plus the AI-generated
     * article metadata (hadith to verify, source issue) on blog posts.
     */
    public function up(): void
    {
        Schema::create('blog_issues', function (Blueprint $table) {
            $table->id();
            $table->string('title', 500);
            $table->text('summary')->nullable();
            $table->string('source', 120);
            $table->string('url', 1000);
            $table->string('url_hash', 64)->unique();
            $table->timestamp('published_at')->nullable();
            $table->unsignedTinyInteger('relevance')->nullable();
            $table->text('angle')->nullable();
            $table->string('status', 20)->default('new')->index();
            $table->text('error')->nullable();
            $table->foreignId('blog_post_id')->nullable()->constrained('blog_posts')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'relevance'], 'blog_issues_status_relevance_index');
        });

        Schema::table('blog_posts', function (Blueprint $table) {
            $table->json('ai_meta')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('blog_posts', function (Blueprint $table) {
            $table->dropColumn('ai_meta');
        });

        Schema::dropIfExists('blog_issues');
    }
};
