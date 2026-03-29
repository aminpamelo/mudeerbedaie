<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_budgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->constrained('departments');
            $table->integer('year');
            $table->decimal('allocated_amount', 10, 2);
            $table->decimal('spent_amount', 10, 2)->default(0);
            $table->timestamps();

            $table->unique(['department_id', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_budgets');
    }
};
