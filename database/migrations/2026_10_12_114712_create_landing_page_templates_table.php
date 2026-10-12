<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('landing_page_templates', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('source_funnel_step_id')->nullable()->constrained('funnel_steps')->nullOnDelete();
            $table->string('name');
            $table->string('description', 500)->nullable();
            $table->string('category', 50)->nullable();
            $table->json('content');
            $table->longText('custom_css')->nullable();
            $table->longText('custom_js')->nullable();
            $table->unsignedInteger('usage_count')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('category');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('landing_page_templates');
    }
};
