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
        Schema::create('lead_scoring_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('event_type', 100);
            $table->json('conditions')->nullable();
            $table->integer('points');
            $table->boolean('is_active')->default(true);
            $table->integer('expires_after_days')->nullable();
            $table->integer('max_occurrences')->nullable();
            $table->timestamps();

            $table->index('event_type');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lead_scoring_rules');
    }
};
