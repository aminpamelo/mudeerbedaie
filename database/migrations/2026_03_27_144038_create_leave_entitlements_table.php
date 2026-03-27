<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_entitlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('leave_type_id')->constrained('leave_types')->cascadeOnDelete();
            $table->enum('employment_type', ['full_time', 'part_time', 'contract', 'intern', 'all']);
            $table->integer('min_service_months')->default(0);
            $table->integer('max_service_months')->nullable();
            $table->decimal('days_per_year', 4, 1);
            $table->boolean('is_prorated')->default(false);
            $table->integer('carry_forward_max')->default(0);
            $table->timestamps();

            $table->index(['leave_type_id', 'employment_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_entitlements');
    }
};
