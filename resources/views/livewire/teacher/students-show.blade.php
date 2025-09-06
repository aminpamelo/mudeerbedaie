<?php

use App\Models\ClassAttendance;
use App\Models\ClassSession;
use App\Models\Student;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.teacher')] class extends Component
{
    public Student $student;

    public string $activeTab = 'overview';

    public function mount(Student $student)
    {
        // Ensure the student belongs to one of the teacher's classes
        $teacher = auth()->user()->teacher;
        $hasAccess = false;

        if ($teacher) {
            $teacherClassIds = $teacher->classes()->pluck('id');
            // Get student's class IDs through attendance -> sessions -> classes
            $studentClassIds = $student->classAttendances()
                ->with('session.class')
                ->get()
                ->pluck('session.class.id')
                ->filter()
                ->unique();
            $hasAccess = $teacherClassIds->intersect($studentClassIds)->isNotEmpty();
        }

        if (! $hasAccess) {
            abort(403, 'You do not have access to view this student.');
        }

        $this->student = $student;
    }

    public function setActiveTab(string $tab)
    {
        $this->activeTab = $tab;
    }

    public function getTeacherClassesProperty()
    {
        $teacher = auth()->user()->teacher;
        if (! $teacher) {
            return collect();
        }

        // Get classes where the student has attendance records
        $studentClassIds = $this->student->classAttendances()
            ->with('session.class')
            ->get()
            ->pluck('session.class.id')
            ->filter()
            ->unique();

        return $teacher->classes()
            ->with(['course'])
            ->whereIn('id', $studentClassIds)
            ->get();
    }

    public function getStudentAttendanceProperty()
    {
        return ClassAttendance::with(['session.class.course'])
            ->where('student_id', $this->student->id)
            ->whereHas('session.class', function ($query) {
                $teacher = auth()->user()->teacher;
                $query->where('teacher_id', $teacher->id);
            })
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function getAttendanceStatsProperty()
    {
        $attendances = $this->studentAttendance;
        $total = $attendances->count();
        $present = $attendances->whereIn('status', ['present', 'late'])->count();
        $absent = $attendances->where('status', 'absent')->count();
        $excused = $attendances->where('status', 'excused')->count();

        return [
            'total' => $total,
            'present' => $present,
            'absent' => $absent,
            'excused' => $excused,
            'attendance_rate' => $total > 0 ? round(($present / $total) * 100, 1) : 0,
        ];
    }

    public function getRecentSessionsProperty()
    {
        return $this->studentAttendance->take(10);
    }

    public function getUpcomingSessionsProperty()
    {
        $teacher = auth()->user()->teacher;
        $classIds = $this->teacherClasses->pluck('id');

        return ClassSession::with(['class.course'])
            ->whereIn('class_id', $classIds)
            ->where('session_date', '>=', now()->toDateString())
            ->where('status', 'scheduled')
            ->orderBy('session_date')
            ->orderBy('session_time')
            ->limit(5)
            ->get();
    }

    public function getMonthlyAttendanceProperty()
    {
        $attendances = $this->studentAttendance;
        $monthlyData = [];

        for ($i = 5; $i >= 0; $i--) {
            $month = now()->subMonths($i);
            $monthKey = $month->format('Y-m');
            $monthName = $month->format('M Y');

            $monthAttendances = $attendances->filter(function ($attendance) use ($month) {
                return $attendance->session->session_date->format('Y-m') === $month->format('Y-m');
            });

            $total = $monthAttendances->count();
            $present = $monthAttendances->whereIn('status', ['present', 'late'])->count();

            $monthlyData[] = [
                'month' => $monthName,
                'total' => $total,
                'present' => $present,
                'rate' => $total > 0 ? round(($present / $total) * 100, 1) : 0,
            ];
        }

        return collect($monthlyData);
    }

    public function getStudentEnrollmentsProperty()
    {
        return $this->student->activeEnrollments()
            ->with(['course'])
            ->get();
    }

    public function sendEmail()
    {
        $email = $this->student->user->email;
        $subject = urlencode('Student Communication - '.$this->student->user->name);
        $mailtoLink = "mailto:{$email}?subject={$subject}";

        $this->dispatch('open-mailto', url: $mailtoLink);
    }

    public function makeCall()
    {
        if ($this->student->phone) {
            $phone = preg_replace('/[^0-9+]/', '', $this->student->phone);
            $telLink = "tel:{$phone}";
            $this->dispatch('open-tel', url: $telLink);
        }
    }
}; ?>

<div>
    <!-- Page Header -->
    <div class="mb-6">
        <!-- Breadcrumb -->
        <nav class="flex mb-4" aria-label="Breadcrumb">
            <ol class="flex items-center space-x-4">
                <li>
                    <flux:link href="{{ route('teacher.students.index') }}" class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
                        Students
                    </flux:link>
                </li>
                <li>
                    <flux:icon name="chevron-right" class="w-4 h-4 text-gray-400" />
                </li>
                <li>
                    <span class="text-gray-900 dark:text-gray-100 font-medium">{{ $student->user->name }}</span>
                </li>
            </ol>
        </nav>
        
        <!-- Student Header Info -->
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-6">
            <div class="flex items-center space-x-4">
                <!-- Student Avatar -->
                <div class="w-16 h-16 bg-gradient-to-br from-blue-500 to-purple-600 rounded-full flex items-center justify-center text-white font-bold text-xl">
                    {{ substr($student->user->name, 0, 2) }}
                </div>
                
                <!-- Student Info -->
                <div>
                    <flux:heading size="xl" class="mb-1">{{ $student->user->name }}</flux:heading>
                    <div class="flex items-center space-x-4 text-sm text-gray-600 dark:text-gray-400">
                        <span>ID: {{ $student->student_id }}</span>
                        <span>•</span>
                        <span>{{ $student->user->email }}</span>
                        @if($student->phone)
                            <span>•</span>
                            <span>{{ $student->phone }}</span>
                        @endif
                    </div>
                    <div class="flex items-center space-x-2 mt-2">
                        <flux:badge color="{{ $student->status === 'active' ? 'green' : 'gray' }}" size="sm">
                            {{ ucfirst($student->status) }}
                        </flux:badge>
                        @if($this->studentEnrollments->isNotEmpty())
                            <flux:badge color="blue" size="sm">
                                {{ $this->studentEnrollments->count() }} {{ $this->studentEnrollments->count() === 1 ? 'Course' : 'Courses' }}
                            </flux:badge>
                        @endif
                    </div>
                </div>
            </div>
            
            <!-- Quick Stats -->
            <div class="flex flex-col sm:flex-row gap-4">
                <flux:card class="p-4 text-center">
                    <div class="text-2xl font-bold text-blue-600 dark:text-blue-400">{{ $this->attendanceStats['attendance_rate'] }}%</div>
                    <div class="text-sm text-gray-600 dark:text-gray-400">Attendance Rate</div>
                </flux:card>
                <flux:card class="p-4 text-center">
                    <div class="text-2xl font-bold text-emerald-600 dark:text-emerald-400">{{ $this->attendanceStats['present'] }}</div>
                    <div class="text-sm text-gray-600 dark:text-gray-400">Sessions Attended</div>
                </flux:card>
                <flux:card class="p-4 text-center">
                    <div class="text-2xl font-bold text-purple-600 dark:text-purple-400">{{ $this->teacherClasses->count() }}</div>
                    <div class="text-sm text-gray-600 dark:text-gray-400">{{ $this->teacherClasses->count() === 1 ? 'Class' : 'Classes' }}</div>
                </flux:card>
            </div>
        </div>
        
        <!-- Tab Navigation -->
        <div class="border-b border-gray-200 dark:border-gray-700 mt-6">
            <nav class="-mb-px flex space-x-8">
                <button 
                    wire:click="setActiveTab('overview')"
                    class="py-2 px-1 border-b-2 font-medium text-sm {{ $activeTab === 'overview' ? 'border-blue-500 text-blue-600 dark:text-blue-400' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300' }}"
                >
                    Overview
                </button>
                <button 
                    wire:click="setActiveTab('attendance')"
                    class="py-2 px-1 border-b-2 font-medium text-sm {{ $activeTab === 'attendance' ? 'border-blue-500 text-blue-600 dark:text-blue-400' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300' }}"
                >
                    Attendance
                </button>
                <button 
                    wire:click="setActiveTab('progress')"
                    class="py-2 px-1 border-b-2 font-medium text-sm {{ $activeTab === 'progress' ? 'border-blue-500 text-blue-600 dark:text-blue-400' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300' }}"
                >
                    Progress
                </button>
                <button 
                    wire:click="setActiveTab('communication')"
                    class="py-2 px-1 border-b-2 font-medium text-sm {{ $activeTab === 'communication' ? 'border-blue-500 text-blue-600 dark:text-blue-400' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300' }}"
                >
                    Communication
                </button>
            </nav>
        </div>
    </div>
    
    <!-- Tab Content -->
    <div class="mt-6">
        
        <!-- Overview Tab -->
        <div class="{{ $activeTab === 'overview' ? 'block' : 'hidden' }}">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                
                <!-- Personal Information -->
                <flux:card>
                    <div class="p-6">
                        <flux:heading size="lg" class="mb-4">Personal Information</flux:heading>
                        <div class="space-y-4">
                            <div class="flex justify-between items-center">
                                <flux:text class="font-medium text-gray-700 dark:text-gray-300">Full Name:</flux:text>
                                <flux:text>{{ $student->user->name }}</flux:text>
                            </div>
                            <div class="flex justify-between items-center">
                                <flux:text class="font-medium text-gray-700 dark:text-gray-300">Email:</flux:text>
                                <flux:text>{{ $student->user->email }}</flux:text>
                            </div>
                            <div class="flex justify-between items-center">
                                <flux:text class="font-medium text-gray-700 dark:text-gray-300">Student ID:</flux:text>
                                <flux:text>{{ $student->student_id }}</flux:text>
                            </div>
                            @if($student->phone)
                                <div class="flex justify-between items-center">
                                    <flux:text class="font-medium text-gray-700 dark:text-gray-300">Phone:</flux:text>
                                    <flux:text>{{ $student->phone }}</flux:text>
                                </div>
                            @endif
                            @if($student->date_of_birth)
                                <div class="flex justify-between items-center">
                                    <flux:text class="font-medium text-gray-700 dark:text-gray-300">Date of Birth:</flux:text>
                                    <flux:text>{{ $student->date_of_birth->format('M d, Y') }}</flux:text>
                                </div>
                                <div class="flex justify-between items-center">
                                    <flux:text class="font-medium text-gray-700 dark:text-gray-300">Age:</flux:text>
                                    <flux:text>{{ $student->age }} years old</flux:text>
                                </div>
                            @endif
                            @if($student->gender)
                                <div class="flex justify-between items-center">
                                    <flux:text class="font-medium text-gray-700 dark:text-gray-300">Gender:</flux:text>
                                    <flux:text>{{ ucfirst($student->gender) }}</flux:text>
                                </div>
                            @endif
                            @if($student->nationality)
                                <div class="flex justify-between items-center">
                                    <flux:text class="font-medium text-gray-700 dark:text-gray-300">Nationality:</flux:text>
                                    <flux:text>{{ $student->nationality }}</flux:text>
                                </div>
                            @endif
                            <div class="flex justify-between items-center">
                                <flux:text class="font-medium text-gray-700 dark:text-gray-300">Status:</flux:text>
                                <flux:badge color="{{ $student->status === 'active' ? 'green' : 'gray' }}" size="sm">
                                    {{ ucfirst($student->status) }}
                                </flux:badge>
                            </div>
                        </div>
                    </div>
                </flux:card>
                
                <!-- Current Enrollments -->
                <flux:card>
                    <div class="p-6">
                        <flux:heading size="lg" class="mb-4">Current Enrollments</flux:heading>
                        @if($this->studentEnrollments->isNotEmpty())
                            <div class="space-y-4">
                                @foreach($this->studentEnrollments as $enrollment)
                                    <div class="flex items-center justify-between p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
                                        <div>
                                            <flux:text class="font-medium">{{ $enrollment->course->name }}</flux:text>
                                            <flux:text size="sm" class="text-gray-500 dark:text-gray-400">
                                                Enrolled: {{ $enrollment->enrollment_date?->format('M d, Y') ?? 'N/A' }}
                                            </flux:text>
                                        </div>
                                        <flux:badge color="{{ $enrollment->status === 'active' ? 'green' : 'blue' }}" size="sm">
                                            {{ ucfirst($enrollment->status) }}
                                        </flux:badge>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <flux:text class="text-gray-500 dark:text-gray-400">No active enrollments</flux:text>
                        @endif
                    </div>
                </flux:card>
                
                <!-- Classes Overview -->
                <flux:card>
                    <div class="p-6">
                        <flux:heading size="lg" class="mb-4">Classes</flux:heading>
                        @if($this->teacherClasses->isNotEmpty())
                            <div class="space-y-4">
                                @foreach($this->teacherClasses as $class)
                                    <div class="p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
                                        <div class="flex items-start justify-between mb-2">
                                            <flux:text class="font-medium">{{ $class->title }}</flux:text>
                                            <flux:badge color="{{ $class->status === 'active' ? 'green' : 'blue' }}" size="sm">
                                                {{ ucfirst($class->status) }}
                                            </flux:badge>
                                        </div>
                                        <flux:text size="sm" class="text-gray-500 dark:text-gray-400 mb-2">
                                            {{ $class->course->name }}
                                        </flux:text>
                                        <div class="flex items-center text-xs text-gray-500 dark:text-gray-400 space-x-4">
                                            <span class="flex items-center">
                                                <flux:icon name="clock" class="w-3 h-3 mr-1" />
                                                {{ $class->formatted_duration }}
                                            </span>
                                            @if($class->location)
                                                <span class="flex items-center">
                                                    <flux:icon name="map-pin" class="w-3 h-3 mr-1" />
                                                    {{ $class->location }}
                                                </span>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <flux:text class="text-gray-500 dark:text-gray-400">Not enrolled in any of your classes</flux:text>
                        @endif
                    </div>
                </flux:card>
                
                <!-- Upcoming Sessions -->
                <flux:card>
                    <div class="p-6">
                        <flux:heading size="lg" class="mb-4">Upcoming Sessions</flux:heading>
                        @if($this->upcomingSessions->isNotEmpty())
                            <div class="space-y-3">
                                @foreach($this->upcomingSessions as $session)
                                    <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-700 rounded">
                                        <div>
                                            <flux:text class="font-medium">{{ $session->class->title }}</flux:text>
                                            <flux:text size="sm" class="text-gray-500 dark:text-gray-400">
                                                {{ $session->formatted_date_time }}
                                            </flux:text>
                                        </div>
                                        <flux:badge color="blue" size="sm">{{ $session->formatted_duration }}</flux:badge>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <flux:text class="text-gray-500 dark:text-gray-400">No upcoming sessions scheduled</flux:text>
                        @endif
                    </div>
                </flux:card>
                
            </div>
        </div>
        
        <!-- Attendance Tab -->
        <div class="{{ $activeTab === 'attendance' ? 'block' : 'hidden' }}">
            
            <!-- Attendance Statistics -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                <flux:card class="p-4 text-center">
                    <div class="text-2xl font-bold text-blue-600 dark:text-blue-400">{{ $this->attendanceStats['total'] }}</div>
                    <div class="text-sm text-gray-600 dark:text-gray-400">Total Sessions</div>
                </flux:card>
                <flux:card class="p-4 text-center">
                    <div class="text-2xl font-bold text-emerald-600 dark:text-emerald-400">{{ $this->attendanceStats['present'] }}</div>
                    <div class="text-sm text-gray-600 dark:text-gray-400">Present</div>
                </flux:card>
                <flux:card class="p-4 text-center">
                    <div class="text-2xl font-bold text-red-600 dark:text-red-400">{{ $this->attendanceStats['absent'] }}</div>
                    <div class="text-sm text-gray-600 dark:text-gray-400">Absent</div>
                </flux:card>
                <flux:card class="p-4 text-center">
                    <div class="text-2xl font-bold text-yellow-600 dark:text-yellow-400">{{ $this->attendanceStats['excused'] }}</div>
                    <div class="text-sm text-gray-600 dark:text-gray-400">Excused</div>
                </flux:card>
            </div>
            
            <!-- Monthly Attendance Chart -->
            <flux:card class="mb-6">
                <div class="p-6">
                    <flux:heading size="lg" class="mb-4">Monthly Attendance Trend</flux:heading>
                    <div class="space-y-4">
                        @foreach($this->monthlyAttendance as $month)
                            <div class="flex items-center justify-between">
                                <div class="flex-1">
                                    <div class="flex items-center justify-between mb-1">
                                        <flux:text class="font-medium">{{ $month['month'] }}</flux:text>
                                        <flux:text size="sm" class="text-gray-500 dark:text-gray-400">
                                            {{ $month['present'] }}/{{ $month['total'] }} ({{ $month['rate'] }}%)
                                        </flux:text>
                                    </div>
                                    <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                                        <div class="bg-{{ $month['rate'] >= 80 ? 'green' : ($month['rate'] >= 60 ? 'yellow' : 'red') }}-600 h-2 rounded-full" 
                                             style="width: {{ $month['rate'] }}%"></div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </flux:card>
            
            <!-- Detailed Attendance History -->
            <flux:card>
                <div class="p-6">
                    <flux:heading size="lg" class="mb-4">Attendance History</flux:heading>
                    @if($this->studentAttendance->isNotEmpty())
                        <div class="space-y-3">
                            @foreach($this->studentAttendance as $attendance)
                                <div class="flex items-center justify-between p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
                                    <div class="flex-1">
                                        <div class="flex items-start justify-between">
                                            <div>
                                                <flux:text class="font-medium">{{ $attendance->session->class->title ?? 'N/A' }}</flux:text>
                                                <flux:text size="sm" class="text-gray-500 dark:text-gray-400">
                                                    {{ $attendance->session->class->course->name ?? 'N/A' }}
                                                </flux:text>
                                            </div>
                                            <flux:text size="sm" class="text-gray-500 dark:text-gray-400">
                                                {{ $attendance->session->formatted_date_time ?? 'N/A' }}
                                            </flux:text>
                                        </div>
                                        @if($attendance->teacher_remarks)
                                            <flux:text size="sm" class="text-gray-600 dark:text-gray-400 mt-1">
                                                Note: {{ $attendance->teacher_remarks }}
                                            </flux:text>
                                        @endif
                                    </div>
                                    <div class="flex items-center space-x-3">
                                        <flux:badge color="{{ 
                                            $attendance->status === 'present' ? 'green' : 
                                            ($attendance->status === 'late' ? 'yellow' : 
                                            ($attendance->status === 'excused' ? 'blue' : 'red')) 
                                        }}" size="sm">
                                            {{ ucfirst($attendance->status) }}
                                        </flux:badge>
                                        @if($attendance->checked_in_at)
                                            <flux:text size="sm" class="text-gray-500 dark:text-gray-400">
                                                {{ $attendance->checked_in_at->format('g:i A') }}
                                            </flux:text>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <flux:text class="text-gray-500 dark:text-gray-400">No attendance records found</flux:text>
                    @endif
                </div>
            </flux:card>
            
        </div>
        
        <!-- Progress Tab -->
        <div class="{{ $activeTab === 'progress' ? 'block' : 'hidden' }}">
            
            <!-- Progress Overview -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                
                <!-- Overall Performance -->
                <flux:card>
                    <div class="p-6">
                        <flux:heading size="lg" class="mb-4">Overall Performance</flux:heading>
                        <div class="space-y-4">
                            <div class="flex items-center justify-between">
                                <flux:text class="font-medium">Attendance Rate</flux:text>
                                <div class="flex items-center space-x-2">
                                    <div class="w-24 bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                                        <div class="bg-green-600 h-2 rounded-full" style="width: {{ $this->attendanceStats['attendance_rate'] }}%"></div>
                                    </div>
                                    <flux:text size="sm">{{ $this->attendanceStats['attendance_rate'] }}%</flux:text>
                                </div>
                            </div>
                            
                            <div class="flex items-center justify-between">
                                <flux:text class="font-medium">Total Sessions</flux:text>
                                <flux:text>{{ $this->attendanceStats['total'] }}</flux:text>
                            </div>
                            
                            <div class="flex items-center justify-between">
                                <flux:text class="font-medium">Sessions Attended</flux:text>
                                <flux:text>{{ $this->attendanceStats['present'] }}</flux:text>
                            </div>
                            
                            <div class="flex items-center justify-between">
                                <flux:text class="font-medium">Active Enrollments</flux:text>
                                <flux:text>{{ $this->studentEnrollments->count() }}</flux:text>
                            </div>
                        </div>
                    </div>
                </flux:card>
                
                <!-- Class Performance -->
                <flux:card>
                    <div class="p-6">
                        <flux:heading size="lg" class="mb-4">Performance by Class</flux:heading>
                        @if($this->teacherClasses->isNotEmpty())
                            <div class="space-y-4">
                                @foreach($this->teacherClasses as $class)
                                    @php
                                        $classAttendances = $this->studentAttendance->filter(function($attendance) use ($class) {
                                            return $attendance->session && $attendance->session->class_id == $class->id;
                                        });
                                        $classTotal = $classAttendances->count();
                                        $classPresent = $classAttendances->whereIn('status', ['present', 'late'])->count();
                                        $classRate = $classTotal > 0 ? round(($classPresent / $classTotal) * 100, 1) : 0;
                                    @endphp
                                    <div>
                                        <div class="flex items-center justify-between mb-1">
                                            <flux:text class="font-medium">{{ $class->title }}</flux:text>
                                            <flux:text size="sm" class="text-gray-500 dark:text-gray-400">
                                                {{ $classPresent }}/{{ $classTotal }} ({{ $classRate }}%)
                                            </flux:text>
                                        </div>
                                        <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                                            <div class="bg-{{ $classRate >= 80 ? 'green' : ($classRate >= 60 ? 'yellow' : 'red') }}-600 h-2 rounded-full" 
                                                 style="width: {{ $classRate }}%"></div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <flux:text class="text-gray-500 dark:text-gray-400">No class data available</flux:text>
                        @endif
                    </div>
                </flux:card>
                
            </div>
            
            <!-- Achievement Badges -->
            <flux:card>
                <div class="p-6">
                    <flux:heading size="lg" class="mb-4">Achievements</flux:heading>
                    <div class="flex flex-wrap gap-3">
                        @if($this->attendanceStats['attendance_rate'] >= 95)
                            <flux:badge color="gold" class="px-3 py-1">🏆 Perfect Attendance</flux:badge>
                        @endif
                        @if($this->attendanceStats['attendance_rate'] >= 80)
                            <flux:badge color="green" class="px-3 py-1">✅ Regular Attendee</flux:badge>
                        @endif
                        @if($this->studentEnrollments->count() > 1)
                            <flux:badge color="blue" class="px-3 py-1">📚 Multi-Course Student</flux:badge>
                        @endif
                        @if($this->attendanceStats['total'] >= 10)
                            <flux:badge color="purple" class="px-3 py-1">🎯 Committed Student</flux:badge>
                        @endif
                        @if($this->attendanceStats['attendance_rate'] < 80 && $this->attendanceStats['total'] > 5)
                            <flux:badge color="yellow" class="px-3 py-1">⚠️ Needs Support</flux:badge>
                        @endif
                    </div>
                </div>
            </flux:card>
            
        </div>
        
        <!-- Communication Tab -->
        <div class="{{ $activeTab === 'communication' ? 'block' : 'hidden' }}">
            
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                
                <!-- Contact Information -->
                <flux:card>
                    <div class="p-6">
                        <flux:heading size="lg" class="mb-4">Contact Information</flux:heading>
                        <div class="space-y-4">
                            <div>
                                <flux:text class="font-medium text-gray-700 dark:text-gray-300 mb-1">Email</flux:text>
                                <div class="flex items-center justify-between">
                                    <flux:text>{{ $student->user->email }}</flux:text>
                                    <flux:button size="sm" variant="ghost" wire:click="sendEmail">
                                        <div class="flex items-center justify-center">
                                            <flux:icon name="envelope" class="w-4 h-4 mr-1" />
                                            Send Email
                                        </div>
                                    </flux:button>
                                </div>
                            </div>
                            
                            @if($student->phone)
                                <div>
                                    <flux:text class="font-medium text-gray-700 dark:text-gray-300 mb-1">Phone</flux:text>
                                    <div class="flex items-center justify-between">
                                        <flux:text>{{ $student->phone }}</flux:text>
                                        <flux:button size="sm" variant="ghost" wire:click="makeCall">
                                            <div class="flex items-center justify-center">
                                                <flux:icon name="phone" class="w-4 h-4 mr-1" />
                                                Call
                                            </div>
                                        </flux:button>
                                    </div>
                                </div>
                            @endif
                            
                            @if($student->address)
                                <div>
                                    <flux:text class="font-medium text-gray-700 dark:text-gray-300 mb-1">Address</flux:text>
                                    <flux:text>{{ $student->address }}</flux:text>
                                </div>
                            @endif
                        </div>
                    </div>
                </flux:card>
                
                <!-- Quick Actions -->
                <flux:card>
                    <div class="p-6">
                        <flux:heading size="lg" class="mb-4">Quick Actions</flux:heading>
                        <div class="space-y-3">
                            <flux:button variant="primary" class="w-full justify-start">
                                <flux:icon name="chat-bubble-left" class="w-4 h-4 mr-2" />
                                Send Message
                            </flux:button>
                            <flux:button variant="outline" class="w-full justify-start">
                                <flux:icon name="document-text" class="w-4 h-4 mr-2" />
                                Generate Progress Report
                            </flux:button>
                            <flux:button variant="outline" class="w-full justify-start">
                                <flux:icon name="calendar-days" class="w-4 h-4 mr-2" />
                                Schedule Meeting
                            </flux:button>
                            <flux:button variant="outline" class="w-full justify-start">
                                <flux:icon name="bell" class="w-4 h-4 mr-2" />
                                Set Reminder
                            </flux:button>
                        </div>
                    </div>
                </flux:card>
                
            </div>
            
            <!-- Teacher Notes Section -->
            <flux:card class="mt-6">
                <div class="p-6">
                    <flux:heading size="lg" class="mb-4">Teacher Notes</flux:heading>
                    <flux:textarea 
                        placeholder="Add notes about this student's progress, behavior, or any observations..."
                        rows="6"
                        class="w-full"
                    />
                    <div class="flex justify-end mt-3">
                        <flux:button variant="primary">
                            <flux:icon name="plus" class="w-4 h-4 mr-1" />
                            Add Note
                        </flux:button>
                    </div>
                </div>
            </flux:card>
            
        </div>
        
    </div>
</div>

<script>
document.addEventListener('livewire:init', function () {
    Livewire.on('open-mailto', (data) => {
        window.open(data.url, '_blank');
    });
    
    Livewire.on('open-tel', (data) => {
        window.location.href = data.url;
    });
});
</script>