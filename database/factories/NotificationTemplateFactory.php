<?php

namespace Database\Factories;

use App\Models\NotificationTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\NotificationTemplate>
 */
class NotificationTemplateFactory extends Factory
{
    protected $model = NotificationTemplate::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $type = $this->faker->randomElement([
            'session_reminder',
            'session_followup',
            'class_update',
            'enrollment_welcome',
            'class_completed',
        ]);

        return [
            'name' => $this->faker->sentence(3),
            'slug' => $this->faker->unique()->slug(),
            'type' => $type,
            'channel' => $this->faker->randomElement(['email', 'whatsapp', 'sms']),
            'subject' => $this->faker->sentence(),
            'content' => $this->faker->paragraph(),
            'language' => 'ms',
            'is_system' => false,
            'is_active' => true,
        ];
    }
}
