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
        Schema::create('student_lead_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->unique()->constrained()->cascadeOnDelete();
            $table->integer('total_score')->default(0);
            $table->integer('engagement_score')->default(0);
            $table->integer('purchase_score')->default(0);
            $table->integer('activity_score')->default(0);
            $table->timestamp('last_activity_at')->nullable();
            $table->enum('grade', ['hot', 'warm', 'cold', 'inactive'])->default('cold');

            // MySQL supports ON UPDATE CURRENT_TIMESTAMP, SQLite does not
            $driver = Schema::getConnection()->getDriverName();
            if ($driver === 'mysql') {
                $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            } else {
                $table->timestamp('updated_at')->useCurrent();
            }

            $table->index('grade');
            $table->index(['total_score']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_lead_scores');
    }
};
