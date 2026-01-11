<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_feedbacks', function (Blueprint $table) {
            $table->id();
            $table->string('feedback_number')->unique();
            $table->foreignId('order_id')->nullable()->constrained('product_orders')->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();

            $table->enum('type', ['complaint', 'suggestion', 'compliment', 'question', 'other'])->default('other');
            $table->unsignedTinyInteger('rating')->nullable();
            $table->string('subject');
            $table->text('message');
            $table->enum('status', ['pending', 'reviewed', 'responded', 'archived'])->default('pending');
            $table->text('admin_response')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->boolean('is_public')->default(false);

            $table->timestamps();

            $table->index(['status', 'type']);
            $table->index('order_id');
            $table->index('customer_id');
            $table->index('rating');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_feedbacks');
    }
};
