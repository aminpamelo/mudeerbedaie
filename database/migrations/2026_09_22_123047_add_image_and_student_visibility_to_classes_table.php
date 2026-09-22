<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adds a per-class cover image and a student-portal visibility flag.
     *
     * `image_path` stores a public-disk path (same convention as
     * courses.thumbnail_path). `is_visible_to_students` lets admins hide a
     * class from the enrolled-student portal (/my) without deleting it; it is
     * distinct from `show_on_storefront`, which governs the public shop.
     */
    public function up(): void
    {
        Schema::table('classes', function (Blueprint $table) {
            $table->string('image_path')->nullable()->after('description');
            $table->boolean('is_visible_to_students')->default(true)->after('show_on_storefront');
            $table->index('is_visible_to_students', 'classes_visible_to_students_index');
        });
    }

    public function down(): void
    {
        Schema::table('classes', function (Blueprint $table) {
            $table->dropIndex('classes_visible_to_students_index');
            $table->dropColumn(['image_path', 'is_visible_to_students']);
        });
    }
};
