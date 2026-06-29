<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_sales_page_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ai_sales_page_id')->constrained('ai_sales_pages')->cascadeOnDelete();
            $table->unsignedInteger('version')->default(1);
            $table->string('label')->nullable();

            $table->longText('html')->nullable();
            $table->longText('custom_css')->nullable();
            $table->longText('custom_js')->nullable();

            $table->enum('generated_by', ['ai', 'human'])->default('ai');
            $table->text('prompt_snapshot')->nullable();
            $table->string('model')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['ai_sales_page_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_sales_page_versions');
    }
};
