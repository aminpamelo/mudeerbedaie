<?php

namespace Database\Factories;

use App\Models\LiveSession;
use App\Models\LiveSessionVerificationEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class LiveSessionVerificationEventFactory extends Factory
{
    protected $model = LiveSessionVerificationEvent::class;

    public function definition(): array
    {
        return [
            'live_session_id' => LiveSession::factory(),
            'actual_live_record_id' => null,
            'action' => 'verify_link',
            'user_id' => User::factory(),
            'gmv_snapshot' => fake()->randomFloat(2, 0, 5000),
            'notes' => null,
        ];
    }
}
