<?php

namespace Database\Factories;

use App\Models\WhatsAppConversation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\WhatsAppMessage>
 */
class WhatsAppMessageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'conversation_id' => WhatsAppConversation::factory(),
            'direction' => 'inbound',
            'type' => 'text',
            'body' => fake()->sentence(),
            'status' => 'delivered',
        ];
    }

    public function inbound(): static
    {
        return $this->state(fn (array $attributes) => ['direction' => 'inbound']);
    }

    public function outbound(): static
    {
        return $this->state(fn (array $attributes) => ['direction' => 'outbound']);
    }

    public function withWamid(): static
    {
        return $this->state(fn (array $attributes) => [
            'wamid' => 'wamid.'.fake()->uuid(),
        ]);
    }

    public function template(string $name = 'test_template'): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'template',
            'template_name' => $name,
            'direction' => 'outbound',
        ]);
    }
}
