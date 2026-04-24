<?php

namespace Database\Factories;

use App\Models\User;
use App\Support\Recruitment\DefaultFormSchema;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\LiveHostRecruitmentCampaign>
 */
class LiveHostRecruitmentCampaignFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = $this->faker->unique()->sentence(3);

        return [
            'title' => $title,
            'slug' => Str::slug($title).'-'.$this->faker->unique()->numberBetween(1000, 9999),
            'description' => $this->faker->paragraph(),
            'status' => 'draft',
            'target_count' => $this->faker->optional()->numberBetween(1, 20),
            'opens_at' => null,
            'closes_at' => null,
            'created_by' => User::factory(),
            'form_schema' => DefaultFormSchema::get(),
        ];
    }

    public function open(): static
    {
        return $this->state(fn () => ['status' => 'open']);
    }
}
