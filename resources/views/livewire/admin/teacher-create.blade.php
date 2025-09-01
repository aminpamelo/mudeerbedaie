<?php

use App\Models\User;
use App\Models\Teacher;
use Livewire\Volt\Component;
use Illuminate\Support\Facades\Hash;

new class extends Component {
    public $name = '';
    public $email = '';
    public $password = '';
    public $password_confirmation = '';
    public $ic_number = '';
    public $phone = '';
    public $status = 'active';
    public $existingUserId = '';
    public $useExistingUser = false;
    public $bank_account_holder = '';
    public $bank_account_number = '';
    public $bank_name = '';

    public function mount(): void
    {
        //
    }

    public function with(): array
    {
        return [
            'users' => User::where('role', 'teacher')
                          ->whereDoesntHave('teacher')
                          ->get(),
        ];
    }

    public function rules(): array
    {
        if ($this->useExistingUser) {
            return [
                'existingUserId' => 'required|exists:users,id',
                'ic_number' => 'nullable|string|max:20',
                'phone' => 'nullable|string|max:20',
                'status' => 'required|in:active,inactive',
                'bank_account_holder' => 'nullable|string|max:255',
                'bank_account_number' => 'nullable|string|max:50',
                'bank_name' => 'nullable|string|max:100',
            ];
        }

        return [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'ic_number' => 'nullable|string|max:20',
            'phone' => 'nullable|string|max:20',
            'status' => 'required|in:active,inactive',
            'bank_account_holder' => 'nullable|string|max:255',
            'bank_account_number' => 'nullable|string|max:50',
            'bank_name' => 'nullable|string|max:100',
        ];
    }

    public function save(): void
    {
        $this->validate();

        if ($this->useExistingUser) {
            $user = User::findOrFail($this->existingUserId);
        } else {
            $user = User::create([
                'name' => $this->name,
                'email' => $this->email,
                'password' => Hash::make($this->password),
                'role' => 'teacher',
            ]);
        }

        $teacherId = $this->generateTeacherId();

        Teacher::create([
            'user_id' => $user->id,
            'teacher_id' => $teacherId,
            'ic_number' => $this->ic_number ?: null,
            'phone' => $this->phone ?: null,
            'status' => $this->status,
            'joined_at' => now(),
            'bank_account_holder' => $this->bank_account_holder ?: null,
            'bank_account_number' => $this->bank_account_number ?: null,
            'bank_name' => $this->bank_name ?: null,
        ]);

        session()->flash('success', 'Teacher created successfully.');
        $this->redirect(route('teachers.index'));
    }

    private function generateTeacherId(): string
    {
        $lastTeacher = Teacher::orderBy('id', 'desc')->first();
        $number = $lastTeacher ? ((int) substr($lastTeacher->teacher_id, 3)) + 1 : 1;
        
        return 'TID' . str_pad($number, 3, '0', STR_PAD_LEFT);
    }

    public function updatedUseExistingUser(): void
    {
        if ($this->useExistingUser) {
            $this->reset(['name', 'email', 'password', 'password_confirmation']);
        } else {
            $this->reset(['existingUserId']);
        }
    }

    public function getBanks(): array
    {
        return Teacher::getMalaysianBanks();
    }
};
?>

<div class="space-y-6">
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading size="xl">Add Teacher</flux:heading>
            <flux:text class="mt-2">Create a new teacher account</flux:text>
        </div>
        <flux:button variant="ghost" href="{{ route('teachers.index') }}">
            Back to Teachers
        </flux:button>
    </div>

    <div class="bg-white shadow rounded-lg">
        <form wire:submit="save" class="p-6 space-y-6">
            
            <!-- User Selection Toggle -->
            <div class="space-y-4">
                <flux:checkbox 
                    wire:model.live="useExistingUser" 
                    label="Use existing user account"
                    description="Select this if the teacher already has a user account with teacher role"
                />
            </div>

            @if($useExistingUser)
                <!-- Existing User Selection -->
                <div class="grid grid-cols-1 gap-6">
                    <flux:field>
                        <flux:label>Select User</flux:label>
                        <flux:select wire:model="existingUserId" placeholder="Choose a user">
                            @foreach($users as $user)
                                <flux:select.option value="{{ $user->id }}">{{ $user->name }} ({{ $user->email }})</flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:error name="existingUserId" />
                    </flux:field>
                </div>
            @else
                <!-- New User Creation -->
                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                    <flux:field>
                        <flux:label>Full Name</flux:label>
                        <flux:input wire:model="name" type="text" />
                        <flux:error name="name" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Email Address</flux:label>
                        <flux:input wire:model="email" type="email" />
                        <flux:error name="email" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Password</flux:label>
                        <flux:input wire:model="password" type="password" />
                        <flux:error name="password" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Confirm Password</flux:label>
                        <flux:input wire:model="password_confirmation" type="password" />
                    </flux:field>
                </div>
            @endif

            <!-- Teacher Details -->
            <div class="border-t pt-6">
                <flux:heading size="lg" class="mb-4">Teacher Details</flux:heading>
                
                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                    <flux:field>
                        <flux:label>IC Number</flux:label>
                        <flux:input wire:model="ic_number" type="text" placeholder="e.g., 901234-12-3456" />
                        <flux:error name="ic_number" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Phone Number</flux:label>
                        <flux:input wire:model="phone" type="text" placeholder="e.g., +60123456789" />
                        <flux:error name="phone" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Status</flux:label>
                        <flux:select wire:model="status">
                            <flux:select.option value="active">Active</flux:select.option>
                            <flux:select.option value="inactive">Inactive</flux:select.option>
                        </flux:select>
                        <flux:error name="status" />
                    </flux:field>
                </div>
            </div>

            <!-- Bank Information -->
            <div class="border-t pt-6">
                <flux:heading size="lg" class="mb-4">Banking Information</flux:heading>
                <flux:text class="text-sm text-gray-600 mb-4">Optional: Add bank account details for payment processing</flux:text>
                
                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                    <flux:field>
                        <flux:label>Account Holder Name</flux:label>
                        <flux:input wire:model="bank_account_holder" type="text" placeholder="Full name as per bank account" />
                        <flux:error name="bank_account_holder" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Bank Name</flux:label>
                        <flux:select wire:model="bank_name" placeholder="Select Bank">
                            @foreach($this->getBanks() as $value => $label)
                                <flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:error name="bank_name" />
                    </flux:field>

                    <div class="sm:col-span-2">
                        <flux:field>
                            <flux:label>Account Number</flux:label>
                            <flux:input wire:model="bank_account_number" type="text" placeholder="Bank account number" />
                            <flux:description>Account number will be encrypted in the database</flux:description>
                            <flux:error name="bank_account_number" />
                        </flux:field>
                    </div>
                </div>
            </div>

            <div class="flex items-center justify-end space-x-3 pt-6 border-t">
                <flux:button variant="ghost" href="{{ route('teachers.index') }}">
                    Cancel
                </flux:button>
                <flux:button type="submit" variant="primary">
                    Create Teacher
                </flux:button>
            </div>
        </form>
    </div>
</div>