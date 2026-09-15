<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cekbot_flow_packages', function (Blueprint $table) {
            // Direct link to a catalogue product (the shop's real products), so a
            // package can be linked even without a Cekbot knowledge entry, and the
            // created order references the real product.
            $table->foreignId('product_id')->nullable()->after('cekbot_product_id')->constrained('products')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('cekbot_flow_packages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('product_id');
        });
    }
};
