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
        Schema::create('funnel_automations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->foreignId('funnel_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('trigger_type', 50);
            $table->json('trigger_config')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('priority')->default(0);
            $table->timestamps();

            $table->index(['funnel_id', 'trigger_type', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('funnel_automations');
    }
};
