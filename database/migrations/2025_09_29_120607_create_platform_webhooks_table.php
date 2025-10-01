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
        Schema::create('platform_webhooks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('platform_id')->constrained()->onDelete('cascade');
            $table->foreignId('platform_account_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('name'); // Webhook name/description
            $table->string('event_type'); // order.created, order.updated, etc.
            $table->string('endpoint_url'); // Where to send webhook
            $table->string('secret')->nullable(); // Webhook signing secret
            $table->string('method')->default('POST'); // HTTP method
            $table->json('headers')->nullable(); // Custom headers
            $table->json('payload_template')->nullable(); // Custom payload format
            $table->boolean('is_active')->default(true);
            $table->boolean('verify_ssl')->default(true);
            $table->integer('timeout_seconds')->default(30);
            $table->integer('retry_attempts')->default(3);
            $table->timestamp('last_triggered_at')->nullable();
            $table->timestamp('last_success_at')->nullable();
            $table->timestamp('last_failure_at')->nullable();
            $table->text('last_error')->nullable();
            $table->integer('total_calls')->default(0);
            $table->integer('successful_calls')->default(0);
            $table->integer('failed_calls')->default(0);
            $table->timestamps();

            $table->index(['platform_id', 'event_type']);
            $table->index(['platform_account_id', 'is_active']);
            $table->index(['last_triggered_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('platform_webhooks');
    }
};
