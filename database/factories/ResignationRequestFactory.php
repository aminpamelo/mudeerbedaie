<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\ResignationRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ResignationRequest> */
class ResignationRequestFactory extends Factory
{
    protected $model = ResignationRequest::class;

    public function definition(): array
    {
        $submittedDate = now();
        $noticePeriod = 30;

        return [
            'employee_id' => Employee::factory(),
            'submitted_date' => $submittedDate,
            'reason' => $this->faker->paragraph(),
            'notice_period_days' => $noticePeriod,
            'last_working_date' => $submittedDate->copy()->addDays($noticePeriod),
            'status' => 'pending',
        ];
    }

    public function approved(): static
    {
        return $this->state(fn () => [
            'status' => 'approved',
            'approved_by' => Employee::factory(),
            'approved_at' => now(),
            'final_last_date' => now()->addDays(30),
        ]);
    }
}
