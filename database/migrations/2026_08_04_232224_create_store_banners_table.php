<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Campaign slides for the storefront hero.
 *
 * The hero is a hybrid: slide 1 is the built-in brand slide (always present, so
 * the homepage can never render an empty carousel) and these rows are the
 * campaign slides an admin stacks on top — Raya promos, a new book launch, and
 * so on. With no active rows the hero degrades to a single static brand slide.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_banners', function (Blueprint $table) {
            $table->id();
            $table->string('eyebrow')->nullable();
            $table->string('title');
            $table->text('subtitle')->nullable();
            $table->string('image_path')->nullable();
            $table->string('cta_text')->nullable();
            $table->string('cta_url')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            // Scheduling window: a campaign can be queued ahead of time and
            // expires on its own without anyone remembering to switch it off.
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'sort_order'], 'store_banners_active_order_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_banners');
    }
};
