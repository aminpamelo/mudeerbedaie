<?php

use App\Models\Warehouse;
use Illuminate\Support\Str;
use Livewire\Volt\Component;

new class extends Component {
    public Warehouse $warehouse;

    public $name = '';
    public $code = '';
    public $description = '';
    public $manager_name = '';
    public $manager_email = '';
    public $manager_phone = '';
    public $status = 'active';

    // Address fields
    public $street = '';
    public $city = '';
    public $state = '';
    public $postal_code = '';
    public $country = 'Malaysia';

    public function mount(Warehouse $warehouse): void
    {
        $this->warehouse = $warehouse;
        $this->name = $warehouse->name;
        $this->code = $warehouse->code;
        $this->description = $warehouse->description;
        $this->manager_name = $warehouse->manager_name;
        $this->manager_email = $warehouse->manager_email;
        $this->manager_phone = $warehouse->manager_phone;
        $this->status = $warehouse->status;

        // Load address fields
        if ($warehouse->address) {
            $this->street = $warehouse->address['street'] ?? '';
            $this->city = $warehouse->address['city'] ?? '';
            $this->state = $warehouse->address['state'] ?? '';
            $this->postal_code = $warehouse->address['postal_code'] ?? '';
            $this->country = $warehouse->address['country'] ?? 'Malaysia';
        }
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:20|unique:warehouses,code,' . $this->warehouse->id,
            'description' => 'nullable|string',
            'manager_name' => 'nullable|string|max:255',
            'manager_email' => 'nullable|email|max:255',
            'manager_phone' => 'nullable|string|max:20',
            'status' => 'required|in:active,inactive',
            'street' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:255',
            'state' => 'nullable|string|max:255',
            'postal_code' => 'nullable|string|max:20',
            'country' => 'nullable|string|max:255',
        ];
    }

    public function updatedName(): void
    {
        $this->code = strtoupper(Str::slug($this->name, ''));
    }

    public function save(): void
    {
        $this->validate();

        $address = array_filter([
            'street' => $this->street,
            'city' => $this->city,
            'state' => $this->state,
            'postal_code' => $this->postal_code,
            'country' => $this->country,
        ]);

        $this->warehouse->update([
            'name' => $this->name,
            'code' => $this->code,
            'description' => $this->description,
            'manager_name' => $this->manager_name,
            'manager_email' => $this->manager_email,
            'manager_phone' => $this->manager_phone,
            'status' => $this->status,
            'address' => $address ?: null,
        ]);

        session()->flash('success', 'Warehouse updated successfully.');

        $this->redirect(route('warehouses.show', $this->warehouse));
    }
}; ?>

<div>
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading size="xl">Edit Warehouse</flux:heading>
            <flux:text class="mt-2">Update warehouse information and settings</flux:text>
        </div>
        <flux:button variant="outline" href="{{ route('warehouses.index') }}" icon="arrow-left">
            Back to Warehouses
        </flux:button>
    </div>

    <form wire:submit="save" class="space-y-6">
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <!-- Basic Information -->
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg">Basic Information</flux:heading>
                    <flux:text class="mt-1 text-sm">Enter the warehouse details</flux:text>
                </div>

                <flux:field>
                    <flux:label>Warehouse Name</flux:label>
                    <flux:input wire:model.live="name" placeholder="Enter warehouse name" />
                    <flux:error name="name" />
                </flux:field>

                <flux:field>
                    <flux:label>Warehouse Code</flux:label>
                    <flux:input wire:model="code" placeholder="WH001" />
                    <flux:error name="code" />
                    <flux:description>Unique identifier for this warehouse</flux:description>
                </flux:field>

                <flux:field>
                    <flux:label>Description</flux:label>
                    <flux:textarea wire:model="description" placeholder="Warehouse description" rows="3" />
                    <flux:error name="description" />
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

            <!-- Manager Information -->
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg">Manager Information</flux:heading>
                    <flux:text class="mt-1 text-sm">Warehouse manager contact details</flux:text>
                </div>

                <flux:field>
                    <flux:label>Manager Name</flux:label>
                    <flux:input wire:model="manager_name" placeholder="Enter manager name" />
                    <flux:error name="manager_name" />
                </flux:field>

                <flux:field>
                    <flux:label>Manager Email</flux:label>
                    <flux:input type="email" wire:model="manager_email" placeholder="manager@example.com" />
                    <flux:error name="manager_email" />
                </flux:field>

                <flux:field>
                    <flux:label>Manager Phone</flux:label>
                    <flux:input wire:model="manager_phone" placeholder="+60 12-345 6789" />
                    <flux:error name="manager_phone" />
                </flux:field>
            </div>
        </div>

        <!-- Address Information -->
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Address Information</flux:heading>
                <flux:text class="mt-1 text-sm">Physical location of the warehouse</flux:text>
            </div>

            <flux:field>
                <flux:label>Street Address</flux:label>
                <flux:input wire:model="street" placeholder="Enter street address" />
                <flux:error name="street" />
            </flux:field>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <flux:field>
                    <flux:label>City</flux:label>
                    <flux:input wire:model="city" placeholder="Enter city" />
                    <flux:error name="city" />
                </flux:field>

                <flux:field>
                    <flux:label>State</flux:label>
                    <flux:input wire:model="state" placeholder="Enter state" />
                    <flux:error name="state" />
                </flux:field>
            </div>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <flux:field>
                    <flux:label>Postal Code</flux:label>
                    <flux:input wire:model="postal_code" placeholder="12345" />
                    <flux:error name="postal_code" />
                </flux:field>

                <flux:field>
                    <flux:label>Country</flux:label>
                    <flux:input wire:model="country" placeholder="Enter country" />
                    <flux:error name="country" />
                </flux:field>
            </div>
        </div>

        <!-- Actions -->
        <div class="flex items-center justify-end space-x-3 border-t border-gray-200 pt-6">
            <flux:button variant="outline" href="{{ route('warehouses.show', $warehouse) }}">
                Cancel
            </flux:button>
            <flux:button type="submit" variant="primary">
                Update Warehouse
            </flux:button>
        </div>
    </form>
</div>