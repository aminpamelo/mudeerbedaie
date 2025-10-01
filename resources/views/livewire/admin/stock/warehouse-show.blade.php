<?php

use App\Models\Warehouse;
use App\Models\StockMovement;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public Warehouse $warehouse;
    public $activeTab = 'stock-items';

    public function mount(Warehouse $warehouse): void
    {
        $this->warehouse = $warehouse->load(['stockLevels.product']);
    }

    public function setActiveTab($tab): void
    {
        $this->activeTab = $tab;
        $this->resetPage();
    }

    public function with(): array
    {
        $data = [];

        if ($this->activeTab === 'stock-movements') {
            $data['stockMovements'] = StockMovement::query()
                ->with(['product', 'productVariant', 'createdBy'])
                ->where('warehouse_id', $this->warehouse->id)
                ->latest()
                ->paginate(10);
        }

        return $data;
    }

    public function delete(): void
    {
        if ($this->warehouse->stockLevels()->count() > 0) {
            session()->flash('error', 'Cannot delete warehouse with existing stock levels.');
            return;
        }

        $this->warehouse->delete();

        session()->flash('success', 'Warehouse deleted successfully.');

        $this->redirect(route('warehouses.index'));
    }

    public function toggleStatus(): void
    {
        $newStatus = $this->warehouse->status === 'active' ? 'inactive' : 'active';
        $this->warehouse->update(['status' => $newStatus]);

        session()->flash('success', 'Warehouse status updated successfully.');
    }
}; ?>

<div>
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ $warehouse->name }}</flux:heading>
            <flux:text class="mt-2">Warehouse details and inventory information</flux:text>
        </div>
        <div class="flex items-center space-x-3">
            <flux:button variant="outline" href="{{ route('warehouses.index') }}" icon="arrow-left">
                Back to Warehouses
            </flux:button>
            <flux:button href="{{ route('warehouses.edit', $warehouse) }}" icon="pencil">
                Edit Warehouse
            </flux:button>
        </div>
    </div>

    <!-- Warehouse Status Badge -->
    <div class="mb-6">
        <flux:badge :variant="$warehouse->status === 'active' ? 'success' : 'gray'" size="lg">
            {{ ucfirst($warehouse->status) }}
        </flux:badge>
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <!-- Main Information -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Basic Information -->
            <div class="rounded-lg border border-gray-200 bg-white p-6">
                <div class="flex items-center justify-between mb-4">
                    <flux:heading size="lg">Warehouse Information</flux:heading>
                    <flux:button wire:click="toggleStatus" variant="outline" size="sm">
                        {{ $warehouse->status === 'active' ? 'Deactivate' : 'Activate' }}
                    </flux:button>
                </div>

                <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Name</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $warehouse->name }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Code</dt>
                        <dd class="mt-1 text-sm text-gray-900">
                            <code class="text-sm">{{ $warehouse->code }}</code>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Created</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $warehouse->created_at->format('M j, Y g:i A') }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Last Updated</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $warehouse->updated_at->format('M j, Y g:i A') }}</dd>
                    </div>
                </dl>

                @if($warehouse->description)
                    <div class="mt-6">
                        <dt class="text-sm font-medium text-gray-500">Description</dt>
                        <dd class="mt-2 text-sm text-gray-900">{{ $warehouse->description }}</dd>
                    </div>
                @endif
            </div>

            <!-- Manager Information -->
            @if($warehouse->manager_name || $warehouse->manager_email || $warehouse->manager_phone)
                <div class="rounded-lg border border-gray-200 bg-white p-6">
                    <flux:heading size="lg" class="mb-4">Manager Information</flux:heading>
                    <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        @if($warehouse->manager_name)
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Name</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ $warehouse->manager_name }}</dd>
                            </div>
                        @endif
                        @if($warehouse->manager_email)
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Email</dt>
                                <dd class="mt-1 text-sm text-gray-900">
                                    <a href="mailto:{{ $warehouse->manager_email }}" class="text-blue-600 hover:text-blue-800">
                                        {{ $warehouse->manager_email }}
                                    </a>
                                </dd>
                            </div>
                        @endif
                        @if($warehouse->manager_phone)
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Phone</dt>
                                <dd class="mt-1 text-sm text-gray-900">
                                    <a href="tel:{{ $warehouse->manager_phone }}" class="text-blue-600 hover:text-blue-800">
                                        {{ $warehouse->manager_phone }}
                                    </a>
                                </dd>
                            </div>
                        @endif
                    </dl>
                </div>
            @endif

            <!-- Address Information -->
            @if($warehouse->address)
                <div class="rounded-lg border border-gray-200 bg-white p-6">
                    <flux:heading size="lg" class="mb-4">Address</flux:heading>
                    <div class="text-sm text-gray-900">
                        @if($warehouse->address['street'])
                            <div>{{ $warehouse->address['street'] }}</div>
                        @endif
                        <div>
                            {{ collect([
                                $warehouse->address['city'] ?? '',
                                $warehouse->address['state'] ?? '',
                                $warehouse->address['postal_code'] ?? ''
                            ])->filter()->implode(', ') }}
                        </div>
                        @if($warehouse->address['country'])
                            <div>{{ $warehouse->address['country'] }}</div>
                        @endif
                    </div>
                </div>
            @endif

            <!-- Inventory Tabs -->
            <div class="rounded-lg border border-gray-200 bg-white">
                <!-- Tab Navigation -->
                <div class="border-b border-gray-200">
                    <nav class="-mb-px flex space-x-8 px-6 py-4" aria-label="Tabs">
                        <button
                            wire:click="setActiveTab('stock-items')"
                            class="flex items-center whitespace-nowrap border-b-2 py-2 px-1 text-sm font-medium transition-colors duration-200 {{ $activeTab === 'stock-items' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700' }}"
                        >
                            <flux:icon name="cube" class="mr-2 h-4 w-4" />
                            Stock Items ({{ $warehouse->stockLevels->count() }})
                        </button>
                        <button
                            wire:click="setActiveTab('stock-movements')"
                            class="flex items-center whitespace-nowrap border-b-2 py-2 px-1 text-sm font-medium transition-colors duration-200 {{ $activeTab === 'stock-movements' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700' }}"
                        >
                            <flux:icon name="arrow-path" class="mr-2 h-4 w-4" />
                            Stock Movements
                        </button>
                    </nav>
                </div>

                <!-- Tab Content -->
                <div class="p-6">
                    @if($activeTab === 'stock-items')
                        <!-- Stock Items Tab -->
                        @if($warehouse->stockLevels->count() > 0)
                            <div class="space-y-4">
                                @foreach($warehouse->stockLevels->take(10) as $stockLevel)
                                    <div class="flex items-center justify-between border-b border-gray-100 pb-3 last:border-b-0 last:pb-0">
                                        <div>
                                            <div class="font-medium text-gray-900">{{ $stockLevel->product->name }}</div>
                                            <div class="text-sm text-gray-500">SKU: {{ $stockLevel->product->sku }}</div>
                                        </div>
                                        <div class="text-right">
                                            <div class="text-sm font-medium {{ $stockLevel->quantity <= 0 ? 'text-red-600' : ($stockLevel->quantity <= $stockLevel->product->min_quantity ? 'text-yellow-600' : 'text-gray-900') }}">
                                                {{ number_format($stockLevel->quantity) }} units
                                            </div>
                                            <div class="text-xs text-gray-500">
                                                Available: {{ number_format($stockLevel->quantity - $stockLevel->reserved_quantity) }}
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                                @if($warehouse->stockLevels->count() > 10)
                                    <div class="text-center pt-4">
                                        <flux:text class="text-gray-500">
                                            And {{ $warehouse->stockLevels->count() - 10 }} more items...
                                        </flux:text>
                                    </div>
                                @endif
                            </div>
                        @else
                            <div class="text-center py-12">
                                <flux:icon name="cube" class="mx-auto h-12 w-12 text-gray-400" />
                                <h3 class="mt-2 text-sm font-medium text-gray-900">No stock items</h3>
                                <p class="mt-1 text-sm text-gray-500">This warehouse doesn't have any stock items yet.</p>
                            </div>
                        @endif

                    @elseif($activeTab === 'stock-movements')
                        <!-- Stock Movements Tab -->
                        @if(isset($stockMovements) && $stockMovements->count() > 0)
                            <div class="space-y-4">
                                @foreach($stockMovements as $movement)
                                    <div class="flex items-center justify-between border-b border-gray-100 pb-4 last:border-b-0 last:pb-0">
                                        <div class="flex-1">
                                            <div class="flex items-center justify-between">
                                                <div>
                                                    <div class="font-medium text-gray-900">{{ $movement->product->name }}</div>
                                                    <div class="text-sm text-gray-500">
                                                        SKU: {{ $movement->product->sku }}
                                                        @if($movement->productVariant)
                                                            • {{ $movement->productVariant->name }}
                                                        @endif
                                                    </div>
                                                </div>
                                                <div class="text-right">
                                                    <div class="text-sm text-gray-500">{{ $movement->created_at->format('M j, Y g:i A') }}</div>
                                                    <div class="text-xs text-gray-400">{{ $movement->createdBy?->name ?? 'System' }}</div>
                                                </div>
                                            </div>
                                            <div class="mt-2 flex items-center justify-between">
                                                <div class="flex items-center space-x-4">
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
                                                    @endif
                                                </div>
                                                <div class="text-right">
                                                    <div class="text-sm font-medium {{ $movement->quantity >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                                        {{ $movement->display_quantity }}
                                                    </div>
                                                    <div class="text-xs text-gray-500">
                                                        {{ number_format($movement->quantity_before) }} → {{ number_format($movement->quantity_after) }}
                                                    </div>
                                                </div>
                                            </div>
                                            @if($movement->notes)
                                                <div class="mt-2 text-xs text-gray-600 italic">
                                                    {{ $movement->notes }}
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach

                                <!-- Pagination -->
                                <div class="mt-6">
                                    {{ $stockMovements->links() }}
                                </div>
                            </div>
                        @else
                            <div class="text-center py-12">
                                <flux:icon name="arrow-path" class="mx-auto h-12 w-12 text-gray-400" />
                                <h3 class="mt-2 text-sm font-medium text-gray-900">No stock movements</h3>
                                <p class="mt-1 text-sm text-gray-500">No inventory transactions have been recorded for this warehouse yet.</p>
                                <div class="mt-6">
                                    <flux:button variant="primary" href="{{ route('stock.movements.create') }}?warehouse={{ $warehouse->id }}" icon="plus">
                                        Add Stock Movement
                                    </flux:button>
                                </div>
                            </div>
                        @endif
                    @endif
                </div>
            </div>
        </div>

        <!-- Sidebar Information -->
        <div class="space-y-6">
            <!-- Quick Stats -->
            <div class="rounded-lg border border-gray-200 bg-white p-6">
                <flux:heading size="lg" class="mb-4">Quick Stats</flux:heading>
                <div class="space-y-4">
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-500">Total Items</span>
                        <span class="font-semibold text-gray-900">{{ $warehouse->stockLevels->count() }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-500">Total Stock</span>
                        <span class="font-semibold text-gray-900">{{ number_format($warehouse->stockLevels->sum('quantity')) }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-500">Available Stock</span>
                        <span class="font-semibold text-gray-900">{{ number_format($warehouse->stockLevels->sum(fn($level) => $level->quantity - $level->reserved_quantity)) }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-500">Reserved Stock</span>
                        <span class="font-semibold text-gray-900">{{ number_format($warehouse->stockLevels->sum('reserved_quantity')) }}</span>
                    </div>
                </div>
            </div>

            <!-- Actions -->
            <div class="rounded-lg border border-gray-200 bg-white p-6">
                <flux:heading size="lg" class="mb-4">Actions</flux:heading>
                <div class="space-y-3">
                    <flux:button href="{{ route('warehouses.edit', $warehouse) }}" variant="primary" class="w-full" icon="pencil">
                        Edit Warehouse
                    </flux:button>
                    <flux:button href="{{ route('stock.movements.create') }}?warehouse={{ $warehouse->id }}" variant="outline" class="w-full" icon="plus">
                        Add Stock Movement
                    </flux:button>
                    <flux:button wire:click="toggleStatus" variant="outline" class="w-full">
                        {{ $warehouse->status === 'active' ? 'Deactivate' : 'Activate' }} Warehouse
                    </flux:button>
                    @if($warehouse->stockLevels->count() === 0)
                        <flux:button
                            wire:click="delete"
                            wire:confirm="Are you sure you want to delete this warehouse?"
                            variant="outline"
                            class="w-full text-red-600 border-red-200 hover:bg-red-50"
                            icon="trash"
                        >
                            Delete Warehouse
                        </flux:button>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>