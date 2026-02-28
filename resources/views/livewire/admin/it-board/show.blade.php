<?php

use App\Models\ItTicket;
use App\Models\User;
use Livewire\Volt\Component;

new class extends Component
{
    public ItTicket $itTicket;
    public string $commentBody = '';

    // Editable fields
    public string $status;
    public string $type;
    public string $priority;
    public ?int $assigneeId;
    public ?string $dueDate;

    public function layout()
    {
        return 'components.layouts.app.sidebar';
    }

    public function mount(ItTicket $itTicket): void
    {
        $this->itTicket = $itTicket->load(['reporter', 'assignee', 'comments.user']);
        $this->status = $itTicket->status;
        $this->type = $itTicket->type;
        $this->priority = $itTicket->priority;
        $this->assigneeId = $itTicket->assignee_id;
        $this->dueDate = $itTicket->due_date?->format('Y-m-d');
    }

    public function getAdminUsersProperty()
    {
        return User::where('role', 'admin')->orderBy('name')->get();
    }

    public function updateField(string $field): void
    {
        $data = [$field => $this->{$field}];

        if ($field === 'status' && $this->status === 'done') {
            $data['completed_at'] = now();
        } elseif ($field === 'status' && $this->itTicket->status === 'done' && $this->status !== 'done') {
            $data['completed_at'] = null;
        }

        if ($field === 'assigneeId') {
            $data = ['assignee_id' => $this->assigneeId];
        }

        if ($field === 'dueDate') {
            $data = ['due_date' => $this->dueDate];
        }

        $this->itTicket->update($data);
        $this->itTicket->refresh();
    }

    public function addComment(): void
    {
        $this->validate([
            'commentBody' => 'required|min:1|max:5000',
        ]);

        $this->itTicket->comments()->create([
            'user_id' => auth()->id(),
            'body' => $this->commentBody,
        ]);

        $this->commentBody = '';
        $this->itTicket->load('comments.user');
    }

    public function deleteComment(int $commentId): void
    {
        $this->itTicket->comments()->where('id', $commentId)->delete();
        $this->itTicket->load('comments.user');
    }

    public function deleteTicket(): void
    {
        $this->itTicket->delete();
        $this->redirect(route('admin.it-board.index'), navigate: true);
    }
}; ?>

<div>
    <div class="mb-6 flex items-center justify-between">
        <div>
            <div class="flex items-center gap-3">
                <flux:heading size="xl">{{ $itTicket->title }}</flux:heading>
                <flux:badge :color="$itTicket->getTypeColor()">{{ $itTicket->getTypeLabel() }}</flux:badge>
            </div>
            <flux:text class="mt-1">{{ $itTicket->ticket_number }} &bull; Reported by {{ $itTicket->reporter->name }} &bull; {{ $itTicket->created_at->diffForHumans() }}</flux:text>
        </div>
        <flux:button variant="ghost" :href="route('admin.it-board.index')" wire:navigate>
            <div class="flex items-center">
                <flux:icon name="chevron-left" class="w-4 h-4 mr-1" />
                Back to Board
            </div>
        </flux:button>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Main Content (Left 2/3) -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Description -->
            <div class="bg-white dark:bg-zinc-800 rounded-lg border border-gray-200 dark:border-zinc-700 p-6">
                <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">Description</h3>
                @if($itTicket->description)
                    <div class="prose prose-sm dark:prose-invert max-w-none text-gray-600 dark:text-gray-400">
                        {!! nl2br(e($itTicket->description)) !!}
                    </div>
                @else
                    <flux:text class="text-gray-400 italic">No description provided.</flux:text>
                @endif
            </div>

            <!-- Comments -->
            <div class="bg-white dark:bg-zinc-800 rounded-lg border border-gray-200 dark:border-zinc-700">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-zinc-700">
                    <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300">
                        Comments ({{ $itTicket->comments->count() }})
                    </h3>
                </div>

                <!-- Comment List -->
                <div class="divide-y divide-gray-200 dark:divide-zinc-700">
                    @forelse($itTicket->comments as $comment)
                        <div class="px-6 py-4" wire:key="comment-{{ $comment->id }}">
                            <div class="flex items-start gap-3">
                                <flux:avatar size="sm" :name="$comment->user->name" />
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center gap-2">
                                            <span class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $comment->user->name }}</span>
                                            <span class="text-xs text-gray-400">{{ $comment->created_at->diffForHumans() }}</span>
                                        </div>
                                        @if($comment->user_id === auth()->id())
                                            <button wire:click="deleteComment({{ $comment->id }})" wire:confirm="Delete this comment?" class="text-gray-400 hover:text-red-500">
                                                <flux:icon name="trash" class="w-3.5 h-3.5" />
                                            </button>
                                        @endif
                                    </div>
                                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">{!! nl2br(e($comment->body)) !!}</p>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="px-6 py-8 text-center">
                            <flux:text class="text-gray-400">No comments yet.</flux:text>
                        </div>
                    @endforelse
                </div>

                <!-- Add Comment Form -->
                <div class="px-6 py-4 border-t border-gray-200 dark:border-zinc-700 bg-gray-50 dark:bg-zinc-900/50 rounded-b-lg">
                    <form wire:submit="addComment" class="flex gap-3">
                        <div class="flex-1">
                            <flux:textarea wire:model="commentBody" rows="2" placeholder="Add a comment..." />
                            @error('commentBody') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
                        </div>
                        <flux:button variant="primary" type="submit" class="self-end">
                            <span wire:loading.remove>Comment</span>
                            <span wire:loading>Sending...</span>
                        </flux:button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Sidebar (Right 1/3) -->
        <div class="space-y-4">
            <div class="bg-white dark:bg-zinc-800 rounded-lg border border-gray-200 dark:border-zinc-700 p-5 space-y-4">
                <div>
                    <flux:label class="mb-1.5">Status</flux:label>
                    <flux:select wire:model="status" wire:change="updateField('status')">
                        @foreach(ItTicket::statuses() as $s)
                            <option value="{{ $s }}">{{ (new ItTicket(['status' => $s]))->getStatusLabel() }}</option>
                        @endforeach
                    </flux:select>
                </div>

                <div>
                    <flux:label class="mb-1.5">Type</flux:label>
                    <flux:select wire:model="type" wire:change="updateField('type')">
                        @foreach(ItTicket::types() as $t)
                            <option value="{{ $t }}">{{ ucfirst($t) }}</option>
                        @endforeach
                    </flux:select>
                </div>

                <div>
                    <flux:label class="mb-1.5">Priority</flux:label>
                    <flux:select wire:model="priority" wire:change="updateField('priority')">
                        @foreach(ItTicket::priorities() as $p)
                            <option value="{{ $p }}">{{ ucfirst($p) }}</option>
                        @endforeach
                    </flux:select>
                </div>

                <div>
                    <flux:label class="mb-1.5">Assignee</flux:label>
                    <flux:select wire:model="assigneeId" wire:change="updateField('assigneeId')">
                        <option value="">Unassigned</option>
                        @foreach($this->adminUsers as $user)
                            <option value="{{ $user->id }}">{{ $user->name }}</option>
                        @endforeach
                    </flux:select>
                </div>

                <div>
                    <flux:label class="mb-1.5">Due Date</flux:label>
                    <flux:input wire:model="dueDate" wire:change="updateField('dueDate')" type="date" />
                </div>

                @if($itTicket->completed_at)
                    <div class="pt-2 border-t border-gray-200 dark:border-zinc-700">
                        <flux:text class="text-xs text-green-600">
                            Completed {{ $itTicket->completed_at->diffForHumans() }}
                        </flux:text>
                    </div>
                @endif
            </div>

            <!-- Danger Zone -->
            <div class="bg-white dark:bg-zinc-800 rounded-lg border border-red-200 dark:border-red-900/50 p-5">
                <h4 class="text-sm font-semibold text-red-600 mb-3">Danger Zone</h4>
                <flux:button variant="danger" size="sm" wire:click="deleteTicket" wire:confirm="Are you sure you want to delete this ticket?">
                    Delete Ticket
                </flux:button>
            </div>
        </div>
    </div>
</div>
