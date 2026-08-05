<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Storefront-facing fields for courses so they can be listed and sold on the
     * public shop: a shareable slug, marketing copy, a thumbnail and an
     * admin-controlled visibility toggle.
     */
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table): void {
            $table->string('slug')->nullable()->unique()->after('name');
            $table->text('short_description')->nullable()->after('description');
            $table->string('thumbnail_path')->nullable()->after('short_description');
            $table->boolean('show_on_storefront')->default(false)->index()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table): void {
            $table->dropColumn(['slug', 'short_description', 'thumbnail_path', 'show_on_storefront']);
        });
    }
};
