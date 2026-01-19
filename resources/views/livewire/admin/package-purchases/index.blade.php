<?php

use App\Models\PackagePurchase;
use App\Models\Package;
use App\Models\User;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public $search = '';
    public $statusFilter = '';
    public $packageFilter = '';
    public $dateFilter = '';
    public $customerFilter = '';

    public function with(): array
    {
        return [
            'purchases' => PackagePurchase::query()
                ->with(['package', 'customer', 'productOrder', 'enrollments.enrollment'])
                ->when($this->search, function($query) {
                    $query->where(function($q) {
                        $q->whereHas('package', fn($q) => $q->where('name', 'like', "%{$this->search}%"))
                          ->orWhereHas('customer', fn($q) => $q->where('name', 'like', "%{$this->search}%")
                                                                ->orWhere('email', 'like', "%{$this->search}%"))
                          ->orWhere('purchase_number', 'like', "%{$this->search}%");
                    });
                })
                ->when($this->statusFilter, fn($query) => $query->where('status', $this->statusFilter))
                ->when($this->packageFilter, fn($query) => $query->where('package_id', $this->packageFilter))
                ->when($this->customerFilter, fn($query) => $query->where('user_id', $this->customerFilter))
                ->when($this->dateFilter, function($query) {
                    match($this->dateFilter) {
                        'today' => $query->whereDate('created_at', today()),
                        'week' => $query->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]),
                        'month' => $query->whereMonth('created_at', now()->month),
                        default => $query
                    };
                })
                ->latest()
                ->paginate(15),
            'packages' => Package::active()->get(),
            'customers' => User::whereIn('id', PackagePurchase::distinct()->pluck('user_id'))->get(),
        ];
    }

    public function updateStatus(PackagePurchase $purchase, string $status): void
    {
        if ($status === 'completed' && $purchase->status !== 'completed') {
            // Allocate and deduct stock when marking as completed
            if (!$purchase->allocateStock()) {
                $this->dispatch('purchase-update-error', message: 'Insufficient stock to complete this purchase.');
                return;
            }
            $purchase->markAsCompleted();
        } elseif ($status === 'cancelled' && $purchase->status !== 'cancelled') {
            // Release any reserved stock
            $purchase->releaseStock();
            $purchase->update(['status' => 'cancelled']);
        } else {
            $purchase->update(['status' => $status]);
        }

        $this->dispatch('purchase-updated');
    }

    public function refund(PackagePurchase $purchase): void
    {
        if ($purchase->status !== 'completed') {
            $this->dispatch('purchase-refund-error', message: 'Only completed purchases can be refunded.');
            return;
        }

        // Release stock back to inventory
        $purchase->releaseStock();
        $purchase->update(['status' => 'refunded']);

        $this->dispatch('purchase-refunded');
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'statusFilter', 'packageFilter', 'dateFilter', 'customerFilter']);
        $this->resetPage();
    }
}; ?>

<div>
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading size="xl">Package Sales</flux:heading>
            <flux:text class="mt-2">Manage package purchases and orders</flux:text>
        </div>
    </div>

    <!-- Filters -->
    <div class="mb-6 grid grid-cols-1 gap-4 md:grid-cols-6">
        <flux:input
            wire:model.live.debounce.300ms="search"
            placeholder="Search purchases..."
            icon="magnifying-glass"
        />

        <flux:select wire:model.live="statusFilter" placeholder="All Statuses">
            <flux:select.option value="">All Statuses</flux:select.option>
            <flux:select.option value="pending">Pending</flux:select.option>
            <flux:select.option value="processing">Processing</flux:select.option>
            <flux:select.option value="completed">Completed</flux:select.option>
            <flux:select.option value="cancelled">Cancelled</flux:select.option>
            <flux:select.option value="refunded">Refunded</flux:select.option>
        </flux:select>

        <flux:select wire:model.live="packageFilter" placeholder="All Packages">
            <flux:select.option value="">All Packages</flux:select.option>
            @foreach($packages as $package)
                <flux:select.option value="{{ $package->id }}">{{ $package->name }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:select wire:model.live="dateFilter" placeholder="All Dates">
            <flux:select.option value="">All Dates</flux:select.option>
            <flux:select.option value="today">Today</flux:select.option>
            <flux:select.option value="week">This Week</flux:select.option>
            <flux:select.option value="month">This Month</flux:select.option>
        </flux:select>

        <flux:select wire:model.live="customerFilter" placeholder="All Customers">
            <flux:select.option value="">All Customers</flux:select.option>
            @foreach($customers as $customer)
                <flux:select.option value="{{ $customer->id }}">{{ $customer->name }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:button wire:click="clearFilters" variant="outline" icon="x-mark">
            Clear Filters
        </flux:button>
    </div>

    <!-- Purchase Stats -->
    <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-4">
        <div class="bg-white dark:bg-zinc-800 overflow-hidden shadow rounded-lg border border-gray-200 dark:border-zinc-700">
            <div class="p-5">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <flux:icon name="shopping-cart" class="h-6 w-6 text-blue-600" />
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">Total Sales</dt>
                            <dd class="text-lg font-medium text-gray-900 dark:text-gray-100">{{ $purchases->total() }}</dd>
                        </dl>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-zinc-800 overflow-hidden shadow rounded-lg border border-gray-200 dark:border-zinc-700">
            <div class="p-5">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <flux:icon name="check-circle" class="h-6 w-6 text-green-600" />
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">Completed</dt>
                            <dd class="text-lg font-medium text-gray-900 dark:text-gray-100">
                                {{ PackagePurchase::completed()->count() }}
                            </dd>
                        </dl>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-zinc-800 overflow-hidden shadow rounded-lg border border-gray-200 dark:border-zinc-700">
            <div class="p-5">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <flux:icon name="clock" class="h-6 w-6 text-yellow-600" />
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">Pending</dt>
                            <dd class="text-lg font-medium text-gray-900 dark:text-gray-100">
                                {{ PackagePurchase::pending()->count() }}
                            </dd>
                        </dl>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-zinc-800 overflow-hidden shadow rounded-lg border border-gray-200 dark:border-zinc-700">
            <div class="p-5">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <flux:icon name="banknotes" class="h-6 w-6 text-purple-600" />
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">Total Revenue</dt>
                            <dd class="text-lg font-medium text-gray-900 dark:text-gray-100">
                                RM {{ number_format(PackagePurchase::completed()->sum('amount_paid'), 2) }}
                            </dd>
                        </dl>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Purchases Table -->
    <div class="bg-white dark:bg-zinc-800 rounded-lg shadow-sm border border-gray-200 dark:border-zinc-700 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full border-collapse border-0">
                <thead class="bg-gray-50 dark:bg-zinc-700/50 border-b border-gray-200 dark:border-zinc-700">
                    <tr>
                        <th scope="col" class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-gray-900 dark:text-gray-100 sm:pl-6">Purchase</th>
                        <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-gray-100">Customer</th>
                        <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-gray-100">Package</th>
                        <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-gray-100">Amount</th>
                        <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-gray-100">Status</th>
                        <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-gray-100">Date</th>
                        <th scope="col" class="relative py-3.5 pl-3 pr-4 sm:pr-6">
                            <span class="sr-only">Actions</span>
                            <span class="text-sm font-semibold text-gray-900 dark:text-gray-100">Actions</span>
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-zinc-800">
                    @forelse($purchases as $purchase)
                        <tr wire:key="purchase-{{ $purchase->id }}" class="border-b border-gray-200 dark:border-zinc-700 hover:bg-gray-50 dark:hover:bg-zinc-700/50">
                        <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm sm:pl-6">
                            <div>
                                <div class="font-medium text-gray-900 dark:text-gray-100">#{{ $purchase->purchase_number }}</div>
                                <div class="text-sm text-gray-500 dark:text-gray-400">
                                    @if($purchase->productOrder)
                                        <span class="inline-flex items-center">
                                            <flux:icon name="cube" class="w-3 h-3 mr-1" />
                                            Order #{{ $purchase->productOrder->order_number }}
                                        </span>
                                    @endif
                                    @if($purchase->enrollments->count() > 0)
                                        <span class="inline-flex items-center ml-2">
                                            <flux:icon name="academic-cap" class="w-3 h-3 mr-1" />
                                            {{ $purchase->enrollments->count() }} Enrollment(s)
                                        </span>
                                    @endif
                                </div>
                            </div>
                        </td>
                        <td class="px-3 py-4 text-sm text-gray-900 dark:text-gray-100">
                            <div>
                                <div class="font-medium">{{ $purchase->customer->name }}</div>
                                <div class="text-gray-500 dark:text-gray-400">{{ $purchase->customer->email }}</div>
                            </div>
                        </td>
                        <td class="px-3 py-4 text-sm text-gray-900 dark:text-gray-100">
                            <div class="flex items-center space-x-3">
                                @if($purchase->package->featured_image)
                                    <img src="{{ $purchase->package->featured_image }}"
                                         alt="{{ $purchase->package->name }}"
                                         class="h-8 w-8 rounded object-cover">
                                @else
                                    <div class="flex h-8 w-8 items-center justify-center rounded bg-gradient-to-br from-purple-500 to-blue-600">
                                        <flux:icon name="gift" class="h-4 w-4 text-white" />
                                    </div>
                                @endif
                                <div>
                                    <div class="font-medium">{{ $purchase->package->name }}</div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">
                                        {{ $purchase->package->getProductCount() }} Products, {{ $purchase->package->getCourseCount() }} Courses
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td class="px-3 py-4 text-sm text-gray-900 dark:text-gray-100">
                            <div class="font-medium">RM {{ number_format($purchase->amount_paid, 2) }}</div>
                            @if($purchase->discount_amount > 0)
                                <div class="text-xs text-green-600">
                                    -RM {{ number_format($purchase->discount_amount, 2) }} discount
                                </div>
                            @endif
                        </td>
                        <td class="px-3 py-4 text-sm text-gray-900 dark:text-gray-100">
                            <flux:badge :variant="match($purchase->status) {
                                'completed' => 'success',
                                'processing' => 'warning',
                                'pending' => 'gray',
                                'cancelled' => 'danger',
                                'refunded' => 'outline',
                                default => 'gray'
                            }" size="sm">
                                {{ ucfirst($purchase->status) }}
                            </flux:badge>
                        </td>
                        <td class="px-3 py-4 text-sm text-gray-500 dark:text-gray-400">
                            <div>{{ $purchase->created_at->format('M j, Y') }}</div>
                            <div class="text-xs">{{ $purchase->created_at->format('g:i A') }}</div>
                        </td>
                        <td class="relative whitespace-nowrap py-4 pl-3 pr-4 text-right text-sm font-medium sm:pr-6">
                            <div class="flex items-center justify-end space-x-1">
                                <flux:button
                                    href="{{ route('package-purchases.show', $purchase) }}"
                                    variant="outline"
                                    size="sm"
                                    icon="eye"
                                >
                                </flux:button>

                                @if($purchase->status === 'pending')
                                    <flux:button
                                        wire:click="updateStatus({{ $purchase->id }}, 'processing')"
                                        variant="outline"
                                        size="sm"
                                        icon="play"
                                        title="Process"
                                    >
                                    </flux:button>
                                @endif

                                @if($purchase->status === 'processing')
                                    <flux:button
                                        wire:click="updateStatus({{ $purchase->id }}, 'completed')"
                                        variant="outline"
                                        size="sm"
                                        icon="check"
                                        title="Complete"
                                    >
                                    </flux:button>
                                @endif

                                @if(in_array($purchase->status, ['pending', 'processing']))
                                    <flux:button
                                        wire:click="updateStatus({{ $purchase->id }}, 'cancelled')"
                                        wire:confirm="Are you sure you want to cancel this purchase?"
                                        variant="outline"
                                        size="sm"
                                        icon="x-mark"
                                        title="Cancel"
                                    >
                                    </flux:button>
                                @endif

                                @if($purchase->status === 'completed')
                                    <flux:button
                                        wire:click="refund({{ $purchase->id }})"
                                        wire:confirm="Are you sure you want to refund this purchase? This will release stock back to inventory."
                                        variant="outline"
                                        size="sm"
                                        icon="arrow-uturn-left"
                                        title="Refund"
                                    >
                                    </flux:button>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-6 py-12 text-center">
                            <div>
                                <flux:icon name="shopping-cart" class="mx-auto h-12 w-12 text-gray-400 dark:text-gray-500" />
                                <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-gray-100">No package purchases found</h3>
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Package sales will appear here once customers start purchasing.</p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
            </table>
        </div>
    </div>

    <!-- Pagination -->
    <div class="mt-6">
        {{ $purchases->links() }}
    </div>
</div>
