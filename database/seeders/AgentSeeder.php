<?php

namespace Database\Seeders;

use App\Models\Agent;
use App\Models\KedaiBukuPricing;
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

        // Create Bookstores (type: bookstore)
        $bookstores = [
            [
                'agent_code' => 'KB0001',
                'name' => 'Kedai Buku Harmoni',
                'type' => 'bookstore',
                'pricing_tier' => 'standard',
                'commission_rate' => 10.00,
                'credit_limit' => 10000.00,
                'consignment_enabled' => true,
                'company_name' => 'Harmoni Books Enterprise',
                'registration_number' => 'KL0789012-B',
                'contact_person' => 'Nurul Huda binti Razak',
                'email' => 'harmoni.books@example.com',
                'phone' => '03-21234567',
                'address' => [
                    'street' => 'No. 45, Jalan Tuanku Abdul Rahman',
                    'city' => 'Kuala Lumpur',
                    'state' => 'Wilayah Persekutuan',
                    'postal_code' => '50100',
                    'country' => 'Malaysia',
                ],
                'payment_terms' => 'Net 30 days',
                'bank_details' => [
                    'bank_name' => 'Maybank',
                    'account_number' => '5678901234',
                    'account_name' => 'Harmoni Books Enterprise',
                ],
                'is_active' => true,
                'notes' => 'Standard tier bookstore - 10% discount',
            ],
            [
                'agent_code' => 'KB0002',
                'name' => 'Pustaka Gemilang',
                'type' => 'bookstore',
                'pricing_tier' => 'premium',
                'commission_rate' => 15.00,
                'credit_limit' => 20000.00,
                'consignment_enabled' => true,
                'company_name' => 'Pustaka Gemilang Sdn Bhd',
                'registration_number' => '202001045678 (1456789-P)',
                'contact_person' => 'Muhammad Hafiz bin Yusof',
                'email' => 'gemilang@example.com',
                'phone' => '03-55678901',
                'address' => [
                    'street' => 'Lot 12, Kompleks PKNS',
                    'city' => 'Shah Alam',
                    'state' => 'Selangor',
                    'postal_code' => '40000',
                    'country' => 'Malaysia',
                ],
                'payment_terms' => 'Net 14 days',
                'bank_details' => [
                    'bank_name' => 'Bank Rakyat',
                    'account_number' => '2101234567',
                    'account_name' => 'Pustaka Gemilang Sdn Bhd',
                ],
                'is_active' => true,
                'notes' => 'Premium tier bookstore - 15% discount - High volume buyer',
            ],
            [
                'agent_code' => 'KB0003',
                'name' => 'Kedai Buku Bestari',
                'type' => 'bookstore',
                'pricing_tier' => 'vip',
                'commission_rate' => 20.00,
                'credit_limit' => 50000.00,
                'consignment_enabled' => true,
                'company_name' => 'Bestari Books Sdn Bhd',
                'registration_number' => '201501067890 (1167890-M)',
                'contact_person' => 'Dr. Zainal Abidin',
                'email' => 'bestari.books@example.com',
                'phone' => '03-89012345',
                'address' => [
                    'street' => 'No. 100, Jalan Universiti',
                    'city' => 'Petaling Jaya',
                    'state' => 'Selangor',
                    'postal_code' => '46200',
                    'country' => 'Malaysia',
                ],
                'payment_terms' => 'Net 45 days',
                'bank_details' => [
                    'bank_name' => 'CIMB Bank',
                    'account_number' => '7612345678',
                    'account_name' => 'Bestari Books Sdn Bhd',
                ],
                'is_active' => true,
                'notes' => 'VIP tier bookstore - 20% discount - Top performer',
            ],
            [
                'agent_code' => 'KB0004',
                'name' => 'Kedai Buku Ilham',
                'type' => 'bookstore',
                'pricing_tier' => 'standard',
                'commission_rate' => 10.00,
                'credit_limit' => 8000.00,
                'consignment_enabled' => false,
                'company_name' => 'Ilham Enterprise',
                'registration_number' => 'PG0345678-C',
                'contact_person' => 'Lee Mei Fong',
                'email' => 'ilham.books@example.com',
                'phone' => '04-2567890',
                'address' => [
                    'street' => 'No. 23, Jalan Burma',
                    'city' => 'Georgetown',
                    'state' => 'Pulau Pinang',
                    'postal_code' => '10050',
                    'country' => 'Malaysia',
                ],
                'payment_terms' => 'COD',
                'bank_details' => [
                    'bank_name' => 'Public Bank',
                    'account_number' => '3234567890',
                    'account_name' => 'Ilham Enterprise',
                ],
                'is_active' => true,
                'notes' => 'Penang area bookstore',
            ],
            [
                'agent_code' => 'KB0005',
                'name' => 'Kedai Buku Wawasan',
                'type' => 'bookstore',
                'pricing_tier' => 'premium',
                'commission_rate' => 15.00,
                'credit_limit' => 15000.00,
                'consignment_enabled' => true,
                'company_name' => 'Wawasan Bookstore Sdn Bhd',
                'registration_number' => '201801089012 (1289012-X)',
                'contact_person' => 'Amirah binti Kamal',
                'email' => 'wawasan@example.com',
                'phone' => '09-7456789',
                'address' => [
                    'street' => 'No. 56, Jalan Sultan Mahmud',
                    'city' => 'Kuala Terengganu',
                    'state' => 'Terengganu',
                    'postal_code' => '20400',
                    'country' => 'Malaysia',
                ],
                'payment_terms' => 'Net 21 days',
                'bank_details' => [
                    'bank_name' => 'Bank Islam',
                    'account_number' => '1456789012',
                    'account_name' => 'Wawasan Bookstore Sdn Bhd',
                ],
                'is_active' => true,
                'notes' => 'East coast premium bookstore',
            ],
        ];

        // Create all agents
        $allAgents = array_merge($agents, $companies, $bookstores);
        $createdAgents = [];

        foreach ($allAgents as $agentData) {
            $createdAgents[] = Agent::updateOrCreate(
                ['agent_code' => $agentData['agent_code']],
                $agentData
            );
        }

        $this->command->info('Created '.count($allAgents).' agents (agents, companies, bookstores)');

        // Create orders for agents
        $this->createAgentOrders($createdAgents);

        // Create custom pricing for bookstores
        $this->createBookstorePricing();
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

                    // Apply tier discount for bookstores
                    if ($agent->isBookstore()) {
                        $unitPrice = $agent->calculateTierPrice($unitPrice);
                    }

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
     * Create custom pricing for bookstore agents.
     */
    private function createBookstorePricing(): void
    {
        $bookstores = Agent::bookstores()->get();
        $products = Product::take(5)->get();

        if ($products->isEmpty() || $bookstores->isEmpty()) {
            $this->command->warn('No products or bookstores found. Skipping custom pricing creation.');

            return;
        }

        $pricingCount = 0;

        foreach ($bookstores as $bookstore) {
            // Create custom pricing for 2-4 products per bookstore
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

                    KedaiBukuPricing::updateOrCreate(
                        [
                            'agent_id' => $bookstore->id,
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

        $this->command->info('Created '.$pricingCount.' custom pricing entries for bookstores');
    }
}
