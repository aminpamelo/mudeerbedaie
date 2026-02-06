<?php

use App\Models\Funnel;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public string $search = '';
    public string $statusFilter = '';
    public string $typeFilter = '';

    public function with(): array
    {
        return [
            'funnels' => Funnel::query()
                ->withCount(['steps', 'sessions', 'orders'])
                ->when($this->search, fn ($query) => $query->where('name', 'like', '%'.$this->search.'%'))
                ->when($this->statusFilter, fn ($query) => $query->where('status', $this->statusFilter))
                ->when($this->typeFilter, fn ($query) => $query->where('type', $this->typeFilter))
                ->latest()
                ->paginate(10),
        ];
    }

    public function publish(Funnel $funnel): void
    {
        $funnel->publish();
        $this->dispatch('funnel-published');
    }

    public function unpublish(Funnel $funnel): void
    {
        $funnel->unpublish();
        $this->dispatch('funnel-unpublished');
    }

    public function duplicate(Funnel $funnel): void
    {
        $newFunnel = $funnel->duplicate();
        $this->dispatch('funnel-duplicated', name: $newFunnel->name);
    }

    public function delete(Funnel $funnel): void
    {
        $funnel->delete();
        $this->dispatch('funnel-deleted');
    }

    public function clearFilters(): void
    {
        $this->search = '';
        $this->statusFilter = '';
        $this->typeFilter = '';
        $this->resetPage();
    }
}; ?>

<div>
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading size="xl">Sales Funnels</flux:heading>
            <flux:text class="mt-2">Create and manage your sales funnels</flux:text>
        </div>
        <flux:button variant="primary" href="{{ route('funnel-builder.index') }}" icon="plus">
            Create Funnel
        </flux:button>
    </div>

    <div class="mt-6 space-y-6">
        <!-- Filters -->
        <flux:card>
            <div class="p-6 border-b border-gray-200 dark:border-zinc-700">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div class="md:col-span-2">
                        <flux:input wire:model.live="search" placeholder="Search funnels..." icon="magnifying-glass" />
                    </div>
                    <div>
                        <flux:select wire:model.live="statusFilter">
                            <option value="">All Status</option>
                            <option value="draft">Draft</option>
                            <option value="published">Published</option>
                            <option value="archived">Archived</option>
                        </flux:select>
                    </div>
                    <div>
                        <flux:select wire:model.live="typeFilter">
                            <option value="">All Types</option>
                            <option value="sales">Sales</option>
                            <option value="lead">Lead Generation</option>
                            <option value="webinar">Webinar</option>
                            <option value="membership">Membership</option>
                        </flux:select>
                    </div>
                </div>
                @if($search || $statusFilter || $typeFilter)
                    <div class="mt-4">
                        <flux:button size="sm" variant="ghost" wire:click="clearFilters" icon="x-mark">
                            Clear Filters
                        </flux:button>
                    </div>
                @endif
            </div>

            <!-- Funnels Table -->
            <div class="overflow-x-auto">
                <table class="min-w-full border-collapse border-0">
                    <thead class="bg-gray-50 dark:bg-zinc-700/50 border-b border-gray-200 dark:border-zinc-700">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Funnel</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Type</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Steps</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Sessions</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Orders</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-zinc-800">
                        @forelse ($funnels as $funnel)
                            <tr class="border-b border-gray-200 dark:border-zinc-700 hover:bg-gray-50 dark:hover:bg-zinc-700/50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div>
                                        <div class="font-medium text-gray-900 dark:text-gray-100">{{ $funnel->name }}</div>
                                        <div class="text-sm text-gray-500 dark:text-gray-400">
                                            <a href="{{ url('/f/'.$funnel->slug) }}" target="_blank" class="hover:text-blue-600">
                                                /f/{{ $funnel->slug }}
                                            </a>
                                        </div>
                                    </div>
                                </td>

                                <td class="px-6 py-4 whitespace-nowrap">
                                    <flux:badge size="sm" color="{{ match($funnel->type) {
                                        'sales' => 'blue',
                                        'lead' => 'green',
                                        'webinar' => 'purple',
                                        'membership' => 'orange',
                                        default => 'zinc'
                                    } }}">
                                        {{ ucfirst($funnel->type) }}
                                    </flux:badge>
                                </td>

                                <td class="px-6 py-4 whitespace-nowrap">
                                    <flux:badge size="sm" color="{{ match($funnel->status) {
                                        'published' => 'green',
                                        'draft' => 'yellow',
                                        'archived' => 'zinc',
                                        default => 'zinc'
                                    } }}">
                                        {{ ucfirst($funnel->status) }}
                                    </flux:badge>
                                </td>

                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                                    {{ $funnel->steps_count }} steps
                                </td>

                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                                    {{ number_format($funnel->sessions_count) }}
                                </td>

                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                                    {{ number_format($funnel->orders_count) }}
                                </td>

                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <flux:dropdown>
                                        <flux:button variant="ghost" size="sm" icon="ellipsis-vertical" />

                                        <flux:menu>
                                            <flux:menu.item icon="chart-bar" href="{{ route('admin.funnels.show', $funnel) }}">
                                                Analytics
                                            </flux:menu.item>

                                            <flux:menu.item icon="pencil-square" href="{{ route('funnel-builder.index') }}?funnel={{ $funnel->uuid }}">
                                                Edit in Builder
                                            </flux:menu.item>

                                            <flux:menu.item icon="arrow-top-right-on-square" href="{{ url('/f/'.$funnel->slug) }}" target="_blank">
                                                View Live
                                            </flux:menu.item>

                                            <flux:menu.separator />

                                            @if($funnel->status === 'draft')
                                                <flux:menu.item icon="rocket-launch" wire:click="publish({{ $funnel->id }})">
                                                    Publish
                                                </flux:menu.item>
                                            @else
                                                <flux:menu.item icon="pause" wire:click="unpublish({{ $funnel->id }})">
                                                    Unpublish
                                                </flux:menu.item>
                                            @endif

                                            <flux:menu.item icon="document-duplicate" wire:click="duplicate({{ $funnel->id }})">
                                                Duplicate
                                            </flux:menu.item>

                                            <flux:menu.separator />

                                            <flux:menu.item
                                                icon="trash"
                                                variant="danger"
                                                wire:click="delete({{ $funnel->id }})"
                                                wire:confirm="Are you sure you want to delete this funnel? This action cannot be undone."
                                            >
                                                Delete
                                            </flux:menu.item>
                                        </flux:menu>
                                    </flux:dropdown>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-6 py-12 text-center">
                                    <div class="flex flex-col items-center">
                                        <flux:icon name="funnel" class="w-12 h-12 text-gray-300 dark:text-gray-600 mb-4" />
                                        <flux:heading size="lg" class="text-gray-500 dark:text-gray-400">No funnels found</flux:heading>
                                        <flux:text class="text-gray-400 dark:text-gray-500 mt-1">
                                            @if($search || $statusFilter || $typeFilter)
                                                Try adjusting your filters
                                            @else
                                                Get started by creating your first funnel
                                            @endif
                                        </flux:text>
                                        @if(!$search && !$statusFilter && !$typeFilter)
                                            <flux:button variant="primary" href="{{ route('funnel-builder.index') }}" icon="plus" class="mt-4">
                                                Create Funnel
                                            </flux:button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($funnels->hasPages())
                <div class="px-6 py-4 border-t border-gray-200 dark:border-zinc-700">
                    {{ $funnels->links() }}
                </div>
            @endif
        </flux:card>
    </div>
</div>
