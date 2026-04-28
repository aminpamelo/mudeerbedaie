@props([
    'tabs' => [],
    'active' => '',
    'wireMethod' => 'setActiveTab',
])

<div class="teacher-card p-1.5 mb-6 inline-flex max-w-full overflow-x-auto">
    <div class="flex items-center gap-1">
        @foreach($tabs as $tab)
            @php
                $isActive = ($active === $tab['key']);
            @endphp
            <button
                type="button"
                wire:click="{{ $wireMethod }}('{{ $tab['key'] }}')"
                wire:key="teacher-tab-{{ $tab['key'] }}"
                @class([
                    'inline-flex items-center gap-1.5 rounded-lg px-3.5 py-2 text-sm font-semibold whitespace-nowrap transition-all',
                    'bg-gradient-to-r from-violet-600 to-violet-500 text-white shadow-md shadow-violet-500/30' => $isActive,
                    'text-slate-600 hover:text-violet-700 hover:bg-violet-50 dark:text-zinc-300 dark:hover:text-violet-300 dark:hover:bg-violet-500/10' => !$isActive,
                ])
            >
                @if(!empty($tab['icon']))
                    <flux:icon name="{{ $tab['icon'] }}" class="w-4 h-4" />
                @endif
                <span>{{ $tab['label'] }}</span>
                @if(isset($tab['badge']) && $tab['badge'] !== '' && $tab['badge'] !== null)
                    <span @class([
                        'ml-0.5 rounded-full px-1.5 text-[10px] font-bold',
                        'bg-white/20 text-white' => $isActive,
                        'bg-violet-100 text-violet-700 dark:bg-violet-500/20 dark:text-violet-300' => !$isActive,
                    ])>
                        {{ $tab['badge'] }}
                    </span>
                @endif
            </button>
        @endforeach
    </div>
</div>
