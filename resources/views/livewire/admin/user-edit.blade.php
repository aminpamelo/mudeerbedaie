<?php

use App\Models\User;
use App\Models\Teacher;
use App\Models\Student;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Volt\Component;

new class extends Component {
    public User $user;
    public $name = '';
    public $email = '';
    public $phone = '';
    public $password = '';
    public $password_confirmation = '';
    public $role = '';
    public $status = '';
    public $change_password = false;

    public function mount(User $user)
    {
        $this->user = $user;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->phone = $user->phone;
        $this->role = $user->role;
        $this->status = $user->status;
    }

    public function rules()
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', Rule::unique('users')->ignore($this->user->id)],
            'phone' => ['required', 'string', Rule::unique('users')->ignore($this->user->id), 'regex:/^[0-9]{10,15}$/'],
            'password' => $this->change_password ? ['required', 'string', 'min:8', 'confirmed'] : [],
            'role' => ['required', Rule::in(['admin', 'teacher', 'student'])],
            'status' => ['required', Rule::in(['active', 'inactive', 'suspended'])],
        ];
    }
    
    public function updatedChangePassword()
    {
        if (!$this->change_password) {
            $this->password = '';
            $this->password_confirmation = '';
        }
    }
    
    public function save()
    {
        // Prevent self-role change and self-status change for certain actions
        if ($this->user->id === auth()->id()) {
            if ($this->role !== $this->user->role) {
                session()->flash('error', 'You cannot change your own role.');
                return;
            }
            if ($this->status !== 'active' && $this->user->status === 'active') {
                session()->flash('error', 'You cannot deactivate or suspend your own account.');
                return;
            }
        }
        
        $this->validate();
        
        $userData = [
            'name' => $this->name,
            'email' => $this->email ?: null,
            'phone' => $this->phone,
            'role' => $this->role,
            'status' => $this->status,
        ];
        
        if ($this->change_password && !empty($this->password)) {
            $userData['password'] = Hash::make($this->password);
        }
        
        $oldRole = $this->user->role;
        
        $this->user->update($userData);
        
        // Handle role change
        if ($oldRole !== $this->role) {
            $this->handleRoleChange($oldRole, $this->role);
        }
        
        session()->flash('success', 'User updated successfully.');
        
        return $this->redirect(route('users.index'), navigate: true);
    }
    
    private function handleRoleChange($oldRole, $newRole)
    {
        // Remove old role-specific profile
        if ($oldRole === 'teacher') {
            $this->user->teacher?->delete();
        } elseif ($oldRole === 'student') {
            $this->user->student?->delete();
        }
        
        // Create new role-specific profile
        if ($newRole === 'teacher') {
            Teacher::create([
                'user_id' => $this->user->id,
                'status' => $this->status,
            ]);
        } elseif ($newRole === 'student') {
            Student::create([
                'user_id' => $this->user->id,
                'status' => $this->status,
            ]);
        }
    }
    
    public function resetPassword()
    {
        // Generate a new random password
        $newPassword = Str::random(12);
        
        $this->user->update([
            'password' => Hash::make($newPassword)
        ]);
        
        // TODO: Send password reset email with new password
        
        session()->flash('success',"Password reset successfully. New password: {$newPassword}");
    }
    
    public function cancel()
    {
        return $this->redirect(route('users.index'), navigate: true);
    }
};

?>

<div>
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading size="xl">Edit User</flux:heading>
            <flux:text class="mt-2">Update user information and settings</flux:text>
        </div>
        <div class="flex gap-2">
            <flux:button variant="ghost" wire:click="resetPassword" wire:confirm="Generate a new random password for this user?">
                Reset Password
            </flux:button>
            <flux:button variant="ghost" href="{{ route('users.index') }}" wire:navigate>
                Cancel
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
    
    <flux:card>
        <form wire:submit.prevent="save" class="p-6">
            <div class="grid grid-cols-1 gap-6">
                <!-- Basic Information -->
                <div>
                    <flux:heading size="lg" class="mb-4">Basic Information</flux:heading>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <flux:field>
                                <flux:label>Full Name</flux:label>
                                <flux:input
                                    wire:model="name"
                                    placeholder="Enter full name"
                                    required
                                />
                                <flux:error name="name" />
                            </flux:field>
                        </div>

                        <div>
                            <flux:field>
                                <flux:label>Phone Number</flux:label>
                                <flux:input
                                    type="tel"
                                    wire:model="phone"
                                    placeholder="60165756060"
                                    required
                                />
                                <flux:description>
                                    Phone number is required for login and communication (10-15 digits).
                                </flux:description>
                                <flux:error name="phone" />
                            </flux:field>
                        </div>

                        <div>
                            <flux:field>
                                <flux:label>Email Address</flux:label>
                                <flux:input
                                    type="email"
                                    wire:model="email"
                                    placeholder="Enter email address (optional)"
                                />
                                <flux:description>
                                    Email is optional but recommended for notifications.
                                </flux:description>
                                <flux:error name="email" />
                            </flux:field>
                        </div>
                    </div>
                </div>
                
                <!-- Account Settings -->
                <div>
                    <flux:heading size="lg" class="mb-4">Account Settings</flux:heading>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <flux:field>
                                <flux:label>Role</flux:label>
                                <flux:select wire:model="role" required>
                                    <option value="student">Student</option>
                                    <option value="teacher">Teacher</option>
                                    <option value="admin">Admin</option>
                                </flux:select>
                                @if ($user->id === auth()->id())
                                    <flux:description class="text-orange-600">
                                        You cannot change your own role.
                                    </flux:description>
                                @endif
                                <flux:error name="role" />
                            </flux:field>
                        </div>
                        
                        <div>
                            <flux:field>
                                <flux:label>Status</flux:label>
                                <flux:select wire:model="status" required>
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                    <option value="suspended">Suspended</option>
                                </flux:select>
                                @if ($user->id === auth()->id())
                                    <flux:description class="text-orange-600">
                                        You cannot deactivate or suspend your own account.
                                    </flux:description>
                                @endif
                                <flux:error name="status" />
                            </flux:field>
                        </div>
                    </div>
                </div>
                
                <!-- Password Change -->
                <div>
                    <flux:heading size="lg" class="mb-4">Password Management</flux:heading>
                    
                    <div class="mb-4">
                        <flux:field>
                            <flux:checkbox wire:model.live="change_password" />
                            <flux:label>Change password</flux:label>
                            <flux:description>
                                Check this box to set a new password for this user.
                            </flux:description>
                        </flux:field>
                    </div>
                    
                    @if ($change_password)
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <flux:field>
                                    <flux:label>New Password</flux:label>
                                    <flux:input
                                        type="password"
                                        wire:model="password"
                                        placeholder="Enter new password"
                                    />
                                    <flux:error name="password" />
                                </flux:field>
                            </div>
                            
                            <div>
                                <flux:field>
                                    <flux:label>Confirm Password</flux:label>
                                    <flux:input
                                        type="password"
                                        wire:model="password_confirmation"
                                        placeholder="Confirm new password"
                                    />
                                    <flux:error name="password_confirmation" />
                                </flux:field>
                            </div>
                        </div>
                    @endif
                </div>
                
                <!-- User Information -->
                <div>
                    <flux:heading size="lg" class="mb-4">Account Details</flux:heading>
                    
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
                            <div>
                                <span class="font-medium text-gray-500">User ID:</span>
                                <div>{{ $user->id }}</div>
                            </div>
                            <div>
                                <span class="font-medium text-gray-500">Created:</span>
                                <div>{{ $user->created_at->format('M d, Y g:i A') }}</div>
                            </div>
                            <div>
                                <span class="font-medium text-gray-500">Last Login:</span>
                                <div>{{ $user->last_login_at ? $user->last_login_at->format('M d, Y g:i A') : 'Never' }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="flex justify-end gap-3 mt-8 pt-6 border-t border-gray-200">
                <flux:button variant="ghost" wire:click="cancel">
                    Cancel
                </flux:button>
                <flux:button type="submit" variant="primary">
                    Update User
                </flux:button>
            </div>
        </form>
    </flux:card>
</div>