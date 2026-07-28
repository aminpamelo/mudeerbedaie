<?php

use App\Jobs\ExternalProvisioning\ProvisionExternalAccountJob;
use App\Models\ExternalProvisioningRequest;
use App\Models\ExternalSystem;
use App\Services\ExternalProvisioning\ExternalSystemClient;
use Livewire\Volt\Component;

new class extends Component
{
    public ExternalSystem $externalSystem;

    public ExternalProvisioningRequest $provisioning;

    public ?array $liveStatus = null;

    public ?string $statusError = null;

    /** @var array<int, array{slug: string, name: string}> */
    public array $availablePlans = [];

    public string $selectedPlan = '';

    public bool $checked = false;

    public function mount(ExternalSystem $externalSystem, ExternalProvisioningRequest $provisioningRequest): void
    {
        if (! auth()->user()->hasAnyRole(['admin', 'employee'])) {
            abort(403, 'Access denied');
        }

        abort_unless($provisioningRequest->external_system_id === $externalSystem->id, 404);

        $this->externalSystem = $externalSystem;
        $this->provisioning = $provisioningRequest;
    }

    private function buyerEmail(): ?string
    {
        return $this->provisioning->request_payload['customer']['email'] ?? null;
    }

    public function loadStatus(): void
    {
        $this->checked = true;
        $this->statusError = null;

        $email = $this->buyerEmail();

        if (! $email) {
            $this->statusError = 'No buyer email on record.';

            return;
        }

        try {
            $client = app(ExternalSystemClient::class);
            $this->liveStatus = $client->accountStatus($this->externalSystem, $email);

            $this->availablePlans = collect($client->packages($this->externalSystem))
                ->map(fn ($p): array => ['slug' => (string) ($p['slug'] ?? $p['id'] ?? ''), 'name' => (string) ($p['name'] ?? $p['slug'] ?? '')])
                ->filter(fn ($p): bool => $p['slug'] !== '')
                ->values()
                ->all();

            $this->selectedPlan = $this->liveStatus['plan']['slug']
                ?? ($this->provisioning->request_payload['product']['plan'] ?? '');
        } catch (\Throwable $e) {
            $this->statusError = $e->getMessage();
        }
    }

    public function changePlan(): void
    {
        $email = $this->buyerEmail();

        if (! $email || $this->selectedPlan === '') {
            return;
        }

        try {
            app(ExternalSystemClient::class)->changePlan(
                $this->externalSystem,
                $email,
                $this->selectedPlan,
                $this->provisioning->request_payload['order_ref'] ?? null,
            );
            session()->flash('status', 'Plan changed to '.$this->selectedPlan.'.');
            $this->loadStatus();
        } catch (\Throwable $e) {
            session()->flash('error', 'Change plan failed: '.$e->getMessage());
        }
    }

    public function revoke(): void
    {
        $email = $this->buyerEmail();

        if (! $email) {
            return;
        }

        try {
            app(ExternalSystemClient::class)->revoke($this->externalSystem, $email);
            session()->flash('status', 'Access revoked.');
            $this->loadStatus();
        } catch (\Throwable $e) {
            session()->flash('error', 'Revoke failed: '.$e->getMessage());
        }
    }

    public function retry(): void
    {
        if ($this->provisioning->isSucceeded()) {
            return;
        }

        $this->provisioning->forceFill([
            'status' => ExternalProvisioningRequest::STATUS_PENDING,
            'last_error' => null,
        ])->save();

        ProvisionExternalAccountJob::dispatch($this->provisioning->id);

        session()->flash('status', 'Provisioning re-queued.');
        $this->provisioning->refresh();
    }
} ?>

<section class="max-w-3xl">
    @php
        $customer = $provisioning->request_payload['customer'] ?? [];
        $product = $provisioning->request_payload['product'] ?? [];
        $credentials = $provisioning->credentials ?? [];
        $statusColor = match ($provisioning->status) {
            'succeeded' => ['text-emerald-600 dark:text-emerald-400', 'bg-emerald-500'],
            'failed' => ['text-red-600 dark:text-red-400', 'bg-red-500'],
            default => ['text-amber-600 dark:text-amber-400', 'bg-amber-500'],
        };
    @endphp

    <div class="mb-6 flex items-start gap-3">
        <flux:button variant="ghost" size="sm" icon="arrow-left" :href="route('admin.external-systems.accounts', $externalSystem)" wire:navigate aria-label="Back" />
        <div>
            <flux:heading size="xl">{{ $customer['email'] ?? 'Account' }}</flux:heading>
            <flux:text class="mt-1">
                {{ $externalSystem->name }} ·
                <span class="inline-flex items-center gap-1.5 font-medium {{ $statusColor[0] }}">
                    <span class="h-1.5 w-1.5 rounded-full {{ $statusColor[1] }}"></span>{{ ucfirst($provisioning->status) }}
                </span>
            </flux:text>
        </div>
    </div>

    @if (session()->has('status'))
        <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-700 dark:border-emerald-800 dark:bg-emerald-900/50 dark:text-emerald-400">{{ session('status') }}</div>
    @endif
    @if (session()->has('error'))
        <div class="mb-4 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700 dark:border-red-800 dark:bg-red-900/50 dark:text-red-400">{{ session('error') }}</div>
    @endif
    @if ($provisioning->status === 'failed' && $provisioning->last_error)
        <div class="mb-4 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700 dark:border-red-800 dark:bg-red-900/50 dark:text-red-400">
            <span class="font-medium">Last error:</span> {{ $provisioning->last_error }}
        </div>
    @endif

    <div class="space-y-4">
        {{-- Buyer --}}
        <div class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:heading size="sm" class="mb-3">Buyer</flux:heading>
            <dl class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                <div><dt class="text-xs uppercase tracking-wider text-zinc-400">Email</dt><dd class="mt-0.5 text-sm text-zinc-900 dark:text-zinc-100">{{ $customer['email'] ?? '—' }}</dd></div>
                <div><dt class="text-xs uppercase tracking-wider text-zinc-400">Name</dt><dd class="mt-0.5 text-sm text-zinc-900 dark:text-zinc-100">{{ $customer['name'] ?? '—' }}</dd></div>
                <div><dt class="text-xs uppercase tracking-wider text-zinc-400">Phone</dt><dd class="mt-0.5 text-sm text-zinc-900 dark:text-zinc-100">{{ $customer['phone'] ?? '—' }}</dd></div>
            </dl>
        </div>

        {{-- Provisioned (what we sent) --}}
        <div class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:heading size="sm" class="mb-3">Provisioned</flux:heading>
            <dl class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                <div><dt class="text-xs uppercase tracking-wider text-zinc-400">Plan bought</dt><dd class="mt-0.5">@if ($product['plan'] ?? null)<flux:badge color="indigo" size="sm">{{ $product['plan'] }}</flux:badge>@else<span class="text-sm text-zinc-400">—</span>@endif</dd></div>
                <div><dt class="text-xs uppercase tracking-wider text-zinc-400">External user ID</dt><dd class="mt-0.5 font-mono text-sm text-zinc-900 dark:text-zinc-100">{{ $provisioning->external_user_id ?? '—' }}</dd></div>
                <div><dt class="text-xs uppercase tracking-wider text-zinc-400">Order ref</dt><dd class="mt-0.5 font-mono text-sm text-zinc-900 dark:text-zinc-100">{{ $provisioning->request_payload['order_ref'] ?? '—' }}</dd></div>
            </dl>

            @if ($provisioning->login_url)
                <div class="mt-4" x-data="{ copied: false }">
                    <dt class="text-xs uppercase tracking-wider text-zinc-400">Login link</dt>
                    <div class="mt-1 flex items-center gap-2 rounded-lg border border-zinc-200 bg-zinc-50 px-3 py-2 dark:border-zinc-700 dark:bg-zinc-800/50">
                        <code class="flex-1 truncate font-mono text-xs text-zinc-700 dark:text-zinc-300">{{ $provisioning->login_url }}</code>
                        <button type="button" class="inline-flex items-center gap-1 text-xs font-medium text-indigo-600 hover:text-indigo-800 dark:text-indigo-400"
                            x-on:click="navigator.clipboard.writeText(@js($provisioning->login_url)); copied = true; setTimeout(() => copied = false, 1200)">
                            <span x-show="!copied">Copy</span><span x-show="copied" x-cloak>Copied ✓</span>
                        </button>
                    </div>
                </div>
            @endif

            @if (! empty($credentials))
                <div class="mt-4" x-data="{ show: false }">
                    <button type="button" x-on:click="show = !show" class="text-xs font-medium text-zinc-500 hover:text-zinc-700 dark:text-zinc-400">
                        <span x-show="!show">Show credentials</span><span x-show="show" x-cloak>Hide credentials</span>
                    </button>
                    <dl x-show="show" x-cloak class="mt-2 grid grid-cols-1 gap-2 rounded-lg border border-zinc-200 bg-zinc-50 p-3 dark:border-zinc-700 dark:bg-zinc-800/50 sm:grid-cols-3">
                        @foreach ($credentials as $k => $v)
                            <div><dt class="text-xs uppercase tracking-wider text-zinc-400">{{ str_replace('_', ' ', $k) }}</dt><dd class="mt-0.5 break-all font-mono text-xs text-zinc-900 dark:text-zinc-100">{{ $v }}</dd></div>
                        @endforeach
                    </dl>
                </div>
            @endif
        </div>

        {{-- Live subscription + controls (from the external system) --}}
        @if ($provisioning->isSucceeded())
            <div class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900" wire:init="loadStatus">
                <div class="mb-3 flex items-center justify-between">
                    <flux:heading size="sm">Live subscription · {{ $externalSystem->name }}</flux:heading>
                    <flux:button variant="ghost" size="sm" icon="arrow-path" wire:click="loadStatus" wire:loading.attr="disabled" wire:target="loadStatus,changePlan,revoke">Refresh</flux:button>
                </div>

                <div wire:loading.flex wire:target="loadStatus,changePlan,revoke" class="items-center gap-2 py-3 text-sm text-zinc-500">
                    <flux:icon name="arrow-path" class="h-4 w-4 animate-spin" /> Checking with {{ $externalSystem->name }}…
                </div>

                <div wire:loading.remove wire:target="loadStatus,changePlan,revoke">
                    @if ($statusError)
                        <div class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-700 dark:border-amber-800 dark:bg-amber-900/40 dark:text-amber-300">
                            Could not read live status: {{ $statusError }}
                        </div>
                    @elseif ($liveStatus)
                        @php $active = ($liveStatus['status'] ?? null) === 'active'; @endphp
                        <dl class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                            <div>
                                <dt class="text-xs uppercase tracking-wider text-zinc-400">Status</dt>
                                <dd class="mt-0.5 inline-flex items-center gap-1.5 text-sm font-medium {{ $active ? 'text-emerald-600 dark:text-emerald-400' : 'text-zinc-500 dark:text-zinc-400' }}">
                                    <span class="h-1.5 w-1.5 rounded-full {{ $active ? 'bg-emerald-500' : 'bg-zinc-400' }}"></span>
                                    {{ ucfirst($liveStatus['status'] ?? 'unknown') }}
                                </dd>
                            </div>
                            <div><dt class="text-xs uppercase tracking-wider text-zinc-400">Current plan</dt><dd class="mt-0.5 text-sm text-zinc-900 dark:text-zinc-100">{{ $liveStatus['plan']['name'] ?? ($liveStatus['plan']['slug'] ?? '—') }}</dd></div>
                            <div><dt class="text-xs uppercase tracking-wider text-zinc-400">Active until</dt><dd class="mt-0.5 text-sm text-zinc-900 dark:text-zinc-100">{{ isset($liveStatus['active_until']) ? \Illuminate\Support\Carbon::parse($liveStatus['active_until'])->format('d M Y') : 'Lifetime / —' }}</dd></div>
                        </dl>

                        {{-- Controls --}}
                        <div class="mt-5 border-t border-zinc-100 pt-4 dark:border-zinc-800">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                                <div class="flex items-end gap-2">
                                    <flux:field class="w-56">
                                        <flux:label>Change plan</flux:label>
                                        <flux:select wire:model="selectedPlan">
                                            @foreach ($availablePlans as $plan)
                                                <flux:select.option value="{{ $plan['slug'] }}">{{ $plan['name'] }}</flux:select.option>
                                            @endforeach
                                        </flux:select>
                                    </flux:field>
                                    <flux:button variant="primary" wire:click="changePlan" wire:confirm="Change this buyer's plan to '{{ $selectedPlan }}'?">Apply</flux:button>
                                </div>
                                <flux:button variant="ghost" icon="no-symbol" class="text-red-600 hover:text-red-700 dark:text-red-400" wire:click="revoke" wire:confirm="Revoke this buyer's access on {{ $externalSystem->name }}?">Revoke access</flux:button>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        @else
            {{-- Not provisioned yet — offer retry --}}
            <div class="flex items-center justify-between rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
                <flux:text class="text-sm">This order has not been provisioned yet.</flux:text>
                <flux:button variant="primary" icon="arrow-path" wire:click="retry" wire:confirm="Re-queue provisioning for this buyer?">Retry</flux:button>
            </div>
        @endif
    </div>
</section>
