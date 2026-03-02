<?php

namespace Database\Factories;

use App\Models\ClassAssignmentApproval;
use App\Models\ClassModel;
use App\Models\ProductOrder;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ClassAssignmentApproval>
 */
class ClassAssignmentApprovalFactory extends Factory
{
    protected $model = ClassAssignmentApproval::class;

    public function definition(): array
    {
        return [
            'class_id' => ClassModel::factory(),
            'student_id' => Student::factory(),
            'product_order_id' => ProductOrder::factory(),
            'status' => 'pending',
            'enroll_with_subscription' => false,
            'assigned_by' => User::factory(),
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'approved',
            'approved_by' => User::factory(),
            'approved_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'rejected',
            'approved_by' => User::factory(),
            'approved_at' => now(),
            'notes' => $this->faker->sentence(),
        ]);
    }
}
