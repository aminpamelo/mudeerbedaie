<?php

use App\Models\User;
use App\Models\Student;
use Livewire\Volt\Component;

new class extends Component {
    // User information
    public $name = '';
    public $email = '';
    public $password = '';
    public $password_confirmation = '';
    
    // Student profile information
    public $ic_number = '';
    public $phone = '';
    public $address = '';
    public $date_of_birth = '';
    public $gender = '';
    public $nationality = '';
    public $status = 'active';

    public function create(): void
    {
        $this->validate([
            // User validation
            'name' => 'required|string|min:3|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            
            // Student validation
            'ic_number' => 'nullable|string|size:12|regex:/^[0-9]{12}$/|unique:students',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:500',
            'date_of_birth' => 'nullable|date|before:today',
            'gender' => 'nullable|in:male,female,other',
            'nationality' => 'nullable|string|max:100',
            'status' => 'required|in:active,inactive,graduated,suspended',
        ]);

        // Create user first
        $user = User::create([
            'name' => $this->name,
            'email' => $this->email,
            'password' => bcrypt($this->password),
            'role' => 'student',
        ]);

        // Create student profile
        $student = Student::create([
            'user_id' => $user->id,
            'ic_number' => $this->ic_number ?: null,
            'phone' => $this->phone,
            'address' => $this->address,
            'date_of_birth' => $this->date_of_birth ?: null,
            'gender' => $this->gender ?: null,
            'nationality' => $this->nationality,
            'status' => $this->status,
        ]);

        session()->flash('success', 'Student created successfully!');
        
        $this->redirect(route('students.show', $student));
    }
}; ?>

<div>
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading size="xl">Add New Student</flux:heading>
            <flux:text class="mt-2">Create a new student profile with user account</flux:text>
        </div>
    </div>

    <div class="mt-6 space-y-8">
        <!-- User Account Information -->
        <flux:card>
            <flux:heading size="lg">Account Information</flux:heading>
            
            <div class="mt-6 space-y-6">
                <flux:input wire:model="name" label="Full Name" placeholder="Enter student's full name" required />

                <flux:input type="email" wire:model="email" label="Email Address" placeholder="student@example.com" required />
                
                <flux:input 
                    wire:model="ic_number" 
                    label="IC Number" 
                    placeholder="e.g., 961208035935" 
                    x-on:input="extractDateFromIC($el.value)" 
                />

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <flux:input type="password" wire:model="password" label="Password" placeholder="Enter password" required />
                    <flux:input type="password" wire:model="password_confirmation" label="Confirm Password" placeholder="Confirm password" required />
                </div>
            </div>
        </flux:card>

        <!-- Student Profile Information -->
        <flux:card>
            <flux:heading size="lg">Student Profile</flux:heading>
            
            <div class="mt-6 space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <flux:input wire:model="phone" label="Phone Number" placeholder="Enter phone number" />
                    <flux:input type="date" wire:model="date_of_birth" label="Date of Birth" />
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <flux:select wire:model="gender" label="Gender">
                        <flux:select.option value="">Select Gender</flux:select.option>
                        <flux:select.option value="male">Male</flux:select.option>
                        <flux:select.option value="female">Female</flux:select.option>
                        <flux:select.option value="other">Other</flux:select.option>
                    </flux:select>

                    <flux:input wire:model="nationality" label="Nationality" placeholder="Enter nationality" />
                </div>

                <flux:textarea wire:model="address" label="Address" placeholder="Enter full address" rows="3" />

                <flux:select wire:model="status" label="Status" required>
                    <flux:select.option value="active">Active</flux:select.option>
                    <flux:select.option value="inactive">Inactive</flux:select.option>
                    <flux:select.option value="suspended">Suspended</flux:select.option>
                </flux:select>
            </div>
        </flux:card>

        <div class="flex justify-between">
            <flux:button variant="ghost" href="{{ route('students.index') }}">Cancel</flux:button>
            <flux:button wire:click="create" variant="primary">Create Student</flux:button>
        </div>
    </div>
</div>

<script>
function extractDateFromIC(icNumber) {
    if (!icNumber || icNumber.length < 6) return;
    
    // Extract first 6 digits (YYMMDD)
    const dateStr = icNumber.substring(0, 6);
    const year = parseInt(dateStr.substring(0, 2));
    const month = parseInt(dateStr.substring(2, 4));
    const day = parseInt(dateStr.substring(4, 6));
    
    // Convert 2-digit year to 4-digit year (00-30 = 2000s, 31-99 = 1900s)
    const fullYear = year <= 30 ? 2000 + year : 1900 + year;
    
    // Validate date components
    if (month < 1 || month > 12 || day < 1 || day > 31) return;
    
    // Format as YYYY-MM-DD
    const formattedDate = fullYear + '-' + month.toString().padStart(2, '0') + '-' + day.toString().padStart(2, '0');
    
    // Update the date_of_birth field using Livewire
    @this.set('date_of_birth', formattedDate);
}
</script>