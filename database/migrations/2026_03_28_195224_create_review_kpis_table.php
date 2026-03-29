<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('review_kpis', function (Blueprint $table) {
            $table->id();
            $table->foreignId('performance_review_id')->constrained('performance_reviews')->cascadeOnDelete();
            $table->foreignId('kpi_template_id')->nullable()->constrained('kpi_templates')->nullOnDelete();
            $table->string('title');
            $table->string('target');
            $table->decimal('weight', 5, 2);
            $table->integer('self_score')->nullable();
            $table->text('self_comments')->nullable();
            $table->integer('manager_score')->nullable();
            $table->text('manager_comments')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('review_kpis');
    }
};
