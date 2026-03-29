<?php

namespace Database\Factories;

use App\Models\DisciplinaryAction;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<DisciplinaryAction> */
class DisciplinaryActionFactory extends Factory
{
    protected $model = DisciplinaryAction::class;

    public function definition(): array
    {
        return [
            'reference_number' => DisciplinaryAction::generateReferenceNumber(),
            'employee_id' => Employee::factory(),
            'type' => $this->faker->randomElement(['verbal_warning', 'first_written', 'second_written', 'show_cause']),
            'reason' => $this->faker->paragraph(),
            'incident_date' => $this->faker->dateTimeBetween('-30 days', 'now'),
            'issued_date' => now(),
            'issued_by' => Employee::factory(),
            'response_required' => false,
            'status' => 'draft',
        ];
    }

    public function issued(): static
    {
        return $this->state(fn () => [
            'status' => 'issued',
            'issued_date' => now(),
        ]);
    }

    public function showCause(): static
    {
        return $this->state(fn () => [
            'type' => 'show_cause',
            'response_required' => true,
            'response_deadline' => now()->addDays(7),
            'status' => 'pending_response',
        ]);
    }
}
