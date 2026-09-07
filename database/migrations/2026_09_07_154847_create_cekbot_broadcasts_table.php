<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cekbot_broadcasts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cekbot_session_id')->constrained('cekbot_sessions')->cascadeOnDelete();
            $table->string('name');
            $table->text('message');
            $table->string('audience_type')->default('all'); // all | label
            $table->string('audience_value')->nullable();     // label key when audience_type = label
            $table->boolean('include_groups')->default(false);
            $table->string('status')->default('draft');        // draft | scheduled | sending | sent | failed
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedInteger('total_recipients')->default(0);
            $table->unsignedInteger('sent_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'scheduled_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cekbot_broadcasts');
    }
};
