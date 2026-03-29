<?php

namespace Database\Factories;

use App\Models\LetterTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<LetterTemplate> */
class LetterTemplateFactory extends Factory
{
    protected $model = LetterTemplate::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->sentence(3),
            'type' => $this->faker->randomElement(['verbal_warning', 'first_written', 'second_written', 'show_cause', 'termination', 'offer_letter', 'resignation_acceptance']),
            'content' => '<p>Dear {{employee_name}},</p><p>{{reason}}</p>',
            'is_active' => true,
        ];
    }
}
