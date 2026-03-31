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
        Schema::create('claim_type_vehicle_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('claim_type_id')->constrained('claim_types')->cascadeOnDelete();
            $table->string('name');
            $table->decimal('rate_per_km', 8, 2);
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['claim_type_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('claim_type_vehicle_rates');
    }
};
