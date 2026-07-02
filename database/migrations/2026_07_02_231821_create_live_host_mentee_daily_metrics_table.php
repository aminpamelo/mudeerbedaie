<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-mentee, per-day performance record. Sales are auto-derived from the
     * host's live-session GMV for that day; sales_override lets the PIC correct
     * a single day (effective daily sales = override ?? auto). The monthly Sales
     * KPI is the SUM of effective daily sales across the month. The comment is
     * the PIC's mandatory daily performance note (the daily activity log).
     */
    public function up(): void
    {
        Schema::create('live_host_mentee_daily_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mentee_id')->constrained('live_host_mentees')->cascadeOnDelete();
            $table->date('metric_date');
            $table->decimal('sales_override', 12, 2)->nullable();
            $table->text('comment')->nullable();
            $table->foreignId('commented_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('commented_at')->nullable();
            $table->timestamps();

            $table->unique(['mentee_id', 'metric_date']);
            $table->index(['mentee_id', 'metric_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('live_host_mentee_daily_metrics');
    }
};
