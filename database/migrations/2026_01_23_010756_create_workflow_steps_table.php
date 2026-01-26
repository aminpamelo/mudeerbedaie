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
        Schema::create('workflow_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_id')->constrained()->cascadeOnDelete();
            $table->uuid('uuid')->unique();
            $table->string('node_id', 100);
            $table->string('type', 50);
            $table->string('action_type', 100)->nullable();
            $table->string('name')->nullable();
            $table->json('config')->nullable();
            $table->integer('position_x')->default(0);
            $table->integer('position_y')->default(0);
            $table->integer('order_index')->default(0);
            $table->timestamps();

            $table->index('workflow_id');
            $table->index('type');
            $table->unique(['workflow_id', 'node_id'], 'uk_workflow_node');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workflow_steps');
    }
};
