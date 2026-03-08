<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\WhatsAppTemplate>
 */
class WhatsAppTemplateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => str_replace('-', '_', fake()->unique()->slug(2)),
            'language' => 'ms',
            'category' => 'utility',
            'status' => 'APPROVED',
            'components' => [
                [
                    'type' => 'BODY',
                    'text' => fake()->sentence(),
                ],
            ],
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => ['status' => 'APPROVED']);
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => ['status' => 'PENDING']);
    }

    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => ['status' => 'REJECTED']);
    }

    public function marketing(): static
    {
        return $this->state(fn (array $attributes) => ['category' => 'marketing']);
    }

    public function utility(): static
    {
        return $this->state(fn (array $attributes) => ['category' => 'utility']);
    }

    public function authentication(): static
    {
        return $this->state(fn (array $attributes) => ['category' => 'authentication']);
    }

    public function metaSynced(): static
    {
        return $this->state(fn (array $attributes) => [
            'meta_template_id' => (string) fake()->unique()->numberBetween(100000, 999999),
            'last_synced_at' => now(),
        ]);
    }
}
