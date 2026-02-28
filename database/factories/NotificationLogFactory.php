<?php

namespace Database\Factories;

use App\Models\NotificationLog;
use App\Models\ScheduledNotification;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\NotificationLog>
 */
class NotificationLogFactory extends Factory
{
    protected $model = NotificationLog::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'scheduled_notification_id' => ScheduledNotification::factory(),
            'recipient_type' => 'student',
            'recipient_id' => Student::factory(),
            'channel' => 'email',
            'destination' => $this->faker->safeEmail(),
            'status' => 'pending',
            'message_id' => null,
            'error_message' => null,
            'sent_at' => null,
            'delivered_at' => null,
        ];
    }

    public function whatsapp(): static
    {
        return $this->state(fn (array $attributes) => [
            'channel' => 'whatsapp',
            'destination' => $this->faker->numerify('60#########'),
        ]);
    }

    public function sent(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'sent',
            'sent_at' => now(),
        ]);
    }

    public function delivered(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'delivered',
            'sent_at' => now()->subMinutes(5),
            'delivered_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
            'error_message' => $this->faker->sentence(),
        ]);
    }
}
