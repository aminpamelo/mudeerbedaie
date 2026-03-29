<?php

namespace Database\Factories;

use App\Models\Certification;
use App\Models\Employee;
use App\Models\EmployeeCertification;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<EmployeeCertification> */
class EmployeeCertificationFactory extends Factory
{
    protected $model = EmployeeCertification::class;

    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'certification_id' => Certification::factory(),
            'certificate_number' => strtoupper($this->faker->bothify('CERT-####')),
            'issued_date' => $this->faker->dateTimeBetween('-2 years', 'now'),
            'expiry_date' => $this->faker->dateTimeBetween('now', '+2 years'),
            'status' => 'active',
        ];
    }

    public function expiringSoon(): static
    {
        return $this->state(fn () => [
            'expiry_date' => now()->addDays(30),
        ]);
    }
}
