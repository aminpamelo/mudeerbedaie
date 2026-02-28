<?php

use App\Models\ItTicket;
use App\Models\User;
use Livewire\Volt\Component;

new class extends Component
{
    public string $title = '';
    public string $description = '';
    public string $type = 'task';
    public string $priority = 'medium';
    public string $status = 'backlog';
    public ?int $assigneeId = null;
    public ?string $dueDate = null;

    public function layout()
    {
        return 'components.layouts.app.sidebar';
    }

    public function getAdminUsersProperty()
    {
        return User::where('role', 'admin')->orderBy('name')->get();
    }

    public function create(): void
    {
        $this->validate([
            'title' => 'required|min:3|max:255',
            'description' => 'nullable|max:5000',
            'type' => 'required|in:' . implode(',', ItTicket::types()),
            'priority' => 'required|in:' . implode(',', ItTicket::priorities()),
            'status' => 'required|in:' . implode(',', ItTicket::statuses()),
            'assigneeId' => 'nullable|exists:users,id',
            'dueDate' => 'nullable|date',
        ]);

        $ticket = ItTicket::create([
            'ticket_number' => ItTicket::generateTicketNumber(),
            'title' => $this->title,
            'description' => $this->description,
            'type' => $this->type,
            'priority' => $this->priority,
            'status' => $this->status,
            'position' => ItTicket::where('status', $this->status)->max('position') + 1,
            'reporter_id' => auth()->id(),
            'assignee_id' => $this->assigneeId,
            'due_date' => $this->dueDate,
        ]);

        session()->flash('success', 'IT Ticket created successfully.');
        $this->redirect(route('admin.it-board.show', $ticket), navigate: true);
    }
}; ?>

<div>
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading size="xl">Create IT Ticket</flux:heading>
            <flux:text class="mt-2">Create a new task for the IT board</flux:text>
        </div>
        <flux:button variant="ghost" :href="route('admin.it-board.index')" wire:navigate>
            <div class="flex items-center">
                <flux:icon name="chevron-left" class="w-4 h-4 mr-1" />
                Back to Board
            </div>
        </flux:button>
    </div>

    <form wire:submit="create">
        <div class="bg-white dark:bg-zinc-800 rounded-lg border border-gray-200 dark:border-zinc-700">
            <div class="px-6 py-5 border-b border-gray-200 dark:border-zinc-700">
                <div class="flex items-center gap-2 mb-1">
                    <flux:icon name="clipboard-document-list" class="w-5 h-5 text-gray-400" />
                    <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Ticket Details</h3>
                </div>
            </div>
            <div class="px-6 py-5 space-y-5">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                    <div>
                        <flux:label for="type" class="mb-1.5">Type *</flux:label>
                        <flux:select wire:model="type" id="type">
                            <option value="bug">Bug</option>
                            <option value="feature">Feature</option>
                            <option value="task">Task</option>
                            <option value="improvement">Improvement</option>
                        </flux:select>
                    </div>
                    <div>
                        <flux:label for="priority" class="mb-1.5">Priority *</flux:label>
                        <flux:select wire:model="priority" id="priority">
                            <option value="low">Low</option>
                            <option value="medium">Medium</option>
                            <option value="high">High</option>
                            <option value="urgent">Urgent</option>
                        </flux:select>
                    </div>
                    <div>
                        <flux:label for="status" class="mb-1.5">Status</flux:label>
                        <flux:select wire:model="status" id="status">
                            @foreach(ItTicket::statuses() as $s)
                                <option value="{{ $s }}">{{ (new ItTicket(['status' => $s]))->getStatusLabel() }}</option>
                            @endforeach
                        </flux:select>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div>
                        <flux:label for="assigneeId" class="mb-1.5">Assign To</flux:label>
                        <flux:select wire:model="assigneeId" id="assigneeId">
                            <option value="">Unassigned</option>
                            @foreach($this->adminUsers as $user)
                                <option value="{{ $user->id }}">{{ $user->name }}</option>
                            @endforeach
                        </flux:select>
                    </div>
                    <div>
                        <flux:label for="dueDate" class="mb-1.5">Due Date</flux:label>
                        <flux:input wire:model="dueDate" id="dueDate" type="date" />
                    </div>
                </div>

                <div>
                    <flux:label for="title" class="mb-1.5">Title *</flux:label>
                    <flux:input wire:model="title" id="title" placeholder="Brief description of the task" />
                    @error('title') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <flux:label for="description" class="mb-1.5">Description</flux:label>
                    <flux:textarea wire:model="description" id="description" rows="5" placeholder="Detailed information about the task..." />
                    @error('description') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="px-6 py-4 bg-gray-50 dark:bg-zinc-900/50 flex items-center justify-end gap-3 rounded-b-lg">
                <flux:button variant="ghost" :href="route('admin.it-board.index')" wire:navigate>Cancel</flux:button>
                <flux:button variant="primary" type="submit">
                    <span wire:loading.remove>Create Ticket</span>
                    <span wire:loading>Creating...</span>
                </flux:button>
            </div>
        </div>
    </form>
</div>
