<?php

declare(strict_types=1);

use App\Models\Department;
use App\Models\User;
use App\Enums\DepartmentRole;
use Livewire\Volt\Component;

new class extends Component {
    public Department $department;

    public bool $showAddMember = false;
    public string $searchUser = '';
    public ?int $selectedUserId = null;
    public string $selectedRole = 'member';

    public function mount(Department $department): void
    {
        $this->department = $department->load('users');
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
        <div class="bg-white dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700">
            <div class="p-4 border-b border-zinc-200 dark:border-zinc-700 flex items-center justify-between">
                <flux:heading size="lg">{{ __('Department Members') }}</flux:heading>
                <flux:button variant="primary" size="sm" wire:click="$set('showAddMember', true)">
                    <flux:icon name="plus" class="w-4 h-4 mr-1" />
                    {{ __('Add Member') }}
                </flux:button>
            </div>

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
</div>
