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
        Schema::create('funnel_analytics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('funnel_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('funnel_step_id')->nullable();
            $table->date('date');
            $table->unsignedInteger('unique_visitors')->default(0);
            $table->unsignedInteger('pageviews')->default(0);
            $table->unsignedInteger('conversions')->default(0);
            $table->decimal('revenue', 12, 2)->default(0);
            $table->unsignedInteger('avg_time_seconds')->default(0);
            $table->unsignedInteger('bounce_count')->default(0);
            $table->timestamps();

            $table->unique(['funnel_id', 'funnel_step_id', 'date']);
            $table->index('date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('funnel_analytics');
    }
};
