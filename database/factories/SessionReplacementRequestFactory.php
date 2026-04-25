<?php

namespace Database\Factories;

use App\Models\LiveScheduleAssignment;
use App\Models\SessionReplacementRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SessionReplacementRequest>
 */
class SessionReplacementRequestFactory extends Factory
{
    public function definition(): array
    {
        return [
            'live_schedule_assignment_id' => LiveScheduleAssignment::factory(),
            'original_host_id' => User::factory(),
            'replacement_host_id' => null,
            'scope' => SessionReplacementRequest::SCOPE_ONE_DATE,
            'target_date' => now()->addDay()->toDateString(),
            'reason_category' => 'sick',
            'reason_note' => null,
            'status' => SessionReplacementRequest::STATUS_PENDING,
            'requested_at' => now(),
            'assigned_at' => null,
            'assigned_by_id' => null,
            'rejection_reason' => null,
            'expires_at' => now()->addHours(24),
            'live_session_id' => null,
        ];
    }

    public function pending(): self
    {
        return $this->state(['status' => SessionReplacementRequest::STATUS_PENDING]);
    }

    public function assigned(?User $replacement = null): self
    {
        return $this->state([
            'status' => SessionReplacementRequest::STATUS_ASSIGNED,
            'replacement_host_id' => $replacement?->id ?? User::factory(),
            'assigned_at' => now(),
            'assigned_by_id' => User::factory(),
        ]);
    }

    public function permanent(): self
    {
        return $this->state([
            'scope' => SessionReplacementRequest::SCOPE_PERMANENT,
            'target_date' => null,
        ]);
    }

    public function expired(): self
    {
        return $this->state([
            'status' => SessionReplacementRequest::STATUS_EXPIRED,
            'expires_at' => now()->subHour(),
        ]);
    }
}
