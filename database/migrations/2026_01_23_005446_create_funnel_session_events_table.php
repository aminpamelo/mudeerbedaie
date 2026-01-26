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
        Schema::create('funnel_session_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('funnel_sessions')->cascadeOnDelete();
            $table->unsignedBigInteger('step_id')->nullable();
            $table->string('event_type', 50);
            $table->json('event_data')->nullable();
            $table->timestamp('created_at');

            $table->index('event_type');
            $table->index('created_at');
            $table->index(['session_id', 'event_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('funnel_session_events');
    }
};
