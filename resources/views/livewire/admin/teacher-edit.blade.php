<?php

use App\Models\Teacher;
use Livewire\Volt\Component;

new class extends Component {
    public Teacher $teacher;
    public $name = '';
    public $email = '';
    public $ic_number = '';
    public $phone = '';
    public $status = '';
    public $bank_account_holder = '';
    public $bank_account_number = '';
    public $bank_name = '';

    public function mount(Teacher $teacher): void
    {
        $this->teacher = $teacher;
        $this->name = $teacher->user->name;
        $this->email = $teacher->user->email;
        $this->ic_number = $teacher->ic_number;
        $this->phone = $teacher->phone;
        $this->status = $teacher->status;
        $this->bank_account_holder = $teacher->bank_account_holder;
        $this->bank_account_number = $teacher->bank_account_number;
        $this->bank_name = $teacher->bank_name;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,' . $this->teacher->user_id,
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

        // Update user information
        $this->teacher->user->update([
            'name' => $this->name,
            'email' => $this->email,
        ]);

        // Update teacher information
        $this->teacher->update([
            'ic_number' => $this->ic_number ?: null,
            'phone' => $this->phone ?: null,
            'status' => $this->status,
            'bank_account_holder' => $this->bank_account_holder ?: null,
            'bank_account_number' => $this->bank_account_number ?: null,
            'bank_name' => $this->bank_name ?: null,
        ]);

        session()->flash('success', 'Teacher updated successfully.');
        $this->redirect(route('teachers.show', $this->teacher));
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
            <flux:heading size="xl">Edit Teacher</flux:heading>
            <flux:text class="mt-2">Update teacher information</flux:text>
        </div>
        <div class="flex space-x-3">
            <flux:button variant="ghost" href="{{ route('teachers.show', $teacher) }}">
                View Teacher
            </flux:button>
            <flux:button variant="ghost" href="{{ route('teachers.index') }}">
                Back to Teachers
            </flux:button>
        </div>
    </div>

    <div class="bg-white dark:bg-zinc-800 shadow rounded-lg border border-gray-200 dark:border-zinc-700">
        <form wire:submit="save" class="p-6 space-y-6">
            
            <!-- Teacher ID Display -->
            <div class="bg-gray-50 p-4 rounded-lg">
                <flux:text class="text-sm text-gray-600">Teacher ID</flux:text>
                <flux:text class="font-semibold">{{ $teacher->teacher_id }}</flux:text>
            </div>

            <!-- User Information -->
            <div>
                <flux:heading size="lg" class="mb-4">User Information</flux:heading>
                
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
                </div>
            </div>

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
                <flux:text class="text-sm text-gray-600 mb-4">Update bank account details for payment processing</flux:text>
                
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

            <!-- Joined Date Display -->
            <div class="border-t pt-6">
                <div class="bg-gray-50 p-4 rounded-lg">
                    <flux:text class="text-sm text-gray-600">Joined Date</flux:text>
                    <flux:text class="font-semibold">
                        {{ $teacher->joined_at ? $teacher->joined_at->format('F j, Y') : 'Not set' }}
                    </flux:text>
                </div>
            </div>

            <div class="flex items-center justify-end space-x-3 pt-6 border-t">
                <flux:button variant="ghost" href="{{ route('teachers.show', $teacher) }}">
                    Cancel
                </flux:button>
                <flux:button type="submit" variant="primary">
                    Update Teacher
                </flux:button>
            </div>
        </form>
    </div>
</div>