<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Reusable, global "slot template": a named weekly grid of time windows that
     * can be applied to a slot override in one pick (copy-on-apply). The windows
     * are stored as a JSON blueprint — they are never referenced by assignments,
     * so they need no live_time_slots rows.
     */
    public function up(): void
    {
        Schema::create('live_slot_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('description')->nullable();
            // [{ "day_of_week": 0-6, "start_time": "HH:MM", "end_time": "HH:MM" }, ...]
            $table->json('slots');
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['is_active', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('live_slot_templates');
    }
};
