<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('performance_reviews')) {
            return;
        }

        Schema::create('performance_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('review_cycle_id')->constrained('review_cycles')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('reviewer_id')->constrained('employees')->cascadeOnDelete();
            $table->enum('status', ['draft', 'self_assessment', 'manager_review', 'completed'])->default('draft');
            $table->text('self_assessment_notes')->nullable();
            $table->text('manager_notes')->nullable();
            $table->decimal('overall_rating', 3, 1)->nullable();
            $table->string('rating_label')->nullable();
            $table->boolean('employee_acknowledged')->default(false);
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['review_cycle_id', 'employee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('performance_reviews');
    }
};
