<?php

use App\Models\ExternalSystem;
use App\Services\ExternalProvisioning\ExternalSystemClient;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Volt\Component;

new class extends Component
{
    public string $name = '';

    public string $slug = '';

    public string $base_url = '';

    public string $provision_path = '/api/v1/provision';

    public string $auth_type = 'both';

    public string $api_key = '';

    public string $signing_secret = '';

    public int $timeout = 30;

    public bool $is_active = true;

    public string $description = '';

    public ?int $editingId = null;

    public bool $showModal = false;

    public bool $showPlansModal = false;

    public ?int $plansSystemId = null;

    public string $plansSystemName = '';

    /** @var array<int, array{slug: string, name: string}> */
    public array $plansList = [];

    public ?string $plansError = null;

    public function mount(): void
    {
        if (! auth()->user()->hasAnyRole(['admin', 'employee'])) {
            abort(403, 'Access denied');
        }
    }

    public function openCreateModal(): void
    {
        $this->resetForm();
        $this->showModal = true;
    }

    public function openEditModal(int $id): void
    {
        $system = ExternalSystem::findOrFail($id);
        $this->editingId = $system->id;
        $this->name = $system->name;
        $this->slug = $system->slug;
        $this->base_url = $system->base_url;
        $this->provision_path = $system->provision_path;
        $this->auth_type = $system->auth_type;
        $this->timeout = $system->timeout;
        $this->is_active = $system->is_active;
        $this->description = $system->description ?? '';
        // Secrets are never echoed back; leave blank to keep the stored value.
        $this->api_key = '';
        $this->signing_secret = '';
        $this->showModal = true;
    }

    public function generateSecrets(): void
    {
        $this->api_key = bin2hex(random_bytes(32));
        $this->signing_secret = bin2hex(random_bytes(32));
    }

    public function save(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'regex:/^[a-z0-9-]+$/', Rule::unique('external_systems', 'slug')->ignore($this->editingId)],
            'base_url' => ['required', 'url', 'max:255'],
            'provision_path' => ['required', 'string', 'max:255'],
            'auth_type' => ['required', Rule::in(['bearer', 'hmac', 'both'])],
            'api_key' => ['nullable', 'string', 'max:1000'],
            'signing_secret' => ['nullable', 'string', 'max:1000'],
            'timeout' => ['required', 'integer', 'min:1', 'max:300'],
            'is_active' => ['boolean'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $data = [
            'name' => $validated['name'],
            'slug' => $this->uniqueSlug($validated['slug'] ?: Str::slug($validated['name'])),
            'base_url' => $this->normalizeBaseUrl($validated['base_url']),
            'provision_path' => $validated['provision_path'],
            'auth_type' => $validated['auth_type'],
            'timeout' => $validated['timeout'],
            'is_active' => $validated['is_active'],
            'description' => $validated['description'],
        ];

        // Only overwrite secrets when a new value was entered.
        if (filled($this->api_key)) {
            $data['api_key'] = $this->api_key;
        }
        if (filled($this->signing_secret)) {
            $data['signing_secret'] = $this->signing_secret;
        }

        ExternalSystem::updateOrCreate(['id' => $this->editingId], $data);

        $this->showModal = false;
        $this->resetForm();
        session()->flash('status', 'External system saved.');
    }

    /**
     * Reduce a pasted URL to its origin (scheme://host[:port]) so a full
     * endpoint accidentally entered as the base URL — e.g.
     * "https://app.test/api/v1/provision" — does not get the provision path
     * appended again, producing a doubled path and a 404.
     */
    protected function normalizeBaseUrl(string $url): string
    {
        $parts = parse_url(trim($url));

        if (! isset($parts['scheme'], $parts['host'])) {
            return rtrim($url, '/');
        }

        return $parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');
    }

    public function testConnection(int $id): void
    {
        $system = ExternalSystem::findOrFail($id);

        try {
            $result = app(ExternalSystemClient::class)->testConnection($system);

            if ($result['success']) {
                $this->dispatch('es-test-success', name: $system->name);
            } else {
                $this->dispatch('es-test-failed', message: $result['error'] ?? ('Unreachable (HTTP '.($result['status'] ?? '???').')'));
            }
        } catch (Throwable $e) {
            $this->dispatch('es-test-failed', message: $e->getMessage());
        }
    }

    public function openPlansModal(int $id): void
    {
        $system = ExternalSystem::findOrFail($id);
        $this->plansSystemId = $system->id;
        $this->plansSystemName = $system->name;
        $this->showPlansModal = true;
        $this->loadPlansList();
    }

    /**
     * Fetch the plans/packages the external system offers via its /packages
     * endpoint. Errors are captured for display, never thrown.
     */
    public function loadPlansList(): void
    {
        $this->plansList = [];
        $this->plansError = null;

        $system = $this->plansSystemId ? ExternalSystem::find($this->plansSystemId) : null;

        if (! $system) {
            return;
        }

        try {
            foreach (app(ExternalSystemClient::class)->packages($system) as $plan) {
                if (is_array($plan) && filled($plan['slug'] ?? null)) {
                    $this->plansList[] = [
                        'slug' => (string) $plan['slug'],
                        'name' => (string) ($plan['name'] ?? $plan['slug']),
                    ];
                }
            }
        } catch (Throwable $e) {
            $this->plansError = $e->getMessage();
        }
    }

    public function toggleActive(int $id): void
    {
        $system = ExternalSystem::findOrFail($id);
        $system->update(['is_active' => ! $system->is_active]);
    }

    public function deleteSystem(int $id): void
    {
        $system = ExternalSystem::findOrFail($id);

        if ($system->provisioningRequests()->exists()) {
            session()->flash('error', 'Cannot delete a system with provisioning history. Deactivate it instead.');

            return;
        }

        $system->delete();
    }

    private function uniqueSlug(string $slug): string
    {
        $base = $slug;
        $i = 2;

        while (ExternalSystem::where('slug', $slug)->when($this->editingId, fn ($q) => $q->where('id', '!=', $this->editingId))->exists()) {
            $slug = $base.'-'.$i;
            $i++;
        }

        return $slug;
    }

    private function resetForm(): void
    {
        $this->editingId = null;
        $this->name = '';
        $this->slug = '';
        $this->base_url = '';
        $this->provision_path = '/api/v1/provision';
        $this->auth_type = 'both';
        $this->api_key = '';
        $this->signing_secret = '';
        $this->timeout = 30;
        $this->is_active = true;
        $this->description = '';
        $this->resetErrorBag();
    }

    public function with(): array
    {
        return [
            'systems' => ExternalSystem::withCount('provisioningRequests')->orderBy('name')->get(),
        ];
    }
} ?>

<section>
    {{-- Gradient hero header --}}
    <div class="mb-6 overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <div class="relative flex flex-col gap-4 bg-gradient-to-br from-indigo-500/10 via-violet-500/5 to-fuchsia-500/10 p-6 sm:flex-row sm:items-center sm:justify-between dark:from-indigo-500/15 dark:via-violet-500/10 dark:to-fuchsia-500/15">
            <div class="pointer-events-none absolute -right-8 -top-8 h-40 w-40 rounded-full bg-gradient-to-br from-indigo-400/20 to-fuchsia-400/20 blur-3xl"></div>
            <div class="flex items-start gap-4">
                <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-indigo-500 to-violet-600 text-white shadow-lg shadow-indigo-500/30">
                    <flux:icon name="server-stack" class="h-7 w-7" />
                </div>
                <div>
                    <flux:heading size="xl" class="!text-zinc-900 dark:!text-white">External Systems</flux:heading>
                    <flux:text class="mt-1 max-w-lg">Connected apps that receive a provisioning call when a paid order comes in.</flux:text>
                </div>
            </div>
            <flux:button variant="primary" icon="plus" wire:click="openCreateModal" class="relative shrink-0">Add System</flux:button>
        </div>
    </div>

    @if (session()->has('error'))
        <div class="mb-4 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700 dark:border-red-800 dark:bg-red-900/50 dark:text-red-400">
            {{ session('error') }}
        </div>
    @endif

    @if (session()->has('status'))
        <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-700 dark:border-emerald-800 dark:bg-emerald-900/50 dark:text-emerald-400">
            {{ session('status') }}
        </div>
    @endif

    @if ($systems->isEmpty())
        <div class="rounded-2xl border border-dashed border-zinc-300 bg-white px-6 py-16 text-center dark:border-zinc-700 dark:bg-zinc-900">
            <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-gradient-to-br from-indigo-500 to-violet-600 text-white shadow-lg shadow-indigo-500/30">
                <flux:icon name="server-stack" class="h-8 w-8" />
            </div>
            <flux:heading size="lg" class="mt-4">No external systems yet</flux:heading>
            <flux:text class="mx-auto mt-1 max-w-sm">Connect an app so a user account is created automatically when a paid order comes in.</flux:text>
            <div class="mt-5 flex justify-center">
                <flux:button variant="primary" icon="plus" wire:click="openCreateModal">Add your first system</flux:button>
            </div>
        </div>
    @else
        <div class="mb-5 grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div class="group relative overflow-hidden rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm transition hover:shadow-md dark:border-zinc-700 dark:bg-zinc-900">
                <div class="absolute right-0 top-0 h-20 w-20 -translate-y-6 translate-x-6 rounded-full bg-indigo-500/10 blur-2xl"></div>
                <div class="flex items-center justify-between">
                    <div class="text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">Systems</div>
                    <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br from-indigo-500 to-violet-600 text-white shadow-md shadow-indigo-500/30">
                        <flux:icon name="server-stack" class="h-4 w-4" />
                    </span>
                </div>
                <div class="mt-3 text-3xl font-bold tabular-nums text-zinc-900 dark:text-zinc-100">{{ $systems->count() }}</div>
            </div>
            <div class="group relative overflow-hidden rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm transition hover:shadow-md dark:border-zinc-700 dark:bg-zinc-900">
                <div class="absolute right-0 top-0 h-20 w-20 -translate-y-6 translate-x-6 rounded-full bg-emerald-500/10 blur-2xl"></div>
                <div class="flex items-center justify-between">
                    <div class="text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">Active</div>
                    <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br from-emerald-500 to-teal-600 text-white shadow-md shadow-emerald-500/30">
                        <flux:icon name="check-circle" class="h-4 w-4" />
                    </span>
                </div>
                <div class="mt-3 flex items-center gap-2 text-3xl font-bold tabular-nums text-zinc-900 dark:text-zinc-100">
                    <span class="relative flex h-2.5 w-2.5">
                        <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75"></span>
                        <span class="relative inline-flex h-2.5 w-2.5 rounded-full bg-emerald-500"></span>
                    </span>
                    {{ $systems->where('is_active', true)->count() }}
                </div>
            </div>
            <div class="group relative overflow-hidden rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm transition hover:shadow-md dark:border-zinc-700 dark:bg-zinc-900">
                <div class="absolute right-0 top-0 h-20 w-20 -translate-y-6 translate-x-6 rounded-full bg-fuchsia-500/10 blur-2xl"></div>
                <div class="flex items-center justify-between">
                    <div class="text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">Provisioning calls</div>
                    <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br from-fuchsia-500 to-pink-600 text-white shadow-md shadow-fuchsia-500/30">
                        <flux:icon name="bolt" class="h-4 w-4" />
                    </span>
                </div>
                <div class="mt-3 text-3xl font-bold tabular-nums text-zinc-900 dark:text-zinc-100">{{ $systems->sum('provisioning_requests_count') }}</div>
            </div>
        </div>

        <div class="overflow-x-auto rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700">
                <thead class="bg-gradient-to-r from-indigo-50 via-violet-50 to-fuchsia-50 dark:from-indigo-500/10 dark:via-violet-500/10 dark:to-fuchsia-500/10">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-zinc-500 dark:text-zinc-400">Name</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-zinc-500 dark:text-zinc-400">Endpoint</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-zinc-500 dark:text-zinc-400">Auth</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-zinc-500 dark:text-zinc-400">Requests</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-zinc-500 dark:text-zinc-400">Status</th>
                        <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-zinc-500 dark:text-zinc-400">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @foreach ($systems as $system)
                        <tr wire:key="system-{{ $system->id }}" class="transition-colors hover:bg-zinc-50 dark:hover:bg-zinc-800/40">
                            <td class="px-4 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-gradient-to-br {{ $system->is_active ? 'from-indigo-500 to-violet-600 text-white shadow-sm shadow-indigo-500/30' : 'from-zinc-200 to-zinc-300 text-zinc-500 dark:from-zinc-700 dark:to-zinc-800 dark:text-zinc-400' }}">
                                        <flux:icon name="server-stack" class="h-5 w-5" />
                                    </div>
                                    <div class="min-w-0">
                                        <div class="truncate text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $system->name }}</div>
                                        <div class="truncate text-xs text-zinc-400">{{ $system->slug }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-4">
                                <div class="max-w-[220px] truncate font-mono text-xs text-zinc-700 dark:text-zinc-300" title="{{ $system->base_url }}">{{ $system->base_url }}</div>
                                <div class="max-w-[220px] truncate font-mono text-xs text-zinc-400" title="{{ $system->provision_path }}">{{ $system->provision_path }}</div>
                            </td>
                            <td class="whitespace-nowrap px-4 py-4">
                                <flux:badge color="{{ $system->auth_type === 'both' ? 'indigo' : 'zinc' }}" size="sm">{{ strtoupper($system->auth_type) }}</flux:badge>
                            </td>
                            <td class="whitespace-nowrap px-4 py-4">
                                <a href="{{ route('admin.external-systems.accounts', $system) }}" wire:navigate class="inline-flex items-center rounded-full bg-zinc-100 px-2 py-0.5 text-xs font-medium tabular-nums text-zinc-600 transition-colors hover:bg-zinc-200 dark:bg-zinc-800 dark:text-zinc-300 dark:hover:bg-zinc-700">{{ $system->provisioning_requests_count }}</a>
                            </td>
                            <td class="whitespace-nowrap px-4 py-4">
                                <span class="inline-flex items-center gap-1.5 text-sm font-medium {{ $system->is_active ? 'text-emerald-600 dark:text-emerald-400' : 'text-zinc-400 dark:text-zinc-500' }}">
                                    <span class="h-1.5 w-1.5 rounded-full {{ $system->is_active ? 'bg-emerald-500' : 'bg-zinc-400' }}"></span>
                                    {{ $system->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="whitespace-nowrap px-4 py-4 text-right">
                                <div class="flex items-center justify-end gap-1">
                                    <flux:tooltip content="Accounts">
                                        <flux:button variant="ghost" size="sm" icon="users" aria-label="Accounts" :href="route('admin.external-systems.accounts', $system)" wire:navigate />
                                    </flux:tooltip>
                                    <flux:tooltip content="Plans">
                                        <flux:button variant="ghost" size="sm" icon="rectangle-stack" aria-label="Plans" wire:click="openPlansModal({{ $system->id }})" wire:loading.attr="disabled" wire:target="openPlansModal({{ $system->id }})" />
                                    </flux:tooltip>
                                    <flux:tooltip content="Test connection">
                                        <flux:button variant="ghost" size="sm" icon="signal" aria-label="Test connection" wire:click="testConnection({{ $system->id }})" wire:loading.attr="disabled" wire:target="testConnection({{ $system->id }})" />
                                    </flux:tooltip>
                                    <flux:tooltip content="Edit">
                                        <flux:button variant="ghost" size="sm" icon="pencil-square" aria-label="Edit" wire:click="openEditModal({{ $system->id }})" />
                                    </flux:tooltip>
                                    <flux:tooltip content="{{ $system->is_active ? 'Deactivate' : 'Activate' }}">
                                        <flux:button variant="ghost" size="sm" icon="{{ $system->is_active ? 'eye-slash' : 'eye' }}" aria-label="{{ $system->is_active ? 'Deactivate' : 'Activate' }}" wire:click="toggleActive({{ $system->id }})" />
                                    </flux:tooltip>
                                    <flux:tooltip content="Delete">
                                        <flux:button variant="ghost" size="sm" icon="trash" aria-label="Delete" class="text-red-500 hover:text-red-600 dark:text-red-400" wire:click="deleteSystem({{ $system->id }})" wire:confirm="Delete this external system?" />
                                    </flux:tooltip>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <flux:modal wire:model="showModal" class="max-w-xl">
        <div class="p-6">
            <div class="mb-5 flex items-center gap-3">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600 dark:bg-indigo-500/10 dark:text-indigo-400">
                    <flux:icon name="server-stack" class="h-5 w-5" />
                </div>
                <div>
                    <flux:heading size="lg">{{ $editingId ? 'Edit System' : 'Add System' }}</flux:heading>
                    <flux:text class="text-sm">Configure the connection and how requests are authenticated.</flux:text>
                </div>
            </div>

            <form wire:submit="save" class="space-y-5" x-data="{ advanced: false }">
                {{-- Essentials --}}
                <flux:field>
                    <flux:label>Name</flux:label>
                    <flux:input wire:model="name" placeholder="e.g. Membership Portal" />
                    <flux:error name="name" />
                </flux:field>

                <flux:field>
                    <flux:label>Base URL</flux:label>
                    <flux:input wire:model="base_url" placeholder="https://system.example.com" />
                    <flux:description>Use <span class="font-mono">https://</span> for TLS-secured hosts.</flux:description>
                    <flux:error name="base_url" />
                </flux:field>

                {{-- Secrets --}}
                <div class="space-y-3 rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <flux:heading size="sm">Authentication</flux:heading>
                            <span class="inline-flex items-center gap-1 text-xs text-zinc-500 dark:text-zinc-400">
                                <flux:icon name="lock-closed" class="h-3.5 w-3.5" /> Encrypted at rest
                            </span>
                        </div>
                        <flux:button type="button" size="sm" variant="ghost" icon="sparkles" wire:click="generateSecrets">Generate</flux:button>
                    </div>

                    <flux:field>
                        <flux:label>API Key (Bearer)</flux:label>
                        <flux:input type="password" wire:model="api_key" placeholder="{{ $editingId ? '•••••• (leave blank to keep current)' : 'Click Generate, or paste a secret' }}" />
                        <flux:error name="api_key" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Signing Secret (HMAC)</flux:label>
                        <flux:input type="password" wire:model="signing_secret" placeholder="{{ $editingId ? '•••••• (leave blank to keep current)' : 'Click Generate, or paste a secret' }}" />
                        <flux:error name="signing_secret" />
                    </flux:field>

                    @if ($api_key && $signing_secret)
                        <div class="overflow-hidden rounded-lg border border-indigo-200 bg-indigo-50/60 dark:border-indigo-500/30 dark:bg-indigo-500/10"
                             x-data="{ copied: false, env: @js("PROVISIONING_API_KEY={$api_key}\nPROVISIONING_SIGNING_SECRET={$signing_secret}") }">
                            <div class="flex items-center justify-between border-b border-indigo-200 px-3 py-2 dark:border-indigo-500/30">
                                <span class="text-xs font-medium text-indigo-700 dark:text-indigo-300">Paste into the external system's .env</span>
                                <button type="button" class="inline-flex items-center gap-1 text-xs font-medium text-indigo-700 hover:text-indigo-900 dark:text-indigo-300 dark:hover:text-indigo-200"
                                        x-on:click="navigator.clipboard.writeText(env); copied = true; setTimeout(() => copied = false, 1500)">
                                    <span x-show="!copied">Copy</span>
                                    <span x-show="copied" x-cloak>Copied ✓</span>
                                </button>
                            </div>
                            <pre class="overflow-x-auto px-3 py-2 font-mono text-xs leading-relaxed text-zinc-700 dark:text-zinc-300">PROVISIONING_API_KEY={{ $api_key }}
PROVISIONING_SIGNING_SECRET={{ $signing_secret }}</pre>
                        </div>
                    @endif
                </div>

                {{-- Advanced (defaulted fields) --}}
                <div>
                    <button type="button" x-on:click="advanced = !advanced"
                            class="flex items-center gap-1.5 text-sm font-medium text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200">
                        <flux:icon name="chevron-right" class="h-4 w-4 transition-transform" x-bind:class="advanced && 'rotate-90'" />
                        Advanced
                    </button>

                    <div x-show="advanced" x-collapse x-cloak class="mt-4 space-y-4">
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <flux:field>
                                <flux:label>Slug</flux:label>
                                <flux:input wire:model="slug" placeholder="auto from name" />
                                <flux:error name="slug" />
                            </flux:field>

                            <flux:field>
                                <flux:label>Timeout (seconds)</flux:label>
                                <flux:input type="number" wire:model="timeout" min="1" max="300" />
                                <flux:error name="timeout" />
                            </flux:field>
                        </div>

                        <flux:field>
                            <flux:label>Provision Path</flux:label>
                            <flux:input wire:model="provision_path" placeholder="/api/v1/provision" />
                            <flux:description>Appended to the base URL. Receives the order &amp; customer payload.</flux:description>
                            <flux:error name="provision_path" />
                        </flux:field>

                        <flux:field>
                            <flux:label>Auth Type</flux:label>
                            <flux:select wire:model="auth_type">
                                <flux:select.option value="both">Bearer token + HMAC signature</flux:select.option>
                                <flux:select.option value="bearer">Bearer token only</flux:select.option>
                                <flux:select.option value="hmac">HMAC signature only</flux:select.option>
                            </flux:select>
                            <flux:error name="auth_type" />
                        </flux:field>

                        <flux:field>
                            <flux:label>Description</flux:label>
                            <flux:textarea wire:model="description" rows="2" placeholder="Optional notes" />
                            <flux:error name="description" />
                        </flux:field>
                    </div>
                </div>

                <flux:separator />

                <div class="flex items-center justify-between">
                    <flux:checkbox wire:model="is_active" label="Active" />
                    <div class="flex gap-2">
                        <flux:button type="button" variant="ghost" wire:click="$set('showModal', false)">Cancel</flux:button>
                        <flux:button type="submit" variant="primary">{{ $editingId ? 'Update' : 'Create' }}</flux:button>
                    </div>
                </div>
            </form>
        </div>
    </flux:modal>

    {{-- Plans modal --}}
    <flux:modal wire:model="showPlansModal" class="max-w-md">
        <div class="p-6">
            <div class="mb-4 flex items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600 dark:bg-indigo-500/10 dark:text-indigo-400">
                        <flux:icon name="rectangle-stack" class="h-5 w-5" />
                    </div>
                    <div>
                        <flux:heading size="lg">Plans</flux:heading>
                        <flux:text class="text-sm">{{ $plansSystemName }}</flux:text>
                    </div>
                </div>
                <flux:button size="sm" variant="ghost" icon="arrow-path" wire:click="loadPlansList" wire:loading.attr="disabled" wire:target="loadPlansList, openPlansModal">Refresh</flux:button>
            </div>

            <div wire:loading.flex wire:target="loadPlansList, openPlansModal" class="items-center gap-2 py-6 text-sm text-zinc-500">
                <flux:icon name="arrow-path" class="h-4 w-4 animate-spin" /> Loading plans…
            </div>

            <div wire:loading.remove wire:target="loadPlansList, openPlansModal">
                @if ($plansError)
                    <flux:callout variant="danger" icon="x-circle" class="text-sm">Couldn't load plans: {{ $plansError }}</flux:callout>
                @elseif (empty($plansList))
                    <div class="rounded-xl border border-dashed border-zinc-300 p-6 text-center dark:border-zinc-700">
                        <flux:text class="text-sm text-zinc-500">This system didn't return any plans — it may not expose a <span class="font-mono">/packages</span> endpoint.</flux:text>
                    </div>
                @else
                    <div class="divide-y divide-zinc-100 rounded-xl border border-zinc-200 dark:divide-zinc-800 dark:border-zinc-700">
                        @foreach ($plansList as $plan)
                            <div class="flex items-center justify-between gap-3 px-4 py-3" wire:key="plan-{{ $plan['slug'] }}">
                                <div class="min-w-0">
                                    <div class="truncate text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $plan['name'] }}</div>
                                    <div class="truncate font-mono text-xs text-zinc-400">{{ $plan['slug'] }}</div>
                                </div>
                                <div x-data="{ copied: false }">
                                    <flux:button size="sm" variant="ghost" type="button"
                                                 x-on:click="navigator.clipboard.writeText(@js($plan['slug'])); copied = true; setTimeout(() => copied = false, 1200)">
                                        <span x-show="!copied">Copy slug</span>
                                        <span x-show="copied" class="text-emerald-600">Copied</span>
                                    </flux:button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <flux:text class="mt-3 text-xs text-zinc-500">Use a plan's <span class="font-mono">slug</span> when marking a product or package for this system.</flux:text>
                @endif
            </div>

            <div class="mt-5 flex justify-end">
                <flux:button variant="primary" wire:click="$set('showPlansModal', false)">Done</flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- Test-connection toast feedback --}}
    <div x-data="{ show: false, name: '' }"
         x-on:es-test-success.window="show = true; name = $event.detail.name; setTimeout(() => show = false, 3000)"
         x-show="show" x-transition x-cloak class="fixed right-4 top-4 z-50">
        <flux:badge color="emerald" size="lg">
            <div class="flex items-center">
                <flux:icon name="check-circle" class="mr-2 h-4 w-4" />
                <span x-text="name + ' reachable'"></span>
            </div>
        </flux:badge>
    </div>
    <div x-data="{ show: false, message: '' }"
         x-on:es-test-failed.window="show = true; message = $event.detail.message; setTimeout(() => show = false, 6000)"
         x-show="show" x-transition x-cloak class="fixed right-4 top-4 z-50">
        <flux:badge color="red" size="lg">
            <div class="flex items-center">
                <flux:icon name="x-circle" class="mr-2 h-4 w-4" />
                <span x-text="message"></span>
            </div>
        </flux:badge>
    </div>
</section>
