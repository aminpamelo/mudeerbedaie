<?php

use App\Models\Broadcast;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public $search = '';
    public $statusFilter = '';

    public function mount(): void
    {
        //
    }

    public function with(): array
    {
        $query = Broadcast::query()
            ->withCount('logs')
            ->orderBy('created_at', 'desc');

        if ($this->search) {
            $query->where('name', 'like', '%' . $this->search . '%')
                ->orWhere('subject', 'like', '%' . $this->search . '%');
        }

        if ($this->statusFilter) {
            $query->where('status', $this->statusFilter);
        }

        return [
            'broadcasts' => $query->paginate(10),
            'totalBroadcasts' => Broadcast::count(),
            'draftCount' => Broadcast::where('status', 'draft')->count(),
            'sentCount' => Broadcast::where('status', 'sent')->count(),
        ];
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function delete($id): void
    {
        $broadcast = Broadcast::findOrFail($id);

        // Only allow deletion of draft broadcasts
        if ($broadcast->status !== 'draft') {
            session()->flash('error', 'Only draft broadcasts can be deleted.');
            return;
        }

        $broadcast->delete();
        session()->flash('message', 'Broadcast deleted successfully.');
    }

    public function duplicate($id): void
    {
        $broadcast = Broadcast::findOrFail($id);

        $newBroadcast = $broadcast->replicate();
        $newBroadcast->name = $broadcast->name . ' (Copy)';
        $newBroadcast->status = 'draft';
        $newBroadcast->scheduled_at = null;
        $newBroadcast->sent_at = null;
        $newBroadcast->total_recipients = 0;
        $newBroadcast->total_sent = 0;
        $newBroadcast->total_failed = 0;
        $newBroadcast->save();

        // Copy audience relationships
        foreach ($broadcast->audiences as $audience) {
            $newBroadcast->audiences()->attach($audience->id);
        }

        session()->flash('message', 'Broadcast duplicated successfully.');
        $this->redirect(route('crm.broadcasts.edit', $newBroadcast));
    }
}; ?>

<div>
    {{-- Header --}}
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-lg font-semibold tracking-tight text-zinc-900 dark:text-zinc-100">Broadcasts</h1>
            <p class="mt-0.5 text-[13px] text-zinc-500 dark:text-zinc-400">Create and manage email broadcasts to your audiences</p>
        </div>
        <flux:button variant="primary" href="{{ route('crm.broadcasts.create') }}">
            Create Broadcast
        </flux:button>
    </div>

    @if (session()->has('message'))
        <div class="mb-6 flex items-center gap-2 rounded-md border border-emerald-200 bg-emerald-50 px-4 py-2.5 text-sm text-emerald-700 dark:border-emerald-800 dark:bg-emerald-900/20 dark:text-emerald-400">
            <flux:icon name="check-circle" class="h-4 w-4 shrink-0" />
            {{ session('message') }}
        </div>
    @endif

    @if (session()->has('error'))
        <div class="mb-6 flex items-center gap-2 rounded-md border border-red-200 bg-red-50 px-4 py-2.5 text-sm text-red-700 dark:border-red-800 dark:bg-red-900/20 dark:text-red-400">
            <flux:icon name="exclamation-circle" class="h-4 w-4 shrink-0" />
            {{ session('error') }}
        </div>
    @endif

    {{-- Stats Strip --}}
    <div class="mb-6 grid grid-cols-3 gap-3">
        <div class="rounded-lg border border-zinc-200 bg-white px-4 py-3 dark:border-zinc-700 dark:bg-zinc-900">
            <span class="text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Total</span>
            <p class="mt-0.5 text-xl font-semibold tabular-nums text-zinc-900 dark:text-zinc-100">{{ $totalBroadcasts }}</p>
        </div>
        <div class="rounded-lg border border-zinc-200 bg-white px-4 py-3 dark:border-zinc-700 dark:bg-zinc-900">
            <span class="text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Draft</span>
            <p class="mt-0.5 text-xl font-semibold tabular-nums text-zinc-900 dark:text-zinc-100">{{ $draftCount }}</p>
        </div>
        <div class="rounded-lg border border-zinc-200 bg-white px-4 py-3 dark:border-zinc-700 dark:bg-zinc-900">
            <span class="text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Sent</span>
            <p class="mt-0.5 text-xl font-semibold tabular-nums text-zinc-900 dark:text-zinc-100">{{ $sentCount }}</p>
        </div>
    </div>

    {{-- Table Card --}}
    <div class="rounded-lg border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
        {{-- Filters --}}
        <div class="border-b border-zinc-200 px-4 py-3 dark:border-zinc-700">
            <div class="flex items-center gap-2">
                <div class="flex-1">
                    <flux:input
                        wire:model.live.debounce.300ms="search"
                        placeholder="Search broadcasts..."
                        icon="magnifying-glass" />
                </div>
                <div class="w-40 shrink-0">
                    <flux:select wire:model.live="statusFilter" placeholder="All Statuses">
                        <flux:select.option value="">All Statuses</flux:select.option>
                        <flux:select.option value="draft">Draft</flux:select.option>
                        <flux:select.option value="scheduled">Scheduled</flux:select.option>
                        <flux:select.option value="sending">Sending</flux:select.option>
                        <flux:select.option value="sent">Sent</flux:select.option>
                        <flux:select.option value="failed">Failed</flux:select.option>
                    </flux:select>
                </div>
            </div>
        </div>

        {{-- Table --}}
        <div class="overflow-x-auto">
            <table class="min-w-full border-collapse">
                <thead>
                    <tr class="border-b border-zinc-200 dark:border-zinc-700">
                        <th class="bg-zinc-50 px-4 py-2 text-left text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:bg-zinc-800/50 dark:text-zinc-500">Name</th>
                        <th class="bg-zinc-50 px-4 py-2 text-left text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:bg-zinc-800/50 dark:text-zinc-500">Subject</th>
                        <th class="bg-zinc-50 px-4 py-2 text-left text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:bg-zinc-800/50 dark:text-zinc-500">Status</th>
                        <th class="bg-zinc-50 px-4 py-2 text-right text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:bg-zinc-800/50 dark:text-zinc-500">Recipients</th>
                        <th class="bg-zinc-50 px-4 py-2 text-right text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:bg-zinc-800/50 dark:text-zinc-500">Sent / Failed</th>
                        <th class="bg-zinc-50 px-4 py-2 text-left text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:bg-zinc-800/50 dark:text-zinc-500">Created</th>
                        <th class="bg-zinc-50 px-4 py-2 text-right text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:bg-zinc-800/50 dark:text-zinc-500">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($broadcasts as $broadcast)
                        <tr wire:key="broadcast-{{ $broadcast->id }}" class="border-b border-zinc-100 transition-colors hover:bg-zinc-50/50 dark:border-zinc-800 dark:hover:bg-zinc-800/30">
                            <td class="px-4 py-2.5">
                                <span class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $broadcast->name }}</span>
                                <span class="mt-0.5 block text-[11px] text-zinc-400 dark:text-zinc-500">{{ ucfirst($broadcast->type) }}</span>
                            </td>
                            <td class="px-4 py-2.5">
                                <span class="text-sm text-zinc-600 dark:text-zinc-400">{{ $broadcast->subject ?: '-' }}</span>
                            </td>
                            <td class="px-4 py-2.5 whitespace-nowrap">
                                <span class="inline-flex items-center rounded px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wider {{ match($broadcast->status) {
                                    'draft' => 'bg-zinc-100 text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400',
                                    'scheduled' => 'bg-blue-50 text-blue-600 dark:bg-blue-900/30 dark:text-blue-400',
                                    'sending' => 'bg-amber-50 text-amber-600 dark:bg-amber-900/30 dark:text-amber-400',
                                    'sent' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
                                    'failed' => 'bg-red-50 text-red-600 dark:bg-red-900/30 dark:text-red-400',
                                    default => 'bg-zinc-100 text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400'
                                } }}">
                                    {{ ucfirst($broadcast->status) }}
                                </span>
                            </td>
                            <td class="px-4 py-2.5 whitespace-nowrap text-right text-sm tabular-nums text-zinc-600 dark:text-zinc-400">
                                {{ number_format($broadcast->total_recipients) }}
                            </td>
                            <td class="px-4 py-2.5 whitespace-nowrap text-right">
                                <span class="text-sm tabular-nums text-emerald-600 dark:text-emerald-400">{{ number_format($broadcast->total_sent) }}</span>
                                @if($broadcast->total_failed > 0)
                                    <span class="mt-0.5 block text-[11px] tabular-nums text-red-500 dark:text-red-400">{{ number_format($broadcast->total_failed) }} failed</span>
                                @endif
                            </td>
                            <td class="px-4 py-2.5 whitespace-nowrap">
                                <span class="text-sm text-zinc-600 dark:text-zinc-400">{{ $broadcast->created_at->format('M d, Y') }}</span>
                                <span class="mt-0.5 block text-[11px] text-zinc-400 dark:text-zinc-500">{{ $broadcast->created_at->format('g:i a') }}</span>
                            </td>
                            <td class="px-4 py-2.5 whitespace-nowrap text-right">
                                <flux:dropdown>
                                    <flux:button variant="ghost" size="sm" icon="ellipsis-vertical" />
                                    <flux:menu>
                                        @if($broadcast->status === 'draft')
                                            <flux:menu.item icon="pencil-square" href="{{ route('crm.broadcasts.edit', $broadcast) }}">
                                                Edit
                                            </flux:menu.item>
                                        @else
                                            <flux:menu.item icon="eye" href="{{ route('crm.broadcasts.show', $broadcast) }}">
                                                View
                                            </flux:menu.item>
                                        @endif
                                        <flux:menu.item icon="document-duplicate" wire:click="duplicate({{ $broadcast->id }})">
                                            Duplicate
                                        </flux:menu.item>
                                        @if($broadcast->status === 'draft')
                                            <flux:menu.separator />
                                            <flux:menu.item
                                                icon="trash"
                                                variant="danger"
                                                wire:click="delete({{ $broadcast->id }})"
                                                wire:confirm="Are you sure you want to delete this broadcast?"
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
                            <td colspan="7" class="px-4 py-12 text-center">
                                <div class="flex flex-col items-center">
                                    <flux:icon name="envelope" class="h-8 w-8 text-zinc-300 dark:text-zinc-600" />
                                    <p class="mt-3 text-sm font-medium text-zinc-600 dark:text-zinc-400">
                                        @if($search || $statusFilter)
                                            No broadcasts match your filters
                                        @else
                                            No broadcasts yet
                                        @endif
                                    </p>
                                    <p class="mt-1 text-[13px] text-zinc-400 dark:text-zinc-500">
                                        @if($search || $statusFilter)
                                            Try adjusting your search or filters.
                                        @else
                                            Create your first email broadcast to get started.
                                        @endif
                                    </p>
                                    @if(!$search && !$statusFilter)
                                        <flux:button variant="primary" href="{{ route('crm.broadcasts.create') }}" class="mt-4" size="sm">
                                            Create Broadcast
                                        </flux:button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($broadcasts->hasPages())
            <div class="border-t border-zinc-200 px-4 py-3 dark:border-zinc-700">
                {{ $broadcasts->links() }}
            </div>
        @endif
    </div>
</div>
