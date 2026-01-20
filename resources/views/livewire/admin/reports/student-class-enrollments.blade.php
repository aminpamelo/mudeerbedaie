<?php

use App\Models\ClassModel;
use App\Models\ClassStudent;
use App\Models\ProductOrder;
use App\Models\Student;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    public $search = '';

    public $selectedClass = 'all';

    public $selectedStatus = 'all';

    public $sortBy = 'name';

    public $sortDirection = 'asc';

    public $perPage = 20;

    // Summary statistics
    public $totalStudents = 0;

    public $totalRevenue = 0;

    public $totalEnrollments = 0;

    public $avgRevenuePerStudent = 0;

    public function mount()
    {
        $this->loadSummaryStats();
    }

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function updatedSelectedClass()
    {
        $this->resetPage();
        $this->loadSummaryStats();
    }

    public function updatedSelectedStatus()
    {
        $this->resetPage();
        $this->loadSummaryStats();
    }

    public function sortByColumn($column)
    {
        if ($this->sortBy === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDirection = 'asc';
        }
    }

    public function loadSummaryStats()
    {
        $query = Student::query()
            ->whereHas('classStudents', function ($q) {
                if ($this->selectedClass !== 'all') {
                    $q->where('class_id', $this->selectedClass);
                }
                if ($this->selectedStatus !== 'all') {
                    $q->where('status', $this->selectedStatus);
                }
            });

        $studentIds = $query->pluck('id');

        $this->totalStudents = $studentIds->count();

        // Get total revenue from paid orders
        $this->totalRevenue = ProductOrder::whereIn('student_id', $studentIds)
            ->whereNotNull('paid_time')
            ->sum('total_amount');

        // Get total class enrollments
        $enrollmentQuery = ClassStudent::query();
        if ($this->selectedClass !== 'all') {
            $enrollmentQuery->where('class_id', $this->selectedClass);
        }
        if ($this->selectedStatus !== 'all') {
            $enrollmentQuery->where('status', $this->selectedStatus);
        }
        $this->totalEnrollments = $enrollmentQuery->whereIn('student_id', $studentIds)->count();

        $this->avgRevenuePerStudent = $this->totalStudents > 0
            ? $this->totalRevenue / $this->totalStudents
            : 0;
    }

    public function getClassesProperty()
    {
        return ClassModel::query()
            ->with('course:id,name')
            ->orderBy('title')
            ->get()
            ->map(fn ($class) => [
                'id' => $class->id,
                'name' => $class->title.' ('.$class->course?->name.')',
            ]);
    }

    public function getStudentsProperty()
    {
        $query = Student::query()
            ->with(['user:id,name,email', 'classStudents.class.course'])
            ->whereHas('classStudents', function ($q) {
                if ($this->selectedClass !== 'all') {
                    $q->where('class_id', $this->selectedClass);
                }
                if ($this->selectedStatus !== 'all') {
                    $q->where('status', $this->selectedStatus);
                }
            })
            ->withCount(['classStudents as active_classes_count' => function ($q) {
                $q->where('status', 'active');
            }])
            ->withSum(['orders as total_paid' => function ($q) {
                $q->whereNotNull('paid_time');
            }], 'total_amount');

        // Apply search
        if ($this->search) {
            $search = $this->search;
            $query->where(function ($q) use ($search) {
                $q->whereHas('user', function ($userQuery) use ($search) {
                    $userQuery->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                })
                    ->orWhere('student_id', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        // Apply sorting
        if ($this->sortBy === 'name') {
            $query->orderBy(
                \App\Models\User::select('name')
                    ->whereColumn('users.id', 'students.user_id')
                    ->limit(1),
                $this->sortDirection
            );
        } elseif ($this->sortBy === 'revenue') {
            $query->orderBy('total_paid', $this->sortDirection);
        } elseif ($this->sortBy === 'classes') {
            $query->orderBy('active_classes_count', $this->sortDirection);
        } else {
            $query->orderBy('created_at', 'desc');
        }

        return $query->paginate($this->perPage);
    }

    public function exportCsv()
    {
        $filename = 'student-class-enrollments-'.now()->format('Y-m-d').'.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () {
            $file = fopen('php://output', 'w');

            // Headers
            fputcsv($file, ['Student Class Enrollment Report']);
            fputcsv($file, ['Generated at', now()->format('Y-m-d H:i:s')]);
            fputcsv($file, ['Filter - Class', $this->selectedClass === 'all' ? 'All Classes' : $this->classes->firstWhere('id', $this->selectedClass)['name'] ?? 'N/A']);
            fputcsv($file, ['Filter - Status', $this->selectedStatus === 'all' ? 'All Statuses' : ucfirst($this->selectedStatus)]);
            fputcsv($file, []);

            // Summary
            fputcsv($file, ['Summary Statistics']);
            fputcsv($file, ['Total Students', $this->totalStudents]);
            fputcsv($file, ['Total Enrollments', $this->totalEnrollments]);
            fputcsv($file, ['Total Revenue', 'RM '.number_format($this->totalRevenue, 2)]);
            fputcsv($file, ['Average Revenue per Student', 'RM '.number_format($this->avgRevenuePerStudent, 2)]);
            fputcsv($file, []);

            // Student Data
            fputcsv($file, ['Student ID', 'Name', 'Email', 'Phone', 'Classes Enrolled', 'Class Names', 'Total Revenue']);

            $students = Student::query()
                ->with(['user:id,name,email', 'classStudents.class.course'])
                ->whereHas('classStudents', function ($q) {
                    if ($this->selectedClass !== 'all') {
                        $q->where('class_id', $this->selectedClass);
                    }
                    if ($this->selectedStatus !== 'all') {
                        $q->where('status', $this->selectedStatus);
                    }
                })
                ->withSum(['orders as total_paid' => function ($q) {
                    $q->whereNotNull('paid_time');
                }], 'total_amount')
                ->get();

            foreach ($students as $student) {
                $classNames = $student->classStudents
                    ->map(fn ($cs) => $cs->class?->title)
                    ->filter()
                    ->implode(', ');

                fputcsv($file, [
                    $student->student_id,
                    $student->user?->name ?? 'N/A',
                    $student->user?->email ?? 'N/A',
                    $student->phone ?? 'N/A',
                    $student->classStudents->count(),
                    $classNames,
                    'RM '.number_format($student->total_paid ?? 0, 2),
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}; ?>

<div>
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading size="xl">Student Class Enrollment Report</flux:heading>
            <flux:text class="mt-2">View students with their class enrollments and revenue</flux:text>
        </div>
        <flux:button wire:click="exportCsv" icon="arrow-down-tray">Export CSV</flux:button>
    </div>

    <!-- Filters -->
    <div class="mb-6 flex flex-wrap items-end gap-4">
        <div class="min-w-64 flex-1">
            <flux:input wire:model.live.debounce.300ms="search" placeholder="Search students..." icon="magnifying-glass" label="Search" />
        </div>
        <div class="w-64">
            <flux:select wire:model.live="selectedClass" label="Class">
                <option value="all">All Classes</option>
                @foreach ($this->classes as $class)
                    <option value="{{ $class['id'] }}">{{ $class['name'] }}</option>
                @endforeach
            </flux:select>
        </div>
        <div class="w-48">
            <flux:select wire:model.live="selectedStatus" label="Status">
                <option value="all">All Statuses</option>
                <option value="active">Active</option>
                <option value="transferred">Transferred</option>
                <option value="quit">Quit</option>
                <option value="completed">Completed</option>
            </flux:select>
        </div>
    </div>

    <!-- Summary Stats -->
    <div class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <flux:card class="space-y-2">
            <div class="flex items-center justify-between">
                <flux:heading size="lg">{{ number_format($totalStudents) }}</flux:heading>
                <div class="rounded-lg bg-blue-100 p-2">
                    <flux:icon name="users" class="h-6 w-6 text-blue-600" />
                </div>
            </div>
            <flux:text>Total Students</flux:text>
            <flux:subheading class="text-xs text-gray-500">Students with class enrollments</flux:subheading>
        </flux:card>

        <flux:card class="space-y-2">
            <div class="flex items-center justify-between">
                <flux:heading size="lg">{{ number_format($totalEnrollments) }}</flux:heading>
                <div class="rounded-lg bg-purple-100 p-2">
                    <flux:icon name="academic-cap" class="h-6 w-6 text-purple-600" />
                </div>
            </div>
            <flux:text>Total Enrollments</flux:text>
            <flux:subheading class="text-xs text-gray-500">Class enrollment records</flux:subheading>
        </flux:card>

        <flux:card class="space-y-2">
            <div class="flex items-center justify-between">
                <flux:heading size="lg">RM {{ number_format($totalRevenue, 2) }}</flux:heading>
                <div class="rounded-lg bg-green-100 p-2">
                    <flux:icon name="banknotes" class="h-6 w-6 text-green-600" />
                </div>
            </div>
            <flux:text>Total Revenue</flux:text>
            <flux:subheading class="text-xs text-gray-500">From student payments</flux:subheading>
        </flux:card>

        <flux:card class="space-y-2">
            <div class="flex items-center justify-between">
                <flux:heading size="lg">RM {{ number_format($avgRevenuePerStudent, 2) }}</flux:heading>
                <div class="rounded-lg bg-yellow-100 p-2">
                    <flux:icon name="calculator" class="h-6 w-6 text-yellow-600" />
                </div>
            </div>
            <flux:text>Avg Revenue/Student</flux:text>
            <flux:subheading class="text-xs text-gray-500">Average per student</flux:subheading>
        </flux:card>
    </div>

    <!-- Students Table -->
    <flux:card>
        <div class="mb-4">
            <flux:heading size="lg">Student Enrollments</flux:heading>
            <flux:text>Showing {{ $this->students->firstItem() ?? 0 }} - {{ $this->students->lastItem() ?? 0 }} of {{ $this->students->total() }} students</flux:text>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead>
                    <tr class="bg-gray-50">
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                            <button wire:click="sortByColumn('name')" class="flex items-center gap-1 hover:text-gray-700">
                                Student
                                @if ($sortBy === 'name')
                                    <flux:icon name="{{ $sortDirection === 'asc' ? 'chevron-up' : 'chevron-down' }}" class="h-4 w-4" />
                                @endif
                            </button>
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                            Student ID
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                            <button wire:click="sortByColumn('classes')" class="flex items-center gap-1 hover:text-gray-700">
                                Classes Enrolled
                                @if ($sortBy === 'classes')
                                    <flux:icon name="{{ $sortDirection === 'asc' ? 'chevron-up' : 'chevron-down' }}" class="h-4 w-4" />
                                @endif
                            </button>
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                            Class Details
                        </th>
                        <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">
                            <button wire:click="sortByColumn('revenue')" class="flex items-center justify-end gap-1 hover:text-gray-700">
                                Total Revenue
                                @if ($sortBy === 'revenue')
                                    <flux:icon name="{{ $sortDirection === 'asc' ? 'chevron-up' : 'chevron-down' }}" class="h-4 w-4" />
                                @endif
                            </button>
                        </th>
                        <th class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wider text-gray-500">
                            Actions
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 bg-white">
                    @forelse ($this->students as $student)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3">
                                <div>
                                    <div class="font-medium text-gray-900">{{ $student->user?->name ?? 'N/A' }}</div>
                                    <div class="text-sm text-gray-500">{{ $student->user?->email ?? 'N/A' }}</div>
                                </div>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-900">
                                {{ $student->student_id }}
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-sm">
                                <div class="flex items-center gap-2">
                                    <span class="font-medium text-gray-900">{{ $student->classStudents->count() }}</span>
                                    @if ($student->active_classes_count > 0)
                                        <flux:badge size="sm" color="green">{{ $student->active_classes_count }} active</flux:badge>
                                    @endif
                                </div>
                            </td>
                            <td class="px-4 py-3 text-sm">
                                <div class="max-w-md">
                                    @foreach ($student->classStudents->take(3) as $classStudent)
                                        <div class="mb-1 flex items-center gap-2">
                                            <flux:badge size="sm" :color="$classStudent->status === 'active' ? 'green' : ($classStudent->status === 'completed' ? 'blue' : 'gray')">
                                                {{ $classStudent->status }}
                                            </flux:badge>
                                            <span class="truncate text-gray-700">{{ $classStudent->class?->title ?? 'N/A' }}</span>
                                        </div>
                                    @endforeach
                                    @if ($student->classStudents->count() > 3)
                                        <div class="text-xs text-gray-500">+ {{ $student->classStudents->count() - 3 }} more classes</div>
                                    @endif
                                </div>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-right text-sm">
                                <span class="font-medium {{ ($student->total_paid ?? 0) > 0 ? 'text-green-600' : 'text-gray-500' }}">
                                    RM {{ number_format($student->total_paid ?? 0, 2) }}
                                </span>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-center text-sm">
                                <flux:button variant="ghost" size="sm" :href="route('students.show', $student)" wire:navigate>
                                    View
                                </flux:button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-8 text-center text-gray-500">
                                <flux:icon name="academic-cap" class="mx-auto mb-2 h-12 w-12 text-gray-300" />
                                <p>No students found with class enrollments.</p>
                                @if ($search || $selectedClass !== 'all' || $selectedStatus !== 'all')
                                    <p class="mt-1 text-sm">Try adjusting your filters.</p>
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($this->students->hasPages())
            <div class="mt-4 border-t border-gray-200 pt-4">
                {{ $this->students->links() }}
            </div>
        @endif
    </flux:card>
</div>
