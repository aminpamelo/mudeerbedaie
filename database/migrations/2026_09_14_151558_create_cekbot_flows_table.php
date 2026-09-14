<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cekbot_flows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cekbot_session_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->boolean('is_active')->default(true);

            // Trigger — which inbound messages start this funnel.
            $table->string('match_type')->default('contains'); // contains|exact|starts
            $table->json('trigger_keywords')->nullable();

            // Scripted copy.
            $table->text('welcome_message')->nullable();
            $table->text('package_prompt')->nullable();
            $table->text('confirmation_message')->nullable();

            // Payment configuration.
            $table->boolean('ask_payment')->default(true);
            $table->boolean('payment_transfer_enabled')->default(true);
            $table->boolean('payment_cod_enabled')->default(true);
            $table->text('bank_details')->nullable();      // shown when transfer chosen
            $table->text('transfer_instructions')->nullable();

            // Detail collection.
            $table->boolean('ask_name')->default(true);

            // Order attribution + result.
            $table->foreignId('sales_source_id')->nullable()->constrained('sales_sources')->nullOnDelete();

            // Follow-up automation (wired in a later phase): [{after_minutes, message}].
            $table->json('follow_ups')->nullable();

            $table->unsignedInteger('sort_order')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['cekbot_session_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cekbot_flows');
    }
};
