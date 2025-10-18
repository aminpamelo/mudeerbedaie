<?php

use App\Models\User;
use App\Models\Teacher;
use App\Models\Student;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Livewire\Volt\Component;

new class extends Component {
    public $name = '';
    public $email = '';
    public $phone = '';
    public $password = '';
    public $password_confirmation = '';
    public $role = 'student';
    public $status = 'active';
    public $send_welcome_email = true;

    public function rules()
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'unique:users,email'],
            'phone' => ['required', 'string', 'unique:users,phone', 'regex:/^[0-9]{10,15}$/'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'role' => ['required', Rule::in(['admin', 'teacher', 'student'])],
            'status' => ['required', Rule::in(['active', 'inactive', 'suspended'])],
        ];
    }

    public function save()
    {
        $this->validate();

        $user = User::create([
            'name' => $this->name,
            'email' => $this->email ?: null,
            'phone' => $this->phone,
            'password' => Hash::make($this->password),
            'role' => $this->role,
            'status' => $this->status,
        ]);
        
        // Create role-specific profile if needed
        if ($this->role === 'teacher') {
            Teacher::create([
                'user_id' => $user->id,
                'teacher_id' => Teacher::generateTeacherId(),
                'status' => $this->status,
            ]);
        } elseif ($this->role === 'student') {
            Student::create([
                'user_id' => $user->id,
                'status' => $this->status,
            ]);
        }
        
        // TODO: Send welcome email if requested
        if ($this->send_welcome_email) {
            // This will be implemented later
        }
        
        session()->flash('success', 'User created successfully.');
        
        return $this->redirect(route('users.index'), navigate: true);
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
            <flux:heading size="xl">Create User</flux:heading>
            <flux:text class="mt-2">Add a new user to your system</flux:text>
        </div>
        <flux:button variant="ghost" href="{{ route('users.index') }}" wire:navigate>
            Cancel
        </flux:button>
    </div>
    
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
                                <flux:error name="status" />
                            </flux:field>
                        </div>
                    </div>
                </div>
                
                <!-- Password -->
                <div>
                    <flux:heading size="lg" class="mb-4">Password</flux:heading>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <flux:field>
                                <flux:label>Password</flux:label>
                                <flux:input
                                    type="password"
                                    wire:model="password"
                                    placeholder="Enter password"
                                    required
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
                                    placeholder="Confirm password"
                                    required
                                />
                                <flux:error name="password_confirmation" />
                            </flux:field>
                        </div>
                    </div>
                </div>
                
                <!-- Email Notification -->
                <div>
                    <flux:heading size="lg" class="mb-4">Notifications</flux:heading>
                    
                    <div>
                        <flux:field>
                            <flux:checkbox wire:model="send_welcome_email" />
                            <flux:label>Send welcome email to user</flux:label>
                            <flux:description>
                                The user will receive an email with login instructions and account details.
                            </flux:description>
                        </flux:field>
                    </div>
                </div>
            </div>
            
            <div class="flex justify-end gap-3 mt-8 pt-6 border-t border-gray-200">
                <flux:button variant="ghost" wire:click="cancel">
                    Cancel
                </flux:button>
                <flux:button type="submit" variant="primary">
                    Create User
                </flux:button>
            </div>
        </form>
    </flux:card>
</div>