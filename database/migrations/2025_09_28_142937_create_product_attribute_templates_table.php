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
        Schema::create('product_attribute_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('label');
            $table->enum('type', ['text', 'select', 'color', 'number']);
            $table->json('values')->nullable();
            $table->boolean('is_required')->default(false);
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index(['type']);
            $table->index(['is_required']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_attribute_templates');
    }
};
