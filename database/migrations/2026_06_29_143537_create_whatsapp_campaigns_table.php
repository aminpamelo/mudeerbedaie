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
        Schema::create('whatsapp_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            // Channel/source the recipients were collected from (e.g. orders_bulk).
            $table->string('source')->default('orders_bulk');
            // Approved Meta template used for the blast.
            $table->foreignId('whatsapp_template_id')->nullable()->constrained('whatsapp_templates')->nullOnDelete();
            $table->string('template_name');
            $table->string('template_language', 16)->default('en');
            // Map of {{n}} placeholders -> {source: order|static, field|value} per component.
            $table->json('variable_mapping')->nullable();
            // draft | queued | sending | completed | cancelled | failed
            $table->string('status')->default('draft');
            $table->unsignedInteger('total_recipients')->default(0);
            $table->unsignedInteger('skipped_count')->default(0);
            $table->unsignedInteger('sent_count')->default(0);
            $table->unsignedInteger('delivered_count')->default(0);
            $table->unsignedInteger('read_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->decimal('estimated_cost_usd', 10, 4)->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('whatsapp_campaigns');
    }
};
