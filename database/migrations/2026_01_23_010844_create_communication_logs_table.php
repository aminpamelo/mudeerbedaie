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
        Schema::create('communication_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->enum('channel', ['email', 'whatsapp', 'sms', 'in_app']);
            $table->enum('direction', ['outbound', 'inbound'])->default('outbound');
            $table->foreignId('template_id')->nullable()->constrained('message_templates')->nullOnDelete();
            $table->foreignId('workflow_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('step_execution_id')->nullable()->constrained('workflow_step_executions')->nullOnDelete();
            $table->string('external_id')->nullable();
            $table->string('recipient');
            $table->string('subject', 500)->nullable();
            $table->text('content')->nullable();
            $table->enum('status', ['queued', 'sent', 'delivered', 'failed', 'opened', 'clicked', 'bounced', 'complained'])->default('queued');
            $table->json('status_details')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('clicked_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('student_id');
            $table->index('channel');
            $table->index('status');
            $table->index('workflow_id');
            $table->index('external_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('communication_logs');
    }
};
