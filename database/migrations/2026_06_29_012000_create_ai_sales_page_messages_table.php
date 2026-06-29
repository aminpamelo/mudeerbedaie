<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_sales_page_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ai_sales_page_id')->constrained('ai_sales_pages')->cascadeOnDelete();
            $table->enum('role', ['user', 'assistant'])->default('user');
            $table->text('content');
            $table->enum('status', ['ok', 'error'])->default('ok');
            $table->timestamps();

            $table->index('ai_sales_page_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_sales_page_messages');
    }
};
