@props(['title' => 'Settings', 'activeTab' => 'general'])

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
                    <flux:navlist.item icon="users" :href="route('students.index')" :current="request()->routeIs('students.*')" wire:navigate>{{ __('Students') }}</flux:navlist.item>
                    <flux:navlist.item icon="clipboard" :href="route('enrollments.index')" :current="request()->routeIs('enrollments.*')" wire:navigate>{{ __('Enrollments') }}</flux:navlist.item>
                </flux:navlist.group>
                
                <flux:navlist.group :heading="__('Financial')" class="grid">
                    <flux:navlist.item icon="document" :href="route('invoices.index')" :current="request()->routeIs('invoices.*')" wire:navigate>{{ __('Invoices') }}</flux:navlist.item>
                    <flux:navlist.item icon="document-plus" :href="route('invoices.generate')" :current="request()->routeIs('invoices.generate')" wire:navigate>{{ __('Generate Invoices') }}</flux:navlist.item>
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
            </flux:navlist>

            <flux:spacer />

            <flux:navlist variant="outline">
                <flux:navlist.item icon="information-circle" :href="route('admin.settings.general')" wire:navigate>
                {{ __('System Info') }}
                </flux:navlist.item>
            </flux:navlist>

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

        <flux:main>
            <div class="flex h-full w-full flex-1 flex-col gap-6">
                <!-- Header -->
                <div class="mb-6 flex items-center justify-between">
                    <div>
                        <flux:heading size="xl">{{ $title }}</flux:heading>
                        <flux:text class="mt-2">Configure your system settings and preferences</flux:text>
                    </div>
                </div>

                <!-- Settings Navigation -->
                <flux:navbar class="mb-6 -mb-px border-b border-zinc-200 dark:border-zinc-700">
                    <flux:navbar.item 
                        :href="route('admin.settings.general')" 
                        wire:navigate
                        :current="$activeTab === 'general'"
                        icon="cog-6-tooth"
                    >
                        General
                    </flux:navbar.item>
                    
                    <flux:navbar.item 
                        :href="route('admin.settings.appearance')" 
                        wire:navigate
                        :current="$activeTab === 'appearance'"
                        icon="paint-brush"
                    >
                        Appearance
                    </flux:navbar.item>
                    
                    <flux:navbar.item 
                        :href="route('admin.settings.payment')" 
                        wire:navigate
                        :current="$activeTab === 'payment'"
                        icon="credit-card"
                    >
                        Payment
                    </flux:navbar.item>
                    
                    <flux:navbar.item 
                        :href="route('admin.settings.email')" 
                        wire:navigate
                        :current="$activeTab === 'email'"
                        icon="envelope"
                    >
                        Email
                    </flux:navbar.item>
                </flux:navbar>

                <!-- Settings Content -->
                <div class="flex-1">
                    {{ $slot }}
                </div>
            </div>
        </flux:main>

        @fluxScripts
    </body>
</html>