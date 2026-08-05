<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Customer testimonials shown on the storefront homepage.
 *
 * Deliberately left empty by the migration — no seeded placeholders. The
 * homepage section only renders once real, admin-entered rows exist, so the
 * store never displays invented reviews.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_testimonials', function (Blueprint $table) {
            $table->id();
            $table->string('author_name');
            $table->string('author_title')->nullable();
            $table->string('author_photo_path')->nullable();
            $table->text('quote');
            $table->unsignedTinyInteger('rating')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'sort_order'], 'store_testimonials_active_order_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_testimonials');
    }
};
