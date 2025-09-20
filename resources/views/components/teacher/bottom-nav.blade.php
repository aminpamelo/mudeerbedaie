{{-- Teacher Mobile Bottom Navigation --}}
<nav class="fixed-bottom-nav bg-white  border-t border-zinc-200  lg:hidden z-50">
    {{-- Safe area padding for iOS devices --}}
    <div class="pb-safe">
        <div class="flex justify-around items-center px-2 py-2">
            {{-- My Classes --}}
            <a 
                href="{{ route('teacher.classes.index') }}" 
                wire:navigate
                class="flex flex-col items-center justify-center px-3 py-2 min-w-0 text-center group {{ request()->routeIs('teacher.classes.*') ? 'text-blue-600 ' : 'text-zinc-600  hover:text-zinc-900 :text-zinc-100' }}"
            >
                <flux:icon 
                    name="calendar-days" 
                    class="w-5 h-5 mb-1 {{ request()->routeIs('teacher.classes.*') ? 'text-blue-600 ' : 'text-zinc-600  group-hover:text-zinc-900 :text-zinc-100' }}" 
                />
                <span class="text-xs font-medium leading-none {{ request()->routeIs('teacher.classes.*') ? 'text-blue-600 ' : 'text-zinc-600  group-hover:text-zinc-900 :text-zinc-100' }}">
                    My Classes
                </span>
            </a>

            {{-- My Sessions --}}
            <a 
                href="{{ route('teacher.sessions.index') }}" 
                wire:navigate
                class="flex flex-col items-center justify-center px-3 py-2 min-w-0 text-center group {{ request()->routeIs('teacher.sessions.*') ? 'text-blue-600 ' : 'text-zinc-600  hover:text-zinc-900 :text-zinc-100' }}"
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
                class="flex flex-col items-center justify-center px-3 py-2 min-w-0 text-center group {{ request()->routeIs('teacher.students.*') ? 'text-blue-600 ' : 'text-zinc-600  hover:text-zinc-900 :text-zinc-100' }}"
            >
                <flux:icon 
                    name="users" 
                    class="w-5 h-5 mb-1 {{ request()->routeIs('teacher.students.*') ? 'text-blue-600 ' : 'text-zinc-600  group-hover:text-zinc-900 :text-zinc-100' }}" 
                />
                <span class="text-xs font-medium leading-none {{ request()->routeIs('teacher.students.*') ? 'text-blue-600 ' : 'text-zinc-600  group-hover:text-zinc-900 :text-zinc-100' }}">
                    My Students
                </span>
            </a>

            {{-- Timetable --}}
            <a 
                href="{{ route('teacher.timetable') }}" 
                wire:navigate
                class="flex flex-col items-center justify-center px-3 py-2 min-w-0 text-center group {{ request()->routeIs('teacher.timetable') ? 'text-blue-600 ' : 'text-zinc-600  hover:text-zinc-900 :text-zinc-100' }}"
            >
                <flux:icon 
                    name="calendar" 
                    class="w-5 h-5 mb-1 {{ request()->routeIs('teacher.timetable') ? 'text-blue-600 ' : 'text-zinc-600  group-hover:text-zinc-900 :text-zinc-100' }}" 
                />
                <span class="text-xs font-medium leading-none {{ request()->routeIs('teacher.timetable') ? 'text-blue-600 ' : 'text-zinc-600  group-hover:text-zinc-900 :text-zinc-100' }}">
                    Timetable
                </span>
            </a>

            {{-- My Profile --}}
            <a 
                href="{{ route('settings.profile') }}" 
                wire:navigate
                class="flex flex-col items-center justify-center px-3 py-2 min-w-0 text-center group {{ request()->routeIs('settings.*') ? 'text-blue-600 ' : 'text-zinc-600  hover:text-zinc-900 :text-zinc-100' }}"
            >
                <flux:icon 
                    name="user-circle" 
                    class="w-5 h-5 mb-1 {{ request()->routeIs('settings.*') ? 'text-blue-600 ' : 'text-zinc-600  group-hover:text-zinc-900 :text-zinc-100' }}" 
                />
                <span class="text-xs font-medium leading-none {{ request()->routeIs('settings.*') ? 'text-blue-600 ' : 'text-zinc-600  group-hover:text-zinc-900 :text-zinc-100' }}">
                    My Profile
                </span>
            </a>
        </div>
    </div>
</nav>

{{-- Add bottom padding to body to prevent content overlap --}}
<div class="lg:hidden h-20"></div>