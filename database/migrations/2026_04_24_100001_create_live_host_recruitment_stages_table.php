<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('live_host_recruitment_stages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')
                ->constrained('live_host_recruitment_campaigns')
                ->cascadeOnDelete();
            $table->unsignedInteger('position');
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_final')->default(false);
            $table->timestamps();

            $table->index(['campaign_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('live_host_recruitment_stages');
    }
};
