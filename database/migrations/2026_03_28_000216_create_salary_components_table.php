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
        if (Schema::hasTable('salary_components')) {
            return;
        }

        Schema::create('salary_components', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 20)->unique();
            $table->enum('type', ['earning', 'deduction']);
            $table->enum('category', ['basic', 'fixed_allowance', 'variable_allowance', 'fixed_deduction', 'variable_deduction']);
            $table->boolean('is_taxable')->default(true);
            $table->boolean('is_epf_applicable')->default(true);
            $table->boolean('is_socso_applicable')->default(true);
            $table->boolean('is_eis_applicable')->default(true);
            $table->boolean('is_system')->default(false);
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('salary_components');
    }
};
