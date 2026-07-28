<?php

use App\Models\ClassModel;
use App\Models\Course;
use App\Models\Product;
use App\Models\ProductOrder;
use App\Models\User;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Volt\Component;

new class extends Component {
    public string $q = '';

    public function updatedQ(): void
    {
        $this->dispatch('results-updated');
    }

    /**
     * Curated admin destinations for the "Pages" jump list.
     *
     * @return array<int, array{label: string, route: string, icon: string, keywords: string}>
     */
    protected function pageIndex(): array
    {
        return [
            ['label' => 'Dashboard', 'route' => 'dashboard', 'icon' => 'home', 'keywords' => 'home overview'],
            ['label' => 'Courses', 'route' => 'courses.index', 'icon' => 'academic-cap', 'keywords' => 'course academic'],
            ['label' => 'Users', 'route' => 'users.index', 'icon' => 'user-circle', 'keywords' => 'user account'],
            ['label' => 'Students', 'route' => 'students.index', 'icon' => 'users', 'keywords' => 'student pelajar'],
            ['label' => 'Teachers', 'route' => 'teachers.index', 'icon' => 'user-group', 'keywords' => 'teacher guru cikgu'],
            ['label' => 'Classes', 'route' => 'classes.index', 'icon' => 'calendar-days', 'keywords' => 'class kelas'],
            ['label' => 'Sessions', 'route' => 'admin.sessions.index', 'icon' => 'presentation-chart-bar', 'keywords' => 'session'],
            ['label' => 'Master Timetable', 'route' => 'admin.master-timetable', 'icon' => 'table-cells', 'keywords' => 'timetable jadual schedule'],
            ['label' => 'Payslips', 'route' => 'admin.payslips.index', 'icon' => 'banknotes', 'keywords' => 'payslip gaji salary'],
            ['label' => 'Enrollments', 'route' => 'enrollments.index', 'icon' => 'clipboard', 'keywords' => 'enroll enrollment daftar'],
            ['label' => 'Subscription Orders', 'route' => 'orders.index', 'icon' => 'clipboard-document-list', 'keywords' => 'subscription order langganan'],
            ['label' => 'Payment Dashboard', 'route' => 'admin.payments', 'icon' => 'credit-card', 'keywords' => 'payment bayaran'],
            ['label' => 'Products', 'route' => 'products.index', 'icon' => 'cube', 'keywords' => 'product produk barang'],
            ['label' => 'Product Categories', 'route' => 'product-categories.index', 'icon' => 'folder', 'keywords' => 'category kategori'],
            ['label' => 'Product Attributes', 'route' => 'product-attributes.index', 'icon' => 'tag', 'keywords' => 'attribute'],
            ['label' => 'CRM Database', 'route' => 'crm.all-database', 'icon' => 'table-cells', 'keywords' => 'crm database contact'],
            ['label' => 'Audiences', 'route' => 'crm.audiences.index', 'icon' => 'user-group', 'keywords' => 'audience segment'],
            ['label' => 'Broadcasts', 'route' => 'crm.broadcasts.index', 'icon' => 'envelope', 'keywords' => 'broadcast email blast'],
            ['label' => 'Sales Funnels', 'route' => 'admin.funnels', 'icon' => 'funnel', 'keywords' => 'funnel sales'],
            ['label' => 'Workflows', 'route' => 'workflows.index', 'icon' => 'bolt', 'keywords' => 'workflow automation'],
            ['label' => 'Orders & Package Sales', 'route' => 'admin.orders.index', 'icon' => 'shopping-bag', 'keywords' => 'order product sale pesanan'],
            ['label' => 'Sales Report', 'route' => 'admin.orders.report', 'icon' => 'chart-bar', 'keywords' => 'report sales laporan'],
            ['label' => 'Packages', 'route' => 'packages.index', 'icon' => 'gift', 'keywords' => 'package pakej'],
            ['label' => 'Customer Service', 'route' => 'admin.customer-service.dashboard', 'icon' => 'lifebuoy', 'keywords' => 'support ticket service'],
            ['label' => 'Return & Refunds', 'route' => 'admin.customer-service.return-refunds.index', 'icon' => 'arrow-path', 'keywords' => 'return refund pulangan'],
            ['label' => 'Certificates', 'route' => 'certificates.index', 'icon' => 'document-text', 'keywords' => 'certificate sijil'],
            ['label' => 'Inventory Dashboard', 'route' => 'inventory.dashboard', 'icon' => 'chart-bar', 'keywords' => 'inventory stock stok'],
            ['label' => 'Stock Levels', 'route' => 'stock.levels', 'icon' => 'squares-2x2', 'keywords' => 'stock level'],
            ['label' => 'Warehouses', 'route' => 'warehouses.index', 'icon' => 'building-storefront', 'keywords' => 'warehouse gudang'],
            ['label' => 'Agents & Companies', 'route' => 'agents.index', 'icon' => 'building-office', 'keywords' => 'agent company ejen'],
            ['label' => 'Platforms', 'route' => 'platforms.index', 'icon' => 'squares-2x2', 'keywords' => 'platform tiktok'],
            ['label' => 'Live Hosts', 'route' => 'admin.live-hosts', 'icon' => 'users', 'keywords' => 'live host'],
            ['label' => 'Session Slots', 'route' => 'admin.session-slots', 'icon' => 'arrow-up-tray', 'keywords' => 'session slot live schedule'],
            ['label' => 'WhatsApp Inbox', 'route' => 'admin.whatsapp-inbox', 'icon' => 'chat-bubble-left-right', 'keywords' => 'whatsapp inbox chat'],
            ['label' => 'Media Library', 'route' => 'admin.media.index', 'icon' => 'photo', 'keywords' => 'media image gambar'],
            ['label' => 'General Settings', 'route' => 'admin.settings.general', 'icon' => 'information-circle', 'keywords' => 'setting tetapan'],
            ['label' => 'External Systems', 'route' => 'admin.external-systems', 'icon' => 'server-stack', 'keywords' => 'external api integration'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    #[Computed]
    public function results(): array
    {
        $term = trim($this->q);

        $pages = collect($this->pageIndex())
            ->filter(fn ($p) => $term === '' || Str::contains(Str::lower($p['label'].' '.$p['keywords']), Str::lower($term)))
            ->filter(fn ($p) => \Illuminate\Support\Facades\Route::has($p['route']))
            ->take($term === '' ? 6 : 5)
            ->map(fn ($p) => [
                'label' => $p['label'],
                'icon' => $p['icon'],
                'url' => route($p['route']),
            ])
            ->values()
            ->all();

        if (mb_strlen($term) < 2) {
            return [
                'pages' => $pages,
                'people' => [],
                'products' => [],
                'orders' => [],
                'courses' => [],
                'classes' => [],
                'hasRecords' => false,
            ];
        }

        $like = '%'.$term.'%';

        $people = User::query()
            ->where(fn ($q) => $q->where('name', 'like', $like)->orWhere('email', 'like', $like))
            ->orderByRaw('CASE WHEN name like ? THEN 0 ELSE 1 END', [$term.'%'])
            ->limit(5)
            ->get(['id', 'name', 'email'])
            ->map(fn ($u) => [
                'label' => $u->name ?: $u->email,
                'sub' => $u->email,
                'url' => route('users.show', $u->id),
            ])->all();

        $products = Product::query()
            ->where(fn ($q) => $q->where('name', 'like', $like)->orWhere('sku', 'like', $like))
            ->limit(5)
            ->get(['id', 'name', 'sku'])
            ->map(fn ($p) => [
                'label' => $p->name,
                'sub' => $p->sku ? 'SKU: '.$p->sku : null,
                'url' => route('products.show', $p->id),
            ])->all();

        $orders = ProductOrder::query()
            ->where(fn ($q) => $q->where('order_number', 'like', $like)->orWhere('customer_name', 'like', $like))
            ->latest('id')
            ->limit(5)
            ->get(['id', 'order_number', 'customer_name'])
            ->map(fn ($o) => [
                'label' => $o->order_number,
                'sub' => $o->customer_name,
                'url' => route('admin.orders.show', $o->id),
            ])->all();

        $courses = Course::query()
            ->where('name', 'like', $like)
            ->limit(5)
            ->get(['id', 'name'])
            ->map(fn ($c) => [
                'label' => $c->name,
                'sub' => null,
                'url' => route('courses.show', $c->id),
            ])->all();

        $classes = ClassModel::query()
            ->where('title', 'like', $like)
            ->limit(5)
            ->get(['id', 'title'])
            ->map(fn ($c) => [
                'label' => $c->title,
                'sub' => null,
                'url' => route('classes.show', $c->id),
            ])->all();

        return [
            'pages' => $pages,
            'people' => $people,
            'products' => $products,
            'orders' => $orders,
            'courses' => $courses,
            'classes' => $classes,
            'hasRecords' => (bool) (count($people) + count($products) + count($orders) + count($courses) + count($classes)),
        ];
    }
}; ?>

<div
    x-data="{
        open: false,
        openPalette() {
            this.open = true;
            $nextTick(() => { this.$refs.input?.focus(); this.$refs.input?.select(); this.highlight(0); });
        },
        close() { this.open = false; },
        items() { return Array.from(this.$refs.list?.querySelectorAll('[data-result]') ?? []); },
        highlight(i) {
            const items = this.items();
            if (! items.length) return;
            const idx = ((i % items.length) + items.length) % items.length;
            items.forEach((el, n) => el.setAttribute('data-active', n === idx ? 'true' : 'false'));
            items[idx].scrollIntoView({ block: 'nearest' });
        },
        current() { return this.items().findIndex(el => el.getAttribute('data-active') === 'true'); },
        move(dir) { const c = this.current(); this.highlight((c < 0 ? 0 : c) + dir); },
        go() {
            const items = this.items();
            const c = this.current();
            const el = items[c] ?? items[0];
            if (el) el.click();
        },
    }"
    @keydown.window.cmd.k.prevent="openPalette()"
    @keydown.window.ctrl.k.prevent="openPalette()"
    @open-global-search.window="openPalette()"
    @results-updated.window="$nextTick(() => highlight(0))"
    class="contents"
>
    {{-- Trigger button (lives in the top bar) --}}
    <button
        type="button"
        @click="openPalette()"
        class="group flex items-center gap-2 rounded-xl border border-zinc-200 bg-white/70 px-3 py-2 text-sm text-zinc-400 shadow-sm backdrop-blur transition hover:border-indigo-300 hover:text-zinc-600 hover:shadow-md dark:border-zinc-700 dark:bg-zinc-800/60 dark:text-zinc-400 dark:hover:border-indigo-500/50 lg:w-72"
    >
        <flux:icon name="magnifying-glass" class="size-4 text-indigo-500 transition group-hover:scale-110" />
        <span class="hidden flex-1 text-start lg:block">{{ __('Search everything...') }}</span>
        <kbd class="hidden items-center gap-0.5 rounded-md border border-zinc-200 bg-zinc-50 px-1.5 py-0.5 font-sans text-[10px] font-semibold text-zinc-500 dark:border-zinc-600 dark:bg-zinc-900 dark:text-zinc-400 lg:flex">⌘K</kbd>
    </button>

    {{-- Palette overlay --}}
    <template x-teleport="body">
        <div
            x-show="open"
            x-cloak
            @keydown.escape.window="close()"
            class="fixed inset-0 z-[100] flex items-start justify-center px-4 pt-[12vh]"
            style="display: none;"
        >
            {{-- Backdrop --}}
            <div
                x-show="open"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                @click="close()"
                class="fixed inset-0 bg-zinc-900/50 backdrop-blur-sm"
            ></div>

            {{-- Panel --}}
            <div
                x-show="open"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 -translate-y-3 scale-95"
                x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="opacity-100 scale-100"
                x-transition:leave-end="opacity-0 scale-95"
                class="relative z-10 w-full max-w-xl overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-2xl ring-1 ring-black/5 dark:border-zinc-700 dark:bg-zinc-900"
            >
                {{-- Gradient top edge --}}
                <div class="h-1 w-full bg-gradient-to-r from-indigo-500 via-violet-500 to-fuchsia-500"></div>

                {{-- Search input --}}
                <div class="flex items-center gap-3 border-b border-zinc-100 px-4 dark:border-zinc-800">
                    <flux:icon name="magnifying-glass" class="size-5 shrink-0 text-indigo-500" />
                    <input
                        x-ref="input"
                        type="text"
                        wire:model.live.debounce.250ms="q"
                        @keydown.down.prevent="move(1)"
                        @keydown.up.prevent="move(-1)"
                        @keydown.enter.prevent="go()"
                        placeholder="{{ __('Search orders, students, products, pages...') }}"
                        class="w-full border-0 bg-transparent py-4 text-base text-zinc-800 placeholder-zinc-400 focus:outline-none focus:ring-0 dark:text-zinc-100"
                        autocomplete="off"
                        spellcheck="false"
                    />
                    <div wire:loading wire:target="q" class="shrink-0">
                        <flux:icon name="arrow-path" class="size-4 animate-spin text-indigo-400" />
                    </div>
                    <kbd class="hidden shrink-0 rounded-md border border-zinc-200 bg-zinc-50 px-1.5 py-0.5 text-[10px] font-semibold text-zinc-400 dark:border-zinc-700 dark:bg-zinc-800 sm:block">ESC</kbd>
                </div>

                {{-- Results --}}
                <div x-ref="list" class="max-h-[60vh] overflow-y-auto p-2" wire:key="results-{{ $q }}">
                    @php $r = $this->results; @endphp

                    @php
                        $groups = [
                            ['key' => 'orders', 'label' => __('Orders'), 'color' => 'text-emerald-500', 'icon' => 'shopping-bag'],
                            ['key' => 'people', 'label' => __('People'), 'color' => 'text-blue-500', 'icon' => 'user-circle'],
                            ['key' => 'products', 'label' => __('Products'), 'color' => 'text-amber-500', 'icon' => 'cube'],
                            ['key' => 'courses', 'label' => __('Courses'), 'color' => 'text-fuchsia-500', 'icon' => 'academic-cap'],
                            ['key' => 'classes', 'label' => __('Classes'), 'color' => 'text-rose-500', 'icon' => 'calendar-days'],
                        ];
                    @endphp

                    @foreach($groups as $group)
                        @if(! empty($r[$group['key']]))
                            <div class="px-2 pb-1 pt-2 text-[11px] font-semibold uppercase tracking-wider text-zinc-400">{{ $group['label'] }}</div>
                            @foreach($r[$group['key']] as $item)
                                <a
                                    href="{{ $item['url'] }}"
                                    wire:navigate
                                    @click="close()"
                                    data-result
                                    data-active="false"
                                    class="group flex items-center gap-3 rounded-xl px-3 py-2.5 transition data-[active=true]:bg-gradient-to-r data-[active=true]:from-indigo-50 data-[active=true]:to-violet-50 dark:data-[active=true]:from-indigo-500/10 dark:data-[active=true]:to-violet-500/10"
                                >
                                    <span class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-zinc-100 group-data-[active=true]:bg-white dark:bg-zinc-800 dark:group-data-[active=true]:bg-zinc-700">
                                        <flux:icon :name="$group['icon']" class="size-4 {{ $group['color'] }}" />
                                    </span>
                                    <span class="min-w-0 flex-1">
                                        <span class="block truncate text-sm font-medium text-zinc-800 dark:text-zinc-100">{{ $item['label'] }}</span>
                                        @if(! empty($item['sub']))
                                            <span class="block truncate text-xs text-zinc-400">{{ $item['sub'] }}</span>
                                        @endif
                                    </span>
                                    <flux:icon name="arrow-up-right" class="size-4 shrink-0 text-zinc-300 opacity-0 transition group-data-[active=true]:opacity-100" />
                                </a>
                            @endforeach
                        @endif
                    @endforeach

                    @if(! empty($r['pages']))
                        <div class="px-2 pb-1 pt-2 text-[11px] font-semibold uppercase tracking-wider text-zinc-400">{{ __('Pages') }}</div>
                        @foreach($r['pages'] as $page)
                            <a
                                href="{{ $page['url'] }}"
                                wire:navigate
                                @click="close()"
                                data-result
                                data-active="false"
                                class="group flex items-center gap-3 rounded-xl px-3 py-2.5 transition data-[active=true]:bg-gradient-to-r data-[active=true]:from-indigo-50 data-[active=true]:to-violet-50 dark:data-[active=true]:from-indigo-500/10 dark:data-[active=true]:to-violet-500/10"
                            >
                                <span class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-zinc-100 group-data-[active=true]:bg-white dark:bg-zinc-800 dark:group-data-[active=true]:bg-zinc-700">
                                    <flux:icon :name="$page['icon']" class="size-4 text-indigo-500" />
                                </span>
                                <span class="min-w-0 flex-1 truncate text-sm font-medium text-zinc-800 dark:text-zinc-100">{{ $page['label'] }}</span>
                                <flux:icon name="arrow-up-right" class="size-4 shrink-0 text-zinc-300 opacity-0 transition group-data-[active=true]:opacity-100" />
                            </a>
                        @endforeach
                    @endif

                    {{-- Empty state --}}
                    @if(mb_strlen(trim($q)) >= 2 && ! $r['hasRecords'] && empty($r['pages']))
                        <div class="flex flex-col items-center gap-2 px-4 py-12 text-center">
                            <span class="flex size-12 items-center justify-center rounded-full bg-zinc-100 dark:bg-zinc-800">
                                <flux:icon name="magnifying-glass" class="size-6 text-zinc-400" />
                            </span>
                            <p class="text-sm font-medium text-zinc-600 dark:text-zinc-300">{{ __('No results for') }} "{{ $q }}"</p>
                            <p class="text-xs text-zinc-400">{{ __('Try an order number, name, SKU, or page') }}</p>
                        </div>
                    @endif

                    {{-- Hint (empty query) --}}
                    @if(trim($q) === '')
                        <div class="px-3 pb-1 pt-3 text-[11px] text-zinc-400">
                            {{ __('Start typing to search across your whole system.') }}
                        </div>
                    @endif
                </div>

                {{-- Footer --}}
                <div class="flex items-center justify-between border-t border-zinc-100 bg-zinc-50/60 px-4 py-2 text-[11px] text-zinc-400 dark:border-zinc-800 dark:bg-zinc-800/40">
                    <span class="flex items-center gap-3">
                        <span class="flex items-center gap-1"><kbd class="rounded border border-zinc-200 bg-white px-1 dark:border-zinc-700 dark:bg-zinc-900">↑</kbd><kbd class="rounded border border-zinc-200 bg-white px-1 dark:border-zinc-700 dark:bg-zinc-900">↓</kbd> {{ __('navigate') }}</span>
                        <span class="flex items-center gap-1"><kbd class="rounded border border-zinc-200 bg-white px-1 dark:border-zinc-700 dark:bg-zinc-900">↵</kbd> {{ __('open') }}</span>
                    </span>
                    <span class="flex items-center gap-1 font-medium text-indigo-500">
                        <flux:icon name="sparkles" class="size-3.5" /> {{ __('Quick Search') }}
                    </span>
                </div>
            </div>
        </div>
    </template>
</div>
