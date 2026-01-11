<?php

use App\Models\ReturnRefund;
use App\Models\ProductOrder;
use Livewire\Volt\Component;

new class extends Component
{
    public ?int $orderId = null;
    public string $reason = '';
    public string $refundAmount = '';
    public string $accountNumber = '';
    public string $accountHolderName = '';
    public string $bankName = '';
    public string $notes = '';

    public function mount(): void
    {
        if (request()->has('order_id')) {
            $order = ProductOrder::where('id', request()->get('order_id'))
                ->where('customer_id', auth()->id())
                ->first();

            if ($order) {
                $this->orderId = $order->id;
                $this->refundAmount = number_format($order->total_amount, 2, '.', '');
            }
        }
    }

    public function getEligibleOrders()
    {
        return ProductOrder::where('customer_id', auth()->id())
            ->whereIn('status', ['delivered', 'shipped', 'completed'])
            ->whereDoesntHave('returnRefunds', function ($query) {
                $query->whereNotIn('status', ['rejected', 'cancelled']);
            })
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function getSelectedOrder()
    {
        if (!$this->orderId) {
            return null;
        }

        return ProductOrder::where('id', $this->orderId)
            ->where('customer_id', auth()->id())
            ->with('items')
            ->first();
    }

    public function updatedOrderId(): void
    {
        $order = $this->getSelectedOrder();
        if ($order) {
            $this->refundAmount = number_format($order->total_amount, 2, '.', '');
        }
    }

    public function submit(): void
    {
        $this->validate([
            'orderId' => 'required|exists:product_orders,id',
            'reason' => 'required|min:10|max:1000',
            'refundAmount' => 'required|numeric|min:0.01',
            'bankName' => 'required|string|max:100',
            'accountNumber' => 'required|string|max:50',
            'accountHolderName' => 'required|string|max:100',
        ], [
            'orderId.required' => 'Please select an order.',
            'reason.required' => 'Please provide a reason for the refund.',
            'reason.min' => 'Please provide more details (at least 10 characters).',
            'refundAmount.required' => 'Please enter the refund amount.',
            'bankName.required' => 'Please enter your bank name.',
            'accountNumber.required' => 'Please enter your account number.',
            'accountHolderName.required' => 'Please enter the account holder name.',
        ]);

        $order = ProductOrder::where('id', $this->orderId)
            ->where('customer_id', auth()->id())
            ->firstOrFail();

        if ($this->refundAmount > $order->total_amount) {
            $this->addError('refundAmount', 'Refund amount cannot exceed the order total.');
            return;
        }

        $refund = ReturnRefund::create([
            'refund_number' => ReturnRefund::generateRefundNumber(),
            'order_id' => $order->id,
            'customer_id' => auth()->id(),
            'return_date' => now(),
            'reason' => $this->reason,
            'refund_amount' => $this->refundAmount,
            'bank_name' => $this->bankName,
            'account_number' => $this->accountNumber,
            'account_holder_name' => $this->accountHolderName,
            'notes' => $this->notes,
            'action' => 'pending',
            'status' => 'pending_review',
        ]);

        session()->flash('success', 'Your refund request has been submitted successfully. We will review it shortly.');

        $this->redirect(route('student.refund-requests.show', $refund));
    }
}; ?>

<div>
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading size="xl">Request a Refund</flux:heading>
            <flux:text class="mt-2">Submit a refund request for your order</flux:text>
        </div>
        <flux:button variant="ghost" href="{{ route('student.refund-requests') }}">
            <div class="flex items-center justify-center">
                <flux:icon name="chevron-left" class="w-4 h-4 mr-1" />
                Back to Requests
            </div>
        </flux:button>
    </div>

    @php $eligibleOrders = $this->getEligibleOrders(); @endphp

    @if($eligibleOrders->count() > 0)
        <form wire:submit="submit">
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Main Form -->
                <div class="lg:col-span-2 space-y-6">
                    <!-- Select Order -->
                    <flux:card>
                        <flux:heading size="lg" class="mb-4">Select Order</flux:heading>

                        <div class="space-y-3">
                            @foreach($eligibleOrders as $order)
                                <label class="flex items-start gap-4 p-4 border rounded-lg cursor-pointer hover:bg-gray-50 transition-colors {{ $orderId == $order->id ? 'border-cyan-500 bg-cyan-50' : 'border-gray-200' }}">
                                    <input type="radio" wire:model.live="orderId" value="{{ $order->id }}" class="mt-1" />
                                    <div class="flex-1">
                                        <div class="flex items-center justify-between">
                                            <flux:text class="font-semibold">{{ $order->order_number }}</flux:text>
                                            <flux:text class="font-bold text-green-600">RM {{ number_format($order->total_amount, 2) }}</flux:text>
                                        </div>
                                        <div class="flex items-center gap-4 mt-1 text-sm text-gray-500">
                                            <span>{{ $order->created_at->format('M j, Y') }}</span>
                                            <flux:badge size="xs">{{ ucfirst($order->status) }}</flux:badge>
                                            <span>{{ $order->items->count() }} item(s)</span>
                                        </div>
                                    </div>
                                </label>
                            @endforeach
                        </div>
                        @error('orderId') <flux:text class="text-red-500 text-sm mt-2">{{ $message }}</flux:text> @enderror
                    </flux:card>

                    <!-- Reason -->
                    <flux:card>
                        <flux:heading size="lg" class="mb-4">Reason for Refund</flux:heading>

                        <div class="space-y-4">
                            <div>
                                <flux:label>Why are you requesting a refund?</flux:label>
                                <flux:textarea wire:model="reason" rows="4" placeholder="Please describe the reason for your refund request in detail..." />
                                @error('reason') <flux:text class="text-red-500 text-sm mt-1">{{ $message }}</flux:text> @enderror
                            </div>

                            <div>
                                <flux:label>Additional Notes (Optional)</flux:label>
                                <flux:textarea wire:model="notes" rows="2" placeholder="Any additional information..." />
                            </div>
                        </div>
                    </flux:card>

                    <!-- Bank Details -->
                    <flux:card>
                        <flux:heading size="lg" class="mb-4">Bank Details for Refund</flux:heading>
                        <flux:text class="text-gray-500 mb-4">Please provide your bank account details where the refund should be deposited.</flux:text>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <flux:label>Bank Name</flux:label>
                                <flux:input wire:model="bankName" placeholder="e.g., Maybank, CIMB, Public Bank" />
                                @error('bankName') <flux:text class="text-red-500 text-sm mt-1">{{ $message }}</flux:text> @enderror
                            </div>
                            <div>
                                <flux:label>Account Number</flux:label>
                                <flux:input wire:model="accountNumber" placeholder="Your bank account number" />
                                @error('accountNumber') <flux:text class="text-red-500 text-sm mt-1">{{ $message }}</flux:text> @enderror
                            </div>
                            <div class="md:col-span-2">
                                <flux:label>Account Holder Name</flux:label>
                                <flux:input wire:model="accountHolderName" placeholder="Name as shown on bank account" />
                                @error('accountHolderName') <flux:text class="text-red-500 text-sm mt-1">{{ $message }}</flux:text> @enderror
                            </div>
                        </div>
                    </flux:card>
                </div>

                <!-- Sidebar -->
                <div class="space-y-6">
                    <!-- Order Summary -->
                    @if($orderId)
                        @php $selectedOrder = $this->getSelectedOrder(); @endphp
                        @if($selectedOrder)
                            <flux:card>
                                <flux:heading size="lg" class="mb-4">Order Summary</flux:heading>

                                <div class="space-y-3">
                                    <div class="flex justify-between">
                                        <flux:text class="text-gray-500">Order Number</flux:text>
                                        <flux:text class="font-medium">{{ $selectedOrder->order_number }}</flux:text>
                                    </div>
                                    <div class="flex justify-between">
                                        <flux:text class="text-gray-500">Order Date</flux:text>
                                        <flux:text>{{ $selectedOrder->created_at->format('M j, Y') }}</flux:text>
                                    </div>
                                    <div class="flex justify-between">
                                        <flux:text class="text-gray-500">Order Total</flux:text>
                                        <flux:text class="font-semibold">RM {{ number_format($selectedOrder->total_amount, 2) }}</flux:text>
                                    </div>

                                    @if($selectedOrder->items->count() > 0)
                                        <div class="pt-3 border-t mt-3">
                                            <flux:text class="text-gray-500 mb-2">Items</flux:text>
                                            @foreach($selectedOrder->items->take(3) as $item)
                                                <div class="flex justify-between text-sm mb-1">
                                                    <span class="truncate flex-1">{{ $item->product_name }}</span>
                                                    <span class="text-gray-500 ml-2">x{{ $item->quantity_ordered }}</span>
                                                </div>
                                            @endforeach
                                            @if($selectedOrder->items->count() > 3)
                                                <flux:text size="xs" class="text-gray-400">+{{ $selectedOrder->items->count() - 3 }} more</flux:text>
                                            @endif
                                        </div>
                                    @endif
                                </div>
                            </flux:card>
                        @endif
                    @endif

                    <!-- Refund Amount -->
                    <flux:card>
                        <flux:heading size="lg" class="mb-4">Refund Amount</flux:heading>

                        <div>
                            <flux:label>Amount to Refund (RM)</flux:label>
                            <flux:input wire:model="refundAmount" type="number" step="0.01" min="0.01" placeholder="0.00" />
                            @error('refundAmount') <flux:text class="text-red-500 text-sm mt-1">{{ $message }}</flux:text> @enderror
                        </div>
                    </flux:card>

                    <!-- Submit -->
                    <flux:card>
                        <flux:button type="submit" variant="primary" class="w-full">
                            <div class="flex items-center justify-center">
                                <flux:icon name="paper-airplane" class="w-4 h-4 mr-2" />
                                Submit Request
                            </div>
                        </flux:button>
                        <flux:text size="sm" class="text-gray-500 text-center mt-3">
                            Your request will be reviewed within 1-3 business days.
                        </flux:text>
                    </flux:card>
                </div>
            </div>
        </form>
    @else
        <flux:card>
            <div class="text-center py-12">
                <flux:icon name="shopping-bag" class="w-12 h-12 text-gray-300 mx-auto mb-4" />
                <flux:heading size="lg">No Eligible Orders</flux:heading>
                <flux:text class="text-gray-500 mt-2">
                    You don't have any orders that are eligible for refund requests.
                    Orders must be delivered, shipped, or completed to request a refund.
                </flux:text>
                <flux:button variant="outline" href="{{ route('student.orders') }}" class="mt-6">
                    View My Orders
                </flux:button>
            </div>
        </flux:card>
    @endif
</div>
