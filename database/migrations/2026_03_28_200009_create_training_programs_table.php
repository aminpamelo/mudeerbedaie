<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('training_programs')) {
            return;
        }

        Schema::create('training_programs', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('type'); // internal, external
            $table->string('category'); // mandatory, technical, soft_skill, compliance, other
            $table->string('provider')->nullable();
            $table->string('location')->nullable();
            $table->date('start_date');
            $table->date('end_date');
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->integer('max_participants')->nullable();
            $table->decimal('cost_per_person', 10, 2)->nullable();
            $table->decimal('total_budget', 10, 2)->nullable();
            $table->string('status')->default('planned'); // planned, ongoing, completed, cancelled
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_programs');
    }
};
