<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_order_items', function (Blueprint $table) {
            // Make product_snapshot nullable for platform orders without mapped products
            $table->json('product_snapshot')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('product_order_items', function (Blueprint $table) {
            // Make product_snapshot non-nullable again
            $table->json('product_snapshot')->nullable(false)->change();
        });
    }
};
