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
        Schema::create('funnel_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('funnel_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->enum('type', ['landing', 'sales', 'checkout', 'upsell', 'downsell', 'thankyou', 'optin']);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->json('settings')->nullable();
            $table->unsignedBigInteger('next_step_id')->nullable();
            $table->unsignedBigInteger('decline_step_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['funnel_id', 'slug']);
            $table->index(['funnel_id', 'sort_order']);
            $table->index('type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('funnel_steps');
    }
};
