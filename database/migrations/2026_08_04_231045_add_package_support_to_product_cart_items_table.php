<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Let a cart line reference a Package as well as a Product — mirroring the
     * package support already present on product_order_items so a package can be
     * added to the storefront cart and carried through to checkout.
     */
    public function up(): void
    {
        Schema::table('product_cart_items', function (Blueprint $table) {
            $table->string('itemable_type')->nullable()->after('cart_id');
            $table->unsignedBigInteger('itemable_id')->nullable()->after('itemable_type');

            $table->foreignId('package_id')->nullable()->after('product_variant_id')->constrained('packages')->onDelete('cascade');
            $table->json('package_snapshot')->nullable()->after('product_snapshot');

            // A cart line is now either a product or a package.
            $table->foreignId('product_id')->nullable()->change();

            $table->index(['itemable_type', 'itemable_id']);
            $table->index('package_id');
        });
    }

    public function down(): void
    {
        Schema::table('product_cart_items', function (Blueprint $table) {
            $table->dropIndex(['itemable_type', 'itemable_id']);
            $table->dropIndex(['package_id']);
            $table->dropForeign(['package_id']);
            $table->dropColumn([
                'itemable_type',
                'itemable_id',
                'package_id',
                'package_snapshot',
            ]);
        });
    }
};
