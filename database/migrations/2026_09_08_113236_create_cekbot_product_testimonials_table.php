<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cekbot_product_testimonials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cekbot_product_id')->constrained()->cascadeOnDelete();
            $table->string('author')->nullable();
            $table->text('text')->nullable();
            $table->string('image')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cekbot_product_testimonials');
    }
};
