<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\ExitChecklist;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ExitChecklist> */
class ExitChecklistFactory extends Factory
{
    protected $model = ExitChecklist::class;

    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'status' => 'in_progress',
        ];
    }
}
