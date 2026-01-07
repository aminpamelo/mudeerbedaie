{{-- Student Mobile Bottom Navigation --}}
@php
    $student = auth()->user()->student ?? null;

    // Badge counts
    $ongoingSessionsCount = 0;
    $activeClassesCount = 0;
    $todaySessionsCount = 0;
    $pendingOrdersCount = 0;

    if ($student) {
        // Get ongoing sessions (live now)
        $ongoingSessionsCount = \App\Models\ClassSession::whereHas('class.classStudents', function($q) use ($student) {
            $q->where('student_id', $student->id)->where('status', 'active');
        })->where('status', 'ongoing')->count();

        // Get active classes count
        $activeClassesCount = $student->activeClasses()->count();

        // Get today's sessions count
        $todaySessionsCount = \App\Models\ClassSession::whereHas('class.classStudents', function($q) use ($student) {
            $q->where('student_id', $student->id)->where('status', 'active');
        })->whereDate('session_date', today())->count();

        // Get pending orders count
        $pendingOrdersCount = $student->orders()->where('status', 'pending')->count();
    }
@endphp

<nav class="fixed-bottom-nav bg-white border-t border-zinc-200 lg:hidden z-50">
    {{-- Safe area padding for iOS devices --}}
    <div class="pb-safe">
        <div class="flex justify-around items-end px-2 py-2">
            {{-- Home --}}
            <a
                href="{{ route('student.dashboard') }}"
                wire:navigate
                class="relative flex flex-col items-center justify-center px-2 py-2 min-w-0 text-center group {{ request()->routeIs('student.dashboard') ? 'text-blue-600' : 'text-zinc-600 hover:text-zinc-900' }}"
            >
                <div class="relative">
                    <flux:icon
                        name="home"
                        class="w-5 h-5 mb-1 {{ request()->routeIs('student.dashboard') ? 'text-blue-600' : 'text-zinc-600 group-hover:text-zinc-900' }}"
                    />
                    @if($ongoingSessionsCount > 0)
                        <span class="absolute -top-1 -right-1 min-w-[16px] h-4 px-1 text-[10px] font-bold text-white bg-red-500 rounded-full flex items-center justify-center animate-pulse">
                            {{ $ongoingSessionsCount }}
                        </span>
                    @endif
                </div>
                <span class="text-xs font-medium leading-none {{ request()->routeIs('student.dashboard') ? 'text-blue-600' : 'text-zinc-600 group-hover:text-zinc-900' }}">
                    {{ __('navigation.home') }}
                </span>
            </a>

            {{-- My Classes --}}
            <a
                href="{{ route('student.classes.index') }}"
                wire:navigate
                class="relative flex flex-col items-center justify-center px-2 py-2 min-w-0 text-center group {{ request()->routeIs('student.classes.*') ? 'text-blue-600' : 'text-zinc-600 hover:text-zinc-900' }}"
            >
                <div class="relative">
                    <flux:icon
                        name="academic-cap"
                        class="w-5 h-5 mb-1 {{ request()->routeIs('student.classes.*') ? 'text-blue-600' : 'text-zinc-600 group-hover:text-zinc-900' }}"
                    />
                    @if($activeClassesCount > 0)
                        <span class="absolute -top-1 -right-1 min-w-[16px] h-4 px-1 text-[10px] font-bold text-white bg-blue-500 rounded-full flex items-center justify-center">
                            {{ $activeClassesCount }}
                        </span>
                    @endif
                </div>
                <span class="text-xs font-medium leading-none {{ request()->routeIs('student.classes.*') ? 'text-blue-600' : 'text-zinc-600 group-hover:text-zinc-900' }}">
                    {{ __('navigation.classes') }}
                </span>
            </a>

            {{-- Schedule (Featured Center Tab) --}}
            <a
                href="{{ route('student.timetable') }}"
                wire:navigate
                class="relative flex flex-col items-center justify-center px-3 py-1 min-w-0 text-center group"
            >
                {{-- Featured background --}}
                <div class="relative {{ request()->routeIs('student.timetable') ? 'bg-blue-600 shadow-lg border-2 border-blue-500' : 'bg-blue-500 hover:bg-blue-600 border-2 border-blue-400' }} rounded-full p-2 mb-1 transform transition-all duration-200 hover:scale-105">
                    <flux:icon
                        name="calendar"
                        class="w-6 h-6 text-white"
                    />
                    @if($todaySessionsCount > 0)
                        <span class="absolute -top-1 -right-1 min-w-[18px] h-[18px] px-1 text-[10px] font-bold text-white bg-orange-500 rounded-full flex items-center justify-center border-2 border-white">
                            {{ $todaySessionsCount }}
                        </span>
                    @endif
                </div>
                <span class="text-xs font-bold {{ request()->routeIs('student.timetable') ? 'text-blue-600' : 'text-blue-500 group-hover:text-blue-600' }}">
                    {{ __('navigation.schedule') }}
                </span>
            </a>

            {{-- Courses --}}
            <a
                href="{{ route('student.courses') }}"
                wire:navigate
                class="flex flex-col items-center justify-center px-2 py-2 min-w-0 text-center group {{ request()->routeIs('student.courses*') || request()->routeIs('student.subscriptions*') ? 'text-blue-600' : 'text-zinc-600 hover:text-zinc-900' }}"
            >
                <flux:icon
                    name="book-open"
                    class="w-5 h-5 mb-1 {{ request()->routeIs('student.courses*') || request()->routeIs('student.subscriptions*') ? 'text-blue-600' : 'text-zinc-600 group-hover:text-zinc-900' }}"
                />
                <span class="text-xs font-medium leading-none {{ request()->routeIs('student.courses*') || request()->routeIs('student.subscriptions*') ? 'text-blue-600' : 'text-zinc-600 group-hover:text-zinc-900' }}">
                    {{ __('navigation.courses') }}
                </span>
            </a>

            {{-- Account --}}
            <a
                href="{{ route('student.account') }}"
                wire:navigate
                class="relative flex flex-col items-center justify-center px-2 py-2 min-w-0 text-center group {{ request()->routeIs('student.account') || request()->routeIs('student.orders*') || request()->routeIs('student.payment-methods*') || request()->routeIs('student.invoices*') || request()->routeIs('student.payments*') || request()->routeIs('settings.*') ? 'text-blue-600' : 'text-zinc-600 hover:text-zinc-900' }}"
            >
                <div class="relative">
                    <flux:icon
                        name="user-circle"
                        class="w-5 h-5 mb-1 {{ request()->routeIs('student.account') || request()->routeIs('student.orders*') || request()->routeIs('student.payment-methods*') || request()->routeIs('student.invoices*') || request()->routeIs('student.payments*') || request()->routeIs('settings.*') ? 'text-blue-600' : 'text-zinc-600 group-hover:text-zinc-900' }}"
                    />
                    @if($pendingOrdersCount > 0)
                        <span class="absolute -top-1 -right-1 min-w-[16px] h-4 px-1 text-[10px] font-bold text-white bg-orange-500 rounded-full flex items-center justify-center">
                            {{ $pendingOrdersCount }}
                        </span>
                    @endif
                </div>
                <span class="text-xs font-medium leading-none {{ request()->routeIs('student.account') || request()->routeIs('student.orders*') || request()->routeIs('student.payment-methods*') || request()->routeIs('student.invoices*') || request()->routeIs('student.payments*') || request()->routeIs('settings.*') ? 'text-blue-600' : 'text-zinc-600 group-hover:text-zinc-900' }}">
                    {{ __('navigation.account') }}
                </span>
            </a>
        </div>
    </div>
</nav>

