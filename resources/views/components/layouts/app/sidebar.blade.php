<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white dark:bg-zinc-900">
        <x-impersonation-banner />

        <flux:sidebar sticky stashable class="border-e border-zinc-200 bg-zinc-50 dark:border-zinc-800 dark:bg-zinc-900"
            x-data="{
                sections: {},
                currentRoute: '{{ request()->route()->getName() }}',
                sectionRoutes: {
                    'platform': ['dashboard'],
                    'administration': ['courses.*', 'users.*', 'students.*', 'teachers.*', 'classes.*', 'class-categories.*', 'admin.sessions.*', 'admin.payslips.*', 'enrollments.*'],
                    'subscription': ['orders.*', 'admin.payments*'],
                    'products': ['products.*', 'product-categories.*', 'product-attributes.*'],
                    'crm': ['crm.*'],
                    'commerce': ['admin.orders.*', 'packages.*'],
                    'customerService': ['admin.customer-service.*'],
                    'certificates': ['certificates.*'],
                    'inventory': ['inventory.*', 'stock.*', 'warehouses.*', 'agents.*'],
                    'platformMgmt': ['platforms.*'],
                    'liveHost': ['admin.live-hosts*', 'admin.live-schedules.*', 'admin.live-sessions.*'],
                    'reports': ['admin.reports.*'],
                    'settings': ['admin.settings.*'],
                    'teaching': ['teacher.courses.*', 'teacher.classes.*', 'teacher.sessions.*', 'teacher.payslips.*', 'teacher.students.*', 'teacher.timetable'],
                    'liveStreaming': ['live-host.*'],
                    'classAdminDashboard': ['class-admin.dashboard'],
                    'classAdminManagement': ['classes.*', 'admin.sessions.*', 'class-categories.*'],
                    'classAdminAcademic': ['courses.*', 'students.*', 'teachers.*', 'enrollments.*'],
                    'classAdminFinance': ['admin.payslips.*'],
                    'studentDashboard': ['student.dashboard'],
                    'studentCourses': ['student.courses*', 'student.subscriptions*'],
                    'studentLearning': ['student.classes.*', 'student.timetable'],
                    'studentAccount': ['student.orders*', 'student.payment-methods*', 'student.payments*', 'student.invoices*']
                },
                init() {
                    // Load saved state from localStorage
                    const saved = localStorage.getItem('sidebarSections');
                    this.sections = saved ? JSON.parse(saved) : {};
                },
                hasCurrentPage(section) {
                    const routes = this.sectionRoutes[section];
                    if (!routes) return false;

                    return routes.some(pattern => {
                        // Convert Laravel route pattern to regex
                        const regex = new RegExp('^' + pattern.replace(/\*/g, '.*') + '$');
                        return regex.test(this.currentRoute);
                    });
                },
                isExpanded(section) {
                    // Always expand if this section contains the current page
                    if (this.hasCurrentPage(section)) {
                        return true;
                    }

                    // Otherwise, use saved state
                    if (this.sections.hasOwnProperty(section)) {
                        return this.sections[section];
                    }

                    // Default to expanded
                    return true;
                },
                saveState(section, event) {
                    // Wait for Flux UI to update, then read the new state
                    this.$nextTick(() => {
                        const group = event.target.closest('[data-flux-navlist-group]');
                        if (group) {
                            const isExpanded = group.hasAttribute('data-expanded');
                            this.sections[section] = isExpanded;
                            localStorage.setItem('sidebarSections', JSON.stringify(this.sections));
                        }
                    });
                }
            }">
            <flux:sidebar.toggle class="lg:hidden" icon="x-mark" />

            <a href="{{ route('dashboard') }}" class="me-5 flex items-center space-x-2 rtl:space-x-reverse" wire:navigate>
                <x-app-logo />
            </a>

            <flux:navlist variant="outline">
                @if(auth()->user()->isStudent())
                {{-- Student Home --}}
                <flux:navlist.group
                    expandable
                    :heading="__('Home')"
                    data-section="studentDashboard"
                    x-init="if (!isExpanded('studentDashboard')) { $nextTick(() => { const btn = $el.querySelector('button'); if (btn && $el.hasAttribute('open')) btn.click(); }); }"
                    @click="saveState('studentDashboard', $event)"
                >
                    <flux:navlist.item icon="home" :href="route('student.dashboard')" :current="request()->routeIs('student.dashboard')" wire:navigate>{{ __('Dashboard') }}</flux:navlist.item>
                </flux:navlist.group>
                @elseif(auth()->user()->isClassAdmin())
                <flux:navlist.group
                    expandable
                    :heading="__('Platform')"
                    data-section="classAdminDashboard"
                    x-init="if (!isExpanded('classAdminDashboard')) { $nextTick(() => { const btn = $el.querySelector('button'); if (btn && $el.hasAttribute('open')) btn.click(); }); }"
                    @click="saveState('classAdminDashboard', $event)"
                >
                    <flux:navlist.item icon="home" :href="route('class-admin.dashboard')" :current="request()->routeIs('class-admin.dashboard')" wire:navigate>{{ __('Dashboard') }}</flux:navlist.item>
                </flux:navlist.group>
                @else
                <flux:navlist.group
                    expandable
                    :heading="__('Platform')"
                    data-section="platform"
                    x-init="if (!isExpanded('platform')) { $nextTick(() => { const btn = $el.querySelector('button'); if (btn && $el.hasAttribute('open')) btn.click(); }); }"
                    @click="saveState('platform', $event)"
                >
                    <flux:navlist.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>{{ __('Dashboard') }}</flux:navlist.item>
                </flux:navlist.group>
                @endif

                @if(auth()->user()->isAdmin())
                <flux:navlist.group
                    expandable
                    :heading="__('Administration')"
                    data-section="administration" x-init="if (!isExpanded('administration')) { $nextTick(() => { const btn = $el.querySelector('button'); if (btn && $el.hasAttribute('open')) btn.click(); }); }"
                    @click="saveState('administration', $event)"
                >
                    <flux:navlist.item icon="academic-cap" :href="route('courses.index')" :current="request()->routeIs('courses.*')" wire:navigate>{{ __('Courses') }}</flux:navlist.item>
                    <flux:navlist.item icon="user-circle" :href="route('users.index')" :current="request()->routeIs('users.*')" wire:navigate>{{ __('Users') }}</flux:navlist.item>
                    <flux:navlist.item icon="users" :href="route('students.index')" :current="request()->routeIs('students.*')" wire:navigate>{{ __('Students') }}</flux:navlist.item>
                    <flux:navlist.item icon="user-group" :href="route('teachers.index')" :current="request()->routeIs('teachers.*')" wire:navigate>{{ __('Teachers') }}</flux:navlist.item>
                    <flux:navlist.item icon="calendar-days" :href="route('classes.index')" :current="request()->routeIs('classes.*')" wire:navigate>{{ __('Classes') }}</flux:navlist.item>
                    <flux:navlist.item icon="folder" :href="route('class-categories.index')" :current="request()->routeIs('class-categories.*')" wire:navigate>{{ __('Class Categories') }}</flux:navlist.item>
                    <flux:navlist.item icon="presentation-chart-bar" :href="route('admin.sessions.index')" :current="request()->routeIs('admin.sessions.*')" wire:navigate>{{ __('Sessions') }}</flux:navlist.item>
                    <flux:navlist.item icon="table-cells" :href="route('admin.master-timetable')" :current="request()->routeIs('admin.master-timetable')" wire:navigate>{{ __('Master Timetable') }}</flux:navlist.item>
                    <flux:navlist.item icon="banknotes" :href="route('admin.payslips.index')" :current="request()->routeIs('admin.payslips.*')" wire:navigate>{{ __('Payslips') }}</flux:navlist.item>
                    <flux:navlist.item icon="clipboard" :href="route('enrollments.index')" :current="request()->routeIs('enrollments.*')" wire:navigate>{{ __('Enrollments') }}</flux:navlist.item>
                </flux:navlist.group>

                <flux:navlist.group
                    expandable
                    :heading="__('Subscription Management')"
                    data-section='subscription' x-init="if (!isExpanded('subscription')) { $nextTick(() => { const btn = $el.querySelector('button'); if (btn && $el.hasAttribute('open')) btn.click(); }); }"
                    @click="saveState('subscription', $event)"
                >
                    <flux:navlist.item icon="clipboard-document-list" :href="route('orders.index')" :current="request()->routeIs('orders.*')" wire:navigate>{{ __('Orders') }}</flux:navlist.item>
                    <flux:navlist.item icon="credit-card" :href="route('admin.payments')" :current="request()->routeIs('admin.payments*')" wire:navigate>{{ __('Payment Dashboard') }}</flux:navlist.item>
                </flux:navlist.group>

                <flux:navlist.group
                    expandable
                    :heading="__('Product Management')"
                    data-section='products' x-init="if (!isExpanded('products')) { $nextTick(() => { const btn = $el.querySelector('button'); if (btn && $el.hasAttribute('open')) btn.click(); }); }"
                    @click="saveState('products', $event)"
                >
                    <flux:navlist.item icon="cube" :href="route('products.index')" :current="request()->routeIs('products.*')" wire:navigate>{{ __('Products') }}</flux:navlist.item>
                    <flux:navlist.item icon="folder" :href="route('product-categories.index')" :current="request()->routeIs('product-categories.*')" wire:navigate>{{ __('Categories') }}</flux:navlist.item>
                    <flux:navlist.item icon="tag" :href="route('product-attributes.index')" :current="request()->routeIs('product-attributes.*')" wire:navigate>{{ __('Attributes') }}</flux:navlist.item>
                </flux:navlist.group>

                <flux:navlist.group
                    expandable
                    :heading="__('CRM & Automation')"
                    data-section='crm' x-init="if (!isExpanded('crm')) { $nextTick(() => { const btn = $el.querySelector('button'); if (btn && $el.hasAttribute('open')) btn.click(); }); }"
                    @click="saveState('crm', $event)"
                >
                    <flux:navlist.item icon="table-cells" :href="route('crm.all-database')" :current="request()->routeIs('crm.all-database')" wire:navigate>{{ __('All Database') }}</flux:navlist.item>
                    <flux:navlist.item icon="user-group" :href="route('crm.audiences.index')" :current="request()->routeIs('crm.audiences.*')" wire:navigate>{{ __('Audiences') }}</flux:navlist.item>
                    <flux:navlist.item icon="envelope" :href="route('crm.broadcasts.index')" :current="request()->routeIs('crm.broadcasts.*')" wire:navigate>{{ __('Broadcasts') }}</flux:navlist.item>
                </flux:navlist.group>

                <flux:navlist.group
                    expandable
                    :heading="__('Commerce & Packages')"
                    data-section='commerce' x-init="if (!isExpanded('commerce')) { $nextTick(() => { const btn = $el.querySelector('button'); if (btn && $el.hasAttribute('open')) btn.click(); }); }"
                    @click="saveState('commerce', $event)"
                >
                    <flux:navlist.item icon="shopping-bag" :href="route('admin.orders.index')" :current="request()->routeIs('admin.orders.*')" wire:navigate>{{ __('Orders & Package Sales') }}</flux:navlist.item>
                    <flux:navlist.item icon="gift" :href="route('packages.index')" :current="request()->routeIs('packages.*')" wire:navigate>{{ __('Packages') }}</flux:navlist.item>
                </flux:navlist.group>

                <flux:navlist.group
                    expandable
                    :heading="__('Customer Service')"
                    data-section='customerService' x-init="if (!isExpanded('customerService')) { $nextTick(() => { const btn = $el.querySelector('button'); if (btn && $el.hasAttribute('open')) btn.click(); }); }"
                    @click="saveState('customerService', $event)"
                >
                    <flux:navlist.item icon="lifebuoy" :href="route('admin.customer-service.dashboard')" :current="request()->routeIs('admin.customer-service.dashboard')" wire:navigate>{{ __('Dashboard') }}</flux:navlist.item>
                    <flux:navlist.item icon="arrow-path" :href="route('admin.customer-service.return-refunds.index')" :current="request()->routeIs('admin.customer-service.return-refunds.*')" wire:navigate>{{ __('Return & Refunds') }}</flux:navlist.item>
                    <flux:navlist.item icon="ticket" :href="route('admin.customer-service.tickets.index')" :current="request()->routeIs('admin.customer-service.tickets.*')" wire:navigate>{{ __('Tickets') }}</flux:navlist.item>
                    <flux:navlist.item icon="chat-bubble-left-right" :href="route('admin.customer-service.feedback.index')" :current="request()->routeIs('admin.customer-service.feedback.*')" wire:navigate>{{ __('Feedback') }}</flux:navlist.item>
                </flux:navlist.group>

                <flux:navlist.group
                    expandable
                    :heading="__('Certificate Management')"
                    data-section='certificates' x-init="if (!isExpanded('certificates')) { $nextTick(() => { const btn = $el.querySelector('button'); if (btn && $el.hasAttribute('open')) btn.click(); }); }"
                    @click="saveState('certificates', $event)"
                >
                    <flux:navlist.item icon="document-text" :href="route('certificates.index')" :current="request()->routeIs('certificates.index', 'certificates.create', 'certificates.edit', 'certificates.preview', 'certificates.assignments')" wire:navigate>{{ __('Certificate Templates') }}</flux:navlist.item>
                    <flux:navlist.item icon="clipboard-document-check" :href="route('certificates.issued')" :current="request()->routeIs('certificates.issued')" wire:navigate>{{ __('Issued Certificates') }}</flux:navlist.item>
                    <flux:navlist.item icon="document-plus" :href="route('certificates.issue')" :current="request()->routeIs('certificates.issue', 'certificates.bulk-issue')" wire:navigate>{{ __('Issue Certificate') }}</flux:navlist.item>
                </flux:navlist.group>

                <flux:navlist.group
                    expandable
                    :heading="__('Inventory Management')"
                    data-section='inventory' x-init="if (!isExpanded('inventory')) { $nextTick(() => { const btn = $el.querySelector('button'); if (btn && $el.hasAttribute('open')) btn.click(); }); }"
                    @click="saveState('inventory', $event)"
                >
                    <flux:navlist.item icon="chart-bar" :href="route('inventory.dashboard')" :current="request()->routeIs('inventory.*')" wire:navigate>{{ __('Dashboard') }}</flux:navlist.item>
                    <flux:navlist.item icon="arrow-path" :href="route('stock.movements')" :current="request()->routeIs('stock.movements*')" wire:navigate>{{ __('Stock Movements') }}</flux:navlist.item>
                    <flux:navlist.item icon="squares-2x2" :href="route('stock.levels')" :current="request()->routeIs('stock.levels*')" wire:navigate>{{ __('Stock Levels') }}</flux:navlist.item>
                    <flux:navlist.item icon="exclamation-triangle" :href="route('stock.alerts')" :current="request()->routeIs('stock.alerts*')" wire:navigate>{{ __('Stock Alerts') }}</flux:navlist.item>
                    <flux:navlist.item icon="building-storefront" :href="route('warehouses.index')" :current="request()->routeIs('warehouses.*')" wire:navigate>{{ __('Warehouses') }}</flux:navlist.item>
                    <flux:navlist.item icon="building-office" :href="route('agents.index')" :current="request()->routeIs('agents.*')" wire:navigate>{{ __('Agents & Companies') }}</flux:navlist.item>
                </flux:navlist.group>

                <flux:navlist.group
                    expandable
                    :heading="__('Platform Management')"
                    data-section='platformMgmt' x-init="if (!isExpanded('platformMgmt')) { $nextTick(() => { const btn = $el.querySelector('button'); if (btn && $el.hasAttribute('open')) btn.click(); }); }"
                    @click="saveState('platformMgmt', $event)"
                >
                    <flux:navlist.item icon="squares-2x2" :href="route('platforms.index')" :current="request()->routeIs('platforms.*')" wire:navigate>{{ __('Platforms') }}</flux:navlist.item>
                    <flux:navlist.item icon="arrows-right-left" :href="route('platforms.sku-mappings.index')" :current="request()->routeIs('platforms.sku-mappings.*')" wire:navigate>{{ __('SKU Mappings') }}</flux:navlist.item>
                    <flux:navlist.item icon="arrow-down-tray" :href="route('platforms.orders.import')" :current="request()->routeIs('platforms.orders.import')" wire:navigate>{{ __('Import Orders') }}</flux:navlist.item>
                    <flux:navlist.item icon="clock" :href="route('platforms.import-history')" :current="request()->routeIs('platforms.import-history')" wire:navigate>{{ __('Import History') }}</flux:navlist.item>
                </flux:navlist.group>
                @endif

                @if(auth()->user()->isAdmin() || auth()->user()->isAdminLivehost())
                <flux:navlist.group
                    expandable
                    :heading="__('Live Host Management')"
                    data-section='liveHost' x-init="if (!isExpanded('liveHost')) { $nextTick(() => { const btn = $el.querySelector('button'); if (btn && $el.hasAttribute('open')) btn.click(); }); }"
                    @click="saveState('liveHost', $event)"
                >
                    <flux:navlist.item icon="users" :href="route('admin.live-hosts')" :current="request()->routeIs('admin.live-hosts*')" wire:navigate>{{ __('Live Hosts') }}</flux:navlist.item>
                    <flux:navlist.item icon="calendar-days" :href="route('admin.live-schedules.index')" :current="request()->routeIs('admin.live-schedules.*')" wire:navigate>{{ __('Live Schedules') }}</flux:navlist.item>
                    <flux:navlist.item icon="video-camera" :href="route('admin.live-sessions.index')" :current="request()->routeIs('admin.live-sessions.*')" wire:navigate>{{ __('Live Sessions') }}</flux:navlist.item>
                </flux:navlist.group>
                @endif

                @if(auth()->user()->isAdmin())
                <flux:navlist.group
                    expandable
                    :heading="__('Reports & Analytics')"
                    data-section='reports' x-init="if (!isExpanded('reports')) { $nextTick(() => { const btn = $el.querySelector('button'); if (btn && $el.hasAttribute('open')) btn.click(); }); }"
                    @click="saveState('reports', $event)"
                >
                    <flux:navlist.item icon="chart-bar" :href="route('admin.reports.packages-orders')" :current="request()->routeIs('admin.reports.packages-orders')" wire:navigate>{{ __('Package & Order Product Report') }}</flux:navlist.item>
                    <flux:navlist.item icon="shopping-cart" :href="route('admin.reports.student-product-orders')" :current="request()->routeIs('admin.reports.student-product-orders')" wire:navigate>{{ __('Student Product Order Report') }}</flux:navlist.item>
                    <flux:navlist.item icon="academic-cap" :href="route('admin.reports.student-class-enrollments')" :current="request()->routeIs('admin.reports.student-class-enrollments')" wire:navigate>{{ __('Student Class Enrollment Report') }}</flux:navlist.item>
                    <flux:navlist.item icon="chart-bar" :href="route('admin.reports.subscriptions')" :current="request()->routeIs('admin.reports.subscriptions')" wire:navigate>{{ __('Subscription Reports') }}</flux:navlist.item>
                    <flux:navlist.item icon="document-chart-bar" :href="route('admin.reports.student-payments')" :current="request()->routeIs('admin.reports.student-payments')" wire:navigate>{{ __('Student Payment Report') }}</flux:navlist.item>
                </flux:navlist.group>

                <flux:navlist.group
                    expandable
                    heading="Settings"
                    data-section='settings' x-init="if (!isExpanded('settings')) { $nextTick(() => { const btn = $el.querySelector('button'); if (btn && $el.hasAttribute('open')) btn.click(); }); }"
                    @click="saveState('settings', $event)"
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

                    <flux:navlist.item
                        icon="bell"
                        :href="route('admin.settings.notifications')"
                        :current="request()->routeIs('admin.settings.notifications')"
                        wire:navigate
                    >
                        {{ __('Notifications') }}
                    </flux:navlist.item>

                    <flux:navlist.item
                        icon="device-phone-mobile"
                        :href="route('admin.settings.whatsapp')"
                        :current="request()->routeIs('admin.settings.whatsapp')"
                        wire:navigate
                    >
                        {{ __('WhatsApp') }}
                    </flux:navlist.item>
                </flux:navlist.group>
                @endif
                
                @if(auth()->user()->isTeacher())
                <flux:navlist.group
                    expandable
                    :heading="__('Teaching')"
                    data-section='teaching' x-init="if (!isExpanded('teaching')) { $nextTick(() => { const btn = $el.querySelector('button'); if (btn && $el.hasAttribute('open')) btn.click(); }); }"
                    @click="saveState('teaching', $event)"
                >
                    <flux:navlist.item icon="academic-cap" :href="route('teacher.courses.index')" :current="request()->routeIs('teacher.courses.*')" wire:navigate>{{ __('My Courses') }}</flux:navlist.item>
                    <flux:navlist.item icon="calendar-days" :href="route('teacher.classes.index')" :current="request()->routeIs('teacher.classes.*')" wire:navigate>{{ __('My Classes') }}</flux:navlist.item>
                    <flux:navlist.item icon="presentation-chart-bar" :href="route('teacher.sessions.index')" :current="request()->routeIs('teacher.sessions.*')" wire:navigate>{{ __('My Sessions') }}</flux:navlist.item>
                    <flux:navlist.item icon="banknotes" :href="route('teacher.payslips.index')" :current="request()->routeIs('teacher.payslips.*')" wire:navigate>{{ __('My Payslips') }}</flux:navlist.item>
                    <flux:navlist.item icon="users" :href="route('teacher.students.index')" :current="request()->routeIs('teacher.students.*')" wire:navigate>{{ __('Students') }}</flux:navlist.item>
                    <flux:navlist.item icon="calendar" :href="route('teacher.timetable')" :current="request()->routeIs('teacher.timetable')" wire:navigate>{{ __('Timetable') }}</flux:navlist.item>
                </flux:navlist.group>
                @endif

                @if(auth()->user()->isLiveHost())
                <flux:navlist.group
                    expandable
                    :heading="__('Live Streaming')"
                    data-section='liveStreaming' x-init="if (!isExpanded('liveStreaming')) { $nextTick(() => { const btn = $el.querySelector('button'); if (btn && $el.hasAttribute('open')) btn.click(); }); }"
                    @click="saveState('liveStreaming', $event)"
                >
                    <flux:navlist.item icon="video-camera" :href="route('live-host.dashboard')" :current="request()->routeIs('live-host.dashboard')" wire:navigate>{{ __('Live Dashboard') }}</flux:navlist.item>
                    <flux:navlist.item icon="calendar" :href="route('live-host.schedule')" :current="request()->routeIs('live-host.schedule')" wire:navigate>{{ __('My Schedule') }}</flux:navlist.item>
                    <flux:navlist.item icon="play-circle" :href="route('live-host.sessions.index')" :current="request()->routeIs('live-host.sessions.*')" wire:navigate>{{ __('My Sessions') }}</flux:navlist.item>
                </flux:navlist.group>
                @endif

                @if(auth()->user()->isClassAdmin())
                <flux:navlist.group
                    expandable
                    :heading="__('Class Management')"
                    data-section='classAdminManagement' x-init="if (!isExpanded('classAdminManagement')) { $nextTick(() => { const btn = $el.querySelector('button'); if (btn && $el.hasAttribute('open')) btn.click(); }); }"
                    @click="saveState('classAdminManagement', $event)"
                >
                    <flux:navlist.item icon="calendar-days" :href="route('classes.index')" :current="request()->routeIs('classes.*')" wire:navigate>{{ __('Classes') }}</flux:navlist.item>
                    <flux:navlist.item icon="presentation-chart-bar" :href="route('admin.sessions.index')" :current="request()->routeIs('admin.sessions.*')" wire:navigate>{{ __('Sessions') }}</flux:navlist.item>
                    <flux:navlist.item icon="table-cells" :href="route('admin.master-timetable')" :current="request()->routeIs('admin.master-timetable')" wire:navigate>{{ __('Master Timetable') }}</flux:navlist.item>
                    <flux:navlist.item icon="folder" :href="route('class-categories.index')" :current="request()->routeIs('class-categories.*')" wire:navigate>{{ __('Class Categories') }}</flux:navlist.item>
                </flux:navlist.group>

                <flux:navlist.group
                    expandable
                    :heading="__('Academic')"
                    data-section='classAdminAcademic' x-init="if (!isExpanded('classAdminAcademic')) { $nextTick(() => { const btn = $el.querySelector('button'); if (btn && $el.hasAttribute('open')) btn.click(); }); }"
                    @click="saveState('classAdminAcademic', $event)"
                >
                    <flux:navlist.item icon="academic-cap" :href="route('courses.index')" :current="request()->routeIs('courses.*')" wire:navigate>{{ __('Courses') }}</flux:navlist.item>
                    <flux:navlist.item icon="users" :href="route('students.index')" :current="request()->routeIs('students.*')" wire:navigate>{{ __('Students') }}</flux:navlist.item>
                    <flux:navlist.item icon="user-group" :href="route('teachers.index')" :current="request()->routeIs('teachers.*')" wire:navigate>{{ __('Teachers') }}</flux:navlist.item>
                    <flux:navlist.item icon="clipboard" :href="route('enrollments.index')" :current="request()->routeIs('enrollments.*')" wire:navigate>{{ __('Enrollments') }}</flux:navlist.item>
                </flux:navlist.group>

                <flux:navlist.group
                    expandable
                    :heading="__('Finance')"
                    data-section='classAdminFinance' x-init="if (!isExpanded('classAdminFinance')) { $nextTick(() => { const btn = $el.querySelector('button'); if (btn && $el.hasAttribute('open')) btn.click(); }); }"
                    @click="saveState('classAdminFinance', $event)"
                >
                    <flux:navlist.item icon="banknotes" :href="route('admin.payslips.index')" :current="request()->routeIs('admin.payslips.*')" wire:navigate>{{ __('Payslips') }}</flux:navlist.item>
                </flux:navlist.group>
                @endif

                @if(auth()->user()->isStudent())
                <flux:navlist.group
                    expandable
                    :heading="__('Courses')"
                    data-section='studentCourses' x-init="if (!isExpanded('studentCourses')) { $nextTick(() => { const btn = $el.querySelector('button'); if (btn && $el.hasAttribute('open')) btn.click(); }); }"
                    @click="saveState('studentCourses', $event)"
                >
                    <flux:navlist.item icon="academic-cap" :href="route('student.courses')" :current="request()->routeIs('student.courses*')" wire:navigate>{{ __('Browse Courses') }}</flux:navlist.item>
                    <flux:navlist.item icon="check-circle" :href="route('student.subscriptions')" :current="request()->routeIs('student.subscriptions*')" wire:navigate>{{ __('My Enrollments') }}</flux:navlist.item>
                </flux:navlist.group>

                <flux:navlist.group
                    expandable
                    :heading="__('Learning')"
                    data-section='studentLearning' x-init="if (!isExpanded('studentLearning')) { $nextTick(() => { const btn = $el.querySelector('button'); if (btn && $el.hasAttribute('open')) btn.click(); }); }"
                    @click="saveState('studentLearning', $event)"
                >
                    <flux:navlist.item icon="calendar-days" :href="route('student.classes.index')" :current="request()->routeIs('student.classes.*')" wire:navigate>{{ __('My Classes') }}</flux:navlist.item>
                    <flux:navlist.item icon="calendar" :href="route('student.timetable')" :current="request()->routeIs('student.timetable')" wire:navigate>{{ __('My Timetable') }}</flux:navlist.item>
                </flux:navlist.group>

                <flux:navlist.group
                    expandable
                    :heading="__('My Account')"
                    data-section='studentAccount' x-init="if (!isExpanded('studentAccount')) { $nextTick(() => { const btn = $el.querySelector('button'); if (btn && $el.hasAttribute('open')) btn.click(); }); }"
                    @click="saveState('studentAccount', $event)"
                >
                    <flux:navlist.item icon="clipboard-document-list" :href="route('student.orders')" :current="request()->routeIs('student.orders*')" wire:navigate>{{ __('Order History') }}</flux:navlist.item>
                    <flux:navlist.item icon="arrow-path" :href="route('student.refund-requests')" :current="request()->routeIs('student.refund-requests*')" wire:navigate>{{ __('Refund Requests') }}</flux:navlist.item>
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

        <!-- Mobile User Menu (Non-Student) -->
        @if(!auth()->user()->isStudent() || auth()->user()->isClassAdmin())
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
        @endif

        {{-- Main content with conditional padding for mobile bottom nav --}}
        @php
            $isStudent = auth()->check() && auth()->user()->isStudent();
        @endphp

        @if($isStudent)
            <flux:main class="lg:pb-0 pb-20">
                {{ $slot }}
            </flux:main>

            {{-- Student mobile bottom navigation --}}
            <x-student.bottom-nav />
        @else
            <flux:main>
                {{ $slot }}
            </flux:main>
        @endif

        @fluxScripts
        @stack('scripts')
    </body>
</html>
