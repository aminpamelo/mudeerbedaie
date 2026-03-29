<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('rating_scales')) {
            return;
        }

        Schema::create('rating_scales', function (Blueprint $table) {
            $table->id();
            $table->integer('score')->unique();
            $table->string('label');
            $table->text('description')->nullable();
            $table->string('color');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rating_scales');
    }
};
