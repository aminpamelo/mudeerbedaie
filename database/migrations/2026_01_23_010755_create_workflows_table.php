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
        Schema::create('workflows', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('type', ['automation', 'funnel', 'sequence', 'broadcast'])->default('automation');
            $table->enum('status', ['draft', 'active', 'paused', 'archived'])->default('draft');
            $table->string('trigger_type', 100);
            $table->json('trigger_config')->nullable();
            $table->json('canvas_data')->nullable();
            $table->json('settings')->nullable();
            $table->json('stats')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('type');
            $table->index('trigger_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workflows');
    }
};
