<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pip_goals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pip_id')->constrained('performance_improvement_plans')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->date('target_date');
            $table->enum('status', ['pending', 'in_progress', 'achieved', 'not_achieved'])->default('pending');
            $table->text('check_in_notes')->nullable();
            $table->timestamp('checked_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pip_goals');
    }
};
