<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class ProductsAndOrdersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('🛍️  Seeding products and orders...');

        // Create warehouses
        $this->command->info('Creating warehouses...');
        $warehouses = \App\Models\Warehouse::factory(3)->create();
        $this->command->info('✅ Created 3 warehouses');

        // Create products
        $this->command->info('Creating products...');
        $products = \App\Models\Product::factory(30)->create();
        $this->command->info('✅ Created 30 products');

        // Create stock levels for products
        $this->command->info('Creating stock levels...');
        $stockCount = 0;
        foreach ($products as $product) {
            foreach ($warehouses as $warehouse) {
                \App\Models\StockLevel::factory()->create([
                    'product_id' => $product->id,
                    'warehouse_id' => $warehouse->id,
                    'quantity' => rand(0, 500),
                ]);
                $stockCount++;
            }
        }
        $this->command->info("✅ Created {$stockCount} stock level records");

        // Create product orders for students
        $this->command->info('Creating product orders...');

        $students = \App\Models\Student::all();

        if ($students->isEmpty()) {
            $this->command->warn('⚠️  No students found. Skipping order creation.');

            return;
        }

        $orderCount = 0;
        foreach ($students->random(min(30, $students->count())) as $student) {
            // Each student gets 1-3 orders
            $numberOfOrders = rand(1, 3);

            for ($i = 0; $i < $numberOfOrders; $i++) {
                $order = \App\Models\ProductOrder::factory()->create([
                    'student_id' => $student->id,
                    'customer_id' => $student->user_id,
                    'order_date' => now()->subDays(rand(1, 90)),
                    'status' => fake()->randomElement(['pending', 'confirmed', 'processing', 'shipped', 'delivered', 'delivered', 'delivered']), // 43% delivered
                ]);

                // Add 1-4 items to each order
                $numberOfItems = rand(1, 4);
                for ($j = 0; $j < $numberOfItems; $j++) {
                    \App\Models\ProductOrderItem::factory()->create([
                        'order_id' => $order->id,
                        'product_id' => $products->random()->id,
                        'warehouse_id' => $warehouses->random()->id,
                    ]);
                }

                $orderCount++;
            }
        }

        $this->command->info("✅ Created {$orderCount} product orders");
        $this->command->info('✨ Products and orders seeding completed!');
    }
}
