<?php

use App\Models\Setting;
use Livewire\Volt\Component;

new class extends Component {
    public string $standard_discount = '10';
    public string $premium_discount = '15';
    public string $vip_discount = '20';

    public function mount(): void
    {
        $this->standard_discount = (string) (Setting::getValue('pricing.tier_discount_standard') ?? 10);
        $this->premium_discount = (string) (Setting::getValue('pricing.tier_discount_premium') ?? 15);
        $this->vip_discount = (string) (Setting::getValue('pricing.tier_discount_vip') ?? 20);
    }

    public function save(): void
    {
        $this->validate([
            'standard_discount' => 'required|numeric|min:0|max:100',
            'premium_discount' => 'required|numeric|min:0|max:100',
            'vip_discount' => 'required|numeric|min:0|max:100',
        ]);

        $settings = [
            'pricing.tier_discount_standard' => $this->standard_discount,
            'pricing.tier_discount_premium' => $this->premium_discount,
            'pricing.tier_discount_vip' => $this->vip_discount,
        ];

        foreach ($settings as $key => $value) {
            Setting::updateOrCreate(
                ['key' => $key],
                [
                    'value' => $value,
                    'group' => 'pricing',
                    'type' => 'number',
                ]
            );
        }

        session()->flash('message', 'Pricing tier settings saved successfully.');
    }

    public function layout(): string
    {
        return 'components.admin.settings-layout';
    }

    public function layoutData(): array
    {
        return [
            'title' => 'Pricing Settings',
            'activeTab' => 'pricing',
        ];
    }
}; ?>

<div>
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading size="xl">Pricing Tier Settings</flux:heading>
            <flux:text class="mt-2">Configure discount percentages for each pricing tier applied to agents and bookstores</flux:text>
        </div>
    </div>

    @if (session()->has('message'))
        <flux:callout variant="success" class="mb-6">
            {{ session('message') }}
        </flux:callout>
    @endif

    <form wire:submit="save">
        <flux:card class="space-y-6">
            <div class="p-6 space-y-6">
                <div>
                    <flux:heading size="lg" class="mb-2">Tier Discount Percentages</flux:heading>
                    <flux:text class="text-sm text-gray-600 mb-4">Set the discount percentage for each pricing tier. These discounts will be applied to product prices for agents and bookstores based on their assigned tier.</flux:text>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div>
                            <flux:field>
                                <flux:label>Standard Tier Discount (%)</flux:label>
                                <flux:text class="text-sm text-gray-600 mb-2">Default tier for new agents</flux:text>
                                <flux:input
                                    wire:model="standard_discount"
                                    type="number"
                                    min="0"
                                    max="100"
                                    step="0.01"
                                    placeholder="10"
                                />
                                <flux:error name="standard_discount" />
                            </flux:field>
                        </div>

                        <div>
                            <flux:field>
                                <flux:label>Premium Tier Discount (%)</flux:label>
                                <flux:text class="text-sm text-gray-600 mb-2">For established partners</flux:text>
                                <flux:input
                                    wire:model="premium_discount"
                                    type="number"
                                    min="0"
                                    max="100"
                                    step="0.01"
                                    placeholder="15"
                                />
                                <flux:error name="premium_discount" />
                            </flux:field>
                        </div>

                        <div>
                            <flux:field>
                                <flux:label>VIP Tier Discount (%)</flux:label>
                                <flux:text class="text-sm text-gray-600 mb-2">For top-tier partners</flux:text>
                                <flux:input
                                    wire:model="vip_discount"
                                    type="number"
                                    min="0"
                                    max="100"
                                    step="0.01"
                                    placeholder="20"
                                />
                                <flux:error name="vip_discount" />
                            </flux:field>
                        </div>
                    </div>
                </div>

                <flux:separator />

                <div>
                    <flux:heading size="lg" class="mb-2">How Pricing Tiers Work</flux:heading>
                    <div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg border border-blue-200 dark:border-blue-800 p-4">
                        <div class="flex items-start gap-3">
                            <flux:icon name="information-circle" class="h-6 w-6 text-blue-500 flex-shrink-0 mt-0.5" />
                            <div class="text-sm text-blue-700 dark:text-blue-300">
                                <p class="mb-2"><strong>Tier discounts</strong> are applied to product prices when creating orders for agents or bookstores.</p>
                                <ul class="list-disc list-inside space-y-1">
                                    <li>Each agent/bookstore is assigned a pricing tier</li>
                                    <li>The discount percentage reduces the product price automatically</li>
                                    <li>Custom pricing can override tier discounts for specific products</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="px-6 py-4 bg-gray-50 dark:bg-zinc-900 border-t border-gray-200 dark:border-zinc-700 flex justify-end">
                <flux:button type="submit" variant="primary">
                    Save Pricing Settings
                </flux:button>
            </div>
        </flux:card>
    </form>
</div>
