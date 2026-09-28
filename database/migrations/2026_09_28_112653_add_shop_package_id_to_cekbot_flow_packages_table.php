<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cekbot_flow_packages', function (Blueprint $table) {
            // Link to a shop catalogue package (bundle), so the created order
            // line references the real package — alongside the product link.
            $table->foreignId('shop_package_id')->nullable()->after('product_id')->constrained('packages')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('cekbot_flow_packages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('shop_package_id');
        });
    }
};
