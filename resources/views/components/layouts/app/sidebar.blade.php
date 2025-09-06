<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white dark:bg-zinc-800">
        <flux:sidebar sticky stashable class="border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:sidebar.toggle class="lg:hidden" icon="x-mark" />

            <a href="{{ route('dashboard') }}" class="me-5 flex items-center space-x-2 rtl:space-x-reverse" wire:navigate>
                <x-app-logo />
            </a>

            <flux:navlist variant="outline">
                <flux:navlist.group :heading="__('Platform')" class="grid">
                    <flux:navlist.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>{{ __('Dashboard') }}</flux:navlist.item>
                </flux:navlist.group>
                
                @if(auth()->user()->isAdmin())
                <flux:navlist.group :heading="__('Administration')" class="grid">
                    <flux:navlist.item icon="academic-cap" :href="route('courses.index')" :current="request()->routeIs('courses.*')" wire:navigate>{{ __('Courses') }}</flux:navlist.item>
                    <flux:navlist.item icon="user-circle" :href="route('users.index')" :current="request()->routeIs('users.*')" wire:navigate>{{ __('Users') }}</flux:navlist.item>
                    <flux:navlist.item icon="users" :href="route('students.index')" :current="request()->routeIs('students.*')" wire:navigate>{{ __('Students') }}</flux:navlist.item>
                    <flux:navlist.item icon="user-group" :href="route('teachers.index')" :current="request()->routeIs('teachers.*')" wire:navigate>{{ __('Teachers') }}</flux:navlist.item>
                    <flux:navlist.item icon="calendar-days" :href="route('classes.index')" :current="request()->routeIs('classes.*')" wire:navigate>{{ __('Classes') }}</flux:navlist.item>
                    <flux:navlist.item icon="clipboard" :href="route('enrollments.index')" :current="request()->routeIs('enrollments.*')" wire:navigate>{{ __('Enrollments') }}</flux:navlist.item>
                </flux:navlist.group>
                
                <flux:navlist.group :heading="__('Subscription Management')" class="grid">
                    <flux:navlist.item icon="clipboard-document-list" :href="route('orders.index')" :current="request()->routeIs('orders.*')" wire:navigate>{{ __('Orders') }}</flux:navlist.item>
                    <flux:navlist.item icon="credit-card" :href="route('admin.payments')" :current="request()->routeIs('admin.payments*')" wire:navigate>{{ __('Payment Dashboard') }}</flux:navlist.item>
                </flux:navlist.group>
                
                <flux:navlist.group 
                    expandable 
                    heading="Settings"
                    :expanded="request()->routeIs('admin.settings.*')"
                >
                    <flux:navlist.item 
                        icon="information-circle" 
                        :href="route('admin.settings.general')" 
                        :current="request()->routeIs('admin.settings.general')" 
                        wire:navigate
                    >
                        {{ __('General') }}
                    </flux:navlist.item>
                    
                    <flux:navlist.item 
                        icon="paint-brush" 
                        :href="route('admin.settings.appearance')" 
                        :current="request()->routeIs('admin.settings.appearance')" 
                        wire:navigate
                    >
                        {{ __('Appearance') }}
                    </flux:navlist.item>
                    
                    <flux:navlist.item 
                        icon="credit-card" 
                        :href="route('admin.settings.payment')" 
                        :current="request()->routeIs('admin.settings.payment')" 
                        wire:navigate
                    >
                        {{ __('Payment') }}
                    </flux:navlist.item>
                    
                    <flux:navlist.item 
                        icon="envelope" 
                        :href="route('admin.settings.email')" 
                        :current="request()->routeIs('admin.settings.email')" 
                        wire:navigate
                    >
                        {{ __('Email') }}
                    </flux:navlist.item>
                </flux:navlist.group>
                @endif
                
                @if(auth()->user()->isTeacher())
                <flux:navlist.group :heading="__('Teaching')" class="grid">
                    <flux:navlist.item icon="academic-cap" :href="route('teacher.courses.index')" :current="request()->routeIs('teacher.courses.*')" wire:navigate>{{ __('My Courses') }}</flux:navlist.item>
                    <flux:navlist.item icon="calendar-days" :href="route('teacher.classes.index')" :current="request()->routeIs('teacher.classes.*')" wire:navigate>{{ __('My Classes') }}</flux:navlist.item>
                    <flux:navlist.item icon="users" :href="route('teacher.students.index')" :current="request()->routeIs('teacher.students.*')" wire:navigate>{{ __('Students') }}</flux:navlist.item>
                    <flux:navlist.item icon="calendar" :href="route('teacher.timetable')" :current="request()->routeIs('teacher.timetable')" wire:navigate>{{ __('Timetable') }}</flux:navlist.item>
                </flux:navlist.group>
                @endif
                
                @if(auth()->user()->isStudent())
                <flux:navlist.group :heading="__('Courses')" class="grid">
                    <flux:navlist.item icon="academic-cap" :href="route('student.courses')" :current="request()->routeIs('student.courses*')" wire:navigate>{{ __('Browse Courses') }}</flux:navlist.item>
                    <flux:navlist.item icon="check-circle" :href="route('student.subscriptions')" :current="request()->routeIs('student.subscriptions*')" wire:navigate>{{ __('My Enrollments') }}</flux:navlist.item>
                </flux:navlist.group>
                
                <flux:navlist.group :heading="__('Learning')" class="grid">
                    <flux:navlist.item icon="calendar-days" :href="route('student.classes.index')" :current="request()->routeIs('student.classes.*')" wire:navigate>{{ __('My Classes') }}</flux:navlist.item>
                    <flux:navlist.item icon="calendar" :href="route('student.timetable')" :current="request()->routeIs('student.timetable')" wire:navigate>{{ __('My Timetable') }}</flux:navlist.item>
                </flux:navlist.group>
                
                <flux:navlist.group :heading="__('My Account')" class="grid">
                    <flux:navlist.item icon="clipboard-document-list" :href="route('student.orders')" :current="request()->routeIs('student.orders*')" wire:navigate>{{ __('Order History') }}</flux:navlist.item>
                    <flux:navlist.item icon="credit-card" :href="route('student.payment-methods')" :current="request()->routeIs('student.payment-methods*')" wire:navigate>{{ __('Payment Methods') }}</flux:navlist.item>
                </flux:navlist.group>
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
                                        class="flex h-full w-full items-center justify-center rounded-lg bg-neutral-200 text-black dark:bg-neutral-700 dark:text-white"
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
                                        class="flex h-full w-full items-center justify-center rounded-lg bg-neutral-200 text-black dark:bg-neutral-700 dark:text-white"
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

        {{ $slot }}

        @fluxScripts
        @stack('scripts')
    </body>
</html>
