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
        Schema::create('class_resource_views', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('class_resource_id')->constrained('class_resources')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->timestamp('first_viewed_at')->useCurrent();
            $table->timestamp('last_viewed_at')->useCurrent();
            $table->unsignedInteger('view_count')->default(1);
            $table->timestamps();

            $table->unique(['class_resource_id', 'student_id'], 'crv_resource_student_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('class_resource_views');
    }
};
