<?php

use App\Models\Warehouse;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public $search = '';
    public $statusFilter = '';
    public $warehouseTypeFilter = '';

    public function with(): array
    {
        return [
            'warehouses' => Warehouse::query()
                ->with('agent')
                ->withCount('stockLevels')
                ->when($this->search, fn($query) => $query->search($this->search))
                ->when($this->statusFilter, fn($query) => $query->where('is_active', $this->statusFilter === '1'))
                ->when($this->warehouseTypeFilter, fn($query) => $query->byType($this->warehouseTypeFilter))
                ->latest()
                ->paginate(15),
        ];
    }

    public function delete(Warehouse $warehouse): void
    {
        if ($warehouse->stockLevels()->count() > 0) {
            session()->flash('error', 'Cannot delete warehouse with existing stock levels.');
            return;
        }

        $warehouse->delete();

        session()->flash('success', 'Warehouse deleted successfully.');
    }

    public function toggleStatus(Warehouse $warehouse): void
    {
        $newStatus = $warehouse->status === 'active' ? 'inactive' : 'active';
        $warehouse->update(['status' => $newStatus]);

        session()->flash('success', 'Warehouse status updated successfully.');
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'statusFilter', 'warehouseTypeFilter']);
        $this->resetPage();
    }
}; ?>

<div>
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading size="xl">Warehouses</flux:heading>
            <flux:text class="mt-2">Manage your warehouse locations and storage facilities</flux:text>
        </div>
        <flux:button variant="primary" href="{{ route('warehouses.create') }}" icon="plus">
            Add Warehouse
        </flux:button>
    </div>

    <!-- Filters -->
    <div class="mb-6 grid grid-cols-1 gap-4 md:grid-cols-4">
        <flux:input
            wire:model.live.debounce.300ms="search"
            placeholder="Search warehouses..."
            icon="magnifying-glass"
        />

        <flux:select wire:model.live="warehouseTypeFilter" placeholder="All Types">
            <flux:select.option value="">All Types</flux:select.option>
            <flux:select.option value="own">Own Warehouses</flux:select.option>
            <flux:select.option value="agent">Agent Locations</flux:select.option>
            <flux:select.option value="company">Company Locations</flux:select.option>
        </flux:select>

        <flux:select wire:model.live="statusFilter" placeholder="All Statuses">
            <flux:select.option value="">All Statuses</flux:select.option>
            <flux:select.option value="1">Active</flux:select.option>
            <flux:select.option value="0">Inactive</flux:select.option>
        </flux:select>

        <flux:button wire:click="clearFilters" variant="outline" icon="x-mark">
            Clear Filters
        </flux:button>
    </div>

    <!-- Warehouses Table -->
    <div class="bg-white dark:bg-zinc-800 rounded-lg shadow-sm border border-gray-200 dark:border-zinc-700 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full border-collapse border-0">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th scope="col" class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-gray-900 sm:pl-6">Warehouse</th>
                        <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Type</th>
                        <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Location</th>
                        <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Manager/Agent</th>
                        <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Stock Items</th>
                        <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Status</th>
                        <th scope="col" class="relative py-3.5 pl-3 pr-4 sm:pr-6">
                            <span class="sr-only">Actions</span>
                            <span class="text-sm font-semibold text-gray-900">Actions</span>
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-zinc-800 divide-y divide-gray-200 dark:divide-zinc-700">
                    @forelse($warehouses as $warehouse)
                        <tr wire:key="warehouse-{{ $warehouse->id }}" class="border-b border-gray-200 hover:bg-gray-50">
                        <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm sm:pl-6">
                            <div>
                                <div class="flex items-center gap-2">
                                    <span class="font-medium text-gray-900">{{ $warehouse->name }}</span>
                                    @if($warehouse->is_default)
                                        <flux:badge variant="primary" size="sm" icon="star">Default</flux:badge>
                                    @endif
                                </div>
                                <div class="text-gray-500">{{ $warehouse->code }}</div>
                            </div>
                        </td>
                        <td class="px-3 py-4 text-sm">
                            @if($warehouse->warehouse_type === 'own')
                                <flux:badge variant="primary" size="sm">Own</flux:badge>
                            @elseif($warehouse->warehouse_type === 'agent')
                                <flux:badge variant="warning" size="sm">Agent</flux:badge>
                            @else
                                <flux:badge variant="info" size="sm">Company</flux:badge>
                            @endif
                        </td>
                        <td class="px-3 py-4 text-sm text-gray-900">
                            <div>
                                @if($warehouse->address)
                                    <div class="font-medium">{{ $warehouse->address['city'] ?? '' }}</div>
                                    <div class="text-gray-500">{{ $warehouse->address['state'] ?? '' }}, {{ $warehouse->address['country'] ?? '' }}</div>
                                @else
                                    <span class="text-gray-400">-</span>
                                @endif
                            </div>
                        </td>
                        <td class="px-3 py-4 text-sm text-gray-900">
                            @if($warehouse->agent)
                                <div>
                                    <div class="font-medium">{{ $warehouse->agent->name }}</div>
                                    <div class="text-xs text-gray-500">{{ $warehouse->agent->agent_code }}</div>
                                </div>
                            @elseif($warehouse->contact_person)
                                {{ $warehouse->contact_person }}
                            @else
                                <span class="text-gray-400">-</span>
                            @endif
                        </td>
                        <td class="px-3 py-4 text-sm text-gray-900">
                            <flux:badge variant="outline" size="sm">
                                {{ number_format($warehouse->stock_levels_count) }} items
                            </flux:badge>
                        </td>
                        <td class="px-3 py-4 text-sm text-gray-900">
                            <flux:badge :variant="$warehouse->is_active ? 'success' : 'gray'" size="sm">
                                {{ $warehouse->is_active ? 'Active' : 'Inactive' }}
                            </flux:badge>
                        </td>
                        <td class="relative whitespace-nowrap py-4 pl-3 pr-4 text-right text-sm font-medium sm:pr-6">
                            <div class="flex items-center justify-end space-x-2">
                                <flux:button
                                    href="{{ route('warehouses.show', $warehouse) }}"
                                    variant="outline"
                                    size="sm"
                                    icon="eye"
                                >
                                    View
                                </flux:button>
                                <flux:button
                                    href="{{ route('warehouses.edit', $warehouse) }}"
                                    variant="outline"
                                    size="sm"
                                    icon="pencil"
                                >
                                    Edit
                                </flux:button>
                                <flux:button
                                    wire:click="toggleStatus({{ $warehouse->id }})"
                                    variant="outline"
                                    size="sm"
                                    :icon="$warehouse->status === 'active' ? 'pause' : 'play'"
                                >
                                    {{ $warehouse->status === 'active' ? 'Deactivate' : 'Activate' }}
                                </flux:button>
                                @if($warehouse->stock_levels_count === 0)
                                    <flux:button
                                        wire:click="delete({{ $warehouse->id }})"
                                        wire:confirm="Are you sure you want to delete this warehouse?"
                                        variant="outline"
                                        size="sm"
                                        icon="trash"
                                        class="text-red-600 border-red-200 hover:bg-red-50"
                                    >
                                        Delete
                                    </flux:button>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-6 py-12 text-center">
                            <div>
                                <flux:icon name="building-office" class="mx-auto h-12 w-12 text-gray-400" />
                                <h3 class="mt-2 text-sm font-medium text-gray-900">No warehouses found</h3>
                                <p class="mt-1 text-sm text-gray-500">Get started by creating your first warehouse.</p>
                                <div class="mt-6">
                                    <flux:button variant="primary" href="{{ route('warehouses.create') }}" icon="plus">
                                        Add Warehouse
                                    </flux:button>
                                </div>
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
        {{ $warehouses->links() }}
    </div>
</div>