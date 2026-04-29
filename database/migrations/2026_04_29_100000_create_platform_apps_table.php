<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_apps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('platform_id')->constrained()->onDelete('cascade');
            $table->string('slug');
            $table->string('name');
            $table->string('category');
            $table->string('app_key');
            $table->text('encrypted_app_secret');
            $table->string('redirect_uri')->nullable();
            $table->json('scopes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['platform_id', 'slug'], 'platform_apps_platform_slug_unique');
            $table->unique(['platform_id', 'category'], 'platform_apps_platform_category_unique');
            $table->index(['platform_id', 'is_active'], 'platform_apps_active_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_apps');
    }
};
