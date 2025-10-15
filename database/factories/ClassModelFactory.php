<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\Product;
use App\Models\Teacher;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ClassModel>
 */
class ClassModelFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'course_id' => Course::factory(),
            'teacher_id' => Teacher::factory(),
            'title' => fake()->sentence(3),
            'description' => fake()->paragraph(),
            'date_time' => fake()->dateTimeBetween('+1 week', '+3 months'),
            'duration_minutes' => fake()->randomElement([60, 90, 120]),
            'class_type' => fake()->randomElement(['lecture', 'tutorial', 'lab']),
            'max_capacity' => fake()->numberBetween(10, 50),
            'location' => fake()->randomElement(['Room A', 'Room B', 'Online', 'Lab 1']),
            'meeting_url' => fake()->url(),
            'whatsapp_group_link' => fake()->url(),
            'teacher_rate' => fake()->randomFloat(2, 50, 200),
            'rate_type' => fake()->randomElement(['hourly', 'per_session', 'flat']),
            'commission_type' => fake()->randomElement(['percentage', 'fixed']),
            'commission_value' => fake()->randomFloat(2, 0, 50),
            'status' => 'scheduled',
            'notes' => fake()->optional()->paragraph(),
            'enable_document_shipment' => false,
            'shipment_frequency' => null,
            'shipment_start_date' => null,
            'shipment_product_id' => null,
            'shipment_warehouse_id' => null,
            'shipment_quantity_per_student' => null,
            'shipment_notes' => null,
        ];
    }

    public function withShipment(): static
    {
        return $this->state(fn (array $attributes) => [
            'enable_document_shipment' => true,
            'shipment_frequency' => 'monthly',
            'shipment_start_date' => now(),
            'shipment_product_id' => Product::factory(),
            'shipment_warehouse_id' => Warehouse::factory(),
            'shipment_quantity_per_student' => 1,
        ]);
    }

    public function scheduled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'scheduled',
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'cancelled',
        ]);
    }
}
