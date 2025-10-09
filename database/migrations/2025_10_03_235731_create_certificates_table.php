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
        Schema::create('certificates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('size', ['letter', 'a4'])->default('a4');
            $table->enum('orientation', ['portrait', 'landscape'])->default('portrait');
            $table->integer('width')->comment('Width in pixels');
            $table->integer('height')->comment('Height in pixels');
            $table->string('background_image')->nullable();
            $table->string('background_color')->default('#ffffff');
            $table->json('elements')->comment('Visual builder elements configuration');
            $table->enum('status', ['draft', 'active', 'archived'])->default('draft');
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->timestamps();

            $table->index('status');
            $table->index('created_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('certificates');
    }
};
