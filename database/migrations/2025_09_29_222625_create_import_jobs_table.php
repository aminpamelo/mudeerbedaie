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
        Schema::create('import_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('platform_id')->constrained()->onDelete('cascade');
            $table->foreignId('platform_account_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // Who started the import

            // File information
            $table->string('file_name');
            $table->string('file_path');
            $table->string('file_hash')->nullable(); // For duplicate detection
            $table->integer('file_size')->nullable(); // In bytes
            $table->string('import_type')->default('orders'); // orders, products, customers

            // Import statistics
            $table->integer('total_rows')->default(0);
            $table->integer('processed_rows')->default(0);
            $table->integer('successful_rows')->default(0);
            $table->integer('failed_rows')->default(0);
            $table->integer('skipped_rows')->default(0); // Duplicates, invalid data

            // Processing status
            $table->enum('status', ['pending', 'processing', 'completed', 'failed', 'cancelled'])->default('pending');
            $table->text('status_message')->nullable();
            $table->integer('progress_percentage')->default(0);

            // Field mapping and configuration
            $table->json('field_mapping')->nullable(); // CSV column to database field mapping
            $table->json('import_settings')->nullable(); // Import configuration options
            $table->json('validation_rules')->nullable(); // Custom validation rules

            // Results and errors
            $table->json('errors')->nullable(); // Array of errors encountered
            $table->json('warnings')->nullable(); // Array of warnings
            $table->json('summary')->nullable(); // Import summary data
            $table->text('log_file_path')->nullable(); // Path to detailed log file

            // Timing
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->integer('duration_seconds')->nullable(); // Processing time

            // Batch processing
            $table->integer('batch_size')->default(100);
            $table->integer('current_batch')->default(0);
            $table->integer('total_batches')->default(0);

            $table->timestamps();

            // Indexes for performance
            $table->index(['platform_account_id', 'status']);
            $table->index(['import_type', 'status']);
            $table->index(['user_id', 'created_at']);
            $table->index(['started_at']);
            $table->index(['file_hash']); // For duplicate detection
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('import_jobs');
    }
};
