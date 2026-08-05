<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blog_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();

            // Drives the storefront category pill / hero accent. Hex, defaulting to
            // the storefront violet from the existing store-grad identity.
            $table->string('color', 20)->default('#7c3aed');
            $table->string('icon', 60)->nullable();

            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);

            $table->string('meta_title')->nullable();
            $table->string('meta_description', 500)->nullable();

            $table->timestamps();

            $table->index(['is_active', 'sort_order'], 'blog_categories_active_order_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blog_categories');
    }
};
