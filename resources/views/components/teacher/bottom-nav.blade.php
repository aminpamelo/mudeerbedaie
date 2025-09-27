{{-- Teacher Mobile Bottom Navigation --}}
<nav class="fixed-bottom-nav bg-white  border-t border-zinc-200  lg:hidden z-50">
    {{-- Safe area padding for iOS devices --}}
    <div class="pb-safe">
        <div class="flex justify-around items-end px-2 py-2">
            {{-- Dashboard --}}
            <a
                href="{{ route('teacher.dashboard') }}"
                wire:navigate
                class="flex flex-col items-center justify-center px-2 py-2 min-w-0 text-center group {{ request()->routeIs('teacher.dashboard') ? 'text-blue-600 ' : 'text-zinc-600  hover:text-zinc-900 :text-zinc-100' }}"
            >
                <flux:icon
                    name="home"
                    class="w-5 h-5 mb-1 {{ request()->routeIs('teacher.dashboard') ? 'text-blue-600 ' : 'text-zinc-600  group-hover:text-zinc-900 :text-zinc-100' }}"
                />
                <span class="text-xs font-medium leading-none {{ request()->routeIs('teacher.dashboard') ? 'text-blue-600 ' : 'text-zinc-600  group-hover:text-zinc-900 :text-zinc-100' }}">
                    Dashboard
                </span>
            </a>

            {{-- My Classes --}}
            <a
                href="{{ route('teacher.classes.index') }}"
                wire:navigate
                class="flex flex-col items-center justify-center px-2 py-2 min-w-0 text-center group {{ request()->routeIs('teacher.classes.*') ? 'text-blue-600 ' : 'text-zinc-600  hover:text-zinc-900 :text-zinc-100' }}"
            >
                <flux:icon
                    name="calendar-days"
                    class="w-5 h-5 mb-1 {{ request()->routeIs('teacher.classes.*') ? 'text-blue-600 ' : 'text-zinc-600  group-hover:text-zinc-900 :text-zinc-100' }}"
                />
                <span class="text-xs font-medium leading-none {{ request()->routeIs('teacher.classes.*') ? 'text-blue-600 ' : 'text-zinc-600  group-hover:text-zinc-900 :text-zinc-100' }}">
                    My Classes
                </span>
            </a>

            {{-- Timetable (Highlighted/Featured) --}}
            <a
                href="{{ route('teacher.timetable') }}"
                wire:navigate
                class="relative flex flex-col items-center justify-center px-3 py-1 min-w-0 text-center group"
            >
                {{-- Spotlight background --}}
                <div class="{{ request()->routeIs('teacher.timetable') ? 'bg-blue-600  shadow-lg border-2 border-blue-500 ' : 'bg-blue-500  hover:bg-blue-600  border-2 border-blue-400 ' }} rounded-full p-2 mb-1 transform transition-all duration-200 hover:scale-105">
                    <flux:icon
                        name="calendar"
                        class="w-6 h-6 text-white"
                    />
                </div>
                <span class="text-xs font-bold {{ request()->routeIs('teacher.timetable') ? 'text-blue-600 ' : 'text-blue-500  group-hover:text-blue-600 ' }}">
                    Timetable
                </span>
                {{-- Spotlight indicator --}}
                @if(request()->routeIs('teacher.timetable'))
                    <div class="absolute -top-1 -right-1 w-3 h-3 bg-orange-400 rounded-full border-2 border-white  animate-pulse"></div>
                @endif
            </a>

            {{-- My Sessions --}}
            <a
                href="{{ route('teacher.sessions.index') }}"
                wire:navigate
                class="flex flex-col items-center justify-center px-2 py-2 min-w-0 text-center group {{ request()->routeIs('teacher.sessions.*') ? 'text-blue-600 ' : 'text-zinc-600  hover:text-zinc-900 :text-zinc-100' }}"
            >
                <flux:icon
                    name="clock"
                    class="w-5 h-5 mb-1 {{ request()->routeIs('teacher.sessions.*') ? 'text-blue-600 ' : 'text-zinc-600  group-hover:text-zinc-900 :text-zinc-100' }}"
                />
                <span class="text-xs font-medium leading-none {{ request()->routeIs('teacher.sessions.*') ? 'text-blue-600 ' : 'text-zinc-600  group-hover:text-zinc-900 :text-zinc-100' }}">
                    My Sessions
                </span>
            </a>

            {{-- My Students --}}
            <a
                href="{{ route('teacher.students.index') }}"
                wire:navigate
                class="flex flex-col items-center justify-center px-2 py-2 min-w-0 text-center group {{ request()->routeIs('teacher.students.*') ? 'text-blue-600 ' : 'text-zinc-600  hover:text-zinc-900 :text-zinc-100' }}"
            >
                <flux:icon
                    name="users"
                    class="w-5 h-5 mb-1 {{ request()->routeIs('teacher.students.*') ? 'text-blue-600 ' : 'text-zinc-600  group-hover:text-zinc-900 :text-zinc-100' }}"
                />
                <span class="text-xs font-medium leading-none {{ request()->routeIs('teacher.students.*') ? 'text-blue-600 ' : 'text-zinc-600  group-hover:text-zinc-900 :text-zinc-100' }}">
                    My Students
                </span>
            </a>
        </div>
    </div>
</nav>

{{-- Add bottom padding to body to prevent content overlap --}}
<div class="lg:hidden h-20"></div>