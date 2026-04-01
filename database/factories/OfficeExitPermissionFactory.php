<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\OfficeExitPermission;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\OfficeExitPermission>
 */
class OfficeExitPermissionFactory extends Factory
{
    protected $model = OfficeExitPermission::class;

    public function definition(): array
    {
        return [
            'permission_number' => $this->faker->unique()->numerify('OEP-202604-####'),
            'employee_id' => Employee::factory(),
            'exit_date' => now()->addDay()->toDateString(),
            'exit_time' => '14:00:00',
            'return_time' => '16:00:00',
            'errand_type' => fake()->randomElement(['company', 'personal']),
            'purpose' => fake()->sentence(15),
            'addressed_to' => fake()->name(),
            'status' => 'pending',
        ];
    }
}
