@php
    $provisionOrder = $this->provisionOrderId ? \App\Models\ProductOrder::find($this->provisionOrderId) : null;
    $provisionSystemsList = $this->provisionSystems();
@endphp

<flux:modal wire:model.self="showProvisionModal" class="w-full max-w-md">
    <div class="space-y-5">
        <div>
            <flux:heading size="lg">Provision to external system</flux:heading>
            <flux:text class="mt-1">Create this buyer's account on a connected system.</flux:text>
        </div>

        @if ($provisionOrder)
            <div class="rounded-xl border border-zinc-200 bg-zinc-50 p-3 text-sm dark:border-zinc-700 dark:bg-zinc-800/50">
                <div class="flex items-center justify-between gap-2">
                    <span class="font-medium text-zinc-900 dark:text-white">{{ $provisionOrder->order_number }}</span>
                    <flux:badge size="sm" :color="$provisionOrder->payment_status === 'paid' ? 'emerald' : 'amber'">
                        {{ ucfirst($provisionOrder->payment_status) }}
                    </flux:badge>
                </div>
                <div class="mt-1 text-zinc-500 dark:text-zinc-400">
                    {{ $provisionOrder->customer_name ?: 'Guest' }}
                    @if ($provisionOrder->guest_email)
                        · {{ $provisionOrder->guest_email }}
                    @elseif ($provisionOrder->customer_phone)
                        · {{ $provisionOrder->customer_phone }} <span class="text-zinc-400">(account email built from phone)</span>
                    @else
                        <span class="text-red-500">· no email or phone on this order</span>
                    @endif
                </div>
            </div>

            @if ($provisionOrder->payment_status !== 'paid')
                <flux:callout variant="warning" icon="exclamation-triangle" class="text-sm">
                    This order is not marked paid — provisioning grants access anyway.
                </flux:callout>
            @endif
        @endif

        @if ($provisionSystemsList->isEmpty())
            <flux:callout variant="warning" icon="exclamation-triangle">
                No active external systems. Add one under <strong>Commerce &amp; Packages → External Systems</strong>.
            </flux:callout>
        @else
            <div>
                <flux:select wire:model="provisionSystemId" label="System" placeholder="Choose a system…">
                    @foreach ($provisionSystemsList as $s)
                        <flux:select.option value="{{ $s->id }}">{{ $s->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="provisionSystemId" />
            </div>

            <div>
                <flux:input wire:model="provisionPlan" label="Plan (optional)" placeholder="e.g. gold" />
                <flux:text size="sm" class="mt-1 text-zinc-500">The plan slug on that system. Leave blank for its default.</flux:text>
            </div>

            <div class="flex justify-end gap-2 pt-1">
                <flux:button variant="ghost" wire:click="$set('showProvisionModal', false)">Cancel</flux:button>
                <flux:button variant="primary" wire:click="provisionOrder" wire:loading.attr="disabled" wire:target="provisionOrder">
                    <span wire:loading.remove wire:target="provisionOrder">Create account</span>
                    <span wire:loading wire:target="provisionOrder">Creating…</span>
                </flux:button>
            </div>
        @endif
    </div>
</flux:modal>
