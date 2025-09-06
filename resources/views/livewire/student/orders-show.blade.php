<?php
use App\Models\Order;
use Livewire\Volt\Component;

new class extends Component {
    public Order $order;

    public function mount()
    {
        $this->order->load(['student.user', 'course', 'enrollment', 'items']);
        
        // Ensure the order belongs to the authenticated student
        if ($this->order->student_id !== auth()->user()->student->id) {
            abort(403, 'You can only view your own orders.');
        }
    }

    public function with(): array
    {
        return [];
    }
}; ?>

<div>
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading size="xl">Order {{ $order->order_number }}</flux:heading>
            <flux:text class="mt-2">Order details and payment information</flux:text>
        </div>
        <div class="flex gap-3">
            <flux:button href="{{ route('student.orders') }}" variant="outline">
                Back to Orders
            </flux:button>
            @if($order->isPaid())
                <flux:button href="{{ route('student.orders.receipt', $order) }}" variant="outline">
                    View Receipt
                </flux:button>
            @endif
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Order Details -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Order Status -->
            <flux:card>
                <div class="flex items-center justify-between mb-4">
                    <flux:heading size="lg">Order Status</flux:heading>
                    @if($order->isPaid())
                        <flux:badge variant="success" size="lg">{{ $order->status_label }}</flux:badge>
                    @elseif($order->isFailed())
                        <flux:badge variant="danger" size="lg">{{ $order->status_label }}</flux:badge>
                    @elseif($order->isPending())
                        <flux:badge variant="warning" size="lg">{{ $order->status_label }}</flux:badge>
                    @else
                        <flux:badge variant="gray" size="lg">{{ $order->status_label }}</flux:badge>
                    @endif
                </div>

                <div class="grid grid-cols-2 gap-6">
                    <div>
                        <flux:text class="text-gray-600">Order Date</flux:text>
                        <flux:text class="font-semibold">{{ $order->created_at->format('M j, Y g:i A') }}</flux:text>
                    </div>
                    
                    @if($order->paid_at)
                        <div>
                            <flux:text class="text-gray-600">Paid Date</flux:text>
                            <flux:text class="font-semibold">{{ $order->paid_at->format('M j, Y g:i A') }}</flux:text>
                        </div>
                    @endif

                    @if($order->failed_at)
                        <div>
                            <flux:text class="text-gray-600">Failed Date</flux:text>
                            <flux:text class="font-semibold">{{ $order->failed_at->format('M j, Y g:i A') }}</flux:text>
                        </div>
                    @endif

                    <div>
                        <flux:text class="text-gray-600">Billing Period</flux:text>
                        <flux:text class="font-semibold">{{ $order->getPeriodDescription() }}</flux:text>
                    </div>

                    <div>
                        <flux:text class="text-gray-600">Billing Reason</flux:text>
                        <flux:text class="font-semibold">{{ $order->billing_reason_label }}</flux:text>
                    </div>
                </div>

                @if($order->failure_reason)
                    <div class="mt-4 p-4 bg-red-50 border border-red-200 rounded-lg">
                        <flux:text class="text-red-800 font-medium">Failure Reason</flux:text>
                        <flux:text class="text-red-700 mt-1">
                            {{ $order->failure_reason['failure_message'] ?? 'Payment failed' }}
                        </flux:text>
                        @if(isset($order->failure_reason['failure_code']))
                            <flux:text size="sm" class="text-red-600 mt-1">
                                Code: {{ $order->failure_reason['failure_code'] }}
                            </flux:text>
                        @endif
                    </div>
                @endif
            </flux:card>

            <!-- Order Items -->
            <flux:card>
                <flux:heading size="lg" class="mb-4">Order Items</flux:heading>
                
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-200">
                                <th class="text-left py-2">Description</th>
                                <th class="text-center py-2">Quantity</th>
                                <th class="text-right py-2">Unit Price</th>
                                <th class="text-right py-2">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($order->items as $item)
                                <tr class="border-b border-gray-100">
                                    <td class="py-3">
                                        <flux:text>{{ $item->description }}</flux:text>
                                    </td>
                                    <td class="py-3 text-center">{{ $item->quantity }}</td>
                                    <td class="py-3 text-right">{{ $item->formatted_unit_price }}</td>
                                    <td class="py-3 text-right font-semibold">{{ $item->formatted_total_price }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="py-4 text-center text-gray-500">
                                        No order items found
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                        <tfoot>
                            <tr class="border-t border-gray-300">
                                <td colspan="3" class="py-3 text-right font-semibold">Order Total:</td>
                                <td class="py-3 text-right font-bold text-lg">{{ $order->formatted_amount }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </flux:card>

            <!-- Payment Information -->
            @if($order->isPaid())
                <flux:card>
                    <flux:heading size="lg" class="mb-4">Payment Details</flux:heading>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        @if($order->stripe_charge_id)
                            <div>
                                <flux:text class="text-gray-600">Transaction ID</flux:text>
                                <flux:text class="font-mono text-sm">{{ $order->stripe_charge_id }}</flux:text>
                            </div>
                        @endif

                        <div>
                            <flux:text class="text-gray-600">Currency</flux:text>
                            <flux:text class="font-semibold">{{ strtoupper($order->currency) }}</flux:text>
                        </div>

                        <div>
                            <flux:text class="text-gray-600">Payment Method</flux:text>
                            <flux:text class="font-semibold">Credit/Debit Card</flux:text>
                        </div>
                    </div>
                </flux:card>
            @endif
        </div>

        <!-- Sidebar -->
        <div class="space-y-6">
            <!-- Course Information -->
            <flux:card>
                <flux:heading size="lg" class="mb-4">Course</flux:heading>
                
                <div class="space-y-3">
                    <div>
                        <flux:text class="text-gray-600">Course Name</flux:text>
                        <flux:text class="font-semibold">{{ $order->course->name }}</flux:text>
                    </div>
                    
                    @if($order->course->description)
                        <div>
                            <flux:text class="text-gray-600">Description</flux:text>
                            <flux:text class="text-sm">{{ Str::limit($order->course->description, 100) }}</flux:text>
                        </div>
                    @endif
                </div>
            </flux:card>

            <!-- Enrollment Information -->
            <flux:card>
                <flux:heading size="lg" class="mb-4">Enrollment</flux:heading>
                
                <div class="space-y-3">
                    <div>
                        <flux:text class="text-gray-600">Status</flux:text>
                        <flux:text class="font-semibold">{{ ucfirst($order->enrollment->status) }}</flux:text>
                    </div>
                    
                    @if($order->enrollment->subscription_status)
                        <div>
                            <flux:text class="text-gray-600">Subscription Status</flux:text>
                            <flux:text class="font-semibold">{{ $order->enrollment->getSubscriptionStatusLabel() }}</flux:text>
                        </div>
                    @endif

                    @if($order->enrollment->enrollment_date)
                        <div>
                            <flux:text class="text-gray-600">Enrolled Date</flux:text>
                            <flux:text class="font-semibold">{{ $order->enrollment->enrollment_date->format('M j, Y') }}</flux:text>
                        </div>
                    @endif
                </div>
            </flux:card>

            <!-- Actions -->
            @if($order->isFailed() && $order->enrollment->hasActiveSubscription())
                <flux:card>
                    <flux:heading size="lg" class="mb-4">Actions</flux:heading>
                    
                    <div class="space-y-3">
                        <flux:button href="{{ route('student.payment-methods') }}" variant="primary" class="w-full">
                            Update Payment Method
                        </flux:button>
                    </div>
                </flux:card>
            @endif

            @if($order->isPaid())
                <flux:card>
                    <flux:heading size="lg" class="mb-4">Receipt</flux:heading>
                    
                    <div class="space-y-3">
                        <flux:button href="{{ route('student.orders.receipt', $order) }}" variant="primary" class="w-full">
                            View Receipt
                        </flux:button>
                        <flux:text size="sm" class="text-gray-500">
                            Download or print your payment receipt
                        </flux:text>
                    </div>
                </flux:card>
            @endif
        </div>
    </div>
</div>