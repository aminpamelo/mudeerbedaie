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
        Schema::create('product_attributes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->string('attribute_name'); // e.g., "Brand", "Material", "Warranty"
            $table->text('attribute_value'); // e.g., "Nike", "Cotton", "2 years"
            $table->enum('attribute_type', ['text', 'number', 'boolean', 'date'])->default('text');
            $table->boolean('is_filterable')->default(false); // For product filtering
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['product_id', 'attribute_name']);
            $table->index(['attribute_name', 'is_filterable']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_attributes');
    }
};
