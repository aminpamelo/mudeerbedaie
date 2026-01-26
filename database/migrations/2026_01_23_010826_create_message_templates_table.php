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
        Schema::create('message_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('channel', ['email', 'whatsapp', 'sms']);
            $table->string('subject', 500)->nullable();
            $table->text('content');
            $table->text('content_html')->nullable();
            $table->json('variables')->nullable();
            $table->string('category', 100)->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('stats')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('channel');
            $table->index('category');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('message_templates');
    }
};
