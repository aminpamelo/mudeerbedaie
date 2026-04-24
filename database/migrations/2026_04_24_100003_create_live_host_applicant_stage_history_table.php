<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('live_host_applicant_stage_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('applicant_id')
                ->constrained('live_host_applicants')
                ->cascadeOnDelete();
            $table->foreignId('from_stage_id')
                ->nullable()
                ->constrained('live_host_recruitment_stages')
                ->nullOnDelete();
            $table->foreignId('to_stage_id')
                ->nullable()
                ->constrained('live_host_recruitment_stages')
                ->nullOnDelete();
            $table->string('action'); // applied|advanced|reverted|rejected|hired|note
            $table->text('notes')->nullable();
            $table->foreignId('changed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamps();

            $table->index(['applicant_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('live_host_applicant_stage_history');
    }
};
