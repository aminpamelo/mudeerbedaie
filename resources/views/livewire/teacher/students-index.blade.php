<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use App\Models\Student;
use App\Models\ClassModel;
use App\Models\Course;
use App\Models\ClassAttendance;
use App\Models\ClassSession;
use App\AcademicStatus;
use Illuminate\Database\Eloquent\Collection;

new #[Layout('components.layouts.teacher')] class extends Component {
    public string $search = '';
    public string $classFilter = 'all';
    public string $courseFilter = 'all';
    public string $statusFilter = 'all';
    public string $sortBy = 'name';
    public string $viewMode = 'grid';
    public ?int $selectedStudentId = null;
    public bool $showStudentModal = false;
    
    public function with()
    {
        $teacher = auth()->user()->teacher;
        
        if (!$teacher) {
            return [
                'students' => collect(),
                'classes' => collect(),
                'courses' => collect(),
                'statistics' => $this->getEmptyStatistics()
            ];
        }
        
        // Get teacher's classes and courses
        $classes = $teacher->classes()->with(['course', 'activeStudents.student.user'])->get();
        $courses = $teacher->courses()->get();
        
        // Get all students from teacher's classes
        $studentIds = collect();
        foreach ($classes as $class) {
            $classStudentIds = $class->activeStudents->pluck('student_id');
            $studentIds = $studentIds->merge($classStudentIds);
        }
        $studentIds = $studentIds->unique();
        
        // Build query for students
        $query = Student::with([
            'user',
            'classAttendances.session.class',
            'activeEnrollments.course'
        ])->whereIn('id', $studentIds);
        
        // Apply search filter
        if ($this->search) {
            $query->whereHas('user', function($q) {
                $q->where('name', 'like', '%' . $this->search . '%')
                  ->orWhere('email', 'like', '%' . $this->search . '%');
            })->orWhere('student_id', 'like', '%' . $this->search . '%');
        }
        
        // Apply class filter
        if ($this->classFilter !== 'all') {
            $query->whereHas('classAttendances.session', function($q) {
                $q->where('class_id', $this->classFilter);
            });
        }
        
        // Apply course filter
        if ($this->courseFilter !== 'all') {
            $query->whereHas('activeEnrollments', function($q) {
                $q->where('course_id', $this->courseFilter);
            });
        }
        
        // Apply status filter
        if ($this->statusFilter !== 'all') {
            $query->where('status', $this->statusFilter);
        }
        
        // Apply sorting
        switch ($this->sortBy) {
            case 'name':
                $query->whereHas('user', function($q) {
                    $q->orderBy('name');
                });
                break;
            case 'attendance':
                // Will be handled after collection
                break;
            case 'recent':
                $query->orderBy('updated_at', 'desc');
                break;
        }
        
        $students = $query->get();
        
        // Calculate statistics
        $statistics = [
            'total_students' => $students->count(),
            'active_students' => $students->where('status', 'active')->count(),
            'average_attendance' => $this->calculateAverageAttendance($students, $classes),
            'this_week_sessions' => $this->getThisWeekSessions($classes)
        ];
        
        return [
            'students' => $students,
            'classes' => $classes,
            'courses' => $courses,
            'statistics' => $statistics
        ];
    }
    
    private function calculateAverageAttendance($students, $classes): float
    {
        if ($students->isEmpty() || $classes->isEmpty()) {
            return 0;
        }
        
        $totalAttendanceRate = 0;
        $studentCount = 0;
        
        foreach ($students as $student) {
            $attendanceRate = $this->getStudentAttendanceRate($student, $classes);
            $totalAttendanceRate += $attendanceRate;
            $studentCount++;
        }
        
        return $studentCount > 0 ? round($totalAttendanceRate / $studentCount, 1) : 0;
    }
    
    private function getStudentAttendanceRate($student, $classes): float
    {
        $totalSessions = 0;
        $attendedSessions = 0;
        
        foreach ($classes as $class) {
            $studentInClass = $class->activeStudents->where('student_id', $student->id)->first();
            if (!$studentInClass) continue;
            
            $classAttendances = ClassAttendance::whereHas('session', function($q) use ($class) {
                $q->where('class_id', $class->id)->where('status', 'completed');
            })->where('student_id', $student->id)->get();
            
            $totalSessions += $classAttendances->count();
            $attendedSessions += $classAttendances->whereIn('status', ['present', 'late'])->count();
        }
        
        return $totalSessions > 0 ? round(($attendedSessions / $totalSessions) * 100, 1) : 0;
    }
    
    private function getThisWeekSessions($classes): int
    {
        $startOfWeek = now()->startOfWeek();
        $endOfWeek = now()->endOfWeek();
        
        return ClassSession::whereIn('class_id', $classes->pluck('id'))
            ->whereBetween('session_date', [$startOfWeek, $endOfWeek])
            ->count();
    }
    
    private function getEmptyStatistics(): array
    {
        return [
            'total_students' => 0,
            'active_students' => 0,
            'average_attendance' => 0,
            'this_week_sessions' => 0
        ];
    }
    
    public function updatedSearch()
    {
        $this->resetPage();
    }
    
    public function updatedClassFilter()
    {
        $this->resetPage();
    }
    
    public function updatedCourseFilter()
    {
        $this->resetPage();
    }
    
    public function updatedStatusFilter()
    {
        $this->resetPage();
    }
    
    public function resetPage()
    {
        // Reset to first page when filters change
    }
    
    public function selectStudent($studentId)
    {
        $this->selectedStudentId = $studentId;
        $this->showStudentModal = true;
    }
    
    public function closeStudentModal()
    {
        $this->showStudentModal = false;
        $this->selectedStudentId = null;
    }
    
    public function getSelectedStudentProperty()
    {
        if (!$this->selectedStudentId) {
            return null;
        }
        
        return Student::with([
            'user',
            'classAttendances.session.class.course',
            'activeEnrollments.course'
        ])->find($this->selectedStudentId);
    }
}; ?>

<div>
    <!-- Page Header -->
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading size="xl">Students</flux:heading>
            <flux:text class="mt-2">View and manage students enrolled in your courses and classes</flux:text>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="grid grid-cols-2 gap-4 md:grid-cols-4 md:gap-6 mb-6">
        <flux:card class="p-4 md:p-6">
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-xl md:text-2xl font-bold text-blue-600">{{ $statistics['total_students'] }}</div>
                    <div class="text-sm text-gray-600">Total Students</div>
                </div>
                <flux:icon name="users" class="h-8 w-8 text-blue-500" />
            </div>
        </flux:card>

        <flux:card class="p-4 md:p-6">
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-xl md:text-2xl font-bold text-emerald-600">{{ $statistics['active_students'] }}</div>
                    <div class="text-sm text-gray-600">Active Students</div>
                </div>
                <flux:icon name="check-circle" class="h-8 w-8 text-emerald-500" />
            </div>
        </flux:card>

        <flux:card class="p-4 md:p-6">
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-xl md:text-2xl font-bold text-purple-600">{{ $statistics['average_attendance'] }}%</div>
                    <div class="text-sm text-gray-600">Avg Attendance</div>
                </div>
                <flux:icon name="chart-bar" class="h-8 w-8 text-purple-500" />
            </div>
        </flux:card>

        <flux:card class="p-4 md:p-6">
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-xl md:text-2xl font-bold text-orange-600">{{ $statistics['this_week_sessions'] }}</div>
                    <div class="text-sm text-gray-600">This Week Sessions</div>
                </div>
                <flux:icon name="calendar-days" class="h-8 w-8 text-orange-500" />
            </div>
        </flux:card>
    </div>

    <!-- Filters and Search -->
    <flux:card class="p-6 mb-6">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
            <!-- Search -->
            <div class="flex-1 max-w-md">
                <flux:input 
                    wire:model.live.debounce.300ms="search" 
                    placeholder="Search students by name, email, or ID..." 
                    icon="magnifying-glass" 
                />
            </div>
            
            <!-- Filters -->
            <div class="flex flex-col sm:flex-row gap-3">
                <flux:select wire:model.live="classFilter" placeholder="All Classes" class="min-w-40">
                    <option value="all">All Classes</option>
                    @foreach($classes as $class)
                        <option value="{{ $class->id }}">{{ $class->title }}</option>
                    @endforeach
                </flux:select>
                
                <flux:select wire:model.live="courseFilter" placeholder="All Courses" class="min-w-40">
                    <option value="all">All Courses</option>
                    @foreach($courses as $course)
                        <option value="{{ $course->id }}">{{ $course->name }}</option>
                    @endforeach
                </flux:select>
                
                <flux:select wire:model.live="statusFilter" placeholder="All Status" class="min-w-32">
                    <option value="all">All Status</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                    <option value="completed">Completed</option>
                </flux:select>
                
                <flux:select wire:model.live="sortBy" class="min-w-32">
                    <option value="name">Sort by Name</option>
                    <option value="attendance">Sort by Attendance</option>
                    <option value="recent">Recently Active</option>
                </flux:select>
            </div>
            
            <!-- View Mode Toggle -->
            <div class="flex rounded-lg border border-gray-200">
                <button 
                    wire:click="$set('viewMode', 'grid')" 
                    class="p-2 {{ $viewMode === 'grid' ? 'bg-blue-50 text-blue-600 /20 ' : 'text-gray-600 ' }}"
                >
                    <flux:icon name="view-columns" class="w-4 h-4" />
                </button>
                <button 
                    wire:click="$set('viewMode', 'list')" 
                    class="p-2 {{ $viewMode === 'list' ? 'bg-blue-50 text-blue-600 /20 ' : 'text-gray-600 ' }}"
                >
                    <flux:icon name="bars-3" class="w-4 h-4" />
                </button>
            </div>
        </div>
    </flux:card>

    @if($students->count() > 0)
        <!-- Students Grid/List -->
        <div class="{{ $viewMode === 'grid' ? 'grid gap-6 md:grid-cols-2 lg:grid-cols-3' : 'space-y-4' }}">
            @foreach($students as $student)
                @php
                    $attendanceRate = $this->getStudentAttendanceRate($student, $classes);
                    $recentSession = $student->classAttendances()->with('session')->latest()->first();
                    $activeEnrollments = $student->activeEnrollments;
                @endphp
                
                <flux:card class="{{ $viewMode === 'grid' ? 'p-6' : 'p-4' }} hover:shadow-lg transition-shadow">
                    <div class="{{ $viewMode === 'grid' ? 'text-center' : 'flex items-center space-x-4' }}">
                        <!-- Student Avatar -->
                        <div class="{{ $viewMode === 'grid' ? 'mb-4' : 'flex-shrink-0' }}">
                            <div class="{{ $viewMode === 'grid' ? 'w-16 h-16 mx-auto' : 'w-12 h-12' }} bg-gradient-to-br from-blue-500 to-purple-600 rounded-full flex items-center justify-center text-white font-bold text-lg">
                                {{ substr($student->user->name, 0, 2) }}
                            </div>
                            @if($student->status === 'active')
                                <div class="{{ $viewMode === 'grid' ? 'mt-1' : 'mt-1 -ml-1' }}">
                                    <flux:badge color="green" size="sm">Active</flux:badge>
                                </div>
                            @endif
                        </div>
                        
                        <!-- Student Info -->
                        <div class="flex-1 {{ $viewMode === 'grid' ? 'text-center' : 'text-left' }}">
                            <div class="{{ $viewMode === 'grid' ? 'mb-2' : 'mb-1' }}">
                                <flux:heading size="sm" class="mb-1">{{ $student->user->name }}</flux:heading>
                                <flux:text size="xs" class="text-gray-500">
                                    ID: {{ $student->student_id }}
                                </flux:text>
                            </div>
                            
                            <!-- Course/Class Info -->
                            <div class="{{ $viewMode === 'grid' ? 'mb-3' : 'mb-2' }}">
                                @if($activeEnrollments->isNotEmpty())
                                    <div class="flex {{ $viewMode === 'grid' ? 'justify-center' : '' }} flex-wrap gap-1">
                                        @foreach($activeEnrollments->take(2) as $enrollment)
                                            <flux:badge color="blue" size="sm">{{ $enrollment->course->name }}</flux:badge>
                                        @endforeach
                                        @if($activeEnrollments->count() > 2)
                                            <flux:badge color="gray" size="sm">+{{ $activeEnrollments->count() - 2 }} more</flux:badge>
                                        @endif
                                    </div>
                                @endif
                            </div>
                            
                            <!-- Attendance Rate -->
                            <div class="{{ $viewMode === 'grid' ? 'mb-3' : 'mb-2' }}">
                                <div class="flex items-center {{ $viewMode === 'grid' ? 'justify-center' : '' }} text-sm text-gray-600  mb-1">
                                    <flux:icon name="chart-bar" class="w-4 h-4 mr-1" />
                                    Attendance: {{ $attendanceRate }}%
                                </div>
                                <div class="w-full bg-gray-200  rounded-full h-2">
                                    <div class="bg-{{ $attendanceRate >= 80 ? 'green' : ($attendanceRate >= 60 ? 'yellow' : 'red') }}-600 h-2 rounded-full" 
                                         style="width: {{ $attendanceRate }}%"></div>
                                </div>
                            </div>
                            
                            <!-- Recent Activity -->
                            @if($recentSession)
                                <div class="{{ $viewMode === 'grid' ? 'text-center' : '' }}">
                                    <flux:text size="xs" class="text-gray-500">
                                        Last session: {{ $recentSession->session->formatted_date_time ?? 'N/A' }}
                                    </flux:text>
                                </div>
                            @endif
                        </div>
                        
                        <!-- Quick Actions (List View) -->
                        @if($viewMode === 'list')
                            <div class="flex-shrink-0">
                                <flux:button size="sm" variant="ghost" href="{{ route('teacher.students.show', $student) }}">
                                    <flux:icon name="eye" class="w-4 h-4" />
                                </flux:button>
                            </div>
                        @endif
                    </div>
                    
                    <!-- Quick Actions (Grid View) -->
                    @if($viewMode === 'grid')
                        <div class="mt-4 flex justify-center space-x-2">
                            <flux:button size="sm" variant="ghost" href="{{ route('teacher.students.show', $student) }}">
                                <flux:icon name="eye" class="w-4 h-4 mr-1" />
                                View
                            </flux:button>
                            <flux:button size="sm" variant="ghost" wire:click="selectStudent({{ $student->id }})">
                                <flux:icon name="chart-bar" class="w-4 h-4 mr-1" />
                                Quick Preview
                            </flux:button>
                        </div>
                    @endif
                </flux:card>
            @endforeach
        </div>
    @else
        <!-- Empty State -->
        <flux:card class="text-center py-12">
            <flux:icon icon="users" class="w-16 h-16 text-gray-400 mx-auto mb-4" />
            @if($search || $classFilter !== 'all' || $courseFilter !== 'all' || $statusFilter !== 'all')
                <flux:heading size="lg" class="mb-4">No Students Found</flux:heading>
                <flux:text class="text-gray-600  mb-6 max-w-md mx-auto">
                    No students match your current search criteria. Try adjusting your filters or search terms.
                </flux:text>
                <flux:button variant="ghost" wire:click="$set('search', ''); $set('classFilter', 'all'); $set('courseFilter', 'all'); $set('statusFilter', 'all')">
                    Clear All Filters
                </flux:button>
            @else
                <flux:heading size="lg" class="mb-4">No Students Assigned</flux:heading>
                <flux:text class="text-gray-600  mb-6 max-w-md mx-auto">
                    You don't have any students in your classes yet. Students will appear here once they are enrolled in your courses and classes.
                </flux:text>
            @endif
        </flux:card>
    @endif
    
    <!-- Student Detail Modal -->
    @if($showStudentModal && $this->selectedStudent)
        @php $student = $this->selectedStudent; @endphp
        <div class="fixed inset-0 z-50 overflow-y-auto" 
             x-data="{ show: @entangle('showStudentModal') }"
             x-show="show"
             x-transition:enter="ease-out duration-300"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="ease-in duration-200"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0">
            
            <!-- Backdrop -->
            <div class="fixed inset-0 bg-black bg-opacity-50" 
                 x-show="show"
                 x-transition:enter="ease-out duration-300"
                 x-transition:enter-start="opacity-0"
                 x-transition:enter-end="opacity-100"
                 x-transition:leave="ease-in duration-200"
                 x-transition:leave-start="opacity-100"
                 x-transition:leave-end="opacity-0"
                 @click="$wire.closeStudentModal()"></div>
            
            <!-- Modal -->
            <div class="flex items-center justify-center min-h-screen px-4 py-8">
                <div class="relative bg-white  rounded-lg shadow-xl w-full max-w-4xl max-h-[90vh] overflow-hidden"
                     x-show="show"
                     x-transition:enter="ease-out duration-300"
                     x-transition:enter-start="opacity-0 scale-95"
                     x-transition:enter-end="opacity-100 scale-100"
                     x-transition:leave="ease-in duration-200"
                     x-transition:leave-start="opacity-100 scale-100"
                     x-transition:leave-end="opacity-0 scale-95">
                    
                    <!-- Modal Header -->
                    <div class="flex items-center justify-between p-6 border-b border-gray-200">
                        <div class="flex items-center space-x-4">
                            <div class="w-12 h-12 bg-gradient-to-br from-blue-500 to-purple-600 rounded-full flex items-center justify-center text-white font-bold text-lg">
                                {{ substr($student->user->name, 0, 2) }}
                            </div>
                            <div>
                                <flux:heading size="lg">{{ $student->user->name }}</flux:heading>
                                <flux:text size="sm" class="text-gray-500">
                                    Student ID: {{ $student->student_id }} • {{ $student->user->email }}
                                </flux:text>
                            </div>
                        </div>
                        <flux:button variant="ghost" wire:click="closeStudentModal">
                            <flux:icon name="x-mark" class="w-5 h-5" />
                        </flux:button>
                    </div>
                    
                    <!-- Modal Content -->
                    <div class="overflow-y-auto max-h-[calc(90vh-140px)]">
                        <div class="p-6">
                            <!-- Student Stats -->
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                                <flux:card class="p-4 text-center">
                                    <div class="text-xl font-bold text-blue-600  mb-1">
                                        {{ $student->activeEnrollments->count() }}
                                    </div>
                                    <div class="text-sm text-gray-600">Active Courses</div>
                                </flux:card>
                                
                                <flux:card class="p-4 text-center">
                                    <div class="text-xl font-bold text-emerald-600  mb-1">
                                        {{ $this->getStudentAttendanceRate($student, $classes) }}%
                                    </div>
                                    <div class="text-sm text-gray-600">Attendance Rate</div>
                                </flux:card>
                                
                                <flux:card class="p-4 text-center">
                                    @php
                                        $totalAttendances = $student->classAttendances->count();
                                        $presentAttendances = $student->classAttendances->whereIn('status', ['present', 'late'])->count();
                                    @endphp
                                    <div class="text-xl font-bold text-purple-600  mb-1">
                                        {{ $presentAttendances }}/{{ $totalAttendances }}
                                    </div>
                                    <div class="text-sm text-gray-600">Sessions Attended</div>
                                </flux:card>
                            </div>
                            
                            <!-- Student Information -->
                            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                                <!-- Personal Information -->
                                <flux:card>
                                    <div class="p-4">
                                        <flux:heading size="md" class="mb-4">Personal Information</flux:heading>
                                        <div class="space-y-3">
                                            <div class="flex justify-between">
                                                <flux:text class="font-medium">Full Name:</flux:text>
                                                <flux:text>{{ $student->user->name }}</flux:text>
                                            </div>
                                            <div class="flex justify-between">
                                                <flux:text class="font-medium">Email:</flux:text>
                                                <flux:text>{{ $student->user->email }}</flux:text>
                                            </div>
                                            @if($student->phone)
                                                <div class="flex justify-between">
                                                    <flux:text class="font-medium">Phone:</flux:text>
                                                    <flux:text>{{ $student->phone }}</flux:text>
                                                </div>
                                            @endif
                                            @if($student->date_of_birth)
                                                <div class="flex justify-between">
                                                    <flux:text class="font-medium">Date of Birth:</flux:text>
                                                    <flux:text>{{ $student->date_of_birth->format('M d, Y') }}</flux:text>
                                                </div>
                                            @endif
                                            @if($student->gender)
                                                <div class="flex justify-between">
                                                    <flux:text class="font-medium">Gender:</flux:text>
                                                    <flux:text>{{ ucfirst($student->gender) }}</flux:text>
                                                </div>
                                            @endif
                                            <div class="flex justify-between">
                                                <flux:text class="font-medium">Status:</flux:text>
                                                <flux:badge color="{{ $student->status === 'active' ? 'green' : 'gray' }}" size="sm">
                                                    {{ ucfirst($student->status) }}
                                                </flux:badge>
                                            </div>
                                        </div>
                                    </div>
                                </flux:card>
                                
                                <!-- Course Enrollments -->
                                <flux:card>
                                    <div class="p-4">
                                        <flux:heading size="md" class="mb-4">Course Enrollments</flux:heading>
                                        @if($student->activeEnrollments->isNotEmpty())
                                            <div class="space-y-3">
                                                @foreach($student->activeEnrollments as $enrollment)
                                                    <div class="flex items-center justify-between p-3 bg-gray-50  rounded">
                                                        <div>
                                                            <flux:text class="font-medium">{{ $enrollment->course->name }}</flux:text>
                                                            <flux:text size="sm" class="text-gray-500">
                                                                Enrolled: {{ $enrollment->enrollment_date ? $enrollment->enrollment_date->format('M d, Y') : 'N/A' }}
                                                            </flux:text>
                                                        </div>
                                                        <flux:badge :class="$enrollment->academic_status->badgeClass()" size="sm">
                                                            {{ $enrollment->academic_status->label() }}
                                                        </flux:badge>
                                                    </div>
                                                @endforeach
                                            </div>
                                        @else
                                            <flux:text class="text-gray-500">No active course enrollments</flux:text>
                                        @endif
                                    </div>
                                </flux:card>
                            </div>
                            
                            <!-- Recent Attendance -->
                            @if($student->classAttendances->isNotEmpty())
                                <flux:card class="mt-6">
                                    <div class="p-4">
                                        <flux:heading size="md" class="mb-4">Recent Attendance</flux:heading>
                                        <div class="space-y-3">
                                            @foreach($student->classAttendances()->with(['session.class'])->latest()->limit(10)->get() as $attendance)
                                                <div class="flex items-center justify-between p-3 bg-gray-50  rounded">
                                                    <div class="flex-1">
                                                        <flux:text class="font-medium">{{ $attendance->session->class->title ?? 'N/A' }}</flux:text>
                                                        <flux:text size="sm" class="text-gray-500">
                                                            {{ $attendance->session->formatted_date_time ?? 'N/A' }}
                                                        </flux:text>
                                                    </div>
                                                    <div class="flex items-center space-x-2">
                                                        <flux:badge color="{{ 
                                                            $attendance->status === 'present' ? 'green' : 
                                                            ($attendance->status === 'late' ? 'yellow' : 
                                                            ($attendance->status === 'excused' ? 'blue' : 'red')) 
                                                        }}" size="sm">
                                                            {{ ucfirst($attendance->status) }}
                                                        </flux:badge>
                                                        @if($attendance->checked_in_at)
                                                            <flux:text size="sm" class="text-gray-500">
                                                                {{ $attendance->checked_in_at->format('g:i A') }}
                                                            </flux:text>
                                                        @endif
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                </flux:card>
                            @endif
                        </div>
                    </div>
                    
                    <!-- Modal Footer -->
                    <div class="flex items-center justify-end p-6 border-t border-gray-200  space-x-3">
                        <flux:button variant="ghost" wire:click="closeStudentModal">Close</flux:button>
                        <flux:button variant="primary">
                            <flux:icon name="chat-bubble-left" class="w-4 h-4 mr-2" />
                            Send Message
                        </flux:button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>