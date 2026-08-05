<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Record a course line on an order so a storefront course purchase can be
     * fulfilled (enrolment created on payment). Mirrors the existing package_id
     * support on order items.
     */
    public function up(): void
    {
        Schema::table('product_order_items', function (Blueprint $table): void {
            $table->foreignId('course_id')->nullable()->after('package_id')->constrained('courses')->nullOnDelete();
            $table->index('course_id');
        });
    }

    public function down(): void
    {
        Schema::table('product_order_items', function (Blueprint $table): void {
            $table->dropIndex(['course_id']);
            $table->dropForeign(['course_id']);
            $table->dropColumn('course_id');
        });
    }
};
