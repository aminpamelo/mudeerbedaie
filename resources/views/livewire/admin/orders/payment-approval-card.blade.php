<?php

use App\Models\ProductOrder;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    public ?ProductOrder $order = null;

    public $receipt;

    public string $rejectionReason = '';

    public function mount(ProductOrder $order): void
    {
        $this->order = $order->load('paymentConfirmedBy');
    }

    public function approve(): void
    {
        $this->authorize('confirmPayment', $this->order);

        $this->validate([
            'receipt' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:4096'],
        ]);

        $path = $this->receipt->store('funnel-payment-receipts', 'public');

        $this->order->markPaymentAsConfirmed(auth()->id(), $path);

        $this->order = $this->order->fresh()->load('paymentConfirmedBy');

        $this->reset(['receipt', 'rejectionReason']);

        $this->dispatch('order-payment-updated', orderId: $this->order->id);
    }

    public function reject(): void
    {
        $this->authorize('confirmPayment', $this->order);

        $this->validate([
            'rejectionReason' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        $this->order->markPaymentAsRejected(auth()->id(), $this->rejectionReason);

        $this->order = $this->order->fresh()->load('paymentConfirmedBy');

        $this->reset(['receipt', 'rejectionReason']);

        $this->dispatch('order-payment-updated', orderId: $this->order->id);
    }
}; ?>

<div>
    @if($order && $order->source === 'funnel' && $order->payment_method === 'cod')
        @if($order->payment_status === 'pending')
            @can('confirmPayment', $order)
                <flux:card class="mb-6">
                    <div class="flex items-center gap-2 mb-4">
                        <flux:icon name="banknotes" class="w-5 h-5 text-amber-500" />
                        <flux:heading size="lg">Payment Actions</flux:heading>
                    </div>

                    <flux:text class="mb-4">
                        Review the COD payment for this funnel order. Upload the receipt to approve, or provide a reason to reject.
                    </flux:text>

                    <div class="grid md:grid-cols-2 gap-6">
                        <div class="space-y-3">
                            <flux:heading size="sm">Approve Payment</flux:heading>
                            <flux:field>
                                <flux:label>Receipt (PDF or image, max 4MB)</flux:label>
                                <flux:input type="file" wire:model="receipt" accept=".pdf,.jpg,.jpeg,.png" />
                                <flux:error name="receipt" />
                            </flux:field>

                            <div wire:loading wire:target="receipt" class="text-sm text-zinc-500">
                                Uploading receipt...
                            </div>

                            <flux:button
                                variant="primary"
                                wire:click="approve"
                                wire:loading.attr="disabled"
                                wire:target="approve,receipt"
                            >
                                <div class="flex items-center justify-center">
                                    <flux:icon name="check-circle" class="w-4 h-4 mr-1" />
                                    <span wire:loading.remove wire:target="approve">Approve Payment</span>
                                    <span wire:loading wire:target="approve">Approving...</span>
                                </div>
                            </flux:button>
                        </div>

                        <div class="space-y-3">
                            <flux:heading size="sm">Reject Payment</flux:heading>
                            <flux:field>
                                <flux:label>Rejection reason</flux:label>
                                <flux:textarea
                                    wire:model="rejectionReason"
                                    rows="3"
                                    placeholder="e.g. No transfer received within 7 days"
                                />
                                <flux:error name="rejectionReason" />
                            </flux:field>

                            <flux:button
                                variant="danger"
                                wire:click="reject"
                                wire:loading.attr="disabled"
                                wire:target="reject"
                            >
                                <div class="flex items-center justify-center">
                                    <flux:icon name="x-circle" class="w-4 h-4 mr-1" />
                                    <span wire:loading.remove wire:target="reject">Reject Payment</span>
                                    <span wire:loading wire:target="reject">Rejecting...</span>
                                </div>
                            </flux:button>
                        </div>
                    </div>
                </flux:card>
            @endcan
        @else
            <flux:card class="mb-6">
                <div class="flex items-center gap-2 mb-4">
                    @if($order->payment_status === 'paid')
                        <flux:icon name="check-badge" class="w-5 h-5 text-green-500" />
                        <flux:heading size="lg">Payment Confirmed</flux:heading>
                        <flux:badge color="green" size="sm">Paid</flux:badge>
                    @elseif($order->payment_status === 'failed')
                        <flux:icon name="x-circle" class="w-5 h-5 text-red-500" />
                        <flux:heading size="lg">Payment Rejected</flux:heading>
                        <flux:badge color="red" size="sm">Failed</flux:badge>
                    @else
                        <flux:icon name="information-circle" class="w-5 h-5 text-zinc-500" />
                        <flux:heading size="lg">Payment Status</flux:heading>
                        <flux:badge color="zinc" size="sm">{{ ucfirst($order->payment_status) }}</flux:badge>
                    @endif
                </div>

                <div class="space-y-2 text-sm">
                    @if($order->paymentConfirmedBy)
                        <div class="flex items-center gap-2">
                            <flux:text class="text-zinc-500 dark:text-zinc-400">Actioned by:</flux:text>
                            <flux:text class="font-medium">{{ $order->paymentConfirmedBy->name }}</flux:text>
                        </div>
                    @endif

                    @if($order->payment_confirmed_at)
                        <div class="flex items-center gap-2">
                            <flux:text class="text-zinc-500 dark:text-zinc-400">When:</flux:text>
                            <flux:text class="font-medium">
                                {{ $order->payment_confirmed_at->diffForHumans() }}
                                <span class="text-zinc-500 dark:text-zinc-400">({{ $order->payment_confirmed_at->format('d M Y, H:i') }})</span>
                            </flux:text>
                        </div>
                    @endif

                    @if($order->payment_status === 'paid' && $order->receipt_attachment)
                        <div class="flex items-center gap-2">
                            <flux:text class="text-zinc-500 dark:text-zinc-400">Receipt:</flux:text>
                            <a
                                href="{{ $order->receipt_attachment_url }}"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="text-sm font-medium text-blue-600 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300 inline-flex items-center gap-1"
                            >
                                <flux:icon name="paper-clip" class="w-4 h-4" />
                                View receipt
                            </a>
                        </div>
                    @endif

                    @if($order->payment_status === 'failed' && $order->payment_rejection_reason)
                        <div class="mt-3 rounded-md bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 px-4 py-3">
                            <flux:text class="text-xs uppercase text-red-700 dark:text-red-300 font-semibold mb-1">Rejection reason</flux:text>
                            <flux:text class="text-sm text-red-700 dark:text-red-300">{{ $order->payment_rejection_reason }}</flux:text>
                        </div>
                    @endif
                </div>
            </flux:card>
        @endif
    @endif
</div>
