<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('live_host_mentee_checklist_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mentee_id')
                ->constrained('live_host_mentees')
                ->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->boolean('is_required')->default(true);
            $table->string('status')->default('pending'); // pending|done
            $table->unsignedInteger('position')->default(0);
            $table->dateTime('due_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('completed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamps();

            $table->index(['mentee_id', 'status']);
            $table->index(['mentee_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('live_host_mentee_checklist_items');
    }
};
