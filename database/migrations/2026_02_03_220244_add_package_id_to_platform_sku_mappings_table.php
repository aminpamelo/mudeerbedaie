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
        Schema::table('platform_sku_mappings', function (Blueprint $table) {
            $table->foreignId('package_id')->nullable()->after('product_variant_id')
                ->constrained('packages')->nullOnDelete();
        });

        // Make product_id nullable since a mapping can now point to a package instead
        Schema::table('platform_sku_mappings', function (Blueprint $table) {
            $table->unsignedBigInteger('product_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('platform_sku_mappings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('package_id');
        });

        Schema::table('platform_sku_mappings', function (Blueprint $table) {
            $table->unsignedBigInteger('product_id')->nullable(false)->change();
        });
    }
};
