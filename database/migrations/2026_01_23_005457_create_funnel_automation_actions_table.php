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
        Schema::create('funnel_automation_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('automation_id')->constrained('funnel_automations')->cascadeOnDelete();
            $table->string('action_type', 50);
            $table->json('action_config');
            $table->unsignedInteger('delay_minutes')->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->json('conditions')->nullable();
            $table->timestamps();

            $table->index(['automation_id', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('funnel_automation_actions');
    }
};
