<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('live_host_applicants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')
                ->constrained('live_host_recruitment_campaigns')
                ->cascadeOnDelete();
            $table->string('applicant_number')->unique();
            $table->string('full_name');
            $table->string('email');
            $table->string('phone');
            $table->string('ic_number')->nullable();
            $table->string('location')->nullable();
            $table->json('platforms');
            $table->text('experience_summary')->nullable();
            $table->text('motivation')->nullable();
            $table->string('resume_path')->nullable();
            $table->string('source')->nullable();
            $table->foreignId('current_stage_id')
                ->nullable()
                ->constrained('live_host_recruitment_stages')
                ->nullOnDelete();
            $table->string('status')->default('active'); // active|rejected|hired|withdrawn
            $table->unsignedTinyInteger('rating')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('applied_at');
            $table->timestamp('hired_at')->nullable();
            $table->foreignId('hired_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamps();

            $table->unique(['campaign_id', 'email']);
            $table->index(['campaign_id', 'status']);
            $table->index(['current_stage_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('live_host_applicants');
    }
};
