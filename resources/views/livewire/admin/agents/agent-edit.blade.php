<?php

use App\Models\Agent;
use Livewire\Volt\Component;

new class extends Component {
    public Agent $agent;

    public $agent_code = '';
    public $name = '';
    public $type = 'agent';
    public $pricing_tier = 'standard';
    public $company_name = '';
    public $registration_number = '';

    public function getPricingTiers(): array
    {
        $discounts = Agent::getTierDiscountsFromSettings();

        return [
            'standard' => "Standard ({$discounts['standard']}% discount)",
            'premium' => "Premium ({$discounts['premium']}% discount)",
            'vip' => "VIP ({$discounts['vip']}% discount)",
        ];
    }
    public $contact_person = '';
    public $email = '';
    public $phone = '';
    public $street = '';
    public $city = '';
    public $state = '';
    public $postal_code = '';
    public $country = 'Malaysia';
    public $payment_terms = '';
    public $bank_name = '';
    public $bank_account_number = '';
    public $bank_account_name = '';
    public $is_active = true;
    public $notes = '';

    public function mount(Agent $agent): void
    {
        $this->agent = $agent;
        $this->agent_code = $agent->agent_code;
        $this->name = $agent->name;
        $this->type = $agent->type;
        $this->pricing_tier = $agent->pricing_tier ?? 'standard';
        $this->company_name = $agent->company_name;
        $this->registration_number = $agent->registration_number;
        $this->contact_person = $agent->contact_person;
        $this->email = $agent->email;
        $this->phone = $agent->phone;
        $this->payment_terms = $agent->payment_terms;
        $this->is_active = $agent->is_active;
        $this->notes = $agent->notes;

        if ($agent->address) {
            $this->street = $agent->address['street'] ?? '';
            $this->city = $agent->address['city'] ?? '';
            $this->state = $agent->address['state'] ?? '';
            $this->postal_code = $agent->address['postal_code'] ?? '';
            $this->country = $agent->address['country'] ?? 'Malaysia';
        }

        if ($agent->bank_details) {
            $this->bank_name = $agent->bank_details['bank_name'] ?? '';
            $this->bank_account_number = $agent->bank_details['account_number'] ?? '';
            $this->bank_account_name = $agent->bank_details['account_name'] ?? '';
        }
    }

    public function save(): void
    {
        $validated = $this->validate([
            'agent_code' => 'required|string|max:50|unique:agents,agent_code,' . $this->agent->id,
            'name' => 'required|string|max:255',
            'type' => 'required|in:agent,company',
            'pricing_tier' => 'required|in:standard,premium,vip',
            'company_name' => 'nullable|string|max:255',
            'registration_number' => 'nullable|string|max:100',
            'contact_person' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'street' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:255',
            'state' => 'nullable|string|max:255',
            'postal_code' => 'nullable|string|max:20',
            'country' => 'nullable|string|max:255',
            'payment_terms' => 'nullable|string|max:100',
            'bank_name' => 'nullable|string|max:255',
            'bank_account_number' => 'nullable|string|max:100',
            'bank_account_name' => 'nullable|string|max:255',
            'is_active' => 'boolean',
            'notes' => 'nullable|string',
        ]);

        $address = null;
        if ($this->street || $this->city || $this->state || $this->postal_code || $this->country) {
            $address = [
                'street' => $this->street,
                'city' => $this->city,
                'state' => $this->state,
                'postal_code' => $this->postal_code,
                'country' => $this->country,
            ];
        }

        $bankDetails = null;
        if ($this->bank_name || $this->bank_account_number || $this->bank_account_name) {
            $bankDetails = [
                'bank_name' => $this->bank_name,
                'account_number' => $this->bank_account_number,
                'account_name' => $this->bank_account_name,
            ];
        }

        $this->agent->update([
            'agent_code' => $validated['agent_code'],
            'name' => $validated['name'],
            'type' => $validated['type'],
            'pricing_tier' => $validated['pricing_tier'],
            'company_name' => $validated['company_name'],
            'registration_number' => $validated['registration_number'],
            'contact_person' => $validated['contact_person'],
            'email' => $validated['email'],
            'phone' => $validated['phone'],
            'address' => $address,
            'payment_terms' => $validated['payment_terms'],
            'bank_details' => $bankDetails,
            'is_active' => $validated['is_active'],
            'notes' => $validated['notes'],
        ]);

        session()->flash('success', 'Agent updated successfully.');

        $this->redirect(route('agents.show', $this->agent), navigate: true);
    }
}; ?>

<div>
    <div class="mb-6">
        <div class="flex items-center gap-4 mb-4">
            <flux:button href="{{ route('agents.index') }}" variant="outline" icon="arrow-left" size="sm">
                <div class="flex items-center justify-center">
                    <flux:icon name="arrow-left" class="w-4 h-4 mr-1" />
                    Back to Agents
                </div>
            </flux:button>
        </div>
        <flux:heading size="xl">Edit Agent</flux:heading>
        <flux:text class="mt-2">Update agent or company information</flux:text>
    </div>

    <form wire:submit="save">
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <!-- Main Information -->
            <div class="lg:col-span-2 space-y-6">
                <div class="bg-white dark:bg-zinc-800 rounded-lg shadow-sm border border-gray-200 dark:border-zinc-700 p-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-zinc-100 mb-4">Basic Information</h3>

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <flux:field>
                            <flux:label>Agent Code</flux:label>
                            <flux:input wire:model="agent_code" placeholder="AGT0001" required />
                            <flux:error name="agent_code" />
                        </flux:field>

                        <flux:field>
                            <flux:label>Type</flux:label>
                            <flux:select wire:model.live="type" required>
                                <flux:select.option value="agent">Agent (Individual)</flux:select.option>
                                <flux:select.option value="company">Company</flux:select.option>
                            </flux:select>
                            <flux:error name="type" />
                        </flux:field>

                        <flux:field>
                            <flux:label>Pricing Tier</flux:label>
                            <flux:select wire:model="pricing_tier" required>
                                @foreach($this->getPricingTiers() as $value => $label)
                                    <flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>
                                @endforeach
                            </flux:select>
                            <flux:error name="pricing_tier" />
                            <flux:description>Discount tier for this agent's orders</flux:description>
                        </flux:field>

                        <flux:field class="md:col-span-2">
                            <flux:label>Name</flux:label>
                            <flux:input wire:model="name" placeholder="Agent or person name" required />
                            <flux:error name="name" />
                        </flux:field>

                        @if($type === 'company')
                            <flux:field>
                                <flux:label>Company Name</flux:label>
                                <flux:input wire:model="company_name" placeholder="Company legal name" />
                                <flux:error name="company_name" />
                            </flux:field>

                            <flux:field>
                                <flux:label>Registration Number</flux:label>
                                <flux:input wire:model="registration_number" placeholder="Company registration number" />
                                <flux:error name="registration_number" />
                            </flux:field>
                        @endif
                    </div>
                </div>

                <!-- Contact Information -->
                <div class="bg-white dark:bg-zinc-800 rounded-lg shadow-sm border border-gray-200 dark:border-zinc-700 p-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-zinc-100 mb-4">Contact Information</h3>

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <flux:field>
                            <flux:label>Contact Person</flux:label>
                            <flux:input wire:model="contact_person" placeholder="Contact person name" />
                            <flux:error name="contact_person" />
                        </flux:field>

                        <flux:field>
                            <flux:label>Phone</flux:label>
                            <flux:input wire:model="phone" type="tel" placeholder="+60123456789" />
                            <flux:error name="phone" />
                        </flux:field>

                        <flux:field class="md:col-span-2">
                            <flux:label>Email</flux:label>
                            <flux:input wire:model="email" type="email" placeholder="email@example.com" />
                            <flux:error name="email" />
                        </flux:field>
                    </div>
                </div>

                <!-- Address -->
                <div class="bg-white dark:bg-zinc-800 rounded-lg shadow-sm border border-gray-200 dark:border-zinc-700 p-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-zinc-100 mb-4">Address</h3>

                    <div class="space-y-4">
                        <flux:field>
                            <flux:label>Street Address</flux:label>
                            <flux:input wire:model="street" placeholder="Street address" />
                            <flux:error name="street" />
                        </flux:field>

                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <flux:field>
                                <flux:label>City</flux:label>
                                <flux:input wire:model="city" placeholder="City" />
                                <flux:error name="city" />
                            </flux:field>

                            <flux:field>
                                <flux:label>State</flux:label>
                                <flux:input wire:model="state" placeholder="State" />
                                <flux:error name="state" />
                            </flux:field>

                            <flux:field>
                                <flux:label>Postal Code</flux:label>
                                <flux:input wire:model="postal_code" placeholder="Postal code" />
                                <flux:error name="postal_code" />
                            </flux:field>

                            <flux:field>
                                <flux:label>Country</flux:label>
                                <flux:input wire:model="country" placeholder="Country" />
                                <flux:error name="country" />
                            </flux:field>
                        </div>
                    </div>
                </div>

                <!-- Bank Details -->
                <div class="bg-white dark:bg-zinc-800 rounded-lg shadow-sm border border-gray-200 dark:border-zinc-700 p-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-zinc-100 mb-4">Bank Details</h3>

                    <div class="grid grid-cols-1 gap-4">
                        <flux:field>
                            <flux:label>Bank Name</flux:label>
                            <flux:input wire:model="bank_name" placeholder="Bank name" />
                            <flux:error name="bank_name" />
                        </flux:field>

                        <flux:field>
                            <flux:label>Account Number</flux:label>
                            <flux:input wire:model="bank_account_number" placeholder="Account number" />
                            <flux:error name="bank_account_number" />
                        </flux:field>

                        <flux:field>
                            <flux:label>Account Name</flux:label>
                            <flux:input wire:model="bank_account_name" placeholder="Account holder name" />
                            <flux:error name="bank_account_name" />
                        </flux:field>
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="space-y-6">
                <div class="bg-white dark:bg-zinc-800 rounded-lg shadow-sm border border-gray-200 dark:border-zinc-700 p-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-zinc-100 mb-4">Settings</h3>

                    <div class="space-y-4">
                        <flux:field>
                            <flux:label>Payment Terms</flux:label>
                            <flux:input wire:model="payment_terms" placeholder="e.g., Net 30 days, COD" />
                            <flux:error name="payment_terms" />
                            <flux:description>Payment terms for consignment settlements</flux:description>
                        </flux:field>

                        <flux:field>
                            <flux:label class="flex items-center gap-2">
                                <flux:checkbox wire:model="is_active" />
                                Active
                            </flux:label>
                            <flux:description>Agent can receive consignment stock</flux:description>
                        </flux:field>

                        <flux:field>
                            <flux:label>Notes</flux:label>
                            <flux:textarea wire:model="notes" rows="4" placeholder="Additional notes..." />
                            <flux:error name="notes" />
                        </flux:field>
                    </div>
                </div>

                <div class="flex flex-col gap-3">
                    <flux:button type="submit" variant="primary" class="w-full">
                        Update Agent
                    </flux:button>
                    <flux:button href="{{ route('agents.show', $agent) }}" variant="outline" class="w-full">
                        Cancel
                    </flux:button>
                </div>
            </div>
        </div>
    </form>
</div>
