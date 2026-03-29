<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('applicants')) {
            return;
        }

        Schema::create('applicants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_posting_id')->constrained('job_postings')->cascadeOnDelete();
            $table->string('applicant_number')->unique();
            $table->string('full_name');
            $table->string('email');
            $table->string('phone');
            $table->string('ic_number')->nullable();
            $table->string('resume_path');
            $table->text('cover_letter')->nullable();
            $table->enum('source', ['website', 'referral', 'jobstreet', 'linkedin', 'walk_in', 'other'])->default('website');
            $table->enum('current_stage', ['applied', 'screening', 'interview', 'assessment', 'offer', 'hired', 'rejected', 'withdrawn'])->default('applied');
            $table->integer('rating')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('applied_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('applicants');
    }
};
