<?php
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Order;
use App\Models\Student;
use App\Services\StripeService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Response;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    public string $search = '';

    public string $courseFilter = '';

    public int $year = 0;

    public string $sortBy = 'name';

    public string $sortDirection = 'asc';

    public array $visiblePeriods = [];

    public bool $showColumnManager = false;

    public function mount()
    {
        // Ensure user is admin
        if (! auth()->user()->isAdmin()) {
            abort(403, 'Access denied');
        }

        // Default to current year
        $this->year = now()->year;

        // Initialize all periods as visible
        $this->initializeVisiblePeriods();
    }

    public function initializeVisiblePeriods()
    {
        $selectedCourse = null;
        if ($this->courseFilter) {
            $selectedCourse = Course::with('feeSettings')->find($this->courseFilter);
        }

        $periodColumns = $this->getPeriodColumns($selectedCourse);
        $this->visiblePeriods = $periodColumns->pluck('label')->toArray();
    }

    public function togglePeriodVisibility($periodLabel)
    {
        if (in_array($periodLabel, $this->visiblePeriods)) {
            $this->visiblePeriods = array_values(array_diff($this->visiblePeriods, [$periodLabel]));
        } else {
            $this->visiblePeriods[] = $periodLabel;
        }
    }

    public function toggleAllPeriods()
    {
        $selectedCourse = null;
        if ($this->courseFilter) {
            $selectedCourse = Course::with('feeSettings')->find($this->courseFilter);
        }

        $periodColumns = $this->getPeriodColumns($selectedCourse);
        $allPeriods = $periodColumns->pluck('label')->toArray();

        if (count($this->visiblePeriods) === count($allPeriods)) {
            $this->visiblePeriods = [];
        } else {
            $this->visiblePeriods = $allPeriods;
        }
    }

    public function toggleColumnManager()
    {
        $this->showColumnManager = ! $this->showColumnManager;
    }

    public function startAutomaticSubscription($enrollmentId)
    {
        try {
            $enrollment = Enrollment::with(['student.user', 'course.feeSettings'])->findOrFail($enrollmentId);

            // Check if student has payment methods
            if (! $enrollment->studentHasPaymentMethods()) {
                $this->dispatch('notification',
                    type: 'error',
                    message: 'Student does not have any payment methods. Please add a payment method first.'
                );

                return;
            }

            // Get the default payment method
            $paymentMethod = $enrollment->student->user->paymentMethods()
                ->where('is_default', true)
                ->where('is_active', true)
                ->first();

            if (! $paymentMethod) {
                $this->dispatch('notification',
                    type: 'error',
                    message: 'Student does not have a default payment method.'
                );

                return;
            }

            // Create subscription using StripeService
            $stripeService = app(StripeService::class);
            $result = $stripeService->createSubscription($enrollment, $paymentMethod);

            // Update payment method type to automatic
            $enrollment->update(['payment_method_type' => 'automatic']);

            $this->dispatch('notification',
                type: 'success',
                message: 'Automatic subscription started successfully!'
            );

        } catch (\Exception $e) {
            $this->dispatch('notification',
                type: 'error',
                message: 'Failed to start subscription: '.$e->getMessage()
            );
        }
    }

    public function cancelSubscription($enrollmentId, $immediately = false)
    {
        try {
            $enrollment = Enrollment::findOrFail($enrollmentId);

            if (! $enrollment->stripe_subscription_id) {
                $this->dispatch('notification',
                    type: 'error',
                    message: 'No active subscription found for this enrollment.'
                );

                return;
            }

            // Cancel subscription using StripeService
            $stripeService = app(StripeService::class);
            $result = $stripeService->cancelSubscription($enrollment->stripe_subscription_id, $immediately);

            // Update enrollment status
            if ($result['immediately']) {
                $enrollment->update([
                    'subscription_status' => 'canceled',
                    'subscription_cancel_at' => now(),
                ]);
            } else {
                $enrollment->update([
                    'subscription_cancel_at' => Carbon::createFromTimestamp($result['cancel_at']),
                ]);
            }

            $this->dispatch('notification',
                type: 'success',
                message: $result['message']
            );

        } catch (\Exception $e) {
            $this->dispatch('notification',
                type: 'error',
                message: 'Failed to cancel subscription: '.$e->getMessage()
            );
        }
    }

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function updatedCourseFilter()
    {
        $this->resetPage();
        $this->initializeVisiblePeriods();
    }

    public function updatedYear()
    {
        $this->resetPage();
        $this->initializeVisiblePeriods();
    }

    public function sortBy($field)
    {
        if ($this->sortBy === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $field;
            $this->sortDirection = 'asc';
        }
        $this->resetPage();
    }

    public function with(): array
    {
        $selectedCourse = null;
        if ($this->courseFilter) {
            $selectedCourse = Course::with('feeSettings')->find($this->courseFilter);
        }

        $students = $this->getStudents();
        $courses = Course::orderBy('name')->get();
        $paymentData = $this->getPaymentData($students, $selectedCourse);
        $allPeriodColumns = $this->getPeriodColumns($selectedCourse);

        // Filter visible period columns
        $visiblePeriodColumns = $allPeriodColumns->filter(function ($period) {
            return in_array($period['label'], $this->visiblePeriods);
        });

        return [
            'students' => $students,
            'courses' => $courses,
            'selectedCourse' => $selectedCourse,
            'paymentData' => $paymentData,
            'periodColumns' => $visiblePeriodColumns,
            'allPeriodColumns' => $allPeriodColumns,
        ];
    }

    private function getStudents()
    {
        $query = Student::with(['user', 'enrollments.course', 'enrollments'])
            ->whereHas('user', function ($q) {
                if ($this->search) {
                    $q->where('name', 'like', '%'.$this->search.'%')
                        ->orWhere('email', 'like', '%'.$this->search.'%');
                }
            });

        // Filter by course if selected
        if ($this->courseFilter) {
            $query->whereHas('enrollments', function ($q) {
                $q->where('course_id', $this->courseFilter)
                    ->whereIn('status', ['enrolled', 'active']);
            });
        }

        // Sort by user name or student ID
        if ($this->sortBy === 'name') {
            $query->whereHas('user')->with('user')
                ->get()
                ->sortBy(function ($student) {
                    return $student->user->name;
                }, SORT_REGULAR, $this->sortDirection === 'desc');

            return $query->paginate(20);
        } else {
            $query->orderBy($this->sortBy, $this->sortDirection);
        }

        return $query->paginate(20);
    }

    private function getPeriodColumns($selectedCourse)
    {
        if (! $selectedCourse || ! $selectedCourse->feeSettings) {
            // Default to monthly
            return collect(range(1, 12))->map(function ($month) {
                return [
                    'label' => Carbon::create()->month($month)->format('M'),
                    'period_start' => Carbon::create($this->year, $month, 1)->startOfMonth(),
                    'period_end' => Carbon::create($this->year, $month, 1)->endOfMonth(),
                ];
            });
        }

        $billingCycle = $selectedCourse->feeSettings->billing_cycle;

        switch ($billingCycle) {
            case 'yearly':
                return collect([
                    [
                        'label' => $this->year,
                        'period_start' => Carbon::create($this->year, 1, 1)->startOfYear(),
                        'period_end' => Carbon::create($this->year, 12, 31)->endOfYear(),
                    ],
                ]);

            case 'quarterly':
                return collect([
                    [
                        'label' => 'Q1',
                        'period_start' => Carbon::create($this->year, 1, 1)->startOfQuarter(),
                        'period_end' => Carbon::create($this->year, 3, 31)->endOfQuarter(),
                    ],
                    [
                        'label' => 'Q2',
                        'period_start' => Carbon::create($this->year, 4, 1)->startOfQuarter(),
                        'period_end' => Carbon::create($this->year, 6, 30)->endOfQuarter(),
                    ],
                    [
                        'label' => 'Q3',
                        'period_start' => Carbon::create($this->year, 7, 1)->startOfQuarter(),
                        'period_end' => Carbon::create($this->year, 9, 30)->endOfQuarter(),
                    ],
                    [
                        'label' => 'Q4',
                        'period_start' => Carbon::create($this->year, 10, 1)->startOfQuarter(),
                        'period_end' => Carbon::create($this->year, 12, 31)->endOfQuarter(),
                    ],
                ]);

            default: // monthly
                return collect(range(1, 12))->map(function ($month) {
                    return [
                        'label' => Carbon::create()->month($month)->format('M'),
                        'period_start' => Carbon::create($this->year, $month, 1)->startOfMonth(),
                        'period_end' => Carbon::create($this->year, $month, 1)->endOfMonth(),
                    ];
                });
        }
    }

    private function getPaymentData($students, $selectedCourse)
    {
        $studentIds = $students->pluck('id');

        // Get all orders (paid and pending) for comprehensive analysis
        $ordersQuery = Order::with(['student', 'course'])
            ->whereIn('student_id', $studentIds)
            ->whereYear('period_start', $this->year);

        // Filter by course if selected
        if ($this->courseFilter) {
            $ordersQuery->where('course_id', $this->courseFilter);
        }

        $orders = $ordersQuery->get();

        // Get enrollments with subscription information
        $enrollmentsQuery = Enrollment::with(['student', 'course'])
            ->whereIn('student_id', $studentIds);

        if ($this->courseFilter) {
            $enrollmentsQuery->where('course_id', $this->courseFilter);
        }

        $enrollments = $enrollmentsQuery->get()->keyBy(function ($enrollment) {
            return $enrollment->student_id.'_'.$enrollment->course_id;
        });

        // Group orders by student and period with enhanced data
        $paymentData = [];

        foreach ($students as $student) {
            $studentOrders = $orders->where('student_id', $student->id);
            $paymentData[$student->id] = [];

            foreach ($this->getPeriodColumns($selectedCourse) as $period) {
                // Get enrollment for this student and course (if specific course selected)
                $enrollment = null;
                if ($this->courseFilter) {
                    $enrollment = $enrollments->get($student->id.'_'.$this->courseFilter);
                } else {
                    // For "All Courses" view, get the first active enrollment
                    $enrollment = $enrollments->filter(function ($e) use ($student) {
                        return $e->student_id == $student->id;
                    })->first();
                }

                $periodOrders = $studentOrders->filter(function ($order) use ($period) {
                    // Match orders where the billing period START date falls within the calendar month
                    // This ensures each monthly payment is counted only once in the correct month
                    return $order->period_start >= $period['period_start'] &&
                           $order->period_start <= $period['period_end'];
                });

                $paidOrders = $periodOrders->where('status', Order::STATUS_PAID);
                $pendingOrders = $periodOrders->where('status', Order::STATUS_PENDING);
                $failedOrders = $periodOrders->where('status', Order::STATUS_FAILED);

                // Calculate expected amount based on enrollment and course fees
                $expectedAmount = $this->calculateExpectedAmount($enrollment, $period, $selectedCourse);
                $paidAmount = $paidOrders->sum('amount');
                $pendingAmount = $pendingOrders->sum('amount');
                $unpaidAmount = max(0, $expectedAmount - $paidAmount);

                // Determine status based on enrollment and subscription
                $status = $this->determinePaymentStatus($enrollment, $period, $paidAmount, $expectedAmount);

                $paymentData[$student->id][$period['label']] = [
                    'orders' => $periodOrders,
                    'paid_orders' => $paidOrders,
                    'pending_orders' => $pendingOrders,
                    'failed_orders' => $failedOrders,
                    'total_amount' => $periodOrders->sum('amount'),
                    'paid_amount' => $paidAmount,
                    'pending_amount' => $pendingAmount,
                    'expected_amount' => $expectedAmount,
                    'unpaid_amount' => $unpaidAmount,
                    'count' => $periodOrders->count(),
                    'enrollment' => $enrollment,
                    'status' => $status,
                ];
            }
        }

        return $paymentData;
    }

    private function calculateExpectedAmount($enrollment, $period, $selectedCourse)
    {
        if (! $enrollment) {
            return 0;
        }

        // Check if student was enrolled during this period
        $enrollmentStart = $enrollment->start_date ?: $enrollment->enrollment_date;
        $periodStart = $period['period_start'];
        $periodEnd = $period['period_end'];

        // If enrollment started after this period, no payment expected
        if ($enrollmentStart && $enrollmentStart > $periodEnd) {
            return 0;
        }

        // If enrollment was cancelled before this period, no payment expected
        if ($enrollment->subscription_cancel_at && $enrollment->subscription_cancel_at <= $periodStart) {
            return 0;
        }

        // If enrollment is withdrawn/suspended before this period, no payment expected
        if (in_array($enrollment->academic_status?->value, ['withdrawn', 'suspended'])) {
            return 0;
        }

        // Prioritize course fee settings over enrollment fee
        // Only use enrollment fee if it's explicitly set and different from course fee (discounts/promotions)

        // First, try to get course fee from selected course
        if ($selectedCourse && $selectedCourse->feeSettings && $selectedCourse->feeSettings->fee_amount > 0) {
            // Check if enrollment has custom fee (discount/promotion)
            if ($enrollment->enrollment_fee > 0 && $enrollment->enrollment_fee != $selectedCourse->feeSettings->fee_amount) {
                return $enrollment->enrollment_fee; // Custom pricing
            }

            return $selectedCourse->feeSettings->fee_amount; // Standard course fee
        }

        // For "All Courses" view, get fee from enrollment's course
        if ($enrollment->course && $enrollment->course->feeSettings && $enrollment->course->feeSettings->fee_amount > 0) {
            // Check if enrollment has custom fee (discount/promotion)
            if ($enrollment->enrollment_fee > 0 && $enrollment->enrollment_fee != $enrollment->course->feeSettings->fee_amount) {
                return $enrollment->enrollment_fee; // Custom pricing
            }

            return $enrollment->course->feeSettings->fee_amount; // Standard course fee
        }

        // Last resort: use enrollment fee only if it's greater than 0
        if ($enrollment->enrollment_fee > 0) {
            return $enrollment->enrollment_fee;
        }

        return 0;
    }

    private function determinePaymentStatus($enrollment, $period, $paidAmount, $expectedAmount)
    {
        if (! $enrollment) {
            return 'no_enrollment';
        }

        $enrollmentStart = $enrollment->start_date ?: $enrollment->enrollment_date;
        $periodStart = $period['period_start'];
        $periodEnd = $period['period_end'];

        // Check if enrollment started after this period
        if ($enrollmentStart && $enrollmentStart > $periodEnd) {
            return 'not_started';
        }

        // PRIORITY 1: Check actual payment status FIRST (actual money received takes priority)
        if ($expectedAmount > 0) {
            if ($paidAmount >= $expectedAmount) {
                return 'paid';
            }

            if ($paidAmount > 0) {
                return 'partial_payment';
            }
        }

        // PRIORITY 2: Check if subscription was cancelled (only if no payment made)
        if ($enrollment->subscription_cancel_at) {
            if ($enrollment->subscription_cancel_at >= $periodStart && $enrollment->subscription_cancel_at <= $periodEnd) {
                return 'cancelled_this_period';
            } elseif ($enrollment->subscription_cancel_at < $periodStart) {
                return 'cancelled_before';
            }
        }

        // PRIORITY 3: Check academic status
        if ($enrollment->academic_status) {
            switch ($enrollment->academic_status->value) {
                case 'withdrawn':
                    return 'withdrawn';
                case 'suspended':
                    return 'suspended';
                case 'completed':
                    return 'completed';
            }
        }

        // PRIORITY 4: Check if payment is expected
        if ($expectedAmount <= 0) {
            return 'no_payment_due';
        }

        // PRIORITY 5: Default to unpaid if period has started
        return 'unpaid';
    }

    public function exportReport()
    {
        $selectedCourse = null;
        if ($this->courseFilter) {
            $selectedCourse = Course::with('feeSettings')->find($this->courseFilter);
        }

        $students = $this->getAllStudentsForExport();
        $paymentData = $this->getPaymentData($students, $selectedCourse);
        $periodColumns = $this->getPeriodColumns($selectedCourse);

        $csvData = [];

        // Header row
        $headers = ['Student Name', 'Student Email', 'Student ID'];
        foreach ($periodColumns as $period) {
            $headers[] = $period['label'].' - Status';
            $headers[] = $period['label'].' - Paid';
            $headers[] = $period['label'].' - Expected';
            $headers[] = $period['label'].' - Unpaid';
        }
        $headers[] = 'Total Paid';
        $headers[] = 'Total Expected';
        $headers[] = 'Total Unpaid';
        $csvData[] = $headers;

        // Data rows
        foreach ($students as $student) {
            $row = [
                $student->user->name,
                $student->user->email,
                $student->student_id,
            ];

            $totalPaid = 0;
            $totalExpected = 0;
            $totalUnpaid = 0;

            foreach ($periodColumns as $period) {
                $payment = $paymentData[$student->id][$period['label']] ?? ['status' => 'no_data', 'paid_amount' => 0, 'expected_amount' => 0, 'unpaid_amount' => 0];

                // Status
                $statusText = match ($payment['status']) {
                    'paid' => 'Paid',
                    'unpaid' => 'Unpaid',
                    'partial_payment' => 'Partial',
                    'not_started' => 'Not Started',
                    'cancelled_this_period' => 'Canceled',
                    'cancelled_before' => 'Previously Canceled',
                    'withdrawn' => 'Withdrawn',
                    'suspended' => 'Suspended',
                    'completed' => 'Completed',
                    default => 'No Data',
                };

                $paidAmount = $payment['paid_amount'] ?? 0;
                $expectedAmount = $payment['expected_amount'] ?? 0;
                $unpaidAmount = $payment['unpaid_amount'] ?? 0;

                $row[] = $statusText;
                $row[] = 'RM '.number_format($paidAmount, 2);
                $row[] = 'RM '.number_format($expectedAmount, 2);
                $row[] = 'RM '.number_format($unpaidAmount, 2);

                $totalPaid += $paidAmount;
                $totalExpected += $expectedAmount;
                $totalUnpaid += $unpaidAmount;
            }

            $row[] = 'RM '.number_format($totalPaid, 2);
            $row[] = 'RM '.number_format($totalExpected, 2);
            $row[] = 'RM '.number_format($totalUnpaid, 2);
            $csvData[] = $row;
        }

        $courseLabel = $selectedCourse ? $selectedCourse->name : 'All_Courses';
        $filename = 'student_payment_report_'.$courseLabel.'_'.$this->year.'_'.now()->format('Y_m_d_His').'.csv';

        $handle = fopen('php://memory', 'r+');
        foreach ($csvData as $row) {
            fputcsv($handle, $row);
        }
        rewind($handle);
        $csvContent = stream_get_contents($handle);
        fclose($handle);

        return Response::streamDownload(function () use ($csvContent) {
            echo $csvContent;
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    private function getAllStudentsForExport()
    {
        $query = Student::with(['user', 'enrollments.course'])
            ->whereHas('user', function ($q) {
                if ($this->search) {
                    $q->where('name', 'like', '%'.$this->search.'%')
                        ->orWhere('email', 'like', '%'.$this->search.'%');
                }
            });

        if ($this->courseFilter) {
            $query->whereHas('enrollments', function ($q) {
                $q->where('course_id', $this->courseFilter)
                    ->whereIn('status', ['enrolled', 'active']);
            });
        }

        return $query->get();
    }
}; ?>

<div>
    <div class="mb-6 flex items-center justify-between">
        <div class="flex items-center space-x-4">
            <flux:icon icon="document-chart-bar" class="w-8 h-8 text-blue-600" />
            <div>
                <flux:heading size="xl">Student Payment Report</flux:heading>
                <flux:text class="mt-2">Track student payment history across different billing periods</flux:text>
            </div>
        </div>
        <div class="flex items-center gap-3">
            <!-- Column Visibility Manager -->
            <div class="relative" x-data="{ open: @entangle('showColumnManager') }">
                <flux:button variant="outline" @click="open = !open">
                    <div class="flex items-center justify-center">
                        <flux:icon icon="view-columns" class="w-4 h-4 mr-1" />
                        Columns
                        <span class="ml-1 text-xs text-gray-500">({{ count($visiblePeriods) }}/{{ $allPeriodColumns->count() }})</span>
                    </div>
                </flux:button>

                <div x-show="open" @click.away="open = false" x-cloak
                     class="absolute right-0 mt-2 w-72 bg-white dark:bg-zinc-800 rounded-lg shadow-lg border border-gray-200 dark:border-zinc-700 z-50"
                     x-transition:enter="transition ease-out duration-100"
                     x-transition:enter-start="transform opacity-0 scale-95"
                     x-transition:enter-end="transform opacity-100 scale-100"
                     x-transition:leave="transition ease-in duration-75"
                     x-transition:leave-start="transform opacity-100 scale-100"
                     x-transition:leave-end="transform opacity-0 scale-95">

                    <div class="p-4">
                        <div class="flex items-center justify-between mb-3">
                            <flux:heading size="sm">Column Visibility</flux:heading>
                            <flux:button variant="ghost" size="sm" wire:click="toggleAllPeriods">
                                <div class="text-xs">
                                    {{ count($visiblePeriods) === $allPeriodColumns->count() ? 'Hide All' : 'Show All' }}
                                </div>
                            </flux:button>
                        </div>

                        <div class="space-y-2 max-h-96 overflow-y-auto">
                            @foreach($allPeriodColumns as $period)
                                <label class="flex items-center space-x-3 p-2 hover:bg-gray-50 rounded cursor-pointer">
                                    <flux:checkbox
                                        wire:model.live="visiblePeriods"
                                        value="{{ $period['label'] }}"
                                    />
                                    <div class="flex-1">
                                        <div class="text-sm font-medium text-gray-700">{{ $period['label'] }}</div>
                                        @if($selectedCourse && $selectedCourse->feeSettings && $selectedCourse->feeSettings->billing_cycle !== 'yearly')
                                            <div class="text-xs text-gray-500">
                                                {{ $period['period_start']->format('M j') }} - {{ $period['period_end']->format('M j') }}
                                            </div>
                                        @endif
                                    </div>
                                </label>
                            @endforeach
                        </div>

                        @if(count($visiblePeriods) === 0)
                            <div class="mt-3 p-2 bg-yellow-50 border border-yellow-200 rounded text-xs text-yellow-700">
                                ⚠️ At least one column must be visible
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Export CSV Button -->
            <flux:button variant="outline" wire:click="exportReport">
                <div class="flex items-center justify-center">
                    <flux:icon icon="arrow-down-tray" class="w-4 h-4 mr-1" />
                    Export CSV
                </div>
            </flux:button>
        </div>
    </div>

    <!-- Filters -->
    <flux:card class="mb-6">
        <flux:header>
            <flux:heading size="lg">Filters</flux:heading>
            <flux:text>Refine the payment report data</flux:text>
        </flux:header>

        <div class="space-y-4">
            <!-- First Row: Student Search (Full Width) -->
            <div>
                <flux:label class="block text-sm font-medium text-gray-700 mb-2">
                    Student Search
                </flux:label>
                <flux:input
                    wire:model.live.debounce.300ms="search"
                    placeholder="Type student name or email to search..."
                    icon="magnifying-glass"
                    class="w-full"
                />
                @if($search)
                    <div class="mt-1 flex items-center justify-between">
                        <flux:text class="text-sm text-gray-600">
                            Filtering by: "{{ $search }}"
                        </flux:text>
                        <flux:button
                            variant="ghost"
                            size="sm"
                            wire:click="$set('search', '')"
                            class="text-gray-500 hover:text-gray-700"
                        >
                            <flux:icon icon="x-mark" class="w-4 h-4" />
                            Clear
                        </flux:button>
                    </div>
                @endif
            </div>

            <!-- Second Row: Course Filter and Report Year -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                <!-- Course Filter (2/3 width) -->
                <div class="lg:col-span-2">
                    <flux:label class="block text-sm font-medium text-gray-700 mb-2">
                        Course Filter
                    </flux:label>
                    <flux:select wire:model.live="courseFilter" placeholder="Select a course..." class="w-full">
                        <flux:select.option value="">All Courses</flux:select.option>
                        @foreach($courses as $course)
                            <flux:select.option value="{{ $course->id }}">
                                {{ $course->name }}
                                @if($course->feeSettings)
                                    <span class="text-gray-500">({{ $course->feeSettings->billing_cycle_label }})</span>
                                @endif
                            </flux:select.option>
                        @endforeach
                    </flux:select>
                    @if($courseFilter)
                        <div class="mt-1 flex items-center justify-between">
                            <flux:text class="text-sm text-gray-600">
                                Showing: {{ $selectedCourse->name ?? 'Selected Course' }}
                            </flux:text>
                            <flux:button
                                variant="ghost"
                                size="sm"
                                wire:click="$set('courseFilter', '')"
                                class="text-gray-500 hover:text-gray-700"
                            >
                                <flux:icon icon="x-mark" class="w-4 h-4" />
                                Clear
                            </flux:button>
                        </div>
                    @endif
                </div>

                <!-- Report Year (1/3 width - compact) -->
                <div>
                    <flux:label class="block text-sm font-medium text-gray-700 mb-2">
                        Year
                    </flux:label>
                    <flux:select wire:model.live="year" class="w-full">
                        @for($y = now()->year; $y >= now()->year - 5; $y--)
                            <flux:select.option value="{{ $y }}">{{ $y }}</flux:select.option>
                        @endfor
                    </flux:select>
                </div>
            </div>

            <!-- Third Row: Quick Clear Actions -->
            @if($search || $courseFilter)
                <div class="flex justify-end">
                    <flux:button
                        variant="outline"
                        size="sm"
                        wire:click="$set('search', ''); $set('courseFilter', '')"
                    >
                        <flux:icon icon="x-mark" class="w-4 h-4 mr-1" />
                        Clear All Filters
                    </flux:button>
                </div>
            @endif
        </div>

        <!-- Filter Status/Information -->
        @if($selectedCourse && $selectedCourse->feeSettings)
            <div class="mt-6 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                <div class="flex items-start space-x-3">
                    <flux:icon icon="information-circle" class="w-5 h-5 text-blue-600 mt-0.5" />
                    <div>
                        <flux:text class="text-blue-800 font-medium">
                            {{ $selectedCourse->feeSettings->billing_cycle_label }} Billing Period
                        </flux:text>
                        <flux:text class="text-blue-700 text-sm mt-1">
                            Showing {{ strtolower($selectedCourse->feeSettings->billing_cycle_label) }} payment periods for
                            <strong>{{ $selectedCourse->name }}</strong>
                            ({{ $selectedCourse->feeSettings->formatted_fee }} per {{ strtolower($selectedCourse->feeSettings->billing_cycle_label) }})
                        </flux:text>
                    </div>
                </div>
            </div>
        @elseif(!$courseFilter)
            <div class="mt-6 p-4 bg-gray-50 border border-gray-200 rounded-lg">
                <div class="flex items-start space-x-3">
                    <flux:icon icon="calendar-days" class="w-5 h-5 text-gray-600 mt-0.5" />
                    <div>
                        <flux:text class="text-gray-800 font-medium">
                            All Courses View
                        </flux:text>
                        <flux:text class="text-gray-600 text-sm mt-1">
                            Displaying monthly payment periods for {{ $year }}. Select a specific course to optimize the view for its billing cycle.
                        </flux:text>
                    </div>
                </div>
            </div>
        @endif

        <!-- Results Summary -->
        <div class="mt-4 pt-4 border-t border-gray-200">
            <div class="flex items-center justify-between text-sm text-gray-600">
                <div>
                    Showing <strong>{{ $students->count() }}</strong> of <strong>{{ $students->total() }}</strong> students
                </div>
                <div class="flex items-center space-x-4">
                    @if($search)
                        <span class="inline-flex items-center px-2 py-1 bg-blue-100 text-blue-800 rounded-md text-xs">
                            <flux:icon icon="magnifying-glass" class="w-3 h-3 mr-1" />
                            Search: {{ $search }}
                        </span>
                    @endif
                    @if($courseFilter && $selectedCourse)
                        <span class="inline-flex items-center px-2 py-1 bg-green-100 text-green-800 rounded-md text-xs">
                            <flux:icon icon="academic-cap" class="w-3 h-3 mr-1" />
                            Course: {{ $selectedCourse->name }}
                        </span>
                    @endif
                    <span class="inline-flex items-center px-2 py-1 bg-gray-100 text-gray-800 rounded-md text-xs">
                        <flux:icon icon="calendar" class="w-3 h-3 mr-1" />
                        Year: {{ $year }}
                    </span>
                </div>
            </div>
        </div>
    </flux:card>

    <!-- Payment Report Table -->
    <flux:card>
        @if($students->count() > 0)
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200">
                            <th class="text-left py-3 px-4 sticky left-0 bg-white dark:bg-zinc-800 z-10 min-w-[200px]">
                                <button wire:click="sortBy('name')" class="flex items-center space-x-1 hover:text-blue-600">
                                    <span>Student Name</span>
                                    @if($sortBy === 'name')
                                        <flux:icon icon="{{ $sortDirection === 'asc' ? 'chevron-up' : 'chevron-down' }}" class="w-4 h-4" />
                                    @endif
                                </button>
                            </th>
                            <th class="text-center py-3 px-3 min-w-[120px] border-l border-gray-100">
                                <div class="font-medium">Payment Type</div>
                                <div class="text-xs text-gray-500 mt-1">Auto / Manual</div>
                            </th>
                            @foreach($periodColumns as $period)
                                <th class="text-center py-3 px-3 min-w-[100px] border-l border-gray-100">
                                    <div class="font-medium">{{ $period['label'] }}</div>
                                    @if($selectedCourse && $selectedCourse->feeSettings && $selectedCourse->feeSettings->billing_cycle !== 'yearly')
                                        <div class="text-xs text-gray-500 mt-1">
                                            {{ $period['period_start']->format('M j') }} - {{ $period['period_end']->format('M j') }}
                                        </div>
                                    @endif
                                </th>
                            @endforeach
                            <th class="text-center py-3 px-4 min-w-[150px] border-l-2 border-gray-300">
                                <div class="font-medium">Payment Summary</div>
                                <div class="text-xs text-gray-500 mt-1">Paid / Expected / Unpaid</div>
                            </th>
                            <th class="text-center py-3 px-4 min-w-[180px] border-l border-gray-200">
                                <div class="font-medium">Actions</div>
                                <div class="text-xs text-gray-500 mt-1">Subscription Management</div>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($students as $student)
                            <tr class="border-b border-gray-100 hover:bg-gray-50">
                                <td class="py-3 px-4 sticky left-0 bg-white dark:bg-zinc-800 z-10 border-r border-gray-100">
                                    <div class="font-medium">{{ $student->user->name }}</div>
                                    <div class="text-xs text-gray-600">{{ $student->student_id }}</div>
                                    <div class="text-xs text-gray-500">{{ $student->user->email }}</div>
                                </td>
                                @php
                                    // Get enrollment for payment type indicator
                                    $studentEnrollment = null;
                                    if ($courseFilter) {
                                        $studentEnrollment = $student->enrollments->where('course_id', $courseFilter)->first();
                                    } else {
                                        $studentEnrollment = $student->enrollments->first();
                                    }
                                    $totalPaidForStudent = 0;
                                    $totalExpectedForStudent = 0;
                                    $totalUnpaidForStudent = 0;
                                @endphp
                                <td class="py-3 px-3 text-center border-l border-gray-100">
                                    @if($studentEnrollment)
                                        @if($studentEnrollment->payment_method_type === 'automatic' || $studentEnrollment->stripe_subscription_id)
                                            <div class="inline-flex items-center space-x-1 px-2 py-1 bg-green-100 text-green-700 rounded-md text-xs font-medium">
                                                <flux:icon name="bolt" class="w-3 h-3" />
                                                <span>Automatic</span>
                                            </div>
                                        @else
                                            <div class="inline-flex items-center space-x-1 px-2 py-1 bg-gray-100 text-gray-700 rounded-md text-xs font-medium">
                                                <flux:icon name="hand-raised" class="w-3 h-3" />
                                                <span>Manual</span>
                                            </div>
                                        @endif
                                    @else
                                        <div class="text-xs text-gray-400">N/A</div>
                                    @endif
                                </td>
                                @foreach($periodColumns as $period)
                                    @php
                                        $payment = $paymentData[$student->id][$period['label']] ?? ['orders' => collect(), 'total_amount' => 0, 'count' => 0, 'status' => 'no_data'];
                                        $totalPaidForStudent += $payment['paid_amount'] ?? 0;
                                        $totalExpectedForStudent += $payment['expected_amount'] ?? 0;
                                        $totalUnpaidForStudent += $payment['unpaid_amount'] ?? 0;
                                    @endphp
                                    <td class="py-3 px-3 text-center border-l border-gray-100">
                                        @switch($payment['status'])
                                            @case('paid')
                                                <div class="space-y-1">
                                                    @if(isset($payment['paid_orders']) && $payment['paid_orders']->count() > 0)
                                                        <flux:link
                                                            :href="route('orders.show', $payment['paid_orders']->first())"
                                                            class="block hover:opacity-80 cursor-pointer"
                                                            wire:navigate
                                                        >
                                                            <div class="inline-flex items-center justify-center w-6 h-6 bg-emerald-100 text-emerald-600 rounded-full mb-1">
                                                                <flux:icon icon="check" class="w-4 h-4" />
                                                            </div>
                                                        </flux:link>
                                                    @else
                                                        <div class="inline-flex items-center justify-center w-6 h-6 bg-emerald-100 text-emerald-600 rounded-full mb-1">
                                                            <flux:icon icon="check" class="w-4 h-4" />
                                                        </div>
                                                    @endif
                                                    <div class="text-xs font-medium text-emerald-600">
                                                        RM {{ number_format($payment['paid_amount'] ?? 0, 2) }}
                                                    </div>
                                                </div>
                                                @break

                                            @case('unpaid')
                                                <div class="space-y-1">
                                                    <div class="inline-flex items-center justify-center w-6 h-6 bg-red-100 text-red-600 rounded-full mb-1">
                                                        <flux:icon icon="exclamation-triangle" class="w-4 h-4" />
                                                    </div>
                                                    <div class="text-xs font-medium text-red-600">
                                                        RM {{ number_format($payment['unpaid_amount'] ?? 0, 2) }} unpaid
                                                    </div>
                                                </div>
                                                @break

                                            @case('partial_payment')
                                                <div class="space-y-1">
                                                    <div class="inline-flex items-center justify-center w-6 h-6 bg-yellow-100 text-yellow-600 rounded-full mb-1">
                                                        <flux:icon icon="minus" class="w-4 h-4" />
                                                    </div>
                                                    <div class="text-xs font-medium text-yellow-600">
                                                        RM {{ number_format($payment['paid_amount'] ?? 0, 2) }} paid
                                                    </div>
                                                    <div class="text-xs text-red-500">
                                                        RM {{ number_format($payment['unpaid_amount'] ?? 0, 2) }} unpaid
                                                    </div>
                                                </div>
                                                @break

                                            @case('not_started')
                                                <div class="space-y-1">
                                                    <div class="inline-flex items-center justify-center w-6 h-6 bg-blue-100 text-blue-600 rounded-full mb-1">
                                                        <flux:icon icon="clock" class="w-4 h-4" />
                                                    </div>
                                                    <div class="text-xs text-blue-600">
                                                        Not started yet
                                                    </div>
                                                    @if(isset($payment['enrollment']) && $payment['enrollment'])
                                                        <div class="text-xs text-gray-500">
                                                            Started: {{ $payment['enrollment']->start_date ? $payment['enrollment']->start_date->format('M Y') : ($payment['enrollment']->enrollment_date ? $payment['enrollment']->enrollment_date->format('M Y') : 'N/A') }}
                                                        </div>
                                                    @endif
                                                </div>
                                                @break

                                            @case('cancelled_this_period')
                                                <div class="space-y-1">
                                                    <div class="inline-flex items-center justify-center w-6 h-6 bg-orange-100 text-orange-600 rounded-full mb-1">
                                                        <flux:icon icon="x-circle" class="w-4 h-4" />
                                                    </div>
                                                    <div class="text-xs text-orange-600">
                                                        Canceled this month
                                                    </div>
                                                    @if(isset($payment['enrollment']) && $payment['enrollment'] && $payment['enrollment']->subscription_cancel_at)
                                                        <div class="text-xs text-gray-500">
                                                            {{ $payment['enrollment']->subscription_cancel_at->format('M j, Y') }}
                                                        </div>
                                                    @endif
                                                </div>
                                                @break

                                            @case('cancelled_before')
                                                <div class="space-y-1">
                                                    <div class="inline-flex items-center justify-center w-6 h-6 bg-gray-100 text-gray-500 rounded-full mb-1">
                                                        <flux:icon icon="x-circle" class="w-4 h-4" />
                                                    </div>
                                                    <div class="text-xs text-gray-500">
                                                        Already cancelled
                                                    </div>
                                                </div>
                                                @break

                                            @case('withdrawn')
                                                <div class="space-y-1">
                                                    <div class="inline-flex items-center justify-center w-6 h-6 bg-red-100 text-red-500 rounded-full mb-1">
                                                        <flux:icon icon="user-minus" class="w-4 h-4" />
                                                    </div>
                                                    <div class="text-xs text-red-500">
                                                        Withdrawn
                                                    </div>
                                                </div>
                                                @break

                                            @case('suspended')
                                                <div class="space-y-1">
                                                    <div class="inline-flex items-center justify-center w-6 h-6 bg-yellow-100 text-yellow-500 rounded-full mb-1">
                                                        <flux:icon icon="pause" class="w-4 h-4" />
                                                    </div>
                                                    <div class="text-xs text-yellow-500">
                                                        Suspended
                                                    </div>
                                                </div>
                                                @break

                                            @case('completed')
                                                <div class="space-y-1">
                                                    <div class="inline-flex items-center justify-center w-6 h-6 bg-green-100 text-green-500 rounded-full mb-1">
                                                        <flux:icon icon="academic-cap" class="w-4 h-4" />
                                                    </div>
                                                    <div class="text-xs text-green-500">
                                                        Completed
                                                    </div>
                                                </div>
                                                @break

                                            @default
                                                <div class="inline-flex items-center justify-center w-6 h-6 bg-gray-100 text-gray-400 rounded-full">
                                                    <flux:icon icon="x-mark" class="w-4 h-4" />
                                                </div>
                                        @endswitch
                                    </td>
                                @endforeach
                                <td class="py-3 px-4 text-center font-medium border-l-2 border-gray-300">
                                    <div class="space-y-1">
                                        <div class="text-emerald-600 font-medium">
                                            RM {{ number_format($totalPaidForStudent, 2) }}
                                        </div>
                                        <div class="text-xs text-gray-500">
                                            Expected: RM {{ number_format($totalExpectedForStudent, 2) }}
                                        </div>
                                        @if($totalUnpaidForStudent > 0)
                                            <div class="text-xs text-red-500 font-medium">
                                                Unpaid: RM {{ number_format($totalUnpaidForStudent, 2) }}
                                            </div>
                                        @endif
                                    </div>
                                </td>
                                <td class="py-3 px-4 text-center border-l border-gray-200">
                                    @if($studentEnrollment)
                                        <div class="flex flex-col gap-2">
                                            @if($studentEnrollment->payment_method_type === 'manual' && !$studentEnrollment->stripe_subscription_id)
                                                <flux:button
                                                    wire:click="startAutomaticSubscription({{ $studentEnrollment->id }})"
                                                    variant="primary"
                                                    size="sm"
                                                    class="w-full"
                                                >
                                                    <div class="flex items-center justify-center">
                                                        <flux:icon name="bolt" class="w-3 h-3 mr-1" />
                                                        Start Auto
                                                    </div>
                                                </flux:button>
                                            @endif

                                            @if($studentEnrollment->stripe_subscription_id && in_array($studentEnrollment->subscription_status, ['active', 'trialing']))
                                                <flux:button
                                                    wire:click="cancelSubscription({{ $studentEnrollment->id }}, false)"
                                                    variant="danger"
                                                    size="sm"
                                                    class="w-full"
                                                    wire:confirm="Are you sure you want to cancel this subscription? It will remain active until the end of the billing period."
                                                >
                                                    <div class="flex items-center justify-center">
                                                        <flux:icon name="x-circle" class="w-3 h-3 mr-1" />
                                                        Cancel
                                                    </div>
                                                </flux:button>
                                            @endif

                                            @if(!$studentEnrollment->stripe_subscription_id && $studentEnrollment->payment_method_type !== 'manual')
                                                <div class="text-xs text-gray-500">No actions</div>
                                            @endif
                                        </div>
                                    @else
                                        <div class="text-xs text-gray-400">N/A</div>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="mt-6">
                {{ $students->links() }}
            </div>
        @else
            <div class="text-center py-12">
                <flux:icon icon="users" class="w-12 h-12 text-gray-400 mx-auto mb-4" />
                <flux:heading size="md" class="text-gray-600 mb-2">No students found</flux:heading>
                <flux:text class="text-gray-600">
                    @if($search || $courseFilter)
                        No students match your current filters.
                        <button wire:click="$set('search', '')" wire:click="$set('courseFilter', '')" class="text-blue-600 hover:underline ml-1">Clear filters</button>
                    @else
                        No students have been enrolled yet.
                    @endif
                </flux:text>
            </div>
        @endif
    </flux:card>

    <!-- Enhanced Legend -->
    <flux:card class="mt-6">
        <flux:header>
            <flux:heading size="lg">Payment Status Legend</flux:heading>
            <flux:text>Understanding the payment indicators and status codes</flux:text>
        </flux:header>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <!-- Payment Status -->
            <div class="space-y-3">
                <flux:heading size="sm" class="text-gray-800">Payment Status</flux:heading>
                <div class="space-y-2">
                    <div class="flex items-center space-x-2">
                        <div class="inline-flex items-center justify-center w-6 h-6 bg-emerald-100 text-emerald-600 rounded-full">
                            <flux:icon icon="check" class="w-4 h-4" />
                        </div>
                        <flux:text>Payment Received (Full)</flux:text>
                    </div>
                    <div class="flex items-center space-x-2">
                        <div class="inline-flex items-center justify-center w-6 h-6 bg-yellow-100 text-yellow-600 rounded-full">
                            <flux:icon icon="minus" class="w-4 h-4" />
                        </div>
                        <flux:text>Partial Payment</flux:text>
                    </div>
                    <div class="flex items-center space-x-2">
                        <div class="inline-flex items-center justify-center w-6 h-6 bg-red-100 text-red-600 rounded-full">
                            <flux:icon icon="exclamation-triangle" class="w-4 h-4" />
                        </div>
                        <flux:text>Unpaid Amount Due</flux:text>
                    </div>
                </div>
            </div>

            <!-- Enrollment Status -->
            <div class="space-y-3">
                <flux:heading size="sm" class="text-gray-800">Enrollment Status</flux:heading>
                <div class="space-y-2">
                    <div class="flex items-center space-x-2">
                        <div class="inline-flex items-center justify-center w-6 h-6 bg-blue-100 text-blue-600 rounded-full">
                            <flux:icon icon="clock" class="w-4 h-4" />
                        </div>
                        <flux:text>Not Started Yet</flux:text>
                    </div>
                    <div class="flex items-center space-x-2">
                        <div class="inline-flex items-center justify-center w-6 h-6 bg-green-100 text-green-500 rounded-full">
                            <flux:icon icon="academic-cap" class="w-4 h-4" />
                        </div>
                        <flux:text>Course Completed</flux:text>
                    </div>
                    <div class="flex items-center space-x-2">
                        <div class="inline-flex items-center justify-center w-6 h-6 bg-red-100 text-red-500 rounded-full">
                            <flux:icon icon="user-minus" class="w-4 h-4" />
                        </div>
                        <flux:text>Student Withdrawn</flux:text>
                    </div>
                    <div class="flex items-center space-x-2">
                        <div class="inline-flex items-center justify-center w-6 h-6 bg-yellow-100 text-yellow-500 rounded-full">
                            <flux:icon icon="pause" class="w-4 h-4" />
                        </div>
                        <flux:text>Enrollment Suspended</flux:text>
                    </div>
                </div>
            </div>

            <!-- Subscription Status -->
            <div class="space-y-3">
                <flux:heading size="sm" class="text-gray-800">Subscription Status</flux:heading>
                <div class="space-y-2">
                    <div class="flex items-center space-x-2">
                        <div class="inline-flex items-center justify-center w-6 h-6 bg-orange-100 text-orange-600 rounded-full">
                            <flux:icon icon="x-circle" class="w-4 h-4" />
                        </div>
                        <flux:text>Canceled This Month</flux:text>
                    </div>
                    <div class="flex items-center space-x-2">
                        <div class="inline-flex items-center justify-center w-6 h-6 bg-gray-100 text-gray-500 rounded-full">
                            <flux:icon icon="x-circle" class="w-4 h-4" />
                        </div>
                        <flux:text>Previously Canceled</flux:text>
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-6 pt-4 border-t border-gray-200">
            <div class="flex items-center justify-between text-sm">
                <div class="text-gray-600">
                    💡 Click on paid amounts to view order details
                </div>
                <div class="text-gray-600">
                    📊 Expected amounts are based on course fees and enrollment periods
                </div>
            </div>
        </div>
    </flux:card>
</div>