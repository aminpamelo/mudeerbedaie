<?php

namespace App\Services\Recruitment;

use App\Models\LiveHostApplicant;
use App\Models\LiveHostApplicantStage;
use App\Models\LiveHostRecruitmentStage;
use Illuminate\Support\Facades\DB;

class ApplicantStageTransition
{
    /**
     * Open the very first stage row for a freshly created applicant.
     * Idempotent: does nothing if the applicant already has an open row.
     */
    public function enterFirstStage(LiveHostApplicant $applicant): ?LiveHostApplicantStage
    {
        if ($applicant->current_stage_id === null) {
            return null;
        }

        $existing = LiveHostApplicantStage::query()
            ->where('applicant_id', $applicant->id)
            ->whereNull('exited_at')
            ->first();

        if ($existing) {
            return $existing;
        }

        return LiveHostApplicantStage::create([
            'applicant_id' => $applicant->id,
            'stage_id' => $applicant->current_stage_id,
            'entered_at' => $applicant->applied_at ?? now(),
        ]);
    }

    /**
     * Move the applicant to the destination stage.
     * Closes any open row, opens a new one, and updates current_stage_id
     * on the applicant. Caller is responsible for writing the audit-log
     * entry to live_host_applicant_stage_history (existing behaviour).
     */
    public function transition(
        LiveHostApplicant $applicant,
        LiveHostRecruitmentStage $toStage,
    ): LiveHostApplicantStage {
        return DB::transaction(function () use ($applicant, $toStage) {
            $now = now();

            LiveHostApplicantStage::query()
                ->where('applicant_id', $applicant->id)
                ->whereNull('exited_at')
                ->update(['exited_at' => $now, 'updated_at' => $now]);

            $applicant->update(['current_stage_id' => $toStage->id]);

            return LiveHostApplicantStage::create([
                'applicant_id' => $applicant->id,
                'stage_id' => $toStage->id,
                'entered_at' => $now,
            ]);
        });
    }

    /**
     * Close the open row without opening a new one — used on reject/hire/withdraw.
     */
    public function closeOpenRow(LiveHostApplicant $applicant): void
    {
        $now = now();
        LiveHostApplicantStage::query()
            ->where('applicant_id', $applicant->id)
            ->whereNull('exited_at')
            ->update(['exited_at' => $now, 'updated_at' => $now]);
    }
}
