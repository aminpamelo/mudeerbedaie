<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="teacher-app min-h-screen bg-gradient-to-br from-slate-50 via-white to-violet-50/40 dark:from-zinc-950 dark:via-slate-950 dark:to-violet-950/40">
        <flux:sidebar sticky stashable class="border-e border-zinc-200/70 bg-white/90 backdrop-blur dark:border-zinc-800/70 dark:bg-zinc-950/80">
            <flux:sidebar.toggle class="lg:hidden" icon="x-mark" />

            {{-- Gradient brand mark for teachers --}}
            <a href="{{ route('teacher.dashboard') }}" class="me-5 group flex items-center gap-2.5 rtl:space-x-reverse" wire:navigate>
                <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-violet-700 via-violet-600 to-violet-500 shadow-lg shadow-violet-500/30 flex items-center justify-center text-white group-hover:scale-105 transition-transform">
                    <flux:icon name="academic-cap" class="w-5 h-5" />
                </div>
                <div class="flex flex-col leading-tight">
                    <span class="teacher-display text-[15px] font-bold text-slate-900 dark:text-white">Mudeer Bedaie</span>
                    <span class="text-[10px] font-semibold uppercase tracking-[0.18em] bg-gradient-to-r from-violet-700 to-violet-600 dark:from-violet-400 dark:to-violet-400 bg-clip-text text-transparent">Teacher</span>
                </div>
            </a>

            <flux:navlist variant="outline">
                <flux:navlist.group :heading="__('Platform')" class="grid">
                    <flux:navlist.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>{{ __('Dashboard') }}</flux:navlist.item>
                </flux:navlist.group>

                @if(auth()->user()->isTeacher())
                <flux:navlist.group :heading="__('Teaching')" class="grid">
                    <flux:navlist.item icon="calendar-days" :href="route('teacher.classes.index')" :current="request()->routeIs('teacher.classes.*')" wire:navigate>{{ __('My Classes') }}</flux:navlist.item>
                    <flux:navlist.item icon="clock" :href="route('teacher.sessions.index')" :current="request()->routeIs('teacher.sessions.*')" wire:navigate>{{ __('My Sessions') }}</flux:navlist.item>
                    <flux:navlist.item icon="users" :href="route('teacher.students.index')" :current="request()->routeIs('teacher.students.*')" wire:navigate>{{ __('Students') }}</flux:navlist.item>
                    <flux:navlist.item icon="calendar" :href="route('teacher.timetable')" :current="request()->routeIs('teacher.timetable')" wire:navigate>{{ __('Timetable') }}</flux:navlist.item>
                </flux:navlist.group>

                {{-- Decorative gradient promo card to anchor the teacher identity --}}
                <div class="mt-6 mx-2 rounded-2xl bg-gradient-to-br from-violet-700 via-violet-600 to-violet-500 p-4 text-white shadow-lg shadow-violet-500/25 relative overflow-hidden">
                    <div class="teacher-grain absolute inset-0 pointer-events-none"></div>
                    <div class="relative">
                        <div class="flex items-center gap-1.5 mb-2">
                            <span class="teacher-live-dot bg-white !shadow-none"></span>
                            <span class="text-[10px] font-bold uppercase tracking-[0.18em] text-white/90">Today</span>
                        </div>
                        <p class="teacher-display text-sm font-bold leading-snug">
                            {{ now()->format('l') }}
                        </p>
                        <p class="text-xs text-white/80 mt-0.5">{{ now()->format('j F Y') }}</p>
                        <a href="{{ route('teacher.timetable') }}" wire:navigate class="mt-3 inline-flex items-center gap-1 text-xs font-semibold text-white hover:text-white/90">
                            View timetable
                            <flux:icon name="arrow-right" class="w-3.5 h-3.5" />
                        </a>
                    </div>
                </div>
                @endif
            </flux:navlist>

            <flux:spacer />


            <!-- Desktop User Menu -->
            <flux:dropdown class="hidden lg:block" position="bottom" align="start">
                <flux:profile
                    :name="auth()->user()->name"
                    :initials="auth()->user()->initials()"
                    icon:trailing="chevrons-up-down"
                />

                <flux:menu class="w-[220px]">
                    <flux:menu.radio.group>
                        <div class="p-0 text-sm font-normal">
                            <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                                <span class="relative flex h-8 w-8 shrink-0 overflow-hidden rounded-lg">
                                    <span
                                        class="flex h-full w-full items-center justify-center rounded-lg bg-neutral-200 text-black"
                                    >
                                        {{ auth()->user()->initials() }}
                                    </span>
                                </span>

                                <div class="grid flex-1 text-start text-sm leading-tight">
                                    <span class="truncate font-semibold">{{ auth()->user()->name }}</span>
                                    <span class="truncate text-xs">{{ auth()->user()->email }}</span>
                                </div>
                            </div>
                        </div>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <flux:menu.radio.group>
                        <flux:menu.item :href="route('settings.profile')" icon="cog" wire:navigate>{{ __('Settings') }}</flux:menu.item>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle" class="w-full">
                            {{ __('Log Out') }}
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </flux:sidebar>

        <!-- Mobile User Menu -->
        <flux:header class="lg:hidden">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

            <flux:spacer />

            <flux:dropdown position="top" align="end">
                <flux:profile
                    :initials="auth()->user()->initials()"
                    icon-trailing="chevron-down"
                />

                <flux:menu>
                    <flux:menu.radio.group>
                        <div class="p-0 text-sm font-normal">
                            <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                                <span class="relative flex h-8 w-8 shrink-0 overflow-hidden rounded-lg">
                                    <span
                                        class="flex h-full w-full items-center justify-center rounded-lg bg-neutral-200 text-black"
                                    >
                                        {{ auth()->user()->initials() }}
                                    </span>
                                </span>

                                <div class="grid flex-1 text-start text-sm leading-tight">
                                    <span class="truncate font-semibold">{{ auth()->user()->name }}</span>
                                    <span class="truncate text-xs">{{ auth()->user()->email }}</span>
                                </div>
                            </div>
                        </div>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <flux:menu.radio.group>
                        <flux:menu.item :href="route('settings.profile')" icon="cog" wire:navigate>{{ __('Settings') }}</flux:menu.item>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle" class="w-full">
                            {{ __('Log Out') }}
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </flux:header>

        <!-- Main content with padding for mobile bottom navigation -->
        <flux:main class="lg:pb-0 pb-20">
            {{ $slot }}
        </flux:main>

        <!-- Include the teacher bottom navigation for mobile -->
        @if(auth()->user()->isTeacher())
            <x-teacher.bottom-nav />
        @endif

        @fluxScripts
        @stack('scripts')
    </body>
</html>