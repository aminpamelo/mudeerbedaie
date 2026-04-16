<?php

use App\Models\Audience;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public $search = '';

    public function mount(): void
    {
        //
    }

    public function with(): array
    {
        $query = Audience::query()
            ->withCount('students')
            ->orderBy('created_at', 'desc');

        if ($this->search) {
            $query->where('name', 'like', '%' . $this->search . '%')
                ->orWhere('description', 'like', '%' . $this->search . '%');
        }

        return [
            'audiences' => $query->paginate(10),
            'totalAudiences' => Audience::count(),
        ];
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function delete($id): void
    {
        $audience = Audience::findOrFail($id);
        $audience->delete();

        $this->dispatch('audience-deleted');
    }

}; ?>

<div>
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-lg font-semibold tracking-tight text-zinc-900 dark:text-zinc-100">Audiences</h1>
            <p class="mt-0.5 text-[13px] text-zinc-500 dark:text-zinc-400">Create and manage audience segments for targeted campaigns</p>
        </div>
        <flux:button variant="primary" href="{{ route('crm.audiences.create') }}">
            Create Audience
        </flux:button>
    </div>

    <div class="mt-6">
        <div class="rounded-lg border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
            <!-- Search -->
            <div class="border-b border-zinc-200 px-4 py-3 dark:border-zinc-700">
                <div class="flex flex-col sm:flex-row gap-4">
                    <div class="flex-1">
                        <flux:input
                            wire:model.live.debounce.300ms="search"
                            placeholder="Search audiences..."
                            icon="magnifying-glass" />
                    </div>
                </div>
            </div>

            <!-- Audiences Table -->
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700">
                    <thead>
                        <tr>
                            <th class="bg-zinc-50 px-4 py-2 text-left text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:bg-zinc-800/50 dark:text-zinc-500">Name</th>
                            <th class="bg-zinc-50 px-4 py-2 text-left text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:bg-zinc-800/50 dark:text-zinc-500">Created On</th>
                            <th class="bg-zinc-50 px-4 py-2 text-left text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:bg-zinc-800/50 dark:text-zinc-500">Description</th>
                            <th class="bg-zinc-50 px-4 py-2 text-left text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:bg-zinc-800/50 dark:text-zinc-500">Contacts</th>
                            <th class="bg-zinc-50 px-4 py-2 text-left text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:bg-zinc-800/50 dark:text-zinc-500">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @forelse($audiences as $audience)
                            <tr wire:key="audience-{{ $audience->id }}" class="hover:bg-zinc-50/50 dark:hover:bg-zinc-800/30">
                                <td class="px-4 py-2.5 whitespace-nowrap">
                                    <a href="{{ route('crm.audiences.show', $audience) }}" class="text-sm font-medium text-zinc-900 dark:text-zinc-100 hover:text-zinc-600 transition-colors">
                                        {{ $audience->name }}
                                    </a>
                                </td>
                                <td class="px-4 py-2.5 whitespace-nowrap">
                                    <div class="text-sm text-zinc-600 dark:text-zinc-400">{{ $audience->created_at->format('M d, Y') }}</div>
                                    <div class="text-[11px] text-zinc-400 dark:text-zinc-500">{{ $audience->created_at->format('g:i a') }}</div>
                                </td>
                                <td class="px-4 py-2.5">
                                    <div class="text-sm text-zinc-600">{{ $audience->description ?: '-' }}</div>
                                </td>
                                <td class="px-4 py-2.5 whitespace-nowrap">
                                    <div class="text-sm font-semibold tabular-nums text-zinc-900 dark:text-zinc-100">
                                        {{ $audience->students_count }} of {{ $audience->students_count }}
                                    </div>
                                    <div class="text-[11px] text-zinc-400">Subscribed</div>
                                </td>
                                <td class="px-4 py-2.5 whitespace-nowrap">
                                    <flux:dropdown>
                                        <flux:button variant="ghost" size="sm" icon="ellipsis-vertical" />
                                        <flux:menu>
                                            <flux:menu.item href="{{ route('crm.audiences.show', $audience) }}" icon="eye">View</flux:menu.item>
                                            <flux:menu.item href="{{ route('crm.audiences.edit', $audience) }}" icon="pencil-square">Edit</flux:menu.item>
                                            <flux:separator />
                                            <flux:menu.item variant="danger" icon="trash" wire:click="delete({{ $audience->id }})" wire:confirm="Are you sure you want to delete this audience?">Delete</flux:menu.item>
                                        </flux:menu>
                                    </flux:dropdown>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-12 text-center">
                                    <flux:icon.users class="mx-auto h-8 w-8 text-zinc-300 dark:text-zinc-600" />
                                    <h3 class="mt-2 text-sm font-medium text-zinc-600">No audiences found</h3>
                                    <p class="mt-1 text-[13px] text-zinc-400">
                                        @if($search)
                                            Try adjusting your search criteria.
                                        @else
                                            Get started by creating your first audience segment.
                                        @endif
                                    </p>
                                    @if(!$search)
                                        <div class="mt-4">
                                            <flux:button variant="primary" href="{{ route('crm.audiences.create') }}">
                                                Create Audience
                                            </flux:button>
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($audiences->hasPages())
                <div class="border-t border-zinc-200 px-4 py-3 dark:border-zinc-700">
                    {{ $audiences->links() }}
                </div>
            @endif
        </div>
    </div>
</div>
