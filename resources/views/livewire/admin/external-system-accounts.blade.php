<?php

use App\Jobs\ExternalProvisioning\ProvisionExternalAccountJob;
use App\Models\ExternalProvisioningRequest;
use App\Models\ExternalSystem;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    public ExternalSystem $externalSystem;

    public string $statusFilter = 'all';

    public function mount(ExternalSystem $externalSystem): void
    {
        if (! auth()->user()->hasAnyRole(['admin', 'employee'])) {
            abort(403, 'Access denied');
        }

        $this->externalSystem = $externalSystem;
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function retry(int $id): void
    {
        $request = ExternalProvisioningRequest::where('external_system_id', $this->externalSystem->id)->findOrFail($id);

        if ($request->isSucceeded()) {
            return;
        }

        $request->forceFill([
            'status' => ExternalProvisioningRequest::STATUS_PENDING,
            'last_error' => null,
        ])->save();

        ProvisionExternalAccountJob::dispatch($request->id);

        session()->flash('status', 'Provisioning re-queued.');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<ExternalProvisioningRequest>
     */
    private function baseQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return ExternalProvisioningRequest::where('external_system_id', $this->externalSystem->id);
    }

    public function with(): array
    {
        return [
            'requests' => $this->baseQuery()
                ->when($this->statusFilter !== 'all', fn ($q) => $this->statusFilter === 'pending'
                    ? $q->whereIn('status', ['pending', 'processing'])
                    : $q->where('status', $this->statusFilter))
                ->latest()
                ->paginate(20),
            'counts' => [
                'all' => $this->baseQuery()->count(),
                'succeeded' => $this->baseQuery()->where('status', 'succeeded')->count(),
                'failed' => $this->baseQuery()->where('status', 'failed')->count(),
                'pending' => $this->baseQuery()->whereIn('status', ['pending', 'processing'])->count(),
            ],
        ];
    }
} ?>

<section>
    <div class="mb-6 flex items-start gap-3">
        <flux:button variant="ghost" size="sm" icon="arrow-left" :href="route('admin.external-systems')" wire:navigate aria-label="Back" />
        <div class="flex items-start gap-3">
            <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600 dark:bg-indigo-500/10 dark:text-indigo-400">
                <flux:icon name="users" class="h-6 w-6" />
            </div>
            <div>
                <flux:heading size="xl">{{ $externalSystem->name }} · Accounts</flux:heading>
                <flux:text class="mt-1">Buyers provisioned into <span class="font-mono text-sm">{{ $externalSystem->base_url }}</span>.</flux:text>
            </div>
        </div>
    </div>

    @if (session()->has('status'))
        <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-700 dark:border-emerald-800 dark:bg-emerald-900/50 dark:text-emerald-400">
            {{ session('status') }}
        </div>
    @endif

    {{-- Filters --}}
    <div class="mb-4 flex flex-wrap gap-2">
        @foreach (['all' => 'All', 'succeeded' => 'Succeeded', 'failed' => 'Failed', 'pending' => 'Pending'] as $key => $label)
            <button
                type="button"
                wire:click="$set('statusFilter', '{{ $key }}')"
                class="inline-flex items-center gap-1.5 rounded-full border px-3 py-1.5 text-sm font-medium transition-colors {{ $statusFilter === $key ? 'border-indigo-300 bg-indigo-50 text-indigo-700 dark:border-indigo-500/40 dark:bg-indigo-500/10 dark:text-indigo-300' : 'border-zinc-200 text-zinc-600 hover:bg-zinc-50 dark:border-zinc-700 dark:text-zinc-400 dark:hover:bg-zinc-800' }}"
            >
                {{ $label }}
                <span class="tabular-nums opacity-70">{{ $counts[$key] }}</span>
            </button>
        @endforeach
    </div>

    <div class="overflow-x-auto rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700">
            <thead class="bg-zinc-50 dark:bg-zinc-800/50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-zinc-500 dark:text-zinc-400">Buyer</th>
                    <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-zinc-500 dark:text-zinc-400">Plan</th>
                    <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-zinc-500 dark:text-zinc-400">Status</th>
                    <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-zinc-500 dark:text-zinc-400">User ID</th>
                    <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-zinc-500 dark:text-zinc-400">When</th>
                    <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-zinc-500 dark:text-zinc-400">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                @forelse ($requests as $request)
                    @php
                        $customer = $request->request_payload['customer'] ?? [];
                        $plan = $request->request_payload['product']['plan'] ?? null;
                    @endphp
                    <tr wire:key="req-{{ $request->id }}" class="transition-colors hover:bg-zinc-50 dark:hover:bg-zinc-800/40">
                        <td class="px-4 py-4">
                            <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $customer['email'] ?? '—' }}</div>
                            <div class="text-xs text-zinc-400">{{ $customer['name'] ?? $request->request_payload['order_ref'] ?? '' }}</div>
                        </td>
                        <td class="whitespace-nowrap px-4 py-4">
                            @if ($plan)
                                <flux:badge color="indigo" size="sm">{{ $plan }}</flux:badge>
                            @else
                                <span class="text-sm text-zinc-400">—</span>
                            @endif
                        </td>
                        <td class="whitespace-nowrap px-4 py-4">
                            @php
                                $color = match ($request->status) {
                                    'succeeded' => ['text-emerald-600 dark:text-emerald-400', 'bg-emerald-500'],
                                    'failed' => ['text-red-600 dark:text-red-400', 'bg-red-500'],
                                    default => ['text-amber-600 dark:text-amber-400', 'bg-amber-500'],
                                };
                            @endphp
                            <span class="inline-flex items-center gap-1.5 text-sm font-medium {{ $color[0] }}">
                                <span class="h-1.5 w-1.5 rounded-full {{ $color[1] }}"></span>
                                {{ ucfirst($request->status) }}
                            </span>
                            @if ($request->status === 'failed' && $request->last_error)
                                <flux:tooltip content="{{ $request->last_error }}">
                                    <flux:icon name="information-circle" class="ml-1 inline h-3.5 w-3.5 text-zinc-400" />
                                </flux:tooltip>
                            @endif
                        </td>
                        <td class="whitespace-nowrap px-4 py-4 text-sm tabular-nums text-zinc-500 dark:text-zinc-400">
                            {{ $request->external_user_id ?? '—' }}
                        </td>
                        <td class="whitespace-nowrap px-4 py-4 text-sm text-zinc-500 dark:text-zinc-400">
                            {{ ($request->provisioned_at ?? $request->created_at)->diffForHumans() }}
                        </td>
                        <td class="whitespace-nowrap px-4 py-4 text-right">
                            <div class="flex items-center justify-end gap-1">
                                <flux:tooltip content="View">
                                    <flux:button variant="ghost" size="sm" icon="eye" aria-label="View" :href="route('admin.external-systems.account', [$externalSystem, $request])" wire:navigate />
                                </flux:tooltip>
                                @if ($request->login_url)
                                    <div x-data="{ copied: false }">
                                        <flux:tooltip content="Copy login link">
                                            <flux:button variant="ghost" size="sm" icon="link" aria-label="Copy login link"
                                                x-on:click="navigator.clipboard.writeText(@js($request->login_url)); copied = true; setTimeout(() => copied = false, 1200)"
                                                x-bind:class="copied && 'text-emerald-600 dark:text-emerald-400'" />
                                        </flux:tooltip>
                                    </div>
                                @endif
                                @unless ($request->isSucceeded())
                                    <flux:tooltip content="Retry">
                                        <flux:button variant="ghost" size="sm" icon="arrow-path" aria-label="Retry" wire:click="retry({{ $request->id }})" wire:confirm="Re-queue provisioning for this buyer?" />
                                    </flux:tooltip>
                                @endunless
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-6 py-12 text-center text-sm text-zinc-500 dark:text-zinc-400">
                            No provisioned accounts{{ $statusFilter !== 'all' ? ' with this status' : ' yet' }}.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($requests->hasPages())
        <div class="mt-4">{{ $requests->links() }}</div>
    @endif
</section>
