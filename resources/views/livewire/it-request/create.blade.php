<?php

use App\Models\ItTicket;
use Livewire\Volt\Component;

new class extends Component
{
    public string $title = '';
    public string $description = '';
    public string $type = 'task';
    public string $priority = 'medium';

    public bool $submitted = false;

    public function layout()
    {
        return 'components.layouts.app.sidebar';
    }

    public function submit(): void
    {
        $this->validate([
            'title' => 'required|min:5|max:255',
            'description' => 'nullable|max:5000',
            'type' => 'required|in:' . implode(',', ItTicket::types()),
            'priority' => 'required|in:' . implode(',', ItTicket::priorities()),
        ]);

        ItTicket::create([
            'ticket_number' => ItTicket::generateTicketNumber(),
            'title' => $this->title,
            'description' => $this->description,
            'type' => $this->type,
            'priority' => $this->priority,
            'status' => 'backlog',
            'position' => ItTicket::where('status', 'backlog')->max('position') + 1,
            'reporter_id' => auth()->id(),
        ]);

        $this->submitted = true;
    }

    public function submitAnother(): void
    {
        $this->reset(['title', 'description', 'type', 'priority', 'submitted']);
        $this->type = 'task';
        $this->priority = 'medium';
    }
}; ?>

<div>
    <div class="mb-6">
        <flux:heading size="xl">Submit IT Request</flux:heading>
        <flux:text class="mt-2">Report a bug, request a feature, or submit a task for the IT team</flux:text>
    </div>

    @if($submitted)
        <div class="bg-white dark:bg-zinc-800 rounded-lg border border-gray-200 dark:border-zinc-700 p-8 text-center">
            <div class="w-16 h-16 bg-green-100 dark:bg-green-900/30 rounded-full flex items-center justify-center mx-auto mb-4">
                <flux:icon name="check-circle" class="w-8 h-8 text-green-600" />
            </div>
            <flux:heading size="lg">Request Submitted!</flux:heading>
            <flux:text class="mt-2">Your IT request has been added to the backlog. The team will review it shortly.</flux:text>
            <div class="mt-6 flex items-center justify-center gap-3">
                <flux:button variant="primary" wire:click="submitAnother">Submit Another</flux:button>
                <flux:button variant="ghost" :href="route('it-request.index')" wire:navigate>View My Requests</flux:button>
            </div>
        </div>
    @else
        <form wire:submit="submit">
            <div class="bg-white dark:bg-zinc-800 rounded-lg border border-gray-200 dark:border-zinc-700">
                <div class="px-6 py-5 border-b border-gray-200 dark:border-zinc-700">
                    <div class="flex items-center gap-2 mb-1">
                        <flux:icon name="clipboard-document-list" class="w-5 h-5 text-gray-400" />
                        <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Request Details</h3>
                    </div>
                </div>
                <div class="px-6 py-5 space-y-5">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div>
                            <flux:label for="type" class="mb-1.5">Type *</flux:label>
                            <flux:select wire:model="type" id="type">
                                <option value="bug">Bug - Something is broken</option>
                                <option value="feature">Feature - New functionality</option>
                                <option value="task">Task - General work item</option>
                                <option value="improvement">Improvement - Enhance existing feature</option>
                            </flux:select>
                        </div>
                        <div>
                            <flux:label for="priority" class="mb-1.5">Priority *</flux:label>
                            <flux:select wire:model="priority" id="priority">
                                <option value="low">Low - No rush</option>
                                <option value="medium">Medium - Normal priority</option>
                                <option value="high">High - Important</option>
                                <option value="urgent">Urgent - Needs immediate attention</option>
                            </flux:select>
                        </div>
                    </div>

                    <div>
                        <flux:label for="title" class="mb-1.5">Title *</flux:label>
                        <flux:input wire:model="title" id="title" placeholder="Brief description of the request" />
                        @error('title') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <flux:label for="description" class="mb-1.5">Description</flux:label>
                        <flux:textarea wire:model="description" id="description" rows="5" placeholder="Provide detailed information about your request..." />
                        @error('description') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="px-6 py-4 bg-gray-50 dark:bg-zinc-900/50 flex items-center justify-end gap-3 rounded-b-lg">
                    <flux:button variant="primary" type="submit">
                        <span wire:loading.remove>Submit Request</span>
                        <span wire:loading>Submitting...</span>
                    </flux:button>
                </div>
            </div>
        </form>
    @endif
</div>
