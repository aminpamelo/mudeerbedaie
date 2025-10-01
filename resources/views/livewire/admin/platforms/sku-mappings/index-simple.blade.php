<?php

use Livewire\Volt\Component;

new class extends Component {
    public function mount()
    {
        //
    }
}; ?>

<x-admin.layout title="SKU Mappings">
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading size="xl">SKU Mappings</flux:heading>
            <flux:text class="mt-2">Manage platform SKU mappings to link external products with your inventory</flux:text>
        </div>
        <flux:button variant="primary" href="/admin/platforms/sku-mappings/create">
            Create Mapping
        </flux:button>
    </div>

    <div class="overflow-hidden rounded-lg border border-zinc-200 bg-white p-6">
        <p class="text-zinc-600">SKU Mappings page is now working. The component will be enhanced with full functionality.</p>
    </div>
</x-admin.layout>