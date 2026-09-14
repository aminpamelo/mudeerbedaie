<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cekbot_flow_packages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cekbot_flow_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cekbot_product_id')->nullable()->constrained('cekbot_products')->nullOnDelete();
            $table->string('label');
            $table->decimal('price', 12, 2)->nullable(); // override; falls back to product price
            $table->string('currency', 8)->default('RM');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['cekbot_flow_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cekbot_flow_packages');
    }
};
