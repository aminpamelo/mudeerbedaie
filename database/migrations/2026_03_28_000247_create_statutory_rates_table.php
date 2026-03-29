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
        if (Schema::hasTable('statutory_rates')) {
            return;
        }

        Schema::create('statutory_rates', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['epf_employee', 'epf_employer', 'socso_employee', 'socso_employer', 'eis_employee', 'eis_employer']);
            $table->decimal('min_salary', 10, 2);
            $table->decimal('max_salary', 10, 2)->nullable();
            $table->decimal('rate_percentage', 5, 2)->nullable();
            $table->decimal('fixed_amount', 8, 2)->nullable();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->timestamps();

            $table->index(['type', 'effective_from']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('statutory_rates');
    }
};
