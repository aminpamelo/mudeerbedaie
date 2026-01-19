@props(['title' => 'Admin Dashboard'])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white">
        <flux:sidebar sticky stashable class="border-e border-zinc-200 bg-zinc-50">
            <flux:sidebar.toggle class="lg:hidden" icon="x-mark" />

            <a href="{{ route('dashboard') }}" class="me-5 flex items-center space-x-2 rtl:space-x-reverse" wire:navigate>
                <x-app-logo />
            </a>

            <flux:navlist variant="outline">
                <flux:navlist.group :heading="__('Platform')" class="grid">
                    <flux:navlist.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>{{ __('Dashboard') }}</flux:navlist.item>
                </flux:navlist.group>

                @if(auth()->user()?->isAdmin())
                <!-- Core Administration -->
                <flux:navlist.group :heading="__('Administration')" class="grid">
                    <flux:navlist.item icon="academic-cap" :href="route('courses.index')" :current="request()->routeIs('courses.*')" wire:navigate>{{ __('Courses') }}</flux:navlist.item>
                    <flux:navlist.item icon="user" :href="route('users.index')" :current="request()->routeIs('users.*')" wire:navigate>{{ __('Users') }}</flux:navlist.item>
                    <flux:navlist.item icon="users" :href="route('students.index')" :current="request()->routeIs('students.*')" wire:navigate>{{ __('Students') }}</flux:navlist.item>
                    <flux:navlist.item icon="user-group" :href="route('teachers.index')" :current="request()->routeIs('teachers.*')" wire:navigate>{{ __('Teachers') }}</flux:navlist.item>
                    <flux:navlist.item icon="clipboard-document-list" :href="route('classes.index')" :current="request()->routeIs('classes.*')" wire:navigate>{{ __('Classes') }}</flux:navlist.item>
                    <flux:navlist.item icon="clock" :href="route('admin.sessions.index')" :current="request()->routeIs('admin.sessions.*')" wire:navigate>{{ __('Sessions') }}</flux:navlist.item>
                    <flux:navlist.item icon="banknotes" :href="route('admin.payslips.index')" :current="request()->routeIs('admin.payslips.*')" wire:navigate>{{ __('Payslips') }}</flux:navlist.item>
                    <flux:navlist.item icon="clipboard" :href="route('enrollments.index')" :current="request()->routeIs('enrollments.*')" wire:navigate>{{ __('Enrollments') }}</flux:navlist.item>
                </flux:navlist.group>

                <!-- Subscription Management -->
                <flux:navlist.group :heading="__('Subscription Management')" class="grid">
                    <flux:navlist.item icon="receipt-percent" :href="route('orders.index')" :current="request()->routeIs('orders.*')" wire:navigate>{{ __('Orders') }}</flux:navlist.item>
                    <flux:navlist.item icon="credit-card" :href="route('admin.payments')" :current="request()->routeIs('admin.payments*')" wire:navigate>{{ __('Payment Dashboard') }}</flux:navlist.item>
                    <flux:navlist.item icon="chart-pie" :href="route('admin.reports.subscriptions')" :current="request()->routeIs('admin.reports.subscriptions')" wire:navigate>{{ __('Subscription Reports') }}</flux:navlist.item>
                    <flux:navlist.item icon="currency-dollar" :href="route('admin.reports.student-payments')" :current="request()->routeIs('admin.reports.student-payments')" wire:navigate>{{ __('Student Payment Report') }}</flux:navlist.item>
                </flux:navlist.group>

                <!-- Product Management -->
                <flux:navlist.group
                    expandable
                    heading="Product Management"
                    :expanded="request()->routeIs('products.*', 'product-categories.*', 'product-attributes.*', 'platforms.*', 'admin.orders.*', 'warehouses.*', 'inventory.*', 'stock.*')"
                >
                    <flux:navlist.item
                        icon="cube"
                        :href="route('products.index')"
                        :current="request()->routeIs('products.*')"
                        wire:navigate
                    >
                        {{ __('Products') }}
                    </flux:navlist.item>

                    <flux:navlist.item
                        icon="tag"
                        :href="route('product-categories.index')"
                        :current="request()->routeIs('product-categories.*')"
                        wire:navigate
                    >
                        {{ __('Categories') }}
                    </flux:navlist.item>

                    {{-- <flux:navlist.item
                        icon="adjustments-horizontal"
                        :href="route('product-attributes.index')"
                        :current="request()->routeIs('product-attributes.*')"
                        wire:navigate
                    >
                        {{ __('Attributes') }}
                    </flux:navlist.item> --}}


                </flux:navlist.group>

                <!-- CRM & Automation -->
                <flux:navlist.group :heading="__('CRM &amp; Automation')" class="grid">
                    <flux:navlist.item icon="table-cells" :href="route('crm.all-database')" :current="request()->routeIs('crm.all-database')" wire:navigate>{{ __('All Database') }}</flux:navlist.item>
                </flux:navlist.group>

                <!-- Commerce & Packages -->
                <flux:navlist.group :heading="__('Commerce &amp; Packages')" class="grid">
                    <flux:navlist.item icon="shopping-bag" :href="route('admin.orders.index')" :current="request()->routeIs('admin.orders.*')" wire:navigate>{{ __('Orders &amp; Package Sales') }}</flux:navlist.item>
                    <flux:navlist.item icon="cube" :href="route('packages.index')" :current="request()->routeIs('packages.*')" wire:navigate>{{ __('Packages') }}</flux:navlist.item>
                </flux:navlist.group>

                <!-- Inventory Management -->
                <flux:navlist.group
                    expandable
                    heading="Inventory"
                    :expanded="request()->routeIs('inventory.*', 'stock.*', 'warehouses.*')"
                >
                    <flux:navlist.item
                        icon="chart-bar-square"
                        :href="route('inventory.dashboard')"
                        :current="request()->routeIs('inventory.dashboard')"
                        wire:navigate
                    >
                        {{ __('Dashboard') }}
                    </flux:navlist.item>

                    <flux:navlist.item
                        icon="arrows-right-left"
                        :href="route('stock.movements')"
                        :current="request()->routeIs('stock.movements*')"
                        wire:navigate
                    >
                        {{ __('Stock Movements') }}
                    </flux:navlist.item>

                    <flux:navlist.item
                        icon="signal"
                        :href="route('stock.levels')"
                        :current="request()->routeIs('stock.levels')"
                        wire:navigate
                    >
                        {{ __('Stock Levels') }}
                    </flux:navlist.item>

                    <flux:navlist.item
                        icon="exclamation-triangle"
                        :href="route('stock.alerts')"
                        :current="request()->routeIs('stock.alerts')"
                        wire:navigate
                    >
                        {{ __('Stock Alerts') }}
                    </flux:navlist.item>

                    <flux:navlist.item
                        icon="building-storefront"
                        :href="route('warehouses.index')"
                        :current="request()->routeIs('warehouses.*')"
                        wire:navigate
                    >
                        {{ __('Warehouses') }}
                    </flux:navlist.item>
                </flux:navlist.group>

                <!-- Platform Management -->
                <flux:navlist.group
                    expandable
                    heading="Platform Management"
                    :expanded="request()->routeIs('platforms.*')"
                >
                    <flux:navlist.item
                        icon="globe-alt"
                        :href="route('platforms.index')"
                        :current="request()->routeIs('platforms.index', 'platforms.show', 'platforms.create', 'platforms.edit')"
                        wire:navigate
                    >
                        {{ __('Platforms') }}
                    </flux:navlist.item>

                    <flux:navlist.item
                        icon="link"
                        :href="route('platforms.sku-mappings.index')"
                        :current="request()->routeIs('platforms.sku-mappings.*')"
                        wire:navigate
                    >
                        {{ __('SKU Mappings') }}
                    </flux:navlist.item>
                </flux:navlist.group>

                <!-- System Settings -->
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
                        icon="currency-dollar"
                        :href="route('admin.settings.pricing')"
                        :current="request()->routeIs('admin.settings.pricing')"
                        wire:navigate
                    >
                        {{ __('Pricing') }}
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
                    :name="auth()->user()?->name"
                    :initials="auth()->user()?->initials()"
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
                                        {{ auth()->user()?->initials() }}
                                    </span>
                                </span>

                                <div class="grid flex-1 text-start text-sm leading-tight">
                                    <span class="truncate font-semibold">{{ auth()->user()?->name }}</span>
                                    <span class="truncate text-xs">{{ auth()->user()?->email }}</span>
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
                    :initials="auth()->user()?->initials()"
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
                                        {{ auth()->user()?->initials() }}
                                    </span>
                                </span>

                                <div class="grid flex-1 text-start text-sm leading-tight">
                                    <span class="truncate font-semibold">{{ auth()->user()?->name }}</span>
                                    <span class="truncate text-xs">{{ auth()->user()?->email }}</span>
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
                {{ $slot }}
            </div>
        </flux:main>

        @fluxScripts
    </body>
</html>