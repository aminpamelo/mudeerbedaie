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
        Schema::create('funnels', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('template_id')->nullable();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->enum('type', ['sales', 'lead', 'webinar', 'course'])->default('sales');
            $table->enum('status', ['draft', 'published', 'archived'])->default('draft');
            $table->json('settings')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'status']);
            $table->index('slug');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('funnels');
    }
};
