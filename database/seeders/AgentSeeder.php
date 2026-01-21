<?php

namespace Database\Seeders;

use App\Models\Agent;
use App\Models\AgentPricing;
use App\Models\Product;
use App\Models\ProductOrder;
use App\Models\ProductOrderItem;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class AgentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create Agents (type: agent)
        $agents = [
            [
                'agent_code' => 'AGT0001',
                'name' => 'Kedai Buku Al-Hidayah',
                'type' => 'agent',
                'pricing_tier' => 'standard',
                'commission_rate' => 5.00,
                'credit_limit' => 5000.00,
                'consignment_enabled' => false,
                'company_name' => 'Al-Hidayah Enterprise',
                'registration_number' => 'SA0123456-A',
                'contact_person' => 'Ahmad bin Abdullah',
                'email' => 'alhidayah@example.com',
                'phone' => '012-3456789',
                'address' => [
                    'street' => 'No. 15, Jalan Masjid India',
                    'city' => 'Kuala Lumpur',
                    'state' => 'Wilayah Persekutuan',
                    'postal_code' => '50100',
                    'country' => 'Malaysia',
                ],
                'payment_terms' => 'Net 30 days',
                'bank_details' => [
                    'bank_name' => 'Maybank',
                    'account_number' => '1234567890',
                    'account_name' => 'Al-Hidayah Enterprise',
                ],
                'is_active' => true,
                'notes' => 'Main distributor for religious books in KL area',
            ],
            [
                'agent_code' => 'AGT0002',
                'name' => 'Kedai Buku Nur Iman',
                'type' => 'agent',
                'pricing_tier' => 'premium',
                'commission_rate' => 7.50,
                'credit_limit' => 8000.00,
                'consignment_enabled' => true,
                'company_name' => null,
                'registration_number' => null,
                'contact_person' => 'Mohd Faiz bin Ismail',
                'email' => 'nuriman.books@example.com',
                'phone' => '019-8765432',
                'address' => [
                    'street' => 'No. 8, Jalan Sultan Ismail',
                    'city' => 'Kota Bharu',
                    'state' => 'Kelantan',
                    'postal_code' => '15000',
                    'country' => 'Malaysia',
                ],
                'payment_terms' => 'COD',
                'bank_details' => [
                    'bank_name' => 'Bank Islam',
                    'account_number' => '14012345678',
                    'account_name' => 'Mohd Faiz bin Ismail',
                ],
                'is_active' => true,
                'notes' => 'East coast region distributor',
            ],
            [
                'agent_code' => 'AGT0003',
                'name' => 'Perpustakaan Mini Johor',
                'type' => 'agent',
                'pricing_tier' => 'standard',
                'commission_rate' => 5.00,
                'credit_limit' => 3000.00,
                'consignment_enabled' => false,
                'company_name' => 'PMJ Enterprise',
                'registration_number' => 'JH0456789-D',
                'contact_person' => 'Lim Wei Ming',
                'email' => 'pmj.johor@example.com',
                'phone' => '07-2345678',
                'address' => [
                    'street' => 'No. 22, Jalan Dhoby',
                    'city' => 'Johor Bahru',
                    'state' => 'Johor',
                    'postal_code' => '80000',
                    'country' => 'Malaysia',
                ],
                'payment_terms' => 'Net 7 days',
                'bank_details' => [
                    'bank_name' => 'Public Bank',
                    'account_number' => '3123456789',
                    'account_name' => 'PMJ Enterprise',
                ],
                'is_active' => true,
                'notes' => 'Southern region main agent',
            ],
        ];

        // Create Companies (type: company)
        $companies = [
            [
                'agent_code' => 'AGT0004',
                'name' => 'Pustaka Ilmu Sdn Bhd',
                'type' => 'company',
                'pricing_tier' => 'premium',
                'commission_rate' => 8.00,
                'credit_limit' => 15000.00,
                'consignment_enabled' => true,
                'company_name' => 'Pustaka Ilmu Sdn Bhd',
                'registration_number' => '201901012345 (1234567-K)',
                'contact_person' => 'Siti Aminah binti Hassan',
                'email' => 'pustakailmu@example.com',
                'phone' => '03-78901234',
                'address' => [
                    'street' => 'Lot 5, Jalan SS15/4',
                    'city' => 'Subang Jaya',
                    'state' => 'Selangor',
                    'postal_code' => '47500',
                    'country' => 'Malaysia',
                ],
                'payment_terms' => 'Net 14 days',
                'bank_details' => [
                    'bank_name' => 'CIMB Bank',
                    'account_number' => '8012345678',
                    'account_name' => 'Pustaka Ilmu Sdn Bhd',
                ],
                'is_active' => true,
                'notes' => 'Educational books specialist',
            ],
            [
                'agent_code' => 'AGT0005',
                'name' => 'Buku Berkualiti Trading',
                'type' => 'company',
                'pricing_tier' => 'vip',
                'commission_rate' => 10.00,
                'credit_limit' => 25000.00,
                'consignment_enabled' => true,
                'company_name' => 'Buku Berkualiti Trading Sdn Bhd',
                'registration_number' => '200801023456 (823456-W)',
                'contact_person' => 'Tan Mei Ling',
                'email' => 'bbt.trading@example.com',
                'phone' => '04-2612345',
                'address' => [
                    'street' => '168, Lebuh Chulia',
                    'city' => 'Georgetown',
                    'state' => 'Pulau Pinang',
                    'postal_code' => '10200',
                    'country' => 'Malaysia',
                ],
                'payment_terms' => 'Net 30 days',
                'bank_details' => [
                    'bank_name' => 'Hong Leong Bank',
                    'account_number' => '23412345678',
                    'account_name' => 'Buku Berkualiti Trading Sdn Bhd',
                ],
                'is_active' => true,
                'notes' => 'Northern region wholesaler - large volume orders',
            ],
        ];

        // Create all agents
        $allAgents = array_merge($agents, $companies);
        $createdAgents = [];

        foreach ($allAgents as $agentData) {
            $createdAgents[] = Agent::updateOrCreate(
                ['agent_code' => $agentData['agent_code']],
                $agentData
            );
        }

        $this->command->info('Created '.count($allAgents).' agents (agents and companies)');

        // Create orders for agents
        $this->createAgentOrders($createdAgents);

        // Create custom pricing for all agents
        $this->createAgentPricing($createdAgents);
    }

    /**
     * Create sample orders for agents across different months.
     */
    private function createAgentOrders(array $agents): void
    {
        $products = Product::take(10)->get();

        if ($products->isEmpty()) {
            $this->command->warn('No products found. Skipping order creation.');

            return;
        }

        $statuses = ['pending', 'confirmed', 'processing', 'shipped', 'delivered', 'cancelled'];
        $orderCount = 0;

        foreach ($agents as $agent) {
            // Create 3-8 orders per agent spread across the last 12 months
            $numOrders = rand(3, 8);

            for ($i = 0; $i < $numOrders; $i++) {
                // Random date within the last 12 months
                $orderDate = Carbon::now()->subMonths(rand(0, 11))->subDays(rand(0, 28));

                // Determine status based on order age
                $daysSinceOrder = $orderDate->diffInDays(now());
                if ($daysSinceOrder > 30) {
                    $status = rand(0, 10) > 2 ? 'delivered' : 'cancelled';
                } elseif ($daysSinceOrder > 14) {
                    $status = $statuses[rand(3, 5)]; // shipped, delivered, or cancelled
                } elseif ($daysSinceOrder > 7) {
                    $status = $statuses[rand(2, 4)]; // processing, shipped, or delivered
                } else {
                    $status = $statuses[rand(0, 3)]; // pending, confirmed, processing, or shipped
                }

                // Create order
                $subtotal = 0;
                $orderItems = [];
                $numItems = rand(1, 4);
                $selectedProducts = $products->random(min($numItems, $products->count()));

                foreach ($selectedProducts as $product) {
                    $quantity = rand(1, 10);
                    $unitPrice = $product->base_price ?? rand(20, 100);

                    // Apply tier discount
                    $unitPrice = $agent->calculateTierPrice($unitPrice);

                    $itemTotal = $quantity * $unitPrice;
                    $subtotal += $itemTotal;

                    $orderItems[] = [
                        'product_id' => $product->id,
                        'product_name' => $product->name,
                        'sku' => $product->sku ?? 'SKU-'.$product->id,
                        'quantity_ordered' => $quantity,
                        'unit_price' => $unitPrice,
                        'total_price' => $itemTotal,
                    ];
                }

                $shippingCost = rand(0, 1) ? rand(10, 30) : 0;
                $totalAmount = $subtotal + $shippingCost;

                $order = ProductOrder::create([
                    'order_number' => 'AGT-'.strtoupper(substr(md5(uniqid()), 0, 8)),
                    'agent_id' => $agent->id,
                    'status' => $status,
                    'order_type' => 'agent',
                    'currency' => 'MYR',
                    'subtotal' => $subtotal,
                    'shipping_cost' => $shippingCost,
                    'tax_amount' => 0,
                    'total_amount' => $totalAmount,
                    'order_date' => $orderDate,
                    'confirmed_at' => in_array($status, ['confirmed', 'processing', 'shipped', 'delivered']) ? $orderDate->copy()->addHours(rand(1, 24)) : null,
                    'shipped_at' => in_array($status, ['shipped', 'delivered']) ? $orderDate->copy()->addDays(rand(1, 3)) : null,
                    'delivered_at' => $status === 'delivered' ? $orderDate->copy()->addDays(rand(3, 7)) : null,
                    'cancelled_at' => $status === 'cancelled' ? $orderDate->copy()->addDays(rand(1, 5)) : null,
                    'internal_notes' => 'Seeded order for '.$agent->name,
                ]);

                // Create order items
                foreach ($orderItems as $item) {
                    ProductOrderItem::create([
                        'order_id' => $order->id,
                        'product_id' => $item['product_id'],
                        'product_name' => $item['product_name'],
                        'sku' => $item['sku'],
                        'quantity_ordered' => $item['quantity_ordered'],
                        'unit_price' => $item['unit_price'],
                        'total_price' => $item['total_price'],
                    ]);
                }

                $orderCount++;
            }
        }

        $this->command->info('Created '.$orderCount.' agent orders');
    }

    /**
     * Create custom pricing for all agents.
     */
    private function createAgentPricing(array $agents): void
    {
        $products = Product::take(5)->get();

        if ($products->isEmpty()) {
            $this->command->warn('No products found. Skipping custom pricing creation.');

            return;
        }

        $pricingCount = 0;

        foreach ($agents as $agent) {
            // Create custom pricing for 2-4 products per agent
            $numPricing = rand(2, min(4, $products->count()));
            $selectedProducts = $products->random($numPricing);

            foreach ($selectedProducts as $product) {
                $basePrice = $product->base_price ?? 50;

                // Create quantity-based pricing tiers
                $pricingTiers = [
                    ['min_quantity' => 1, 'discount' => rand(5, 10)],
                    ['min_quantity' => 10, 'discount' => rand(12, 18)],
                    ['min_quantity' => 50, 'discount' => rand(20, 25)],
                ];

                foreach ($pricingTiers as $tier) {
                    $customPrice = $basePrice * (1 - ($tier['discount'] / 100));

                    AgentPricing::updateOrCreate(
                        [
                            'agent_id' => $agent->id,
                            'product_id' => $product->id,
                            'min_quantity' => $tier['min_quantity'],
                        ],
                        [
                            'price' => round($customPrice, 2),
                            'is_active' => true,
                        ]
                    );

                    $pricingCount++;
                }
            }
        }

        $this->command->info('Created '.$pricingCount.' custom pricing entries for all agents');
    }
}
