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

    /**
     * A previously soft-deleted user occupying the entered email/phone, if any.
     *
     * @var array{id: int, name: string, email: ?string, phone: ?string}|null
     */
    public ?array $restorableUser = null;

    public function rules()
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', Rule::unique('users', 'email')->whereNull('deleted_at')],
            'phone' => ['nullable', 'string', Rule::unique('users', 'phone')->whereNull('deleted_at'), 'regex:/^[0-9]{10,15}$/'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'role' => ['required', Rule::in(['admin', 'teacher', 'student', 'live_host', 'admin_livehost', 'livehost_assistant', 'class_admin', 'employee'])],
            'status' => ['required', Rule::in(['active', 'inactive', 'suspended'])],
        ];
    }

    public function save()
    {
        $this->normalizeInput();
        $this->validate();

        $trashed = $this->findTrashedMatch();

        if ($trashed) {
            $this->restorableUser = [
                'id' => $trashed->id,
                'name' => $trashed->name,
                'email' => $trashed->email,
                'phone' => $trashed->phone,
            ];

            return null;
        }

        $user = User::create([
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'password' => Hash::make($this->password),
            'role' => $this->role,
            'status' => $this->status,
        ]);

        $this->syncRoleProfile($user);

        session()->flash('success', 'User created successfully.');

        return $this->redirect(route('users.index'), navigate: true);
    }

    public function restoreUser()
    {
        if (! $this->restorableUser) {
            return null;
        }

        $user = User::onlyTrashed()->find($this->restorableUser['id']);

        if (! $user) {
            $this->restorableUser = null;
            session()->flash('error', 'That account could no longer be found.');

            return null;
        }

        $this->normalizeInput();
        $this->validate();

        $user->restore();
        $user->update([
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'password' => Hash::make($this->password),
            'role' => $this->role,
            'status' => $this->status,
        ]);

        $this->syncRoleProfile($user);

        session()->flash('success', 'Previously deleted account restored and updated.');

        return $this->redirect(route('users.index'), navigate: true);
    }

    public function dismissRestore(): void
    {
        $this->restorableUser = null;
    }

    private function normalizeInput(): void
    {
        $this->email = filled($this->email) ? $this->email : null;
        $this->phone = filled($this->phone) ? $this->phone : null;
    }

    private function findTrashedMatch(): ?User
    {
        if (! $this->email && ! $this->phone) {
            return null;
        }

        return User::onlyTrashed()
            ->where(function ($query) {
                $query->whereRaw('1 = 0');

                if ($this->phone) {
                    $query->orWhere('phone', $this->phone);
                }

                if ($this->email) {
                    $query->orWhere('email', $this->email);
                }
            })
            ->first();
    }

    private function syncRoleProfile(User $user): void
    {
        if ($this->role === 'teacher' && ! $user->teacher()->exists()) {
            Teacher::create([
                'user_id' => $user->id,
                'teacher_id' => Teacher::generateTeacherId(),
                'status' => $this->status,
            ]);
        } elseif ($this->role === 'student' && ! $user->student()->exists()) {
            Student::create([
                'user_id' => $user->id,
                'status' => $this->status,
            ]);
        }
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

    @if ($restorableUser)
        <div class="mb-6 p-4 bg-amber-50 border border-amber-200 rounded-lg">
            <div class="flex items-start gap-3">
                <flux:icon.exclamation-triangle class="w-5 h-5 text-amber-600 mt-0.5 shrink-0" />
                <div class="flex-1">
                    <div class="font-medium text-amber-900">
                        This email or phone belonged to a previously deleted account.
                    </div>
                    <div class="text-sm text-amber-800 mt-1">
                        Deleted account: <span class="font-medium">{{ $restorableUser['name'] }}</span>@if ($restorableUser['email']) &middot; {{ $restorableUser['email'] }}@endif @if ($restorableUser['phone']) &middot; {{ $restorableUser['phone'] }}@endif.
                        You can restore it and update it with the details below, or cancel and use a different email/phone.
                    </div>
                    <div class="flex gap-3 mt-3">
                        <flux:button variant="primary" wire:click="restoreUser">
                            Restore &amp; update this account
                        </flux:button>
                        <flux:button variant="ghost" wire:click="dismissRestore">
                            Cancel
                        </flux:button>
                    </div>
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
                                />
                                <flux:description>
                                    Optional. Used for login and communication (10–15 digits).
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
                                    <option value="live_host">Live Host</option>
                                    <option value="admin_livehost">Admin Live Host</option>
                                    <option value="livehost_assistant">Live Host Assistant</option>
                                    <option value="class_admin">Class Admin</option>
                                    <option value="employee">Employee</option>
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