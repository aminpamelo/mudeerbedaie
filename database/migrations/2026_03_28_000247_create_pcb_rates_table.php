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
        Schema::create('pcb_rates', function (Blueprint $table) {
            $table->id();
            $table->enum('category', ['single', 'married_spouse_not_working', 'married_spouse_working']);
            $table->integer('num_children')->default(0);
            $table->decimal('min_monthly_income', 10, 2);
            $table->decimal('max_monthly_income', 10, 2)->nullable();
            $table->decimal('pcb_amount', 8, 2);
            $table->integer('year');
            $table->timestamps();

            $table->index(['category', 'year']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pcb_rates');
    }
};
