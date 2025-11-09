<?php

use App\Models\User;
use Livewire\Volt\Component;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

new class extends Component {
    public $name = '';
    public $email = '';
    public $phone = '';
    public $password = '';
    public $password_confirmation = '';
    public $status = 'active';

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:20'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'status' => ['required', 'in:active,inactive,suspended'],
        ];
    }

    public function save(): void
    {
        $validated = $this->validate();

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'],
            'password' => Hash::make($validated['password']),
            'role' => 'live_host',
            'status' => $validated['status'],
        ]);

        session()->flash('success', 'Live host created successfully.');

        $this->redirect(route('admin.live-hosts.show', $user), navigate: true);
    }

    public function cancel(): void
    {
        $this->redirect(route('admin.live-hosts'), navigate: true);
    }
}; ?>

<div>
    <x-slot:title>Create Live Host</x-slot:title>

    <div class="mb-6">
        <flux:heading size="xl">Create Live Host</flux:heading>
        <flux:text class="mt-2">Add a new user with live streaming access</flux:text>
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
                <flux:heading size="lg" class="mb-4">Security</flux:heading>

                <div class="space-y-4">
                    <flux:field>
                        <flux:label>Password *</flux:label>
                        <flux:input type="password" wire:model="password" placeholder="Enter password" />
                        <flux:error name="password" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Confirm Password *</flux:label>
                        <flux:input type="password" wire:model="password_confirmation" placeholder="Confirm password" />
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
                        Create Live Host
                    </div>
                </flux:button>
            </div>
        </form>
    </div>
</div>
