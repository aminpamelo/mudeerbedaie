<?php

namespace Database\Factories;

use App\Models\LiveTimeSlot;
use App\Models\PlatformAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\LiveScheduleAssignment>
 */
class LiveScheduleAssignmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'platform_account_id' => PlatformAccount::factory(),
            'time_slot_id' => LiveTimeSlot::factory(),
            'live_host_id' => null,
            'day_of_week' => fake()->numberBetween(0, 6),
            'schedule_date' => null,
            'remarks' => null,
            'status' => 'scheduled',
            'is_template' => true,
            'created_by' => null,
        ];
    }

    public function template(): self
    {
        return $this->state(['is_template' => true, 'schedule_date' => null]);
    }

    public function forDate(?string $date = null): self
    {
        return $this->state([
            'is_template' => false,
            'schedule_date' => $date ?? fake()->dateTimeBetween('-1 month', '+1 month')->format('Y-m-d'),
        ]);
    }
}
