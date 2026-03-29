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
        if (Schema::hasTable('employee_tax_profiles')) {
            return;
        }

        Schema::create('employee_tax_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->unique()->constrained('employees')->cascadeOnDelete();
            $table->string('tax_number')->nullable();
            $table->enum('marital_status', ['single', 'married_spouse_not_working', 'married_spouse_working'])->default('single');
            $table->integer('num_children')->default(0);
            $table->integer('num_children_studying')->default(0);
            $table->boolean('disabled_individual')->default(false);
            $table->boolean('disabled_spouse')->default(false);
            $table->boolean('is_pcb_manual')->default(false);
            $table->decimal('manual_pcb_amount', 8, 2)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_tax_profiles');
    }
};
