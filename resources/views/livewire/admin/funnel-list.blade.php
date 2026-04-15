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
    {{-- Header --}}
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-lg font-semibold tracking-tight text-zinc-900 dark:text-zinc-100">Sales Funnels</h1>
            <p class="mt-0.5 text-[13px] text-zinc-500 dark:text-zinc-400">Create and manage your sales funnels</p>
        </div>
        <flux:button variant="primary" href="{{ route('funnel-builder.index') }}" icon="plus">
            Create Funnel
        </flux:button>
    </div>

    {{-- Filters --}}
    <div class="rounded-lg border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
        <div class="border-b border-zinc-200 px-4 py-3 dark:border-zinc-700">
            <div class="flex items-center gap-2 flex-wrap">
                <div class="flex-1 min-w-[200px]">
                    <flux:input wire:model.live="search" placeholder="Search funnels..." icon="magnifying-glass" />
                </div>
                <div class="w-36 shrink-0">
                    <flux:select wire:model.live="statusFilter">
                        <option value="">All Status</option>
                        <option value="draft">Draft</option>
                        <option value="published">Published</option>
                        <option value="archived">Archived</option>
                    </flux:select>
                </div>
                <div class="w-40 shrink-0">
                    <flux:select wire:model.live="typeFilter">
                        <option value="">All Types</option>
                        <option value="sales">Sales</option>
                        <option value="lead">Lead Generation</option>
                        <option value="webinar">Webinar</option>
                        <option value="membership">Membership</option>
                    </flux:select>
                </div>
                @if($search || $statusFilter || $typeFilter)
                    <flux:button size="sm" variant="ghost" wire:click="clearFilters" icon="x-mark">
                        Clear
                    </flux:button>
                @endif
            </div>
        </div>

        {{-- Funnels Table --}}
        <div class="overflow-x-auto">
            <table class="min-w-full border-collapse">
                <thead>
                    <tr class="border-b border-zinc-200 dark:border-zinc-700">
                        <th scope="col" class="bg-zinc-50 px-4 py-2 text-left text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:bg-zinc-800/50 dark:text-zinc-500">Funnel</th>
                        <th scope="col" class="bg-zinc-50 px-4 py-2 text-left text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:bg-zinc-800/50 dark:text-zinc-500">Type</th>
                        <th scope="col" class="bg-zinc-50 px-4 py-2 text-left text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:bg-zinc-800/50 dark:text-zinc-500">Status</th>
                        <th scope="col" class="bg-zinc-50 px-4 py-2 text-left text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:bg-zinc-800/50 dark:text-zinc-500">Steps</th>
                        <th scope="col" class="bg-zinc-50 px-4 py-2 text-left text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:bg-zinc-800/50 dark:text-zinc-500">Sessions</th>
                        <th scope="col" class="bg-zinc-50 px-4 py-2 text-left text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:bg-zinc-800/50 dark:text-zinc-500">Orders</th>
                        <th scope="col" class="bg-zinc-50 px-4 py-2 text-right text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:bg-zinc-800/50 dark:text-zinc-500">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($funnels as $funnel)
                        <tr wire:key="funnel-{{ $funnel->id }}" class="border-b border-zinc-100 transition-colors hover:bg-zinc-50/50 dark:border-zinc-800 dark:hover:bg-zinc-800/30">
                            <td class="px-4 py-2.5 whitespace-nowrap">
                                <div>
                                    <span class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $funnel->name }}</span>
                                    <a href="{{ url('/f/'.$funnel->slug) }}" target="_blank" class="mt-0.5 block text-[12px] text-zinc-400 transition-colors hover:text-zinc-600 dark:text-zinc-500 dark:hover:text-zinc-300">
                                        /f/{{ $funnel->slug }}
                                    </a>
                                </div>
                            </td>

                            <td class="px-4 py-2.5 whitespace-nowrap">
                                <span class="inline-flex items-center rounded px-1.5 py-0.5 text-[11px] font-medium uppercase tracking-wider {{ match($funnel->type) {
                                    'sales' => 'bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-400',
                                    'lead' => 'bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-400',
                                    'webinar' => 'bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-400',
                                    'membership' => 'bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-400',
                                    default => 'bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-400'
                                } }}">
                                    {{ ucfirst($funnel->type) }}
                                </span>
                            </td>

                            <td class="px-4 py-2.5 whitespace-nowrap">
                                <span class="inline-flex items-center rounded px-1.5 py-0.5 text-[11px] font-medium uppercase tracking-wider {{ match($funnel->status) {
                                    'published' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
                                    'draft' => 'bg-amber-50 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
                                    'archived' => 'bg-zinc-100 text-zinc-500 dark:bg-zinc-800 dark:text-zinc-500',
                                    default => 'bg-zinc-100 text-zinc-500 dark:bg-zinc-800 dark:text-zinc-500'
                                } }}">
                                    {{ ucfirst($funnel->status) }}
                                </span>
                            </td>

                            <td class="px-4 py-2.5 whitespace-nowrap text-sm tabular-nums text-zinc-600 dark:text-zinc-400">
                                {{ $funnel->steps_count }}
                            </td>

                            <td class="px-4 py-2.5 whitespace-nowrap text-sm tabular-nums text-zinc-600 dark:text-zinc-400">
                                {{ number_format($funnel->sessions_count) }}
                            </td>

                            <td class="px-4 py-2.5 whitespace-nowrap text-sm tabular-nums text-zinc-600 dark:text-zinc-400">
                                {{ number_format($funnel->orders_count) }}
                            </td>

                            <td class="px-4 py-2.5 whitespace-nowrap text-right">
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
                            <td colspan="7" class="px-4 py-12 text-center">
                                <div class="flex flex-col items-center">
                                    <flux:icon name="funnel" class="w-8 h-8 text-zinc-300 dark:text-zinc-600" />
                                    <p class="mt-3 text-sm font-medium text-zinc-600 dark:text-zinc-400">
                                        @if($search || $statusFilter || $typeFilter)
                                            No funnels match your filters
                                        @else
                                            No funnels yet
                                        @endif
                                    </p>
                                    <p class="mt-1 text-[13px] text-zinc-400 dark:text-zinc-500">
                                        @if($search || $statusFilter || $typeFilter)
                                            Try adjusting your search or filters.
                                        @else
                                            Create your first funnel to get started.
                                        @endif
                                    </p>
                                    @if(!$search && !$statusFilter && !$typeFilter)
                                        <flux:button variant="primary" href="{{ route('funnel-builder.index') }}" icon="plus" class="mt-4" size="sm">
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
            <div class="border-t border-zinc-200 px-4 py-3 dark:border-zinc-700">
                {{ $funnels->links() }}
            </div>
        @endif
    </div>
</div>
