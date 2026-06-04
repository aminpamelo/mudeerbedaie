<?php

namespace Database\Factories;

use App\Models\LiveAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\LiveAccount>
 */
class LiveAccountFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $handle = $this->faker->unique()->userName();

        return [
            'creator_user_id' => (string) $this->faker->unique()->numerify('################'),
            'nickname' => $handle,
            'display_name' => $this->faker->name(),
            'normalized_handle' => LiveAccount::normalizeHandle($handle),
            'avatar_url' => null,
            'follower_count' => $this->faker->numberBetween(0, 500000),
            'is_active' => true,
            'needs_review' => false,
            'metadata' => null,
        ];
    }

    /**
     * An imported account that lacks a numeric creator id (CSV-only origin).
     */
    public function withoutCreatorId(): static
    {
        return $this->state(fn (array $attributes): array => [
            'creator_user_id' => null,
            'needs_review' => true,
        ]);
    }
}
