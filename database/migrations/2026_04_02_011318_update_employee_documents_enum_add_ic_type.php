<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * SQLite does not support ALTER COLUMN, so we recreate the table
     * with the updated enum that replaces ic_front/ic_back with ic.
     */
    public function up(): void
    {
        Schema::create('employee_documents_new', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->enum('document_type', ['ic', 'offer_letter', 'contract', 'bank_statement', 'epf_form', 'socso_form']);
            $table->string('file_name');
            $table->string('file_path');
            $table->integer('file_size');
            $table->string('mime_type');
            $table->timestamp('uploaded_at');
            $table->date('expiry_date')->nullable();
            $table->string('notes')->nullable();
            $table->timestamps();
        });

        DB::statement('INSERT INTO employee_documents_new SELECT * FROM employee_documents');

        Schema::drop('employee_documents');

        Schema::rename('employee_documents_new', 'employee_documents');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('employee_documents_old', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->enum('document_type', ['ic_front', 'ic_back', 'offer_letter', 'contract', 'bank_statement', 'epf_form', 'socso_form']);
            $table->string('file_name');
            $table->string('file_path');
            $table->integer('file_size');
            $table->string('mime_type');
            $table->timestamp('uploaded_at');
            $table->date('expiry_date')->nullable();
            $table->string('notes')->nullable();
            $table->timestamps();
        });

        DB::statement('INSERT INTO employee_documents_old SELECT * FROM employee_documents');

        Schema::drop('employee_documents');

        Schema::rename('employee_documents_old', 'employee_documents');
    }
};
