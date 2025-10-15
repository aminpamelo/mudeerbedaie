<?php

namespace Database\Factories;

use App\Models\ClassDocumentShipment;
use App\Models\ClassModel;
use App\Models\Product;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

class ClassDocumentShipmentFactory extends Factory
{
    protected $model = ClassDocumentShipment::class;

    public function definition(): array
    {
        $periodStart = fake()->dateTimeBetween('-1 month', '+1 month');
        $periodEnd = (clone $periodStart)->modify('+1 month');

        return [
            'class_id' => ClassModel::factory(),
            'product_id' => Product::factory(),
            'shipment_number' => 'SHIP-'.date('Ymd').'-'.strtoupper(fake()->lexify('??????')),
            'period_label' => date('F Y', $periodStart->getTimestamp()),
            'period_start_date' => $periodStart,
            'period_end_date' => $periodEnd,
            'status' => 'pending',
            'total_recipients' => fake()->numberBetween(1, 20),
            'quantity_per_student' => 1,
            'total_quantity' => fake()->numberBetween(1, 20),
            'warehouse_id' => Warehouse::factory(),
            'total_cost' => fake()->randomFloat(2, 50, 500),
            'shipping_cost' => fake()->randomFloat(2, 10, 100),
            'scheduled_at' => $periodStart,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
        ]);
    }

    public function processing(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'processing',
            'processed_at' => now(),
        ]);
    }

    public function shipped(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'shipped',
            'processed_at' => now()->subDays(2),
            'shipped_at' => now(),
        ]);
    }

    public function delivered(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'delivered',
            'processed_at' => now()->subDays(5),
            'shipped_at' => now()->subDays(3),
            'delivered_at' => now(),
        ]);
    }
}
