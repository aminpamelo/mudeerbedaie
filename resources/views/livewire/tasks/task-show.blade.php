<?php

declare(strict_types=1);

use App\Models\Task;
use App\Models\TaskComment;
use App\Enums\TaskStatus;
use App\Enums\TaskPriority;
use Livewire\Volt\Component;

new class extends Component {
    public Task $task;
    public bool $isReadOnly = false;
    public bool $canManage = false;

    public string $newComment = '';
    public bool $showActivityLog = false;

    public function mount(Task $task): void
    {
        $this->task = $task->load(['department', 'assignee', 'creator', 'comments.user', 'comments.replies.user', 'activityLogs.user']);

        $user = auth()->user();

        // Check access
        if (! $task->canBeViewedBy($user)) {
            abort(403, 'You do not have access to this task.');
        }

        $this->isReadOnly = $user->isAdmin();
        $this->canManage = $task->canBeEditedBy($user);
    }

    public function addComment(): void
    {
        if ($this->isReadOnly) {
            return;
        }

        $this->validate([
            'newComment' => 'required|string|max:2000',
        ]);

        $this->task->addComment($this->newComment, auth()->user());
        $this->task->logActivity('commented', null, null, 'Added a comment');

        $this->newComment = '';
        $this->task->refresh();
    }

    public function changeStatus(string $newStatus): void
    {
        if (! $this->canManage) {
            return;
        }

        $oldStatus = $this->task->status;
        $this->task->changeStatus(TaskStatus::from($newStatus));

        $this->task->logActivity(
            'status_changed',
            ['status' => $oldStatus->value],
            ['status' => $newStatus],
            "Status changed from {$oldStatus->label()} to " . TaskStatus::from($newStatus)->label()
        );

        $this->task->refresh();
    }

    public function deleteTask(): void
    {
        if (! $this->canManage) {
            return;
        }

        $department = $this->task->department;
        $this->task->delete();

        $this->redirect(route('tasks.department.board', $department->slug), navigate: true);
    }
}; ?>

<div>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <div class="flex items-center gap-3 mb-2">
                    <flux:badge size="sm" :color="$task->status->color()">{{ $task->status->label() }}</flux:badge>
                    <flux:badge size="sm" :color="$task->priority->color()">{{ $task->priority->label() }}</flux:badge>
                    <flux:badge size="sm" variant="outline" :color="$task->task_type->color()">{{ $task->task_type->label() }}</flux:badge>
                </div>
                <flux:heading size="xl">{{ $task->title }}</flux:heading>
                <flux:text class="mt-2">{{ $task->task_number }} &bull; {{ $task->department->name }}</flux:text>
            </div>
            <div class="flex items-center gap-2">
                @if($canManage)
                <flux:button variant="outline" :href="route('tasks.edit', $task)">
                    <flux:icon name="pencil" class="w-4 h-4 mr-1" />
                    {{ __('Edit') }}
                </flux:button>
                @endif
                <flux:button variant="ghost" :href="route('tasks.department.board', $task->department->slug)">
                    <flux:icon name="arrow-left" class="w-4 h-4 mr-1" />
                    {{ __('Back') }}
                </flux:button>
            </div>
        </div>
    </x-slot>

    @if($isReadOnly)
    <div class="mb-6">
        <flux:callout type="info" icon="eye">
            <flux:callout.heading>{{ __('Read-Only Access') }}</flux:callout.heading>
            <flux:callout.text>{{ __('You are viewing this task in read-only mode.') }}</flux:callout.text>
        </flux:callout>
    </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Main Content --}}
        <div class="lg:col-span-2 space-y-6">
            {{-- Description --}}
            <div class="bg-white dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700 p-6">
                <flux:heading size="lg" class="mb-4">{{ __('Description') }}</flux:heading>
                @if($task->description)
                <div class="prose dark:prose-invert max-w-none">
                    {!! nl2br(e($task->description)) !!}
                </div>
                @else
                <flux:text class="text-zinc-500 italic">{{ __('No description provided.') }}</flux:text>
                @endif
            </div>

            {{-- Comments --}}
            <div class="bg-white dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700">
                <div class="p-4 border-b border-zinc-200 dark:border-zinc-700 flex items-center justify-between">
                    <flux:heading size="lg">{{ __('Comments') }} ({{ $task->comments->count() }})</flux:heading>
                </div>

                <div class="p-4 space-y-4 max-h-96 overflow-y-auto">
                    @forelse($task->rootComments as $comment)
                    <div class="flex gap-3" wire:key="comment-{{ $comment->id }}">
                        <flux:avatar size="sm" :name="$comment->user->name" />
                        <div class="flex-1">
                            <div class="flex items-center gap-2">
                                <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ $comment->user->name }}</span>
                                <span class="text-sm text-zinc-500">{{ $comment->created_at->diffForHumans() }}</span>
                            </div>
                            <div class="mt-1 text-zinc-700 dark:text-zinc-300">
                                {!! nl2br(e($comment->content)) !!}
                            </div>

                            {{-- Replies --}}
                            @if($comment->replies->count() > 0)
                            <div class="mt-3 ml-6 space-y-3">
                                @foreach($comment->replies as $reply)
                                <div class="flex gap-3" wire:key="reply-{{ $reply->id }}">
                                    <flux:avatar size="xs" :name="$reply->user->name" />
                                    <div class="flex-1">
                                        <div class="flex items-center gap-2">
                                            <span class="font-medium text-sm text-zinc-900 dark:text-zinc-100">{{ $reply->user->name }}</span>
                                            <span class="text-xs text-zinc-500">{{ $reply->created_at->diffForHumans() }}</span>
                                        </div>
                                        <div class="mt-1 text-sm text-zinc-700 dark:text-zinc-300">
                                            {!! nl2br(e($reply->content)) !!}
                                        </div>
                                    </div>
                                </div>
                                @endforeach
                            </div>
                            @endif
                        </div>
                    </div>
                    @empty
                    <flux:text class="text-center py-4 text-zinc-500">{{ __('No comments yet.') }}</flux:text>
                    @endforelse
                </div>

                {{-- Add Comment --}}
                @if(! $isReadOnly)
                <div class="p-4 border-t border-zinc-200 dark:border-zinc-700">
                    <form wire:submit="addComment" class="flex gap-3">
                        <flux:avatar size="sm" :name="auth()->user()->name" />
                        <div class="flex-1">
                            <flux:textarea
                                wire:model="newComment"
                                rows="2"
                                placeholder="{{ __('Write a comment...') }}"
                            />
                            <div class="mt-2 flex justify-end">
                                <flux:button type="submit" variant="primary" size="sm">
                                    {{ __('Post Comment') }}
                                </flux:button>
                            </div>
                        </div>
                    </form>
                </div>
                @endif
            </div>

            {{-- Activity Log --}}
            <div class="bg-white dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700">
                <button wire:click="$toggle('showActivityLog')"
                        class="w-full p-4 border-b border-zinc-200 dark:border-zinc-700 flex items-center justify-between hover:bg-zinc-50 dark:hover:bg-zinc-700 transition-colors">
                    <flux:heading size="lg">{{ __('Activity Log') }}</flux:heading>
                    <flux:icon :name="$showActivityLog ? 'chevron-up' : 'chevron-down'" class="w-5 h-5 text-zinc-400" />
                </button>

                @if($showActivityLog)
                <div class="p-4 space-y-3 max-h-64 overflow-y-auto">
                    @forelse($task->activityLogs as $log)
                    <div class="flex items-start gap-3 text-sm">
                        <flux:avatar size="xs" :name="$log->user->name" />
                        <div>
                            <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ $log->user->name }}</span>
                            <span class="text-zinc-600 dark:text-zinc-400">{{ $log->description }}</span>
                            <span class="text-zinc-400 ml-2">{{ $log->created_at->diffForHumans() }}</span>
                        </div>
                    </div>
                    @empty
                    <flux:text class="text-center py-4 text-zinc-500">{{ __('No activity recorded.') }}</flux:text>
                    @endforelse
                </div>
                @endif
            </div>
        </div>

        {{-- Sidebar --}}
        <div class="space-y-6">
            {{-- Task Details --}}
            <div class="bg-white dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700 p-6">
                <flux:heading size="lg" class="mb-4">{{ __('Details') }}</flux:heading>

                <dl class="space-y-4">
                    {{-- Status --}}
                    <div>
                        <dt class="text-sm font-medium text-zinc-500 mb-1">{{ __('Status') }}</dt>
                        <dd>
                            @if($canManage)
                            <flux:select wire:model.live="task.status" wire:change="changeStatus($event.target.value)">
                                @foreach(TaskStatus::cases() as $status)
                                <flux:select.option value="{{ $status->value }}" {{ $task->status === $status ? 'selected' : '' }}>{{ $status->label() }}</flux:select.option>
                                @endforeach
                            </flux:select>
                            @else
                            <flux:badge :color="$task->status->color()">{{ $task->status->label() }}</flux:badge>
                            @endif
                        </dd>
                    </div>

                    {{-- Priority --}}
                    <div>
                        <dt class="text-sm font-medium text-zinc-500 mb-1">{{ __('Priority') }}</dt>
                        <dd>
                            <flux:badge :color="$task->priority->color()">{{ $task->priority->label() }}</flux:badge>
                        </dd>
                    </div>

                    {{-- Type --}}
                    <div>
                        <dt class="text-sm font-medium text-zinc-500 mb-1">{{ __('Type') }}</dt>
                        <dd>
                            <flux:badge variant="outline" :color="$task->task_type->color()">{{ $task->task_type->label() }}</flux:badge>
                        </dd>
                    </div>

                    {{-- Assignee --}}
                    <div>
                        <dt class="text-sm font-medium text-zinc-500 mb-1">{{ __('Assignee') }}</dt>
                        <dd>
                            @if($task->assignee)
                            <div class="flex items-center gap-2">
                                <flux:avatar size="sm" :name="$task->assignee->name" />
                                <span class="text-zinc-900 dark:text-zinc-100">{{ $task->assignee->name }}</span>
                            </div>
                            @else
                            <span class="text-zinc-500">{{ __('Unassigned') }}</span>
                            @endif
                        </dd>
                    </div>

                    {{-- Due Date --}}
                    <div>
                        <dt class="text-sm font-medium text-zinc-500 mb-1">{{ __('Due Date') }}</dt>
                        <dd>
                            @if($task->due_date)
                            <span class="{{ $task->isOverdue() ? 'text-red-500 font-medium' : 'text-zinc-900 dark:text-zinc-100' }}">
                                {{ $task->due_date->format('d M Y') }}
                                @if($task->due_time)
                                {{ __('at') }} {{ $task->due_time }}
                                @endif
                                @if($task->isOverdue())
                                <span class="block text-sm">{{ __('Overdue') }}</span>
                                @endif
                            </span>
                            @else
                            <span class="text-zinc-500">{{ __('Not set') }}</span>
                            @endif
                        </dd>
                    </div>

                    {{-- Estimated Hours --}}
                    @if($task->estimated_hours)
                    <div>
                        <dt class="text-sm font-medium text-zinc-500 mb-1">{{ __('Estimated Hours') }}</dt>
                        <dd class="text-zinc-900 dark:text-zinc-100">{{ $task->estimated_hours }} {{ __('hours') }}</dd>
                    </div>
                    @endif

                    {{-- Created By --}}
                    <div>
                        <dt class="text-sm font-medium text-zinc-500 mb-1">{{ __('Created By') }}</dt>
                        <dd>
                            <div class="flex items-center gap-2">
                                <flux:avatar size="sm" :name="$task->creator->name" />
                                <span class="text-zinc-900 dark:text-zinc-100">{{ $task->creator->name }}</span>
                            </div>
                        </dd>
                    </div>

                    {{-- Created At --}}
                    <div>
                        <dt class="text-sm font-medium text-zinc-500 mb-1">{{ __('Created At') }}</dt>
                        <dd class="text-zinc-900 dark:text-zinc-100">{{ $task->created_at->format('d M Y H:i') }}</dd>
                    </div>

                    {{-- Completed At --}}
                    @if($task->completed_at)
                    <div>
                        <dt class="text-sm font-medium text-zinc-500 mb-1">{{ __('Completed At') }}</dt>
                        <dd class="text-green-600">{{ $task->completed_at->format('d M Y H:i') }}</dd>
                    </div>
                    @endif
                </dl>
            </div>

            {{-- Danger Zone --}}
            @if($canManage)
            <div class="bg-white dark:bg-zinc-800 rounded-lg border border-red-200 dark:border-red-800 p-6">
                <flux:heading size="lg" class="mb-4 text-red-600">{{ __('Danger Zone') }}</flux:heading>
                <flux:button variant="danger" wire:click="deleteTask" wire:confirm="{{ __('Are you sure you want to delete this task?') }}">
                    <flux:icon name="trash" class="w-4 h-4 mr-1" />
                    {{ __('Delete Task') }}
                </flux:button>
            </div>
            @endif
        </div>
    </div>
</div>
