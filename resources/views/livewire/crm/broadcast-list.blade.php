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
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading size="xl">Broadcasts</flux:heading>
            <flux:text class="mt-2">Create and manage email broadcasts to your audiences</flux:text>
        </div>
        <flux:button variant="primary" href="{{ route('crm.broadcasts.create') }}">
            Create Broadcast
        </flux:button>
    </div>

    @if (session()->has('message'))
        <flux:callout variant="success" class="mb-6">
            {{ session('message') }}
        </flux:callout>
    @endif

    @if (session()->has('error'))
        <flux:callout variant="danger" class="mb-6">
            {{ session('error') }}
        </flux:callout>
    @endif

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <flux:card>
            <div class="p-4">
                <flux:text class="text-sm text-gray-600">Total Broadcasts</flux:text>
                <flux:heading size="lg" class="mt-1">{{ $totalBroadcasts }}</flux:heading>
            </div>
        </flux:card>
        <flux:card>
            <div class="p-4">
                <flux:text class="text-sm text-gray-600">Draft Broadcasts</flux:text>
                <flux:heading size="lg" class="mt-1">{{ $draftCount }}</flux:heading>
            </div>
        </flux:card>
        <flux:card>
            <div class="p-4">
                <flux:text class="text-sm text-gray-600">Sent Broadcasts</flux:text>
                <flux:heading size="lg" class="mt-1">{{ $sentCount }}</flux:heading>
            </div>
        </flux:card>
    </div>

    <div class="mt-6 space-y-6">
        <!-- Search and Filters -->
        <flux:card>
            <div class="p-6 border-b border-gray-200">
                <div class="flex flex-col sm:flex-row gap-4">
                    <div class="flex-1">
                        <flux:input
                            wire:model.live.debounce.300ms="search"
                            placeholder="Search broadcasts..."
                            icon="magnifying-glass" />
                    </div>
                    <div class="w-full sm:w-48">
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

            <!-- Broadcasts Table -->
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Subject</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Recipients</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Sent / Failed</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse($broadcasts as $broadcast)
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4">
                                    <div class="text-sm font-medium text-gray-900">{{ $broadcast->name }}</div>
                                    <div class="text-xs text-gray-500">{{ ucfirst($broadcast->type) }}</div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm text-gray-900">{{ $broadcast->subject ?: '-' }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <flux:badge size="sm" :class="match($broadcast->status) {
                                        'draft' => 'badge-gray',
                                        'scheduled' => 'badge-blue',
                                        'sending' => 'badge-yellow',
                                        'sent' => 'badge-green',
                                        'failed' => 'badge-red',
                                        default => 'badge-gray'
                                    }">
                                        {{ ucfirst($broadcast->status) }}
                                    </flux:badge>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">{{ number_format($broadcast->total_recipients) }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-green-600">{{ number_format($broadcast->total_sent) }}</div>
                                    @if($broadcast->total_failed > 0)
                                        <div class="text-xs text-red-600">{{ number_format($broadcast->total_failed) }} failed</div>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">{{ $broadcast->created_at->format('M d, Y') }}</div>
                                    <div class="text-xs text-gray-500">{{ $broadcast->created_at->format('g:i a') }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <div class="flex space-x-2">
                                        @if($broadcast->status === 'draft')
                                            <flux:button size="sm" variant="ghost" href="{{ route('crm.broadcasts.edit', $broadcast) }}">
                                                Edit
                                            </flux:button>
                                        @else
                                            <flux:button size="sm" variant="ghost" href="{{ route('crm.broadcasts.show', $broadcast) }}">
                                                View
                                            </flux:button>
                                        @endif
                                        <flux:button size="sm" variant="ghost" wire:click="duplicate({{ $broadcast->id }})">
                                            Duplicate
                                        </flux:button>
                                        @if($broadcast->status === 'draft')
                                            <flux:button size="sm" variant="ghost" wire:click="delete({{ $broadcast->id }})" wire:confirm="Are you sure you want to delete this broadcast?">
                                                Delete
                                            </flux:button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-6 py-12 text-center">
                                    <flux:icon.envelope class="mx-auto h-12 w-12 text-gray-400" />
                                    <h3 class="mt-2 text-sm font-medium text-gray-900">No broadcasts found</h3>
                                    <p class="mt-1 text-sm text-gray-500">
                                        @if($search || $statusFilter)
                                            Try adjusting your search criteria.
                                        @else
                                            Get started by creating your first email broadcast.
                                        @endif
                                    </p>
                                    @if(!$search && !$statusFilter)
                                        <div class="mt-6">
                                            <flux:button variant="primary" href="{{ route('crm.broadcasts.create') }}">
                                                Create Broadcast
                                            </flux:button>
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($broadcasts->hasPages())
                <div class="px-6 py-4 border-t border-gray-200">
                    {{ $broadcasts->links() }}
                </div>
            @endif
        </flux:card>
    </div>
</div>
