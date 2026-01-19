<?php

use App\Models\User;
use App\Services\ImpersonationService;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;
    
    public $search = '';
    public $roleFilter = '';
    public $statusFilter = '';
    public $perPage = 15;
    public $selected = [];
    public $selectAll = false;
    
    public function updatingSearch()
    {
        $this->resetPage();
    }
    
    public function updatingRoleFilter()
    {
        $this->resetPage();
    }
    
    public function updatingStatusFilter()
    {
        $this->resetPage();
    }
    
    public function updatedSelectAll()
    {
        if ($this->selectAll) {
            $this->selected = $this->users->pluck('id')->toArray();
        } else {
            $this->selected = [];
        }
    }
    
    public function clearFilters()
    {
        $this->search = '';
        $this->roleFilter = '';
        $this->statusFilter = '';
        $this->selected = [];
        $this->selectAll = false;
        $this->resetPage();
    }
    
    public function bulkActivate()
    {
        if (count($this->selected) > 0) {
            User::whereIn('id', $this->selected)->update(['status' => 'active']);
            $this->selected = [];
            $this->selectAll = false;
            session()->flash('success', 'Selected users have been activated.');
        }
    }
    
    public function bulkDeactivate()
    {
        if (count($this->selected) > 0) {
            User::whereIn('id', $this->selected)->update(['status' => 'inactive']);
            $this->selected = [];
            $this->selectAll = false;
            session()->flash('success', 'Selected users have been deactivated.');
        }
    }
    
    public function bulkSuspend()
    {
        if (count($this->selected) > 0) {
            User::whereIn('id', $this->selected)->update(['status' => 'suspended']);
            $this->selected = [];
            $this->selectAll = false;
            session()->flash('warning', 'Selected users have been suspended.');
        }
    }
    
    public function deleteUser($userId)
    {
        $user = User::find($userId);
        if ($user && $user->id !== auth()->id()) {
            $user->delete();
            session()->flash('success', 'User deleted successfully.');
        }
    }

    public function impersonateUser($userId)
    {
        $user = User::find($userId);
        if (! $user) {
            session()->flash('error', 'User not found.');

            return;
        }

        $impersonationService = app(ImpersonationService::class);
        $admin = auth()->user();

        if (! $impersonationService->canBeImpersonated($user, $admin)) {
            session()->flash('error', 'You cannot impersonate this user.');

            return;
        }

        $impersonationService->start($admin, $user);
        $dashboardRoute = $impersonationService->getDashboardRoute($user);

        return $this->redirect(route($dashboardRoute), navigate: true);
    }

    public function getUsersProperty()
    {
        return User::query()
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('name', 'like', '%' . $this->search . '%')
                      ->orWhere('email', 'like', '%' . $this->search . '%');
                });
            })
            ->when($this->roleFilter, function ($query) {
                $query->where('role', $this->roleFilter);
            })
            ->when($this->statusFilter, function ($query) {
                $query->where('status', $this->statusFilter);
            })
            ->orderBy('created_at', 'desc')
            ->paginate($this->perPage);
    }
    
    public function getTotalUsersProperty()
    {
        return User::count();
    }
    
    public function getActiveUsersProperty()
    {
        return User::where('status', 'active')->count();
    }
    
    public function getAdminsCountProperty()
    {
        return User::where('role', 'admin')->count();
    }
    
    public function getTeachersCountProperty()
    {
        return User::where('role', 'teacher')->count();
    }
    
    public function getStudentsCountProperty()
    {
        return User::where('role', 'student')->count();
    }

    public function getLiveHostsCountProperty()
    {
        return User::where('role', 'live_host')->count();
    }

    public function getAdminLivehostsCountProperty()
    {
        return User::where('role', 'admin_livehost')->count();
    }
};

?>

<div>
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading size="xl">Users</flux:heading>
            <flux:text class="mt-2">Manage all users in your system</flux:text>
        </div>
        <flux:button variant="primary" href="{{ route('users.create') }}" icon="user-plus">
            Add New User
        </flux:button>
    </div>

    @if (session()->has('success'))
        <div class="mb-6 p-4 bg-emerald-50 border border-emerald-200 rounded-lg">
            <div class="flex items-center">
                <flux:icon.check-circle class="w-5 h-5 text-emerald-600 mr-3" />
                <div class="text-emerald-800">
                    {{ session('success') }}
                </div>
            </div>
        </div>
    @endif

    @if (session()->has('warning'))
        <div class="mb-6 p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
            <div class="flex items-center">
                <flux:icon.exclamation-triangle class="w-5 h-5 text-yellow-600 mr-3" />
                <div class="text-yellow-800">
                    {{ session('warning') }}
                </div>
            </div>
        </div>
    @endif

    <div class="mt-6 space-y-6">
        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 xl:grid-cols-7 gap-4">
            <flux:card class="p-6">
                <div class="flex items-center">
                    <div class="rounded-md bg-blue-50 dark:bg-blue-900/30 p-3">
                        <flux:icon.users class="h-6 w-6 text-blue-600 dark:text-blue-400" />
                    </div>
                    <div class="ml-4">
                        <p class="text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ $this->totalUsers }}</p>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Total Users</p>
                    </div>
                </div>
            </flux:card>
            
            <flux:card class="p-6">
                <div class="flex items-center">
                    <div class="rounded-md bg-green-50 dark:bg-green-900/30 p-3">
                        <flux:icon.check-circle class="h-6 w-6 text-green-600 dark:text-green-400" />
                    </div>
                    <div class="ml-4">
                        <p class="text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ $this->activeUsers }}</p>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Active Users</p>
                    </div>
                </div>
            </flux:card>
            
            <flux:card class="p-6">
                <div class="flex items-center">
                    <div class="rounded-md bg-purple-50 dark:bg-purple-900/30 p-3">
                        <flux:icon.shield-check class="h-6 w-6 text-purple-600 dark:text-purple-400" />
                    </div>
                    <div class="ml-4">
                        <p class="text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ $this->adminsCount }}</p>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Admins</p>
                    </div>
                </div>
            </flux:card>
            
            <flux:card class="p-6">
                <div class="flex items-center">
                    <div class="rounded-md bg-orange-50 dark:bg-orange-900/30 p-3">
                        <flux:icon.user-group class="h-6 w-6 text-orange-600 dark:text-orange-400" />
                    </div>
                    <div class="ml-4">
                        <p class="text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ $this->teachersCount }}</p>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Teachers</p>
                    </div>
                </div>
            </flux:card>
            
            <flux:card class="p-6">
                <div class="flex items-center">
                    <div class="rounded-md bg-indigo-50 dark:bg-indigo-900/30 p-3">
                        <flux:icon.academic-cap class="h-6 w-6 text-indigo-600 dark:text-indigo-400" />
                    </div>
                    <div class="ml-4">
                        <p class="text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ $this->studentsCount }}</p>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Students</p>
                    </div>
                </div>
            </flux:card>

            <flux:card class="p-6">
                <div class="flex items-center">
                    <div class="rounded-md bg-pink-50 dark:bg-pink-900/30 p-3">
                        <flux:icon.video-camera class="h-6 w-6 text-pink-600 dark:text-pink-400" />
                    </div>
                    <div class="ml-4">
                        <p class="text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ $this->liveHostsCount }}</p>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Live Hosts</p>
                    </div>
                </div>
            </flux:card>

            <flux:card class="p-6">
                <div class="flex items-center">
                    <div class="rounded-md bg-teal-50 dark:bg-teal-900/30 p-3">
                        <flux:icon.shield-check class="h-6 w-6 text-teal-600 dark:text-teal-400" />
                    </div>
                    <div class="ml-4">
                        <p class="text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ $this->adminLivehostsCount }}</p>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Admin Livehosts</p>
                    </div>
                </div>
            </flux:card>
        </div>

        <!-- Filters -->
        <flux:card>
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <flux:input
                            wire:model.live.debounce.300ms="search"
                            placeholder="Search by name or email..."
                            icon="magnifying-glass"
                        />
                    </div>
                    
                    <div>
                        <flux:select wire:model.live="roleFilter" placeholder="All Roles">
                            <option value="">All Roles</option>
                            <option value="admin">Admin</option>
                            <option value="teacher">Teacher</option>
                            <option value="student">Student</option>
                            <option value="live_host">Live Host</option>
                            <option value="admin_livehost">Admin Livehost</option>
                        </flux:select>
                    </div>
                    
                    <div>
                        <flux:select wire:model.live="statusFilter" placeholder="All Statuses">
                            <option value="">All Statuses</option>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                            <option value="suspended">Suspended</option>
                        </flux:select>
                    </div>
                    
                    <div class="flex gap-2">
                        <flux:button variant="ghost" wire:click="clearFilters">
                            Clear Filters
                        </flux:button>
                    </div>
                </div>
                
                @if (count($this->selected) > 0)
                    <div class="mt-4 p-4 bg-blue-50 rounded-lg border border-blue-200">
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-medium text-blue-900">
                                {{ count($this->selected) }} user(s) selected
                            </span>
                            <div class="flex gap-2">
                                <flux:button size="sm" variant="ghost" wire:click="bulkActivate">
                                    Activate
                                </flux:button>
                                <flux:button size="sm" variant="ghost" wire:click="bulkDeactivate">
                                    Deactivate
                                </flux:button>
                                <flux:button size="sm" variant="ghost" wire:click="bulkSuspend">
                                    Suspend
                                </flux:button>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </flux:card>

        <!-- Users Table -->
        <flux:card>
            <div class="overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-zinc-700">
                    <thead class="bg-gray-50 dark:bg-zinc-700/50">
                        <tr>
                            <th class="px-6 py-3 text-left">
                                <flux:checkbox 
                                    wire:model.live="selectAll" 
                                    class="rounded border-gray-300"
                                />
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                User
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                Role
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                Status
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                Last Login
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                Created
                            </th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-zinc-800 divide-y divide-gray-200 dark:divide-zinc-700">
                        @forelse ($this->users as $user)
                            <tr class="hover:bg-gray-50 dark:hover:bg-zinc-700/50">
                                <td class="px-6 py-4">
                                    <flux:checkbox 
                                        wire:model.live="selected" 
                                        value="{{ $user->id }}"
                                        class="rounded border-gray-300"
                                    />
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex items-center">
                                        <div class="h-10 w-10 flex-shrink-0">
                                            <div class="h-10 w-10 rounded-full bg-gray-300 dark:bg-zinc-600 flex items-center justify-center">
                                                <span class="text-sm font-medium text-gray-700 dark:text-gray-200">
                                                    {{ $user->initials() }}
                                                </span>
                                            </div>
                                        </div>
                                        <div class="ml-4">
                                            <div class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $user->name }}</div>
                                            <div class="text-sm text-gray-500 dark:text-gray-400">{{ $user->email }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    @php
                                        $roleColor = match($user->role) {
                                            'admin' => 'purple',
                                            'teacher' => 'orange',
                                            'live_host' => 'pink',
                                            'admin_livehost' => 'teal',
                                            default => 'blue'
                                        };
                                    @endphp
                                    <flux:badge variant="filled" :color="$roleColor">
                                        {{ $user->role_name }}
                                    </flux:badge>
                                </td>
                                <td class="px-6 py-4">
                                    <flux:badge variant="filled" :color="$user->getStatusColor()">
                                        {{ ucfirst($user->status) }}
                                    </flux:badge>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                    {{ $user->last_login_at ? $user->last_login_at->diffForHumans() : 'Never' }}
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                    {{ $user->created_at->format('M d, Y') }}
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <div class="flex justify-end gap-2">
                                        <flux:button 
                                            size="sm" 
                                            variant="ghost"
                                            href="{{ route('users.show', $user) }}"
                                            wire:navigate
                                        >
                                            View
                                        </flux:button>
                                        <flux:button 
                                            size="sm" 
                                            variant="ghost"
                                            href="{{ route('users.edit', $user) }}"
                                            wire:navigate
                                        >
                                            Edit
                                        </flux:button>
                                        @if ($user->id !== auth()->id())
                                            <flux:button
                                                size="sm"
                                                variant="ghost"
                                                wire:click="impersonateUser({{ $user->id }})"
                                                wire:confirm="Are you sure you want to impersonate {{ $user->name }}?"
                                                title="Impersonate User"
                                            >
                                                <flux:icon name="eye" class="w-4 h-4" />
                                            </flux:button>
                                            <flux:button
                                                size="sm"
                                                variant="ghost"
                                                wire:click="deleteUser({{ $user->id }})"
                                                wire:confirm="Are you sure you want to delete this user? This action cannot be undone."
                                            >
                                                Delete
                                            </flux:button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-6 py-12 text-center">
                                    <div class="text-gray-500 dark:text-gray-400">
                                        <flux:icon.users class="mx-auto h-12 w-12 text-gray-400 dark:text-gray-500 mb-4" />
                                        <p class="text-lg font-medium text-gray-900 dark:text-gray-100">No users found</p>
                                        <p class="text-sm">Try adjusting your search criteria</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($this->users->hasPages())
                <div class="px-6 py-3 border-t border-gray-200 dark:border-zinc-700">
                    {{ $this->users->links() }}
                </div>
            @endif
        </flux:card>
    </div>
</div>