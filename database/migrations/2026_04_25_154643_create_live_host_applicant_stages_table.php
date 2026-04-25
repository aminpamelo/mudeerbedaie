<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('live_host_applicant_stages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('applicant_id')
                ->constrained('live_host_applicants')
                ->cascadeOnDelete();
            $table->foreignId('stage_id')
                ->nullable()
                ->constrained('live_host_recruitment_stages')
                ->nullOnDelete();
            $table->foreignId('assignee_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->dateTime('due_at')->nullable();
            $table->text('stage_notes')->nullable();
            $table->dateTime('entered_at');
            $table->dateTime('exited_at')->nullable();
            $table->timestamps();

            $table->index(['applicant_id', 'exited_at']);
            $table->index(['assignee_id', 'due_at']);
        });

        // Backfill: open one row per existing applicant at its current stage.
        // Idempotent: skips applicants that already have an open stage row.
        DB::table('live_host_applicants')
            ->whereNotNull('current_stage_id')
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('live_host_applicant_stages')
                    ->whereColumn('live_host_applicant_stages.applicant_id', 'live_host_applicants.id')
                    ->whereNull('live_host_applicant_stages.exited_at');
            })
            ->orderBy('id')
            ->select(['id', 'current_stage_id', 'applied_at'])
            ->chunkById(500, function ($rows) {
                $now = now();
                $insert = $rows->map(fn ($row) => [
                    'applicant_id' => $row->id,
                    'stage_id' => $row->current_stage_id,
                    'assignee_id' => null,
                    'due_at' => null,
                    'stage_notes' => null,
                    'entered_at' => $row->applied_at ?? $now,
                    'exited_at' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all();
                if (! empty($insert)) {
                    DB::table('live_host_applicant_stages')->insert($insert);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('live_host_applicant_stages');
    }
};
