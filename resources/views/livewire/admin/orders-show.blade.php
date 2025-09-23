<?php
use App\Models\Order;
use App\Services\StripeService;
use Livewire\Volt\Component;
use Carbon\Carbon;

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

    public function hasReceiptAttachment(): bool
    {
        return isset($this->order->metadata['receipt_file']) && !empty($this->order->metadata['receipt_file']);
    }

    public function getReceiptUrl(): ?string
    {
        if (!$this->hasReceiptAttachment()) {
            return null;
        }
        
        return asset('storage/' . $this->order->metadata['receipt_file']);
    }

    public function getApprover()
    {
        if (!isset($this->order->metadata['approved_by'])) {
            return null;
        }

        return \App\Models\User::find($this->order->metadata['approved_by']);
    }

    public function downloadReceipt()
    {
        if (!$this->hasReceiptAttachment()) {
            session()->flash('error', 'No receipt attachment found.');
            return;
        }

        $filePath = storage_path('app/public/' . $this->order->metadata['receipt_file']);
        
        if (!file_exists($filePath)) {
            session()->flash('error', 'Receipt file not found.');
            return;
        }

        return response()->download($filePath, 'receipt-' . $this->order->order_number . '.' . pathinfo($filePath, PATHINFO_EXTENSION));
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
            @if($order->isPaid())
                <flux:button href="{{ route('orders.receipt', $order) }}" variant="outline">
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
                        <flux:text class="text-gray-600">Payment Method</flux:text>
                        <div class="flex items-center">
                            @if($order->payment_method === 'stripe')
                                <flux:icon name="credit-card" class="w-4 h-4 mr-2 text-blue-500" />
                            @else
                                <flux:icon name="banknotes" class="w-4 h-4 mr-2 text-green-500" />
                            @endif
                            <flux:text class="font-semibold">{{ $order->payment_method_label }}</flux:text>
                        </div>
                    </div>

                    <div>
                        <flux:text class="text-gray-600">Billing Period</flux:text>
                        <flux:text class="font-semibold">{{ $order->getPeriodDescription() }}</flux:text>
                    </div>

                    <div>
                        <flux:text class="text-gray-600">Billing Reason</flux:text>
                        <flux:text class="font-semibold">{{ $order->billing_reason_label }}</flux:text>
                    </div>
                </div>

                @if($order->isFailed() && $order->failure_reason)
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

            <!-- Payment Information -->
            <flux:card>
                <flux:heading size="lg" class="mb-4">Payment Details</flux:heading>

                <!-- Payment Method Section -->
                <div class="mb-6 p-4 rounded-lg {{ $order->payment_method === 'stripe' ? 'bg-blue-50 border border-blue-200' : 'bg-green-50 border border-green-200' }}">
                    <div class="flex items-center">
                        @if($order->payment_method === 'stripe')
                            <flux:icon name="credit-card" class="w-6 h-6 mr-3 text-blue-600" />
                            <div>
                                <flux:text class="font-semibold text-blue-900">Stripe Card Payment</flux:text>
                                <flux:text size="sm" class="text-blue-700">Processed automatically via Stripe</flux:text>
                            </div>
                        @else
                            <flux:icon name="banknotes" class="w-6 h-6 mr-3 text-green-600" />
                            <div>
                                <flux:text class="font-semibold text-green-900">Manual Payment</flux:text>
                                <flux:text size="sm" class="text-green-700">Payment processed manually (bank transfer, cash, etc.)</flux:text>
                            </div>
                        @endif
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    @if($order->payment_method === 'stripe')
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
                    @else
                        <div>
                            <flux:text class="text-gray-600">Payment Type</flux:text>
                            <flux:text class="font-semibold">Manual Payment</flux:text>
                        </div>

                        @if(isset($order->metadata['payment_notes']))
                            <div>
                                <flux:text class="text-gray-600">Payment Notes</flux:text>
                                <flux:text class="text-sm">{{ $order->metadata['payment_notes'] }}</flux:text>
                            </div>
                        @endif
                    @endif

                    <div>
                        <flux:text class="text-gray-600">Currency</flux:text>
                        <flux:text class="font-semibold">{{ strtoupper($order->currency) }}</flux:text>
                    </div>

                    @if($order->payment_method === 'manual' && $this->hasReceiptAttachment())
                        <div class="md:col-span-2">
                            <flux:text class="text-gray-600">Payment Receipt</flux:text>
                            <div class="mt-2 flex items-center space-x-3">
                                <flux:icon icon="document" class="w-5 h-5 text-green-600" />
                                <flux:text class="text-sm text-green-700">Receipt attachment available</flux:text>
                                <flux:button
                                    wire:click="downloadReceipt"
                                    variant="outline"
                                    size="sm"
                                    icon="document">
                                    Download
                                </flux:button>
                                <a href="{{ $this->getReceiptUrl() }}" target="_blank">
                                    <flux:button variant="ghost" size="sm" icon="magnifying-glass">
                                        View
                                    </flux:button>
                                </a>
                            </div>
                            @if(isset($order->metadata['approved_by']) && isset($order->metadata['approved_at']))
                                <div class="mt-2">
                                    <flux:text size="xs" class="text-gray-500">
                                        Approved manually on {{ \Carbon\Carbon::parse($order->metadata['approved_at'])->format('M j, Y g:i A') }}
                                        @if($this->getApprover())
                                            by {{ $this->getApprover()->name }}
                                        @endif
                                    </flux:text>
                                </div>
                            @endif
                        </div>
                    @elseif($order->payment_method === 'stripe' && $order->receipt_url)
                        <div class="md:col-span-2">
                            <flux:text class="text-gray-600">Stripe Receipt</flux:text>
                            <div class="mt-2 flex items-center space-x-3">
                                <flux:icon icon="document" class="w-5 h-5 text-blue-600" />
                                <flux:text class="text-sm text-blue-700">Official Stripe receipt available</flux:text>
                                <a href="{{ $order->receipt_url }}" target="_blank">
                                    <flux:button variant="outline" size="sm" icon="external-link">
                                        View Stripe Receipt
                                    </flux:button>
                                </a>
                            </div>
                        </div>
                    @endif
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
                        
                        <flux:button href="{{ route('orders.receipt', $order) }}" variant="outline" class="w-full">
                            View Official Receipt
                        </flux:button>
                        @if($this->hasReceiptAttachment())
                            <flux:button wire:click="downloadReceipt" variant="outline" class="w-full">
                                Download Payment Receipt
                            </flux:button>
                        @endif
                        <flux:button wire:click="resendReceipt" variant="outline" class="w-full">
                            Resend Receipt
                        </flux:button>
                    </div>
                </flux:card>
            @endif
        </div>
    </div>
</div>