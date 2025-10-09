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
        Schema::create('certificate_issues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('certificate_id')->constrained('certificates')->onDelete('cascade');
            $table->foreignId('student_id')->constrained('students')->onDelete('cascade');
            $table->foreignId('enrollment_id')->nullable()->constrained('enrollments')->onDelete('set null');
            $table->foreignId('class_id')->nullable()->constrained('classes')->onDelete('set null');
            $table->string('certificate_number')->unique()->comment('Unique certificate number e.g. CERT-2025-0001');
            $table->date('issue_date');
            $table->foreignId('issued_by')->constrained('users')->onDelete('cascade');
            $table->string('file_path')->nullable()->comment('Path to generated PDF');
            $table->json('data_snapshot')->comment('Student/course data at time of issue');
            $table->enum('status', ['issued', 'revoked'])->default('issued');
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('revoked_by')->nullable()->constrained('users')->onDelete('set null');
            $table->text('revocation_reason')->nullable();
            $table->timestamps();

            $table->index('certificate_number');
            $table->index('student_id');
            $table->index('issue_date');
            $table->index('status');
            $table->index('enrollment_id');
            $table->index('class_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('certificate_issues');
    }
};
