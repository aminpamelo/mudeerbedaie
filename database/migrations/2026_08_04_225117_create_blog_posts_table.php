<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blog_posts', function (Blueprint $table) {
            $table->id();

            $table->string('title');
            $table->string('slug')->unique();
            $table->text('excerpt')->nullable();

            // `content` holds the Markdown source the admin actually edits;
            // `content_html` caches the rendered + sanitised HTML so the public
            // page never pays the parse cost on read.
            $table->longText('content')->nullable();
            $table->longText('content_html')->nullable();

            $table->foreignId('featured_image_id')->nullable()->constrained('media')->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('blog_categories')->nullOnDelete();

            // nullOnDelete (not cascade) so removing a staff account never deletes
            // published content; the model exposes a null-safe author accessor.
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();

            // Per-post language tag. The public index filters to the visitor's
            // active locale so BM and EN readers each get a coherent feed.
            $table->string('locale', 5)->default('ms');

            // draft | scheduled | published | archived. Kept as a string rather
            // than an enum so future statuses don't need a column alter (enum
            // changes are painful across MySQL + SQLite).
            $table->string('status', 20)->default('draft');
            $table->timestamp('published_at')->nullable();

            $table->unsignedSmallInteger('reading_time')->default(0);
            $table->unsignedBigInteger('view_count')->default(0);
            $table->boolean('is_featured')->default(false);
            $table->boolean('allow_comments')->default(true);

            // ---- SEO ----
            $table->string('meta_title')->nullable();
            $table->string('meta_description', 500)->nullable();
            $table->string('focus_keyword')->nullable();
            $table->string('canonical_url')->nullable();
            $table->foreignId('og_image_id')->nullable()->constrained('media')->nullOnDelete();
            $table->boolean('noindex')->default(false);

            // Cached output of SeoAnalyzer so the admin list and SEO dashboard can
            // sort/filter on score without re-analysing every post on each render.
            $table->unsignedTinyInteger('seo_score')->default(0);
            $table->json('seo_report')->nullable();
            $table->timestamp('seo_checked_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'published_at'], 'blog_posts_status_published_index');
            $table->index(['locale', 'status', 'published_at'], 'blog_posts_locale_status_pub_index');
            $table->index(['is_featured', 'published_at'], 'blog_posts_featured_published_index');
            $table->index('seo_score', 'blog_posts_seo_score_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blog_posts');
    }
};
