<?php
use App\Models\Payment;
use Livewire\Volt\Component;

new class extends Component {
    public Payment $payment;

    public function mount(Payment $payment)
    {
        // Ensure user is a student and owns this payment
        if (!auth()->user()->isStudent() || $payment->user_id !== auth()->id()) {
            abort(403, 'Access denied');
        }

        $this->payment = $payment->load(['invoice.course', 'paymentMethod']);
    }

    public function getStatusBadgeColor($status): string
    {
        return match($status) {
            Payment::STATUS_SUCCEEDED => 'emerald',
            Payment::STATUS_FAILED, Payment::STATUS_CANCELLED => 'red',
            Payment::STATUS_PROCESSING => 'blue',
            Payment::STATUS_PENDING => 'amber',
            Payment::STATUS_REQUIRES_ACTION, Payment::STATUS_REQUIRES_PAYMENT_METHOD => 'orange',
            Payment::STATUS_REFUNDED, Payment::STATUS_PARTIALLY_REFUNDED => 'purple',
            default => 'gray'
        };
    }
}; ?>

<div>
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading size="xl">Payment Details</flux:heading>
            <flux:text class="mt-2">Transaction #{{ $payment->id }}</flux:text>
        </div>
        <flux:button variant="outline" href="{{ route('student.payments') }}" wire:navigate>
            <flux:icon icon="arrow-left" class="w-4 h-4" />
            Back to Payments
        </flux:button>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Payment Information -->
        <div class="lg:col-span-2">
            <flux:card>
                <flux:header>
                    <div class="flex items-center justify-between">
                        <flux:heading size="lg">Payment Information</flux:heading>
                        <flux:badge :color="$this->getStatusBadgeColor($payment->status)">
                            {{ $payment->status_label }}
                        </flux:badge>
                    </div>
                </flux:header>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="space-y-4">
                        <div>
                            <flux:text size="sm" class="text-gray-600">Payment ID</flux:text>
                            <flux:text class="font-medium">{{ $payment->id }}</flux:text>
                        </div>

                        <div>
                            <flux:text size="sm" class="text-gray-600">Amount</flux:text>
                            <flux:heading size="md">{{ $payment->formatted_amount }}</flux:heading>
                        </div>

                        <div>
                            <flux:text size="sm" class="text-gray-600">Payment Method</flux:text>
                            <div class="flex items-center space-x-2">
                                <flux:badge color="{{ $payment->isStripePayment() ? 'blue' : 'green' }}" size="sm">
                                    {{ $payment->type_label }}
                                </flux:badge>
                                @if($payment->isStripePayment() && $payment->paymentMethod)
                                    <flux:text class="text-sm">
                                        **** {{ $payment->paymentMethod->card_details['last4'] ?? '****' }}
                                    </flux:text>
                                @endif
                            </div>
                        </div>

                        <div>
                            <flux:text size="sm" class="text-gray-600">Date & Time</flux:text>
                            <flux:text class="font-medium">{{ $payment->created_at->format('M d, Y \a\t H:i') }}</flux:text>
                            <flux:text size="sm" class="text-gray-500">{{ $payment->created_at->diffForHumans() }}</flux:text>
                        </div>
                    </div>

                    <div class="space-y-4">
                        @if($payment->stripe_payment_intent_id)
                            <div>
                                <flux:text size="sm" class="text-gray-600">Transaction Reference</flux:text>
                                <flux:text class="font-mono text-sm">{{ Str::limit($payment->stripe_payment_intent_id, 30) }}</flux:text>
                            </div>
                        @endif

                        @if($payment->stripe_fee > 0)
                            <div>
                                <flux:text size="sm" class="text-gray-600">Processing Fee</flux:text>
                                <flux:text class="font-medium">RM {{ number_format($payment->stripe_fee, 2) }}</flux:text>
                            </div>
                            
                            <div>
                                <flux:text size="sm" class="text-gray-600">Net Amount</flux:text>
                                <flux:text class="font-medium">RM {{ number_format($payment->net_amount ?? ($payment->amount - $payment->stripe_fee), 2) }}</flux:text>
                            </div>
                        @endif

                        @if($payment->receipt_url)
                            <div>
                                <flux:text size="sm" class="text-gray-600">Receipt</flux:text>
                                <flux:link href="{{ $payment->receipt_url }}" target="_blank" class="text-blue-600 hover:underline">
                                    <flux:icon icon="document" class="w-4 h-4 inline" />
                                    Download Receipt
                                </flux:link>
                            </div>
                        @endif

                        @if($payment->paid_at)
                            <div>
                                <flux:text size="sm" class="text-gray-600">Processed At</flux:text>
                                <flux:text class="font-medium">{{ $payment->paid_at->format('M d, Y H:i') }}</flux:text>
                            </div>
                        @endif
                    </div>
                </div>

                @if($payment->failure_reason && $payment->isFailed())
                    <div class="mt-6 border-t pt-6">
                        <flux:text size="sm" class="text-gray-600 mb-2">Failure Reason</flux:text>
                        <div class="bg-red-50 dark:bg-red-900/20 rounded-lg p-4">
                            <flux:text class="text-red-800 dark:text-red-200">{{ $payment->failure_reason }}</flux:text>
                        </div>
                    </div>
                @endif
            </flux:card>

            <!-- Bank Transfer Details -->
            @if($payment->isBankTransfer() && $payment->bank_transfer_details)
                <flux:card class="mt-6">
                    <flux:header>
                        <flux:heading size="lg">Bank Transfer Details</flux:heading>
                    </flux:header>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        @foreach($payment->bank_transfer_details as $key => $value)
                            @if($value && $key !== 'proof_of_payment_url')
                                <div>
                                    <flux:text size="sm" class="text-gray-600">{{ ucwords(str_replace('_', ' ', $key)) }}</flux:text>
                                    <flux:text class="font-medium">{{ $value }}</flux:text>
                                </div>
                            @endif
                        @endforeach
                    </div>

                    @if(isset($payment->bank_transfer_details['proof_of_payment_url']))
                        <div class="mt-6 border-t pt-6">
                            <flux:text size="sm" class="text-gray-600 mb-2">Proof of Payment</flux:text>
                            <img src="{{ $payment->bank_transfer_details['proof_of_payment_url'] }}" alt="Proof of Payment" class="max-w-md rounded-lg border">
                        </div>
                    @endif
                </flux:card>
            @endif
        </div>

        <!-- Sidebar Information -->
        <div>
            <!-- Invoice Information -->
            <flux:card>
                <flux:header>
                    <flux:heading size="lg">Invoice</flux:heading>
                </flux:header>

                <div class="space-y-4">
                    <div>
                        <flux:text size="sm" class="text-gray-600">Invoice Number</flux:text>
                        <flux:link :href="route('student.invoices.show', $payment->invoice)" class="font-medium hover:text-blue-600" wire:navigate>
                            {{ $payment->invoice->invoice_number }}
                        </flux:link>
                    </div>

                    <div>
                        <flux:text size="sm" class="text-gray-600">Course</flux:text>
                        <flux:text class="font-medium">{{ $payment->invoice->course->name }}</flux:text>
                    </div>

                    <div>
                        <flux:text size="sm" class="text-gray-600">Invoice Amount</flux:text>
                        <flux:text class="font-medium">{{ $payment->invoice->formatted_amount }}</flux:text>
                    </div>

                    <div>
                        <flux:text size="sm" class="text-gray-600">Due Date</flux:text>
                        <flux:text class="font-medium {{ $payment->invoice->isOverdue() ? 'text-red-600' : '' }}">
                            {{ $payment->invoice->due_date->format('M d, Y') }}
                        </flux:text>
                    </div>

                    <flux:badge :color="$payment->invoice->isPaid() ? 'emerald' : ($payment->invoice->isOverdue() ? 'red' : 'amber')" size="sm">
                        {{ $payment->invoice->status_label }}
                    </flux:badge>
                </div>

                <div class="mt-6">
                    <flux:button variant="outline" class="w-full" :href="route('student.invoices.show', $payment->invoice)" wire:navigate>
                        <flux:icon icon="document-text" class="w-4 h-4" />
                        View Full Invoice
                    </flux:button>
                </div>
            </flux:card>

            <!-- Actions -->
            <flux:card class="mt-6">
                <flux:header>
                    <flux:heading size="lg">Actions</flux:heading>
                </flux:header>

                <div class="space-y-3">
                    @if($payment->isFailed() && !$payment->invoice->isPaid())
                        <flux:button 
                            variant="filled" 
                            color="blue" 
                            class="w-full" 
                            :href="route('student.invoices.pay', $payment->invoice)" 
                            wire:navigate
                        >
                            <flux:icon icon="credit-card" class="w-4 h-4" />
                            Retry Payment
                        </flux:button>
                    @endif

                    @if($payment->receipt_url)
                        <flux:button 
                            variant="outline" 
                            class="w-full" 
                            :href="$payment->receipt_url" 
                            target="_blank"
                        >
                            <flux:icon icon="document-arrow-down" class="w-4 h-4" />
                            Download Receipt
                        </flux:button>
                    @endif

                    <flux:button 
                        variant="outline" 
                        class="w-full" 
                        :href="route('student.invoices')" 
                        wire:navigate
                    >
                        <flux:icon icon="document" class="w-4 h-4" />
                        View All Invoices
                    </flux:button>
                </div>
            </flux:card>

            <!-- Support -->
            <flux:card class="mt-6">
                <flux:header>
                    <flux:heading size="lg">Need Help?</flux:heading>
                </flux:header>

                <div class="space-y-4">
                    <flux:text size="sm" class="text-gray-600">
                        If you have any questions about this payment or need assistance, please contact our support team.
                    </flux:text>

                    @if($payment->isFailed())
                        <div class="bg-red-50 dark:bg-red-900/20 rounded-lg p-4">
                            <flux:text size="sm" class="text-red-800 dark:text-red-200">
                                <strong>Payment Failed:</strong> This usually occurs due to insufficient funds, expired cards, or bank security measures. Please try again with a different payment method.
                            </flux:text>
                        </div>
                    @endif

                    @if($payment->isPending())
                        <div class="bg-amber-50 dark:bg-amber-900/20 rounded-lg p-4">
                            <flux:text size="sm" class="text-amber-800 dark:text-amber-200">
                                <strong>Payment Pending:</strong> Bank transfers require manual verification. You'll receive an email confirmation once processed.
                            </flux:text>
                        </div>
                    @endif
                </div>
            </flux:card>
        </div>
    </div>
</div>