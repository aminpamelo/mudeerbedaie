<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_order_items', function (Blueprint $table) {
            // Drop the foreign key first
            $table->dropForeign(['product_id']);

            // Make product_id nullable
            $table->unsignedBigInteger('product_id')->nullable()->change();

            // Re-add the foreign key with the nullable constraint
            $table->foreign('product_id')
                ->references('id')
                ->on('products')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('product_order_items', function (Blueprint $table) {
            // Drop the foreign key
            $table->dropForeign(['product_id']);

            // Make product_id non-nullable again
            $table->unsignedBigInteger('product_id')->nullable(false)->change();

            // Re-add the foreign key
            $table->foreign('product_id')
                ->references('id')
                ->on('products')
                ->onDelete('cascade');
        });
    }
};
