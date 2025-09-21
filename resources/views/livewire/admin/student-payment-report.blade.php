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

        $ordersQuery = Order::with(['student', 'course'])
            ->whereIn('student_id', $studentIds)
            ->whereYear('period_start', $this->year)
            ->where('status', Order::STATUS_PAID);

        // Filter by course if selected
        if ($this->courseFilter) {
            $ordersQuery->where('course_id', $this->courseFilter);
        }

        $orders = $ordersQuery->get();

        // Group orders by student and period
        $paymentData = [];

        foreach ($students as $student) {
            $studentOrders = $orders->where('student_id', $student->id);
            $paymentData[$student->id] = [];

            foreach ($this->getPeriodColumns($selectedCourse) as $period) {
                $periodOrders = $studentOrders->filter(function($order) use ($period) {
                    return $order->period_start >= $period['period_start'] &&
                           $order->period_end <= $period['period_end'];
                });

                $paymentData[$student->id][$period['label']] = [
                    'orders' => $periodOrders,
                    'total_amount' => $periodOrders->sum('amount'),
                    'count' => $periodOrders->count(),
                ];
            }
        }

        return $paymentData;
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
            $headers[] = $period['label'] . ' - Paid';
            $headers[] = $period['label'] . ' - Amount';
        }
        $headers[] = 'Total Paid';
        $csvData[] = $headers;

        // Data rows
        foreach ($students as $student) {
            $row = [
                $student->user->name,
                $student->user->email,
                $student->student_id,
            ];

            $totalPaid = 0;
            foreach ($periodColumns as $period) {
                $payment = $paymentData[$student->id][$period['label']] ?? ['orders' => collect(), 'total_amount' => 0, 'count' => 0];
                $row[] = $payment['count'] > 0 ? 'Yes' : 'No';
                $row[] = 'RM ' . number_format($payment['total_amount'], 2);
                $totalPaid += $payment['total_amount'];
            }

            $row[] = 'RM ' . number_format($totalPaid, 2);
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
                            <th class="text-center py-3 px-4 min-w-[120px] border-l-2 border-gray-300">
                                <div class="font-medium">Total Paid</div>
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
                                @endphp
                                @foreach($periodColumns as $period)
                                    @php
                                        $payment = $paymentData[$student->id][$period['label']] ?? ['orders' => collect(), 'total_amount' => 0, 'count' => 0];
                                        $totalPaidForStudent += $payment['total_amount'];
                                    @endphp
                                    <td class="py-3 px-3 text-center border-l border-gray-100">
                                        @if($payment['count'] > 0)
                                            <div class="space-y-1">
                                                <div class="inline-flex items-center justify-center w-6 h-6 bg-emerald-100 text-emerald-600 rounded-full">
                                                    <flux:icon icon="check" class="w-4 h-4" />
                                                </div>
                                                <div class="text-xs font-medium text-emerald-600">
                                                    RM {{ number_format($payment['total_amount'], 2) }}
                                                </div>
                                                @if($payment['orders']->count() > 0)
                                                    <div class="space-y-1">
                                                        @foreach($payment['orders'] as $order)
                                                            <flux:link
                                                                :href="route('orders.show', $order)"
                                                                class="block text-xs text-blue-600 hover:text-blue-800 hover:underline"
                                                                wire:navigate
                                                            >
                                                                {{ $order->order_number }}
                                                            </flux:link>
                                                        @endforeach
                                                    </div>
                                                @endif
                                            </div>
                                        @else
                                            <div class="inline-flex items-center justify-center w-6 h-6 bg-gray-100 text-gray-400 rounded-full">
                                                <flux:icon icon="x-mark" class="w-4 h-4" />
                                            </div>
                                        @endif
                                    </td>
                                @endforeach
                                <td class="py-3 px-4 text-center font-medium border-l-2 border-gray-300">
                                    <div class="text-emerald-600">
                                        RM {{ number_format($totalPaidForStudent, 2) }}
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

    @if($selectedCourse && $selectedCourse->feeSettings)
        <!-- Legend -->
        <flux:card class="mt-6">
            <flux:header>
                <flux:heading size="lg">Legend</flux:heading>
            </flux:header>

            <div class="flex items-center space-x-6">
                <div class="flex items-center space-x-2">
                    <div class="inline-flex items-center justify-center w-6 h-6 bg-emerald-100 text-emerald-600 rounded-full">
                        <flux:icon icon="check" class="w-4 h-4" />
                    </div>
                    <flux:text>Payment Received</flux:text>
                </div>
                <div class="flex items-center space-x-2">
                    <div class="inline-flex items-center justify-center w-6 h-6 bg-gray-100 text-gray-400 rounded-full">
                        <flux:icon icon="x-mark" class="w-4 h-4" />
                    </div>
                    <flux:text>No Payment</flux:text>
                </div>
                <div class="text-sm text-gray-600">
                    Click on order numbers to view payment details
                </div>
            </div>
        </flux:card>
    @endif
</div>