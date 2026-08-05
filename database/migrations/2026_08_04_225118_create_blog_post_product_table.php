<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Products featured inside an article. Powers the "featured in this article"
     * shopping strip that turns editorial content into a storefront entry point.
     */
    public function up(): void
    {
        Schema::create('blog_post_product', function (Blueprint $table) {
            $table->id();
            $table->foreignId('blog_post_id')->constrained('blog_posts')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['blog_post_id', 'product_id'], 'blog_post_product_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blog_post_product');
    }
};
