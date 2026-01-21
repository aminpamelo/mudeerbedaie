<?php

namespace Database\Seeders;

use App\Models\ProductOrder;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Seeder;

class TicketSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get existing data
        $orders = ProductOrder::all();
        $customers = User::whereIn('role', ['user', 'student'])->get();
        $staff = User::where('role', 'admin')->get();

        // Helper functions to safely get random items
        $getRandomOrder = fn() => $orders->isNotEmpty() ? $orders->random() : null;
        $getRandomCustomer = fn() => $customers->isNotEmpty() ? $customers->random() : null;
        $getRandomStaff = fn() => $staff->isNotEmpty() ? $staff->random() : null;

        $ticketData = [
            // Open tickets
            [
                'subject' => 'Order not received after 7 days',
                'description' => "I placed an order on last week but I still haven't received it. The tracking number shows it's stuck in transit. Can you please help me track down my package?",
                'category' => 'inquiry',
                'status' => 'open',
                'priority' => 'high',
                'assigned' => false,
            ],
            [
                'subject' => 'Request for refund - damaged item',
                'description' => "I received my order today but the product was damaged during shipping. The packaging was torn and the item inside was broken. I would like to request a full refund or replacement.",
                'category' => 'refund',
                'status' => 'open',
                'priority' => 'high',
                'assigned' => false,
            ],
            [
                'subject' => 'Wrong item received',
                'description' => "I ordered a blue shirt size M but received a red shirt size L instead. Please arrange for a return and send me the correct item.",
                'category' => 'return',
                'status' => 'open',
                'priority' => 'medium',
                'assigned' => false,
            ],

            // In Progress tickets
            [
                'subject' => 'Product quality issue',
                'description' => "The product I received doesn't match the description on the website. The material quality is much lower than expected. I'm very disappointed with this purchase.",
                'category' => 'complaint',
                'status' => 'in_progress',
                'priority' => 'medium',
                'assigned' => true,
            ],
            [
                'subject' => 'Request partial refund - missing item',
                'description' => "My order arrived but one item was missing from the package. I ordered 3 items but only received 2. Please refund the missing item or send it separately.",
                'category' => 'refund',
                'status' => 'in_progress',
                'priority' => 'high',
                'assigned' => true,
            ],
            [
                'subject' => 'How to track my order?',
                'description' => "I can't find the tracking number for my order. Where can I find the shipping information? The order was placed 3 days ago.",
                'category' => 'inquiry',
                'status' => 'in_progress',
                'priority' => 'low',
                'assigned' => true,
            ],

            // Pending tickets
            [
                'subject' => 'Return request - changed my mind',
                'description' => "I would like to return the product as I no longer need it. The item is unopened and in original packaging. What is your return policy?",
                'category' => 'return',
                'status' => 'pending',
                'priority' => 'low',
                'assigned' => true,
            ],
            [
                'subject' => 'Payment issue - double charged',
                'description' => "I was charged twice for the same order. My bank statement shows two identical transactions. Please refund the duplicate charge immediately.",
                'category' => 'refund',
                'status' => 'pending',
                'priority' => 'urgent',
                'assigned' => true,
            ],

            // Resolved tickets
            [
                'subject' => 'Delivery address correction',
                'description' => "I made a mistake with my delivery address. Can you please update it before the order is shipped? The correct address should be...",
                'category' => 'inquiry',
                'status' => 'resolved',
                'priority' => 'medium',
                'assigned' => true,
            ],
            [
                'subject' => 'Cancel my order',
                'description' => "I would like to cancel my order as I found a better deal elsewhere. The order hasn't been shipped yet. Please process the cancellation and refund.",
                'category' => 'refund',
                'status' => 'resolved',
                'priority' => 'medium',
                'assigned' => true,
            ],
            [
                'subject' => 'Product inquiry - size guide',
                'description' => "Can you provide me with the exact measurements for size M? I want to make sure I order the right size.",
                'category' => 'inquiry',
                'status' => 'resolved',
                'priority' => 'low',
                'assigned' => true,
            ],

            // Closed tickets
            [
                'subject' => 'Exchange request completed',
                'description' => "Thank you for processing my exchange. I received the correct item today and everything looks great. You can close this ticket.",
                'category' => 'return',
                'status' => 'closed',
                'priority' => 'medium',
                'assigned' => true,
            ],
            [
                'subject' => 'Refund received - thank you',
                'description' => "I received the refund in my account. Thank you for the quick resolution. Great customer service!",
                'category' => 'refund',
                'status' => 'closed',
                'priority' => 'high',
                'assigned' => true,
            ],
            [
                'subject' => 'Issue resolved',
                'description' => "The replacement item arrived and it's perfect. Thank you for your help in resolving this matter.",
                'category' => 'complaint',
                'status' => 'closed',
                'priority' => 'medium',
                'assigned' => true,
            ],
            [
                'subject' => 'Question answered',
                'description' => "Thanks for the product information. I will proceed with the order now.",
                'category' => 'inquiry',
                'status' => 'closed',
                'priority' => 'low',
                'assigned' => true,
            ],
        ];

        foreach ($ticketData as $data) {
            $order = $getRandomOrder();
            $customer = $order?->customer ?? $getRandomCustomer();
            $assignedTo = $data['assigned'] ? $getRandomStaff() : null;

            $createdAt = now()->subDays(rand(1, 30))->subHours(rand(0, 23));

            $ticket = Ticket::create([
                'ticket_number' => Ticket::generateTicketNumber(),
                'order_id' => $order?->id,
                'customer_id' => $customer?->id,
                'subject' => $data['subject'],
                'description' => $data['description'],
                'category' => $data['category'],
                'status' => $data['status'],
                'priority' => $data['priority'],
                'assigned_to' => $assignedTo?->id,
                'resolved_at' => in_array($data['status'], ['resolved', 'closed']) ? $createdAt->copy()->addDays(rand(1, 5)) : null,
                'closed_at' => $data['status'] === 'closed' ? $createdAt->copy()->addDays(rand(2, 7)) : null,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);

            // Add replies for non-open tickets
            if ($data['status'] !== 'open' && $assignedTo) {
                // Staff initial response
                $ticket->replies()->create([
                    'user_id' => $assignedTo->id,
                    'message' => $this->getStaffResponse($data['category']),
                    'is_internal' => false,
                    'created_at' => $createdAt->copy()->addHours(rand(1, 24)),
                ]);

                // Customer follow-up for some tickets
                if (in_array($data['status'], ['in_progress', 'resolved', 'closed']) && rand(0, 1)) {
                    $ticket->replies()->create([
                        'user_id' => $customer?->id,
                        'message' => $this->getCustomerResponse($data['status']),
                        'is_internal' => false,
                        'created_at' => $createdAt->copy()->addDays(1)->addHours(rand(1, 12)),
                    ]);
                }

                // Internal note for some tickets
                if (rand(0, 2) === 0) {
                    $ticket->replies()->create([
                        'user_id' => $assignedTo->id,
                        'message' => 'Verified customer information. Processing request.',
                        'is_internal' => true,
                        'created_at' => $createdAt->copy()->addHours(rand(2, 8)),
                    ]);
                }

                // Resolution message for resolved/closed tickets
                if (in_array($data['status'], ['resolved', 'closed'])) {
                    $ticket->replies()->create([
                        'user_id' => $assignedTo->id,
                        'message' => $this->getResolutionMessage($data['category']),
                        'is_internal' => false,
                        'created_at' => $ticket->resolved_at ?? $createdAt->copy()->addDays(2),
                    ]);
                }
            }
        }

        $this->command->info('Created ' . count($ticketData) . ' tickets with replies');
    }

    private function getStaffResponse(string $category): string
    {
        return match ($category) {
            'refund' => "Thank you for contacting us. I understand you're requesting a refund. Let me review your order and I'll get back to you with the next steps.",
            'return' => "Thank you for reaching out. I'll help you process this return request. Please ensure the item is in its original condition.",
            'complaint' => "We're sorry to hear about your experience. We take customer feedback seriously and will investigate this matter.",
            'inquiry' => "Thank you for your inquiry. I'll look into this and provide you with the information you need.",
            default => "Thank you for contacting customer support. I'm reviewing your request and will assist you shortly.",
        };
    }

    private function getCustomerResponse(string $status): string
    {
        return match ($status) {
            'resolved', 'closed' => "Thank you for your help. I appreciate the quick response.",
            'in_progress' => "Thanks for the update. Please let me know when there's any progress.",
            default => "Thank you for looking into this.",
        };
    }

    private function getResolutionMessage(string $category): string
    {
        return match ($category) {
            'refund' => "Your refund has been processed. Please allow 3-5 business days for the amount to reflect in your account.",
            'return' => "Your return has been approved. Please use the prepaid shipping label sent to your email to return the item.",
            'complaint' => "We have addressed your concerns and taken appropriate measures. Thank you for bringing this to our attention.",
            'inquiry' => "I hope this information was helpful. Please don't hesitate to reach out if you have any other questions.",
            default => "This matter has been resolved. Thank you for your patience.",
        };
    }
}
