<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Let a cart line reference a Course (a one-time storefront course purchase),
     * alongside the existing product and package support.
     */
    public function up(): void
    {
        Schema::table('product_cart_items', function (Blueprint $table): void {
            $table->foreignId('course_id')->nullable()->after('package_id')->constrained('courses')->nullOnDelete();
            $table->index('course_id');
        });
    }

    public function down(): void
    {
        Schema::table('product_cart_items', function (Blueprint $table): void {
            $table->dropIndex(['course_id']);
            $table->dropForeign(['course_id']);
            $table->dropColumn('course_id');
        });
    }
};
