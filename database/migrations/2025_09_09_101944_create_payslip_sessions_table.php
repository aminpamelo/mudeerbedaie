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
        Schema::create('payslip_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payslip_id')->constrained('payslips')->onDelete('cascade');
            $table->foreignId('session_id')->constrained('class_sessions')->onDelete('cascade');
            $table->decimal('amount', 10, 2); // Frozen amount at the time of payslip generation
            $table->timestamp('included_at');
            $table->timestamps();

            // Prevent duplicate session inclusion in same payslip
            $table->unique(['payslip_id', 'session_id'], 'payslip_sessions_unique');

            // Indexes for performance
            $table->index(['payslip_id', 'included_at']);
            $table->index(['session_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payslip_sessions');
    }
};
