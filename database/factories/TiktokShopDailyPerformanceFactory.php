<?php

namespace Database\Factories;

use App\Models\PlatformAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\TiktokShopDailyPerformance>
 */
class TiktokShopDailyPerformanceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $gmv = fake()->randomFloat(2, 500, 20000);
        $orders = fake()->numberBetween(5, 200);

        return [
            'platform_account_id' => PlatformAccount::factory(),
            'date' => fake()->dateTimeBetween('-30 days', '-1 day')->format('Y-m-d'),
            'gmv' => $gmv,
            'gmv_live' => round($gmv * 0.9, 2),
            'gmv_video' => round($gmv * 0.05, 2),
            'gmv_product_card' => round($gmv * 0.05, 2),
            'orders' => $orders,
            'sku_orders' => $orders,
            'units_sold' => $orders,
            'buyers' => $orders,
            'avg_order_value' => round($gmv / $orders, 2),
            'currency' => 'MYR',
            'fetched_at' => now(),
        ];
    }
}
