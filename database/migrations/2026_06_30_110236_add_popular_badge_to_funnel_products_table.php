<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('funnel_products', function (Blueprint $table) {
            $table->boolean('is_popular')->default(false)->after('compare_at_price');
            $table->string('popular_label')->nullable()->after('is_popular');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('funnel_products', function (Blueprint $table) {
            $table->dropColumn(['is_popular', 'popular_label']);
        });
    }
};
