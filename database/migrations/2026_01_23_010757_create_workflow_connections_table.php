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
        Schema::create('workflow_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_id')->constrained()->cascadeOnDelete();
            $table->foreignId('source_step_id')->constrained('workflow_steps')->cascadeOnDelete();
            $table->foreignId('target_step_id')->constrained('workflow_steps')->cascadeOnDelete();
            $table->string('source_handle', 50)->nullable();
            $table->string('target_handle', 50)->nullable();
            $table->string('label', 100)->nullable();
            $table->json('condition_config')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('workflow_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workflow_connections');
    }
};
