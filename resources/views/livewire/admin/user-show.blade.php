<?php

use App\Models\User;
use Livewire\Volt\Component;

new class extends Component {
    public User $user;
    
    public function mount(User $user)
    {
        $this->user = $user;
    }
    
    public function activateUser()
    {
        $this->user->activate();
        session()->flash('success', 'User activated successfully.');
    }
    
    public function deactivateUser()
    {
        if ($this->user->id === auth()->id()) {
            session()->flash('error', 'You cannot deactivate your own account.');
            return;
        }
        
        $this->user->deactivate();
        session()->flash('success', 'User deactivated successfully.');
    }
    
    public function suspendUser()
    {
        if ($this->user->id === auth()->id()) {
            session()->flash('error', 'You cannot suspend your own account.');
            return;
        }
        
        $this->user->suspend();
        session()->flash('warning', 'User suspended successfully.');
    }
    
    public function deleteUser()
    {
        if ($this->user->id === auth()->id()) {
            session()->flash('error', 'You cannot delete your own account.');
            return;
        }
        
        $this->user->delete();
        session()->flash('success', 'User deleted successfully.');
        
        return $this->redirect(route('users.index'), navigate: true);
    }
};

?>

<div>
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading size="xl">User Details</flux:heading>
            <flux:text class="mt-2">View and manage user information</flux:text>
        </div>
        <div class="flex gap-2">
            <flux:button variant="ghost" href="{{ route('users.edit', $user) }}" wire:navigate>
                Edit User
            </flux:button>
            <flux:button variant="ghost" href="{{ route('users.index') }}" wire:navigate>
                Back to Users
            </flux:button>
        </div>
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
    
    @if (session()->has('error'))
        <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg">
            <div class="flex items-center">
                <flux:icon.exclamation-circle class="w-5 h-5 text-red-600 mr-3" />
                <div class="text-red-800">
                    {{ session('error') }}
                </div>
            </div>
        </div>
    @endif
    
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- User Information -->
        <div class="lg:col-span-2">
            <flux:card>
                <div class="p-6">
                    <flux:heading size="lg" class="mb-4">User Information</flux:heading>
                    
                    <div class="flex items-start space-x-4 mb-6">
                        <div class="h-20 w-20 rounded-full bg-gray-300 flex items-center justify-center">
                            <span class="text-2xl font-medium text-gray-700">
                                {{ $user->initials() }}
                            </span>
                        </div>
                        <div class="flex-1">
                            <h3 class="text-xl font-semibold text-gray-900">{{ $user->name }}</h3>
                            <p class="text-gray-600">{{ $user->email }}</p>
                            <div class="flex items-center gap-3 mt-2">
                                <flux:badge variant="filled" :color="$user->role === 'admin' ? 'purple' : ($user->role === 'teacher' ? 'orange' : 'blue')">
                                    {{ $user->role_name }}
                                </flux:badge>
                                <flux:badge variant="filled" :color="$user->getStatusColor()">
                                    {{ ucfirst($user->status) }}
                                </flux:badge>
                            </div>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <h4 class="text-sm font-medium text-gray-500 mb-2">Account Details</h4>
                            <div class="space-y-2 text-sm">
                                <div class="flex justify-between">
                                    <span class="text-gray-600">User ID:</span>
                                    <span class="font-medium">{{ $user->id }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Email Verified:</span>
                                    <span class="font-medium">
                                        @if ($user->email_verified_at)
                                            <flux:badge variant="filled" color="green" size="sm">Verified</flux:badge>
                                        @else
                                            <flux:badge variant="filled" color="red" size="sm">Not Verified</flux:badge>
                                        @endif
                                    </span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Created:</span>
                                    <span class="font-medium">{{ $user->created_at->format('M d, Y') }}</span>
                                </div>
                            </div>
                        </div>
                        
                        <div>
                            <h4 class="text-sm font-medium text-gray-500 mb-2">Activity</h4>
                            <div class="space-y-2 text-sm">
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Last Login:</span>
                                    <span class="font-medium">
                                        {{ $user->last_login_at ? $user->last_login_at->diffForHumans() : 'Never' }}
                                    </span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Updated:</span>
                                    <span class="font-medium">{{ $user->updated_at->diffForHumans() }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </flux:card>
            
            <!-- Role-Specific Information -->
            @if ($user->role === 'teacher' && $user->teacher)
                <flux:card class="mt-6">
                    <div class="p-6">
                        <flux:heading size="lg" class="mb-4">Teacher Profile</flux:heading>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <h4 class="text-sm font-medium text-gray-500 mb-2">Teaching Statistics</h4>
                                <div class="space-y-2 text-sm">
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Total Courses:</span>
                                        <span class="font-medium">{{ $user->createdCourses->count() }}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Total Enrollments:</span>
                                        <span class="font-medium">{{ $user->enrolledStudents->count() }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </flux:card>
            @elseif ($user->role === 'student' && $user->student)
                <flux:card class="mt-6">
                    <div class="p-6">
                        <flux:heading size="lg" class="mb-4">Student Profile</flux:heading>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <h4 class="text-sm font-medium text-gray-500 mb-2">Learning Statistics</h4>
                                <div class="space-y-2 text-sm">
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Payment Methods:</span>
                                        <span class="font-medium">{{ $user->paymentMethods->count() }}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Total Orders:</span>
                                        <span class="font-medium">{{ $user->orders->count() }}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Paid Orders:</span>
                                        <span class="font-medium">{{ $user->student ? $user->student->paidOrders->count() : 0 }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </flux:card>
            @endif
        </div>
        
        <!-- Actions Sidebar -->
        <div>
            <flux:card>
                <div class="p-6">
                    <flux:heading size="lg" class="mb-4">Quick Actions</flux:heading>
                    
                    <div class="space-y-3">
                        <flux:button 
                            variant="outline" 
                            class="w-full justify-start"
                            href="{{ route('users.edit', $user) }}"
                            wire:navigate
                        >
                            <div class="flex items-center justify-center">
                                <flux:icon.pencil class="w-4 h-4 mr-2" />
                                Edit User
                            </div>
                        </flux:button>
                        
                        @if ($user->status === 'active')
                            @if ($user->id !== auth()->id())
                                <flux:button 
                                    variant="outline" 
                                    class="w-full justify-start"
                                    wire:click="deactivateUser"
                                >
                                    <div class="flex items-center justify-center">
                                        <flux:icon.pause class="w-4 h-4 mr-2" />
                                        Deactivate User
                                    </div>
                                </flux:button>
                                
                                <flux:button 
                                    variant="outline" 
                                    class="w-full justify-start"
                                    wire:click="suspendUser"
                                    wire:confirm="Are you sure you want to suspend this user?"
                                >
                                    <div class="flex items-center justify-center">
                                        <flux:icon.exclamation-triangle class="w-4 h-4 mr-2" />
                                        Suspend User
                                    </div>
                                </flux:button>
                            @endif
                        @else
                            <flux:button 
                                variant="outline" 
                                class="w-full justify-start"
                                wire:click="activateUser"
                            >
                                <div class="flex items-center justify-center">
                                    <flux:icon.play class="w-4 h-4 mr-2" />
                                    Activate User
                                </div>
                            </flux:button>
                        @endif
                        
                        @if ($user->id !== auth()->id())
                            <flux:button 
                                variant="danger" 
                                class="w-full justify-start"
                                wire:click="deleteUser"
                                wire:confirm="Are you sure you want to delete this user? This action cannot be undone."
                            >
                                <div class="flex items-center justify-center">
                                    <flux:icon.trash class="w-4 h-4 mr-2" />
                                    Delete User
                                </div>
                            </flux:button>
                        @endif
                    </div>
                </div>
            </flux:card>
            
            <!-- Status Information -->
            <flux:card class="mt-6">
                <div class="p-6">
                    <flux:heading size="lg" class="mb-4">Status Information</flux:heading>
                    
                    <div class="space-y-3">
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-600">Current Status</span>
                            <flux:badge variant="filled" :color="$user->getStatusColor()">
                                {{ ucfirst($user->status) }}
                            </flux:badge>
                        </div>
                        
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-600">Role</span>
                            <flux:badge variant="filled" :color="$user->role === 'admin' ? 'purple' : ($user->role === 'teacher' ? 'orange' : 'blue')">
                                {{ $user->role_name }}
                            </flux:badge>
                        </div>
                        
                        @if ($user->email_verified_at)
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-gray-600">Email Verified</span>
                                <flux:icon.check-circle class="w-5 h-5 text-green-500" />
                            </div>
                        @else
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-gray-600">Email Not Verified</span>
                                <flux:icon.x-circle class="w-5 h-5 text-red-500" />
                            </div>
                        @endif
                    </div>
                </div>
            </flux:card>
        </div>
    </div>
</div>