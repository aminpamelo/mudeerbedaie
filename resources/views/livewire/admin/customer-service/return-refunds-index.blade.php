<?php

use App\Models\ReturnRefund;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component
{
    public function layout()
    {
        return 'components.layouts.app.sidebar';
    }

    use WithPagination;

    public string $search = '';
    public string $decisionFilter = '';
    public string $statusFilter = '';
    public string $dateFilter = '';
    public string $sortBy = 'created_at';
    public string $sortDirection = 'desc';

    public function mount(): void
    {
        // Check for URL query parameters
        if (request()->has('decision')) {
            $this->decisionFilter = request()->get('decision');
        }
        if (request()->has('status')) {
            $this->statusFilter = request()->get('status');
        }
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingDecisionFilter(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatingDateFilter(): void
    {
        $this->resetPage();
    }

    public function sortBy(string $field): void
    {
        if ($this->sortBy === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $field;
            $this->sortDirection = 'asc';
        }
        $this->resetPage();
    }

    public function getRefunds()
    {
        return ReturnRefund::query()
            ->with(['order', 'package', 'customer', 'processedBy'])
            ->when($this->search, function ($query) {
                $query->search($this->search);
            })
            ->when($this->decisionFilter, function ($query) {
                $query->where('decision', $this->decisionFilter);
            })
            ->when($this->statusFilter, function ($query) {
                $query->where('status', $this->statusFilter);
            })
            ->when($this->dateFilter, function ($query) {
                match ($this->dateFilter) {
                    'today' => $query->whereDate('return_date', today()),
                    'week' => $query->whereBetween('return_date', [now()->startOfWeek(), now()->endOfWeek()]),
                    'month' => $query->whereMonth('return_date', now()->month)->whereYear('return_date', now()->year),
                    'year' => $query->whereYear('return_date', now()->year),
                    default => $query
                };
            })
            ->orderBy($this->sortBy, $this->sortDirection)
            ->paginate(20);
    }

    public function getStatusCount(string $status): int
    {
        if ($status === 'all') {
            return ReturnRefund::count();
        }
        return ReturnRefund::where('status', $status)->count();
    }

    public function getDecisionCount(string $decision): int
    {
        return ReturnRefund::where('decision', $decision)->count();
    }

    public function clearFilters(): void
    {
        $this->search = '';
        $this->decisionFilter = '';
        $this->statusFilter = '';
        $this->dateFilter = '';
        $this->resetPage();
    }

    public function exportCsv()
    {
        $refunds = ReturnRefund::query()
            ->with(['order', 'package', 'customer'])
            ->when($this->search, fn($q) => $q->search($this->search))
            ->when($this->decisionFilter, fn($q) => $q->where('decision', $this->decisionFilter))
            ->when($this->statusFilter, fn($q) => $q->where('status', $this->statusFilter))
            ->orderBy($this->sortBy, $this->sortDirection)
            ->get();

        $filename = 'return-refunds-' . now()->format('Y-m-d') . '.csv';
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ];

        $callback = function() use ($refunds) {
            $file = fopen('php://output', 'w');
            fputcsv($file, [
                'Refund Number',
                'Order ID',
                'Package',
                'Customer',
                'Return Date',
                'Reason',
                'Refund Amount',
                'Decision',
                'Decision Reason',
                'Tracking Number',
                'Account Number',
                'Bank Name',
                'Status',
                'Created At'
            ]);

            foreach ($refunds as $refund) {
                fputcsv($file, [
                    $refund->refund_number,
                    $refund->getOrderNumber(),
                    $refund->getPackageName(),
                    $refund->getCustomerName(),
                    $refund->return_date->format('Y-m-d'),
                    $refund->reason,
                    $refund->refund_amount,
                    $refund->getDecisionLabel(),
                    $refund->decision_reason,
                    $refund->tracking_number,
                    $refund->account_number,
                    $refund->bank_name,
                    $refund->getStatusLabel(),
                    $refund->created_at->format('Y-m-d H:i:s'),
                ]);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}; ?>

<div>
    <!-- Header -->
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading size="xl">Return & Refund Requests</flux:heading>
            <flux:text class="mt-2">Manage all return and refund requests</flux:text>
        </div>

        <div class="flex gap-3">
            <flux:button variant="outline" wire:click="exportCsv">
                <div class="flex items-center justify-center">
                    <flux:icon name="arrow-down-tray" class="w-4 h-4 mr-2" />
                    Export CSV
                </div>
            </flux:button>
            <flux:button variant="primary" :href="route('admin.customer-service.return-refunds.create')" wire:navigate>
                <div class="flex items-center justify-center">
                    <flux:icon name="plus" class="w-4 h-4 mr-2" />
                    New Request
                </div>
            </flux:button>
        </div>
    </div>

    <!-- Decision Tabs -->
    <div class="mb-6 bg-white dark:bg-zinc-800 rounded-lg border border-gray-200 dark:border-zinc-700">
        <div class="border-b border-gray-200 dark:border-zinc-700">
            <nav class="flex gap-4 px-6" aria-label="Tabs">
                <button
                    wire:click="$set('decisionFilter', '')"
                    class="py-4 px-1 border-b-2 font-medium text-sm transition-colors {{ $decisionFilter === '' ? 'border-cyan-500 text-cyan-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}"
                >
                    All
                    <flux:badge size="sm" class="ml-2">{{ number_format($this->getStatusCount('all')) }}</flux:badge>
                </button>

                <button
                    wire:click="$set('decisionFilter', 'pending')"
                    class="py-4 px-1 border-b-2 font-medium text-sm transition-colors {{ $decisionFilter === 'pending' ? 'border-cyan-500 text-cyan-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}"
                >
                    Pending
                    <flux:badge size="sm" color="yellow" class="ml-2">{{ number_format($this->getDecisionCount('pending')) }}</flux:badge>
                </button>

                <button
                    wire:click="$set('decisionFilter', 'approved')"
                    class="py-4 px-1 border-b-2 font-medium text-sm transition-colors {{ $decisionFilter === 'approved' ? 'border-cyan-500 text-cyan-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}"
                >
                    Approved
                    <flux:badge size="sm" color="green" class="ml-2">{{ number_format($this->getDecisionCount('approved')) }}</flux:badge>
                </button>

                <button
                    wire:click="$set('decisionFilter', 'rejected')"
                    class="py-4 px-1 border-b-2 font-medium text-sm transition-colors {{ $decisionFilter === 'rejected' ? 'border-cyan-500 text-cyan-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}"
                >
                    Rejected
                    <flux:badge size="sm" color="red" class="ml-2">{{ number_format($this->getDecisionCount('rejected')) }}</flux:badge>
                </button>
            </nav>
        </div>
    </div>

    <!-- Filters -->
    <div class="mb-6 bg-white dark:bg-zinc-800 rounded-lg border border-gray-200 dark:border-zinc-700 p-4">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <!-- Search -->
            <div class="md:col-span-2">
                <flux:label>Search</flux:label>
                <flux:input
                    wire:model.live.debounce.300ms="search"
                    placeholder="Refund number, order number, customer..."
                    class="w-full"
                />
            </div>

            <!-- Status Filter -->
            <div>
                <flux:label>Status</flux:label>
                <flux:select wire:model.live="statusFilter" placeholder="All Statuses">
                    <option value="">All Statuses</option>
                    <option value="pending_review">Pending Review</option>
                    <option value="approved_pending_return">Approved - Pending Return</option>
                    <option value="item_received">Item Received</option>
                    <option value="refund_processing">Refund Processing</option>
                    <option value="refund_completed">Refund Completed</option>
                    <option value="rejected">Rejected</option>
                    <option value="cancelled">Cancelled</option>
                </flux:select>
            </div>

            <!-- Date Filter -->
            <div>
                <flux:label>Period</flux:label>
                <flux:select wire:model.live="dateFilter" placeholder="All Time">
                    <option value="">All Time</option>
                    <option value="today">Today</option>
                    <option value="week">This Week</option>
                    <option value="month">This Month</option>
                    <option value="year">This Year</option>
                </flux:select>
            </div>
        </div>

        <!-- Filter Actions -->
        @if($search || $decisionFilter || $statusFilter || $dateFilter)
            <div class="flex items-center justify-between mt-4 pt-4 border-t border-gray-200">
                <div class="flex items-center gap-2">
                    <flux:text size="sm" class="text-gray-600">Active filters:</flux:text>
                    @if($search)
                        <flux:badge color="gray">
                            Search: {{ Str::limit($search, 20) }}
                            <button wire:click="$set('search', '')" class="ml-1 hover:text-red-600">&times;</button>
                        </flux:badge>
                    @endif
                    @if($decisionFilter)
                        <flux:badge color="gray">
                            Decision: {{ ucfirst($decisionFilter) }}
                            <button wire:click="$set('decisionFilter', '')" class="ml-1 hover:text-red-600">&times;</button>
                        </flux:badge>
                    @endif
                    @if($statusFilter)
                        <flux:badge color="gray">
                            Status: {{ str_replace('_', ' ', ucfirst($statusFilter)) }}
                            <button wire:click="$set('statusFilter', '')" class="ml-1 hover:text-red-600">&times;</button>
                        </flux:badge>
                    @endif
                    @if($dateFilter)
                        <flux:badge color="gray">
                            Period: {{ ucfirst($dateFilter) }}
                            <button wire:click="$set('dateFilter', '')" class="ml-1 hover:text-red-600">&times;</button>
                        </flux:badge>
                    @endif
                    <flux:button variant="ghost" size="sm" wire:click="clearFilters">
                        Clear all
                    </flux:button>
                </div>
            </div>
        @endif
    </div>

    <!-- Table -->
    <div class="bg-white dark:bg-zinc-800 rounded-lg shadow-sm border border-gray-200 dark:border-zinc-700 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full border-collapse border-0">
                <thead class="bg-gray-50 dark:bg-zinc-700/50 border-b border-gray-200 dark:border-zinc-700">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            <button wire:click="sortBy('refund_number')" class="flex items-center space-x-1 hover:text-gray-700">
                                <span>Refund #</span>
                                @if($sortBy === 'refund_number')
                                    <flux:icon name="{{ $sortDirection === 'asc' ? 'chevron-up' : 'chevron-down' }}" class="w-4 h-4" />
                                @endif
                            </button>
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Order / Package
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            <button wire:click="sortBy('return_date')" class="flex items-center space-x-1 hover:text-gray-700">
                                <span>Return Date</span>
                                @if($sortBy === 'return_date')
                                    <flux:icon name="{{ $sortDirection === 'asc' ? 'chevron-up' : 'chevron-down' }}" class="w-4 h-4" />
                                @endif
                            </button>
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Reason
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            <button wire:click="sortBy('refund_amount')" class="flex items-center space-x-1 hover:text-gray-700">
                                <span>Amount</span>
                                @if($sortBy === 'refund_amount')
                                    <flux:icon name="{{ $sortDirection === 'asc' ? 'chevron-up' : 'chevron-down' }}" class="w-4 h-4" />
                                @endif
                            </button>
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Decision
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Tracking
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Status
                        </th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Actions
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-zinc-800">
                    @forelse($this->getRefunds() as $refund)
                        <tr class="border-b border-gray-200 dark:border-zinc-700 hover:bg-gray-50 dark:hover:bg-zinc-700/50" wire:key="refund-{{ $refund->id }}">
                            <!-- Refund Number -->
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div>
                                    <flux:text class="font-semibold">{{ $refund->refund_number }}</flux:text>
                                    <flux:text size="sm" class="text-gray-500">{{ $refund->getCustomerName() }}</flux:text>
                                </div>
                            </td>

                            <!-- Order / Package -->
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div>
                                    @if($refund->order)
                                        <flux:text class="font-medium">{{ $refund->order->order_number }}</flux:text>
                                    @endif
                                    @if($refund->package)
                                        <flux:badge size="xs" color="purple">{{ $refund->package->name }}</flux:badge>
                                    @endif
                                    @if(!$refund->order && !$refund->package)
                                        <flux:text class="text-gray-400">N/A</flux:text>
                                    @endif
                                </div>
                            </td>

                            <!-- Return Date -->
                            <td class="px-6 py-4 whitespace-nowrap">
                                <flux:text>{{ $refund->return_date->format('M j, Y') }}</flux:text>
                            </td>

                            <!-- Reason -->
                            <td class="px-6 py-4">
                                <flux:text size="sm" class="max-w-xs truncate">{{ Str::limit($refund->reason, 40) }}</flux:text>
                            </td>

                            <!-- Amount -->
                            <td class="px-6 py-4 whitespace-nowrap">
                                <flux:text class="font-semibold">RM {{ number_format($refund->refund_amount, 2) }}</flux:text>
                            </td>

                            <!-- Decision -->
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center gap-2">
                                    <flux:badge size="sm" color="{{ $refund->getDecisionColor() }}">
                                        {{ $refund->getDecisionLabel() }}
                                    </flux:badge>
                                    @if($refund->attachments && count($refund->attachments) > 0)
                                        <span class="inline-flex items-center text-gray-500" title="{{ count($refund->attachments) }} attachment(s)">
                                            <flux:icon name="paper-clip" class="w-4 h-4" />
                                            <span class="text-xs ml-0.5">{{ count($refund->attachments) }}</span>
                                        </span>
                                    @endif
                                </div>
                                @if($refund->decision_reason)
                                    <flux:text size="xs" class="text-gray-500 mt-1 block max-w-xs truncate">{{ Str::limit($refund->decision_reason, 30) }}</flux:text>
                                @endif
                            </td>

                            <!-- Tracking -->
                            <td class="px-6 py-4 whitespace-nowrap">
                                @if($refund->tracking_number)
                                    <flux:text size="sm">{{ $refund->tracking_number }}</flux:text>
                                @else
                                    <flux:text size="sm" class="text-gray-400">-</flux:text>
                                @endif
                            </td>

                            <!-- Status -->
                            <td class="px-6 py-4 whitespace-nowrap">
                                <flux:badge size="sm" color="{{ $refund->getStatusColor() }}">
                                    {{ $refund->getStatusLabel() }}
                                </flux:badge>
                            </td>

                            <!-- Actions -->
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <div class="flex items-center justify-end space-x-2">
                                    <flux:button variant="ghost" size="sm" href="{{ route('admin.customer-service.return-refunds.show', $refund) }}" wire:navigate>
                                        <flux:icon name="eye" class="w-4 h-4" />
                                    </flux:button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-6 py-12 text-center">
                                <div class="text-gray-500">
                                    <flux:icon name="arrow-path" class="w-12 h-12 mx-auto mb-4 text-gray-300" />
                                    <flux:text>No return/refund requests found</flux:text>
                                    @if($search || $decisionFilter || $statusFilter || $dateFilter)
                                        <flux:button variant="ghost" wire:click="clearFilters" class="mt-2">
                                            Clear filters
                                        </flux:button>
                                    @else
                                        <div class="mt-4">
                                            <flux:button variant="primary" :href="route('admin.customer-service.return-refunds.create')" wire:navigate>
                                                Create First Request
                                            </flux:button>
                                        </div>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        @if($this->getRefunds()->hasPages())
            <div class="px-6 py-4 border-t border-gray-200 dark:border-zinc-700 bg-gray-50 dark:bg-zinc-700/50">
                {{ $this->getRefunds()->links() }}
            </div>
        @endif
    </div>

    <!-- Summary Stats -->
    <div class="mt-6 grid grid-cols-1 md:grid-cols-4 gap-4">
        @php
            $totalRefunds = ReturnRefund::count();
            $pendingRefunds = ReturnRefund::where('decision', 'pending')->count();
            $totalApprovedAmount = ReturnRefund::where('decision', 'approved')->sum('refund_amount');
            $completedRefunds = ReturnRefund::where('status', 'refund_completed')->count();
        @endphp

        <div class="bg-white dark:bg-zinc-800 rounded-lg border border-gray-200 dark:border-zinc-700 p-4">
            <flux:text size="sm" class="text-gray-600">Total Requests</flux:text>
            <flux:text class="text-2xl font-bold">{{ number_format($totalRefunds) }}</flux:text>
        </div>

        <div class="bg-white dark:bg-zinc-800 rounded-lg border border-gray-200 dark:border-zinc-700 p-4">
            <flux:text size="sm" class="text-gray-600">Pending Review</flux:text>
            <flux:text class="text-2xl font-bold text-yellow-600">{{ number_format($pendingRefunds) }}</flux:text>
        </div>

        <div class="bg-white dark:bg-zinc-800 rounded-lg border border-gray-200 dark:border-zinc-700 p-4">
            <flux:text size="sm" class="text-gray-600">Completed Refunds</flux:text>
            <flux:text class="text-2xl font-bold text-green-600">{{ number_format($completedRefunds) }}</flux:text>
        </div>

        <div class="bg-white dark:bg-zinc-800 rounded-lg border border-gray-200 dark:border-zinc-700 p-4">
            <flux:text size="sm" class="text-gray-600">Total Approved Amount</flux:text>
            <flux:text class="text-2xl font-bold text-blue-600">RM {{ number_format($totalApprovedAmount, 2) }}</flux:text>
        </div>
    </div>
</div>
