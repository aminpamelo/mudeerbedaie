<?php

use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Volt\Component;

new class extends Component {
    #[Computed]
    public function unreadCount(): int
    {
        return auth()->user()->unreadNotifications()->count();
    }

    /**
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    #[Computed]
    public function items()
    {
        return auth()->user()->notifications()->latest()->limit(10)->get()->map(function ($n) {
            $data = $n->data;

            if (is_string($data)) {
                $data = json_decode($data, true) ?: [];
            }

            return [
                'id' => $n->id,
                'title' => $data['title'] ?? Str::headline(class_basename($n->type)),
                'body' => $data['message'] ?? $data['body'] ?? null,
                'url' => $data['url'] ?? $data['action_url'] ?? null,
                'icon' => $this->iconFor($data['icon'] ?? $data['kind'] ?? null),
                'unread' => is_null($n->read_at),
                'time' => $n->created_at?->diffForHumans(),
            ];
        });
    }

    protected function iconFor(?string $hint): string
    {
        return match (true) {
            $hint === null => 'bell',
            Str::contains($hint, ['alert', 'warning', 'late', 'triangle']) => 'exclamation-triangle',
            Str::contains($hint, ['ticket', 'it_']) => 'ticket',
            Str::contains($hint, ['order', 'sale', 'purchase']) => 'shopping-bag',
            Str::contains($hint, ['schedule', 'assign', 'calendar']) => 'calendar-days',
            Str::contains($hint, ['payment', 'refund', 'money', 'gaji']) => 'banknotes',
            Str::contains($hint, ['check', 'success', 'resolved']) => 'check-circle',
            default => 'bell',
        };
    }

    public function markRead(string $id): void
    {
        auth()->user()->notifications()->where('id', $id)->whereNull('read_at')->update(['read_at' => now()]);
        unset($this->unreadCount, $this->items);
    }

    public function markAllRead(): void
    {
        auth()->user()->unreadNotifications->markAsRead();
        unset($this->unreadCount, $this->items);
    }
}; ?>

<flux:dropdown position="bottom" align="end">
    <button
        type="button"
        class="relative flex size-10 items-center justify-center rounded-xl border border-zinc-200 bg-white/70 text-zinc-500 shadow-sm backdrop-blur transition hover:border-indigo-300 hover:text-indigo-600 hover:shadow-md dark:border-zinc-700 dark:bg-zinc-800/60 dark:text-zinc-300 dark:hover:border-indigo-500/50"
        aria-label="{{ __('Notifications') }}"
    >
        <flux:icon name="bell" class="size-5" />
        @if($this->unreadCount > 0)
            <span class="absolute -right-1 -top-1 flex min-w-[18px] items-center justify-center rounded-full bg-gradient-to-br from-rose-500 to-fuchsia-600 px-1 text-[10px] font-bold text-white shadow ring-2 ring-white dark:ring-zinc-900">
                {{ $this->unreadCount > 99 ? '99+' : $this->unreadCount }}
            </span>
            <span class="absolute -right-1 -top-1 size-[18px] animate-ping rounded-full bg-rose-400/60"></span>
        @endif
    </button>

    <flux:menu class="w-[360px] !p-0">
        {{-- Header --}}
        <div class="flex items-center justify-between border-b border-zinc-100 px-4 py-3 dark:border-zinc-800">
            <div class="flex items-center gap-2">
                <span class="flex size-7 items-center justify-center rounded-lg bg-gradient-to-br from-indigo-500 to-violet-600 text-white shadow">
                    <flux:icon name="bell" class="size-4" />
                </span>
                <span class="text-sm font-semibold text-zinc-800 dark:text-zinc-100">{{ __('Notifications') }}</span>
                @if($this->unreadCount > 0)
                    <span class="rounded-full bg-indigo-100 px-2 py-0.5 text-[10px] font-bold text-indigo-600 dark:bg-indigo-500/20 dark:text-indigo-300">{{ $this->unreadCount }} {{ __('new') }}</span>
                @endif
            </div>
            @if($this->unreadCount > 0)
                <button type="button" wire:click="markAllRead" class="text-xs font-medium text-indigo-500 transition hover:text-indigo-700">
                    {{ __('Mark all read') }}
                </button>
            @endif
        </div>

        {{-- List --}}
        <div class="max-h-[380px] overflow-y-auto">
            @forelse($this->items as $item)
                <a
                    @if($item['url']) href="{{ $item['url'] }}" wire:navigate @endif
                    wire:click="markRead('{{ $item['id'] }}')"
                    wire:key="notif-{{ $item['id'] }}"
                    class="flex items-start gap-3 border-b border-zinc-50 px-4 py-3 transition hover:bg-zinc-50 dark:border-zinc-800/50 dark:hover:bg-zinc-800/50 {{ $item['unread'] ? 'bg-indigo-50/40 dark:bg-indigo-500/5' : '' }}"
                >
                    <span class="mt-0.5 flex size-9 shrink-0 items-center justify-center rounded-xl {{ $item['unread'] ? 'bg-gradient-to-br from-indigo-500 to-violet-600 text-white' : 'bg-zinc-100 text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400' }}">
                        <flux:icon :name="$item['icon']" class="size-4" />
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="flex items-start justify-between gap-2">
                            <span class="text-sm font-semibold text-zinc-800 dark:text-zinc-100">{{ $item['title'] }}</span>
                            @if($item['unread'])
                                <span class="mt-1 size-2 shrink-0 rounded-full bg-indigo-500"></span>
                            @endif
                        </span>
                        @if($item['body'])
                            <span class="mt-0.5 block text-xs leading-relaxed text-zinc-500 dark:text-zinc-400">{{ Str::limit($item['body'], 100) }}</span>
                        @endif
                        @if($item['time'])
                            <span class="mt-1 block text-[11px] text-zinc-400">{{ $item['time'] }}</span>
                        @endif
                    </span>
                </a>
            @empty
                <div class="flex flex-col items-center gap-2 px-4 py-12 text-center">
                    <span class="flex size-12 items-center justify-center rounded-full bg-gradient-to-br from-indigo-100 to-violet-100 dark:from-indigo-500/10 dark:to-violet-500/10">
                        <flux:icon name="bell-slash" class="size-6 text-indigo-400" />
                    </span>
                    <p class="text-sm font-medium text-zinc-600 dark:text-zinc-300">{{ __('All caught up!') }}</p>
                    <p class="text-xs text-zinc-400">{{ __('No notifications right now.') }}</p>
                </div>
            @endforelse
        </div>
    </flux:menu>
</flux:dropdown>
