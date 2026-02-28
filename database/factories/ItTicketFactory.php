<?php

namespace Database\Factories;

use App\Models\ItTicket;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ItTicketFactory extends Factory
{
    protected $model = ItTicket::class;

    public function definition(): array
    {
        return [
            'ticket_number' => ItTicket::generateTicketNumber(),
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'type' => fake()->randomElement(ItTicket::types()),
            'priority' => fake()->randomElement(ItTicket::priorities()),
            'status' => 'backlog',
            'position' => 0,
            'reporter_id' => User::factory(),
            'assignee_id' => null,
            'due_date' => fake()->optional()->dateTimeBetween('now', '+30 days'),
        ];
    }

    public function bug(): static
    {
        return $this->state(fn (array $attributes) => ['type' => 'bug']);
    }

    public function feature(): static
    {
        return $this->state(fn (array $attributes) => ['type' => 'feature']);
    }

    public function urgent(): static
    {
        return $this->state(fn (array $attributes) => ['priority' => 'urgent']);
    }

    public function assigned(User $user): static
    {
        return $this->state(fn (array $attributes) => ['assignee_id' => $user->id]);
    }

    public function withStatus(string $status): static
    {
        return $this->state(fn (array $attributes) => ['status' => $status]);
    }

    public function done(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'done',
            'completed_at' => now(),
        ]);
    }
}
