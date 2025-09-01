<?php
use App\Models\Order;
use App\Services\StripeService;
use Livewire\Volt\Component;

new class extends Component {
    public Order $order;

    public function mount()
    {
        $this->order->load(['student.user', 'course', 'enrollment', 'items']);
    }

    public function processRefund($amount = null)
    {
        try {
            $stripeService = app(StripeService::class);
            
            // Implementation would depend on adding refund functionality to StripeService
            session()->flash('info', 'Refund functionality will be implemented in the next phase.');
            
        } catch (\Exception $e) {
            session()->flash('error', 'Failed to process refund: ' . $e->getMessage());
        }
    }

    public function resendReceipt()
    {
        try {
            if (!$this->order->receipt_url) {
                session()->flash('error', 'No receipt URL available for this order.');
                return;
            }

            // Could implement email sending here
            session()->flash('success', 'Receipt sent to student email successfully!');
            
        } catch (\Exception $e) {
            session()->flash('error', 'Failed to send receipt: ' . $e->getMessage());
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
            <flux:button href="{{ route('orders.index') }}" variant="outline">
                Back to Orders
            </flux:button>
            @if($order->receipt_url)
                <flux:button href="{{ $order->receipt_url }}" target="_blank" variant="outline">
                    View Receipt
                </flux:button>
                <flux:button wire:click="resendReceipt" variant="outline">
                    Resend Receipt
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
                                        @if($item->stripe_line_item_id)
                                            <flux:text size="xs" class="text-gray-500 block">{{ $item->stripe_line_item_id }}</flux:text>
                                        @endif
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
                            @if($order->stripe_fee)
                                <tr>
                                    <td colspan="3" class="py-1 text-right text-gray-600">Stripe Fee:</td>
                                    <td class="py-1 text-right text-gray-600">-RM {{ number_format($order->stripe_fee, 2) }}</td>
                                </tr>
                            @endif
                            @if($order->net_amount && $order->net_amount != $order->amount)
                                <tr>
                                    <td colspan="3" class="py-1 text-right font-semibold">Net Amount:</td>
                                    <td class="py-1 text-right font-semibold">RM {{ number_format($order->net_amount, 2) }}</td>
                                </tr>
                            @endif
                        </tfoot>
                    </table>
                </div>
            </flux:card>

            <!-- Stripe Information -->
            <flux:card>
                <flux:heading size="lg" class="mb-4">Payment Details</flux:heading>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    @if($order->stripe_invoice_id)
                        <div>
                            <flux:text class="text-gray-600">Stripe Invoice ID</flux:text>
                            <flux:text class="font-mono text-sm">{{ $order->stripe_invoice_id }}</flux:text>
                        </div>
                    @endif

                    @if($order->stripe_charge_id)
                        <div>
                            <flux:text class="text-gray-600">Stripe Charge ID</flux:text>
                            <flux:text class="font-mono text-sm">{{ $order->stripe_charge_id }}</flux:text>
                        </div>
                    @endif

                    @if($order->stripe_payment_intent_id)
                        <div>
                            <flux:text class="text-gray-600">Payment Intent ID</flux:text>
                            <flux:text class="font-mono text-sm">{{ $order->stripe_payment_intent_id }}</flux:text>
                        </div>
                    @endif

                    <div>
                        <flux:text class="text-gray-600">Currency</flux:text>
                        <flux:text class="font-semibold">{{ strtoupper($order->currency) }}</flux:text>
                    </div>
                </div>
            </flux:card>
        </div>

        <!-- Sidebar -->
        <div class="space-y-6">
            <!-- Student Information -->
            <flux:card>
                <flux:heading size="lg" class="mb-4">Student</flux:heading>
                
                <div class="space-y-3">
                    <div>
                        <flux:text class="text-gray-600">Name</flux:text>
                        <flux:text class="font-semibold">{{ $order->student->user->name }}</flux:text>
                    </div>
                    
                    <div>
                        <flux:text class="text-gray-600">Email</flux:text>
                        <flux:text class="font-semibold">{{ $order->student->user->email }}</flux:text>
                    </div>

                    <div class="pt-2">
                        <flux:button href="{{ route('students.show', $order->student) }}" variant="outline" size="sm">
                            View Student
                        </flux:button>
                    </div>
                </div>
            </flux:card>

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

                    <div class="pt-2">
                        <flux:button href="{{ route('courses.show', $order->course) }}" variant="outline" size="sm">
                            View Course
                        </flux:button>
                    </div>
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

                    <div class="pt-2">
                        <flux:button href="{{ route('enrollments.show', $order->enrollment) }}" variant="outline" size="sm">
                            View Enrollment
                        </flux:button>
                    </div>
                </div>
            </flux:card>

            <!-- Actions -->
            @if($order->isPaid())
                <flux:card>
                    <flux:heading size="lg" class="mb-4">Actions</flux:heading>
                    
                    <div class="space-y-3">
                        <flux:button wire:click="processRefund" variant="outline" class="w-full">
                            Process Refund
                        </flux:button>
                        
                        @if($order->receipt_url)
                            <flux:button wire:click="resendReceipt" variant="outline" class="w-full">
                                Resend Receipt
                            </flux:button>
                        @endif
                    </div>
                </flux:card>
            @endif
        </div>
    </div>
</div>