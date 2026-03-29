<?php

namespace Database\Factories;

use App\Models\Certification;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Certification> */
class CertificationFactory extends Factory
{
    protected $model = Certification::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->sentence(3),
            'issuing_body' => $this->faker->company(),
            'validity_months' => $this->faker->randomElement([12, 24, 36]),
            'is_active' => true,
        ];
    }
}
