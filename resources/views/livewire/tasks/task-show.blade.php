<?php

declare(strict_types=1);

use App\Models\Task;
use App\Models\TaskComment;
use App\Models\TaskAttachment;
use App\Models\TaskTemplate;
use App\Enums\TaskStatus;
use App\Enums\TaskPriority;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new class extends Component {
    use WithFileUploads;

    public Task $task;
    public bool $isReadOnly = false;
    public bool $canManage = false;

    public string $newComment = '';
    public bool $showActivityLog = true;

    public $attachments = [];

    public bool $showSaveTemplate = false;
    public string $templateName = '';
    public string $templateDescription = '';

    public function mount(Task $task): void
    {
        $this->task = $task->load(['department', 'assignee', 'creator', 'comments.user', 'comments.replies.user', 'activityLogs.user', 'attachments.user']);

        $user = auth()->user();

        // Check access
        if (! $task->canBeViewedBy($user)) {
            abort(403, 'You do not have access to this task.');
        }

        // Admin is view-only for department tasks (can only edit own created tasks)
        if ($user->isAdmin()) {
            $this->isReadOnly = ! $task->canBeEditedBy($user);
            $this->canManage = (int) $task->created_by === (int) $user->id;
        } elseif ($task->department) {
            $this->isReadOnly = ! $user->canEditTasks($task->department);
            $this->canManage = $user->canManageTasks($task->department);
        } else {
            $this->isReadOnly = true;
            $this->canManage = false;
        }
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

    public function uploadAttachments(): void
    {
        if ($this->isReadOnly) {
            return;
        }

        $this->validate([
            'attachments.*' => 'file|max:10240',
        ]);

        foreach ($this->attachments as $file) {
            $path = $file->store('task-attachments/' . $this->task->id, 'public');

            $this->task->attachments()->create([
                'user_id' => auth()->id(),
                'filename' => basename($path),
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
                'disk' => 'public',
                'path' => $path,
            ]);
        }

        $this->task->logActivity('attachment_added', null, null, 'Added ' . count($this->attachments) . ' attachment(s)');
        $this->attachments = [];
        $this->task->refresh();
    }

    public function deleteAttachment(int $attachmentId): void
    {
        if (! $this->canManage) {
            return;
        }

        $attachment = TaskAttachment::findOrFail($attachmentId);

        if ($attachment->task_id !== $this->task->id) {
            return;
        }

        $name = $attachment->original_name;
        $attachment->delete();

        $this->task->logActivity('attachment_deleted', null, null, "Deleted attachment: {$name}");
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

    public function saveAsTemplate(): void
    {
        if (! $this->canManage) {
            return;
        }

        $this->validate([
            'templateName' => 'required|string|max:255',
            'templateDescription' => 'nullable|string|max:1000',
        ]);

        TaskTemplate::create([
            'department_id' => $this->task->department_id,
            'created_by' => auth()->id(),
            'name' => $this->templateName,
            'description' => $this->templateDescription,
            'task_type' => $this->task->task_type->value,
            'priority' => $this->task->priority->value,
            'estimated_hours' => $this->task->estimated_hours,
            'template_data' => [
                'title' => $this->task->title,
                'description' => $this->task->description,
            ],
        ]);

        $this->showSaveTemplate = false;
        $this->templateName = '';
        $this->templateDescription = '';

        session()->flash('message', __('Template saved successfully!'));
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
                <flux:text class="mt-2">{{ $task->task_number }} &bull; {{ $task->department?->name ?? __('Personal') }}</flux:text>
            </div>
            <div class="flex items-center gap-2">
                @if($canManage)
                <flux:button variant="ghost" size="sm" wire:click="$set('showSaveTemplate', true)">
                    <div class="flex items-center justify-center">
                        <flux:icon name="bookmark" class="w-4 h-4 mr-1" />
                        {{ __('Save as Template') }}
                    </div>
                </flux:button>
                <flux:button variant="outline" :href="route('tasks.edit', $task)">
                    <flux:icon name="pencil" class="w-4 h-4 mr-1" />
                    {{ __('Edit') }}
                </flux:button>
                @endif
                <flux:button variant="ghost" :href="$task->department ? route('tasks.department.board', $task->department->slug) : route('tasks.my-tasks')">
                    <flux:icon name="arrow-left" class="w-4 h-4 mr-1" />
                    {{ __('Back') }}
                </flux:button>
            </div>
        </div>
    </x-slot>

    @if(session('message'))
    <div class="mb-6">
        <flux:callout type="success" icon="check-circle">
            <flux:callout.text>{{ session('message') }}</flux:callout.text>
        </flux:callout>
    </div>
    @endif

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
            {{-- Title --}}
            <div class="bg-white dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700 p-6">
                <div class="flex items-center gap-3 mb-3">
                    <flux:badge size="sm" :color="$task->status->color()">{{ $task->status->label() }}</flux:badge>
                    <flux:badge size="sm" :color="$task->priority->color()">{{ $task->priority->label() }}</flux:badge>
                    <flux:badge size="sm" variant="outline" :color="$task->task_type->color()">{{ $task->task_type->label() }}</flux:badge>
                </div>
                <flux:heading size="xl">{{ $task->title }}</flux:heading>
                <flux:text class="mt-1">{{ $task->task_number }} &bull; {{ $task->department?->name ?? __('Personal') }}</flux:text>
            </div>

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

            {{-- Attachments --}}
            <div class="bg-white dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700">
                <div class="p-4 border-b border-zinc-200 dark:border-zinc-700 flex items-center justify-between">
                    <flux:heading size="lg">{{ __('Attachments') }} ({{ $task->attachments->count() }})</flux:heading>
                </div>

                <div class="p-4">
                    {{-- Attachment List --}}
                    @if($task->attachments->count() > 0)
                    <div class="space-y-3 mb-4">
                        @foreach($task->attachments as $attachment)
                        <div class="flex items-center justify-between p-3 bg-zinc-50 dark:bg-zinc-700/50 rounded-lg" wire:key="attachment-{{ $attachment->id }}">
                            <div class="flex items-center gap-3 min-w-0">
                                {{-- File Icon --}}
                                <div class="w-10 h-10 rounded-lg flex items-center justify-center shrink-0
                                    {{ $attachment->isImage() ? 'bg-blue-100 dark:bg-blue-900/30' : ($attachment->isPdf() ? 'bg-red-100 dark:bg-red-900/30' : 'bg-zinc-100 dark:bg-zinc-600') }}">
                                    @if($attachment->isImage())
                                    <flux:icon name="photo" class="w-5 h-5 text-blue-600 dark:text-blue-400" />
                                    @elseif($attachment->isPdf())
                                    <flux:icon name="document-text" class="w-5 h-5 text-red-600 dark:text-red-400" />
                                    @else
                                    <flux:icon name="paper-clip" class="w-5 h-5 text-zinc-500" />
                                    @endif
                                </div>

                                <div class="min-w-0">
                                    <a href="{{ $attachment->getUrl() }}" target="_blank" class="text-sm font-medium text-zinc-900 dark:text-zinc-100 hover:text-violet-600 truncate block">
                                        {{ $attachment->original_name }}
                                    </a>
                                    <p class="text-xs text-zinc-500">
                                        {{ $attachment->getHumanSize() }} &bull; {{ $attachment->user->name }} &bull; {{ $attachment->created_at->diffForHumans() }}
                                    </p>
                                </div>
                            </div>

                            <div class="flex items-center gap-1 shrink-0">
                                <a href="{{ $attachment->getUrl() }}" target="_blank" class="p-1.5 text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-300 rounded">
                                    <flux:icon name="arrow-down-tray" class="w-4 h-4" />
                                </a>
                                @if($canManage)
                                <button wire:click="deleteAttachment({{ $attachment->id }})" wire:confirm="{{ __('Delete this attachment?') }}" class="p-1.5 text-zinc-400 hover:text-red-600 rounded">
                                    <flux:icon name="trash" class="w-4 h-4" />
                                </button>
                                @endif
                            </div>
                        </div>
                        @endforeach
                    </div>
                    @else
                    <flux:text class="text-center py-4 text-zinc-500">{{ __('No attachments yet.') }}</flux:text>
                    @endif

                    {{-- Upload Form --}}
                    @if(! $isReadOnly)
                    <form wire:submit="uploadAttachments" class="mt-4 pt-4 border-t border-zinc-200 dark:border-zinc-700">
                        <div>
                            <input type="file" wire:model="attachments" multiple class="block w-full text-sm text-zinc-500
                                file:mr-4 file:py-2 file:px-4
                                file:rounded-lg file:border-0
                                file:text-sm file:font-medium
                                file:bg-violet-50 file:text-violet-700
                                hover:file:bg-violet-100
                                dark:file:bg-violet-900/30 dark:file:text-violet-300
                                dark:hover:file:bg-violet-900/50" />
                            <p class="mt-1 text-xs text-zinc-400">{{ __('Max 10MB per file. Images, PDFs, documents supported.') }}</p>
                        </div>

                        @error('attachments.*')
                        <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                        @enderror

                        @if(count($attachments) > 0)
                        <div class="mt-3 flex items-center gap-2">
                            <flux:button type="submit" variant="primary" size="sm">
                                <div class="flex items-center justify-center">
                                    <flux:icon name="arrow-up-tray" class="w-4 h-4 mr-1" />
                                    {{ __('Upload :count file(s)', ['count' => count($attachments)]) }}
                                </div>
                            </flux:button>
                            <div wire:loading wire:target="attachments">
                                <flux:text class="text-sm text-zinc-500">{{ __('Processing...') }}</flux:text>
                            </div>
                        </div>
                        @endif
                    </form>
                    @endif
                </div>
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
                            <flux:select wire:change="changeStatus($event.target.value)">
                                @foreach(TaskStatus::cases() as $status)
                                <flux:select.option value="{{ $status->value }}" :selected="$task->status === $status">{{ $status->label() }}</flux:select.option>
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

            {{-- Workflow Data --}}
            @if($task->metadata && is_array($task->metadata) && count($task->metadata) > 0)
            <div class="bg-white dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700 p-6">
                <flux:heading size="lg" class="mb-4">{{ __('Maklumat Workflow') }}</flux:heading>
                <dl class="space-y-3">
                    @foreach($task->metadata as $key => $value)
                    <div>
                        <dt class="text-xs font-medium text-zinc-500 uppercase tracking-wide">
                            {{ str_replace('_', ' ', $key) }}
                        </dt>
                        <dd class="mt-0.5 text-sm text-zinc-900 dark:text-zinc-100">
                            @if(is_array($value))
                                @foreach($value as $subKey => $subValue)
                                <span class="block text-sm">
                                    <span class="text-zinc-500">{{ str_replace('_', ' ', $subKey) }}:</span>
                                    {{ $subValue !== '' && $subValue !== null ? $subValue : '—' }}
                                </span>
                                @endforeach
                            @else
                                {{ $value !== '' && $value !== null ? $value : '—' }}
                            @endif
                        </dd>
                    </div>
                    @endforeach
                </dl>
            </div>
            @endif

            {{-- Danger Zone --}}
            @if($canManage)
            <div class="bg-white dark:bg-zinc-800 rounded-lg border border-red-200 dark:border-red-800 p-6">
                <flux:heading size="lg" class="mb-2 text-red-600">{{ __('Danger Zone') }}</flux:heading>
                <flux:text class="mb-4 text-sm text-zinc-500">{{ __('This action cannot be undone.') }}</flux:text>
                <flux:button variant="danger" wire:click="deleteTask" wire:confirm="{{ __('Are you sure you want to delete this task?') }}">
                    <div class="flex items-center justify-center">
                        <flux:icon name="trash" class="w-4 h-4 mr-1" />
                        {{ __('Delete Task') }}
                    </div>
                </flux:button>
            </div>
            @endif
        </div>
    </div>

    {{-- Save as Template Modal --}}
    @if($canManage)
    <flux:modal wire:model="showSaveTemplate" class="max-w-md">
        <div class="p-6">
            <flux:heading size="lg" class="mb-4">{{ __('Save Task as Template') }}</flux:heading>
            <flux:text class="mb-4 text-sm">{{ __('Save this task configuration as a reusable template for your department.') }}</flux:text>

            <form wire:submit="saveAsTemplate" class="space-y-4">
                <flux:field>
                    <flux:label>{{ __('Template Name') }}</flux:label>
                    <flux:input wire:model="templateName" placeholder="{{ __('e.g. Weekly Report Task') }}" />
                    <flux:error name="templateName" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Description') }}</flux:label>
                    <flux:textarea wire:model="templateDescription" rows="3" placeholder="{{ __('Brief description of when to use this template...') }}" />
                    <flux:error name="templateDescription" />
                </flux:field>

                <div class="bg-zinc-50 dark:bg-zinc-700/50 rounded-lg p-3">
                    <p class="text-xs font-medium text-zinc-500 mb-2">{{ __('Template will include:') }}</p>
                    <ul class="text-xs text-zinc-500 space-y-1">
                        <li>&bull; {{ __('Title') }}: {{ $task->title }}</li>
                        <li>&bull; {{ __('Type') }}: {{ $task->task_type->label() }}</li>
                        <li>&bull; {{ __('Priority') }}: {{ $task->priority->label() }}</li>
                        @if($task->estimated_hours)
                        <li>&bull; {{ __('Estimated Hours') }}: {{ $task->estimated_hours }}</li>
                        @endif
                    </ul>
                </div>

                <div class="flex justify-end gap-2 pt-4">
                    <flux:button variant="ghost" wire:click="$set('showSaveTemplate', false)">{{ __('Cancel') }}</flux:button>
                    <flux:button type="submit" variant="primary">{{ __('Save Template') }}</flux:button>
                </div>
            </form>
        </div>
    </flux:modal>
    @endif
</div>
