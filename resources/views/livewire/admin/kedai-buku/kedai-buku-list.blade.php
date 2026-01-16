<?php

use App\Models\Agent;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public string $search = '';
    public string $tierFilter = '';
    public string $statusFilter = '';

    public function with(): array
    {
        $bookstores = Agent::query()
            ->bookstores()
            ->withCount('orders')
            ->when($this->search, fn($query) => $query->search($this->search))
            ->when($this->tierFilter, fn($query) => $query->where('pricing_tier', $this->tierFilter))
            ->when($this->statusFilter !== '', function ($query) {
                $query->where('is_active', $this->statusFilter === '1');
            })
            ->latest()
            ->paginate(20);

        // Calculate summary statistics
        $totalBookstores = Agent::bookstores()->count();
        $activeBookstores = Agent::bookstores()->active()->count();
        $totalRevenue = Agent::bookstores()
            ->withSum(['orders' => function ($query) {
                $query->where('status', 'delivered');
            }], 'total_amount')
            ->get()
            ->sum('orders_sum_total_amount');
        $outstandingBalance = Agent::bookstores()
            ->withSum(['orders' => function ($query) {
                $query->where('payment_status', '!=', 'paid');
            }], 'total_amount')
            ->get()
            ->sum('orders_sum_total_amount');

        return [
            'bookstores' => $bookstores,
            'totalBookstores' => $totalBookstores,
            'activeBookstores' => $activeBookstores,
            'totalRevenue' => $totalRevenue,
            'outstandingBalance' => $outstandingBalance,
        ];
    }

    public function delete(Agent $agent): void
    {
        if (! $agent->isBookstore()) {
            session()->flash('error', 'Invalid bookstore.');
            return;
        }

        if ($agent->orders()->count() > 0) {
            session()->flash('error', 'Cannot delete bookstore with existing orders.');
            return;
        }

        $agent->delete();
        session()->flash('success', 'Kedai Buku deleted successfully.');
    }

    public function toggleStatus(Agent $agent): void
    {
        if (! $agent->isBookstore()) {
            session()->flash('error', 'Invalid bookstore.');
            return;
        }

        $agent->update(['is_active' => ! $agent->is_active]);
        session()->flash('success', 'Kedai Buku status updated successfully.');
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'tierFilter', 'statusFilter']);
        $this->resetPage();
    }
}; ?>

<div>
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading size="xl">Kedai Buku</flux:heading>
            <flux:text class="mt-2">Manage bookstore agents and wholesale pricing</flux:text>
        </div>
        <flux:button variant="primary" href="{{ route('agents-kedai-buku.create') }}" icon="plus">
            Tambah Kedai Buku
        </flux:button>
    </div>

    <!-- Summary Statistics -->
    <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="bg-white dark:bg-zinc-800 rounded-lg shadow-sm border border-gray-200 dark:border-zinc-700 p-4">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <flux:icon name="building-storefront" class="h-8 w-8 text-blue-500" />
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-500 dark:text-zinc-400">Total Kedai Buku</p>
                    <p class="text-2xl font-semibold text-gray-900 dark:text-zinc-100">{{ $totalBookstores }}</p>
                </div>
            </div>
        </div>
        <div class="bg-white dark:bg-zinc-800 rounded-lg shadow-sm border border-gray-200 dark:border-zinc-700 p-4">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <flux:icon name="check-circle" class="h-8 w-8 text-green-500" />
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-500 dark:text-zinc-400">Active</p>
                    <p class="text-2xl font-semibold text-gray-900 dark:text-zinc-100">{{ $activeBookstores }}</p>
                </div>
            </div>
        </div>
        <div class="bg-white dark:bg-zinc-800 rounded-lg shadow-sm border border-gray-200 dark:border-zinc-700 p-4">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <flux:icon name="banknotes" class="h-8 w-8 text-emerald-500" />
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-500 dark:text-zinc-400">Total Revenue</p>
                    <p class="text-2xl font-semibold text-gray-900 dark:text-zinc-100">RM {{ number_format($totalRevenue, 2) }}</p>
                </div>
            </div>
        </div>
        <div class="bg-white dark:bg-zinc-800 rounded-lg shadow-sm border border-gray-200 dark:border-zinc-700 p-4">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <flux:icon name="exclamation-circle" class="h-8 w-8 text-amber-500" />
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-500 dark:text-zinc-400">Outstanding</p>
                    <p class="text-2xl font-semibold {{ $outstandingBalance > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-gray-900 dark:text-zinc-100' }}">RM {{ number_format($outstandingBalance, 2) }}</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="mb-6 grid grid-cols-1 gap-4 md:grid-cols-4">
        <flux:input
            wire:model.live.debounce.300ms="search"
            placeholder="Search kedai buku..."
            icon="magnifying-glass"
        />

        <flux:select wire:model.live="tierFilter" placeholder="All Tiers">
            <flux:select.option value="">All Tiers</flux:select.option>
            <flux:select.option value="standard">Standard (10% discount)</flux:select.option>
            <flux:select.option value="premium">Premium (15% discount)</flux:select.option>
            <flux:select.option value="vip">VIP (20% discount)</flux:select.option>
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

    <!-- Bookstores Table -->
    <div class="bg-white dark:bg-zinc-800 rounded-lg shadow-sm border border-gray-200 dark:border-zinc-700 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full border-collapse border-0">
                <thead class="bg-gray-50 dark:bg-zinc-900 border-b border-gray-200 dark:border-zinc-700">
                    <tr>
                        <th scope="col" class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-gray-900 dark:text-zinc-100 sm:pl-6">Kedai Buku</th>
                        <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-zinc-100">Contact</th>
                        <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-zinc-100">Pricing Tier</th>
                        <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-zinc-100">Credit Limit</th>
                        <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-zinc-100">Orders</th>
                        <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-zinc-100">Status</th>
                        <th scope="col" class="relative py-3.5 pl-3 pr-4 sm:pr-6">
                            <span class="sr-only">Actions</span>
                            <span class="text-sm font-semibold text-gray-900 dark:text-zinc-100">Actions</span>
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-zinc-800">
                    @forelse($bookstores as $bookstore)
                        <tr wire:key="bookstore-{{ $bookstore->id }}" class="border-b border-gray-200 dark:border-zinc-700 hover:bg-gray-50 dark:hover:bg-zinc-700/50">
                            <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm sm:pl-6">
                                <div>
                                    <div class="font-medium text-gray-900 dark:text-zinc-100">{{ $bookstore->name }}</div>
                                    <div class="text-gray-500 dark:text-zinc-400">{{ $bookstore->agent_code }}</div>
                                    @if($bookstore->company_name)
                                        <div class="text-xs text-gray-500 dark:text-zinc-400">{{ $bookstore->company_name }}</div>
                                    @endif
                                </div>
                            </td>
                            <td class="px-3 py-4 text-sm text-gray-900 dark:text-zinc-100">
                                <div class="space-y-1">
                                    @if($bookstore->contact_person)
                                        <div class="font-medium">{{ $bookstore->contact_person }}</div>
                                    @endif
                                    @if($bookstore->phone)
                                        <div class="text-gray-500 dark:text-zinc-400">{{ $bookstore->phone }}</div>
                                    @endif
                                    @if($bookstore->email)
                                        <div class="text-gray-500 dark:text-zinc-400">{{ $bookstore->email }}</div>
                                    @endif
                                </div>
                            </td>
                            <td class="px-3 py-4 text-sm">
                                @php
                                    $tierVariant = match($bookstore->pricing_tier) {
                                        'vip' => 'warning',
                                        'premium' => 'info',
                                        default => 'outline',
                                    };
                                @endphp
                                <flux:badge :variant="$tierVariant" size="sm">
                                    {{ ucfirst($bookstore->pricing_tier ?? 'standard') }}
                                </flux:badge>
                            </td>
                            <td class="px-3 py-4 text-sm text-gray-900 dark:text-zinc-100">
                                RM {{ number_format($bookstore->credit_limit, 2) }}
                            </td>
                            <td class="px-3 py-4 text-sm">
                                <flux:badge variant="outline" size="sm">
                                    {{ $bookstore->orders_count }} {{ Str::plural('order', $bookstore->orders_count) }}
                                </flux:badge>
                            </td>
                            <td class="px-3 py-4 text-sm">
                                <flux:badge :variant="$bookstore->is_active ? 'success' : 'gray'" size="sm">
                                    {{ $bookstore->is_active ? 'Active' : 'Inactive' }}
                                </flux:badge>
                            </td>
                            <td class="relative whitespace-nowrap py-4 pl-3 pr-4 text-right text-sm font-medium sm:pr-6">
                                <flux:dropdown>
                                    <flux:button variant="outline" size="sm" icon="ellipsis-vertical" />
                                    <flux:menu>
                                        <flux:menu.item href="{{ route('agents-kedai-buku.show', $bookstore) }}" icon="eye">
                                            View Details
                                        </flux:menu.item>
                                        <flux:menu.item href="{{ route('agents-kedai-buku.edit', $bookstore) }}" icon="pencil">
                                            Edit
                                        </flux:menu.item>
                                        <flux:menu.item href="{{ route('agents-kedai-buku.orders', $bookstore) }}" icon="shopping-bag">
                                            View Orders
                                        </flux:menu.item>
                                        <flux:menu.item href="{{ route('agents-kedai-buku.pricing', $bookstore) }}" icon="currency-dollar">
                                            Manage Pricing
                                        </flux:menu.item>
                                        <flux:menu.separator />
                                        <flux:menu.item wire:click="toggleStatus({{ $bookstore->id }})" :icon="$bookstore->is_active ? 'pause' : 'play'">
                                            {{ $bookstore->is_active ? 'Deactivate' : 'Activate' }}
                                        </flux:menu.item>
                                        @if($bookstore->orders_count === 0)
                                            <flux:menu.item
                                                wire:click="delete({{ $bookstore->id }})"
                                                wire:confirm="Are you sure you want to delete this kedai buku?"
                                                icon="trash"
                                                variant="danger"
                                            >
                                                Delete
                                            </flux:menu.item>
                                        @endif
                                    </flux:menu>
                                </flux:dropdown>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-12 text-center">
                                <div>
                                    <flux:icon name="building-storefront" class="mx-auto h-12 w-12 text-gray-400 dark:text-zinc-500" />
                                    <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-zinc-100">No kedai buku found</h3>
                                    <p class="mt-1 text-sm text-gray-500 dark:text-zinc-400">Get started by adding your first bookstore agent.</p>
                                    <div class="mt-6">
                                        <flux:button variant="primary" href="{{ route('agents-kedai-buku.create') }}" icon="plus">
                                            Tambah Kedai Buku
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
        {{ $bookstores->links() }}
    </div>
</div>
