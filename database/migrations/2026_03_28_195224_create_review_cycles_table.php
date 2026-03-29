<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('review_cycles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('type', ['monthly', 'quarterly', 'semi_annual', 'annual']);
            $table->date('start_date');
            $table->date('end_date');
            $table->date('submission_deadline');
            $table->enum('status', ['draft', 'active', 'in_review', 'completed', 'cancelled'])->default('draft');
            $table->text('description')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('review_cycles');
    }
};
