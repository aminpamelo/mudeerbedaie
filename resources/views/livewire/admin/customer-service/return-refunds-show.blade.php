<?php

use App\Models\ReturnRefund;
use Livewire\Volt\Component;

new class extends Component
{
    public function layout()
    {
        return 'components.layouts.app.sidebar';
    }

    public ReturnRefund $refund;

    // Action modal states
    public bool $showApproveModal = false;
    public bool $showRejectModal = false;
    public bool $showStatusModal = false;

    // Form fields
    public string $actionReason = '';
    public string $newStatus = '';
    public string $trackingNumber = '';
    public string $accountNumber = '';
    public string $accountHolderName = '';
    public string $bankName = '';
    public string $notes = '';

    public function mount(ReturnRefund $refund): void
    {
        $this->refund = $refund->load(['order.items', 'package', 'customer', 'processedBy']);
        $this->trackingNumber = $refund->tracking_number ?? '';
        $this->accountNumber = $refund->account_number ?? '';
        $this->accountHolderName = $refund->account_holder_name ?? '';
        $this->bankName = $refund->bank_name ?? '';
        $this->notes = $refund->notes ?? '';
    }

    public function approveRefund(): void
    {
        $this->validate([
            'actionReason' => 'nullable|string|max:500',
        ]);

        $this->refund->approve(auth()->user(), $this->actionReason ?: null);
        $this->showApproveModal = false;
        $this->actionReason = '';

        $this->dispatch('refund-updated', message: 'Refund request approved successfully');
        $this->refund->refresh();
    }

    public function rejectRefund(): void
    {
        $this->validate([
            'actionReason' => 'required|string|max:500',
        ]);

        $this->refund->reject(auth()->user(), $this->actionReason);
        $this->showRejectModal = false;
        $this->actionReason = '';

        $this->dispatch('refund-updated', message: 'Refund request rejected');
        $this->refund->refresh();
    }

    public function updateStatus(): void
    {
        $this->validate([
            'newStatus' => 'required|in:approved_pending_return,item_received,refund_processing,refund_completed,cancelled',
        ]);

        $this->refund->update(['status' => $this->newStatus]);
        $this->showStatusModal = false;

        $this->dispatch('refund-updated', message: 'Status updated successfully');
        $this->refund->refresh();
    }

    public function updateDetails(): void
    {
        $this->validate([
            'trackingNumber' => 'nullable|string|max:100',
            'accountNumber' => 'nullable|string|max:50',
            'accountHolderName' => 'nullable|string|max:100',
            'bankName' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:1000',
        ]);

        $this->refund->update([
            'tracking_number' => $this->trackingNumber ?: null,
            'account_number' => $this->accountNumber ?: null,
            'account_holder_name' => $this->accountHolderName ?: null,
            'bank_name' => $this->bankName ?: null,
            'notes' => $this->notes ?: null,
        ]);

        $this->dispatch('refund-updated', message: 'Details updated successfully');
        $this->refund->refresh();
    }

    public function getAvailableStatuses(): array
    {
        return [
            'approved_pending_return' => 'Approved - Pending Return',
            'item_received' => 'Item Received',
            'refund_processing' => 'Refund Processing',
            'refund_completed' => 'Refund Completed',
            'cancelled' => 'Cancelled',
        ];
    }
}; ?>

<div>
    <!-- Header -->
    <div class="mb-6">
        <div class="flex items-center gap-2 mb-4">
            <flux:button variant="ghost" size="sm" :href="route('admin.customer-service.return-refunds.index')" wire:navigate>
                <div class="flex items-center justify-center">
                    <flux:icon name="chevron-left" class="w-4 h-4 mr-1" />
                    Back to List
                </div>
            </flux:button>
        </div>

        <div class="flex items-center justify-between">
            <div>
                <div class="flex items-center gap-3">
                    <flux:heading size="xl">{{ $refund->refund_number }}</flux:heading>
                    <flux:badge size="lg" color="{{ $refund->getActionColor() }}">
                        {{ $refund->getActionLabel() }}
                    </flux:badge>
                    <flux:badge size="lg" color="{{ $refund->getStatusColor() }}">
                        {{ $refund->getStatusLabel() }}
                    </flux:badge>
                </div>
                <flux:text class="mt-2">Created {{ $refund->created_at->format('M j, Y \a\t g:i A') }}</flux:text>
            </div>

            <div class="flex gap-3">
                @if($refund->isPending())
                    <flux:button variant="primary" wire:click="$set('showApproveModal', true)">
                        <div class="flex items-center justify-center">
                            <flux:icon name="check" class="w-4 h-4 mr-2" />
                            Approve
                        </div>
                    </flux:button>
                    <flux:button variant="danger" wire:click="$set('showRejectModal', true)">
                        <div class="flex items-center justify-center">
                            <flux:icon name="x-mark" class="w-4 h-4 mr-2" />
                            Reject
                        </div>
                    </flux:button>
                @elseif($refund->isApproved() && $refund->status !== 'refund_completed')
                    <flux:button variant="outline" wire:click="$set('showStatusModal', true)">
                        <div class="flex items-center justify-center">
                            <flux:icon name="arrow-path" class="w-4 h-4 mr-2" />
                            Update Status
                        </div>
                    </flux:button>
                @endif
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Main Content -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Refund Details -->
            <div class="bg-white dark:bg-zinc-800 rounded-lg border border-gray-200 dark:border-zinc-700">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-zinc-700">
                    <flux:heading size="lg">Refund Details</flux:heading>
                </div>
                <div class="p-6">
                    <dl class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Return Date</dt>
                            <dd class="mt-1 text-lg font-semibold">{{ $refund->return_date->format('M j, Y') }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Refund Amount</dt>
                            <dd class="mt-1 text-lg font-semibold text-green-600">RM {{ number_format($refund->refund_amount, 2) }}</dd>
                        </div>
                        <div class="md:col-span-2">
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Reason for Return</dt>
                            <dd class="mt-1 text-gray-900 dark:text-gray-100">{{ $refund->reason ?? 'No reason provided' }}</dd>
                        </div>
                        @if($refund->action_reason)
                            <div class="md:col-span-2">
                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Action Reason ({{ $refund->getActionLabel() }})</dt>
                                <dd class="mt-1 text-gray-900 dark:text-gray-100">{{ $refund->action_reason }}</dd>
                            </div>
                        @endif
                        @if($refund->action_date)
                            <div>
                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Action Date</dt>
                                <dd class="mt-1 text-gray-900 dark:text-gray-100">{{ $refund->action_date->format('M j, Y \a\t g:i A') }}</dd>
                            </div>
                        @endif
                        @if($refund->processedBy)
                            <div>
                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Processed By</dt>
                                <dd class="mt-1 text-gray-900 dark:text-gray-100">{{ $refund->processedBy->name }}</dd>
                            </div>
                        @endif
                    </dl>
                </div>
            </div>

            <!-- Order Information -->
            @if($refund->order)
                <div class="bg-white dark:bg-zinc-800 rounded-lg border border-gray-200 dark:border-zinc-700">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-zinc-700 flex items-center justify-between">
                        <flux:heading size="lg">Order Information</flux:heading>
                        <flux:button variant="ghost" size="sm" :href="route('admin.orders.show', $refund->order)" wire:navigate>
                            View Order
                        </flux:button>
                    </div>
                    <div class="p-6">
                        <dl class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Order Number</dt>
                                <dd class="mt-1 text-lg font-semibold">{{ $refund->order->order_number }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Order Total</dt>
                                <dd class="mt-1 text-lg font-semibold">RM {{ number_format($refund->order->total_amount, 2) }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Order Date</dt>
                                <dd class="mt-1 text-gray-900 dark:text-gray-100">{{ $refund->order->created_at->format('M j, Y') }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Order Status</dt>
                                <dd class="mt-1">
                                    <flux:badge size="sm">{{ ucfirst($refund->order->status) }}</flux:badge>
                                </dd>
                            </div>
                        </dl>

                        @if($refund->order->items->count() > 0)
                            <div class="mt-6">
                                <h4 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-3">Order Items</h4>
                                <div class="border rounded-lg overflow-hidden">
                                    <table class="min-w-full divide-y divide-gray-200 dark:divide-zinc-700">
                                        <thead class="bg-gray-50 dark:bg-zinc-700/50">
                                            <tr>
                                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Product</th>
                                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Qty</th>
                                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Price</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-200 dark:divide-zinc-700">
                                            @foreach($refund->order->items as $item)
                                                <tr>
                                                    <td class="px-4 py-3 text-sm">{{ $item->product_name }}</td>
                                                    <td class="px-4 py-3 text-sm">{{ $item->quantity_ordered }}</td>
                                                    <td class="px-4 py-3 text-sm text-right">RM {{ number_format($item->total_price, 2) }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            @endif

            <!-- Package Information -->
            @if($refund->package)
                <div class="bg-white dark:bg-zinc-800 rounded-lg border border-gray-200 dark:border-zinc-700">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-zinc-700 flex items-center justify-between">
                        <flux:heading size="lg">Package Information</flux:heading>
                        <flux:button variant="ghost" size="sm" :href="route('packages.show', $refund->package)" wire:navigate>
                            View Package
                        </flux:button>
                    </div>
                    <div class="p-6">
                        <dl class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Package Name</dt>
                                <dd class="mt-1 text-lg font-semibold">{{ $refund->package->name }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Package Price</dt>
                                <dd class="mt-1 text-lg font-semibold">RM {{ number_format($refund->package->price, 2) }}</dd>
                            </div>
                            @if($refund->package->description)
                                <div class="md:col-span-2">
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Description</dt>
                                    <dd class="mt-1 text-gray-900 dark:text-gray-100">{{ $refund->package->description }}</dd>
                                </div>
                            @endif
                        </dl>
                    </div>
                </div>
            @endif

            <!-- Tracking & Bank Details Form -->
            <div class="bg-white dark:bg-zinc-800 rounded-lg border border-gray-200 dark:border-zinc-700">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-zinc-700">
                    <flux:heading size="lg">Tracking & Bank Details</flux:heading>
                </div>
                <form wire:submit="updateDetails" class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <flux:label for="trackingNumber">Tracking Number</flux:label>
                            <flux:input wire:model="trackingNumber" id="trackingNumber" placeholder="Enter tracking number" />
                        </div>
                        <div>
                            <flux:label for="bankName">Bank Name</flux:label>
                            <flux:input wire:model="bankName" id="bankName" placeholder="e.g., Maybank, CIMB" />
                        </div>
                        <div>
                            <flux:label for="accountNumber">Account Number</flux:label>
                            <flux:input wire:model="accountNumber" id="accountNumber" placeholder="Enter bank account number" />
                        </div>
                        <div>
                            <flux:label for="accountHolderName">Account Holder Name</flux:label>
                            <flux:input wire:model="accountHolderName" id="accountHolderName" placeholder="Enter account holder name" />
                        </div>
                        <div class="md:col-span-2">
                            <flux:label for="notes">Internal Notes</flux:label>
                            <flux:textarea wire:model="notes" id="notes" rows="3" placeholder="Add any internal notes..." />
                        </div>
                    </div>
                    <div class="mt-6 flex justify-end">
                        <flux:button type="submit" variant="primary">
                            Save Details
                        </flux:button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="space-y-6">
            <!-- Customer Information -->
            <div class="bg-white dark:bg-zinc-800 rounded-lg border border-gray-200 dark:border-zinc-700">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-zinc-700">
                    <flux:heading size="lg">Customer</flux:heading>
                </div>
                <div class="p-6">
                    @if($refund->customer)
                        <div class="flex items-center gap-4 mb-4">
                            <div class="w-12 h-12 bg-gray-200 dark:bg-zinc-700 rounded-full flex items-center justify-center">
                                <flux:icon name="user" class="w-6 h-6 text-gray-500" />
                            </div>
                            <div>
                                <flux:text class="font-semibold">{{ $refund->customer->name }}</flux:text>
                                <flux:text size="sm" class="text-gray-500">{{ $refund->customer->email }}</flux:text>
                            </div>
                        </div>
                        @if($refund->customer->phone)
                            <div class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400">
                                <flux:icon name="phone" class="w-4 h-4" />
                                {{ $refund->customer->phone }}
                            </div>
                        @endif
                    @else
                        <flux:text class="text-gray-500">No customer information</flux:text>
                    @endif
                </div>
            </div>

            <!-- Status Timeline -->
            <div class="bg-white dark:bg-zinc-800 rounded-lg border border-gray-200 dark:border-zinc-700">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-zinc-700">
                    <flux:heading size="lg">Status Timeline</flux:heading>
                </div>
                <div class="p-6">
                    <div class="space-y-4">
                        <div class="flex items-start gap-3">
                            <div class="w-8 h-8 rounded-full bg-green-100 flex items-center justify-center flex-shrink-0">
                                <flux:icon name="document-plus" class="w-4 h-4 text-green-600" />
                            </div>
                            <div>
                                <flux:text class="font-medium">Request Created</flux:text>
                                <flux:text size="sm" class="text-gray-500">{{ $refund->created_at->format('M j, Y g:i A') }}</flux:text>
                            </div>
                        </div>

                        @if($refund->action !== 'pending')
                            <div class="flex items-start gap-3">
                                <div class="w-8 h-8 rounded-full {{ $refund->isApproved() ? 'bg-green-100' : 'bg-red-100' }} flex items-center justify-center flex-shrink-0">
                                    <flux:icon name="{{ $refund->isApproved() ? 'check' : 'x-mark' }}" class="w-4 h-4 {{ $refund->isApproved() ? 'text-green-600' : 'text-red-600' }}" />
                                </div>
                                <div>
                                    <flux:text class="font-medium">{{ $refund->getActionLabel() }}</flux:text>
                                    <flux:text size="sm" class="text-gray-500">{{ $refund->action_date?->format('M j, Y g:i A') }}</flux:text>
                                    @if($refund->processedBy)
                                        <flux:text size="sm" class="text-gray-500">by {{ $refund->processedBy->name }}</flux:text>
                                    @endif
                                </div>
                            </div>
                        @endif

                        @if($refund->status === 'refund_completed')
                            <div class="flex items-start gap-3">
                                <div class="w-8 h-8 rounded-full bg-green-100 flex items-center justify-center flex-shrink-0">
                                    <flux:icon name="banknotes" class="w-4 h-4 text-green-600" />
                                </div>
                                <div>
                                    <flux:text class="font-medium">Refund Completed</flux:text>
                                    <flux:text size="sm" class="text-gray-500">{{ $refund->updated_at->format('M j, Y g:i A') }}</flux:text>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            @if($refund->isApproved() && !$refund->isRefundCompleted())
                <div class="bg-white dark:bg-zinc-800 rounded-lg border border-gray-200 dark:border-zinc-700">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-zinc-700">
                        <flux:heading size="lg">Quick Actions</flux:heading>
                    </div>
                    <div class="p-6 space-y-3">
                        @if($refund->status === 'approved_pending_return')
                            <flux:button variant="outline" class="w-full" wire:click="$set('newStatus', 'item_received'); $set('showStatusModal', true)">
                                Mark Item Received
                            </flux:button>
                        @endif
                        @if($refund->status === 'item_received')
                            <flux:button variant="outline" class="w-full" wire:click="$set('newStatus', 'refund_processing'); $set('showStatusModal', true)">
                                Start Refund Processing
                            </flux:button>
                        @endif
                        @if($refund->status === 'refund_processing')
                            <flux:button variant="primary" class="w-full" wire:click="$set('newStatus', 'refund_completed'); $set('showStatusModal', true)">
                                Mark Refund Completed
                            </flux:button>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    </div>

    <!-- Approve Modal -->
    <flux:modal wire:model="showApproveModal" class="max-w-md">
        <div class="p-6">
            <flux:heading size="lg" class="mb-4">Approve Refund Request</flux:heading>
            <flux:text class="mb-4 text-gray-600">
                Are you sure you want to approve this refund request for <strong>RM {{ number_format($refund->refund_amount, 2) }}</strong>?
            </flux:text>
            <div class="mb-6">
                <flux:label for="approveReason">Reason (Optional)</flux:label>
                <flux:textarea wire:model="actionReason" id="approveReason" rows="3" placeholder="Add a note about why this was approved..." />
            </div>
            <div class="flex justify-end gap-3">
                <flux:button variant="ghost" wire:click="$set('showApproveModal', false)">Cancel</flux:button>
                <flux:button variant="primary" wire:click="approveRefund">Approve</flux:button>
            </div>
        </div>
    </flux:modal>

    <!-- Reject Modal -->
    <flux:modal wire:model="showRejectModal" class="max-w-md">
        <div class="p-6">
            <flux:heading size="lg" class="mb-4">Reject Refund Request</flux:heading>
            <flux:text class="mb-4 text-gray-600">
                Please provide a reason for rejecting this refund request.
            </flux:text>
            <div class="mb-6">
                <flux:label for="rejectReason">Reason *</flux:label>
                <flux:textarea wire:model="actionReason" id="rejectReason" rows="3" placeholder="Explain why this refund is being rejected..." required />
                @error('actionReason') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
            </div>
            <div class="flex justify-end gap-3">
                <flux:button variant="ghost" wire:click="$set('showRejectModal', false)">Cancel</flux:button>
                <flux:button variant="danger" wire:click="rejectRefund">Reject</flux:button>
            </div>
        </div>
    </flux:modal>

    <!-- Status Update Modal -->
    <flux:modal wire:model="showStatusModal" class="max-w-md">
        <div class="p-6">
            <flux:heading size="lg" class="mb-4">Update Status</flux:heading>
            <div class="mb-6">
                <flux:label for="newStatus">New Status</flux:label>
                <flux:select wire:model="newStatus" id="newStatus">
                    <option value="">Select Status</option>
                    @foreach($this->getAvailableStatuses() as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </flux:select>
                @error('newStatus') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
            </div>
            <div class="flex justify-end gap-3">
                <flux:button variant="ghost" wire:click="$set('showStatusModal', false)">Cancel</flux:button>
                <flux:button variant="primary" wire:click="updateStatus">Update</flux:button>
            </div>
        </div>
    </flux:modal>
</div>

<script>
    document.addEventListener('livewire:init', function () {
        Livewire.on('refund-updated', (event) => {
            // Can be replaced with toast notifications
            console.log(event.message);
        });
    });
</script>
