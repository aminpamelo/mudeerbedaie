<?php

namespace Database\Seeders;

use App\Models\Funnel;
use App\Models\FunnelOrder;
use App\Models\ProductOrder;
use App\Models\User;
use App\Services\Fighter\FighterProvisioner;
use Illuminate\Database\Seeder;

class FighterDemoDataSeeder extends Seeder
{
    /**
     * Populate the fighter workspace with demo funnels and orders so the
     * Dashboard, Performance and Orders pages have something to show.
     *
     * Every order is tagged with the fighter's sales-source segment (the same
     * way the app tags real funnel/POS orders) so it appears in the fighter's
     * read-only Orders feed.
     */
    public function run(): void
    {
        $fighter = User::where('role', 'fighter')->orderBy('id')->first();

        if (! $fighter) {
            $this->command->warn('No fighter user found. Run FighterSeeder first.');

            return;
        }

        $segment = app(FighterProvisioner::class)->ensureSalesSource($fighter);

        $funnels = collect([
            'Ramadan Skincare Bundle',
            'Weekend Flash Sale',
        ])->map(fn (string $name): Funnel => Funnel::factory()->published()->create([
            'user_id' => $fighter->id,
            'name' => $name,
        ]));

        $this->command->info("Created {$funnels->count()} funnels for {$fighter->name}.");

        $funnelOrders = 0;
        foreach ($funnels as $funnel) {
            foreach (range(1, 6) as $i) {
                $total = fake()->randomFloat(2, 59, 349);
                $status = fake()->randomElement(['processing', 'shipped', 'delivered']);
                $shipped = in_array($status, ['shipped', 'delivered'], true);
                $awb = $shipped ? (string) fake()->numerify('6321########') : null;

                $order = ProductOrder::factory()->create([
                    'sales_source_id' => $segment->id,
                    'source' => 'funnel',
                    'status' => $status,
                    'payment_status' => 'paid',
                    'currency' => 'MYR',
                    'subtotal' => $total,
                    'shipping_cost' => 0,
                    'total_amount' => $total,
                    'shipping_provider' => $shipped ? 'easyparcel' : null,
                    'tracking_id' => $awb,
                    'shipped_at' => $shipped ? ($date = fake()->dateTimeBetween('-25 days', 'now')) : null,
                    'metadata' => $shipped ? ['shipping_tracking_url' => 'https://easyparcel.com/my/en/track/details/?awb_no='.$awb] : null,
                    'order_date' => $date = fake()->dateTimeBetween('-25 days', 'now'),
                    'created_at' => $date,
                    'updated_at' => $date,
                ]);

                FunnelOrder::factory()->create([
                    'funnel_id' => $funnel->id,
                    'session_id' => null,
                    'step_id' => null,
                    'product_order_id' => $order->id,
                    'order_type' => 'main',
                    'funnel_revenue' => $total,
                    'created_at' => $date,
                    'updated_at' => $date,
                ]);

                $funnelOrders++;
            }
        }

        $manualOrders = 4;
        foreach (range(1, $manualOrders) as $i) {
            $total = fake()->randomFloat(2, 39, 199);
            $status = fake()->randomElement(['pending', 'processing', 'delivered']);
            $shipped = $status === 'delivered';

            ProductOrder::factory()->create([
                'sales_source_id' => $segment->id,
                'source' => 'pos',
                'status' => $status,
                'payment_status' => fake()->randomElement(['paid', 'pending', 'failed']),
                'currency' => 'MYR',
                'subtotal' => $total,
                'shipping_cost' => 0,
                'total_amount' => $total,
                // Manual orders often carry only an AWB with no recorded courier —
                // the Orders feed falls back to EasyParcel's universal tracker.
                'shipping_provider' => null,
                'tracking_id' => $shipped ? (string) fake()->numerify('6321########') : null,
                'shipped_at' => $shipped ? fake()->dateTimeBetween('-15 days', 'now') : null,
                'order_date' => $date = fake()->dateTimeBetween('-15 days', 'now'),
                'created_at' => $date,
                'updated_at' => $date,
            ]);
        }

        $this->command->info("Created {$funnelOrders} funnel orders and {$manualOrders} manual orders (segment \"{$segment->name}\").");
    }
}
