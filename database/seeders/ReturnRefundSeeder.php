<?php

namespace Database\Seeders;

use App\Models\Package;
use App\Models\ProductOrder;
use App\Models\ReturnRefund;
use App\Models\User;
use Illuminate\Database\Seeder;

class ReturnRefundSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $orders = ProductOrder::limit(10)->get();
        $packages = Package::limit(5)->get();
        $customers = User::whereIn('role', ['student', 'user'])->limit(10)->get();
        $admin = User::where('role', 'admin')->first();

        // If no orders exist, show warning and create sample data without order references
        if ($orders->isEmpty()) {
            $this->command->warn('No orders found. Creating return refunds without order references.');
        }

        if ($packages->isEmpty()) {
            $this->command->warn('No packages found. Creating return refunds without package references.');
        }

        $reasons = [
            'Product arrived damaged',
            'Wrong item received',
            'Item not as described',
            'Changed my mind',
            'Better price found elsewhere',
            'Product quality not as expected',
            'Size/fit issue',
            'Missing parts or accessories',
            'Late delivery - no longer needed',
            'Duplicate order',
        ];

        $decisionReasons = [
            'approved' => [
                'Valid return request within policy period',
                'Product confirmed damaged upon inspection',
                'Customer provided valid proof of issue',
                'Item returned in original condition',
            ],
            'rejected' => [
                'Return period expired',
                'Product shows signs of use',
                'Missing original packaging',
                'No valid proof of purchase',
                'Item not eligible for return',
            ],
        ];

        $banks = ['Maybank', 'CIMB', 'Public Bank', 'RHB Bank', 'Hong Leong Bank', 'AmBank'];

        $statuses = [
            'pending' => ['pending_review'],
            'approved' => ['approved_pending_return', 'item_received', 'refund_processing', 'refund_completed'],
            'rejected' => ['rejected'],
        ];

        // Create various return refund records
        $data = [];

        // Helper function to get random item or null
        $getRandomOrder = fn() => $orders->isNotEmpty() ? $orders->random() : null;
        $getRandomPackage = fn() => $packages->isNotEmpty() ? $packages->random() : null;
        $getRandomCustomer = fn() => $customers->isNotEmpty() ? $customers->random() : null;

        // Pending requests (5)
        for ($i = 0; $i < 5; $i++) {
            $order = $getRandomOrder();
            $customer = $getRandomCustomer();

            $data[] = [
                'refund_number' => ReturnRefund::generateRefundNumber(),
                'order_id' => $order?->id,
                'package_id' => $i % 2 === 0 ? $getRandomPackage()?->id : null,
                'customer_id' => $customer?->id ?? $order?->customer_id,
                'return_date' => now()->subDays(rand(1, 7)),
                'reason' => $reasons[array_rand($reasons)],
                'refund_amount' => $order ? $order->total_amount : rand(50, 500),
                'decision' => 'pending',
                'decision_reason' => null,
                'decision_date' => null,
                'processed_by' => null,
                'tracking_number' => null,
                'account_number' => null,
                'account_holder_name' => null,
                'bank_name' => null,
                'status' => 'pending_review',
                'notes' => null,
                'created_at' => now()->subDays(rand(1, 7)),
                'updated_at' => now()->subDays(rand(0, 3)),
            ];
        }

        // Approved requests in various stages (8)
        $approvedStatuses = ['approved_pending_return', 'item_received', 'refund_processing', 'refund_completed'];
        for ($i = 0; $i < 8; $i++) {
            $order = $getRandomOrder();
            $customer = $getRandomCustomer();
            $status = $approvedStatuses[$i % 4];
            $hasTracking = in_array($status, ['item_received', 'refund_processing', 'refund_completed']);
            $hasBankDetails = in_array($status, ['refund_processing', 'refund_completed']);

            $data[] = [
                'refund_number' => ReturnRefund::generateRefundNumber(),
                'order_id' => $order?->id,
                'package_id' => $i % 3 === 0 ? $getRandomPackage()?->id : null,
                'customer_id' => $customer?->id ?? $order?->customer_id,
                'return_date' => now()->subDays(rand(5, 20)),
                'reason' => $reasons[array_rand($reasons)],
                'refund_amount' => $order ? $order->total_amount * (rand(50, 100) / 100) : rand(50, 500),
                'decision' => 'approved',
                'decision_reason' => $decisionReasons['approved'][array_rand($decisionReasons['approved'])],
                'decision_date' => now()->subDays(rand(3, 15)),
                'processed_by' => $admin?->id,
                'tracking_number' => $hasTracking ? 'TRK' . strtoupper(substr(md5(rand()), 0, 10)) : null,
                'account_number' => $hasBankDetails ? rand(1000000000, 9999999999) : null,
                'account_holder_name' => $hasBankDetails ? ($customer?->name ?? 'Customer Name') : null,
                'bank_name' => $hasBankDetails ? $banks[array_rand($banks)] : null,
                'status' => $status,
                'notes' => $status === 'refund_completed' ? 'Refund processed successfully' : null,
                'created_at' => now()->subDays(rand(10, 30)),
                'updated_at' => now()->subDays(rand(0, 5)),
            ];
        }

        // Rejected requests (3)
        for ($i = 0; $i < 3; $i++) {
            $order = $getRandomOrder();
            $customer = $getRandomCustomer();

            $data[] = [
                'refund_number' => ReturnRefund::generateRefundNumber(),
                'order_id' => $order?->id,
                'package_id' => null,
                'customer_id' => $customer?->id ?? $order?->customer_id,
                'return_date' => now()->subDays(rand(10, 30)),
                'reason' => $reasons[array_rand($reasons)],
                'refund_amount' => $order ? $order->total_amount : rand(50, 500),
                'decision' => 'rejected',
                'decision_reason' => $decisionReasons['rejected'][array_rand($decisionReasons['rejected'])],
                'decision_date' => now()->subDays(rand(5, 20)),
                'processed_by' => $admin?->id,
                'tracking_number' => null,
                'account_number' => null,
                'account_holder_name' => null,
                'bank_name' => null,
                'status' => 'rejected',
                'notes' => 'Customer notified of rejection',
                'created_at' => now()->subDays(rand(15, 40)),
                'updated_at' => now()->subDays(rand(5, 15)),
            ];
        }

        // Cancelled requests (2)
        for ($i = 0; $i < 2; $i++) {
            $order = $getRandomOrder();
            $customer = $getRandomCustomer();

            $data[] = [
                'refund_number' => ReturnRefund::generateRefundNumber(),
                'order_id' => $order?->id,
                'package_id' => null,
                'customer_id' => $customer?->id ?? $order?->customer_id,
                'return_date' => now()->subDays(rand(10, 25)),
                'reason' => $reasons[array_rand($reasons)],
                'refund_amount' => $order ? $order->total_amount : rand(50, 500),
                'decision' => 'pending',
                'decision_reason' => null,
                'decision_date' => null,
                'processed_by' => null,
                'tracking_number' => null,
                'account_number' => null,
                'account_holder_name' => null,
                'bank_name' => null,
                'status' => 'cancelled',
                'notes' => 'Customer cancelled the return request',
                'created_at' => now()->subDays(rand(20, 45)),
                'updated_at' => now()->subDays(rand(10, 20)),
            ];
        }

        foreach ($data as $item) {
            ReturnRefund::create($item);
        }

        $this->command->info('Created ' . count($data) . ' return refund records.');
    }
}
