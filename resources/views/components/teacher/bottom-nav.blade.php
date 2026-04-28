{{-- Teacher Mobile Bottom Navigation - modern colorful redesign --}}
<nav class="fixed-bottom-nav teacher-bottom-nav lg:hidden z-50">
    {{-- Safe area padding for iOS devices --}}
    <div class="pb-safe">
        <div class="grid grid-cols-5 items-end px-1.5 pt-2 pb-1.5 gap-0.5">
            {{-- Dashboard --}}
            <a
                href="{{ route('teacher.dashboard') }}"
                wire:navigate
                @class([
                    'teacher-bottom-link flex flex-col items-center justify-center gap-1 px-1 py-2 rounded-xl text-center group',
                    'is-active' => request()->routeIs('teacher.dashboard'),
                ])
            >
                <div @class([
                    'flex items-center justify-center w-9 h-7 rounded-lg transition-all',
                    'bg-gradient-to-br from-violet-500/15 to-violet-500/15 dark:from-violet-400/20 dark:to-violet-400/20' => request()->routeIs('teacher.dashboard'),
                ])>
                    <flux:icon name="home" class="w-5 h-5" variant="{{ request()->routeIs('teacher.dashboard') ? 'solid' : 'outline' }}" />
                </div>
                <span class="text-[10px] font-semibold leading-none">Home</span>
            </a>

            {{-- My Classes --}}
            <a
                href="{{ route('teacher.classes.index') }}"
                wire:navigate
                @class([
                    'teacher-bottom-link flex flex-col items-center justify-center gap-1 px-1 py-2 rounded-xl text-center group',
                    'is-active' => request()->routeIs('teacher.classes.*'),
                ])
            >
                <div @class([
                    'flex items-center justify-center w-9 h-7 rounded-lg transition-all',
                    'bg-gradient-to-br from-violet-500/15 to-violet-500/15 dark:from-violet-400/20 dark:to-violet-400/20' => request()->routeIs('teacher.classes.*'),
                ])>
                    <flux:icon name="academic-cap" class="w-5 h-5" variant="{{ request()->routeIs('teacher.classes.*') ? 'solid' : 'outline' }}" />
                </div>
                <span class="text-[10px] font-semibold leading-none">Classes</span>
            </a>

            {{-- Timetable (Centered/Featured) --}}
            <a
                href="{{ route('teacher.timetable') }}"
                wire:navigate
                class="relative flex flex-col items-center justify-end -mt-6 px-1 group"
            >
                <div class="teacher-bottom-spotlight relative rounded-full p-3.5 mb-1.5 ring-4 ring-white/90 dark:ring-zinc-950/80">
                    <flux:icon name="calendar" class="w-6 h-6 text-white" variant="solid" />
                    @if(request()->routeIs('teacher.timetable'))
                        <span class="absolute -top-0.5 -right-0.5 w-3 h-3 rounded-full bg-emerald-400 ring-2 ring-white dark:ring-zinc-950 animate-pulse"></span>
                    @endif
                </div>
                <span class="text-[10px] font-bold leading-none bg-gradient-to-r from-violet-700 to-violet-600 dark:from-violet-400 dark:to-violet-400 bg-clip-text text-transparent">Timetable</span>
            </a>

            {{-- My Sessions --}}
            <a
                href="{{ route('teacher.sessions.index') }}"
                wire:navigate
                @class([
                    'teacher-bottom-link flex flex-col items-center justify-center gap-1 px-1 py-2 rounded-xl text-center group',
                    'is-active' => request()->routeIs('teacher.sessions.*'),
                ])
            >
                <div @class([
                    'flex items-center justify-center w-9 h-7 rounded-lg transition-all',
                    'bg-gradient-to-br from-violet-500/15 to-violet-500/15 dark:from-violet-400/20 dark:to-violet-400/20' => request()->routeIs('teacher.sessions.*'),
                ])>
                    <flux:icon name="clock" class="w-5 h-5" variant="{{ request()->routeIs('teacher.sessions.*') ? 'solid' : 'outline' }}" />
                </div>
                <span class="text-[10px] font-semibold leading-none">Sessions</span>
            </a>

            {{-- My Students --}}
            <a
                href="{{ route('teacher.students.index') }}"
                wire:navigate
                @class([
                    'teacher-bottom-link flex flex-col items-center justify-center gap-1 px-1 py-2 rounded-xl text-center group',
                    'is-active' => request()->routeIs('teacher.students.*'),
                ])
            >
                <div @class([
                    'flex items-center justify-center w-9 h-7 rounded-lg transition-all',
                    'bg-gradient-to-br from-violet-500/15 to-violet-500/15 dark:from-violet-400/20 dark:to-violet-400/20' => request()->routeIs('teacher.students.*'),
                ])>
                    <flux:icon name="users" class="w-5 h-5" variant="{{ request()->routeIs('teacher.students.*') ? 'solid' : 'outline' }}" />
                </div>
                <span class="text-[10px] font-semibold leading-none">Students</span>
            </a>
        </div>
    </div>
</nav>

{{-- Add bottom padding to body to prevent content overlap --}}
<div class="lg:hidden h-20"></div>
