@php
    $user = auth()->user();
    $isAdmin = $user->isAdmin();
    $isTeacher = $user->isTeacher();
    $isStudent = $user->isStudent();

    if ($isAdmin) {
        // E-commerce sales channel figures — same source of truth as the
        // Orders & Package Sales Report (app/Services/Reports/SalesChannelDashboard).
        $salesYear = (int) request('year', (int) date('Y'));
        $sales = new \App\Services\Reports\SalesChannelDashboard($salesYear);
        $availableYears = $sales->availableYears();

        if (! empty($availableYears) && ! in_array($salesYear, $availableYears, true)) {
            $salesYear = $availableYears[0];
            $sales = new \App\Services\Reports\SalesChannelDashboard($salesYear);
        }

        $overview = $sales->overview();
        $monthlyData = $sales->monthlyData();
        $sourceBreakdown = $sales->sourceBreakdown($monthlyData);
        $recentOrders = $sales->recentOrders(6);
        $topProducts = $sales->topProducts(5);

        $yearRevenue = collect($monthlyData)->sum('total_revenue');
        $yearOrders = collect($monthlyData)->sum('total_orders');

        // Academy snapshot (secondary strip) + subscription health for the alert.
        $activeCourses = \App\Models\Course::where('status', 'active')->count();
        $totalCourses = \App\Models\Course::count();
        $activeStudents = \App\Models\Student::where('status', 'active')->count();
        $activeEnrollments = \App\Models\Enrollment::whereIn('status', ['enrolled', 'active'])->count();
        $subscriptionIssues = \App\Models\Enrollment::whereIn('subscription_status', ['past_due', 'incomplete'])->count();
    }
    
    if ($isStudent && $user->student) {
        $studentEnrollments = $user->student->enrollments()
            ->with('course')
            ->orderBy('enrollment_date', 'desc')
            ->limit(6)
            ->get();
        $activeEnrollmentsCount = $user->student->activeEnrollments()->count();
        $completedEnrollmentsCount = $user->student->completedEnrollments()->count();
        
        $savedPaymentMethods = $user->paymentMethods()->active()->count();
    }
    
    $isEmployee = $user->isEmployee();

    if ($isTeacher) {
        $teacherCourses = $user->createdCourses()->withCount(['enrollments', 'activeEnrollments'])->get();
        $totalTeacherEnrollments = \App\Models\Enrollment::whereHas('course', function($q) use ($user) {
            $q->where('created_by', $user->id);
        })->count();
        
        // Mock data for enhanced teacher dashboard
        $todayClasses = [
            (object) ['name' => 'Advanced Laravel Development', 'time' => '09:00', 'duration' => 120, 'students_count' => 24, 'room' => 'Room A1'],
            (object) ['name' => 'PHP Fundamentals', 'time' => '14:00', 'duration' => 90, 'students_count' => 18, 'room' => 'Room B2'],
            (object) ['name' => 'Database Design', 'time' => '16:30', 'duration' => 90, 'students_count' => 15, 'room' => 'Online'],
        ];
        
        $recentActivities = [
            (object) ['type' => 'enrollment', 'message' => 'Sarah Ahmad enrolled in Laravel Basics', 'time' => '2 hours ago', 'icon' => 'user-plus'],
            (object) ['type' => 'assignment', 'message' => 'Assignment submitted for PHP Advanced', 'time' => '4 hours ago', 'icon' => 'document'],
            (object) ['type' => 'course', 'message' => 'New course"Vue.js Essentials" was published', 'time' => '1 day ago', 'icon' => 'academic-cap'],
            (object) ['type' => 'message', 'message' => 'Question posted in Laravel Discussion', 'time' => '2 days ago', 'icon' => 'chat-bubble-left'],
        ];
        
        $weeklyStats = [
            'classes_taught' => 12,
            'students_taught' => 145,
            'assignments_graded' => 38,
            'new_enrollments' => 7,
        ];
        
        $pendingTasks = [
            (object) ['task' => 'Grade PHP Fundamentals Assignment #3', 'due' => 'Today', 'priority' => 'high'],
            (object) ['task' => 'Prepare slides for Laravel Advanced', 'due' => 'Tomorrow', 'priority' => 'medium'],
            (object) ['task' => 'Review course feedback submissions', 'due' => 'This week', 'priority' => 'low'],
        ];
    }
@endphp

<x-layouts.app :title="__('Dashboard')">
    <div class="flex h-full w-full flex-1 flex-col gap-6">
        @unless($isAdmin)
            <flux:header>
                <flux:heading size="xl">
                    Welcome back, {{ $user->name }}!
                    @if($isTeacher)
                        <flux:badge size="sm" color="blue">Teacher</flux:badge>
                    @elseif($isEmployee)
                        <flux:badge size="sm" color="green">Employee</flux:badge>
                    @elseif($isStudent)
                        <flux:badge size="sm" color="emerald">Student</flux:badge>
                    @endif
                </flux:heading>
            </flux:header>
        @endunless

        @if($isAdmin)
            <!-- Admin Dashboard -->

            <!-- Welcome Hero -->
            <div class="relative overflow-hidden rounded-2xl border border-zinc-200/80 bg-gradient-to-br from-indigo-50 via-white to-violet-50/60 p-5 dark:border-zinc-700/80 dark:from-indigo-950/40 dark:via-zinc-900 dark:to-violet-950/20 sm:p-6">
                <div class="pointer-events-none absolute -right-12 -top-12 h-52 w-52 rounded-full bg-gradient-to-br from-indigo-400/20 to-fuchsia-400/20 blur-3xl"></div>
                <div class="relative flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between">
                    <div class="flex items-center gap-4">
                        <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-indigo-500 to-violet-600 text-lg font-bold text-white shadow-lg shadow-indigo-500/30">
                            {{ $user->initials() }}
                        </div>
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <h1 class="text-2xl font-bold tracking-tight text-zinc-900 dark:text-white">Welcome back, {{ $user->name }}!</h1>
                                <flux:badge size="sm" color="indigo">Admin</flux:badge>
                            </div>
                            <p class="mt-1 flex items-center gap-1.5 text-sm text-zinc-500 dark:text-zinc-400">
                                <flux:icon name="calendar" class="h-4 w-4" />
                                <span class="tabular-nums">{{ now()->format('l, F j, Y') }}</span>
                            </p>
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <flux:button variant="primary" size="sm" href="{{ route('admin.orders.report') }}">
                            <div class="flex items-center justify-center">
                                <flux:icon name="chart-bar" class="w-4 h-4 mr-1" />
                                Sales Report
                            </div>
                        </flux:button>
                        <flux:button variant="outline" size="sm" href="{{ route('admin.orders.index') }}">Orders</flux:button>
                        <flux:button variant="ghost" size="sm" href="{{ route('storefront.home') }}" target="_blank">
                            <div class="flex items-center justify-center">
                                <flux:icon name="building-storefront" class="w-4 h-4 mr-1" />
                                Front Store
                            </div>
                        </flux:button>
                    </div>
                </div>
            </div>

            <!-- Critical Alerts Banner -->
            @if($overview['pending_orders'] > 0 || $subscriptionIssues > 0)
                <div class="relative overflow-hidden rounded-xl border border-amber-200/50 dark:border-amber-500/20 bg-gradient-to-r from-amber-50 to-orange-50/50 dark:from-amber-950/40 dark:to-orange-950/20 p-4">
                    <div class="absolute left-0 top-0 bottom-0 w-1 bg-gradient-to-b from-amber-400 to-orange-500"></div>
                    <div class="flex items-center justify-between pl-3">
                        <div class="flex items-center gap-3">
                            <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-amber-500/10 ring-1 ring-amber-500/20">
                                <flux:icon icon="exclamation-triangle" class="w-5 h-5 text-amber-600 dark:text-amber-400" />
                            </div>
                            <div>
                                <flux:heading size="sm" class="text-amber-900 dark:text-amber-200">Attention Required</flux:heading>
                                <flux:text size="sm" class="text-amber-700 dark:text-amber-300/80">
                                    @if($overview['pending_orders'] > 0) {{ number_format($overview['pending_orders']) }} pending orders @endif
                                    @if($overview['pending_orders'] > 0 && $subscriptionIssues > 0) · @endif
                                    @if($subscriptionIssues > 0) {{ $subscriptionIssues }} subscription issues @endif
                                </flux:text>
                            </div>
                        </div>
                        <div class="flex gap-2">
                            @if($overview['pending_orders'] > 0)
                                <flux:button variant="outline" size="sm" href="{{ route('admin.orders.index') }}?status=pending">Review Orders</flux:button>
                            @endif
                            @if($subscriptionIssues > 0)
                                <flux:button variant="outline" size="sm" href="{{ route('enrollments.index') }}?subscription_status=past_due,incomplete">Fix Subscriptions</flux:button>
                            @endif
                        </div>
                    </div>
                </div>
            @endif

            <!-- Sales KPIs -->
            <div>
                <div class="mb-3 flex items-center justify-between">
                    <flux:text size="xs" class="uppercase tracking-widest font-semibold text-zinc-400 dark:text-zinc-500">E-commerce Sales</flux:text>
                    @if(!empty($availableYears))
                        <form method="GET" action="{{ route('dashboard') }}" class="flex items-center gap-2">
                            <label for="year" class="text-xs font-medium text-zinc-400 dark:text-zinc-500">Year</label>
                            <select id="year" name="year" onchange="this.form.submit()"
                                class="rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-2.5 py-1 text-sm text-zinc-700 dark:text-zinc-200 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                @foreach($availableYears as $year)
                                    <option value="{{ $year }}" @selected($year === $salesYear)>{{ $year }}</option>
                                @endforeach
                            </select>
                        </form>
                    @endif
                </div>
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <!-- Total Revenue (all time) -->
                    <div class="group relative overflow-hidden rounded-2xl border border-zinc-200/80 dark:border-zinc-700/60 bg-white dark:bg-zinc-800/70 p-5 transition-all duration-300 hover:-translate-y-0.5 hover:shadow-xl hover:shadow-emerald-500/5 dark:hover:border-zinc-600">
                        <div class="absolute inset-x-0 top-0 h-1 bg-gradient-to-r from-emerald-400 to-teal-500"></div>
                        <div class="pointer-events-none absolute -right-8 -top-10 h-28 w-28 rounded-full bg-emerald-500/25 blur-2xl transition-all duration-300 group-hover:scale-125 group-hover:bg-emerald-500/40 dark:bg-emerald-500/20"></div>
                        <div class="flex items-start justify-between">
                            <div class="min-w-0">
                                <flux:text size="sm" class="text-zinc-500 dark:text-zinc-400">Total Revenue</flux:text>
                                <div class="mt-1 text-2xl font-extrabold tracking-tight bg-gradient-to-br from-emerald-600 to-teal-700 bg-clip-text text-transparent dark:from-emerald-300 dark:to-teal-400">RM {{ number_format($overview['total_revenue'], 2) }}</div>
                                <flux:text size="sm" class="mt-1 text-zinc-400 dark:text-zinc-500">All sources · all time</flux:text>
                            </div>
                            <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-emerald-500 to-teal-600 text-white shadow-lg ring-1 ring-white/25 transition-transform duration-300 group-hover:scale-110 shadow-emerald-500/25">
                                <flux:icon icon="currency-dollar" class="w-5 h-5" />
                            </div>
                        </div>
                    </div>

                    <!-- This Month Revenue -->
                    <div class="group relative overflow-hidden rounded-2xl border border-zinc-200/80 dark:border-zinc-700/60 bg-white dark:bg-zinc-800/70 p-5 transition-all duration-300 hover:-translate-y-0.5 hover:shadow-xl hover:shadow-blue-500/5 dark:hover:border-zinc-600">
                        <div class="absolute inset-x-0 top-0 h-1 bg-gradient-to-r from-blue-400 to-indigo-500"></div>
                        <div class="pointer-events-none absolute -right-8 -top-10 h-28 w-28 rounded-full bg-blue-500/25 blur-2xl transition-all duration-300 group-hover:scale-125 group-hover:bg-blue-500/40 dark:bg-blue-500/20"></div>
                        <div class="flex items-start justify-between">
                            <div class="min-w-0">
                                <flux:text size="sm" class="text-zinc-500 dark:text-zinc-400">This Month</flux:text>
                                <div class="mt-1 text-2xl font-extrabold tracking-tight bg-gradient-to-br from-blue-600 to-indigo-700 bg-clip-text text-transparent dark:from-blue-300 dark:to-indigo-400">RM {{ number_format($overview['this_month_revenue'], 2) }}</div>
                                <div class="mt-1 flex items-center gap-1">
                                    @if($overview['month_growth'] >= 0)
                                        <flux:icon icon="arrow-trending-up" class="w-3.5 h-3.5 text-emerald-500" />
                                    @else
                                        <flux:icon icon="arrow-trending-down" class="w-3.5 h-3.5 text-red-500" />
                                    @endif
                                    <flux:text size="sm" class="{{ $overview['month_growth'] >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400' }}">
                                        {{ $overview['month_growth'] > 0 ? '+' : '' }}{{ number_format($overview['month_growth'], 1) }}% vs last month
                                    </flux:text>
                                </div>
                            </div>
                            <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-blue-500 to-indigo-600 text-white shadow-lg ring-1 ring-white/25 transition-transform duration-300 group-hover:scale-110 shadow-blue-500/25">
                                <flux:icon icon="chart-bar" class="w-5 h-5" />
                            </div>
                        </div>
                    </div>

                    <!-- Total Orders -->
                    <div class="group relative overflow-hidden rounded-2xl border border-zinc-200/80 dark:border-zinc-700/60 bg-white dark:bg-zinc-800/70 p-5 transition-all duration-300 hover:-translate-y-0.5 hover:shadow-xl hover:shadow-violet-500/5 dark:hover:border-zinc-600">
                        <div class="absolute inset-x-0 top-0 h-1 bg-gradient-to-r from-violet-400 to-purple-500"></div>
                        <div class="pointer-events-none absolute -right-8 -top-10 h-28 w-28 rounded-full bg-violet-500/25 blur-2xl transition-all duration-300 group-hover:scale-125 group-hover:bg-violet-500/40 dark:bg-violet-500/20"></div>
                        <div class="flex items-start justify-between">
                            <div class="min-w-0">
                                <flux:text size="sm" class="text-zinc-500 dark:text-zinc-400">Total Orders</flux:text>
                                <div class="mt-1 text-2xl font-extrabold tracking-tight bg-gradient-to-br from-violet-600 to-purple-700 bg-clip-text text-transparent dark:from-violet-300 dark:to-purple-400">{{ number_format($overview['total_orders']) }}</div>
                                <flux:text size="sm" class="mt-1 text-violet-600 dark:text-violet-400">{{ number_format($overview['completion_rate'], 1) }}% completed</flux:text>
                            </div>
                            <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-violet-500 to-purple-600 text-white shadow-lg ring-1 ring-white/25 transition-transform duration-300 group-hover:scale-110 shadow-violet-500/25">
                                <flux:icon icon="shopping-bag" class="w-5 h-5" />
                            </div>
                        </div>
                    </div>

                    <!-- Avg Order Value -->
                    <div class="group relative overflow-hidden rounded-2xl border border-zinc-200/80 dark:border-zinc-700/60 bg-white dark:bg-zinc-800/70 p-5 transition-all duration-300 hover:-translate-y-0.5 hover:shadow-xl dark:hover:border-zinc-600">
                        <div class="absolute inset-x-0 top-0 h-1 bg-gradient-to-r from-amber-400 to-orange-500"></div>
                        <div class="pointer-events-none absolute -right-8 -top-10 h-28 w-28 rounded-full bg-amber-500/20 blur-2xl transition-all duration-300 group-hover:scale-125 dark:bg-amber-500/15"></div>
                        <div class="flex items-start justify-between">
                            <div class="min-w-0">
                                <flux:text size="sm" class="text-zinc-500 dark:text-zinc-400">Avg Order Value</flux:text>
                                <div class="mt-1 text-2xl font-extrabold tracking-tight bg-gradient-to-br from-amber-600 to-orange-700 bg-clip-text text-transparent dark:from-amber-300 dark:to-orange-400">RM {{ number_format($overview['avg_order_value'], 2) }}</div>
                                <flux:text size="sm" class="mt-1 text-zinc-400 dark:text-zinc-500">Today: RM {{ number_format($overview['today_revenue'], 2) }}</flux:text>
                            </div>
                            <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-amber-500 to-orange-600 text-white shadow-lg ring-1 ring-white/25 transition-transform duration-300 group-hover:scale-110 shadow-amber-500/25">
                                <flux:icon icon="calculator" class="w-5 h-5" />
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            @php
                $sourceMeta = [
                    'platform' => ['bar' => 'from-orange-400 to-amber-500', 'icon' => 'globe-alt', 'text' => 'text-orange-600 dark:text-orange-400', 'chip' => 'from-orange-500 to-amber-600 shadow-orange-500/25'],
                    'agent_company' => ['bar' => 'from-blue-400 to-indigo-500', 'icon' => 'building-office-2', 'text' => 'text-blue-600 dark:text-blue-400', 'chip' => 'from-blue-500 to-indigo-600 shadow-blue-500/25'],
                    'funnel' => ['bar' => 'from-violet-400 to-purple-500', 'icon' => 'funnel', 'text' => 'text-violet-600 dark:text-violet-400', 'chip' => 'from-violet-500 to-purple-600 shadow-violet-500/25'],
                    'pos' => ['bar' => 'from-pink-400 to-rose-500', 'icon' => 'computer-desktop', 'text' => 'text-pink-600 dark:text-pink-400', 'chip' => 'from-pink-500 to-rose-600 shadow-pink-500/25'],
                    'fighter' => ['bar' => 'from-red-400 to-rose-500', 'icon' => 'bolt', 'text' => 'text-red-600 dark:text-red-400', 'chip' => 'from-red-500 to-rose-600 shadow-red-500/25'],
                ];
            @endphp

            <!-- Sales by Channel -->
            <div>
                <flux:text size="xs" class="uppercase tracking-widest font-semibold text-zinc-400 dark:text-zinc-500 mb-3">Sales by Channel · {{ $salesYear }}</flux:text>
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                    @foreach($sourceBreakdown as $source)
                        @php $meta = $sourceMeta[$source['key']]; @endphp
                        <div class="group relative overflow-hidden rounded-2xl border border-zinc-200/80 dark:border-zinc-700/60 bg-white dark:bg-zinc-800/70 p-5 transition-all duration-300 hover:-translate-y-0.5 hover:shadow-xl dark:hover:border-zinc-600">
                            <div class="absolute inset-x-0 top-0 h-1 bg-gradient-to-r {{ $meta['bar'] }}"></div>
                            <div class="flex items-start justify-between">
                                <flux:text size="sm" class="font-semibold text-zinc-600 dark:text-zinc-300">{{ $source['label'] }}</flux:text>
                                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br {{ $meta['chip'] }} text-white shadow-lg ring-1 ring-white/25 transition-transform duration-300 group-hover:scale-110">
                                    <flux:icon icon="{{ $meta['icon'] }}" class="w-4 h-4" />
                                </div>
                            </div>
                            <div class="mt-3 text-xl font-extrabold tracking-tight text-zinc-900 dark:text-white tabular-nums">{{ number_format($source['orders']) }} <span class="text-sm font-medium text-zinc-400 dark:text-zinc-500">orders</span></div>
                            <div class="mt-1 text-sm font-semibold {{ $meta['text'] }} tabular-nums">RM {{ number_format($source['revenue'], 2) }}</div>
                        </div>
                    @endforeach
                </div>
            </div>

            <!-- Charts -->
            <div class="grid gap-4 lg:grid-cols-2">
                <div class="overflow-hidden rounded-xl border border-zinc-200/80 dark:border-zinc-700/80 bg-white dark:bg-zinc-800/80 p-5">
                    <flux:heading size="lg">Monthly Revenue Trend</flux:heading>
                    <flux:text size="sm" class="text-zinc-500 dark:text-zinc-400">Revenue and order count by month · {{ $salesYear }}</flux:text>
                    <div class="mt-4 h-72">
                        <canvas id="dashboardRevenueTrendChart"></canvas>
                    </div>
                </div>
                <div class="overflow-hidden rounded-xl border border-zinc-200/80 dark:border-zinc-700/80 bg-white dark:bg-zinc-800/80 p-5">
                    <flux:heading size="lg">Orders by Source</flux:heading>
                    <flux:text size="sm" class="text-zinc-500 dark:text-zinc-400">Channel comparison by month · {{ $salesYear }}</flux:text>
                    <div class="mt-4 h-72">
                        <canvas id="dashboardBySourceChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Recent Orders + Top Products -->
            <div class="grid gap-4 lg:grid-cols-2">
                <!-- Recent Orders -->
                <div class="overflow-hidden rounded-xl border border-zinc-200/80 dark:border-zinc-700/80 bg-white dark:bg-zinc-800/80">
                    <div class="flex items-center justify-between px-5 pt-5 pb-3">
                        <div>
                            <flux:heading size="lg">Recent Orders</flux:heading>
                            <flux:text size="sm" class="text-zinc-500 dark:text-zinc-400">Latest across every channel</flux:text>
                        </div>
                        <flux:button variant="ghost" size="sm" href="{{ route('admin.orders.index') }}">View all</flux:button>
                    </div>

                    @if($recentOrders->isNotEmpty())
                        <div class="px-5 pb-5 space-y-1">
                            @foreach($recentOrders as $order)
                                <div class="flex items-center gap-3 p-3 rounded-lg hover:bg-zinc-50 dark:hover:bg-zinc-700/50 transition-colors">
                                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-indigo-500/10 ring-1 ring-indigo-500/20">
                                        <span class="text-xs font-bold text-indigo-700 dark:text-indigo-400">{{ strtoupper(mb_substr($order['customer'], 0, 1)) }}</span>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <flux:text class="font-medium truncate">{{ $order['customer'] }}</flux:text>
                                        <div class="flex items-center gap-1.5">
                                            <flux:text size="sm" class="text-zinc-500 dark:text-zinc-400 truncate">{{ $order['number'] }}</flux:text>
                                            <span class="inline-flex items-center rounded-full bg-zinc-100 dark:bg-zinc-700 px-1.5 py-0.5 text-[10px] font-semibold text-zinc-600 dark:text-zinc-300">{{ $order['source'] }}</span>
                                        </div>
                                    </div>
                                    <div class="text-right shrink-0">
                                        <div class="text-sm font-semibold text-emerald-600 dark:text-emerald-400 tabular-nums">RM {{ number_format($order['amount'], 2) }}</div>
                                        <flux:text size="xs" class="text-zinc-400 dark:text-zinc-500">{{ $order['date']?->diffForHumans() }}</flux:text>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="px-5 pb-5">
                            <flux:text class="text-zinc-500 dark:text-zinc-400">No orders yet.</flux:text>
                        </div>
                    @endif
                </div>

                <!-- Top Products -->
                <div class="overflow-hidden rounded-xl border border-zinc-200/80 dark:border-zinc-700/80 bg-white dark:bg-zinc-800/80">
                    <div class="flex items-center justify-between px-5 pt-5 pb-3">
                        <div>
                            <flux:heading size="lg">Top Products</flux:heading>
                            <flux:text size="sm" class="text-zinc-500 dark:text-zinc-400">Best sellers by revenue · {{ $salesYear }}</flux:text>
                        </div>
                        <flux:button variant="ghost" size="sm" href="{{ route('admin.orders.report') }}?tab=products">View all</flux:button>
                    </div>

                    @if(!empty($topProducts))
                        <div class="px-5 pb-5 space-y-1">
                            @foreach($topProducts as $index => $product)
                                <div class="flex items-center gap-3 p-3 rounded-lg hover:bg-zinc-50 dark:hover:bg-zinc-700/50 transition-colors">
                                    <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg {{ $index === 0 ? 'bg-gradient-to-br from-amber-400 to-yellow-500 text-white shadow-sm shadow-amber-500/30' : ($index === 1 ? 'bg-gradient-to-br from-zinc-300 to-zinc-400 text-white' : ($index === 2 ? 'bg-gradient-to-br from-orange-400 to-amber-600 text-white' : 'bg-zinc-100 dark:bg-zinc-700/50 text-zinc-500 dark:text-zinc-400')) }} text-sm font-bold tabular-nums">
                                        {{ $index + 1 }}
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <flux:text class="font-medium truncate">{{ $product['name'] }}</flux:text>
                                        <flux:text size="sm" class="text-zinc-500 dark:text-zinc-400">
                                            {{ number_format($product['units']) }} units · {{ number_format($product['orders']) }} orders
                                        </flux:text>
                                    </div>
                                    <div class="text-right shrink-0">
                                        <div class="text-sm font-semibold text-emerald-600 dark:text-emerald-400 tabular-nums">RM {{ number_format($product['revenue'], 2) }}</div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="px-5 pb-5">
                            <flux:text class="text-zinc-500 dark:text-zinc-400">No product sales in {{ $salesYear }} yet.</flux:text>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Academy Snapshot (secondary) -->
            <div>
                <flux:text size="xs" class="uppercase tracking-widest font-semibold text-zinc-400 dark:text-zinc-500 mb-3">Academy Snapshot</flux:text>
                <div class="grid gap-4 sm:grid-cols-3">
                    <div class="flex items-center gap-3 rounded-2xl border border-zinc-200/80 dark:border-zinc-700/60 bg-white dark:bg-zinc-800/70 p-4">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-sky-500 to-cyan-600 text-white shadow-lg shadow-sky-500/25">
                            <flux:icon icon="academic-cap" class="w-5 h-5" />
                        </div>
                        <div>
                            <div class="text-lg font-extrabold text-zinc-900 dark:text-white tabular-nums">{{ number_format($activeCourses) }}</div>
                            <flux:text size="sm" class="text-zinc-500 dark:text-zinc-400">Active courses · {{ $totalCourses }} total</flux:text>
                        </div>
                    </div>
                    <div class="flex items-center gap-3 rounded-2xl border border-zinc-200/80 dark:border-zinc-700/60 bg-white dark:bg-zinc-800/70 p-4">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-teal-500 to-emerald-600 text-white shadow-lg shadow-teal-500/25">
                            <flux:icon icon="users" class="w-5 h-5" />
                        </div>
                        <div>
                            <div class="text-lg font-extrabold text-zinc-900 dark:text-white tabular-nums">{{ number_format($activeStudents) }}</div>
                            <flux:text size="sm" class="text-zinc-500 dark:text-zinc-400">Active students</flux:text>
                        </div>
                    </div>
                    <div class="flex items-center gap-3 rounded-2xl border border-zinc-200/80 dark:border-zinc-700/60 bg-white dark:bg-zinc-800/70 p-4">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-indigo-500 to-blue-600 text-white shadow-lg shadow-indigo-500/25">
                            <flux:icon icon="clipboard-document" class="w-5 h-5" />
                        </div>
                        <div>
                            <div class="text-lg font-extrabold text-zinc-900 dark:text-white tabular-nums">{{ number_format($activeEnrollments) }}</div>
                            <flux:text size="sm" class="text-zinc-500 dark:text-zinc-400">Active enrollments</flux:text>
                        </div>
                    </div>
                </div>
            </div>

            @vite('resources/js/reports-charts.js')
            <script>
                (function () {
                    const monthlyData = @json(array_values($monthlyData));

                    function renderDashboardCharts(retry = 0) {
                        if (typeof window.initializeDashboardSalesCharts === 'function') {
                            window.initializeDashboardSalesCharts(monthlyData);
                        } else if (retry < 40) {
                            setTimeout(() => renderDashboardCharts(retry + 1), 100);
                        }
                    }

                    if (document.readyState === 'loading') {
                        document.addEventListener('DOMContentLoaded', () => renderDashboardCharts());
                    } else {
                        renderDashboardCharts();
                    }
                    document.addEventListener('livewire:navigated', () => renderDashboardCharts());
                })();
            </script>

        @endif

        @if($isStudent && $user->student)
            <!-- Student Dashboard -->
            <div class="grid gap-6 md:grid-cols-3">
                <flux:card>
                    <div class="flex items-center justify-between">
                        <div>
                            <flux:heading size="sm" class="text-gray-600">Active Courses</flux:heading>
                            <flux:heading size="xl">{{ $activeEnrollmentsCount }}</flux:heading>
                            <flux:text size="sm" class="text-blue-600">Currently enrolled</flux:text>
                        </div>
                        <flux:icon icon="academic-cap" class="w-8 h-8 text-blue-500" />
                    </div>
                </flux:card>

                <flux:card>
                    <div class="flex items-center justify-between">
                        <div>
                            <flux:heading size="sm" class="text-gray-600">Completed Courses</flux:heading>
                            <flux:heading size="xl">{{ $completedEnrollmentsCount }}</flux:heading>
                            <flux:text size="sm" class="text-emerald-600">Successfully finished</flux:text>
                        </div>
                        <flux:icon icon="trophy" class="w-8 h-8 text-emerald-500" />
                    </div>
                </flux:card>


                <flux:card>
                    <div class="flex items-center justify-between">
                        <div>
                            <flux:heading size="sm" class="text-gray-600">Payment Methods</flux:heading>
                            <flux:heading size="xl">{{ $savedPaymentMethods }}</flux:heading>
                            <flux:text size="sm" class="text-gray-600">
                                <flux:link :href="route('student.payment-methods')" class="hover:text-blue-600">Manage cards</flux:link>
                            </flux:text>
                        </div>
                        <flux:icon icon="credit-card" class="w-8 h-8 text-purple-500" />
                    </div>
                </flux:card>
            </div>


            <!-- My Courses -->
            <flux:card>
                <flux:header>
                    <flux:heading size="lg">My Courses</flux:heading>
                </flux:header>
                
                @if($studentEnrollments->isNotEmpty())
                    <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                        @foreach($studentEnrollments as $enrollment)
                            <div class="p-4 border rounded-lg">
                                <div class="flex items-start justify-between mb-3">
                                    <flux:heading size="sm">{{ $enrollment->course->name }}</flux:heading>
                                    <flux:badge :class="$enrollment->academic_status->badgeClass()" size="sm">
                                        {{ $enrollment->academic_status->label() }}
                                    </flux:badge>
                                </div>
                                @if($enrollment->course->description)
                                    <flux:text size="sm" class="text-gray-600  mb-3">
                                        {{ Str::limit($enrollment->course->description, 100) }}
                                    </flux:text>
                                @endif
                                <div class="flex justify-between items-center text-sm text-gray-600">
                                    <span>Enrolled: {{ $enrollment->enrollment_date->format('M d, Y') }}</span>
                                    @if($enrollment->completion_date)
                                        <span>Completed: {{ $enrollment->completion_date->format('M d, Y') }}</span>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <flux:text class="text-gray-600">You're not enrolled in any courses yet.</flux:text>
                @endif
            </flux:card>
        @endif

        @if($isEmployee)
            <!-- Employee Dashboard -->
            <div class="grid gap-6">
                <flux:card class="text-center p-8">
                    <div class="flex flex-col items-center gap-4">
                        <div class="w-16 h-16 bg-blue-100 dark:bg-blue-900/30 rounded-full flex items-center justify-center">
                            <flux:icon.briefcase class="w-8 h-8 text-blue-600 dark:text-blue-400" />
                        </div>
                        <div>
                            <flux:heading size="lg">HR Portal</flux:heading>
                            <flux:text class="mt-1 text-gray-600">View your profile, employment details, and manage your information</flux:text>
                        </div>
                        <flux:button variant="primary" href="/hr" class="mt-2">
                            <div class="flex items-center justify-center">
                                <flux:icon name="arrow-right" class="w-4 h-4 mr-1" />
                                Go to HR Portal
                            </div>
                        </flux:button>
                    </div>
                </flux:card>
            </div>
        @endif

        @if($isTeacher)
            <!-- Teacher Dashboard -->
            
            <!-- Quick Actions Bar -->
            <div class="mb-6 flex items-center justify-between">
                <div>
                    <flux:heading size="xl">Teacher Dashboard</flux:heading>
                    <flux:text class="mt-2">Manage your courses, track student progress, and stay organized</flux:text>
                </div>
                <div class="flex gap-2">
                    <flux:button variant="primary" icon="plus">Create Course</flux:button>
                    <flux:button variant="outline" icon="calendar">Schedule Class</flux:button>
                    <flux:button variant="ghost" icon="document-text">View Reports</flux:button>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="grid gap-6 md:grid-cols-4">
                <flux:card>
                    <div class="flex items-center justify-between">
                        <div>
                            <flux:heading size="sm" class="text-gray-600">Active Courses</flux:heading>
                            <flux:heading size="xl">{{ $teacherCourses->count() }}</flux:heading>
                            <flux:text size="sm" class="text-blue-600">{{ $teacherCourses->where('status', 'active')->count() }} published</flux:text>
                        </div>
                        <flux:icon icon="academic-cap" class="w-8 h-8 text-blue-500" />
                    </div>
                </flux:card>

                <flux:card>
                    <div class="flex items-center justify-between">
                        <div>
                            <flux:heading size="sm" class="text-gray-600">Total Students</flux:heading>
                            <flux:heading size="xl">{{ $totalTeacherEnrollments }}</flux:heading>
                            <flux:text size="sm" class="text-emerald-600">Across all courses</flux:text>
                        </div>
                        <flux:icon icon="users" class="w-8 h-8 text-emerald-500" />
                    </div>
                </flux:card>

                <flux:card>
                    <div class="flex items-center justify-between">
                        <div>
                            <flux:heading size="sm" class="text-gray-600">This Week</flux:heading>
                            <flux:heading size="xl">{{ $weeklyStats['classes_taught'] }}</flux:heading>
                            <flux:text size="sm" class="text-purple-600">Classes taught</flux:text>
                        </div>
                        <flux:icon icon="presentation-chart-line" class="w-8 h-8 text-purple-500" />
                    </div>
                </flux:card>

                <flux:card>
                    <div class="flex items-center justify-between">
                        <div>
                            <flux:heading size="sm" class="text-gray-600">Pending Tasks</flux:heading>
                            <flux:heading size="xl">{{ count($pendingTasks) }}</flux:heading>
                            <flux:text size="sm" class="text-orange-600">Need attention</flux:text>
                        </div>
                        <flux:icon icon="exclamation-triangle" class="w-8 h-8 text-orange-500" />
                    </div>
                </flux:card>
            </div>

            <!-- Main Content Grid -->
            <div class="grid gap-6 lg:grid-cols-3">
                <!-- Today's Schedule -->
                <div class="lg:col-span-2">
                    <flux:card>
                        <flux:header>
                            <flux:heading size="lg">Today's Schedule</flux:heading>
                            <flux:text size="sm" class="text-gray-600">{{ date('l, F j, Y') }}</flux:text>
                        </flux:header>
                        
                        <div class="space-y-4">
                            @foreach($todayClasses as $class)
                                <div class="flex items-center justify-between p-4 bg-gray-50  rounded-lg">
                                    <div class="flex items-center space-x-4">
                                        <div class="text-center">
                                            <flux:text size="sm" class="text-gray-600">{{ $class->time }}</flux:text>
                                            <flux:text size="xs" class="text-gray-500">{{ $class->duration }}min</flux:text>
                                        </div>
                                        <div>
                                            <flux:heading size="sm">{{ $class->name }}</flux:heading>
                                            <flux:text size="sm" class="text-gray-600">
                                                {{ $class->students_count }} students • {{ $class->room }}
                                            </flux:text>
                                        </div>
                                    </div>
                                    <div class="flex items-center space-x-2">
                                        <flux:button variant="ghost" size="sm" icon="video-camera">Join</flux:button>
                                        <flux:button variant="ghost" size="sm" icon="document">Materials</flux:button>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        
                        <div class="mt-4 pt-4 border-t border-gray-200">
                            <flux:link href="#" variant="subtle" icon="calendar-days">View full schedule</flux:link>
                        </div>
                    </flux:card>
                </div>

                <!-- Pending Tasks & Activities -->
                <div class="space-y-6">
                    <!-- Pending Tasks -->
                    <flux:card>
                        <flux:header>
                            <flux:heading size="lg">Pending Tasks</flux:heading>
                        </flux:header>
                        
                        <div class="space-y-3">
                            @foreach($pendingTasks as $task)
                                <div class="flex items-start space-x-3 p-3 bg-gray-50  rounded-lg">
                                    <flux:icon 
                                        icon="{{ $task->priority === 'high' ? 'exclamation-circle' : ($task->priority === 'medium' ? 'clock' : 'information-circle') }}" 
                                        class="w-5 h-5 mt-0.5 {{ $task->priority === 'high' ? 'text-red-500' : ($task->priority === 'medium' ? 'text-yellow-500' : 'text-blue-500') }}" 
                                    />
                                    <div class="flex-1">
                                        <flux:text size="sm">{{ $task->task }}</flux:text>
                                        <flux:text size="xs" class="text-gray-600">Due: {{ $task->due }}</flux:text>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        
                        <div class="mt-4 pt-4 border-t border-gray-200">
                            <flux:link href="#" variant="subtle">View all tasks</flux:link>
                        </div>
                    </flux:card>

                    <!-- Recent Activity -->
                    <flux:card>
                        <flux:header>
                            <flux:heading size="lg">Recent Activity</flux:heading>
                        </flux:header>
                        
                        <div class="space-y-3">
                            @foreach($recentActivities as $activity)
                                <div class="flex items-start space-x-3">
                                    <div class="w-8 h-8 bg-gray-100  rounded-full flex items-center justify-center">
                                        <flux:icon icon="{{ $activity->icon }}" class="w-4 h-4 text-gray-600" />
                                    </div>
                                    <div class="flex-1">
                                        <flux:text size="sm">{{ $activity->message }}</flux:text>
                                        <flux:text size="xs" class="text-gray-600">{{ $activity->time }}</flux:text>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        
                        <div class="mt-4 pt-4 border-t border-gray-200">
                            <flux:link href="#" variant="subtle">View all activity</flux:link>
                        </div>
                    </flux:card>
                </div>
            </div>

            <!-- My Courses Section -->
            <flux:card>
                <flux:header>
                    <flux:heading size="lg">My Courses</flux:heading>
                    <div class="flex items-center space-x-2">
                        <flux:button variant="outline" size="sm">Filter</flux:button>
                        <flux:button variant="primary" size="sm" icon="plus">Create Course</flux:button>
                    </div>
                </flux:header>
                
                @if($teacherCourses->isNotEmpty())
                    <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                        @foreach($teacherCourses as $course)
                            <div class="p-6 border border-gray-200  rounded-xl hover:shadow-md transition-shadow">
                                <div class="flex items-start justify-between mb-4">
                                    <flux:badge :color="$course->status === 'active' ? 'emerald' : 'gray'" size="sm">
                                        {{ ucfirst($course->status) }}
                                    </flux:badge>
                                    <flux:dropdown>
                                        <flux:button variant="ghost" size="sm" icon="ellipsis-vertical"></flux:button>
                                        <flux:menu>
                                            <flux:menu.item icon="pencil">Edit Course</flux:menu.item>
                                            <flux:menu.item icon="eye">View Details</flux:menu.item>
                                            <flux:menu.item icon="users">Manage Students</flux:menu.item>
                                            <flux:menu.separator />
                                            <flux:menu.item icon="archive-box" variant="danger">Archive</flux:menu.item>
                                        </flux:menu>
                                    </flux:dropdown>
                                </div>
                                
                                <flux:heading size="sm" class="mb-2">{{ $course->name }}</flux:heading>
                                
                                @if($course->description)
                                    <flux:text size="sm" class="text-gray-600  mb-4">
                                        {{ Str::limit($course->description, 100) }}
                                    </flux:text>
                                @endif
                                
                                <div class="flex items-center justify-between text-sm text-gray-600  mb-4">
                                    <span class="flex items-center">
                                        <flux:icon icon="users" class="w-4 h-4 mr-1" />
                                        {{ $course->enrollments_count }} students
                                    </span>
                                    <span class="flex items-center">
                                        <flux:icon icon="chart-bar" class="w-4 h-4 mr-1" />
                                        {{ $course->active_enrollments_count }} active
                                    </span>
                                </div>
                                
                                <div class="flex items-center space-x-2">
                                    <flux:button variant="primary" size="sm" class="flex-1">Manage</flux:button>
                                    <flux:button variant="outline" size="sm" icon="chart-bar">Analytics</flux:button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="text-center py-12">
                        <flux:icon icon="academic-cap" class="w-12 h-12 text-gray-400 mx-auto mb-4" />
                        <flux:heading size="lg" class="text-gray-600  mb-2">No courses yet</flux:heading>
                        <flux:text class="text-gray-600  mb-6">Start creating your first course to begin teaching</flux:text>
                        <flux:button variant="primary" icon="plus">Create Your First Course</flux:button>
                    </div>
                @endif
            </flux:card>
        @endif
    </div>
</x-layouts.app>
