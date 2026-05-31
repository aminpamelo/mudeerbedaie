<?php

namespace App\Services\Mentoring;

use App\Models\LiveHostMentee;
use App\Models\LiveHostMenteeStage;
use App\Models\LiveHostMentoringStage;
use Illuminate\Support\Facades\DB;

class MenteeStageTransition
{
    /**
     * Open the very first stage row for a freshly enrolled mentee.
     * Idempotent: does nothing if the mentee already has an open row.
     */
    public function enterFirstStage(LiveHostMentee $mentee): ?LiveHostMenteeStage
    {
        if ($mentee->current_stage_id === null) {
            return null;
        }

        $existing = LiveHostMenteeStage::query()
            ->where('mentee_id', $mentee->id)
            ->whereNull('exited_at')
            ->first();

        if ($existing) {
            return $existing;
        }

        return LiveHostMenteeStage::create([
            'mentee_id' => $mentee->id,
            'stage_id' => $mentee->current_stage_id,
            'assignee_id' => $mentee->effectiveMentorId(),
            'entered_at' => $mentee->enrolled_at ?? now(),
        ]);
    }

    /**
     * Move the mentee to the destination stage. Closes any open row, opens a
     * new one (carrying the mentee's effective mentor as the default assignee),
     * and updates current_stage_id. Caller writes the audit-log entry to
     * live_host_mentee_stage_history.
     */
    public function transition(
        LiveHostMentee $mentee,
        LiveHostMentoringStage $toStage,
    ): LiveHostMenteeStage {
        return DB::transaction(function () use ($mentee, $toStage) {
            $now = now();

            LiveHostMenteeStage::query()
                ->where('mentee_id', $mentee->id)
                ->whereNull('exited_at')
                ->update(['exited_at' => $now, 'updated_at' => $now]);

            $mentee->update(['current_stage_id' => $toStage->id]);

            return LiveHostMenteeStage::create([
                'mentee_id' => $mentee->id,
                'stage_id' => $toStage->id,
                'assignee_id' => $mentee->effectiveMentorId(),
                'entered_at' => $now,
            ]);
        });
    }

    /**
     * Close the open row without opening a new one — used on graduate/drop.
     */
    public function closeOpenRow(LiveHostMentee $mentee): void
    {
        $now = now();
        LiveHostMenteeStage::query()
            ->where('mentee_id', $mentee->id)
            ->whereNull('exited_at')
            ->update(['exited_at' => $now, 'updated_at' => $now]);
    }
}
