<?php

declare(strict_types=1);

use App\Models\Department;
use App\Models\TaskTemplate;
use App\Models\User;
use App\Enums\DepartmentRole;
use Livewire\Volt\Component;

new class extends Component {
    public Department $department;

    public bool $showAddMember = false;
    public string $searchUser = '';
    public ?int $selectedUserId = null;
    public string $selectedRole = 'member';

    public bool $showCreateTemplate = false;
    public string $tplName = '';
    public string $tplDescription = '';
    public string $tplTaskType = 'adhoc';
    public string $tplPriority = 'medium';
    public ?float $tplEstimatedHours = null;
    public string $tplTitle = '';
    public string $tplTaskDescription = '';

    public function mount(Department $department): void
    {
        $this->department = $department->load(['users', 'children', 'parent']);
        $user = auth()->user();

        // Admin and PICs can access settings
        if (! $user->isAdmin() && ! $user->canManageTasks($department)) {
            abort(403, 'You do not have permission to manage this department.');
        }
    }

    public function getAvailableUsers()
    {
        $existingUserIds = $this->department->users->pluck('id');

        return User::where('status', 'active')
            ->whereNotIn('id', $existingUserIds)
            ->when($this->searchUser, function ($q) {
                $q->where('name', 'like', "%{$this->searchUser}%")
                  ->orWhere('email', 'like', "%{$this->searchUser}%");
            })
            ->limit(10)
            ->get();
    }

    public function addMember(): void
    {
        $this->validate([
            'selectedUserId' => 'required|exists:users,id',
            'selectedRole' => 'required|in:department_pic,member',
        ]);

        $this->department->users()->attach($this->selectedUserId, [
            'role' => $this->selectedRole,
            'assigned_by' => auth()->id(),
        ]);

        $this->reset(['showAddMember', 'selectedUserId', 'selectedRole', 'searchUser']);
        $this->department->refresh();
    }

    public function removeMember(int $userId): void
    {
        // Prevent removing self if you're the only PIC
        $pics = $this->department->pics;
        if ($pics->count() === 1 && $pics->first()->id === $userId) {
            session()->flash('error', __('Cannot remove the only PIC from the department.'));
            return;
        }

        $this->department->users()->detach($userId);
        $this->department->refresh();
    }

    public function changeRole(int $userId, string $newRole): void
    {
        // Prevent changing role if this would leave no PICs
        if ($newRole === 'member') {
            $pics = $this->department->pics;
            if ($pics->count() === 1 && $pics->first()->id === $userId) {
                session()->flash('error', __('Cannot change role. At least one PIC is required.'));
                return;
            }
        }

        $this->department->users()->updateExistingPivot($userId, [
            'role' => $newRole,
        ]);

        $this->department->refresh();
    }

    public function getTemplates()
    {
        return TaskTemplate::where('department_id', $this->department->id)
            ->with('creator')
            ->orderBy('name')
            ->get();
    }

    public function createTemplate(): void
    {
        $this->validate([
            'tplName' => 'required|string|max:255',
            'tplDescription' => 'nullable|string|max:1000',
            'tplTaskType' => 'required|in:kpi,adhoc',
            'tplPriority' => 'required|in:low,medium,high,urgent',
            'tplEstimatedHours' => 'nullable|numeric|min:0',
            'tplTitle' => 'nullable|string|max:255',
            'tplTaskDescription' => 'nullable|string',
        ]);

        TaskTemplate::create([
            'department_id' => $this->department->id,
            'created_by' => auth()->id(),
            'name' => $this->tplName,
            'description' => $this->tplDescription,
            'task_type' => $this->tplTaskType,
            'priority' => $this->tplPriority,
            'estimated_hours' => $this->tplEstimatedHours,
            'template_data' => [
                'title' => $this->tplTitle,
                'description' => $this->tplTaskDescription,
            ],
        ]);

        $this->reset(['showCreateTemplate', 'tplName', 'tplDescription', 'tplTaskType', 'tplPriority', 'tplEstimatedHours', 'tplTitle', 'tplTaskDescription']);
    }

    public function deleteTemplate(int $templateId): void
    {
        $template = TaskTemplate::where('department_id', $this->department->id)->findOrFail($templateId);
        $template->delete();
    }
}; ?>

<div>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <div class="flex items-center gap-3">
                    <div class="w-4 h-4 rounded-full" style="background-color: {{ $department->color }}"></div>
                    <flux:heading size="xl">{{ $department->name }} - {{ __('Settings') }}</flux:heading>
                </div>
                <flux:text class="mt-2">{{ __('Manage department members and PICs') }}</flux:text>
            </div>
            <flux:button variant="ghost" :href="route('tasks.department.board', $department->slug)">
                <flux:icon name="arrow-left" class="w-4 h-4 mr-1" />
                {{ __('Back to Board') }}
            </flux:button>
        </div>
    </x-slot>

    @if(session('error'))
    <div class="mb-6">
        <flux:callout type="error">
            <flux:callout.text>{{ session('error') }}</flux:callout.text>
        </flux:callout>
    </div>
    @endif

    <div class="max-w-4xl mx-auto space-y-6">
        {{-- Department Info --}}
        <div class="bg-white dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700 p-6">
            <flux:heading size="lg" class="mb-4">{{ __('Department Information') }}</flux:heading>

            <dl class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <dt class="text-sm font-medium text-zinc-500">{{ __('Name') }}</dt>
                    <dd class="mt-1 text-zinc-900 dark:text-zinc-100">{{ $department->name }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-zinc-500">{{ __('Slug') }}</dt>
                    <dd class="mt-1 text-zinc-900 dark:text-zinc-100">{{ $department->slug }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-zinc-500">{{ __('Color') }}</dt>
                    <dd class="mt-1 flex items-center gap-2">
                        <div class="w-6 h-6 rounded" style="background-color: {{ $department->color }}"></div>
                        <span class="text-zinc-900 dark:text-zinc-100">{{ $department->color }}</span>
                    </dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-zinc-500">{{ __('Status') }}</dt>
                    <dd class="mt-1">
                        <flux:badge :color="$department->status === 'active' ? 'green' : 'zinc'">
                            {{ ucfirst($department->status) }}
                        </flux:badge>
                    </dd>
                </div>
                @if($department->description)
                <div class="md:col-span-2">
                    <dt class="text-sm font-medium text-zinc-500">{{ __('Description') }}</dt>
                    <dd class="mt-1 text-zinc-900 dark:text-zinc-100">{{ $department->description }}</dd>
                </div>
                @endif
            </dl>
        </div>

        {{-- Members --}}
        @if($department->isChild())
        {{-- Sub-department: members are inherited from parent --}}
        <div class="bg-white dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700 p-6">
            <flux:heading size="lg" class="mb-4">{{ __('Department Members') }}</flux:heading>
            <flux:callout type="info">
                <flux:callout.heading>{{ __('Managed by Parent Department') }}</flux:callout.heading>
                <flux:callout.text>
                    {{ __('Members for this sub-department are managed through the parent department') }}
                    (<strong>{{ $department->parent->name }}</strong>).
                    {{ __('PIC and members of the parent department automatically have access to this sub-department.') }}
                </flux:callout.text>
            </flux:callout>

            @php
                $parentMembers = $department->parent->users()->orderByRaw("CASE WHEN department_users.role = 'department_pic' THEN 0 ELSE 1 END")->get();
            @endphp

            @if($parentMembers->count() > 0)
            <div class="mt-4">
                <flux:text class="text-sm font-medium mb-3">{{ __('Inherited Members from :parent', ['parent' => $department->parent->name]) }}</flux:text>
                <div class="space-y-2">
                    @foreach($parentMembers as $member)
                    <div class="flex items-center justify-between p-3 bg-zinc-50 dark:bg-zinc-900 rounded-lg" wire:key="inherited-{{ $member->id }}">
                        <div class="flex items-center gap-3">
                            <flux:avatar :name="$member->name" size="sm" />
                            <div>
                                <p class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $member->name }}</p>
                                <p class="text-xs text-zinc-500">{{ $member->email }}</p>
                            </div>
                        </div>
                        <flux:badge size="sm" :color="$member->pivot->role === 'department_pic' ? 'violet' : 'zinc'">
                            {{ $member->pivot->role === 'department_pic' ? __('PIC') : __('Member') }}
                        </flux:badge>
                    </div>
                    @endforeach
                </div>
            </div>
            @endif
        </div>
        @else
        {{-- Top-level department: full member management --}}
        <div class="bg-white dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700">
            <div class="p-4 border-b border-zinc-200 dark:border-zinc-700 flex items-center justify-between">
                <flux:heading size="lg">{{ __('Department Members') }}</flux:heading>
                <flux:button variant="primary" size="sm" wire:click="$set('showAddMember', true)">
                    <flux:icon name="plus" class="w-4 h-4 mr-1" />
                    {{ __('Add Member') }}
                </flux:button>
            </div>

            @if($department->children->count() > 0)
            <div class="p-4 border-b border-zinc-200 dark:border-zinc-700">
                <flux:callout type="info">
                    <flux:callout.text>
                        {{ __('PIC and members here automatically manage all sub-departments:') }}
                        <span class="font-medium">{{ $department->children->pluck('name')->join(', ') }}</span>
                    </flux:callout.text>
                </flux:callout>
            </div>
            @endif

            <div class="divide-y divide-zinc-200 dark:divide-zinc-700">
                @forelse($department->users as $member)
                <div class="p-4 flex items-center justify-between" wire:key="member-{{ $member->id }}">
                    <div class="flex items-center gap-3">
                        <flux:avatar :name="$member->name" />
                        <div>
                            <p class="font-medium text-zinc-900 dark:text-zinc-100">{{ $member->name }}</p>
                            <p class="text-sm text-zinc-500">{{ $member->email }}</p>
                        </div>
                    </div>

                    <div class="flex items-center gap-3">
                        <flux:select
                            wire:change="changeRole({{ $member->id }}, $event.target.value)"
                            class="w-40"
                        >
                            @foreach(DepartmentRole::cases() as $role)
                            <flux:select.option
                                value="{{ $role->value }}"
                                :selected="$member->pivot->role === $role->value"
                            >{{ $role->label() }}</flux:select.option>
                            @endforeach
                        </flux:select>

                        <flux:button
                            variant="ghost"
                            size="sm"
                            wire:click="removeMember({{ $member->id }})"
                            wire:confirm="{{ __('Are you sure you want to remove this member?') }}"
                        >
                            <flux:icon name="trash" class="w-4 h-4 text-red-500" />
                        </flux:button>
                    </div>
                </div>
                @empty
                <div class="p-8 text-center">
                    <flux:icon name="users" class="w-12 h-12 mx-auto text-zinc-400" />
                    <flux:text class="mt-2">{{ __('No members in this department.') }}</flux:text>
                </div>
                @endforelse
            </div>
        </div>
        @endif

        {{-- Task Templates --}}
        <div class="bg-white dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700">
            <div class="p-4 border-b border-zinc-200 dark:border-zinc-700 flex items-center justify-between">
                <flux:heading size="lg">{{ __('Task Templates') }}</flux:heading>
                <flux:button variant="primary" size="sm" wire:click="$set('showCreateTemplate', true)">
                    <flux:icon name="plus" class="w-4 h-4 mr-1" />
                    {{ __('Create Template') }}
                </flux:button>
            </div>

            @php $templates = $this->getTemplates(); @endphp

            <div class="divide-y divide-zinc-200 dark:divide-zinc-700">
                @forelse($templates as $template)
                <div class="p-4 flex items-center justify-between" wire:key="tpl-{{ $template->id }}">
                    <div class="min-w-0">
                        <p class="font-medium text-zinc-900 dark:text-zinc-100">{{ $template->name }}</p>
                        @if($template->description)
                        <p class="text-sm text-zinc-500 truncate">{{ $template->description }}</p>
                        @endif
                        <div class="flex items-center gap-2 mt-1">
                            <flux:badge size="sm" :color="$template->task_type->color()">{{ $template->task_type->label() }}</flux:badge>
                            <flux:badge size="sm" :color="$template->priority->color()">{{ $template->priority->label() }}</flux:badge>
                            <span class="text-xs text-zinc-400">{{ __('by') }} {{ $template->creator->name }}</span>
                        </div>
                    </div>
                    <flux:button
                        variant="ghost"
                        size="sm"
                        wire:click="deleteTemplate({{ $template->id }})"
                        wire:confirm="{{ __('Delete this template?') }}"
                    >
                        <flux:icon name="trash" class="w-4 h-4 text-red-500" />
                    </flux:button>
                </div>
                @empty
                <div class="p-8 text-center">
                    <flux:icon name="bookmark" class="w-12 h-12 mx-auto text-zinc-400" />
                    <flux:text class="mt-2">{{ __('No templates yet. Create one to speed up task creation.') }}</flux:text>
                </div>
                @endforelse
            </div>
        </div>

        {{-- Role Descriptions --}}
        <div class="bg-white dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700 p-6">
            <flux:heading size="lg" class="mb-4">{{ __('Role Descriptions') }}</flux:heading>

            <div class="space-y-4">
                @foreach(DepartmentRole::cases() as $role)
                <div class="flex items-start gap-3">
                    <flux:badge :color="$role === DepartmentRole::DEPARTMENT_PIC ? 'violet' : 'zinc'">
                        {{ $role->label() }}
                    </flux:badge>
                    <p class="text-zinc-600 dark:text-zinc-400">{{ $role->description() }}</p>
                </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- Add Member Modal --}}
    <flux:modal wire:model="showAddMember" class="max-w-md">
        <div class="p-6">
            <flux:heading size="lg" class="mb-4">{{ __('Add Department Member') }}</flux:heading>

            <form wire:submit="addMember" class="space-y-4">
                <flux:field>
                    <flux:label>{{ __('Search User') }}</flux:label>
                    <flux:input
                        wire:model.live.debounce.300ms="searchUser"
                        placeholder="{{ __('Search by name or email...') }}"
                        icon="magnifying-glass"
                    />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Select User') }}</flux:label>
                    <flux:select wire:model="selectedUserId">
                        <flux:select.option value="">{{ __('Select a user') }}</flux:select.option>
                        @foreach($this->getAvailableUsers() as $user)
                        <flux:select.option value="{{ $user->id }}">{{ $user->name }} ({{ $user->email }})</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="selectedUserId" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Role') }}</flux:label>
                    <flux:select wire:model="selectedRole">
                        @foreach(DepartmentRole::cases() as $role)
                        <flux:select.option value="{{ $role->value }}">{{ $role->label() }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="selectedRole" />
                </flux:field>

                <div class="flex justify-end gap-2 pt-4">
                    <flux:button variant="ghost" wire:click="$set('showAddMember', false)">{{ __('Cancel') }}</flux:button>
                    <flux:button type="submit" variant="primary">{{ __('Add Member') }}</flux:button>
                </div>
            </form>
        </div>
    </flux:modal>

    {{-- Create Template Modal --}}
    <flux:modal wire:model="showCreateTemplate" class="max-w-lg">
        <div class="p-6">
            <flux:heading size="lg" class="mb-4">{{ __('Create Task Template') }}</flux:heading>
            <flux:text class="mb-4 text-sm">{{ __('Create a reusable template to speed up task creation.') }}</flux:text>

            <form wire:submit="createTemplate" class="space-y-4">
                <flux:field>
                    <flux:label>{{ __('Template Name') }}</flux:label>
                    <flux:input wire:model="tplName" placeholder="{{ __('e.g. Weekly KPI Report') }}" />
                    <flux:error name="tplName" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Template Description') }}</flux:label>
                    <flux:textarea wire:model="tplDescription" rows="2" placeholder="{{ __('When should this template be used?') }}" />
                    <flux:error name="tplDescription" />
                </flux:field>

                <flux:separator />

                <flux:heading size="sm" class="mb-2">{{ __('Default Task Values') }}</flux:heading>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <flux:field>
                        <flux:label>{{ __('Task Type') }}</flux:label>
                        <flux:select wire:model="tplTaskType">
                            @foreach(\App\Enums\TaskType::cases() as $type)
                            <flux:select.option value="{{ $type->value }}">{{ $type->label() }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    </flux:field>

                    <flux:field>
                        <flux:label>{{ __('Priority') }}</flux:label>
                        <flux:select wire:model="tplPriority">
                            @foreach(\App\Enums\TaskPriority::cases() as $priority)
                            <flux:select.option value="{{ $priority->value }}">{{ $priority->label() }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    </flux:field>
                </div>

                <flux:field>
                    <flux:label>{{ __('Estimated Hours') }}</flux:label>
                    <flux:input type="number" step="0.5" min="0" wire:model="tplEstimatedHours" placeholder="0" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Default Task Title') }}</flux:label>
                    <flux:input wire:model="tplTitle" placeholder="{{ __('Pre-filled title when using template') }}" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Default Task Description') }}</flux:label>
                    <flux:textarea wire:model="tplTaskDescription" rows="3" placeholder="{{ __('Pre-filled description when using template') }}" />
                </flux:field>

                <div class="flex justify-end gap-2 pt-4">
                    <flux:button variant="ghost" wire:click="$set('showCreateTemplate', false)">{{ __('Cancel') }}</flux:button>
                    <flux:button type="submit" variant="primary">{{ __('Create Template') }}</flux:button>
                </div>
            </form>
        </div>
    </flux:modal>
</div>
