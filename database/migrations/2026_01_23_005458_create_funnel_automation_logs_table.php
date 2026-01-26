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
        Schema::create('funnel_automation_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('automation_id')->constrained('funnel_automations')->cascadeOnDelete();
            $table->foreignId('action_id')->constrained('funnel_automation_actions')->cascadeOnDelete();
            $table->foreignId('session_id')->nullable()->constrained('funnel_sessions')->nullOnDelete();
            $table->string('contact_email')->nullable();
            $table->enum('status', ['pending', 'executed', 'failed', 'skipped'])->default('pending');
            $table->json('result')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('executed_at')->nullable();
            $table->timestamp('created_at');

            $table->index(['automation_id', 'status']);
            $table->index('scheduled_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('funnel_automation_logs');
    }
};
