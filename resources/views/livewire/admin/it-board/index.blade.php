<?php

use App\Models\ItTicket;
use App\Models\User;
use Livewire\Volt\Component;
use Livewire\Attributes\On;

new class extends Component
{
    public string $search = '';
    public string $typeFilter = '';
    public string $priorityFilter = '';
    public string $assigneeFilter = '';

    // Quick-create modal
    public bool $showCreateModal = false;
    public string $newTitle = '';
    public string $newType = 'task';
    public string $newPriority = 'medium';
    public string $createInStatus = 'backlog';

    public function layout()
    {
        return 'components.layouts.app.sidebar';
    }

    public function getColumnsProperty(): array
    {
        return [
            'backlog' => ['label' => 'Backlog', 'color' => 'gray'],
            'todo' => ['label' => 'To Do', 'color' => 'blue'],
            'in_progress' => ['label' => 'In Progress', 'color' => 'yellow'],
            'review' => ['label' => 'Review', 'color' => 'purple'],
            'testing' => ['label' => 'Testing', 'color' => 'orange'],
            'done' => ['label' => 'Done', 'color' => 'green'],
        ];
    }

    public function getTicketsProperty()
    {
        $query = ItTicket::with(['reporter', 'assignee']);

        if ($this->search) {
            $query->search($this->search);
        }
        if ($this->typeFilter) {
            $query->where('type', $this->typeFilter);
        }
        if ($this->priorityFilter) {
            $query->where('priority', $this->priorityFilter);
        }
        if ($this->assigneeFilter) {
            $query->where('assignee_id', $this->assigneeFilter);
        }

        return $query->orderBy('position')->get()->groupBy('status');
    }

    public function getAdminUsersProperty()
    {
        return User::where('role', 'admin')->orderBy('name')->get();
    }

    public function updateTicketStatus(int $ticketId, string $newStatus, int $newPosition): void
    {
        $ticket = ItTicket::findOrFail($ticketId);
        $ticket->update([
            'status' => $newStatus,
            'position' => $newPosition,
            'completed_at' => $newStatus === 'done' ? now() : ($ticket->status === 'done' && $newStatus !== 'done' ? null : $ticket->completed_at),
        ]);
    }

    public function reorderColumn(string $status, array $orderedIds): void
    {
        foreach ($orderedIds as $position => $id) {
            ItTicket::where('id', $id)->update(['position' => $position]);
        }
    }

    public function openQuickCreate(string $status = 'backlog'): void
    {
        $this->createInStatus = $status;
        $this->reset(['newTitle', 'newType', 'newPriority']);
        $this->newType = 'task';
        $this->newPriority = 'medium';
        $this->showCreateModal = true;
    }

    public function quickCreate(): void
    {
        $this->validate([
            'newTitle' => 'required|min:3|max:255',
            'newType' => 'required|in:' . implode(',', ItTicket::types()),
            'newPriority' => 'required|in:' . implode(',', ItTicket::priorities()),
        ]);

        ItTicket::create([
            'ticket_number' => ItTicket::generateTicketNumber(),
            'title' => $this->newTitle,
            'type' => $this->newType,
            'priority' => $this->newPriority,
            'status' => $this->createInStatus,
            'position' => ItTicket::where('status', $this->createInStatus)->max('position') + 1,
            'reporter_id' => auth()->id(),
        ]);

        $this->showCreateModal = false;
        $this->reset(['newTitle']);
    }

    public function deleteTicket(int $ticketId): void
    {
        ItTicket::findOrFail($ticketId)->delete();
    }
}; ?>

<div>
    <!-- Header -->
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading size="xl">IT Board</flux:heading>
            <flux:text class="mt-2">Manage IT tasks and development requests</flux:text>
        </div>
        <div class="flex items-center gap-2">
            <flux:button variant="primary" :href="route('admin.it-board.create')" wire:navigate>
                <div class="flex items-center">
                    <flux:icon name="plus" class="w-4 h-4 mr-1" />
                    New Ticket
                </div>
            </flux:button>
        </div>
    </div>

    <!-- Filters -->
    <div class="mb-4 flex flex-wrap items-center gap-3">
        <div class="w-64">
            <flux:input wire:model.live.debounce.300ms="search" placeholder="Search tickets..." icon="magnifying-glass" size="sm" />
        </div>
        <flux:select wire:model.live="typeFilter" size="sm">
            <option value="">All Types</option>
            @foreach(ItTicket::types() as $type)
                <option value="{{ $type }}">{{ ucfirst($type) }}</option>
            @endforeach
        </flux:select>
        <flux:select wire:model.live="priorityFilter" size="sm">
            <option value="">All Priorities</option>
            @foreach(ItTicket::priorities() as $priority)
                <option value="{{ $priority }}">{{ ucfirst($priority) }}</option>
            @endforeach
        </flux:select>
        <flux:select wire:model.live="assigneeFilter" size="sm">
            <option value="">All Assignees</option>
            @foreach($this->adminUsers as $user)
                <option value="{{ $user->id }}">{{ $user->name }}</option>
            @endforeach
        </flux:select>
    </div>

    <!-- Kanban Board -->
    <div class="flex gap-4 overflow-x-auto pb-4" x-data="kanbanBoard()" x-init="initBoard()">
        @foreach($this->columns as $status => $column)
            @php $columnTickets = $this->tickets[$status] ?? collect(); @endphp
            <div class="flex-shrink-0 w-72">
                <!-- Column Header -->
                <div class="flex items-center justify-between mb-3 px-1">
                    <div class="flex items-center gap-2">
                        <div class="w-2.5 h-2.5 rounded-full bg-{{ $column['color'] }}-500"></div>
                        <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300">{{ $column['label'] }}</h3>
                        <span class="text-xs text-gray-400 bg-gray-100 dark:bg-zinc-700 px-1.5 py-0.5 rounded-full">{{ $columnTickets->count() }}</span>
                    </div>
                    <button wire:click="openQuickCreate('{{ $status }}')" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
                        <flux:icon name="plus" class="w-4 h-4" />
                    </button>
                </div>

                <!-- Column Body (drop zone) -->
                <div
                    class="space-y-2 min-h-[200px] p-2 bg-gray-50 dark:bg-zinc-800/50 rounded-lg border border-gray-200 dark:border-zinc-700"
                    data-status="{{ $status }}"
                    x-ref="column_{{ $status }}"
                >
                    @foreach($columnTickets as $ticket)
                        <div
                            wire:key="ticket-{{ $ticket->id }}"
                            data-ticket-id="{{ $ticket->id }}"
                            class="bg-white dark:bg-zinc-800 rounded-lg border border-gray-200 dark:border-zinc-700 p-3 cursor-grab active:cursor-grabbing shadow-sm hover:shadow-md transition-shadow"
                        >
                            <!-- Card Header: Type + Priority -->
                            <div class="flex items-center justify-between mb-2">
                                <flux:badge size="sm" :color="$ticket->getTypeColor()">{{ $ticket->getTypeLabel() }}</flux:badge>
                                <div class="flex items-center gap-1.5">
                                    <div class="w-2 h-2 rounded-full bg-{{ $ticket->getPriorityColor() }}-500" title="{{ ucfirst($ticket->priority) }} priority"></div>
                                    <span class="text-[10px] text-gray-400">{{ $ticket->ticket_number }}</span>
                                </div>
                            </div>

                            <!-- Title -->
                            <a href="{{ route('admin.it-board.show', $ticket) }}" class="text-sm font-medium text-gray-900 dark:text-gray-100 hover:text-blue-600 dark:hover:text-blue-400 line-clamp-2 block" wire:navigate>
                                {{ $ticket->title }}
                            </a>

                            <!-- Footer: Assignee + Due Date -->
                            <div class="flex items-center justify-between mt-3">
                                <div>
                                    @if($ticket->assignee)
                                        <flux:avatar size="xs" :name="$ticket->assignee->name" />
                                    @else
                                        <span class="text-[10px] text-gray-400">Unassigned</span>
                                    @endif
                                </div>
                                @if($ticket->due_date)
                                    <span class="text-[10px] {{ $ticket->isOverdue() ? 'text-red-600 font-semibold' : 'text-gray-400' }}">
                                        <flux:icon name="calendar" class="w-3 h-3 inline" />
                                        {{ $ticket->due_date->format('M j') }}
                                    </span>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>

    <!-- Quick Create Modal -->
    <flux:modal wire:model="showCreateModal">
        <div class="p-6">
            <flux:heading size="lg">Quick Create Ticket</flux:heading>
            <flux:text class="mt-1">Adding to: {{ $this->columns[$createInStatus]['label'] ?? 'Backlog' }}</flux:text>

            <form wire:submit="quickCreate" class="mt-4 space-y-4">
                <div>
                    <flux:label for="newTitle" class="mb-1.5">Title *</flux:label>
                    <flux:input wire:model="newTitle" id="newTitle" placeholder="What needs to be done?" />
                    @error('newTitle') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <flux:label for="newType" class="mb-1.5">Type</flux:label>
                        <flux:select wire:model="newType" id="newType">
                            <option value="bug">Bug</option>
                            <option value="feature">Feature</option>
                            <option value="task">Task</option>
                            <option value="improvement">Improvement</option>
                        </flux:select>
                    </div>
                    <div>
                        <flux:label for="newPriority" class="mb-1.5">Priority</flux:label>
                        <flux:select wire:model="newPriority" id="newPriority">
                            <option value="low">Low</option>
                            <option value="medium">Medium</option>
                            <option value="high">High</option>
                            <option value="urgent">Urgent</option>
                        </flux:select>
                    </div>
                </div>
                <div class="flex justify-end gap-3 pt-2">
                    <flux:button variant="ghost" @click="$wire.showCreateModal = false">Cancel</flux:button>
                    <flux:button variant="primary" type="submit">Create</flux:button>
                </div>
            </form>
        </div>
    </flux:modal>
</div>

@script
<script>
    function kanbanBoard() {
        return {
            sortables: [],
            initBoard() {
                this.$nextTick(() => {
                    const columns = this.$el.querySelectorAll('[data-status]');
                    columns.forEach(column => {
                        const sortable = new Sortable(column, {
                            group: 'kanban',
                            animation: 150,
                            ghostClass: 'opacity-50',
                            dragClass: 'rotate-2',
                            handle: '.cursor-grab',
                            onEnd: (evt) => {
                                const ticketId = parseInt(evt.item.dataset.ticketId);
                                const newStatus = evt.to.dataset.status;
                                const newPosition = evt.newIndex;

                                // Get all ticket IDs in the target column
                                const orderedIds = Array.from(evt.to.children).map(el => parseInt(el.dataset.ticketId));

                                this.$wire.updateTicketStatus(ticketId, newStatus, newPosition);
                                this.$wire.reorderColumn(newStatus, orderedIds);
                            },
                        });
                        this.sortables.push(sortable);
                    });
                });
            }
        };
    }
</script>
@endscript
