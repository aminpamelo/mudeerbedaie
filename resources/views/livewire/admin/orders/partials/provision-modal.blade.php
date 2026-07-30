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
        @endif

        @if ($provisionResult)
            {{-- SUCCESS: the login details to hand to the buyer --}}
            <flux:callout variant="success" icon="check-circle">Account created in {{ $provisionResult['system'] }}.</flux:callout>

            <div class="space-y-3">
                @foreach ([
                    ['Magic login link', $provisionResult['magic_link'] ?? null],
                    ['Login URL', $provisionResult['login_url'] ?? null],
                    ['Username', $provisionResult['username'] ?? null],
                    ['Temporary password', $provisionResult['temp_password'] ?? null],
                ] as $i => $field)
                    @php [$label, $value] = $field; @endphp
                    @if ($value)
                        <div x-data="{ copied: false }">
                            <flux:text size="sm" class="text-zinc-500">{{ $label }}</flux:text>
                            <div class="mt-1 flex items-center gap-2">
                                <input x-ref="cp{{ $i }}" type="text" readonly value="{{ $value }}"
                                       class="min-w-0 flex-1 rounded-lg border border-zinc-200 bg-white px-3 py-1.5 font-mono text-xs text-zinc-800 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-200" />
                                <flux:button size="sm" variant="ghost" type="button"
                                             x-on:click="navigator.clipboard.writeText($refs.cp{{ $i }}.value); copied = true; setTimeout(() => copied = false, 1500)">
                                    <span x-show="!copied">Copy</span>
                                    <span x-show="copied" class="text-emerald-600">Copied</span>
                                </flux:button>
                            </div>
                        </div>
                    @endif
                @endforeach

                @unless ($provisionResult['magic_link'] ?? $provisionResult['login_url'] ?? $provisionResult['username'] ?? $provisionResult['temp_password'] ?? null)
                    <flux:text size="sm" class="text-zinc-500">Account created (ID {{ $provisionResult['external_user_id'] }}). This system returned no login link.</flux:text>
                @endunless
            </div>

            <div class="flex items-center justify-between gap-2 pt-1">
                <flux:button variant="ghost" size="sm" wire:click="provisionAnother">Provision to another system</flux:button>
                <div class="flex gap-2">
                    @if ($provisionResult['magic_link'] ?? $provisionResult['login_url'] ?? null)
                        <flux:button variant="ghost" :href="$provisionResult['magic_link'] ?? $provisionResult['login_url']" target="_blank">Open login</flux:button>
                    @endif
                    <flux:button variant="primary" wire:click="closeProvisionModal">Done</flux:button>
                </div>
            </div>
        @elseif ($provisionSystemsList->isEmpty())
            <flux:callout variant="warning" icon="exclamation-triangle">
                No active external systems. Add one under <strong>Commerce &amp; Packages → External Systems</strong>.
            </flux:callout>
        @else
            @if ($provisionError)
                <flux:callout variant="danger" icon="x-circle" class="text-sm">{{ $provisionError }}</flux:callout>
            @endif

            @if ($provisionOrder && $provisionOrder->payment_status !== 'paid')
                <flux:callout variant="warning" icon="exclamation-triangle" class="text-sm">
                    This order is not marked paid — provisioning grants access anyway.
                </flux:callout>
            @endif

            <div>
                <flux:select wire:model.live="provisionSystemId" label="System" placeholder="Choose a system…">
                    @foreach ($provisionSystemsList as $s)
                        <flux:select.option value="{{ $s->id }}">{{ $s->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="provisionSystemId" />
            </div>

            <div>
                <div wire:loading.flex wire:target="provisionSystemId, loadProvisionPlans, openProvisionModal" class="items-center gap-2 py-1 text-sm text-zinc-500">
                    <flux:icon name="arrow-path" class="h-4 w-4 animate-spin" /> Loading plans…
                </div>

                <div wire:loading.remove wire:target="provisionSystemId, loadProvisionPlans, openProvisionModal">
                    @if (! empty($provisionPlans))
                        <flux:select wire:model="provisionPlan" label="Plan" placeholder="Default plan">
                            <flux:select.option value="">Default plan</flux:select.option>
                            @foreach ($provisionPlans as $planSlug => $planName)
                                <flux:select.option value="{{ $planSlug }}">{{ $planName }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    @else
                        <flux:input wire:model="provisionPlan" label="Plan (optional)" placeholder="e.g. gold" />
                        <flux:text size="sm" class="mt-1 text-zinc-500">
                            @if ($provisionSystemId && $provisionPlansLoaded)
                                This system didn't return a plan list — type the slug, or leave blank for its default.
                            @else
                                The plan slug on that system. Leave blank for its default.
                            @endif
                        </flux:text>
                    @endif
                </div>
            </div>

            <div class="flex justify-end gap-2 pt-1">
                <flux:button variant="ghost" wire:click="closeProvisionModal">Cancel</flux:button>
                <flux:button variant="primary" wire:click="provisionOrder" wire:loading.attr="disabled" wire:target="provisionOrder">
                    <span wire:loading.remove wire:target="provisionOrder">Create account</span>
                    <span wire:loading wire:target="provisionOrder">Creating…</span>
                </flux:button>
            </div>
        @endif
    </div>
</flux:modal>

{{-- Provision result toast (works on any page that includes this partial) --}}
<div
    x-data="{ show: false, message: '', ok: true }"
    x-on:order-provisioned.window="message = $event.detail.message; ok = true; show = true; setTimeout(() => show = false, 3500)"
    x-on:order-provision-failed.window="message = $event.detail.message; ok = false; show = true; setTimeout(() => show = false, 6000)"
    x-show="show"
    x-transition
    class="fixed bottom-4 right-4 z-[60]"
    style="display: none;"
>
    <div class="flex items-center gap-3 rounded-xl px-5 py-3 text-white shadow-lg" :class="ok ? 'bg-emerald-600' : 'bg-red-600'">
        <flux:icon name="server-stack" class="h-5 w-5" />
        <span x-text="message" class="text-sm font-medium"></span>
    </div>
</div>
