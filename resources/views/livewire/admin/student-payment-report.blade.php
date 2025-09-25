<?php
use App\Models\Student;
use App\Models\Course;
use App\Models\Order;
use App\Models\Enrollment;
use Livewire\WithPagination;
use Livewire\Volt\Component;
use Carbon\Carbon;
use Illuminate\Support\Facades\Response;

new class extends Component {
    use WithPagination;

    public string $search = '';
    public string $courseFilter = '';
    public int $year = 0;
    public string $sortBy = 'name';
    public string $sortDirection = 'asc';

    public function mount()
    {
        // Ensure user is admin
        if (!auth()->user()->isAdmin()) {
            abort(403, 'Access denied');
        }

        // Default to current year
        $this->year = now()->year;
    }

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function updatedCourseFilter()
    {
        $this->resetPage();
    }

    public function updatedYear()
    {
        $this->resetPage();
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

        return [
            'students' => $students,
            'courses' => $courses,
            'selectedCourse' => $selectedCourse,
            'paymentData' => $paymentData,
            'periodColumns' => $this->getPeriodColumns($selectedCourse),
        ];
    }

    private function getStudents()
    {
        $query = Student::with(['user', 'enrollments.course'])
            ->whereHas('user', function($q) {
                if ($this->search) {
                    $q->where('name', 'like', '%' . $this->search . '%')
                      ->orWhere('email', 'like', '%' . $this->search . '%');
                }
            });

        // Filter by course if selected
        if ($this->courseFilter) {
            $query->whereHas('enrollments', function($q) {
                $q->where('course_id', $this->courseFilter)
                  ->whereIn('status', ['enrolled', 'active']);
            });
        }

        // Sort by user name or student ID
        if ($this->sortBy === 'name') {
            $query->whereHas('user')->with('user')
                  ->get()
                  ->sortBy(function($student) {
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
        if (!$selectedCourse || !$selectedCourse->feeSettings) {
            // Default to monthly
            return collect(range(1, 12))->map(function($month) {
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
                    ]
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
                return collect(range(1, 12))->map(function($month) {
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

        $enrollments = $enrollmentsQuery->get()->keyBy(function($enrollment) {
            return $enrollment->student_id . '_' . $enrollment->course_id;
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
                    $enrollment = $enrollments->get($student->id . '_' . $this->courseFilter);
                } else {
                    // For "All Courses" view, get the first active enrollment
                    $enrollment = $enrollments->filter(function($e) use ($student) {
                        return $e->student_id == $student->id;
                    })->first();
                }

                $periodOrders = $studentOrders->filter(function($order) use ($period) {
                    return $order->period_start >= $period['period_start'] &&
                           $order->period_end <= $period['period_end'];
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
        if (!$enrollment) {
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

        // Get course fee settings
        if ($selectedCourse && $selectedCourse->feeSettings) {
            return $selectedCourse->feeSettings->fee_amount;
        }

        // For "All Courses" view, get fee from enrollment's course
        if ($enrollment->course && $enrollment->course->feeSettings) {
            return $enrollment->course->feeSettings->fee_amount;
        }

        return 0;
    }

    private function determinePaymentStatus($enrollment, $period, $paidAmount, $expectedAmount)
    {
        if (!$enrollment) {
            return 'no_enrollment';
        }

        $enrollmentStart = $enrollment->start_date ?: $enrollment->enrollment_date;
        $periodStart = $period['period_start'];
        $periodEnd = $period['period_end'];

        // Check if enrollment started after this period
        if ($enrollmentStart && $enrollmentStart > $periodEnd) {
            return 'not_started';
        }

        // Check if subscription was cancelled during or before this period
        if ($enrollment->subscription_cancel_at) {
            if ($enrollment->subscription_cancel_at >= $periodStart && $enrollment->subscription_cancel_at <= $periodEnd) {
                return 'cancelled_this_period';
            } elseif ($enrollment->subscription_cancel_at < $periodStart) {
                return 'cancelled_before';
            }
        }

        // Check academic status
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

        // Check payment status
        if ($expectedAmount <= 0) {
            return 'no_payment_due';
        }

        if ($paidAmount >= $expectedAmount) {
            return 'paid';
        }

        if ($paidAmount > 0) {
            return 'partial_payment';
        }

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
            $headers[] = $period['label'] . ' - Status';
            $headers[] = $period['label'] . ' - Paid';
            $headers[] = $period['label'] . ' - Expected';
            $headers[] = $period['label'] . ' - Unpaid';
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
                $statusText = match($payment['status']) {
                    'paid' => 'Paid',
                    'unpaid' => 'Unpaid',
                    'partial_payment' => 'Partial',
                    'not_started' => 'Not Started',
                    'cancelled_this_period' => 'Cancelled',
                    'cancelled_before' => 'Previously Cancelled',
                    'withdrawn' => 'Withdrawn',
                    'suspended' => 'Suspended',
                    'completed' => 'Completed',
                    default => 'No Data',
                };

                $paidAmount = $payment['paid_amount'] ?? 0;
                $expectedAmount = $payment['expected_amount'] ?? 0;
                $unpaidAmount = $payment['unpaid_amount'] ?? 0;

                $row[] = $statusText;
                $row[] = 'RM ' . number_format($paidAmount, 2);
                $row[] = 'RM ' . number_format($expectedAmount, 2);
                $row[] = 'RM ' . number_format($unpaidAmount, 2);

                $totalPaid += $paidAmount;
                $totalExpected += $expectedAmount;
                $totalUnpaid += $unpaidAmount;
            }

            $row[] = 'RM ' . number_format($totalPaid, 2);
            $row[] = 'RM ' . number_format($totalExpected, 2);
            $row[] = 'RM ' . number_format($totalUnpaid, 2);
            $csvData[] = $row;
        }

        $courseLabel = $selectedCourse ? $selectedCourse->name : 'All_Courses';
        $filename = 'student_payment_report_' . $courseLabel . '_' . $this->year . '_' . now()->format('Y_m_d_His') . '.csv';

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
            ->whereHas('user', function($q) {
                if ($this->search) {
                    $q->where('name', 'like', '%' . $this->search . '%')
                      ->orWhere('email', 'like', '%' . $this->search . '%');
                }
            });

        if ($this->courseFilter) {
            $query->whereHas('enrollments', function($q) {
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
        <flux:button variant="outline" wire:click="exportReport">
            <div class="flex items-center justify-center">
                <flux:icon icon="arrow-down-tray" class="w-4 h-4 mr-1" />
                Export CSV
            </div>
        </flux:button>
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
                            <th class="text-left py-3 px-4 sticky left-0 bg-white z-10 min-w-[200px]">
                                <button wire:click="sortBy('name')" class="flex items-center space-x-1 hover:text-blue-600">
                                    <span>Student Name</span>
                                    @if($sortBy === 'name')
                                        <flux:icon icon="{{ $sortDirection === 'asc' ? 'chevron-up' : 'chevron-down' }}" class="w-4 h-4" />
                                    @endif
                                </button>
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
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($students as $student)
                            <tr class="border-b border-gray-100 hover:bg-gray-50">
                                <td class="py-3 px-4 sticky left-0 bg-white z-10 border-r border-gray-100">
                                    <div class="font-medium">{{ $student->user->name }}</div>
                                    <div class="text-xs text-gray-600">{{ $student->student_id }}</div>
                                    <div class="text-xs text-gray-500">{{ $student->user->email }}</div>
                                </td>
                                @php
                                    $totalPaidForStudent = 0;
                                    $totalExpectedForStudent = 0;
                                    $totalUnpaidForStudent = 0;
                                @endphp
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
                                                        Cancelled this month
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
                        <flux:text>Cancelled This Month</flux:text>
                    </div>
                    <div class="flex items-center space-x-2">
                        <div class="inline-flex items-center justify-center w-6 h-6 bg-gray-100 text-gray-500 rounded-full">
                            <flux:icon icon="x-circle" class="w-4 h-4" />
                        </div>
                        <flux:text>Previously Cancelled</flux:text>
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