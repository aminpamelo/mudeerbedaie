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
        Schema::create('funnel_step_contents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('funnel_step_id')->constrained('funnel_steps')->cascadeOnDelete();
            $table->unsignedBigInteger('template_id')->nullable();
            $table->json('content');
            $table->longText('custom_css')->nullable();
            $table->longText('custom_js')->nullable();
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->string('og_image')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->boolean('is_published')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index('funnel_step_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('funnel_step_contents');
    }
};
