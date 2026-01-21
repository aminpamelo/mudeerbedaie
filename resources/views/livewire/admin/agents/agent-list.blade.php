<?php

use App\Models\Agent;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public $search = '';
    public $typeFilter = '';
    public $statusFilter = '';

    public function with(): array
    {
        return [
            'agents' => Agent::query()
                ->withCount('warehouses')
                ->when($this->search, fn($query) => $query->search($this->search))
                ->when($this->typeFilter, fn($query) => $query->where('type', $this->typeFilter))
                ->when($this->statusFilter !== '', function ($query) {
                    $query->where('is_active', $this->statusFilter === '1');
                })
                ->latest()
                ->paginate(15),
        ];
    }

    public function delete(Agent $agent): void
    {
        if ($agent->warehouses()->count() > 0) {
            session()->flash('error', 'Cannot delete agent with existing warehouses. Remove warehouses first.');

            return;
        }

        $agent->delete();

        session()->flash('success', 'Agent deleted successfully.');
    }

    public function toggleStatus(Agent $agent): void
    {
        $agent->update(['is_active' => ! $agent->is_active]);

        session()->flash('success', 'Agent status updated successfully.');
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'typeFilter', 'statusFilter']);
        $this->resetPage();
    }
}; ?>

<div>
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading size="xl">Agent & Companies</flux:heading>
            <flux:text class="mt-2">Manage agents and companies for consignment stock tracking</flux:text>
        </div>
        <flux:button variant="primary" href="{{ route('agents.create') }}" icon="plus">
            Add Agent
        </flux:button>
    </div>

    <!-- Filters -->
    <div class="mb-6 grid grid-cols-1 gap-4 md:grid-cols-4">
        <flux:input
            wire:model.live.debounce.300ms="search"
            placeholder="Search agents..."
            icon="magnifying-glass"
        />

        <flux:select wire:model.live="typeFilter" placeholder="All Types">
            <flux:select.option value="">All Types</flux:select.option>
            <flux:select.option value="agent">Agent</flux:select.option>
            <flux:select.option value="company">Company</flux:select.option>
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

    <!-- Agents Table -->
    <div class="bg-white dark:bg-zinc-800 rounded-lg shadow-sm border border-gray-200 dark:border-zinc-700 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full border-collapse border-0">
<<<<<<< HEAD
                <thead class="bg-gray-50 dark:bg-zinc-900 border-b border-gray-200 dark:border-zinc-700">
                    <tr>
                        <th scope="col" class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-gray-900 dark:text-zinc-100 sm:pl-6">Agent/Company</th>
                        <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-zinc-100">Type</th>
                        <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-zinc-100">Contact</th>
                        <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-zinc-100">Payment Terms</th>
                        <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-zinc-100">Warehouses</th>
                        <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-zinc-100">Status</th>
                        <th scope="col" class="relative py-3.5 pl-3 pr-4 sm:pr-6">
                            <span class="sr-only">Actions</span>
                            <span class="text-sm font-semibold text-gray-900 dark:text-zinc-100">Actions</span>
=======
                <thead class="bg-gray-50 dark:bg-zinc-700/50 border-b border-gray-200 dark:border-zinc-700">
                    <tr>
                        <th scope="col" class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-gray-900 dark:text-gray-100 sm:pl-6">Agent/Company</th>
                        <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-gray-100">Type</th>
                        <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-gray-100">Contact</th>
                        <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-gray-100">Payment Terms</th>
                        <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-gray-100">Warehouses</th>
                        <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-gray-100">Status</th>
                        <th scope="col" class="relative py-3.5 pl-3 pr-4 sm:pr-6">
                            <span class="sr-only">Actions</span>
                            <span class="text-sm font-semibold text-gray-900 dark:text-gray-100">Actions</span>
>>>>>>> origin/main
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-zinc-800">
                    @forelse($agents as $agent)
                        <tr wire:key="agent-{{ $agent->id }}" class="border-b border-gray-200 dark:border-zinc-700 hover:bg-gray-50 dark:hover:bg-zinc-700/50">
                            <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm sm:pl-6">
                                <div>
<<<<<<< HEAD
                                    <div class="font-medium text-gray-900 dark:text-zinc-100">{{ $agent->name }}</div>
                                    <div class="text-gray-500 dark:text-zinc-400">{{ $agent->agent_code }}</div>
                                    @if($agent->company_name)
                                        <div class="text-xs text-gray-500 dark:text-zinc-400">{{ $agent->company_name }}</div>
=======
                                    <div class="font-medium text-gray-900 dark:text-gray-100">{{ $agent->name }}</div>
                                    <div class="text-gray-500 dark:text-gray-400">{{ $agent->agent_code }}</div>
                                    @if($agent->company_name)
                                        <div class="text-xs text-gray-500 dark:text-gray-400">{{ $agent->company_name }}</div>
>>>>>>> origin/main
                                    @endif
                                </div>
                            </td>
                            <td class="px-3 py-4 text-sm">
                                <flux:badge :variant="$agent->type === 'company' ? 'info' : 'outline'" size="sm">
                                    {{ ucfirst($agent->type) }}
                                </flux:badge>
                            </td>
<<<<<<< HEAD
                            <td class="px-3 py-4 text-sm text-gray-900 dark:text-zinc-100">
=======
                            <td class="px-3 py-4 text-sm text-gray-900 dark:text-gray-100">
>>>>>>> origin/main
                                <div class="space-y-1">
                                    @if($agent->contact_person)
                                        <div class="font-medium">{{ $agent->contact_person }}</div>
                                    @endif
                                    @if($agent->phone)
<<<<<<< HEAD
                                        <div class="text-gray-500 dark:text-zinc-400">{{ $agent->phone }}</div>
                                    @endif
                                    @if($agent->email)
                                        <div class="text-gray-500 dark:text-zinc-400">{{ $agent->email }}</div>
                                    @endif
                                </div>
                            </td>
                            <td class="px-3 py-4 text-sm text-gray-500 dark:text-zinc-400">
=======
                                        <div class="text-gray-500 dark:text-gray-400">{{ $agent->phone }}</div>
                                    @endif
                                    @if($agent->email)
                                        <div class="text-gray-500 dark:text-gray-400">{{ $agent->email }}</div>
                                    @endif
                                </div>
                            </td>
                            <td class="px-3 py-4 text-sm text-gray-500 dark:text-gray-400">
>>>>>>> origin/main
                                {{ $agent->payment_terms ?: '-' }}
                            </td>
                            <td class="px-3 py-4 text-sm">
                                <flux:badge variant="outline" size="sm">
                                    {{ $agent->warehouses_count }} {{ Str::plural('warehouse', $agent->warehouses_count) }}
                                </flux:badge>
                            </td>
                            <td class="px-3 py-4 text-sm">
                                <flux:badge :variant="$agent->is_active ? 'success' : 'gray'" size="sm">
                                    {{ $agent->is_active ? 'Active' : 'Inactive' }}
                                </flux:badge>
                            </td>
                            <td class="relative whitespace-nowrap py-4 pl-3 pr-4 text-right text-sm font-medium sm:pr-6">
                                <div class="flex items-center justify-end gap-2">
                                    <flux:button
                                        href="{{ route('agents.show', $agent) }}"
                                        variant="outline"
                                        size="sm"
                                        icon="eye"
                                    >
                                        View
                                    </flux:button>
                                    <flux:button
                                        href="{{ route('agents.edit', $agent) }}"
                                        variant="outline"
                                        size="sm"
                                        icon="pencil"
                                    >
                                        Edit
                                    </flux:button>
                                    <flux:button
                                        wire:click="toggleStatus({{ $agent->id }})"
                                        variant="outline"
                                        size="sm"
                                        :icon="$agent->is_active ? 'pause' : 'play'"
                                    >
                                        {{ $agent->is_active ? 'Deactivate' : 'Activate' }}
                                    </flux:button>
                                    @if($agent->warehouses_count === 0)
                                        <flux:button
                                            wire:click="delete({{ $agent->id }})"
                                            wire:confirm="Are you sure you want to delete this agent?"
                                            variant="outline"
                                            size="sm"
                                            icon="trash"
                                            class="text-red-600 dark:text-red-400 border-red-200 dark:border-red-800 hover:bg-red-50 dark:hover:bg-red-900/30"
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
<<<<<<< HEAD
                                    <flux:icon name="user-group" class="mx-auto h-12 w-12 text-gray-400 dark:text-zinc-500" />
                                    <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-zinc-100">No agents found</h3>
                                    <p class="mt-1 text-sm text-gray-500 dark:text-zinc-400">Get started by creating your first agent or company.</p>
=======
                                    <flux:icon name="user-group" class="mx-auto h-12 w-12 text-gray-400 dark:text-gray-500" />
                                    <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-gray-100">No agents found</h3>
                                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Get started by creating your first agent or company.</p>
>>>>>>> origin/main
                                    <div class="mt-6">
                                        <flux:button variant="primary" href="{{ route('agents.create') }}" icon="plus">
                                            Add Agent
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
        {{ $agents->links() }}
    </div>
</div>
