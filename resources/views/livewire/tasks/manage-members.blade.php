<?php

declare(strict_types=1);

use App\Models\Department;
use App\Models\User;
use App\Enums\DepartmentRole;
use Livewire\Volt\Component;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

new class extends Component {
    public ?string $activeTab = null;
    public bool $showAddMember = false;
    public string $addMode = 'existing'; // 'existing' or 'new'

    // Existing user selection
    public string $searchUser = '';
    public ?int $selectedUserId = null;

    // New user registration
    public string $newUserName = '';
    public string $newUserEmail = '';
    public string $newUserPassword = '';

    // Common
    public string $selectedRole = 'member';
    public ?int $selectedDepartmentId = null;

    public function mount(): void
    {
        // Only admin can access this page
        if (! auth()->user()->isAdmin()) {
            abort(403, 'Only administrators can manage department members.');
        }

        // Set first department as active tab
        $firstDept = Department::active()->ordered()->first();
        if ($firstDept) {
            $this->activeTab = $firstDept->slug;
            $this->selectedDepartmentId = $firstDept->id;
        }
    }

    public function getDepartments()
    {
        return Department::active()
            ->ordered()
            ->withCount('users')
            ->with(['users' => function ($query) {
                $query->orderByRaw("CASE WHEN department_users.role = 'department_pic' THEN 0 ELSE 1 END");
            }])
            ->get();
    }

    public function setActiveTab(string $slug): void
    {
        $this->activeTab = $slug;
        $dept = Department::where('slug', $slug)->first();
        if ($dept) {
            $this->selectedDepartmentId = $dept->id;
        }
        $this->resetForm();
    }

    public function openAddMember(): void
    {
        $this->showAddMember = true;
        $this->resetForm();
    }

    public function resetForm(): void
    {
        $this->addMode = 'existing';
        $this->searchUser = '';
        $this->selectedUserId = null;
        $this->newUserName = '';
        $this->newUserEmail = '';
        $this->newUserPassword = '';
        $this->selectedRole = 'member';
    }

    public function getAvailableUsers()
    {
        if (! $this->selectedDepartmentId) {
            return collect();
        }

        $department = Department::find($this->selectedDepartmentId);
        if (! $department) {
            return collect();
        }

        $existingUserIds = $department->users->pluck('id');

        return User::where('status', 'active')
            ->whereNotIn('id', $existingUserIds)
            ->when($this->searchUser, function ($q) {
                $q->where(function ($query) {
                    $query->where('name', 'like', "%{$this->searchUser}%")
                          ->orWhere('email', 'like', "%{$this->searchUser}%");
                });
            })
            ->limit(10)
            ->get();
    }

    public function addMember(): void
    {
        if ($this->addMode === 'existing') {
            $this->addExistingMember();
        } else {
            $this->addNewMember();
        }
    }

    protected function addExistingMember(): void
    {
        $this->validate([
            'selectedUserId' => 'required|exists:users,id',
            'selectedRole' => 'required|in:department_pic,member',
            'selectedDepartmentId' => 'required|exists:departments,id',
        ]);

        $department = Department::findOrFail($this->selectedDepartmentId);

        // Check if user is already in department
        if ($department->users()->where('user_id', $this->selectedUserId)->exists()) {
            session()->flash('error', __('User is already a member of this department.'));
            return;
        }

        $department->users()->attach($this->selectedUserId, [
            'role' => $this->selectedRole,
            'assigned_by' => auth()->id(),
        ]);

        $this->showAddMember = false;
        $this->resetForm();
        session()->flash('success', __('Member added successfully.'));
    }

    protected function addNewMember(): void
    {
        $this->validate([
            'newUserName' => 'required|string|max:255',
            'newUserEmail' => 'required|email|unique:users,email',
            'newUserPassword' => ['required', Password::defaults()],
            'selectedRole' => 'required|in:department_pic,member',
            'selectedDepartmentId' => 'required|exists:departments,id',
        ]);

        $department = Department::findOrFail($this->selectedDepartmentId);

        // Determine user role based on department role selection
        // pic_department role for PICs, member_department role for members
        $userRole = $this->selectedRole === 'department_pic' ? 'pic_department' : 'member_department';

        // Create new user with active status so they can login immediately
        $user = User::create([
            'name' => $this->newUserName,
            'email' => $this->newUserEmail,
            'password' => Hash::make($this->newUserPassword),
            'role' => $userRole, // Department staff role based on selection
            'status' => 'active',
            'email_verified_at' => now(), // Mark as verified so they can login
        ]);

        // Add to department
        $department->users()->attach($user->id, [
            'role' => $this->selectedRole,
            'assigned_by' => auth()->id(),
        ]);

        $this->showAddMember = false;
        $this->resetForm();
        session()->flash('success', __('New user created and added to department. They can login immediately with the provided credentials.'));
    }

    public function removeMember(int $departmentId, int $userId): void
    {
        $department = Department::findOrFail($departmentId);

        // Prevent removing the only PIC
        $pics = $department->pics;
        if ($pics->count() === 1 && $pics->first()->id === $userId) {
            session()->flash('error', __('Cannot remove the only PIC from the department.'));
            return;
        }

        $department->users()->detach($userId);
        session()->flash('success', __('Member removed from department.'));
    }

    public function changeRole(int $departmentId, int $userId, string $newRole): void
    {
        $department = Department::findOrFail($departmentId);
        $user = User::findOrFail($userId);

        // Prevent changing role if this would leave no PICs
        if ($newRole === 'member') {
            $pics = $department->pics;
            if ($pics->count() === 1 && $pics->first()->id === $userId) {
                session()->flash('error', __('Cannot change role. At least one PIC is required.'));
                return;
            }
        }

        $department->users()->updateExistingPivot($userId, [
            'role' => $newRole,
        ]);

        // Update user's account role if they are department staff
        // (only update if their current role is pic_department or member_department)
        if ($user->isDepartmentStaff()) {
            $newUserRole = $newRole === 'department_pic' ? 'pic_department' : 'member_department';
            $user->update(['role' => $newUserRole]);
        }

        session()->flash('success', __('Role updated successfully.'));
    }
}; ?>

<div>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <flux:heading size="xl">{{ __('Manage Department Members') }}</flux:heading>
                <flux:text class="mt-2">{{ __('Add or remove PICs and members for each department') }}</flux:text>
            </div>
            <flux:button variant="ghost" :href="route('tasks.dashboard')">
                <flux:icon name="arrow-left" class="w-4 h-4 mr-1" />
                {{ __('Back to Dashboard') }}
            </flux:button>
        </div>
    </x-slot>

    @php
        $departments = $this->getDepartments();
        $activeDepartment = $departments->firstWhere('slug', $activeTab);
    @endphp

    {{-- Flash Messages --}}
    @if(session('success'))
    <div class="max-w-5xl mx-auto mb-4">
        <flux:callout type="success">
            <flux:callout.text>{{ session('success') }}</flux:callout.text>
        </flux:callout>
    </div>
    @endif

    @if(session('error'))
    <div class="max-w-5xl mx-auto mb-4">
        <flux:callout type="error">
            <flux:callout.text>{{ session('error') }}</flux:callout.text>
        </flux:callout>
    </div>
    @endif

    <div class="max-w-5xl mx-auto">
        {{-- Department Tabs --}}
        <div class="bg-white dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700">
            <div class="border-b border-zinc-200 dark:border-zinc-700">
                <nav class="flex -mb-px overflow-x-auto" aria-label="Tabs">
                    @foreach($departments as $department)
                    <button
                        wire:click="setActiveTab('{{ $department->slug }}')"
                        class="relative min-w-0 flex-1 overflow-hidden py-4 px-4 text-center text-sm font-medium border-b-2 transition-colors whitespace-nowrap
                            {{ $activeTab === $department->slug
                                ? 'border-violet-500 text-violet-600 dark:text-violet-400'
                                : 'border-transparent text-zinc-500 hover:text-zinc-700 hover:border-zinc-300 dark:text-zinc-400 dark:hover:text-zinc-300' }}"
                    >
                        <div class="flex items-center justify-center gap-2">
                            <div class="w-3 h-3 rounded-full flex-shrink-0" style="background-color: {{ $department->color }}"></div>
                            <span class="truncate">{{ $department->name }}</span>
                            <flux:badge size="sm" class="flex-shrink-0">{{ $department->users_count }}</flux:badge>
                        </div>
                    </button>
                    @endforeach
                </nav>
            </div>

            {{-- Active Department Content --}}
            @if($activeDepartment)
            <div class="p-6">
                {{-- Department Header --}}
                <div class="flex items-center justify-between mb-6">
                    <div class="flex items-center gap-3">
                        <div class="w-6 h-6 rounded-full" style="background-color: {{ $activeDepartment->color }}"></div>
                        <flux:heading size="lg">{{ $activeDepartment->name }}</flux:heading>
                    </div>
                    <flux:button variant="primary" size="sm" wire:click="openAddMember">
                        <flux:icon name="user-plus" class="w-4 h-4 mr-1" />
                        {{ __('Add Member') }}
                    </flux:button>
                </div>

                {{-- Members List --}}
                @if($activeDepartment->users->count() > 0)
                <div class="space-y-3">
                    @foreach($activeDepartment->users as $member)
                    <div class="flex items-center justify-between p-4 bg-zinc-50 dark:bg-zinc-900 rounded-lg" wire:key="member-{{ $activeDepartment->id }}-{{ $member->id }}">
                        <div class="flex items-center gap-3">
                            <flux:avatar :name="$member->name" />
                            <div>
                                <p class="font-medium text-zinc-900 dark:text-zinc-100">{{ $member->name }}</p>
                                <p class="text-sm text-zinc-500">{{ $member->email }}</p>
                            </div>
                        </div>

                        <div class="flex items-center gap-3">
                            <flux:select
                                wire:change="changeRole({{ $activeDepartment->id }}, {{ $member->id }}, $event.target.value)"
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
                                wire:click="removeMember({{ $activeDepartment->id }}, {{ $member->id }})"
                                wire:confirm="{{ __('Are you sure you want to remove this member from the department?') }}"
                            >
                                <flux:icon name="trash" class="w-4 h-4 text-red-500" />
                            </flux:button>
                        </div>
                    </div>
                    @endforeach
                </div>
                @else
                <div class="text-center py-12">
                    <flux:icon name="users" class="w-12 h-12 mx-auto text-zinc-400" />
                    <flux:heading size="md" class="mt-4">{{ __('No Members') }}</flux:heading>
                    <flux:text class="mt-2">{{ __('This department has no members yet.') }}</flux:text>
                    <div class="mt-4">
                        <flux:button variant="primary" wire:click="openAddMember">
                            <flux:icon name="user-plus" class="w-4 h-4 mr-1" />
                            {{ __('Add First Member') }}
                        </flux:button>
                    </div>
                </div>
                @endif
            </div>
            @else
            <div class="p-8 text-center">
                <flux:icon name="building-office" class="w-12 h-12 mx-auto text-zinc-400" />
                <flux:heading size="lg" class="mt-4">{{ __('No Departments') }}</flux:heading>
                <flux:text class="mt-2">{{ __('No departments have been created yet.') }}</flux:text>
            </div>
            @endif
        </div>

        {{-- Role Legend --}}
        <div class="mt-6 bg-zinc-50 dark:bg-zinc-900 rounded-lg border border-zinc-200 dark:border-zinc-700 p-4">
            <flux:heading size="sm" class="mb-3">{{ __('Role Descriptions') }}</flux:heading>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                @foreach(DepartmentRole::cases() as $role)
                <div class="flex items-start gap-3">
                    <flux:badge size="sm" :color="$role === DepartmentRole::DEPARTMENT_PIC ? 'violet' : 'zinc'">
                        {{ $role->label() }}
                    </flux:badge>
                    <p class="text-sm text-zinc-600 dark:text-zinc-400">{{ $role->description() }}</p>
                </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- Add Member Modal --}}
    <flux:modal wire:model="showAddMember" class="max-w-lg">
        <div class="p-6">
            <flux:heading size="lg" class="mb-4">{{ __('Add Department Member') }}</flux:heading>

            @if($activeDepartment)
            <div class="mb-4 flex items-center gap-2 text-sm text-zinc-600 dark:text-zinc-400">
                <div class="w-3 h-3 rounded-full" style="background-color: {{ $activeDepartment->color }}"></div>
                <span>{{ __('Adding to') }}: <strong>{{ $activeDepartment->name }}</strong></span>
            </div>
            @endif

            <form wire:submit="addMember" class="space-y-4">
                {{-- Mode Toggle --}}
                <div class="flex rounded-lg border border-zinc-200 dark:border-zinc-700 overflow-hidden">
                    <button
                        type="button"
                        wire:click="$set('addMode', 'existing')"
                        class="flex-1 px-4 py-2 text-sm font-medium transition-colors
                            {{ $addMode === 'existing'
                                ? 'bg-violet-500 text-white'
                                : 'bg-white dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300 hover:bg-zinc-50 dark:hover:bg-zinc-700' }}"
                    >
                        {{ __('Existing User') }}
                    </button>
                    <button
                        type="button"
                        wire:click="$set('addMode', 'new')"
                        class="flex-1 px-4 py-2 text-sm font-medium transition-colors border-l border-zinc-200 dark:border-zinc-700
                            {{ $addMode === 'new'
                                ? 'bg-violet-500 text-white'
                                : 'bg-white dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300 hover:bg-zinc-50 dark:hover:bg-zinc-700' }}"
                    >
                        {{ __('Register New User') }}
                    </button>
                </div>

                @if($addMode === 'existing')
                    {{-- Existing User Selection --}}
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
                @else
                    {{-- New User Registration --}}
                    <flux:field>
                        <flux:label>{{ __('Full Name') }}</flux:label>
                        <flux:input
                            wire:model="newUserName"
                            placeholder="{{ __('Enter full name...') }}"
                            icon="user"
                        />
                        <flux:error name="newUserName" />
                    </flux:field>

                    <flux:field>
                        <flux:label>{{ __('Email Address') }}</flux:label>
                        <flux:input
                            type="email"
                            wire:model="newUserEmail"
                            placeholder="{{ __('Enter email address...') }}"
                            icon="envelope"
                        />
                        <flux:error name="newUserEmail" />
                    </flux:field>

                    <flux:field>
                        <flux:label>{{ __('Password') }}</flux:label>
                        <div x-data="{ showPassword: false }" class="relative">
                            <flux:input
                                x-bind:type="showPassword ? 'text' : 'password'"
                                wire:model="newUserPassword"
                                placeholder="{{ __('Enter password...') }}"
                                icon="lock-closed"
                            />
                            <button
                                type="button"
                                x-on:click="showPassword = !showPassword"
                                class="absolute inset-y-0 right-0 flex items-center pr-3 text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-300"
                            >
                                <flux:icon x-show="!showPassword" name="eye" class="w-5 h-5" />
                                <flux:icon x-show="showPassword" name="eye-slash" class="w-5 h-5" x-cloak />
                            </button>
                        </div>
                        <flux:description>{{ __('User will use this password to login') }}</flux:description>
                        <flux:error name="newUserPassword" />
                    </flux:field>

                    <flux:callout type="info" class="text-sm">
                        <flux:callout.text>{{ __('The new user will be able to login immediately with the provided credentials.') }}</flux:callout.text>
                    </flux:callout>
                @endif

                {{-- Role Selection (common for both modes) --}}
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
                    <flux:button type="submit" variant="primary">
                        {{ $addMode === 'new' ? __('Create & Add') : __('Add Member') }}
                    </flux:button>
                </div>
            </form>
        </div>
    </flux:modal>
</div>
