@php
    /**
     * Variable field picker combobox.
     *
     * Required vars (passed via @include):
     *   $varNum  - the template variable index (e.g. "1")
     *   $wireProperty - the Livewire dotted path to bind (e.g. "variableMappings.body.1")
     *
     * Defines $variableFieldGroups lazily once per request.
     */
    if (! isset($variableFieldGroups)) {
        $variableFieldGroups = [
            [
                'group' => 'Certificate',
                'icon' => '<path d="M12 14l9-5-9-5-9 5 9 5z"/><path d="M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 14l9-5-9-5-9 5 9 5zm0 0l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14zm-4 6v-7.5l4-2.222"/>',
                'items' => [
                    ['value' => 'student_name', 'label' => 'Student Name', 'tag' => '{{student_name}}'],
                    ['value' => 'certificate_name', 'label' => 'Certificate Name', 'tag' => '{{certificate_name}}'],
                    ['value' => 'certificate_number', 'label' => 'Certificate Number', 'tag' => '{{certificate_number}}'],
                    ['value' => 'class_name', 'label' => 'Class Name', 'tag' => '{{class_name}}'],
                    ['value' => 'course_name', 'label' => 'Course Name', 'tag' => '{{course_name}}'],
                    ['value' => 'issue_date', 'label' => 'Issue Date', 'tag' => '{{issue_date}}'],
                ],
            ],
            [
                'group' => 'Customer',
                'icon' => '<path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>',
                'items' => [
                    ['value' => 'contact.name', 'label' => 'Name', 'tag' => '{{contact.name}}'],
                    ['value' => 'contact.first_name', 'label' => 'First Name', 'tag' => '{{contact.first_name}}'],
                    ['value' => 'contact.email', 'label' => 'Email', 'tag' => '{{contact.email}}'],
                    ['value' => 'contact.phone', 'label' => 'Phone', 'tag' => '{{contact.phone}}'],
                    ['value' => 'contact.address', 'label' => 'Address', 'tag' => '{{contact.address}}'],
                ],
            ],
            [
                'group' => 'Order',
                'icon' => '<path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>',
                'items' => [
                    ['value' => 'order.number', 'label' => 'Number', 'tag' => '{{order.number}}'],
                    ['value' => 'order.total', 'label' => 'Total', 'tag' => '{{order.total}}'],
                    ['value' => 'order.total_raw', 'label' => 'Total (Raw)', 'tag' => '{{order.total_raw}}'],
                    ['value' => 'order.subtotal', 'label' => 'Subtotal', 'tag' => '{{order.subtotal}}'],
                    ['value' => 'order.currency', 'label' => 'Currency', 'tag' => '{{order.currency}}'],
                    ['value' => 'order.status', 'label' => 'Status', 'tag' => '{{order.status}}'],
                    ['value' => 'order.items_count', 'label' => 'Items Count', 'tag' => '{{order.items_count}}'],
                    ['value' => 'order.items_list', 'label' => 'Items List', 'tag' => '{{order.items_list}}'],
                    ['value' => 'order.first_item_name', 'label' => 'First Item Name', 'tag' => '{{order.first_item_name}}'],
                    ['value' => 'order.discount_amount', 'label' => 'Discount Amount', 'tag' => '{{order.discount_amount}}'],
                    ['value' => 'order.coupon_code', 'label' => 'Coupon Code', 'tag' => '{{order.coupon_code}}'],
                    ['value' => 'order.date', 'label' => 'Date', 'tag' => '{{order.date}}'],
                    ['value' => 'order.tracking_number', 'label' => 'Tracking Number', 'tag' => '{{order.tracking_number}}'],
                ],
            ],
        ];
    }
@endphp

<div
    x-data="{
        open: false,
        search: '',
        highlighted: 0,
        value: @entangle($wireProperty),
        groups: {{ \Illuminate\Support\Js::from($variableFieldGroups) }},
        get flat() {
            return this.filteredGroups.flatMap(g => g.items);
        },
        get filteredGroups() {
            const q = this.search.trim().toLowerCase();
            if (!q) return this.groups;
            return this.groups
                .map(g => ({
                    ...g,
                    items: g.items.filter(i =>
                        i.label.toLowerCase().includes(q) ||
                        i.tag.toLowerCase().includes(q) ||
                        g.group.toLowerCase().includes(q)
                    ),
                }))
                .filter(g => g.items.length > 0);
        },
        get currentItem() {
            if (!this.value) return null;
            if (this.value === 'custom') return { label: 'Custom text', group: '', tag: '' };
            for (const g of this.groups) {
                const hit = g.items.find(i => i.value === this.value);
                if (hit) return { label: hit.label, group: g.group, tag: hit.tag };
            }
            return null;
        },
        toggle() {
            this.open = !this.open;
            if (this.open) {
                this.search = '';
                this.highlighted = 0;
                this.$nextTick(() => this.$refs.search?.focus());
            }
        },
        close() { this.open = false; },
        select(val) { this.value = val; this.close(); },
        navigate(dir) {
            const len = this.flat.length;
            if (!len) return;
            this.highlighted = (this.highlighted + dir + len) % len;
            this.$nextTick(() => {
                const el = this.$refs.list?.querySelector(`[data-idx='${this.highlighted}']`);
                el?.scrollIntoView({ block: 'nearest' });
            });
        },
        enter() {
            const item = this.flat[this.highlighted];
            if (item) this.select(item.value);
        },
        indexOf(val) {
            return this.flat.findIndex(f => f.value === val);
        },
    }"
    @keydown.escape.window="close"
    @click.outside="close"
    class="relative flex-1"
>
    {{-- Trigger --}}
    <button
        type="button"
        @click="toggle"
        :aria-expanded="open"
        class="group flex w-full items-center justify-between gap-2 rounded-lg border border-zinc-200 bg-white px-3 py-2 text-left text-sm text-zinc-900 shadow-sm transition hover:border-zinc-300 focus:outline-none focus:ring-2 focus:ring-zinc-950/10 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100 dark:hover:border-zinc-600 dark:focus:ring-white/15"
    >
        <template x-if="currentItem">
            <span class="flex min-w-0 flex-1 items-center gap-2">
                <span class="truncate" x-text="currentItem.label"></span>
                <span x-show="currentItem.tag" class="hidden truncate rounded bg-zinc-100 px-1.5 py-0.5 font-mono text-[11px] text-zinc-500 sm:inline dark:bg-zinc-700/50 dark:text-zinc-400" x-text="currentItem.tag"></span>
            </span>
        </template>
        <template x-if="!currentItem">
            <span class="text-zinc-400 dark:text-zinc-500">Select a field…</span>
        </template>
        <svg class="h-4 w-4 flex-shrink-0 text-zinc-400 transition-transform duration-150" :class="{ 'rotate-180': open }" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
            <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.06l3.71-3.83a.75.75 0 111.08 1.04l-4.25 4.39a.75.75 0 01-1.08 0L5.21 8.27a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
        </svg>
    </button>

    {{-- Popover --}}
    <div
        x-show="open"
        x-cloak
        x-transition:enter="transition ease-out duration-150"
        x-transition:enter-start="opacity-0 -translate-y-1 scale-[0.98]"
        x-transition:enter-end="opacity-100 translate-y-0 scale-100"
        x-transition:leave="transition ease-in duration-100"
        x-transition:leave-start="opacity-100 translate-y-0 scale-100"
        x-transition:leave-end="opacity-0 -translate-y-1 scale-[0.98]"
        class="absolute left-0 right-0 z-[60] mt-2 overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-2xl shadow-zinc-900/10 ring-1 ring-zinc-950/5 dark:border-zinc-700 dark:bg-zinc-800 dark:shadow-black/40 dark:ring-white/10"
    >
        {{-- Search --}}
        <div class="relative border-b border-zinc-200 dark:border-zinc-700">
            <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-zinc-400" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 100 11 5.5 5.5 0 000-11zM2 9a7 7 0 1112.452 4.391l3.328 3.329a.75.75 0 11-1.06 1.06l-3.329-3.328A7 7 0 012 9z" clip-rule="evenodd" />
            </svg>
            <input
                x-ref="search"
                x-model="search"
                @keydown.arrow-down.prevent="navigate(1)"
                @keydown.arrow-up.prevent="navigate(-1)"
                @keydown.enter.prevent="enter"
                @keydown.escape.prevent="close"
                @input="highlighted = 0"
                type="text"
                placeholder="Search fields, groups, or tokens…"
                class="w-full border-0 bg-transparent py-3 pl-10 pr-3 text-sm text-zinc-900 placeholder-zinc-400 focus:outline-none focus:ring-0 dark:text-zinc-100 dark:placeholder-zinc-500"
            />
        </div>

        {{-- Options --}}
        <div x-ref="list" class="max-h-80 overflow-y-auto overscroll-contain">
            <template x-for="group in filteredGroups" :key="group.group">
                <div class="py-1">
                    <div class="sticky top-0 z-10 flex items-center gap-2 bg-white px-3 py-1.5 dark:bg-zinc-800">
                        <svg class="h-3.5 w-3.5 text-zinc-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" x-html="group.icon"></svg>
                        <span class="text-[10px] font-semibold uppercase tracking-[0.12em] text-zinc-500 dark:text-zinc-400" x-text="group.group"></span>
                        <span class="h-px flex-1 bg-zinc-100 dark:bg-zinc-700/60"></span>
                    </div>
                    <template x-for="item in group.items" :key="item.value">
                        <button
                            type="button"
                            role="option"
                            @click="select(item.value)"
                            @mouseenter="highlighted = indexOf(item.value)"
                            :data-idx="indexOf(item.value)"
                            :aria-selected="value === item.value"
                            :class="{
                                'bg-zinc-950 text-white dark:bg-white dark:text-zinc-950': flat[highlighted] && flat[highlighted].value === item.value,
                                'text-zinc-700 dark:text-zinc-200': !(flat[highlighted] && flat[highlighted].value === item.value),
                            }"
                            class="flex w-full items-center gap-3 px-3 py-2 text-left text-sm transition-colors"
                        >
                            <span class="flex-1 truncate font-medium" x-text="item.label"></span>
                            <span
                                :class="flat[highlighted] && flat[highlighted].value === item.value
                                    ? 'text-white/70 dark:text-zinc-950/70'
                                    : 'text-zinc-400 dark:text-zinc-500'"
                                class="font-mono text-[11px] tracking-tight"
                                x-text="item.tag"
                            ></span>
                            <svg
                                x-show="value === item.value"
                                class="h-4 w-4 flex-shrink-0"
                                :class="flat[highlighted] && flat[highlighted].value === item.value
                                    ? 'text-white dark:text-zinc-950'
                                    : 'text-emerald-500'"
                                viewBox="0 0 20 20"
                                fill="currentColor"
                                aria-hidden="true"
                            >
                                <path fill-rule="evenodd" d="M16.704 5.29a1 1 0 010 1.42l-7.5 7.5a1 1 0 01-1.42 0l-3.5-3.5a1 1 0 011.42-1.42L8.5 12.08l6.79-6.79a1 1 0 011.414 0z" clip-rule="evenodd" />
                            </svg>
                        </button>
                    </template>
                </div>
            </template>
            <div x-show="filteredGroups.length === 0" class="px-4 py-10 text-center text-sm text-zinc-400 dark:text-zinc-500">
                <svg class="mx-auto mb-2 h-8 w-8 text-zinc-300 dark:text-zinc-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/>
                </svg>
                No fields match <span class="font-mono text-zinc-600 dark:text-zinc-300" x-text="`&quot;${search}&quot;`"></span>
            </div>
        </div>

        {{-- Footer --}}
        <div class="flex items-center justify-between gap-3 border-t border-zinc-200 bg-zinc-50 px-3 py-2 text-[11px] text-zinc-500 dark:border-zinc-700 dark:bg-zinc-900/40 dark:text-zinc-400">
            <div class="hidden items-center gap-3 sm:flex">
                <span class="flex items-center gap-1">
                    <kbd class="rounded border border-zinc-200 bg-white px-1.5 py-[1px] font-mono text-[10px] text-zinc-600 shadow-sm dark:border-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">↑↓</kbd>
                    navigate
                </span>
                <span class="flex items-center gap-1">
                    <kbd class="rounded border border-zinc-200 bg-white px-1.5 py-[1px] font-mono text-[10px] text-zinc-600 shadow-sm dark:border-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">↵</kbd>
                    select
                </span>
                <span class="flex items-center gap-1">
                    <kbd class="rounded border border-zinc-200 bg-white px-1.5 py-[1px] font-mono text-[10px] text-zinc-600 shadow-sm dark:border-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">esc</kbd>
                    close
                </span>
            </div>
            <button
                type="button"
                @click="select('custom')"
                :class="value === 'custom'
                    ? 'text-zinc-950 dark:text-white'
                    : 'text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200'"
                class="ml-auto flex items-center gap-1.5 font-medium transition-colors"
            >
                <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" />
                </svg>
                Custom text
            </button>
        </div>
    </div>
</div>
