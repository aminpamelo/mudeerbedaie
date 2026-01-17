<?php

use App\Models\Agent;
use Livewire\Volt\Component;

new class extends Component {
    public string $agent_code = '';
    public string $name = '';
    public string $company_name = '';
    public string $registration_number = '';
    public string $contact_person = '';
    public string $email = '';
    public string $phone = '';
    public string $street = '';
    public string $city = '';
    public string $state = '';
    public string $postal_code = '';
    public string $country = 'Malaysia';
    public string $pricing_tier = 'standard';
    public ?string $commission_rate = null;
    public string $credit_limit = '0';
    public string $payment_terms = '30';
    public bool $consignment_enabled = false;
    public string $bank_name = '';
    public string $bank_account_number = '';
    public string $bank_account_name = '';
    public bool $is_active = true;
    public string $notes = '';

    public function mount(): void
    {
        $this->agent_code = Agent::generateBookstoreCode();
    }

    public function save(): void
    {
        $validated = $this->validate([
            'agent_code' => 'required|string|max:50|unique:agents,agent_code',
            'name' => 'required|string|max:255',
            'company_name' => 'nullable|string|max:255',
            'registration_number' => 'nullable|string|max:100',
            'contact_person' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:agents,email',
            'phone' => 'required|string|max:50',
            'street' => 'required|string|max:255',
            'city' => 'required|string|max:255',
            'state' => 'required|string|max:255',
            'postal_code' => 'required|string|max:20',
            'country' => 'required|string|max:255',
            'pricing_tier' => 'required|in:standard,premium,vip',
            'commission_rate' => 'nullable|numeric|min:0|max:100',
            'credit_limit' => 'required|numeric|min:0',
            'payment_terms' => 'required|in:0,30,60,90',
            'consignment_enabled' => 'boolean',
            'bank_name' => 'nullable|string|max:255',
            'bank_account_number' => 'nullable|string|max:100',
            'bank_account_name' => 'nullable|string|max:255',
            'is_active' => 'boolean',
            'notes' => 'nullable|string',
        ]);

        $address = [
            'street' => $this->street,
            'city' => $this->city,
            'state' => $this->state,
            'postal_code' => $this->postal_code,
            'country' => $this->country,
        ];

        $bankDetails = null;
        if ($this->bank_name || $this->bank_account_number || $this->bank_account_name) {
            $bankDetails = [
                'bank_name' => $this->bank_name,
                'account_number' => $this->bank_account_number,
                'account_name' => $this->bank_account_name,
            ];
        }

        Agent::create([
            'agent_code' => $validated['agent_code'],
            'name' => $validated['name'],
            'type' => Agent::TYPE_BOOKSTORE,
            'company_name' => $validated['company_name'],
            'registration_number' => $validated['registration_number'],
            'contact_person' => $validated['contact_person'],
            'email' => $validated['email'],
            'phone' => $validated['phone'],
            'address' => $address,
            'pricing_tier' => $validated['pricing_tier'],
            'commission_rate' => $validated['commission_rate'],
            'credit_limit' => $validated['credit_limit'],
            'payment_terms' => $validated['payment_terms'] . ' days',
            'consignment_enabled' => $validated['consignment_enabled'],
            'bank_details' => $bankDetails,
            'is_active' => $validated['is_active'],
            'notes' => $validated['notes'],
        ]);

        session()->flash('success', 'Kedai Buku created successfully.');

        $this->redirect(route('agents-kedai-buku.index'), navigate: true);
    }
}; ?>

<div>
    <div class="mb-6">
        <div class="flex items-center gap-4 mb-4">
            <flux:button href="{{ route('agents-kedai-buku.index') }}" variant="outline" size="sm">
                <div class="flex items-center justify-center">
                    <flux:icon name="arrow-left" class="w-4 h-4 mr-1" />
                    Back to Kedai Buku
                </div>
            </flux:button>
        </div>
        <flux:heading size="xl">Tambah Kedai Buku Baru</flux:heading>
        <flux:text class="mt-2">Add a new bookstore agent for wholesale orders</flux:text>
    </div>

    <form wire:submit="save">
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <!-- Main Information -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Basic Information -->
                <div class="bg-white dark:bg-zinc-800 rounded-lg shadow-sm border border-gray-200 dark:border-zinc-700 p-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-zinc-100 mb-4">Basic Information</h3>

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <flux:field>
                            <flux:label>Bookstore Code</flux:label>
                            <flux:input wire:model="agent_code" placeholder="KB0001" required readonly />
                            <flux:error name="agent_code" />
                            <flux:description>Auto-generated code</flux:description>
                        </flux:field>

                        <flux:field>
                            <flux:label>Kedai Buku Name *</flux:label>
                            <flux:input wire:model="name" placeholder="Bookstore name" required />
                            <flux:error name="name" />
                        </flux:field>

                        <flux:field>
                            <flux:label>Company Name</flux:label>
                            <flux:input wire:model="company_name" placeholder="Legal company name (if applicable)" />
                            <flux:error name="company_name" />
                        </flux:field>

                        <flux:field>
                            <flux:label>Registration Number</flux:label>
                            <flux:input wire:model="registration_number" placeholder="SSM/Business registration number" />
                            <flux:error name="registration_number" />
                        </flux:field>

                        <flux:field class="md:col-span-2">
                            <flux:label>Contact Person *</flux:label>
                            <flux:input wire:model="contact_person" placeholder="Person in charge of orders" required />
                            <flux:error name="contact_person" />
                        </flux:field>
                    </div>
                </div>

                <!-- Contact Information -->
                <div class="bg-white dark:bg-zinc-800 rounded-lg shadow-sm border border-gray-200 dark:border-zinc-700 p-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-zinc-100 mb-4">Contact Information</h3>

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <flux:field>
                            <flux:label>Email *</flux:label>
                            <flux:input wire:model="email" type="email" placeholder="email@kedaibuku.com" required />
                            <flux:error name="email" />
                        </flux:field>

                        <flux:field>
                            <flux:label>Phone *</flux:label>
                            <flux:input wire:model="phone" type="tel" placeholder="+60123456789" required />
                            <flux:error name="phone" />
                        </flux:field>
                    </div>
                </div>

                <!-- Business Terms -->
                <div class="bg-white dark:bg-zinc-800 rounded-lg shadow-sm border border-gray-200 dark:border-zinc-700 p-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-zinc-100 mb-4">Business Terms</h3>

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <flux:field>
                            <flux:label>Pricing Tier *</flux:label>
                            <flux:select wire:model="pricing_tier" required>
                                <flux:select.option value="standard">Standard (10% discount)</flux:select.option>
                                <flux:select.option value="premium">Premium (15% discount)</flux:select.option>
                                <flux:select.option value="vip">VIP (20% discount)</flux:select.option>
                            </flux:select>
                            <flux:error name="pricing_tier" />
                            <flux:description>Default discount applied to all products</flux:description>
                        </flux:field>

                        <flux:field>
                            <flux:label>Commission Rate (%)</flux:label>
                            <flux:input wire:model="commission_rate" type="number" step="0.01" min="0" max="100" placeholder="0.00" />
                            <flux:error name="commission_rate" />
                            <flux:description>Optional commission percentage</flux:description>
                        </flux:field>

                        <flux:field>
                            <flux:label>Credit Limit (RM) *</flux:label>
                            <flux:input wire:model="credit_limit" type="number" step="0.01" min="0" placeholder="0.00" required />
                            <flux:error name="credit_limit" />
                            <flux:description>Maximum outstanding balance allowed</flux:description>
                        </flux:field>

                        <flux:field>
                            <flux:label>Payment Terms *</flux:label>
                            <flux:select wire:model="payment_terms" required>
                                <flux:select.option value="0">COD (Cash on Delivery)</flux:select.option>
                                <flux:select.option value="30">Net 30 days</flux:select.option>
                                <flux:select.option value="60">Net 60 days</flux:select.option>
                                <flux:select.option value="90">Net 90 days</flux:select.option>
                            </flux:select>
                            <flux:error name="payment_terms" />
                        </flux:field>

                        <flux:field class="md:col-span-2">
                            <flux:label class="flex items-center gap-2">
                                <flux:checkbox wire:model="consignment_enabled" />
                                Enable Consignment
                            </flux:label>
                            <flux:description>Allow consignment stock tracking for this bookstore</flux:description>
                        </flux:field>
                    </div>
                </div>

                <!-- Address -->
                <div class="bg-white dark:bg-zinc-800 rounded-lg shadow-sm border border-gray-200 dark:border-zinc-700 p-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-zinc-100 mb-4">Address</h3>

                    <div class="space-y-4">
                        <flux:field>
                            <flux:label>Street Address *</flux:label>
                            <flux:textarea wire:model="street" rows="2" placeholder="Street address" required />
                            <flux:error name="street" />
                        </flux:field>

                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <flux:field>
                                <flux:label>City *</flux:label>
                                <flux:input wire:model="city" placeholder="City" required />
                                <flux:error name="city" />
                            </flux:field>

                            <flux:field>
                                <flux:label>State *</flux:label>
                                <flux:select wire:model="state" required>
                                    <flux:select.option value="">Select State</flux:select.option>
                                    <flux:select.option value="Johor">Johor</flux:select.option>
                                    <flux:select.option value="Kedah">Kedah</flux:select.option>
                                    <flux:select.option value="Kelantan">Kelantan</flux:select.option>
                                    <flux:select.option value="Melaka">Melaka</flux:select.option>
                                    <flux:select.option value="Negeri Sembilan">Negeri Sembilan</flux:select.option>
                                    <flux:select.option value="Pahang">Pahang</flux:select.option>
                                    <flux:select.option value="Perak">Perak</flux:select.option>
                                    <flux:select.option value="Perlis">Perlis</flux:select.option>
                                    <flux:select.option value="Pulau Pinang">Pulau Pinang</flux:select.option>
                                    <flux:select.option value="Sabah">Sabah</flux:select.option>
                                    <flux:select.option value="Sarawak">Sarawak</flux:select.option>
                                    <flux:select.option value="Selangor">Selangor</flux:select.option>
                                    <flux:select.option value="Terengganu">Terengganu</flux:select.option>
                                    <flux:select.option value="Wilayah Persekutuan Kuala Lumpur">WP Kuala Lumpur</flux:select.option>
                                    <flux:select.option value="Wilayah Persekutuan Labuan">WP Labuan</flux:select.option>
                                    <flux:select.option value="Wilayah Persekutuan Putrajaya">WP Putrajaya</flux:select.option>
                                </flux:select>
                                <flux:error name="state" />
                            </flux:field>

                            <flux:field>
                                <flux:label>Postal Code *</flux:label>
                                <flux:input wire:model="postal_code" placeholder="Postal code" required />
                                <flux:error name="postal_code" />
                            </flux:field>

                            <flux:field>
                                <flux:label>Country *</flux:label>
                                <flux:input wire:model="country" placeholder="Country" required />
                                <flux:error name="country" />
                            </flux:field>
                        </div>
                    </div>
                </div>

                <!-- Bank Details -->
                <div class="bg-white dark:bg-zinc-800 rounded-lg shadow-sm border border-gray-200 dark:border-zinc-700 p-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-zinc-100 mb-4">Bank Details (Optional)</h3>

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <flux:field>
                            <flux:label>Bank Name</flux:label>
                            <flux:select wire:model="bank_name">
                                <flux:select.option value="">Select Bank</flux:select.option>
                                <flux:select.option value="Maybank">Maybank</flux:select.option>
                                <flux:select.option value="CIMB Bank">CIMB Bank</flux:select.option>
                                <flux:select.option value="Public Bank">Public Bank</flux:select.option>
                                <flux:select.option value="RHB Bank">RHB Bank</flux:select.option>
                                <flux:select.option value="Hong Leong Bank">Hong Leong Bank</flux:select.option>
                                <flux:select.option value="AmBank">AmBank</flux:select.option>
                                <flux:select.option value="Bank Islam">Bank Islam</flux:select.option>
                                <flux:select.option value="Bank Rakyat">Bank Rakyat</flux:select.option>
                                <flux:select.option value="BSN">BSN</flux:select.option>
                                <flux:select.option value="Affin Bank">Affin Bank</flux:select.option>
                                <flux:select.option value="Alliance Bank">Alliance Bank</flux:select.option>
                                <flux:select.option value="OCBC Bank">OCBC Bank</flux:select.option>
                                <flux:select.option value="UOB Bank">UOB Bank</flux:select.option>
                                <flux:select.option value="HSBC Bank">HSBC Bank</flux:select.option>
                                <flux:select.option value="Standard Chartered">Standard Chartered</flux:select.option>
                            </flux:select>
                            <flux:error name="bank_name" />
                        </flux:field>

                        <flux:field>
                            <flux:label>Account Number</flux:label>
                            <flux:input wire:model="bank_account_number" placeholder="Account number" />
                            <flux:error name="bank_account_number" />
                        </flux:field>

                        <flux:field class="md:col-span-2">
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
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-zinc-100 mb-4">Status</h3>

                    <div class="space-y-4">
                        <flux:field>
                            <flux:label class="flex items-center gap-2">
                                <flux:checkbox wire:model="is_active" />
                                Active
                            </flux:label>
                            <flux:description>Kedai Buku can place orders</flux:description>
                        </flux:field>

                        <flux:field>
                            <flux:label>Notes</flux:label>
                            <flux:textarea wire:model="notes" rows="4" placeholder="Additional notes about this bookstore..." />
                            <flux:error name="notes" />
                        </flux:field>
                    </div>
                </div>

                <!-- Pricing Tier Info -->
                <div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg border border-blue-200 dark:border-blue-800 p-4">
                    <h4 class="text-sm font-semibold text-blue-900 dark:text-blue-100 mb-2">Pricing Tiers</h4>
                    <ul class="text-sm text-blue-700 dark:text-blue-300 space-y-1">
                        <li><span class="font-medium">Standard:</span> 10% discount on all products</li>
                        <li><span class="font-medium">Premium:</span> 15% discount on all products</li>
                        <li><span class="font-medium">VIP:</span> 20% discount on all products</li>
                    </ul>
                    <p class="text-xs text-blue-600 dark:text-blue-400 mt-2">
                        Custom pricing can be set per product after creating the bookstore.
                    </p>
                </div>

                <div class="flex flex-col gap-3">
                    <flux:button type="submit" variant="primary" class="w-full">
                        Create Kedai Buku
                    </flux:button>
                    <flux:button href="{{ route('agents-kedai-buku.index') }}" variant="outline" class="w-full">
                        Cancel
                    </flux:button>
                </div>
            </div>
        </div>
    </form>
</div>
