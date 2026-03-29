<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('exit_checklists')) {
            return;
        }

        Schema::create('exit_checklists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees');
            $table->foreignId('resignation_request_id')->nullable()->constrained('resignation_requests');
            $table->string('status')->default('in_progress'); // in_progress, completed
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exit_checklists');
    }
};
