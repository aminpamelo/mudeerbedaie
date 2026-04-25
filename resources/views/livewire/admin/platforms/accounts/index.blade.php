<?php

use App\Models\Platform;
use App\Models\PlatformAccount;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    public Platform $platform;

    public $search = '';

    public $statusFilter = '';

    public $sortBy = 'created_at';

    public $sortDirection = 'desc';

    public function mount(Platform $platform)
    {
        $this->platform = $platform;
        $this->resetPage();
    }

    /**
     * Check if an account has active OAuth credentials.
     */
    public function hasOAuthCredentials(PlatformAccount $account): bool
    {
        return $account->credentials()
            ->where('credential_type', 'oauth_token')
            ->where('is_active', true)
            ->exists();
    }

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function updatedStatusFilter()
    {
        $this->resetPage();
    }

    public function setStatusFilter(string $value): void
    {
        $this->statusFilter = $value;
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->search = '';
        $this->statusFilter = '';
        $this->resetPage();
    }

    public function sortBy($field)
    {
        if ($this->sortBy === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $field;
            $this->sortDirection = 'asc';
        }
        $this->resetPage();
    }

    public function toggleStatus($accountId)
    {
        $account = PlatformAccount::findOrFail($accountId);
        $account->update(['is_active' => ! $account->is_active]);

        $this->dispatch('account-updated', [
            'message' => "Account '{$account->name}' has been ".($account->is_active ? 'activated' : 'deactivated'),
        ]);
    }

    public function deleteAccount($accountId)
    {
        $account = PlatformAccount::findOrFail($accountId);
        $accountName = $account->name;
        $account->delete();

        $this->dispatch('account-deleted', [
            'message' => "Account '{$accountName}' has been deleted successfully",
        ]);
    }

    public function with()
    {
        $query = $this->platform->accounts()->with(['liveHosts', 'credentials']);

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('name', 'like', "%{$this->search}%")
                    ->orWhere('account_id', 'like', "%{$this->search}%")
                    ->orWhere('shop_id', 'like', "%{$this->search}%")
                    ->orWhere('business_manager_id', 'like', "%{$this->search}%");
            });
        }

        if ($this->statusFilter === 'active') {
            $query->where('is_active', true);
        } elseif ($this->statusFilter === 'inactive') {
            $query->where('is_active', false);
        }

        $accounts = $query->orderBy($this->sortBy, $this->sortDirection)
            ->paginate(12);

        $totalAccounts = $this->platform->accounts()->count();
        $activeAccounts = $this->platform->accounts()->where('is_active', true)->count();
        $inactiveAccounts = $totalAccounts - $activeAccounts;
        $syncedAccounts = $this->platform->accounts()->whereNotNull('last_sync_at')->count();

        return [
            'accounts' => $accounts,
            'totalAccounts' => $totalAccounts,
            'activeAccounts' => $activeAccounts,
            'inactiveAccounts' => $inactiveAccounts,
            'syncedAccounts' => $syncedAccounts,
            'syncPercent' => $totalAccounts > 0 ? round(($syncedAccounts / $totalAccounts) * 100) : 0,
            'activePercent' => $totalAccounts > 0 ? round(($activeAccounts / $totalAccounts) * 100) : 0,
        ];
    }
}; ?>

<div>
    {{-- Page-scoped fonts --}}
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=manrope:400,500,600,700,800&family=jetbrains-mono:400,500&display=swap" rel="stylesheet">

    <style>
        .pf-page,
        .pf-page * { font-family: 'Manrope', ui-sans-serif, system-ui, -apple-system, sans-serif; }
        .pf-page .num,
        .pf-page .mono { font-family: 'JetBrains Mono', ui-monospace, SFMono-Regular, Menlo, monospace; font-variant-numeric: tabular-nums; font-feature-settings: "tnum"; letter-spacing: -0.01em; }

        .pf-page { color-scheme: light dark; }

        .pf-surface { background: #ffffff; border: 1px solid rgb(228 228 231 / 1); border-radius: 14px; transition: all .2s cubic-bezier(.2,.7,.2,1); }
        .dark .pf-surface { background: rgb(9 9 11 / 1); border-color: rgb(39 39 42 / 1); }

        .pf-card { position: relative; isolation: isolate; transition: all .25s cubic-bezier(.2,.7,.2,1); }
        .pf-card:hover { transform: translateY(-1px); border-color: rgb(212 212 216); box-shadow: 0 1px 2px rgb(0 0 0 / 0.04), 0 8px 24px -8px rgb(0 0 0 / 0.08); }
        .dark .pf-card:hover { border-color: rgb(63 63 70); box-shadow: 0 1px 2px rgb(0 0 0 / 0.5), 0 8px 24px -4px rgb(0 0 0 / 0.6); }

        /* Pill tabs */
        .pill-tab {
            display: inline-flex; align-items: center; gap: 6px;
            height: 32px; padding: 0 12px;
            border-radius: 8px;
            font-size: 13px; font-weight: 500; line-height: 1;
            color: rgb(82 82 91); background: transparent;
            transition: all .15s ease; cursor: pointer; border: none;
        }
        .dark .pill-tab { color: rgb(161 161 170); }
        .pill-tab:hover { color: rgb(24 24 27); background: rgb(244 244 245); }
        .dark .pill-tab:hover { color: rgb(244 244 245); background: rgb(39 39 42); }
        .pill-tab[aria-pressed="true"] { color: rgb(24 24 27); background: #ffffff; box-shadow: 0 1px 2px rgb(0 0 0 / .08), 0 0 0 1px rgb(228 228 231); }
        .dark .pill-tab[aria-pressed="true"] { color: #fff; background: rgb(39 39 42); box-shadow: 0 0 0 1px rgb(63 63 70); }
        .pill-tab .count {
            font-family: 'JetBrains Mono', monospace; font-size: 11px; font-weight: 500;
            padding: 2px 6px; border-radius: 4px; background: rgb(244 244 245); color: rgb(82 82 91);
            font-variant-numeric: tabular-nums;
        }
        .dark .pill-tab .count { background: rgb(39 39 42); color: rgb(161 161 170); }
        .pill-tab[aria-pressed="true"] .count { background: rgb(24 24 27); color: #fff; }
        .dark .pill-tab[aria-pressed="true"] .count { background: rgb(82 82 91); color: #fff; }

        /* Status pill */
        .stat-pill {
            display: inline-flex; align-items: center; gap: 6px;
            height: 22px; padding: 0 8px; border-radius: 999px;
            font-size: 11px; font-weight: 600; letter-spacing: -0.005em;
            border: 1px solid transparent;
        }
        .stat-pill.active   { background: rgb(220 252 231); color: rgb(21 128 61);  border-color: rgb(187 247 208); }
        .stat-pill.idle     { background: rgb(244 244 245); color: rgb(82 82 91);  border-color: rgb(228 228 231); }
        .stat-pill.warn     { background: rgb(254 243 199); color: rgb(146 64 14); border-color: rgb(253 230 138); }
        .stat-pill.info     { background: rgb(219 234 254); color: rgb(29 78 216); border-color: rgb(191 219 254); }
        .dark .stat-pill.active { background: rgb(20 83 45 / .25); color: rgb(134 239 172); border-color: rgb(22 101 52 / .5); }
        .dark .stat-pill.idle   { background: rgb(39 39 42); color: rgb(161 161 170); border-color: rgb(63 63 70); }
        .dark .stat-pill.warn   { background: rgb(120 53 15 / .25); color: rgb(252 211 77); border-color: rgb(146 64 14 / .5); }
        .dark .stat-pill.info   { background: rgb(30 58 138 / .25); color: rgb(147 197 253); border-color: rgb(30 64 175 / .5); }
        .stat-pill .ring { width: 6px; height: 6px; border-radius: 999px; background: currentColor; box-shadow: 0 0 0 2.5px currentColor; opacity: .9; }
        .stat-pill .ring { box-shadow: 0 0 0 2.5px rgba(currentColor,.18); }

        /* Stat cards */
        .pf-stat { padding: 18px 20px; }
        .pf-stat .label { font-size: 12px; font-weight: 500; color: rgb(113 113 122); }
        .dark .pf-stat .label { color: rgb(161 161 170); }
        .pf-stat .num { font-size: 30px; font-weight: 600; color: rgb(9 9 11); line-height: 1; letter-spacing: -0.03em; }
        .dark .pf-stat .num { color: #fff; }
        .pf-stat .meter { height: 4px; border-radius: 999px; background: rgb(244 244 245); overflow: hidden; }
        .dark .pf-stat .meter { background: rgb(39 39 42); }
        .pf-stat .meter > i { display: block; height: 100%; border-radius: 999px; }
        .pf-stat .delta { font-size: 11px; font-weight: 500; }

        /* Search input */
        .pf-search {
            width: 100%; height: 40px; padding: 0 14px 0 40px;
            background: #fff; border: 1px solid rgb(228 228 231); border-radius: 10px;
            font-size: 13.5px; color: rgb(24 24 27);
            transition: all .15s ease;
        }
        .dark .pf-search { background: rgb(9 9 11); border-color: rgb(39 39 42); color: #fff; }
        .pf-search::placeholder { color: rgb(161 161 170); }
        .pf-search:focus { outline: none; border-color: rgb(82 82 91); box-shadow: 0 0 0 4px rgb(244 244 245); }
        .dark .pf-search:focus { border-color: rgb(113 113 122); box-shadow: 0 0 0 4px rgb(39 39 42 / .8); }

        /* Buttons */
        .btn {
            display: inline-flex; align-items: center; justify-content: center; gap: 6px;
            height: 36px; padding: 0 14px;
            border-radius: 9px;
            font-size: 13px; font-weight: 500; line-height: 1;
            transition: all .15s ease; cursor: pointer; border: none; text-decoration: none;
        }
        .btn-primary { background: rgb(9 9 11); color: #fff; }
        .btn-primary:hover { background: rgb(39 39 42); }
        .dark .btn-primary { background: #fff; color: rgb(9 9 11); }
        .dark .btn-primary:hover { background: rgb(228 228 231); }
        .btn-secondary { background: #fff; color: rgb(24 24 27); border: 1px solid rgb(228 228 231); }
        .btn-secondary:hover { border-color: rgb(161 161 170); }
        .dark .btn-secondary { background: rgb(9 9 11); color: #fff; border-color: rgb(39 39 42); }
        .dark .btn-secondary:hover { border-color: rgb(82 82 91); }
        .btn-ghost { background: transparent; color: rgb(82 82 91); }
        .btn-ghost:hover { background: rgb(244 244 245); color: rgb(24 24 27); }
        .dark .btn-ghost { color: rgb(161 161 170); }
        .dark .btn-ghost:hover { background: rgb(39 39 42); color: #fff; }
        .btn-danger-ghost { background: transparent; color: rgb(220 38 38); }
        .btn-danger-ghost:hover { background: rgb(254 226 226); color: rgb(185 28 28); }
        .dark .btn-danger-ghost { color: rgb(248 113 113); }
        .dark .btn-danger-ghost:hover { background: rgb(127 29 29 / .4); color: rgb(252 165 165); }
        .btn-sm { height: 30px; padding: 0 11px; font-size: 12.5px; border-radius: 7px; }
        .btn-icon { width: 32px; padding: 0; }

        /* Brand button (TikTok-style) */
        .btn-brand {
            background: linear-gradient(180deg, rgb(24 24 27), rgb(9 9 11));
            color: #fff;
            box-shadow: inset 0 1px 0 rgb(255 255 255 / .1);
        }
        .btn-brand:hover { background: linear-gradient(180deg, rgb(39 39 42), rgb(24 24 27)); }

        /* Live host chip */
        .host-chip {
            display: inline-flex; align-items: center; gap: 6px;
            height: 26px; padding: 0 10px 0 4px;
            border-radius: 999px;
            background: rgb(244 244 245); border: 1px solid rgb(228 228 231);
            font-size: 12px; font-weight: 500; color: rgb(63 63 70);
        }
        .dark .host-chip { background: rgb(39 39 42); border-color: rgb(63 63 70); color: rgb(228 228 231); }
        .host-chip .avatar {
            width: 18px; height: 18px; border-radius: 999px;
            display: grid; place-items: center;
            background: linear-gradient(135deg, rgb(99 102 241), rgb(168 85 247));
            color: #fff; font-size: 9px; font-weight: 700;
        }

        /* ID row */
        .id-row {
            display: flex; align-items: center; justify-content: space-between;
            padding: 10px 12px; border-radius: 8px;
            background: rgb(250 250 250);
            border: 1px solid rgb(244 244 245);
        }
        .dark .id-row { background: rgb(24 24 27); border-color: rgb(39 39 42); }
        .id-row .id-label { font-size: 11px; font-weight: 500; text-transform: uppercase; letter-spacing: .06em; color: rgb(113 113 122); }
        .id-row .id-value { font-family: 'JetBrains Mono', monospace; font-size: 12px; color: rgb(24 24 27); }
        .dark .id-row .id-value { color: rgb(244 244 245); }

        /* Sync indicator */
        .sync-line { display: inline-flex; align-items: center; gap: 6px; font-size: 12px; }
        .sync-line.ok   { color: rgb(21 128 61); }
        .sync-line.warn { color: rgb(146 64 14); }
        .dark .sync-line.ok   { color: rgb(134 239 172); }
        .dark .sync-line.warn { color: rgb(252 211 77); }

        /* Reveal */
        .reveal { opacity: 0; transform: translateY(6px); animation: reveal .5s cubic-bezier(.2,.7,.2,1) forwards; }
        .reveal.d1 { animation-delay: .04s; } .reveal.d2 { animation-delay: .10s; } .reveal.d3 { animation-delay: .16s; } .reveal.d4 { animation-delay: .22s; } .reveal.d5 { animation-delay: .28s; }
        @keyframes reveal { to { opacity: 1; transform: none; } }

        /* Logo wrapper */
        .pf-logo { width: 44px; height: 44px; border-radius: 11px; display: grid; place-items: center; color: #fff; font-weight: 600; font-size: 16px; letter-spacing: -0.02em; flex-shrink: 0; box-shadow: inset 0 0 0 1px rgb(0 0 0 / .04); position: relative; overflow: hidden; }
        .pf-logo::after { content: ''; position: absolute; inset: 0; background: linear-gradient(180deg, rgba(255,255,255,.18), transparent 50%); pointer-events: none; }

        /* Platform banner accent stripe */
        .pf-banner { position: relative; overflow: hidden; }
        .pf-banner::before {
            content: ''; position: absolute; left: 0; top: 0; bottom: 0; width: 4px;
            background: var(--accent, #71717a);
        }

        /* Back link */
        .back-link {
            display: inline-flex; align-items: center; gap: 4px;
            font-size: 13px; font-weight: 500;
            color: rgb(113 113 122); text-decoration: none;
            transition: color .15s ease;
        }
        .back-link:hover { color: rgb(24 24 27); }
        .dark .back-link { color: rgb(161 161 170); }
        .dark .back-link:hover { color: #fff; }
    </style>

    @php
        $accent = $platform->color_primary ?: '#52525b';
        $hasApi = $platform->settings['api_available'] ?? false;
    @endphp

    <div class="pf-page">

        {{-- ─────────────── Back link ─────────────── --}}
        <div class="reveal d1 mb-5">
            <a href="{{ route('platforms.index') }}" wire:navigate class="back-link">
                <flux:icon name="chevron-left" class="w-3.5 h-3.5" />
                Back to Platforms
            </a>
        </div>

        {{-- ─────────────── Hero ─────────────── --}}
        <header class="reveal d1 mb-6 flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
            <div class="max-w-2xl">
                <div class="inline-flex items-center gap-2 mb-3 text-[11px] font-semibold uppercase tracking-[0.12em] text-zinc-500 dark:text-zinc-400">
                    <span class="relative flex h-1.5 w-1.5">
                        <span class="absolute inline-flex h-full w-full rounded-full opacity-75 animate-ping" style="background: {{ $accent }};"></span>
                        <span class="relative inline-flex h-1.5 w-1.5 rounded-full" style="background: {{ $accent }};"></span>
                    </span>
                    Account Centre · {{ $platform->display_name }}
                </div>
                <h1 class="text-[34px] sm:text-[40px] font-semibold tracking-[-0.035em] leading-[1.05] text-zinc-900 dark:text-white">
                    Accounts
                </h1>
                <p class="mt-2 text-[14px] text-zinc-500 dark:text-zinc-400 leading-relaxed">
                    Manage seller accounts, shop IDs and business manager connections for {{ $platform->display_name }}.
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <button type="button" class="btn btn-ghost">
                    <flux:icon name="arrow-down-tray" class="w-4 h-4" />
                    Export
                </button>
                <a href="{{ route('platforms.accounts.create', $platform) }}" wire:navigate class="btn btn-secondary">
                    <flux:icon name="plus" class="w-4 h-4" />
                    Add Manually
                </a>
                @if($platform->slug === 'tiktok-shop')
                    <a href="{{ route('tiktok.connect') }}" class="btn btn-brand">
                        <flux:icon name="link" class="w-4 h-4" />
                        Connect TikTok Shop
                    </a>
                @endif
            </div>
        </header>

        {{-- ─────────────── Platform info banner ─────────────── --}}
        <div class="reveal d2 mb-6 pf-surface pf-banner pl-5 pr-4 py-4 flex items-center gap-4" style="--accent: {{ $accent }};">
            @if($platform->logo_url)
                <img src="{{ $platform->logo_url }}" alt="" class="pf-logo object-cover" style="background:{{ $accent }};">
            @else
                <div class="pf-logo" style="background:{{ $accent }};">
                    {{ strtoupper(substr($platform->display_name ?? $platform->name, 0, 2)) }}
                </div>
            @endif
            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 flex-wrap">
                    <h2 class="text-[15px] font-semibold tracking-[-0.01em] text-zinc-900 dark:text-white">{{ $platform->display_name }}</h2>
                    <span class="stat-pill {{ $platform->is_active ? 'active' : 'idle' }}">
                        <span class="ring"></span>{{ $platform->is_active ? 'Active' : 'Idle' }}
                    </span>
                    <span class="stat-pill {{ $hasApi ? 'info' : 'warn' }}">
                        @if($hasApi)
                            <flux:icon name="bolt" class="w-3 h-3" /> API Available
                        @else
                            <flux:icon name="cursor-arrow-rays" class="w-3 h-3" /> Manual Only
                        @endif
                    </span>
                </div>
                <div class="mt-1 text-[12.5px] text-zinc-500 dark:text-zinc-400 capitalize">
                    {{ str_replace('_', ' ', $platform->type) }} platform
                    @if($platform->website_url)
                        <span class="text-zinc-300 dark:text-zinc-600 mx-1.5">·</span>
                        <a href="{{ $platform->website_url }}" target="_blank" rel="noopener" class="hover:text-zinc-700 dark:hover:text-zinc-200 inline-flex items-center gap-0.5">
                            {{ parse_url($platform->website_url, PHP_URL_HOST) }}
                            <flux:icon name="arrow-top-right-on-square" class="w-3 h-3" />
                        </a>
                    @endif
                </div>
            </div>
            <a href="{{ route('platforms.edit', $platform) }}" wire:navigate class="btn btn-ghost btn-sm">
                <flux:icon name="cog-6-tooth" class="w-4 h-4" /> Settings
            </a>
        </div>

        {{-- ─────────────── Stats ─────────────── --}}
        <section class="reveal d2 mb-6 grid grid-cols-2 lg:grid-cols-4 gap-3">
            <div class="pf-surface pf-stat">
                <div class="flex items-center justify-between mb-3">
                    <span class="label">Total accounts</span>
                    <flux:icon name="user-group" class="w-4 h-4 text-zinc-400" />
                </div>
                <div class="num">{{ $totalAccounts }}</div>
                <div class="mt-3 text-[11px] text-zinc-500 dark:text-zinc-400">All seller connections</div>
            </div>

            <div class="pf-surface pf-stat">
                <div class="flex items-center justify-between mb-3">
                    <span class="label">Active</span>
                    <span class="delta text-emerald-600 dark:text-emerald-400 num">{{ $activePercent }}%</span>
                </div>
                <div class="flex items-baseline gap-1.5">
                    <span class="num">{{ $activeAccounts }}</span>
                    <span class="num text-base text-zinc-400 dark:text-zinc-500" style="font-size:14px;font-weight:500;">/ {{ $totalAccounts }}</span>
                </div>
                <div class="meter mt-3"><i style="width: {{ $activePercent }}%; background: linear-gradient(90deg, rgb(16 185 129), rgb(52 211 153));"></i></div>
            </div>

            <div class="pf-surface pf-stat">
                <div class="flex items-center justify-between mb-3">
                    <span class="label">Synced</span>
                    <span class="delta text-zinc-500 dark:text-zinc-400 num">{{ $syncPercent }}%</span>
                </div>
                <div class="flex items-baseline gap-1.5">
                    <span class="num">{{ $syncedAccounts }}</span>
                    <span class="text-[12px] text-zinc-400 dark:text-zinc-500 ml-1">accounts</span>
                </div>
                <div class="meter mt-3"><i style="width: {{ $syncPercent }}%; background: linear-gradient(90deg, rgb(24 24 27), rgb(82 82 91));"></i></div>
            </div>

            <div class="pf-surface pf-stat">
                <div class="flex items-center justify-between mb-3">
                    <span class="label">Idle</span>
                    <flux:icon name="exclamation-circle" class="w-4 h-4 text-amber-500" />
                </div>
                <div class="num">{{ $inactiveAccounts }}</div>
                <div class="mt-3 text-[11px] text-zinc-500 dark:text-zinc-400">Inactive or disabled</div>
            </div>
        </section>

        {{-- ─────────────── Filter rail ─────────────── --}}
        <section class="reveal d3 mb-5">
            <div class="flex flex-wrap items-center gap-3 mb-3">
                <div class="inline-flex items-center gap-1 rounded-[10px] p-1 bg-zinc-100 dark:bg-zinc-900">
                    <button type="button" wire:click="setStatusFilter('')"         class="pill-tab" aria-pressed="{{ $statusFilter === '' ? 'true' : 'false' }}">All <span class="count">{{ $totalAccounts }}</span></button>
                    <button type="button" wire:click="setStatusFilter('active')"   class="pill-tab" aria-pressed="{{ $statusFilter === 'active' ? 'true' : 'false' }}">Active <span class="count">{{ $activeAccounts }}</span></button>
                    <button type="button" wire:click="setStatusFilter('inactive')" class="pill-tab" aria-pressed="{{ $statusFilter === 'inactive' ? 'true' : 'false' }}">Inactive <span class="count">{{ $inactiveAccounts }}</span></button>
                </div>

                @if($search || $statusFilter)
                    <button type="button" wire:click="clearFilters" class="btn btn-ghost btn-sm ml-auto">
                        <flux:icon name="x-mark" class="w-3.5 h-3.5" /> Reset
                    </button>
                @endif
            </div>

            <div class="relative">
                <flux:icon name="magnifying-glass" class="w-4 h-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-zinc-400" />
                <input
                    type="text"
                    wire:model.live.debounce.300ms="search"
                    placeholder="Search by account name, seller ID, shop ID…"
                    class="pf-search"
                />
            </div>
        </section>

        {{-- ─────────────── Account cards ─────────────── --}}
        <section class="reveal d4">
            @if($accounts->isEmpty())
                <div class="pf-surface p-14 text-center">
                    <div class="mx-auto w-11 h-11 rounded-xl bg-zinc-100 dark:bg-zinc-800 grid place-items-center mb-4">
                        <flux:icon name="user-group" class="w-5 h-5 text-zinc-400" />
                    </div>
                    <h3 class="text-[15px] font-semibold text-zinc-900 dark:text-zinc-100">No accounts yet</h3>
                    <p class="text-[13px] text-zinc-500 dark:text-zinc-400 mt-1.5 mb-5 max-w-sm mx-auto">
                        @if($search || $statusFilter)
                            Try a different search or reset the filters.
                        @else
                            Add your first {{ $platform->display_name }} seller account to start syncing orders.
                        @endif
                    </p>
                    @if($search || $statusFilter)
                        <button type="button" wire:click="clearFilters" class="btn btn-secondary btn-sm">
                            <flux:icon name="x-mark" class="w-3.5 h-3.5" /> Reset filters
                        </button>
                    @else
                        <a href="{{ route('platforms.accounts.create', $platform) }}" wire:navigate class="btn btn-primary btn-sm">
                            <flux:icon name="plus" class="w-3.5 h-3.5" /> Add account
                        </a>
                    @endif
                </div>
            @else
                <div class="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-3 gap-4">
                    @foreach($accounts as $account)
                        @php
                            $isLinked = $platform->slug === 'tiktok-shop' ? $this->hasOAuthCredentials($account) : true;
                        @endphp
                        <article wire:key="account-{{ $account->id }}" class="pf-surface pf-card flex flex-col">

                            {{-- Header --}}
                            <div class="px-5 pt-5 pb-4">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <h3 class="text-[15px] font-semibold tracking-[-0.015em] text-zinc-900 dark:text-white leading-tight truncate">{{ $account->name }}</h3>
                                        <div class="mt-0.5 text-[12px] text-zinc-500 dark:text-zinc-400">
                                            Created {{ $account->created_at->format('M j, Y') }}
                                        </div>
                                    </div>
                                    <div class="flex flex-wrap items-center gap-1.5 shrink-0">
                                        <span class="stat-pill {{ $account->is_active ? 'active' : 'idle' }}">
                                            <span class="ring"></span>{{ $account->is_active ? 'Active' : 'Idle' }}
                                        </span>
                                        @if($platform->slug === 'tiktok-shop')
                                            <span class="stat-pill {{ $isLinked ? 'info' : 'warn' }}">
                                                @if($isLinked)
                                                    <flux:icon name="bolt" class="w-3 h-3" /> API Linked
                                                @else
                                                    <flux:icon name="link-slash" class="w-3 h-3" /> Not Linked
                                                @endif
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            </div>

                            {{-- Live Hosts --}}
                            @if($account->liveHosts->isNotEmpty())
                                <div class="px-5 pb-4">
                                    <div class="text-[10.5px] font-semibold uppercase tracking-[0.08em] text-zinc-500 dark:text-zinc-400 mb-2">Live Hosts</div>
                                    <div class="flex flex-wrap gap-1.5">
                                        @foreach($account->liveHosts as $host)
                                            <span class="host-chip">
                                                <span class="avatar">{{ strtoupper(substr($host->name, 0, 1)) }}</span>
                                                {{ $host->name }}
                                            </span>
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                            {{-- IDs --}}
                            @if($account->account_id || $account->shop_id || $account->business_manager_id)
                                <div class="px-5 pb-4 space-y-1.5">
                                    @if($account->account_id)
                                        <div class="id-row">
                                            <span class="id-label">Seller ID</span>
                                            <span class="id-value truncate ml-3">{{ $account->account_id }}</span>
                                        </div>
                                    @endif
                                    @if($account->shop_id)
                                        <div class="id-row">
                                            <span class="id-label">Shop ID</span>
                                            <span class="id-value truncate ml-3">{{ $account->shop_id }}</span>
                                        </div>
                                    @endif
                                    @if($account->business_manager_id)
                                        <div class="id-row">
                                            <span class="id-label">Business Mgr</span>
                                            <span class="id-value truncate ml-3">{{ $account->business_manager_id }}</span>
                                        </div>
                                    @endif
                                </div>
                            @endif

                            {{-- Notes --}}
                            @if($account->description)
                                <div class="px-5 pb-3">
                                    <p class="text-[12.5px] leading-relaxed text-zinc-600 dark:text-zinc-300 line-clamp-2">
                                        {{ $account->description }}
                                    </p>
                                </div>
                            @endif

                            {{-- Sync status --}}
                            <div class="px-5 pb-4 mt-auto">
                                @if($account->last_sync_at)
                                    <div class="sync-line ok">
                                        <flux:icon name="check-circle" class="w-3.5 h-3.5" />
                                        Last synced {{ $account->last_sync_at->diffForHumans() }}
                                    </div>
                                @else
                                    <div class="sync-line warn">
                                        <flux:icon name="exclamation-triangle" class="w-3.5 h-3.5" />
                                        Never synced
                                    </div>
                                @endif
                            </div>

                            {{-- TikTok-specific link button --}}
                            @if($platform->slug === 'tiktok-shop' && !$isLinked)
                                <div class="px-5 pb-4">
                                    <a
                                        href="{{ route('tiktok.connect', ['link_account' => $account->id]) }}"
                                        class="btn btn-brand w-full"
                                    >
                                        <flux:icon name="link" class="w-4 h-4" />
                                        Link to TikTok Shop
                                    </a>
                                </div>
                            @endif

                            {{-- Actions --}}
                            <div class="flex items-center justify-between gap-2 px-4 py-3 border-t border-zinc-100 dark:border-zinc-800/80">
                                <div class="flex items-center gap-1">
                                    <a href="{{ route('platforms.accounts.show', [$platform, $account]) }}" wire:navigate class="btn btn-ghost btn-sm">
                                        <flux:icon name="eye" class="w-3.5 h-3.5" /> View
                                    </a>
                                    <a href="{{ route('platforms.accounts.edit', [$platform, $account]) }}" wire:navigate class="btn btn-ghost btn-sm">
                                        <flux:icon name="pencil" class="w-3.5 h-3.5" /> Edit
                                    </a>
                                </div>
                                <div class="flex items-center gap-1">
                                    <button
                                        type="button"
                                        wire:click="toggleStatus({{ $account->id }})"
                                        wire:confirm="Are you sure you want to {{ $account->is_active ? 'deactivate' : 'activate' }} this account?"
                                        class="btn btn-ghost btn-icon btn-sm"
                                        title="{{ $account->is_active ? 'Deactivate' : 'Activate' }}"
                                    >
                                        @if($account->is_active)
                                            <flux:icon name="pause" class="w-4 h-4" />
                                        @else
                                            <flux:icon name="play" class="w-4 h-4" />
                                        @endif
                                    </button>
                                    <button
                                        type="button"
                                        wire:click="deleteAccount({{ $account->id }})"
                                        wire:confirm="Are you sure you want to delete this account? This action cannot be undone."
                                        class="btn btn-danger-ghost btn-icon btn-sm"
                                        title="Delete account"
                                    >
                                        <flux:icon name="trash" class="w-4 h-4" />
                                    </button>
                                </div>
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif

            @if($accounts->hasPages())
                <div class="mt-6">
                    {{ $accounts->links() }}
                </div>
            @endif
        </section>
    </div>
</div>
