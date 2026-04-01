<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('office_exit_permissions', function (Blueprint $table) {
            $table->id();
            $table->string('permission_number')->unique();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->date('exit_date');
            $table->time('exit_time');
            $table->time('return_time');
            $table->enum('errand_type', ['company', 'personal']);
            $table->text('purpose');
            $table->string('addressed_to');
            $table->enum('status', ['pending', 'approved', 'rejected', 'cancelled'])->default('pending');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('cc_notified_at')->nullable();
            $table->boolean('attendance_note_created')->default(false);
            $table->timestamps();

            $table->index(['employee_id', 'status']);
            $table->index('exit_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('office_exit_permissions');
    }
};
