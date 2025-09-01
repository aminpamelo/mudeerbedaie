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
        Schema::create('course_class_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained('courses')->onDelete('cascade');
            $table->enum('teaching_mode', ['online', 'offline', 'hybrid'])->default('online');
            $table->enum('billing_type', ['per_month', 'per_session', 'per_minute']);
            $table->integer('sessions_per_month')->nullable();
            $table->integer('session_duration_hours')->default(0);
            $table->integer('session_duration_minutes')->default(0);
            $table->decimal('price_per_session', 8, 2)->nullable();
            $table->decimal('price_per_month', 8, 2)->nullable();
            $table->decimal('price_per_minute', 8, 2)->nullable();
            $table->text('class_description')->nullable();
            $table->text('class_instructions')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('course_class_settings');
    }
};
