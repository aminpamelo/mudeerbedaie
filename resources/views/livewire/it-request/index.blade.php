<?php

use App\Models\ItTicket;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    public function layout()
    {
        return 'components.layouts.app.sidebar';
    }

    public function getTicketsProperty()
    {
        return ItTicket::where('reporter_id', auth()->id())
            ->orderByDesc('created_at')
            ->paginate(10);
    }
}; ?>

<div>
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading size="xl">My IT Requests</flux:heading>
            <flux:text class="mt-2">Track the status of your submitted IT requests</flux:text>
        </div>
        <flux:button variant="primary" :href="route('it-request.create')" wire:navigate>
            New Request
        </flux:button>
    </div>

    <div class="bg-white dark:bg-zinc-800 rounded-lg border border-gray-200 dark:border-zinc-700 overflow-hidden">
        @forelse($this->tickets as $ticket)
            <div class="px-6 py-4 border-b border-gray-200 dark:border-zinc-700 last:border-b-0">
                <div class="flex items-center justify-between">
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2">
                            <p class="text-sm font-medium text-gray-900 dark:text-gray-100 truncate">{{ $ticket->title }}</p>
                            <flux:badge size="sm" :color="$ticket->getTypeColor()">{{ $ticket->getTypeLabel() }}</flux:badge>
                            <flux:badge size="sm" :color="$ticket->getPriorityColor()">{{ ucfirst($ticket->priority) }}</flux:badge>
                        </div>
                        <div class="flex items-center gap-3 mt-1">
                            <span class="text-xs text-gray-500">{{ $ticket->ticket_number }}</span>
                            <span class="text-xs text-gray-500">{{ $ticket->created_at->diffForHumans() }}</span>
                        </div>
                    </div>
                    <flux:badge size="sm" :color="$ticket->getStatusColor()">{{ $ticket->getStatusLabel() }}</flux:badge>
                </div>
            </div>
        @empty
            <div class="px-6 py-12 text-center">
                <flux:icon name="inbox" class="w-10 h-10 mx-auto text-gray-300 mb-3" />
                <flux:text>No IT requests submitted yet.</flux:text>
                <flux:button variant="primary" :href="route('it-request.create')" wire:navigate class="mt-4">
                    Submit Your First Request
                </flux:button>
            </div>
        @endforelse
    </div>

    <div class="mt-4">
        {{ $this->tickets->links() }}
    </div>
</div>
