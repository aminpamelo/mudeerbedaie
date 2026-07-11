<?php

namespace Database\Factories;

use App\Models\LiveAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LiveAccount>
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
            // A factory account represents a clean, real creator — i.e. the
            // shop's own linked TikTok Shop account (the timetable default).
            'account_type' => LiveAccount::TYPE_LINKED,
            'metadata' => null,
        ];
    }

    /**
     * An imported account that lacks a numeric creator id (CSV-only origin).
     * Treated as not-yet-classified, so it stays off the timetable by default.
     */
    public function withoutCreatorId(): static
    {
        return $this->state(fn (array $attributes): array => [
            'creator_user_id' => null,
            'needs_review' => true,
            'account_type' => LiveAccount::TYPE_UNKNOWN,
        ]);
    }

    public function linked(): static
    {
        return $this->state(fn (array $attributes): array => [
            'account_type' => LiveAccount::TYPE_LINKED,
        ]);
    }

    public function affiliate(): static
    {
        return $this->state(fn (array $attributes): array => [
            'account_type' => LiveAccount::TYPE_AFFILIATE,
        ]);
    }

    public function unknown(): static
    {
        return $this->state(fn (array $attributes): array => [
            'account_type' => LiveAccount::TYPE_UNKNOWN,
        ]);
    }
}
