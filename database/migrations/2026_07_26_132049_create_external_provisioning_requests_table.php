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
        Schema::create('external_provisioning_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('external_system_id')->constrained('external_systems')->cascadeOnDelete();
            $table->foreignId('product_order_id')->constrained('product_orders')->cascadeOnDelete();
            $table->foreignId('funnel_product_id')->nullable()->constrained('funnel_products')->nullOnDelete();
            $table->string('idempotency_key')->unique();
            $table->string('status')->default('pending'); // pending | processing | succeeded | failed
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->string('external_user_id')->nullable();
            $table->text('login_url')->nullable();
            $table->text('credentials')->nullable(); // encrypted: username / temp_password / magic_link
            $table->unsignedInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('provisioned_at')->nullable();
            $table->timestamps();

            $table->index(['external_system_id', 'status'], 'ext_prov_system_status_index');
            $table->index('product_order_id', 'ext_prov_order_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('external_provisioning_requests');
    }
};
