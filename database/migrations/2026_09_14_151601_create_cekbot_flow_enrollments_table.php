<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cekbot_flow_enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cekbot_conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cekbot_flow_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('active'); // active|completed|abandoned
            $table->string('current_step')->nullable();   // await_package|await_payment|await_name|await_address|await_receipt|done
            $table->json('data')->nullable();              // collected answers: package, payment_method, name, address...
            $table->foreignId('product_order_id')->nullable()->constrained('product_orders')->nullOnDelete();

            // Follow-up automation (wired in a later phase).
            $table->unsignedInteger('follow_up_stage')->default(0);
            $table->timestamp('next_follow_up_at')->nullable();

            $table->timestamp('started_at')->nullable();
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['cekbot_conversation_id', 'status']);
            $table->index(['status', 'next_follow_up_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cekbot_flow_enrollments');
    }
};
