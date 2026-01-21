<?php

use App\Models\ReturnRefund;
use App\Models\ProductOrder;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    public string $statusFilter = '';

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function getRefundRequests()
    {
        $userId = auth()->id();

        return ReturnRefund::query()
            ->with(['order', 'processedBy'])
            ->where('customer_id', $userId)
            ->when($this->statusFilter, fn($q) => $q->where('status', $this->statusFilter))
            ->orderBy('created_at', 'desc')
            ->paginate(10);
    }

    public function getStats(): array
    {
        $userId = auth()->id();

        return [
            'total' => ReturnRefund::where('customer_id', $userId)->count(),
            'pending' => ReturnRefund::where('customer_id', $userId)->where('action', 'pending')->count(),
            'approved' => ReturnRefund::where('customer_id', $userId)->where('action', 'approved')->count(),
            'completed' => ReturnRefund::where('customer_id', $userId)->where('status', 'refund_completed')->count(),
        ];
    }

    public function getEligibleOrders()
    {
        $userId = auth()->id();

        return ProductOrder::where('customer_id', $userId)
            ->whereIn('status', ['delivered', 'shipped', 'completed'])
            ->whereDoesntHave('returnRefunds', function ($query) {
                $query->whereNotIn('status', ['rejected', 'cancelled']);
            })
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();
    }
}; ?>

<div>
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading size="xl">Refund Requests</flux:heading>
            <flux:text class="mt-2">View and manage your refund requests</flux:text>
        </div>
        <flux:button variant="primary" href="{{ route('student.refund-requests.create') }}">
            <div class="flex items-center justify-center">
                <flux:icon name="plus" class="w-4 h-4 mr-2" />
                New Request
            </div>
        </flux:button>
    </div>

    @php $stats = $this->getStats(); @endphp

    <!-- Stats Cards -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <flux:card class="p-4">
            <div class="text-center">
                <p class="text-2xl font-bold text-gray-700">{{ number_format($stats['total']) }}</p>
                <p class="text-sm text-gray-500 mt-1">Total Requests</p>
            </div>
        </flux:card>
        <flux:card class="p-4">
            <div class="text-center">
                <p class="text-2xl font-bold text-yellow-600">{{ number_format($stats['pending']) }}</p>
                <p class="text-sm text-gray-500 mt-1">Pending</p>
            </div>
        </flux:card>
        <flux:card class="p-4">
            <div class="text-center">
                <p class="text-2xl font-bold text-green-600">{{ number_format($stats['approved']) }}</p>
                <p class="text-sm text-gray-500 mt-1">Approved</p>
            </div>
        </flux:card>
        <flux:card class="p-4">
            <div class="text-center">
                <p class="text-2xl font-bold text-blue-600">{{ number_format($stats['completed']) }}</p>
                <p class="text-sm text-gray-500 mt-1">Completed</p>
            </div>
        </flux:card>
    </div>

    <!-- Filter -->
    <flux:card class="mb-6">
        <div class="flex items-center gap-4">
            <flux:select wire:model.live="statusFilter" class="w-48">
                <option value="">All Status</option>
                <option value="pending_review">Pending Review</option>
                <option value="approved_pending_return">Approved - Pending Return</option>
                <option value="item_received">Item Received</option>
                <option value="refund_processing">Refund Processing</option>
                <option value="refund_completed">Completed</option>
                <option value="rejected">Rejected</option>
                <option value="cancelled">Cancelled</option>
            </flux:select>
            @if($statusFilter)
                <flux:button variant="ghost" size="sm" wire:click="$set('statusFilter', '')">
                    Clear Filter
                </flux:button>
            @endif
        </div>
    </flux:card>

    <!-- Refund Requests List -->
    @php $refundRequests = $this->getRefundRequests(); @endphp

    @if($refundRequests->count() > 0)
        <div class="space-y-4">
            @foreach($refundRequests as $refund)
                <flux:card>
                    <div class="flex items-start justify-between">
                        <div class="flex-1">
                            <div class="flex items-center gap-3 mb-2">
                                <flux:heading size="md">{{ $refund->refund_number }}</flux:heading>
                                <flux:badge size="sm" color="{{ $refund->getStatusColor() }}">
                                    {{ $refund->getStatusLabel() }}
                                </flux:badge>
                                <flux:badge size="sm" color="{{ $refund->getActionColor() }}">
                                    {{ $refund->getActionLabel() }}
                                </flux:badge>
                            </div>

                            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mt-4">
                                <div>
                                    <flux:text size="sm" class="text-gray-500">Order</flux:text>
                                    <flux:text class="font-medium">{{ $refund->order?->order_number ?? 'N/A' }}</flux:text>
                                </div>
                                <div>
                                    <flux:text size="sm" class="text-gray-500">Refund Amount</flux:text>
                                    <flux:text class="font-semibold text-green-600">RM {{ number_format($refund->refund_amount, 2) }}</flux:text>
                                </div>
                                <div>
                                    <flux:text size="sm" class="text-gray-500">Submitted</flux:text>
                                    <flux:text>{{ $refund->created_at->format('M j, Y') }}</flux:text>
                                </div>
                                <div>
                                    <flux:text size="sm" class="text-gray-500">Return Date</flux:text>
                                    <flux:text>{{ $refund->return_date->format('M j, Y') }}</flux:text>
                                </div>
                            </div>

                            @if($refund->reason)
                                <div class="mt-4 p-3 bg-gray-50 rounded-lg">
                                    <flux:text size="sm" class="text-gray-500">Reason</flux:text>
                                    <flux:text class="mt-1">{{ $refund->reason }}</flux:text>
                                </div>
                            @endif

                            @if($refund->action_reason && $refund->action !== 'pending')
                                <div class="mt-4 p-3 {{ $refund->action === 'approved' ? 'bg-green-50' : 'bg-red-50' }} rounded-lg">
                                    <flux:text size="sm" class="{{ $refund->action === 'approved' ? 'text-green-600' : 'text-red-600' }}">
                                        Admin Response
                                    </flux:text>
                                    <flux:text class="mt-1">{{ $refund->action_reason }}</flux:text>
                                </div>
                            @endif
                        </div>

                        <div class="ml-4">
                            <flux:button variant="ghost" size="sm" href="{{ route('student.refund-requests.show', $refund) }}">
                                View Details
                            </flux:button>
                        </div>
                    </div>
                </flux:card>
            @endforeach
        </div>

        @if($refundRequests->hasPages())
            <div class="mt-6">
                {{ $refundRequests->links() }}
            </div>
        @endif
    @else
        <flux:card>
            <div class="text-center py-12">
                <flux:icon name="arrow-path" class="w-12 h-12 text-gray-300 mx-auto mb-4" />
                <flux:heading size="lg">No Refund Requests</flux:heading>
                <flux:text class="text-gray-500 mt-2">You haven't submitted any refund requests yet.</flux:text>

                @php $eligibleOrders = $this->getEligibleOrders(); @endphp
                @if($eligibleOrders->count() > 0)
                    <div class="mt-6">
                        <flux:button variant="primary" href="{{ route('student.refund-requests.create') }}">
                            Request a Refund
                        </flux:button>
                    </div>
                @endif
            </div>
        </flux:card>
    @endif
</div>
