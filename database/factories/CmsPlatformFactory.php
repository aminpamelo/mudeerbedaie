<?php

namespace Database\Factories;

use App\Models\CmsPlatform;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CmsPlatform>
 */
class CmsPlatformFactory extends Factory
{
    protected $model = CmsPlatform::class;

    public function definition(): array
    {
        return [
            'key' => $this->faker->unique()->slug(2),
            'name' => $this->faker->words(2, true),
            'icon' => null,
            'sort_order' => $this->faker->numberBetween(0, 100),
            'is_enabled' => true,
        ];
    }

    public function disabled(): self
    {
        return $this->state(fn () => ['is_enabled' => false]);
    }
}
