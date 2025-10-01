<?php

use App\Models\Warehouse;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public $search = '';
    public $statusFilter = '';

    public function with(): array
    {
        return [
            'warehouses' => Warehouse::query()
                ->withCount('stockLevels')
                ->when($this->search, fn($query) => $query->where('name', 'like', "%{$this->search}%")
                    ->orWhere('code', 'like', "%{$this->search}%")
                    ->orWhereJsonContains('address->city', $this->search))
                ->when($this->statusFilter, fn($query) => $query->where('status', $this->statusFilter))
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
        $this->reset(['search', 'statusFilter']);
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
    <div class="mb-6 grid grid-cols-1 gap-4 md:grid-cols-3">
        <flux:input
            wire:model.live.debounce.300ms="search"
            placeholder="Search warehouses..."
            icon="magnifying-glass"
        />

        <flux:select wire:model.live="statusFilter" placeholder="All Statuses">
            <flux:select.option value="">All Statuses</flux:select.option>
            <flux:select.option value="active">Active</flux:select.option>
            <flux:select.option value="inactive">Inactive</flux:select.option>
        </flux:select>

        <flux:button wire:click="clearFilters" variant="outline" icon="x-mark">
            Clear Filters
        </flux:button>
    </div>

    <!-- Warehouses Table -->
    <div class="overflow-hidden bg-white shadow ring-1 ring-black ring-opacity-5 sm:rounded-lg">
        <table class="min-w-full divide-y divide-gray-300">
            <thead class="bg-gray-50">
                <tr>
                    <th scope="col" class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-gray-900 sm:pl-6">Warehouse</th>
                    <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Location</th>
                    <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Manager</th>
                    <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Stock Items</th>
                    <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Status</th>
                    <th scope="col" class="relative py-3.5 pl-3 pr-4 sm:pr-6">
                        <span class="sr-only">Actions</span>
                        <span class="text-sm font-semibold text-gray-900">Actions</span>
                    </th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 bg-white">
                @forelse($warehouses as $warehouse)
                    <tr wire:key="warehouse-{{ $warehouse->id }}" class="hover:bg-gray-50">
                        <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm sm:pl-6">
                            <div>
                                <div class="font-medium text-gray-900">{{ $warehouse->name }}</div>
                                <div class="text-gray-500">{{ $warehouse->code }}</div>
                            </div>
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
                            {{ $warehouse->manager_name ?: '-' }}
                        </td>
                        <td class="px-3 py-4 text-sm text-gray-900">
                            <flux:badge variant="outline" size="sm">
                                {{ number_format($warehouse->stock_levels_count) }} items
                            </flux:badge>
                        </td>
                        <td class="px-3 py-4 text-sm text-gray-900">
                            <flux:badge :variant="$warehouse->status === 'active' ? 'success' : 'gray'" size="sm">
                                {{ ucfirst($warehouse->status) }}
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
                        <td colspan="6" class="px-6 py-12 text-center">
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

    <!-- Pagination -->
    <div class="mt-6">
        {{ $warehouses->links() }}
    </div>
</div>