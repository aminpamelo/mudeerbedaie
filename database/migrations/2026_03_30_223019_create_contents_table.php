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
        Schema::create('contents', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('stage', ['idea', 'shooting', 'editing', 'posting', 'posted'])->default('idea');
            $table->date('due_date')->nullable();
            $table->enum('priority', ['low', 'medium', 'high', 'urgent'])->default('medium');
            $table->string('tiktok_url')->nullable();
            $table->string('tiktok_post_id')->nullable();
            $table->boolean('is_flagged_for_ads')->default(false);
            $table->boolean('is_marked_for_ads')->default(false);
            $table->foreignId('marked_by')->nullable()->constrained('employees')->nullOnDelete();
            $table->timestamp('marked_at')->nullable();
            $table->foreignId('created_by')->constrained('employees')->cascadeOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index('stage');
            $table->index('priority');
            $table->index('is_marked_for_ads');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contents');
    }
};
