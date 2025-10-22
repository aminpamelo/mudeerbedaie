<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white dark:bg-zinc-900">
        <flux:sidebar sticky stashable class="border-e border-zinc-200 bg-zinc-50 dark:border-zinc-800 dark:bg-zinc-900">
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
                    <flux:navlist.item icon="presentation-chart-bar" :href="route('admin.sessions.index')" :current="request()->routeIs('admin.sessions.*')" wire:navigate>{{ __('Sessions') }}</flux:navlist.item>
                    <flux:navlist.item icon="banknotes" :href="route('admin.payslips.index')" :current="request()->routeIs('admin.payslips.*')" wire:navigate>{{ __('Payslips') }}</flux:navlist.item>
                    <flux:navlist.item icon="clipboard" :href="route('enrollments.index')" :current="request()->routeIs('enrollments.*')" wire:navigate>{{ __('Enrollments') }}</flux:navlist.item>
                </flux:navlist.group>
                
                <flux:navlist.group :heading="__('Subscription Management')" class="grid">
                    <flux:navlist.item icon="clipboard-document-list" :href="route('orders.index')" :current="request()->routeIs('orders.*')" wire:navigate>{{ __('Orders') }}</flux:navlist.item>
                    <flux:navlist.item icon="credit-card" :href="route('admin.payments')" :current="request()->routeIs('admin.payments*')" wire:navigate>{{ __('Payment Dashboard') }}</flux:navlist.item>
                </flux:navlist.group>

                <flux:navlist.group :heading="__('Product Management')" class="grid">
                    <flux:navlist.item icon="cube" :href="route('products.index')" :current="request()->routeIs('products.*')" wire:navigate>{{ __('Products') }}</flux:navlist.item>
                    <flux:navlist.item icon="folder" :href="route('product-categories.index')" :current="request()->routeIs('product-categories.*')" wire:navigate>{{ __('Categories') }}</flux:navlist.item>
                    <flux:navlist.item icon="tag" :href="route('product-attributes.index')" :current="request()->routeIs('product-attributes.*')" wire:navigate>{{ __('Attributes') }}</flux:navlist.item>
                </flux:navlist.group>

                <flux:navlist.group :heading="__('CRM & Automation')" class="grid">
                    <flux:navlist.item icon="table-cells" :href="route('crm.all-database')" :current="request()->routeIs('crm.all-database')" wire:navigate>{{ __('All Database') }}</flux:navlist.item>
                    <flux:navlist.item icon="user-group" :href="route('crm.audiences.index')" :current="request()->routeIs('crm.audiences.*')" wire:navigate>{{ __('Audiences') }}</flux:navlist.item>
                    <flux:navlist.item icon="envelope" :href="route('crm.broadcasts.index')" :current="request()->routeIs('crm.broadcasts.*')" wire:navigate>{{ __('Broadcasts') }}</flux:navlist.item>
                </flux:navlist.group>

                <flux:navlist.group :heading="__('Commerce & Packages')" class="grid">
                    <flux:navlist.item icon="shopping-bag" :href="route('admin.orders.index')" :current="request()->routeIs('admin.orders.*')" wire:navigate>{{ __('Orders & Package Sales') }}</flux:navlist.item>
                    <flux:navlist.item icon="gift" :href="route('packages.index')" :current="request()->routeIs('packages.*')" wire:navigate>{{ __('Packages') }}</flux:navlist.item>
                </flux:navlist.group>

                <flux:navlist.group :heading="__('Certificate Management')" class="grid">
                    <flux:navlist.item icon="document-text" :href="route('certificates.index')" :current="request()->routeIs('certificates.index', 'certificates.create', 'certificates.edit', 'certificates.preview', 'certificates.assignments')" wire:navigate>{{ __('Certificate Templates') }}</flux:navlist.item>
                    <flux:navlist.item icon="clipboard-document-check" :href="route('certificates.issued')" :current="request()->routeIs('certificates.issued')" wire:navigate>{{ __('Issued Certificates') }}</flux:navlist.item>
                    <flux:navlist.item icon="document-plus" :href="route('certificates.issue')" :current="request()->routeIs('certificates.issue', 'certificates.bulk-issue')" wire:navigate>{{ __('Issue Certificate') }}</flux:navlist.item>
                </flux:navlist.group>

                <flux:navlist.group :heading="__('Inventory Management')" class="grid">
                    <flux:navlist.item icon="chart-bar" :href="route('inventory.dashboard')" :current="request()->routeIs('inventory.*')" wire:navigate>{{ __('Dashboard') }}</flux:navlist.item>
                    <flux:navlist.item icon="arrow-path" :href="route('stock.movements')" :current="request()->routeIs('stock.movements*')" wire:navigate>{{ __('Stock Movements') }}</flux:navlist.item>
                    <flux:navlist.item icon="squares-2x2" :href="route('stock.levels')" :current="request()->routeIs('stock.levels*')" wire:navigate>{{ __('Stock Levels') }}</flux:navlist.item>
                    <flux:navlist.item icon="exclamation-triangle" :href="route('stock.alerts')" :current="request()->routeIs('stock.alerts*')" wire:navigate>{{ __('Stock Alerts') }}</flux:navlist.item>
                    <flux:navlist.item icon="building-storefront" :href="route('warehouses.index')" :current="request()->routeIs('warehouses.*')" wire:navigate>{{ __('Warehouses') }}</flux:navlist.item>
                    <flux:navlist.item icon="building-office" :href="route('agents.index')" :current="request()->routeIs('agents.*')" wire:navigate>{{ __('Agents & Companies') }}</flux:navlist.item>
                </flux:navlist.group>

                <flux:navlist.group :heading="__('Platform Management')" class="grid">
                    <flux:navlist.item icon="squares-2x2" :href="route('platforms.index')" :current="request()->routeIs('platforms.*')" wire:navigate>{{ __('Platforms') }}</flux:navlist.item>
                    <flux:navlist.item icon="arrows-right-left" :href="route('platforms.sku-mappings.index')" :current="request()->routeIs('platforms.sku-mappings.*')" wire:navigate>{{ __('SKU Mappings') }}</flux:navlist.item>
                    <flux:navlist.item icon="arrow-down-tray" :href="route('platforms.orders.import')" :current="request()->routeIs('platforms.orders.import')" wire:navigate>{{ __('Import Orders') }}</flux:navlist.item>
                    <flux:navlist.item icon="clock" :href="route('platforms.import-history')" :current="request()->routeIs('platforms.import-history')" wire:navigate>{{ __('Import History') }}</flux:navlist.item>
                </flux:navlist.group>

                <flux:navlist.group :heading="__('Reports & Analytics')" class="grid">
                    <flux:navlist.item icon="chart-bar" :href="route('admin.reports.packages-orders')" :current="request()->routeIs('admin.reports.packages-orders')" wire:navigate>{{ __('Package & Order Product Report') }}</flux:navlist.item>
                    <flux:navlist.item icon="shopping-cart" :href="route('admin.reports.student-product-orders')" :current="request()->routeIs('admin.reports.student-product-orders')" wire:navigate>{{ __('Student Product Order Report') }}</flux:navlist.item>
                    <flux:navlist.item icon="chart-bar" :href="route('admin.reports.subscriptions')" :current="request()->routeIs('admin.reports.subscriptions')" wire:navigate>{{ __('Subscription Reports') }}</flux:navlist.item>
                    <flux:navlist.item icon="document-chart-bar" :href="route('admin.reports.student-payments')" :current="request()->routeIs('admin.reports.student-payments')" wire:navigate>{{ __('Student Payment Report') }}</flux:navlist.item>
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
                    <flux:navlist.item icon="presentation-chart-bar" :href="route('teacher.sessions.index')" :current="request()->routeIs('teacher.sessions.*')" wire:navigate>{{ __('My Sessions') }}</flux:navlist.item>
                    <flux:navlist.item icon="banknotes" :href="route('teacher.payslips.index')" :current="request()->routeIs('teacher.payslips.*')" wire:navigate>{{ __('My Payslips') }}</flux:navlist.item>
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

        {{ $slot }}

        @fluxScripts
        @stack('scripts')
    </body>
</html>
