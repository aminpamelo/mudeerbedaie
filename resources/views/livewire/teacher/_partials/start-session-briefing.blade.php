@php
    /** @var array|null $briefing */
@endphp

@if($briefing && $briefing['class'])
    <div class="mt-5 space-y-3 text-left">
        {{-- Class context --}}
        <div class="rounded-2xl bg-slate-50 dark:bg-zinc-800/60 ring-1 ring-slate-200 dark:ring-zinc-700 px-4 py-3">
            <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500 dark:text-zinc-400">Class</p>
            <p class="text-sm font-semibold text-slate-900 dark:text-zinc-100 mt-0.5">{{ $briefing['class']->title }}</p>
            @if($briefing['class']->course)
                <p class="text-xs text-slate-500 dark:text-zinc-400">{{ $briefing['class']->course->name }}</p>
            @endif
            <div class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-[11px] text-slate-600 dark:text-zinc-300">
                <span class="inline-flex items-center gap-1">
                    <flux:icon name="clock" class="w-3 h-3" />
                    {{ $briefing['when']->format('g:i A') }}
                </span>
                <span class="inline-flex items-center gap-1">
                    <flux:icon name="bolt" class="w-3 h-3" />
                    {{ $briefing['duration_minutes'] }} min
                </span>
                <span class="inline-flex items-center gap-1">
                    <flux:icon name="users" class="w-3 h-3" />
                    {{ $briefing['student_count'] }} {{ \Illuminate\Support\Str::plural('student', $briefing['student_count']) }}
                </span>
            </div>
        </div>

        {{-- Syllabus to teach --}}
        <div class="rounded-2xl bg-gradient-to-br from-violet-50 to-violet-100/40 dark:from-violet-950/40 dark:to-violet-900/20 ring-1 ring-violet-100 dark:ring-violet-900/40 px-4 py-3">
            <div class="flex items-start gap-2.5">
                <div class="shrink-0 w-7 h-7 rounded-lg bg-violet-600 text-white flex items-center justify-center">
                    <flux:icon name="book-open" class="w-3.5 h-3.5" />
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-violet-700 dark:text-violet-300">What to teach</p>
                    @if($briefing['syllabus']->isNotEmpty())
                        <ul class="mt-1.5 space-y-1.5">
                            @foreach($briefing['syllabus'] as $topic)
                                <li class="text-xs">
                                    <p class="font-semibold text-violet-900 dark:text-violet-200">
                                        <span class="text-violet-500 dark:text-violet-400">#{{ $topic->sort_order + 1 }}</span>
                                        {{ $topic->title }}
                                    </p>
                                    @if($topic->description)
                                        <p class="text-violet-700/80 dark:text-violet-300/80 line-clamp-2 mt-0.5 whitespace-pre-wrap">{{ $topic->description }}</p>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <p class="mt-1 text-xs text-violet-700/80 dark:text-violet-300/80">
                            @if($briefing['session'])
                                No specific syllabus topic assigned for this session.
                            @else
                                Per-session syllabus is set after the session is created.
                            @endif
                        </p>
                    @endif
                </div>
            </div>
        </div>

        {{-- Upsell funnels to share --}}
        @if($briefing['upsell_funnels']->isNotEmpty())
            <div class="rounded-2xl bg-gradient-to-br from-emerald-50 to-emerald-100/40 dark:from-emerald-950/40 dark:to-emerald-900/20 ring-1 ring-emerald-100 dark:ring-emerald-900/40 px-4 py-3">
                <div class="flex items-start gap-2.5">
                    <div class="shrink-0 w-7 h-7 rounded-lg bg-emerald-600 text-white flex items-center justify-center">
                        <flux:icon name="megaphone" class="w-3.5 h-3.5" />
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-[11px] font-semibold uppercase tracking-wider text-emerald-700 dark:text-emerald-300">Upsell to share</p>
                        <ul class="mt-1.5 space-y-1.5">
                            @foreach($briefing['upsell_funnels'] as $funnel)
                                <li
                                    class="flex items-center justify-between gap-2 text-xs"
                                    x-data="{
                                        copied: false,
                                        async copy() {
                                            try {
                                                await navigator.clipboard.writeText(@js($funnel->getPublicUrl()));
                                            } catch (e) {
                                                const ta = document.createElement('textarea');
                                                ta.value = @js($funnel->getPublicUrl());
                                                document.body.appendChild(ta);
                                                ta.select();
                                                document.execCommand('copy');
                                                ta.remove();
                                            }
                                            this.copied = true;
                                            setTimeout(() => this.copied = false, 1500);
                                        }
                                    }"
                                >
                                    <span class="font-semibold text-emerald-900 dark:text-emerald-200 truncate">{{ $funnel->name }}</span>
                                    <div class="flex items-center gap-1 shrink-0">
                                        <button
                                            type="button"
                                            @click="copy()"
                                            class="inline-flex items-center gap-1 px-2 py-1 rounded-lg bg-white dark:bg-emerald-900/40 hover:bg-emerald-50 dark:hover:bg-emerald-900/60 text-emerald-700 dark:text-emerald-300 text-[11px] font-medium ring-1 ring-emerald-300 dark:ring-emerald-700 transition-colors"
                                            :title="copied ? 'Copied!' : 'Copy funnel link'"
                                        >
                                            <flux:icon name="clipboard" class="w-3 h-3" x-show="!copied" />
                                            <flux:icon name="check" class="w-3 h-3" x-show="copied" x-cloak />
                                            <span x-text="copied ? 'Copied' : 'Copy'"></span>
                                        </button>
                                        <a
                                            href="{{ $funnel->getPublicUrl() }}"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            class="inline-flex items-center gap-1 px-2 py-1 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-[11px] font-medium transition-colors"
                                        >
                                            <flux:icon name="arrow-top-right-on-square" class="w-3 h-3" />
                                            Open
                                        </a>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
        @endif

        {{-- PIC contact --}}
        @if($briefing['pics']->isNotEmpty())
            <div class="rounded-2xl bg-slate-50 dark:bg-zinc-800/60 ring-1 ring-slate-200 dark:ring-zinc-700 px-4 py-3">
                <div class="flex items-start gap-2.5">
                    <div class="shrink-0 w-7 h-7 rounded-lg bg-slate-600 text-white flex items-center justify-center">
                        <flux:icon name="lifebuoy" class="w-3.5 h-3.5" />
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500 dark:text-zinc-400">Need help? Contact PIC</p>
                        <p class="mt-0.5 text-xs font-medium text-slate-800 dark:text-zinc-200">{{ $briefing['pics']->pluck('name')->join(', ') }}</p>
                    </div>
                </div>
            </div>
        @endif
    </div>
@endif
