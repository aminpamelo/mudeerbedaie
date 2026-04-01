<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('department_approvers')) {
            return;
        }

        Schema::create('department_approvers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->constrained('departments')->cascadeOnDelete();
            $table->foreignId('approver_employee_id')->constrained('employees')->cascadeOnDelete();
            $table->enum('approval_type', ['overtime', 'leave', 'claims', 'exit_permission']);
            $table->timestamps();

            $table->index(['department_id', 'approval_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('department_approvers');
    }
};
