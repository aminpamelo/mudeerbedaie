<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('exit_interviews')) {
            return;
        }

        Schema::create('exit_interviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees');
            $table->foreignId('conducted_by')->constrained('employees');
            $table->date('interview_date');
            $table->string('reason_for_leaving'); // better_opportunity, salary, work_environment, personal, relocation, career_change, management, other
            $table->integer('overall_satisfaction'); // 1-5
            $table->boolean('would_recommend');
            $table->text('feedback')->nullable();
            $table->text('improvements')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exit_interviews');
    }
};
