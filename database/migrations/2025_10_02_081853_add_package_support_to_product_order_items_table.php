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
        Schema::table('product_order_items', function (Blueprint $table) {
            // Add polymorphic columns for supporting both products and packages
            $table->string('itemable_type')->nullable()->after('order_id'); // Product or Package
            $table->unsignedBigInteger('itemable_id')->nullable()->after('itemable_type');

            // Package-specific fields
            $table->foreignId('package_id')->nullable()->after('itemable_id')->constrained('packages')->onDelete('cascade');
            $table->json('package_snapshot')->nullable()->after('product_snapshot'); // Package details snapshot
            $table->json('package_items_snapshot')->nullable()->after('package_snapshot'); // Package items at order time

            // Make product_id nullable since we might have package items
            $table->foreignId('product_id')->nullable()->change();
            $table->string('sku')->nullable()->change();

            // Add index for polymorphic relationship
            $table->index(['itemable_type', 'itemable_id']);
            $table->index('package_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_order_items', function (Blueprint $table) {
            $table->dropIndex(['itemable_type', 'itemable_id']);
            $table->dropIndex(['package_id']);
            $table->dropForeign(['package_id']);
            $table->dropColumn([
                'itemable_type',
                'itemable_id',
                'package_id',
                'package_snapshot',
                'package_items_snapshot',
            ]);
        });
    }
};
