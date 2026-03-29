<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('employee_certifications')) {
            return;
        }

        Schema::create('employee_certifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees');
            $table->foreignId('certification_id')->constrained('certifications');
            $table->string('certificate_number')->nullable();
            $table->date('issued_date');
            $table->date('expiry_date')->nullable();
            $table->string('certificate_path')->nullable();
            $table->string('status')->default('active'); // active, expired, revoked
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_certifications');
    }
};
