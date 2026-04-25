<?php

namespace Database\Factories;

use App\Models\LiveHostApplicant;
use App\Models\LiveHostRecruitmentStage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\LiveHostApplicantStage>
 */
class LiveHostApplicantStageFactory extends Factory
{
    public function definition(): array
    {
        return [
            'applicant_id' => LiveHostApplicant::factory(),
            'stage_id' => LiveHostRecruitmentStage::factory(),
            'assignee_id' => null,
            'due_at' => null,
            'stage_notes' => null,
            'entered_at' => now(),
            'exited_at' => null,
        ];
    }

    public function closed(): self
    {
        return $this->state(fn () => ['exited_at' => now()]);
    }

    public function dueAt(\DateTimeInterface $date): self
    {
        return $this->state(fn () => ['due_at' => $date]);
    }
}
