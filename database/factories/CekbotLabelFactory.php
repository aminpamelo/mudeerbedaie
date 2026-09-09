<?php

namespace Database\Factories;

use App\Models\CekbotLabel;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CekbotLabel>
 */
class CekbotLabelFactory extends Factory
{
    protected $model = CekbotLabel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = $this->faker->unique()->word();

        return [
            'key' => Str::slug($name),
            'name' => Str::title($name),
            'color' => $this->faker->randomElement(['blue', 'amber', 'green', 'red', 'violet', 'sky', 'rose']),
            'sort_order' => 0,
        ];
    }
}
