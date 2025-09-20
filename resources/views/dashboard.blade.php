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
            ->having('orders_sum_amount', '>', 0)
            ->orderBy('orders_sum_amount', 'desc')
            ->limit(5)
            ->get();

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
                @elseif($isStudent)
                    <flux:badge size="sm" color="emerald">Student</flux:badge>
                @endif
            </flux:heading>
        </flux:header>

        @if($isAdmin)
            <!-- Business Owner Dashboard -->

            <!-- Critical Alerts Banner -->
            @if($failedPayments > 0 || $pendingOrders > 0 || $subscriptionIssues > 0)
                <flux:card class="border-orange-200 bg-orange-50  /20">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-3">
                            <flux:icon icon="exclamation-triangle" class="w-6 h-6 text-orange-600" />
                            <div>
                                <flux:heading size="sm" class="text-orange-800">Attention Required</flux:heading>
                                <flux:text size="sm" class="text-orange-700">
                                    @if($failedPayments > 0) {{ $failedPayments }} failed payments • @endif
                                    @if($pendingOrders > 0) {{ $pendingOrders }} pending orders • @endif
                                    @if($subscriptionIssues > 0) {{ $subscriptionIssues }} subscription issues @endif
                                </flux:text>
                            </div>
                        </div>
                        <div class="flex space-x-2">
                            @if($failedPayments > 0)
                                <flux:button variant="outline" size="sm" href="{{ route('orders.index') }}?status=failed">Fix Payments</flux:button>
                            @endif
                            @if($subscriptionIssues > 0)
                                <flux:button variant="outline" size="sm" href="{{ route('enrollments.index') }}?subscription_status=past_due,incomplete">Fix Subscriptions</flux:button>
                            @endif
                        </div>
                    </div>
                </flux:card>
            @endif

            <!-- Quick Actions Header -->
            <div class="mb-6 flex items-center justify-between">
                <div>
                    <flux:heading size="xl">Business Overview</flux:heading>
                    <flux:text class="mt-2">Your platform's performance and key business metrics</flux:text>
                </div>
                <div class="flex gap-2">
                    <flux:button variant="primary" href="{{ route('courses.create') }}">Add Course</flux:button>
                    <flux:button variant="outline" href="{{ route('enrollments.index') }}">Manage Enrollments</flux:button>
                    <flux:button variant="ghost" href="{{ route('orders.index') }}">View Orders</flux:button>
                </div>
            </div>

            <!-- Financial Overview -->
            <div class="grid gap-6 md:grid-cols-4">
                <flux:card>
                    <div class="flex items-center justify-between">
                        <div>
                            <flux:heading size="sm" class="text-gray-600">Total Revenue</flux:heading>
                            <flux:heading size="xl">RM {{ number_format($totalRevenue, 2) }}</flux:heading>
                            <flux:text size="sm" class="text-blue-600">All time</flux:text>
                        </div>
                        <flux:icon icon="currency-dollar" class="w-8 h-8 text-green-500" />
                    </div>
                </flux:card>

                <flux:card>
                    <div class="flex items-center justify-between">
                        <div>
                            <flux:heading size="sm" class="text-gray-600">Monthly Revenue</flux:heading>
                            <flux:heading size="xl">RM {{ number_format($monthlyRevenue, 2) }}</flux:heading>
                            <flux:text size="sm" class="{{ $revenueGrowth >= 0 ? 'text-emerald-600' : 'text-red-600' }}">
                                {{ $revenueGrowth > 0 ? '+' : '' }}{{ $revenueGrowth }}% vs last month
                            </flux:text>
                        </div>
                        <flux:icon icon="chart-bar" class="w-8 h-8 text-blue-500" />
                    </div>
                </flux:card>

                <flux:card>
                    <div class="flex items-center justify-between">
                        <div>
                            <flux:heading size="sm" class="text-gray-600">Monthly Recurring Revenue</flux:heading>
                            <flux:heading size="xl">RM {{ number_format($mrr, 2) }}</flux:heading>
                            <flux:text size="sm" class="text-purple-600">Active subscriptions</flux:text>
                        </div>
                        <flux:icon icon="arrow-trending-up" class="w-8 h-8 text-purple-500" />
                    </div>
                </flux:card>

                <flux:card>
                    <div class="flex items-center justify-between">
                        <div>
                            <flux:heading size="sm" class="text-gray-600">Payment Success</flux:heading>
                            <flux:heading size="xl">{{ $paymentSuccessRate }}%</flux:heading>
                            <flux:text size="sm" class="{{ $paymentSuccessRate >= 95 ? 'text-emerald-600' : ($paymentSuccessRate >= 90 ? 'text-yellow-600' : 'text-red-600') }}">
                                Last 30 days
                            </flux:text>
                        </div>
                        <flux:icon icon="check-circle" class="w-8 h-8 text-emerald-500" />
                    </div>
                </flux:card>
            </div>

            <!-- Business Health Metrics -->
            <div class="grid gap-6 md:grid-cols-3">
                <flux:card>
                    <div class="flex items-center justify-between">
                        <div>
                            <flux:heading size="sm" class="text-gray-600">Active Courses</flux:heading>
                            <flux:heading size="xl">{{ $activeCourses }}</flux:heading>
                            <flux:text size="sm" class="text-gray-600">of {{ $totalCourses }} total</flux:text>
                        </div>
                        <flux:icon icon="academic-cap" class="w-8 h-8 text-blue-500" />
                    </div>
                </flux:card>

                <flux:card>
                    <div class="flex items-center justify-between">
                        <div>
                            <flux:heading size="sm" class="text-gray-600">Active Students</flux:heading>
                            <flux:heading size="xl">{{ $activeStudents }}</flux:heading>
                            <flux:text size="sm" class="{{ $enrollmentGrowth >= 0 ? 'text-emerald-600' : 'text-red-600' }}">
                                {{ $enrollmentGrowth > 0 ? '+' : '' }}{{ $enrollmentGrowth }}% growth this month
                            </flux:text>
                        </div>
                        <flux:icon icon="users" class="w-8 h-8 text-emerald-500" />
                    </div>
                </flux:card>

                <flux:card>
                    <div class="flex items-center justify-between">
                        <div>
                            <flux:heading size="sm" class="text-gray-600">Active Enrollments</flux:heading>
                            <flux:heading size="xl">{{ $activeEnrollments }}</flux:heading>
                            <flux:text size="sm" class="text-blue-600">{{ $thisMonthEnrollments }} new this month</flux:text>
                        </div>
                        <flux:icon icon="clipboard-document" class="w-8 h-8 text-blue-500" />
                    </div>
                </flux:card>
            </div>

            <!-- Performance Insights Row -->
            <div class="grid gap-6 lg:grid-cols-2">
                <!-- Top Performing Courses -->
                <flux:card>
                    <flux:header>
                        <flux:heading size="lg">Top Revenue Courses</flux:heading>
                        <flux:text size="sm" class="text-gray-600">Highest earning courses</flux:text>
                    </flux:header>

                    @if($topCourses->isNotEmpty())
                        <div class="space-y-4">
                            @foreach($topCourses as $course)
                                <div class="flex items-center justify-between p-4 bg-gray-50  rounded-lg">
                                    <div class="flex-1">
                                        <flux:heading size="sm">{{ $course->name }}</flux:heading>
                                        <flux:text size="sm" class="text-gray-600">
                                            {{ $course->enrollments_count }} enrollments • {{ $course->active_enrollments_count }} active
                                        </flux:text>
                                    </div>
                                    <div class="text-right">
                                        <flux:heading size="sm" class="text-green-600">RM {{ number_format($course->orders_sum_amount, 2) }}</flux:heading>
                                        <flux:text size="xs" class="text-gray-500">Revenue</flux:text>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <flux:text class="text-gray-600">No revenue data available yet.</flux:text>
                    @endif
                </flux:card>

                <!-- High-Value Recent Orders -->
                <flux:card>
                    <flux:header>
                        <flux:heading size="lg">Recent High-Value Orders</flux:heading>
                        <flux:text size="sm" class="text-gray-600">Orders ≥ RM 100</flux:text>
                    </flux:header>

                    @if($highValueOrders->isNotEmpty())
                        <div class="space-y-4">
                            @foreach($highValueOrders as $order)
                                <div class="flex items-center justify-between p-4 bg-gray-50  rounded-lg">
                                    <div class="flex items-center space-x-3">
                                        <div class="w-8 h-8 bg-green-100  rounded-full flex items-center justify-center">
                                            <span class="text-xs font-medium text-green-700">{{ $order->student->user->initials() }}</span>
                                        </div>
                                        <div>
                                            <flux:text class="font-medium">{{ $order->student->user->name }}</flux:text>
                                            <flux:text size="sm" class="text-gray-600">{{ $order->course->name }}</flux:text>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <flux:heading size="sm" class="text-green-600">RM {{ number_format($order->amount, 2) }}</flux:heading>
                                        <flux:text size="xs" class="text-gray-500">{{ $order->paid_at->diffForHumans() }}</flux:text>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <flux:text class="text-gray-600">No high-value orders yet.</flux:text>
                    @endif
                </flux:card>
            </div>

            <!-- Recent Activity -->
            <flux:card>
                <flux:header>
                    <flux:heading size="lg">Recent Enrollments</flux:heading>
                    <flux:link href="{{ route('enrollments.index') }}" variant="subtle">View all</flux:link>
                </flux:header>

                @if($recentEnrollments->isNotEmpty())
                    <div class="space-y-4">
                        @foreach($recentEnrollments as $enrollment)
                            <div class="flex items-center justify-between p-4 border rounded-lg">
                                <div class="flex items-center space-x-4">
                                    <div class="w-10 h-10 bg-gray-200  rounded-full flex items-center justify-center">
                                        <span class="text-sm font-medium">{{ $enrollment->student->user->initials() }}</span>
                                    </div>
                                    <div>
                                        <flux:text class="font-medium">{{ $enrollment->student->user->name }}</flux:text>
                                        <flux:text size="sm" class="text-gray-600">{{ $enrollment->course->name }}</flux:text>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <flux:badge :class="$enrollment->academic_status->badgeClass()">
                                        {{ $enrollment->academic_status->label() }}
                                    </flux:badge>
                                    <flux:text size="sm" class="text-gray-600  block mt-1">
                                        {{ $enrollment->enrollment_date->format('M d, Y') }}
                                    </flux:text>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <flux:text class="text-gray-600">No enrollments yet.</flux:text>
                @endif
            </flux:card>

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
