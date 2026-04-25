<?php

use Livewire\Volt\Component;
use Livewire\WithPagination;
use App\Models\Platform;

new class extends Component {
    use WithPagination;

    public $search = '';
    public $typeFilter = '';
    public $statusFilter = '';
    public $sortBy = 'sort_order';
    public $sortDirection = 'asc';
    public $viewMode = 'grid';

    public function mount()
    {
        $this->resetPage();
    }

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function updatedTypeFilter()
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

    public function setViewMode(string $mode): void
    {
        $this->viewMode = in_array($mode, ['grid', 'list']) ? $mode : 'grid';
    }

    public function clearFilters(): void
    {
        $this->search = '';
        $this->typeFilter = '';
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

    public function toggleStatus($platformId)
    {
        $platform = Platform::findOrFail($platformId);
        $platform->update(['is_active' => !$platform->is_active]);

        $this->dispatch('platform-updated', [
            'message' => "Platform '{$platform->name}' has been " . ($platform->is_active ? 'activated' : 'deactivated')
        ]);
    }

    public function with()
    {
        $query = Platform::query();

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('name', 'like', "%{$this->search}%")
                  ->orWhere('display_name', 'like', "%{$this->search}%")
                  ->orWhere('description', 'like', "%{$this->search}%");
            });
        }

        if ($this->typeFilter) {
            $query->where('type', $this->typeFilter);
        }

        if ($this->statusFilter === 'active') {
            $query->where('is_active', true);
        } elseif ($this->statusFilter === 'inactive') {
            $query->where('is_active', false);
        } elseif ($this->statusFilter === 'connected') {
            $query->whereHas('accounts', fn ($q) => $q->where('is_active', true));
        }

        $platforms = $query->orderBy($this->sortBy, $this->sortDirection)
                          ->paginate($this->viewMode === 'list' ? 20 : 12);

        $totalPlatforms = Platform::count();
        $activePlatforms = Platform::where('is_active', true)->count();
        $inactivePlatforms = $totalPlatforms - $activePlatforms;
        $connectedPlatforms = Platform::whereHas('accounts', fn ($q) => $q->where('is_active', true))->count();

        return [
            'platforms' => $platforms,
            'platformTypes' => ['marketplace', 'social_media', 'custom'],
            'totalPlatforms' => $totalPlatforms,
            'activePlatforms' => $activePlatforms,
            'inactivePlatforms' => $inactivePlatforms,
            'connectedPlatforms' => $connectedPlatforms,
            'activePercent' => $totalPlatforms > 0 ? round(($activePlatforms / $totalPlatforms) * 100) : 0,
            'connectedPercent' => $totalPlatforms > 0 ? round(($connectedPlatforms / $totalPlatforms) * 100) : 0,
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

        /* ── Modern surfaces ─────────────────────────────────── */
        .pf-surface { background: #ffffff; border: 1px solid rgb(228 228 231 / 1); border-radius: 14px; transition: all .2s cubic-bezier(.2,.7,.2,1); }
        .dark .pf-surface { background: rgb(9 9 11 / 1); border-color: rgb(39 39 42 / 1); }

        .pf-card { position: relative; isolation: isolate; transition: all .25s cubic-bezier(.2,.7,.2,1); }
        .pf-card:hover { transform: translateY(-1px); border-color: rgb(212 212 216); box-shadow: 0 1px 2px rgb(0 0 0 / 0.04), 0 8px 24px -8px rgb(0 0 0 / 0.08); }
        .dark .pf-card:hover { border-color: rgb(63 63 70); box-shadow: 0 1px 2px rgb(0 0 0 / 0.5), 0 8px 24px -4px rgb(0 0 0 / 0.6); }

        /* ── Pill tabs ────────────────────────────────────────── */
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

        /* ── Status pill ──────────────────────────────────────── */
        .stat-pill {
            display: inline-flex; align-items: center; gap: 6px;
            height: 22px; padding: 0 8px; border-radius: 999px;
            font-size: 11px; font-weight: 600; letter-spacing: -0.005em;
            border: 1px solid transparent;
        }
        .stat-pill.active { background: rgb(220 252 231); color: rgb(21 128 61); border-color: rgb(187 247 208); }
        .stat-pill.idle   { background: rgb(244 244 245); color: rgb(82 82 91); border-color: rgb(228 228 231); }
        .dark .stat-pill.active { background: rgb(20 83 45 / .25); color: rgb(134 239 172); border-color: rgb(22 101 52 / .5); }
        .dark .stat-pill.idle   { background: rgb(39 39 42); color: rgb(161 161 170); border-color: rgb(63 63 70); }
        .stat-pill .ring { width: 6px; height: 6px; border-radius: 999px; background: currentColor; box-shadow: 0 0 0 2.5px rgba(34,197,94,.18); }
        .stat-pill.idle .ring { box-shadow: 0 0 0 2.5px rgba(161,161,170,.18); }

        /* ── View toggle ─────────────────────────────────────── */
        .view-toggle { display: inline-flex; padding: 3px; border-radius: 9px; background: rgb(244 244 245); }
        .dark .view-toggle { background: rgb(24 24 27); }
        .view-toggle button {
            display: inline-grid; place-items: center; width: 28px; height: 28px;
            border-radius: 6px; color: rgb(113 113 122); transition: all .15s ease; border: none; background: transparent; cursor: pointer;
        }
        .view-toggle button:hover { color: rgb(24 24 27); }
        .dark .view-toggle button:hover { color: rgb(244 244 245); }
        .view-toggle button[aria-pressed="true"] { background: #ffffff; color: rgb(24 24 27); box-shadow: 0 1px 2px rgb(0 0 0 / .08); }
        .dark .view-toggle button[aria-pressed="true"] { background: rgb(39 39 42); color: rgb(244 244 245); box-shadow: none; }

        /* ── Stat cards ──────────────────────────────────────── */
        .pf-stat { padding: 18px 20px; }
        .pf-stat .label { font-size: 12px; font-weight: 500; color: rgb(113 113 122); }
        .dark .pf-stat .label { color: rgb(161 161 170); }
        .pf-stat .num { font-size: 30px; font-weight: 600; color: rgb(9 9 11); line-height: 1; letter-spacing: -0.03em; }
        .dark .pf-stat .num { color: #fff; }
        .pf-stat .meter { height: 4px; border-radius: 999px; background: rgb(244 244 245); overflow: hidden; }
        .dark .pf-stat .meter { background: rgb(39 39 42); }
        .pf-stat .meter > i { display: block; height: 100%; border-radius: 999px; }
        .pf-stat .delta { font-size: 11px; font-weight: 500; }

        /* ── Search input ────────────────────────────────────── */
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

        .pf-select {
            height: 40px; padding: 0 32px 0 12px;
            background: #fff; border: 1px solid rgb(228 228 231); border-radius: 10px;
            font-size: 13px; color: rgb(63 63 70);
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%2371717a' stroke-width='2'%3E%3Cpath d='m6 9 6 6 6-6'/%3E%3C/svg%3E");
            background-repeat: no-repeat; background-position: right 10px center;
            appearance: none;
        }
        .dark .pf-select { background-color: rgb(9 9 11); border-color: rgb(39 39 42); color: rgb(228 228 231); }

        /* ── Buttons ─────────────────────────────────────────── */
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
        .btn-sm { height: 30px; padding: 0 11px; font-size: 12.5px; border-radius: 7px; }
        .btn-icon { width: 32px; padding: 0; }

        /* ── Card meta ───────────────────────────────────────── */
        .meta-row { display: inline-flex; align-items: center; gap: 6px; font-size: 12px; color: rgb(113 113 122); }
        .dark .meta-row { color: rgb(161 161 170); }
        .meta-row .num { color: rgb(24 24 27); font-weight: 600; font-size: 12px; }
        .dark .meta-row .num { color: rgb(244 244 245); }
        .meta-sep { width: 1px; height: 10px; background: rgb(228 228 231); }
        .dark .meta-sep { background: rgb(39 39 42); }

        /* Logo wrapper */
        .pf-logo { width: 40px; height: 40px; border-radius: 10px; display: grid; place-items: center; color: #fff; font-weight: 600; font-size: 15px; letter-spacing: -0.02em; flex-shrink: 0; box-shadow: inset 0 0 0 1px rgb(0 0 0 / .04); position: relative; overflow: hidden; }
        .pf-logo::after { content: ''; position: absolute; inset: 0; background: linear-gradient(180deg, rgba(255,255,255,.18), transparent 50%); pointer-events: none; }

        /* Reveal */
        .reveal { opacity: 0; transform: translateY(6px); animation: reveal .5s cubic-bezier(.2,.7,.2,1) forwards; }
        .reveal.d1 { animation-delay: .04s; } .reveal.d2 { animation-delay: .10s; } .reveal.d3 { animation-delay: .16s; } .reveal.d4 { animation-delay: .22s; }
        @keyframes reveal { to { opacity: 1; transform: none; } }

        /* List rows */
        .pf-list-row { transition: background-color .15s ease; }
        .pf-list-row:hover { background: rgb(250 250 250); }
        .dark .pf-list-row:hover { background: rgb(24 24 27); }
    </style>

    <div class="pf-page">

        {{-- ─────────────────────── Hero ─────────────────────── --}}
        <header class="reveal d1 mb-8 flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
            <div class="max-w-2xl">
                <div class="inline-flex items-center gap-2 mb-3 text-[11px] font-semibold uppercase tracking-[0.12em] text-zinc-500 dark:text-zinc-400">
                    <span class="relative flex h-1.5 w-1.5">
                        <span class="absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75 animate-ping"></span>
                        <span class="relative inline-flex h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                    </span>
                    Platform Integration
                </div>
                <h1 class="text-[34px] sm:text-[40px] font-semibold tracking-[-0.035em] leading-[1.05] text-zinc-900 dark:text-white">
                    Platforms
                </h1>
                <p class="mt-2 text-[14px] text-zinc-500 dark:text-zinc-400 leading-relaxed">
                    Connect, monitor and orchestrate every marketplace and channel that feeds orders into Mudeer Bedaie.
                </p>
            </div>
            <div class="flex items-center gap-2">
                <button type="button" class="btn btn-secondary">
                    <flux:icon name="arrow-down-tray" class="w-4 h-4" />
                    Export
                </button>
                <a href="/admin/platforms/create" class="btn btn-primary">
                    <flux:icon name="plus" class="w-4 h-4" />
                    Add Platform
                </a>
            </div>
        </header>

        {{-- ─────────────────────── Stats ────────────────────── --}}
        <section class="reveal d2 mb-6 grid grid-cols-2 lg:grid-cols-4 gap-3">
            <div class="pf-surface pf-stat">
                <div class="flex items-center justify-between mb-3">
                    <span class="label">Total platforms</span>
                    <flux:icon name="squares-2x2" class="w-4 h-4 text-zinc-400" />
                </div>
                <div class="num">{{ $totalPlatforms }}</div>
                <div class="mt-3 flex items-center gap-1.5 text-[11px] text-zinc-500 dark:text-zinc-400">
                    <span>Marketplace</span><span class="text-zinc-300 dark:text-zinc-600">·</span>
                    <span>Social</span><span class="text-zinc-300 dark:text-zinc-600">·</span>
                    <span>Custom</span>
                </div>
            </div>

            <div class="pf-surface pf-stat">
                <div class="flex items-center justify-between mb-3">
                    <span class="label">Active</span>
                    <span class="delta text-emerald-600 dark:text-emerald-400 num">{{ $activePercent }}%</span>
                </div>
                <div class="flex items-baseline gap-1.5">
                    <span class="num">{{ $activePlatforms }}</span>
                    <span class="num text-base text-zinc-400 dark:text-zinc-500" style="font-size:14px;font-weight:500;">/ {{ $totalPlatforms }}</span>
                </div>
                <div class="meter mt-3"><i style="width: {{ $activePercent }}%; background: linear-gradient(90deg, rgb(16 185 129), rgb(52 211 153));"></i></div>
            </div>

            <div class="pf-surface pf-stat">
                <div class="flex items-center justify-between mb-3">
                    <span class="label">Connected</span>
                    <span class="delta text-zinc-500 dark:text-zinc-400 num">{{ $connectedPercent }}%</span>
                </div>
                <div class="flex items-baseline gap-1.5">
                    <span class="num">{{ $connectedPlatforms }}</span>
                    <span class="text-[12px] text-zinc-400 dark:text-zinc-500 ml-1">accounts</span>
                </div>
                <div class="meter mt-3"><i style="width: {{ $connectedPercent }}%; background: linear-gradient(90deg, rgb(24 24 27), rgb(82 82 91));"></i></div>
            </div>

            <div class="pf-surface pf-stat">
                <div class="flex items-center justify-between mb-3">
                    <span class="label">Needs attention</span>
                    <flux:icon name="exclamation-circle" class="w-4 h-4 text-amber-500" />
                </div>
                <div class="num">{{ $inactivePlatforms }}</div>
                <div class="mt-3 text-[11px] text-zinc-500 dark:text-zinc-400">Idle or unconfigured</div>
            </div>
        </section>

        {{-- ───────────────── Filter rail ───────────────── --}}
        <section class="reveal d3 mb-5">
            <div class="flex flex-wrap items-center gap-3 mb-3">
                {{-- Pill tabs --}}
                <div class="inline-flex items-center gap-1 rounded-[10px] p-1 bg-zinc-100 dark:bg-zinc-900">
                    <button type="button" wire:click="setStatusFilter('')"          class="pill-tab" aria-pressed="{{ $statusFilter === '' ? 'true' : 'false' }}">All <span class="count">{{ $totalPlatforms }}</span></button>
                    <button type="button" wire:click="setStatusFilter('active')"    class="pill-tab" aria-pressed="{{ $statusFilter === 'active' ? 'true' : 'false' }}">Active <span class="count">{{ $activePlatforms }}</span></button>
                    <button type="button" wire:click="setStatusFilter('inactive')"  class="pill-tab" aria-pressed="{{ $statusFilter === 'inactive' ? 'true' : 'false' }}">Inactive <span class="count">{{ $inactivePlatforms }}</span></button>
                    <button type="button" wire:click="setStatusFilter('connected')" class="pill-tab" aria-pressed="{{ $statusFilter === 'connected' ? 'true' : 'false' }}">Connected <span class="count">{{ $connectedPlatforms }}</span></button>
                </div>

                <div class="ml-auto flex items-center gap-2">
                    @if($search || $typeFilter || $statusFilter)
                        <button type="button" wire:click="clearFilters" class="btn btn-ghost btn-sm">
                            <flux:icon name="x-mark" class="w-3.5 h-3.5" /> Reset
                        </button>
                    @endif
                    <div class="view-toggle" role="group" aria-label="View mode">
                        <button type="button" wire:click="setViewMode('grid')" aria-pressed="{{ $viewMode === 'grid' ? 'true' : 'false' }}" title="Grid view">
                            <flux:icon name="squares-2x2" class="w-4 h-4" />
                        </button>
                        <button type="button" wire:click="setViewMode('list')" aria-pressed="{{ $viewMode === 'list' ? 'true' : 'false' }}" title="List view">
                            <flux:icon name="bars-3" class="w-4 h-4" />
                        </button>
                    </div>
                </div>
            </div>

            <div class="flex flex-col sm:flex-row gap-3">
                <div class="flex-1 relative">
                    <flux:icon name="magnifying-glass" class="w-4 h-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-zinc-400" />
                    <input
                        type="text"
                        wire:model.live.debounce.300ms="search"
                        placeholder="Search platforms…"
                        class="pf-search"
                    />
                </div>
                <select wire:model.live="typeFilter" class="pf-select">
                    <option value="">All types</option>
                    @foreach($platformTypes as $type)
                        <option value="{{ $type }}">{{ ucfirst(str_replace('_', ' ', $type)) }}</option>
                    @endforeach
                </select>
            </div>
        </section>

        {{-- ─────────────────── Results ──────────────────── --}}
        <section class="reveal d4">
            @if($platforms->isEmpty())
                <div class="pf-surface p-14 text-center">
                    <div class="mx-auto w-11 h-11 rounded-xl bg-zinc-100 dark:bg-zinc-800 grid place-items-center mb-4">
                        <flux:icon name="squares-2x2" class="w-5 h-5 text-zinc-400" />
                    </div>
                    <h3 class="text-[15px] font-semibold text-zinc-900 dark:text-zinc-100">No platforms match</h3>
                    <p class="text-[13px] text-zinc-500 dark:text-zinc-400 mt-1.5 mb-5 max-w-sm mx-auto">
                        @if($search || $typeFilter || $statusFilter)
                            Try a different search or reset the filters.
                        @else
                            Wire up your first marketplace or channel to start streaming orders.
                        @endif
                    </p>
                    @if($search || $typeFilter || $statusFilter)
                        <button type="button" wire:click="clearFilters" class="btn btn-secondary btn-sm">
                            <flux:icon name="x-mark" class="w-3.5 h-3.5" /> Reset filters
                        </button>
                    @else
                        <a href="/admin/platforms/create" class="btn btn-primary btn-sm">
                            <flux:icon name="plus" class="w-3.5 h-3.5" /> Add platform
                        </a>
                    @endif
                </div>

            @elseif($viewMode === 'list')
                {{-- ─────── List view ─────── --}}
                <div class="pf-surface overflow-hidden">
                    <div class="grid grid-cols-12 px-5 py-3 border-b border-zinc-200 dark:border-zinc-800 bg-zinc-50/60 dark:bg-zinc-900/40">
                        <div class="col-span-5 text-[11px] font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">Platform</div>
                        <div class="col-span-2 text-[11px] font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">Type</div>
                        <div class="col-span-2 text-[11px] font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">Status</div>
                        <div class="col-span-1 text-[11px] font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">Mode</div>
                        <div class="col-span-2 text-[11px] font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400 text-right">Actions</div>
                    </div>
                    @foreach($platforms as $platform)
                        @php
                            $accent = $platform->color_primary ?: '#52525b';
                            $accountCount = $platform->accounts()->where('is_active', true)->count();
                            $hasApi = $platform->settings['api_available'] ?? false;
                        @endphp
                        <div wire:key="row-{{ $platform->id }}" class="pf-list-row grid grid-cols-12 items-center px-5 py-3.5 border-b last:border-b-0 border-zinc-100 dark:border-zinc-800/70">
                            <div class="col-span-5 flex items-center gap-3 min-w-0">
                                @if($platform->logo_url)
                                    <img src="{{ $platform->logo_url }}" alt="" class="pf-logo" style="background:{{ $accent }};">
                                @else
                                    <div class="pf-logo" style="background:{{ $accent }};">
                                        {{ strtoupper(substr($platform->display_name ?? $platform->name, 0, 2)) }}
                                    </div>
                                @endif
                                <div class="min-w-0">
                                    <div class="text-[14px] font-semibold text-zinc-900 dark:text-zinc-100 truncate tracking-[-0.01em]">{{ $platform->display_name }}</div>
                                    <div class="text-[12px] text-zinc-500 dark:text-zinc-400 truncate">{{ \Illuminate\Support\Str::limit($platform->description, 70) }}</div>
                                </div>
                            </div>
                            <div class="col-span-2 text-[12.5px] text-zinc-600 dark:text-zinc-300 capitalize">{{ str_replace('_', ' ', $platform->type) }}</div>
                            <div class="col-span-2">
                                <span class="stat-pill {{ $platform->is_active ? 'active' : 'idle' }}">
                                    <span class="ring"></span> {{ $platform->is_active ? 'Active' : 'Idle' }}
                                </span>
                            </div>
                            <div class="col-span-1 text-[12.5px] text-zinc-600 dark:text-zinc-300">{{ $hasApi ? 'API' : 'Manual' }}</div>
                            <div class="col-span-2 flex items-center justify-end gap-1.5">
                                <a href="/admin/platforms/{{ $platform->slug }}/accounts" class="btn btn-ghost btn-icon btn-sm" title="Accounts">
                                    <flux:icon name="user-group" class="w-4 h-4" />
                                </a>
                                <a href="/admin/platforms/{{ $platform->slug }}/edit" class="btn btn-ghost btn-icon btn-sm" title="Edit">
                                    <flux:icon name="pencil" class="w-4 h-4" />
                                </a>
                                <button
                                    type="button"
                                    wire:click="toggleStatus({{ $platform->id }})"
                                    wire:confirm="Are you sure you want to {{ $platform->is_active ? 'deactivate' : 'activate' }} this platform?"
                                    class="btn btn-sm {{ $platform->is_active ? 'btn-secondary' : 'btn-primary' }}"
                                >
                                    {{ $platform->is_active ? 'Deactivate' : 'Activate' }}
                                </button>
                            </div>
                        </div>
                    @endforeach
                </div>

            @else
                {{-- ─────── Grid view ─────── --}}
                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                    @foreach($platforms as $platform)
                        @php
                            $accent = $platform->color_primary ?: '#52525b';
                            $accountCount = $platform->accounts()->where('is_active', true)->count();
                            $hasApi = $platform->settings['api_available'] ?? false;
                            $features = $platform->features ?? [];
                            $featureCount = count($features);
                        @endphp
                        <article wire:key="card-{{ $platform->id }}" class="pf-surface pf-card p-5 flex flex-col gap-4">
                            {{-- Top row: logo + identity + status --}}
                            <div class="flex items-start justify-between gap-3">
                                <div class="flex items-center gap-3 min-w-0">
                                    @if($platform->logo_url)
                                        <img src="{{ $platform->logo_url }}" alt="" class="pf-logo object-cover" style="background:{{ $accent }};">
                                    @else
                                        <div class="pf-logo" style="background:{{ $accent }};">
                                            {{ strtoupper(substr($platform->display_name ?? $platform->name, 0, 2)) }}
                                        </div>
                                    @endif
                                    <div class="min-w-0">
                                        <h3 class="text-[15px] font-semibold tracking-[-0.015em] text-zinc-900 dark:text-white leading-tight truncate">{{ $platform->display_name }}</h3>
                                        <div class="mt-0.5 text-[12px] text-zinc-500 dark:text-zinc-400 capitalize truncate">{{ str_replace('_', ' ', $platform->type) }}</div>
                                    </div>
                                </div>
                                <span class="stat-pill {{ $platform->is_active ? 'active' : 'idle' }} shrink-0">
                                    <span class="ring"></span>{{ $platform->is_active ? 'Active' : 'Idle' }}
                                </span>
                            </div>

                            {{-- Description --}}
                            <p class="text-[13px] leading-[1.55] text-zinc-600 dark:text-zinc-300 line-clamp-2 min-h-[2.6em]">
                                {{ $platform->description ?: 'No description provided.' }}
                            </p>

                            {{-- Inline metadata — no boxed grid, just clean inline meta --}}
                            <div class="flex items-center flex-wrap gap-x-3 gap-y-1.5">
                                <span class="meta-row">
                                    <flux:icon name="user-group" class="w-3.5 h-3.5" />
                                    <span class="num">{{ $accountCount }}</span>
                                    {{ $accountCount === 1 ? 'account' : 'accounts' }}
                                </span>
                                <span class="meta-sep"></span>
                                <span class="meta-row">
                                    @if($hasApi)
                                        <flux:icon name="bolt" class="w-3.5 h-3.5 text-emerald-500" /> API
                                    @else
                                        <flux:icon name="cursor-arrow-rays" class="w-3.5 h-3.5" /> Manual
                                    @endif
                                </span>
                                <span class="meta-sep"></span>
                                <span class="meta-row">
                                    <flux:icon name="cube" class="w-3.5 h-3.5" />
                                    <span class="num">{{ $featureCount }}</span>
                                    {{ $featureCount === 1 ? 'feature' : 'features' }}
                                </span>
                            </div>

                            {{-- Actions --}}
                            <div class="flex items-center justify-between gap-2 pt-3 border-t border-zinc-100 dark:border-zinc-800/80">
                                <a href="/admin/platforms/{{ $platform->slug }}/accounts" class="btn btn-secondary btn-sm">
                                    <flux:icon name="user-group" class="w-3.5 h-3.5" /> Manage
                                    <flux:icon name="arrow-right" class="w-3.5 h-3.5 -mr-0.5" />
                                </a>
                                <div class="flex items-center gap-1">
                                    <a href="/admin/platforms/{{ $platform->slug }}/edit" class="btn btn-ghost btn-icon btn-sm" title="Edit">
                                        <flux:icon name="pencil" class="w-4 h-4" />
                                    </a>
                                    <button
                                        type="button"
                                        wire:click="toggleStatus({{ $platform->id }})"
                                        wire:confirm="Are you sure you want to {{ $platform->is_active ? 'deactivate' : 'activate' }} this platform?"
                                        class="btn btn-ghost btn-icon btn-sm"
                                        title="{{ $platform->is_active ? 'Deactivate' : 'Activate' }}"
                                    >
                                        @if($platform->is_active)
                                            <flux:icon name="pause" class="w-4 h-4" />
                                        @else
                                            <flux:icon name="play" class="w-4 h-4" />
                                        @endif
                                    </button>
                                </div>
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif

            {{-- Pagination --}}
            @if($platforms->hasPages())
                <div class="mt-6">
                    {{ $platforms->links() }}
                </div>
            @endif
        </section>
    </div>
</div>
