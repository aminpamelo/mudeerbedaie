<?php

use App\Models\Student;
use App\Models\User;
use Livewire\Volt\Component;

new class extends Component
{
    // User information
    public $name = '';

    public $email = '';

    public $password = '';

    public $password_confirmation = '';

    // Student profile information
    public $ic_number = '';

    public $phone = '';

    public $country_code = '+60'; // Default to Malaysia

    public $address_line_1 = '';

    public $address_line_2 = '';

    public $city = '';

    public $state = '';

    public $postcode = '';

    public $country = 'Malaysia';

    public $date_of_birth = '';

    public $gender = '';

    public $nationality = '';

    public $status = 'active';

    public function create(): void
    {
        $this->validate([
            // User validation
            'name' => 'required|string|min:3|max:255',
            'email' => 'nullable|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',

            // Student validation
            'ic_number' => 'nullable|string|size:12|regex:/^[0-9]{12}$/|unique:students',
            'country_code' => 'nullable|string|max:5',
            'phone' => 'nullable|string|max:15|regex:/^[0-9]+$/',
            'address_line_1' => 'nullable|string|max:255',
            'address_line_2' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'postcode' => 'nullable|string|max:20',
            'country' => 'nullable|string|max:100',
            'date_of_birth' => 'nullable|date|before:today',
            'gender' => 'nullable|in:male,female,other',
            'nationality' => 'nullable|string|max:100',
            'status' => 'required|in:active,inactive,graduated,suspended',
        ]);

        // Create user first
        $user = User::create([
            'name' => $this->name,
            'email' => $this->email ?: null,
            'password' => bcrypt($this->password),
            'role' => 'student',
        ]);

        // Create student profile
        // Combine country code and phone number (remove + symbol and spaces)
        $fullPhone = null;
        if ($this->phone) {
            $countryCode = ltrim($this->country_code, '+');
            $fullPhone = $countryCode.$this->phone;
        }

        $student = Student::create([
            'user_id' => $user->id,
            'ic_number' => $this->ic_number ?: null,
            'phone' => $fullPhone,
            'address_line_1' => $this->address_line_1 ?: null,
            'address_line_2' => $this->address_line_2 ?: null,
            'city' => $this->city ?: null,
            'state' => $this->state ?: null,
            'postcode' => $this->postcode ?: null,
            'country' => $this->country ?: null,
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

                <flux:input type="email" wire:model="email" label="Email Address" placeholder="student@example.com" />
                
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
                    <div>
                        <flux:field label="Phone Number">
                            <div class="flex gap-2">
                                <div class="w-28 shrink-0">
                                    <flux:select wire:model="country_code">
                                        <flux:select.option value="+60">🇲🇾 +60</flux:select.option>
                                        <flux:select.option value="+65">🇸🇬 +65</flux:select.option>
                                        <flux:select.option value="+62">🇮🇩 +62</flux:select.option>
                                        <flux:select.option value="+66">🇹🇭 +66</flux:select.option>
                                        <flux:select.option value="+84">🇻🇳 +84</flux:select.option>
                                        <flux:select.option value="+63">🇵🇭 +63</flux:select.option>
                                        <flux:select.option value="+95">🇲🇲 +95</flux:select.option>
                                        <flux:select.option value="+855">🇰🇭 +855</flux:select.option>
                                        <flux:select.option value="+856">🇱🇦 +856</flux:select.option>
                                        <flux:select.option value="+673">🇧🇳 +673</flux:select.option>
                                        <flux:select.option value="+1">🇺🇸 +1</flux:select.option>
                                        <flux:select.option value="+44">🇬🇧 +44</flux:select.option>
                                        <flux:select.option value="+86">🇨🇳 +86</flux:select.option>
                                        <flux:select.option value="+91">🇮🇳 +91</flux:select.option>
                                        <flux:select.option value="+81">🇯🇵 +81</flux:select.option>
                                        <flux:select.option value="+82">🇰🇷 +82</flux:select.option>
                                    </flux:select>
                                </div>
                                <div class="flex-1">
                                    <flux:input wire:model="phone" placeholder="123456789" />
                                </div>
                            </div>
                        </flux:field>
                    </div>
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

                <!-- Address Information -->
                <div class="space-y-4">
                    <flux:heading size="sm">Address Information</flux:heading>

                    <flux:input wire:model="address_line_1" label="Address Line 1" placeholder="Street address, P.O. box, company name" />

                    <flux:input wire:model="address_line_2" label="Address Line 2 (Optional)" placeholder="Apartment, suite, unit, building, floor, etc." />

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <flux:input wire:model="city" label="City" placeholder="City" />
                        <flux:input wire:model="state" label="State/Province" placeholder="State" />
                        <flux:input wire:model="postcode" label="Postcode" placeholder="Postcode" />
                    </div>

                    <flux:input wire:model="country" label="Country" placeholder="Country" />
                </div>

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