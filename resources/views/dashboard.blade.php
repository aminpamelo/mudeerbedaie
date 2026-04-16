@php
    $user = auth()->user();
    $isAdmin = $user->isAdmin();
    $isTeacher = $user->isTeacher();
    $isStudent = $user->isStudent();

    if ($isAdmin) {
        // Basic Metrics
        $totalCourses = \App\Models\Course::count();
        $activeCourses = \App\Models\Course::where('status', 'active')->count();
        $totalStudents = \App\Models\Student::count();
        $activeStudents = \App\Models\Student::where('status', 'active')->count();
        $totalEnrollments = \App\Models\Enrollment::count();
        $activeEnrollments = \App\Models\Enrollment::whereIn('status', ['enrolled', 'active'])->count();
        $recentEnrollments = \App\Models\Enrollment::with(['student.user', 'course'])
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        // Financial Metrics
        $totalRevenue = \App\Models\Order::where('status', 'paid')->sum('amount');
        $monthlyRevenue = \App\Models\Order::where('status', 'paid')
            ->whereBetween('paid_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->sum('amount');
        $dailyRevenue = \App\Models\Order::where('status', 'paid')
            ->whereDate('paid_at', today())
            ->sum('amount');

        // Payment Success Rate (last 30 days)
        $totalOrdersLast30Days = \App\Models\Order::where('created_at', '>=', now()->subDays(30))->count();
        $paidOrdersLast30Days = \App\Models\Order::where('status', 'paid')
            ->where('created_at', '>=', now()->subDays(30))
            ->count();
        $paymentSuccessRate = $totalOrdersLast30Days > 0 ? round(($paidOrdersLast30Days / $totalOrdersLast30Days) * 100, 1) : 0;

        // Critical Alerts
        $failedPayments = \App\Models\Order::where('status', 'failed')
            ->where('created_at', '>=', now()->subDays(7))
            ->count();
        $pendingOrders = \App\Models\Order::where('status', 'pending')
            ->where('created_at', '>=', now()->subDays(3))
            ->count();
        $subscriptionIssues = \App\Models\Enrollment::whereIn('subscription_status', ['past_due', 'incomplete'])
            ->count();

        // Growth Metrics (compare to last month)
        $lastMonthRevenue = \App\Models\Order::where('status', 'paid')
            ->whereBetween('paid_at', [now()->subMonth()->startOfMonth(), now()->subMonth()->endOfMonth()])
            ->sum('amount');
        $revenueGrowth = $lastMonthRevenue > 0 ? round((($monthlyRevenue - $lastMonthRevenue) / $lastMonthRevenue) * 100, 1) : 0;

        $lastMonthEnrollments = \App\Models\Enrollment::whereBetween('created_at', [now()->subMonth()->startOfMonth(), now()->subMonth()->endOfMonth()])->count();
        $thisMonthEnrollments = \App\Models\Enrollment::whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])->count();
        $enrollmentGrowth = $lastMonthEnrollments > 0 ? round((($thisMonthEnrollments - $lastMonthEnrollments) / $lastMonthEnrollments) * 100, 1) : 0;

        // Top Performing Courses (by revenue)
        $topCourses = \App\Models\Course::withSum(['orders' => function($query) {
                $query->where('status', 'paid');
            }], 'amount')
            ->withCount(['enrollments', 'activeEnrollments'])
            ->get()
            ->filter(fn($course) => $course->orders_sum_amount > 0)
            ->sortByDesc('orders_sum_amount')
            ->take(5);

        // Monthly Recurring Revenue (MRR) - active subscriptions
        $mrr = \App\Models\Enrollment::whereIn('subscription_status', ['active', 'trialing'])
            ->join('courses', 'enrollments.course_id', '=', 'courses.id')
            ->join('course_fee_settings', 'courses.id', '=', 'course_fee_settings.course_id')
            ->sum('course_fee_settings.fee_amount');

        // Recent High-Value Orders
        $highValueOrders = \App\Models\Order::with(['student.user', 'course'])
            ->where('status', 'paid')
            ->where('amount', '>=', 100) // High value threshold
            ->orderBy('paid_at', 'desc')
            ->limit(5)
            ->get();
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
        <flux:header>
            <flux:heading size="xl">
                Welcome back, {{ $user->name }}!
                @if($isAdmin)
                    <flux:badge size="sm" color="zinc">Admin</flux:badge>
                @elseif($isTeacher)
                    <flux:badge size="sm" color="blue">Teacher</flux:badge>
                @elseif($isEmployee)
                    <flux:badge size="sm" color="green">Employee</flux:badge>
                @elseif($isStudent)
                    <flux:badge size="sm" color="emerald">Student</flux:badge>
                @endif
            </flux:heading>
        </flux:header>

        @if($isAdmin)
            <!-- Admin Dashboard -->

            <!-- Date & Quick Actions -->
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <flux:text size="sm" class="text-zinc-500 dark:text-zinc-400 tabular-nums">{{ now()->format('l, F j, Y') }}</flux:text>
                <div class="flex gap-2">
                    <flux:button variant="primary" size="sm" href="{{ route('courses.create') }}">
                        <div class="flex items-center justify-center">
                            <flux:icon name="plus" class="w-4 h-4 mr-1" />
                            Add Course
                        </div>
                    </flux:button>
                    <flux:button variant="outline" size="sm" href="{{ route('enrollments.index') }}">Enrollments</flux:button>
                    <flux:button variant="ghost" size="sm" href="{{ route('orders.index') }}">Orders</flux:button>
                </div>
            </div>

            <!-- Critical Alerts Banner -->
            @if($failedPayments > 0 || $pendingOrders > 0 || $subscriptionIssues > 0)
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
                                    @if($failedPayments > 0) {{ $failedPayments }} failed payments @endif
                                    @if($failedPayments > 0 && ($pendingOrders > 0 || $subscriptionIssues > 0)) · @endif
                                    @if($pendingOrders > 0) {{ $pendingOrders }} pending orders @endif
                                    @if($pendingOrders > 0 && $subscriptionIssues > 0) · @endif
                                    @if($subscriptionIssues > 0) {{ $subscriptionIssues }} subscription issues @endif
                                </flux:text>
                            </div>
                        </div>
                        <div class="flex gap-2">
                            @if($failedPayments > 0)
                                <flux:button variant="outline" size="sm" href="{{ route('orders.index') }}?status=failed">Fix Payments</flux:button>
                            @endif
                            @if($subscriptionIssues > 0)
                                <flux:button variant="outline" size="sm" href="{{ route('enrollments.index') }}?subscription_status=past_due,incomplete">Fix Subscriptions</flux:button>
                            @endif
                        </div>
                    </div>
                </div>
            @endif

            <!-- Revenue Metrics -->
            <div>
                <flux:text size="xs" class="uppercase tracking-widest font-semibold text-zinc-400 dark:text-zinc-500 mb-3">Revenue</flux:text>
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <!-- Total Revenue -->
                    <div class="group relative overflow-hidden rounded-xl border border-zinc-200/80 dark:border-zinc-700/80 bg-white dark:bg-zinc-800/80 p-5 transition-all duration-200 hover:shadow-lg hover:shadow-emerald-500/5 dark:hover:border-zinc-600">
                        <div class="absolute top-0 left-0 right-0 h-0.5 bg-gradient-to-r from-emerald-400 to-teal-500"></div>
                        <div class="flex items-start justify-between">
                            <div class="min-w-0">
                                <flux:text size="sm" class="text-zinc-500 dark:text-zinc-400">Total Revenue</flux:text>
                                <div class="mt-1 text-2xl font-bold tracking-tight text-zinc-900 dark:text-zinc-100">RM {{ number_format($totalRevenue, 2) }}</div>
                                <flux:text size="sm" class="mt-1 text-zinc-400 dark:text-zinc-500">All time</flux:text>
                            </div>
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-emerald-500/10">
                                <flux:icon icon="currency-dollar" class="w-5 h-5 text-emerald-500" />
                            </div>
                        </div>
                    </div>

                    <!-- Monthly Revenue -->
                    <div class="group relative overflow-hidden rounded-xl border border-zinc-200/80 dark:border-zinc-700/80 bg-white dark:bg-zinc-800/80 p-5 transition-all duration-200 hover:shadow-lg hover:shadow-blue-500/5 dark:hover:border-zinc-600">
                        <div class="absolute top-0 left-0 right-0 h-0.5 bg-gradient-to-r from-blue-400 to-indigo-500"></div>
                        <div class="flex items-start justify-between">
                            <div class="min-w-0">
                                <flux:text size="sm" class="text-zinc-500 dark:text-zinc-400">Monthly Revenue</flux:text>
                                <div class="mt-1 text-2xl font-bold tracking-tight text-zinc-900 dark:text-zinc-100">RM {{ number_format($monthlyRevenue, 2) }}</div>
                                <div class="mt-1 flex items-center gap-1">
                                    @if($revenueGrowth >= 0)
                                        <flux:icon icon="arrow-trending-up" class="w-3.5 h-3.5 text-emerald-500" />
                                    @else
                                        <flux:icon icon="arrow-trending-down" class="w-3.5 h-3.5 text-red-500" />
                                    @endif
                                    <flux:text size="sm" class="{{ $revenueGrowth >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400' }}">
                                        {{ $revenueGrowth > 0 ? '+' : '' }}{{ $revenueGrowth }}% vs last month
                                    </flux:text>
                                </div>
                            </div>
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-blue-500/10">
                                <flux:icon icon="chart-bar" class="w-5 h-5 text-blue-500" />
                            </div>
                        </div>
                    </div>

                    <!-- Monthly Recurring Revenue -->
                    <div class="group relative overflow-hidden rounded-xl border border-zinc-200/80 dark:border-zinc-700/80 bg-white dark:bg-zinc-800/80 p-5 transition-all duration-200 hover:shadow-lg hover:shadow-violet-500/5 dark:hover:border-zinc-600">
                        <div class="absolute top-0 left-0 right-0 h-0.5 bg-gradient-to-r from-violet-400 to-purple-500"></div>
                        <div class="flex items-start justify-between">
                            <div class="min-w-0">
                                <flux:text size="sm" class="text-zinc-500 dark:text-zinc-400">Monthly Recurring</flux:text>
                                <div class="mt-1 text-2xl font-bold tracking-tight text-zinc-900 dark:text-zinc-100">RM {{ number_format($mrr, 2) }}</div>
                                <flux:text size="sm" class="mt-1 text-violet-600 dark:text-violet-400">Active subscriptions</flux:text>
                            </div>
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-violet-500/10">
                                <flux:icon icon="arrow-trending-up" class="w-5 h-5 text-violet-500" />
                            </div>
                        </div>
                    </div>

                    <!-- Payment Success -->
                    <div class="group relative overflow-hidden rounded-xl border border-zinc-200/80 dark:border-zinc-700/80 bg-white dark:bg-zinc-800/80 p-5 transition-all duration-200 hover:shadow-lg dark:hover:border-zinc-600">
                        <div class="absolute top-0 left-0 right-0 h-0.5 bg-gradient-to-r {{ $paymentSuccessRate >= 95 ? 'from-emerald-400 to-green-500' : ($paymentSuccessRate >= 90 ? 'from-amber-400 to-yellow-500' : 'from-red-400 to-rose-500') }}"></div>
                        <div class="flex items-start justify-between">
                            <div class="min-w-0">
                                <flux:text size="sm" class="text-zinc-500 dark:text-zinc-400">Payment Success</flux:text>
                                <div class="mt-1 text-2xl font-bold tracking-tight text-zinc-900 dark:text-zinc-100">{{ $paymentSuccessRate }}%</div>
                                <flux:text size="sm" class="mt-1 {{ $paymentSuccessRate >= 95 ? 'text-emerald-600 dark:text-emerald-400' : ($paymentSuccessRate >= 90 ? 'text-amber-600 dark:text-amber-400' : 'text-red-600 dark:text-red-400') }}">
                                    Last 30 days
                                </flux:text>
                            </div>
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl {{ $paymentSuccessRate >= 95 ? 'bg-emerald-500/10' : ($paymentSuccessRate >= 90 ? 'bg-amber-500/10' : 'bg-red-500/10') }}">
                                <flux:icon icon="check-circle" class="w-5 h-5 {{ $paymentSuccessRate >= 95 ? 'text-emerald-500' : ($paymentSuccessRate >= 90 ? 'text-amber-500' : 'text-red-500') }}" />
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Operational Metrics -->
            <div>
                <flux:text size="xs" class="uppercase tracking-widest font-semibold text-zinc-400 dark:text-zinc-500 mb-3">Operations</flux:text>
                <div class="grid gap-4 sm:grid-cols-3">
                    <!-- Active Courses -->
                    <div class="relative overflow-hidden rounded-xl border border-zinc-200/80 dark:border-zinc-700/80 bg-white dark:bg-zinc-800/80 p-5 transition-all duration-200 hover:shadow-lg dark:hover:border-zinc-600">
                        <div class="absolute top-0 left-0 right-0 h-0.5 bg-gradient-to-r from-sky-400 to-cyan-500"></div>
                        <div class="flex items-start justify-between">
                            <div>
                                <flux:text size="sm" class="text-zinc-500 dark:text-zinc-400">Active Courses</flux:text>
                                <div class="mt-1 text-2xl font-bold tracking-tight text-zinc-900 dark:text-zinc-100">{{ $activeCourses }}</div>
                                <flux:text size="sm" class="mt-1 text-zinc-400 dark:text-zinc-500">of {{ $totalCourses }} total</flux:text>
                            </div>
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-sky-500/10">
                                <flux:icon icon="academic-cap" class="w-5 h-5 text-sky-500" />
                            </div>
                        </div>
                    </div>

                    <!-- Active Students -->
                    <div class="relative overflow-hidden rounded-xl border border-zinc-200/80 dark:border-zinc-700/80 bg-white dark:bg-zinc-800/80 p-5 transition-all duration-200 hover:shadow-lg dark:hover:border-zinc-600">
                        <div class="absolute top-0 left-0 right-0 h-0.5 bg-gradient-to-r from-teal-400 to-emerald-500"></div>
                        <div class="flex items-start justify-between">
                            <div>
                                <flux:text size="sm" class="text-zinc-500 dark:text-zinc-400">Active Students</flux:text>
                                <div class="mt-1 text-2xl font-bold tracking-tight text-zinc-900 dark:text-zinc-100">{{ number_format($activeStudents) }}</div>
                                <div class="mt-1 flex items-center gap-1">
                                    @if($enrollmentGrowth >= 0)
                                        <flux:icon icon="arrow-trending-up" class="w-3.5 h-3.5 text-emerald-500" />
                                    @else
                                        <flux:icon icon="arrow-trending-down" class="w-3.5 h-3.5 text-red-500" />
                                    @endif
                                    <flux:text size="sm" class="{{ $enrollmentGrowth >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400' }}">
                                        {{ $enrollmentGrowth > 0 ? '+' : '' }}{{ $enrollmentGrowth }}% this month
                                    </flux:text>
                                </div>
                            </div>
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-teal-500/10">
                                <flux:icon icon="users" class="w-5 h-5 text-teal-500" />
                            </div>
                        </div>
                    </div>

                    <!-- Active Enrollments -->
                    <div class="relative overflow-hidden rounded-xl border border-zinc-200/80 dark:border-zinc-700/80 bg-white dark:bg-zinc-800/80 p-5 transition-all duration-200 hover:shadow-lg dark:hover:border-zinc-600">
                        <div class="absolute top-0 left-0 right-0 h-0.5 bg-gradient-to-r from-indigo-400 to-blue-500"></div>
                        <div class="flex items-start justify-between">
                            <div>
                                <flux:text size="sm" class="text-zinc-500 dark:text-zinc-400">Active Enrollments</flux:text>
                                <div class="mt-1 text-2xl font-bold tracking-tight text-zinc-900 dark:text-zinc-100">{{ number_format($activeEnrollments) }}</div>
                                <flux:text size="sm" class="mt-1 text-indigo-600 dark:text-indigo-400">{{ $thisMonthEnrollments }} new this month</flux:text>
                            </div>
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-indigo-500/10">
                                <flux:icon icon="clipboard-document" class="w-5 h-5 text-indigo-500" />
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Performance Insights -->
            <div class="grid gap-4 lg:grid-cols-2">
                <!-- Top Revenue Courses -->
                <div class="overflow-hidden rounded-xl border border-zinc-200/80 dark:border-zinc-700/80 bg-white dark:bg-zinc-800/80">
                    <div class="flex items-center justify-between px-5 pt-5 pb-3">
                        <div>
                            <flux:heading size="lg">Top Revenue Courses</flux:heading>
                            <flux:text size="sm" class="text-zinc-500 dark:text-zinc-400">Highest earning courses</flux:text>
                        </div>
                        <flux:button variant="ghost" size="sm" href="{{ route('courses.index') }}">View all</flux:button>
                    </div>

                    @if($topCourses->isNotEmpty())
                        <div class="px-5 pb-5 space-y-1">
                            @foreach($topCourses as $index => $course)
                                <div class="flex items-center gap-3 p-3 rounded-lg hover:bg-zinc-50 dark:hover:bg-zinc-700/50 transition-colors">
                                    <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg {{ $index === 0 ? 'bg-amber-500/10 text-amber-600 dark:text-amber-400' : ($index === 1 ? 'bg-zinc-200/60 dark:bg-zinc-600/40 text-zinc-600 dark:text-zinc-300' : 'bg-zinc-100 dark:bg-zinc-700/50 text-zinc-500 dark:text-zinc-400') }} text-sm font-bold tabular-nums">
                                        {{ $index + 1 }}
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <flux:text class="font-medium truncate">{{ $course->name }}</flux:text>
                                        <flux:text size="sm" class="text-zinc-500 dark:text-zinc-400">
                                            {{ $course->enrollments_count }} enrolled · {{ $course->active_enrollments_count }} active
                                        </flux:text>
                                    </div>
                                    <div class="text-right shrink-0">
                                        <div class="text-sm font-semibold text-emerald-600 dark:text-emerald-400 tabular-nums">RM {{ number_format($course->orders_sum_amount, 2) }}</div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="px-5 pb-5">
                            <flux:text class="text-zinc-500 dark:text-zinc-400">No revenue data available yet.</flux:text>
                        </div>
                    @endif
                </div>

                <!-- High-Value Recent Orders -->
                <div class="overflow-hidden rounded-xl border border-zinc-200/80 dark:border-zinc-700/80 bg-white dark:bg-zinc-800/80">
                    <div class="flex items-center justify-between px-5 pt-5 pb-3">
                        <div>
                            <flux:heading size="lg">Recent High-Value Orders</flux:heading>
                            <flux:text size="sm" class="text-zinc-500 dark:text-zinc-400">Orders ≥ RM 100</flux:text>
                        </div>
                        <flux:button variant="ghost" size="sm" href="{{ route('orders.index') }}">View all</flux:button>
                    </div>

                    @if($highValueOrders->isNotEmpty())
                        <div class="px-5 pb-5 space-y-1">
                            @foreach($highValueOrders as $order)
                                <div class="flex items-center gap-3 p-3 rounded-lg hover:bg-zinc-50 dark:hover:bg-zinc-700/50 transition-colors">
                                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-emerald-500/10 ring-1 ring-emerald-500/20">
                                        <span class="text-xs font-bold text-emerald-700 dark:text-emerald-400">{{ $order->student?->user?->initials() ?? '?' }}</span>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <flux:text class="font-medium truncate">{{ $order->student?->user?->name ?? 'Unknown Student' }}</flux:text>
                                        <flux:text size="sm" class="text-zinc-500 dark:text-zinc-400 truncate">{{ $order->course?->name ?? 'Unknown Course' }}</flux:text>
                                    </div>
                                    <div class="text-right shrink-0">
                                        <div class="text-sm font-semibold text-emerald-600 dark:text-emerald-400 tabular-nums">RM {{ number_format($order->amount, 2) }}</div>
                                        <flux:text size="xs" class="text-zinc-400 dark:text-zinc-500">{{ $order->paid_at->diffForHumans() }}</flux:text>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="px-5 pb-5">
                            <flux:text class="text-zinc-500 dark:text-zinc-400">No high-value orders yet.</flux:text>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Recent Enrollments -->
            <div class="overflow-hidden rounded-xl border border-zinc-200/80 dark:border-zinc-700/80 bg-white dark:bg-zinc-800/80">
                <div class="flex items-center justify-between px-5 pt-5 pb-3">
                    <flux:heading size="lg">Recent Enrollments</flux:heading>
                    <flux:link href="{{ route('enrollments.index') }}" variant="subtle">View all</flux:link>
                </div>

                @if($recentEnrollments->isNotEmpty())
                    <div class="px-5 pb-5 space-y-1">
                        @foreach($recentEnrollments as $enrollment)
                            <div class="flex items-center justify-between p-3 rounded-lg hover:bg-zinc-50 dark:hover:bg-zinc-700/50 transition-colors">
                                <div class="flex items-center gap-3">
                                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-zinc-100 dark:bg-zinc-700 ring-1 ring-zinc-200 dark:ring-zinc-600">
                                        <span class="text-xs font-bold text-zinc-600 dark:text-zinc-300">{{ $enrollment->student?->user?->initials() ?? '?' }}</span>
                                    </div>
                                    <div class="min-w-0">
                                        <flux:text class="font-medium">{{ $enrollment->student?->user?->name ?? 'Unknown Student' }}</flux:text>
                                        <flux:text size="sm" class="text-zinc-500 dark:text-zinc-400">{{ $enrollment->course?->name ?? 'Unknown Course' }}</flux:text>
                                    </div>
                                </div>
                                <div class="flex items-center gap-3 shrink-0">
                                    <flux:badge :class="$enrollment->academic_status->badgeClass()">
                                        {{ $enrollment->academic_status->label() }}
                                    </flux:badge>
                                    <flux:text size="sm" class="text-zinc-400 dark:text-zinc-500 tabular-nums">
                                        {{ $enrollment->enrollment_date->format('M d, Y') }}
                                    </flux:text>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="px-5 pb-5">
                        <flux:text class="text-zinc-500 dark:text-zinc-400">No enrollments yet.</flux:text>
                    </div>
                @endif
            </div>

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
