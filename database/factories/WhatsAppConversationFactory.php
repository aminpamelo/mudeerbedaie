<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\WhatsAppConversation>
 */
class WhatsAppConversationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'phone_number' => '60'.fake()->numerify('#########'),
            'contact_name' => fake()->name(),
            'status' => 'active',
            'unread_count' => 0,
            'is_service_window_open' => false,
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => ['status' => 'active']);
    }

    public function archived(): static
    {
        return $this->state(fn (array $attributes) => ['status' => 'archived']);
    }

    public function withUnread(int $count = 3): static
    {
        return $this->state(fn (array $attributes) => ['unread_count' => $count]);
    }

    public function withServiceWindow(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_service_window_open' => true,
            'service_window_expires_at' => now()->addHours(24),
        ]);
    }

    public function withExpiredServiceWindow(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_service_window_open' => true,
            'service_window_expires_at' => now()->subHour(),
        ]);
    }
}
