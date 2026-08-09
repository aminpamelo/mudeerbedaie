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
        Schema::create('mindpal_documents', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('file_path');
            $table->string('file_name');
            $table->unsignedBigInteger('file_size')->default(0);
            $table->unsignedInteger('total_pages')->default(0);
            $table->unsignedInteger('total_chunks')->default(0);
            $table->string('status', 20)->default('processing');
            $table->text('error_message')->nullable();
            $table->foreignId('uploaded_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->index('status', 'mindpal_doc_status_index');
        });

        Schema::create('mindpal_chunks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained('mindpal_documents')->cascadeOnDelete();
            $table->text('content');
            $table->unsignedInteger('page_number')->default(1);
            $table->unsignedInteger('chunk_index')->default(0);
            $table->json('embedding')->nullable();
            $table->unsignedInteger('token_count')->default(0);
            $table->timestamps();

            $table->index(['document_id', 'chunk_index'], 'mindpal_chunk_doc_idx');
        });

        Schema::create('mindpal_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('title')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'updated_at'], 'mindpal_conv_user_time');
        });

        Schema::create('mindpal_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('mindpal_conversations')->cascadeOnDelete();
            $table->string('role', 20);
            $table->text('content');
            $table->json('sources')->nullable();
            $table->unsignedInteger('tokens_used')->default(0);
            $table->timestamps();

            $table->index('conversation_id', 'mindpal_msg_conv_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mindpal_messages');
        Schema::dropIfExists('mindpal_conversations');
        Schema::dropIfExists('mindpal_chunks');
        Schema::dropIfExists('mindpal_documents');
    }
};
