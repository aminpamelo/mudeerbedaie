<?php

use App\Models\User;
use Livewire\Volt\Component;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Rule;

new class extends Component {
    public User $host;

    public $name = '';
    public $email = '';
    public $phone = '';
    public $password = '';
    public $password_confirmation = '';
    public $status = 'active';

    public function mount(User $host): void
    {
        $this->host = $host;
        $this->name = $host->name;
        $this->email = $host->email;
        $this->phone = $host->phone ?? '';
        $this->status = $host->status;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->host->id)],
            'phone' => ['nullable', 'string', 'max:20'],
            'password' => ['nullable', 'confirmed', Password::defaults()],
            'status' => ['required', 'in:active,inactive,suspended'],
        ];
    }

    public function save(): void
    {
        $validated = $this->validate();

        $updateData = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'],
            'status' => $validated['status'],
        ];

        if (!empty($validated['password'])) {
            $updateData['password'] = Hash::make($validated['password']);
        }

        $this->host->update($updateData);

        session()->flash('success', 'Live host updated successfully.');

        $this->redirect(route('admin.live-hosts.show', $this->host), navigate: true);
    }

    public function cancel(): void
    {
        $this->redirect(route('admin.live-hosts.show', $this->host), navigate: true);
    }
}; ?>

<div>
    <x-slot:title>Edit Live Host</x-slot:title>

    <div class="mb-6">
        <flux:heading size="xl">Edit Live Host</flux:heading>
        <flux:text class="mt-2">Update live host information</flux:text>
    </div>

    <div class="max-w-2xl">
        <form wire:submit="save" class="space-y-6">
            <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-6">
                <flux:heading size="lg" class="mb-4">Basic Information</flux:heading>

                <div class="space-y-4">
                    <flux:field>
                        <flux:label>Name *</flux:label>
                        <flux:input wire:model="name" placeholder="Enter full name" />
                        <flux:error name="name" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Email *</flux:label>
                        <flux:input type="email" wire:model="email" placeholder="Enter email address" />
                        <flux:error name="email" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Phone</flux:label>
                        <flux:input wire:model="phone" placeholder="Enter phone number" />
                        <flux:error name="phone" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Status *</flux:label>
                        <flux:select wire:model="status">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                            <option value="suspended">Suspended</option>
                        </flux:select>
                        <flux:error name="status" />
                    </flux:field>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-6">
                <flux:heading size="lg" class="mb-4">Change Password</flux:heading>
                <flux:text class="mb-4 text-sm">Leave blank to keep the current password</flux:text>

                <div class="space-y-4">
                    <flux:field>
                        <flux:label>New Password</flux:label>
                        <flux:input type="password" wire:model="password" placeholder="Enter new password" />
                        <flux:error name="password" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Confirm New Password</flux:label>
                        <flux:input type="password" wire:model="password_confirmation" placeholder="Confirm new password" />
                        <flux:error name="password_confirmation" />
                    </flux:field>
                </div>
            </div>

            <div class="flex items-center justify-end gap-3">
                <flux:button variant="ghost" type="button" wire:click="cancel">
                    Cancel
                </flux:button>
                <flux:button variant="primary" type="submit">
                    <div class="flex items-center justify-center">
                        <flux:icon name="check" class="w-4 h-4 mr-1" />
                        Save Changes
                    </div>
                </flux:button>
            </div>
        </form>
    </div>
</div>
