<?php

use App\Models\StockMovement;
use App\Models\Warehouse;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public $search = '';
    public $warehouseFilter = '';
    public $typeFilter = '';
    public $dateFrom = '';
    public $dateTo = '';

    public function with(): array
    {
        return [
            'movements' => StockMovement::query()
                ->with(['product', 'productVariant', 'warehouse', 'createdBy'])
                ->when($this->search, function($query) {
                    $query->whereHas('product', function($q) {
                        $q->where('name', 'like', "%{$this->search}%")
                          ->orWhere('sku', 'like', "%{$this->search}%");
                    });
                })
                ->when($this->warehouseFilter, fn($query) => $query->where('warehouse_id', $this->warehouseFilter))
                ->when($this->typeFilter, fn($query) => $query->where('type', $this->typeFilter))
                ->when($this->dateFrom, fn($query) => $query->whereDate('created_at', '>=', $this->dateFrom))
                ->when($this->dateTo, fn($query) => $query->whereDate('created_at', '<=', $this->dateTo))
                ->latest()
                ->paginate(20),
            'warehouses' => Warehouse::active()->get(),
            'totalIncoming' => StockMovement::query()
                ->when($this->warehouseFilter, fn($query) => $query->where('warehouse_id', $this->warehouseFilter))
                ->when($this->dateFrom, fn($query) => $query->whereDate('created_at', '>=', $this->dateFrom))
                ->when($this->dateTo, fn($query) => $query->whereDate('created_at', '<=', $this->dateTo))
                ->where('quantity', '>', 0)
                ->sum('quantity'),
            'totalOutgoing' => StockMovement::query()
                ->when($this->warehouseFilter, fn($query) => $query->where('warehouse_id', $this->warehouseFilter))
                ->when($this->dateFrom, fn($query) => $query->whereDate('created_at', '>=', $this->dateFrom))
                ->when($this->dateTo, fn($query) => $query->whereDate('created_at', '<=', $this->dateTo))
                ->where('quantity', '<', 0)
                ->sum('quantity'),
        ];
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'warehouseFilter', 'typeFilter', 'dateFrom', 'dateTo']);
        $this->resetPage();
    }
}; ?>

<div>
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading size="xl">Stock Movements</flux:heading>
            <flux:text class="mt-2">Track all inventory transactions and adjustments</flux:text>
        </div>
        <flux:button variant="primary" href="{{ route('stock.movements.create') }}" icon="plus">
            Add Movement
        </flux:button>
    </div>

    <!-- Summary Stats -->
    <div class="grid grid-cols-1 gap-6 md:grid-cols-3 mb-6">
        <div class="rounded-lg border border-gray-200 bg-white p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <flux:icon name="arrow-down" class="h-8 w-8 text-green-600" />
                </div>
                <div class="ml-5 w-0 flex-1">
                    <dl>
                        <dt class="text-sm font-medium text-gray-500 truncate">Total Incoming</dt>
                        <dd class="text-lg font-medium text-green-600">+{{ number_format($totalIncoming) }}</dd>
                    </dl>
                </div>
            </div>
        </div>

        <div class="rounded-lg border border-gray-200 bg-white p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <flux:icon name="arrow-up" class="h-8 w-8 text-red-600" />
                </div>
                <div class="ml-5 w-0 flex-1">
                    <dl>
                        <dt class="text-sm font-medium text-gray-500 truncate">Total Outgoing</dt>
                        <dd class="text-lg font-medium text-red-600">{{ number_format($totalOutgoing) }}</dd>
                    </dl>
                </div>
            </div>
        </div>

        <div class="rounded-lg border border-gray-200 bg-white p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <flux:icon name="chart-bar" class="h-8 w-8 text-blue-600" />
                </div>
                <div class="ml-5 w-0 flex-1">
                    <dl>
                        <dt class="text-sm font-medium text-gray-500 truncate">Net Movement</dt>
                        <dd class="text-lg font-medium {{ ($totalIncoming + $totalOutgoing) >= 0 ? 'text-green-600' : 'text-red-600' }}">
                            {{ ($totalIncoming + $totalOutgoing) >= 0 ? '+' : '' }}{{ number_format($totalIncoming + $totalOutgoing) }}
                        </dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="mb-6 grid grid-cols-1 gap-4 md:grid-cols-6">
        <flux:input
            wire:model.live.debounce.300ms="search"
            placeholder="Search products..."
            icon="magnifying-glass"
        />

        <flux:select wire:model.live="warehouseFilter" placeholder="All Warehouses">
            <flux:select.option value="">All Warehouses</flux:select.option>
            @foreach($warehouses as $warehouse)
                <flux:select.option value="{{ $warehouse->id }}">{{ $warehouse->name }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:select wire:model.live="typeFilter" placeholder="All Types">
            <flux:select.option value="">All Types</flux:select.option>
            <flux:select.option value="in">Stock In</flux:select.option>
            <flux:select.option value="out">Stock Out</flux:select.option>
            <flux:select.option value="adjustment">Adjustment</flux:select.option>
            <flux:select.option value="transfer">Transfer</flux:select.option>
        </flux:select>

        <flux:input
            type="date"
            wire:model.live="dateFrom"
            placeholder="From Date"
        />

        <flux:input
            type="date"
            wire:model.live="dateTo"
            placeholder="To Date"
        />

        <flux:button wire:click="clearFilters" variant="outline" icon="x-mark">
            Clear
        </flux:button>
    </div>

    <!-- Movements Table -->
    <div class="overflow-hidden bg-white shadow ring-1 ring-black ring-opacity-5 sm:rounded-lg">
        <table class="min-w-full divide-y divide-gray-300">
            <thead class="bg-gray-50">
                <tr>
                    <th scope="col" class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-gray-900 sm:pl-6">Date & Time</th>
                    <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Product</th>
                    <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Warehouse</th>
                    <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Type</th>
                    <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Quantity</th>
                    <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Before</th>
                    <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">After</th>
                    <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Reference</th>
                    <th scope="col" class="relative py-3.5 pl-3 pr-4 sm:pr-6">
                        <span class="sr-only">User</span>
                        <span class="text-sm font-semibold text-gray-900">User</span>
                    </th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 bg-white">
                @forelse($movements as $movement)
                    <tr wire:key="movement-{{ $movement->id }}" class="hover:bg-gray-50">
                        <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm sm:pl-6">
                            <div class="text-sm">
                                <div class="font-medium text-gray-900">{{ $movement->created_at->format('M j, Y') }}</div>
                                <div class="text-gray-500">{{ $movement->created_at->format('g:i A') }}</div>
                            </div>
                        </td>
                        <td class="px-3 py-4 text-sm text-gray-900">
                            <div>
                                <div class="font-medium text-gray-900">{{ $movement->product->name }}</div>
                                <div class="text-sm text-gray-500">
                                    SKU: {{ $movement->product->sku }}
                                    @if($movement->productVariant)
                                        • {{ $movement->productVariant->name }}
                                    @endif
                                </div>
                            </div>
                        </td>
                        <td class="px-3 py-4 text-sm text-gray-900">
                            <flux:badge variant="outline" size="sm">
                                {{ $movement->warehouse->name }}
                            </flux:badge>
                        </td>
                        <td class="px-3 py-4 text-sm text-gray-900">
                            <flux:badge
                                :variant="match($movement->type) {
                                    'in' => 'success',
                                    'out' => 'danger',
                                    'adjustment' => 'warning',
                                    'transfer' => 'info',
                                    default => 'outline'
                                }"
                                size="sm"
                            >
                                {{ $movement->formatted_type }}
                            </flux:badge>
                        </td>
                        <td class="px-3 py-4 text-sm text-gray-900">
                            <span class="font-medium {{ $movement->quantity >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                {{ $movement->display_quantity }}
                            </span>
                        </td>
                        <td class="px-3 py-4 text-sm text-gray-900">{{ number_format($movement->quantity_before) }}</td>
                        <td class="px-3 py-4 text-sm text-gray-900">{{ number_format($movement->quantity_after) }}</td>
                        <td class="px-3 py-4 text-sm text-gray-900">
                            @php $reference = $movement->formatted_reference @endphp
                            @if($reference['label'] !== '-')
                                @if($reference['clickable'] && $reference['url'])
                                    <a href="{{ $reference['url'] }}" wire:navigate class="inline-block hover:opacity-80 transition-opacity">
                                        <flux:badge
                                            :variant="$reference['variant']"
                                            size="sm"
                                            :icon="$reference['icon']"
                                        >
                                            {{ $reference['label'] }}
                                        </flux:badge>
                                    </a>
                                @else
                                    <flux:badge
                                        :variant="$reference['variant']"
                                        size="sm"
                                        :icon="$reference['icon']"
                                    >
                                        {{ $reference['label'] }}
                                    </flux:badge>
                                @endif
                            @else
                                <span class="text-sm text-gray-400">{{ $reference['label'] }}</span>
                            @endif
                        </td>
                        <td class="relative whitespace-nowrap py-4 pl-3 pr-4 text-right text-sm font-medium sm:pr-6">
                            <span class="text-sm text-gray-500">
                                {{ $movement->createdBy?->name ?? 'System' }}
                            </span>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="px-6 py-12 text-center">
                            <div>
                                <flux:icon name="arrow-path" class="mx-auto h-12 w-12 text-gray-400" />
                                <h3 class="mt-2 text-sm font-medium text-gray-900">No stock movements found</h3>
                                <p class="mt-1 text-sm text-gray-500">
                                    @if($search || $warehouseFilter || $typeFilter || $dateFrom || $dateTo)
                                        Try adjusting your filters or search terms.
                                    @else
                                        Stock movements will appear here as inventory changes occur.
                                    @endif
                                </p>
                                @if(!$search && !$warehouseFilter && !$typeFilter && !$dateFrom && !$dateTo)
                                    <div class="mt-6">
                                        <flux:button variant="primary" href="{{ route('stock.movements.create') }}" icon="plus">
                                            Add Movement
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
    <div class="mt-6">
        {{ $movements->links() }}
    </div>
</div>