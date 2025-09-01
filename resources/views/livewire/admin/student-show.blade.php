<?php

use App\Models\Student;
use Livewire\Volt\Component;

new class extends Component {
    public Student $student;

    public function mount(): void
    {
        $this->student->load([
            'user', 
            'enrollments.course', 
            'activeEnrollments.course', 
            'completedEnrollments.course', 
            'user.paymentMethods',
            'classAttendances.class.course',
            'classAttendances' => function ($query) {
                $query->orderBy('created_at', 'desc')->limit(20);
            }
        ]);
    }
    
    public function getAttendanceStatsProperty(): array
    {
        $attendances = $this->student->classAttendances;
        
        return [
            'total' => $attendances->count(),
            'present' => $attendances->where('status', 'present')->count(),
            'absent' => $attendances->where('status', 'absent')->count(),
            'late' => $attendances->where('status', 'late')->count(),
            'excused' => $attendances->where('status', 'excused')->count(),
            'rate' => $attendances->count() > 0 ? round(($attendances->where('status', 'present')->count() / $attendances->count()) * 100, 1) : 0,
        ];
    }
}; ?>

<div>
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ $student->user->name }}</flux:heading>
            <flux:text class="mt-2">Student ID: {{ $student->student_id }}</flux:text>
        </div>
        <div class="flex space-x-3">
            <flux:button variant="ghost" href="{{ route('students.index') }}">
                Back to Students
            </flux:button>
            <flux:button variant="primary" href="{{ route('students.edit', $student) }}" icon="pencil">
                Edit Student
            </flux:button>
        </div>
    </div>

    <div class="mt-6 space-y-8">
        <!-- Quick Stats -->
        <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
            <flux:card class="p-6">
                <div class="text-center">
                    <p class="text-2xl font-semibold text-blue-600">{{ $student->enrollments->count() }}</p>
                    <p class="text-sm text-gray-500">Total Enrollments</p>
                </div>
            </flux:card>
            
            <flux:card class="p-6">
                <div class="text-center">
                    <p class="text-2xl font-semibold text-green-600">{{ $student->activeEnrollments->count() }}</p>
                    <p class="text-sm text-gray-500">Active Enrollments</p>
                </div>
            </flux:card>
            
            <flux:card class="p-6">
                <div class="text-center">
                    <p class="text-2xl font-semibold text-emerald-600">{{ $student->completedEnrollments->count() }}</p>
                    <p class="text-sm text-gray-500">Completed Courses</p>
                </div>
            </flux:card>

            <flux:card class="p-6">
                <div class="text-center">
                    <p class="text-2xl font-semibold text-purple-600">{{ $this->attendanceStats['total'] }}</p>
                    <p class="text-sm text-gray-500">Classes Attended</p>
                </div>
            </flux:card>

            <flux:card class="p-6">
                <div class="text-center">
                    <flux:badge :class="match($student->status) {
                        'active' => 'badge-green',
                        'inactive' => 'badge-gray',
                        'graduated' => 'badge-blue',
                        'suspended' => 'badge-red',
                        default => 'badge-gray'
                    }">
                        {{ ucfirst($student->status) }}
                    </flux:badge>
                    <p class="text-sm text-gray-500 mt-1">Status</p>
                </div>
            </flux:card>
        </div>

        <!-- Payment Methods Section -->
        <flux:card>
            <div class="flex items-center justify-between">
                <div>
                    <flux:heading size="lg">Payment Methods</flux:heading>
                    <flux:text class="text-gray-600">Manage saved payment methods for subscriptions</flux:text>
                </div>
                <flux:button variant="outline" icon="credit-card" href="{{ route('admin.students.payment-methods', $student) }}">
                    Manage Payment Methods
                </flux:button>
            </div>
            
            <div class="mt-6 grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <p class="text-sm font-medium text-gray-500">Total Payment Methods</p>
                    <p class="text-2xl font-semibold text-blue-600">{{ $student->user->paymentMethods->count() }}</p>
                </div>
                <div>
                    <p class="text-sm font-medium text-gray-500">Active Methods</p>
                    <p class="text-2xl font-semibold text-green-600">{{ $student->user->paymentMethods->where('is_active', true)->count() }}</p>
                </div>
                <div>
                    <p class="text-sm font-medium text-gray-500">Default Method</p>
                    @php $defaultMethod = $student->user->paymentMethods->where('is_default', true)->first(); @endphp
                    @if($defaultMethod)
                        <p class="text-sm text-gray-900">{{ $defaultMethod->display_name }}</p>
                        <p class="text-xs text-gray-500">{{ $defaultMethod->is_expired ? 'Expired' : 'Active' }}</p>
                    @else
                        <p class="text-sm text-red-600">No default method</p>
                        <p class="text-xs text-gray-500">Subscription creation blocked</p>
                    @endif
                </div>
            </div>

            @if($student->user->paymentMethods->isEmpty())
                <div class="mt-6 p-4 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-700 rounded-lg">
                    <div class="flex items-start space-x-3">
                        <flux:icon.exclamation-triangle class="w-5 h-5 text-amber-600 mt-0.5" />
                        <div>
                            <flux:text class="font-medium text-amber-800 dark:text-amber-200">No Payment Methods</flux:text>
                            <flux:text class="text-amber-700 dark:text-amber-300 mt-1">
                                This student cannot create subscriptions until a payment method is added. 
                                Click "Manage Payment Methods" to add one.
                            </flux:text>
                        </div>
                    </div>
                </div>
            @endif
        </flux:card>

        <!-- Attendance Summary -->
        @if($this->attendanceStats['total'] > 0)
            <flux:card>
                <flux:heading size="lg">Attendance Overview</flux:heading>
                
                <div class="mt-6 grid grid-cols-2 md:grid-cols-5 gap-4">
                    <div class="text-center">
                        <p class="text-2xl font-bold text-green-600">{{ $this->attendanceStats['present'] }}</p>
                        <p class="text-sm text-gray-500">Present</p>
                    </div>
                    
                    <div class="text-center">
                        <p class="text-2xl font-bold text-red-600">{{ $this->attendanceStats['absent'] }}</p>
                        <p class="text-sm text-gray-500">Absent</p>
                    </div>
                    
                    <div class="text-center">
                        <p class="text-2xl font-bold text-yellow-600">{{ $this->attendanceStats['late'] }}</p>
                        <p class="text-sm text-gray-500">Late</p>
                    </div>
                    
                    <div class="text-center">
                        <p class="text-2xl font-bold text-blue-600">{{ $this->attendanceStats['excused'] }}</p>
                        <p class="text-sm text-gray-500">Excused</p>
                    </div>
                    
                    <div class="text-center">
                        <p class="text-2xl font-bold text-purple-600">{{ $this->attendanceStats['rate'] }}%</p>
                        <p class="text-sm text-gray-500">Attendance Rate</p>
                    </div>
                </div>

                <div class="mt-6 bg-gray-200 rounded-full h-3">
                    <div 
                        class="bg-green-500 h-3 rounded-full transition-all duration-300" 
                        style="width: {{ $this->attendanceStats['rate'] }}%"
                    ></div>
                </div>
            </flux:card>
        @endif

        <!-- Recent Attendance History -->
        @if($student->classAttendances->count() > 0)
            <flux:card>
                <flux:heading size="lg">Recent Class Attendance</flux:heading>
                <flux:text class="text-gray-600">Last 20 class sessions</flux:text>
                
                <div class="mt-6 overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Class</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Course</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Check In</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Remarks</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach($student->classAttendances as $attendance)
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div>
                                            <div class="text-sm font-medium text-gray-900">{{ $attendance->class->title }}</div>
                                            <div class="text-sm text-gray-500">{{ $attendance->class->formatted_duration }}</div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900">{{ $attendance->class->course->name }}</div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div>
                                            <div class="text-sm text-gray-900">{{ $attendance->class->date_time->format('M d, Y') }}</div>
                                            <div class="text-sm text-gray-500">{{ $attendance->class->date_time->format('g:i A') }}</div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <flux:badge size="sm" :class="$attendance->status_badge_class">
                                            {{ $attendance->status_label }}
                                        </flux:badge>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        {{ $attendance->formatted_checked_in_time ?: '-' }}
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-500">
                                        <div class="max-w-xs truncate">
                                            {{ $attendance->teacher_remarks ?: '-' }}
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </flux:card>
        @endif

        <!-- Personal Information -->
        <flux:card>
            <flux:heading size="lg">Personal Information</flux:heading>
            
            <div class="mt-6 space-y-4">
                <div class="flex items-center space-x-4">
                    <flux:avatar size="lg">
                        {{ $student->user->initials() }}
                    </flux:avatar>
                    <div>
                        <p class="text-lg font-medium">{{ $student->user->name }}</p>
                        <p class="text-gray-500">{{ $student->user->email }}</p>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4 pt-4">
                    <div>
                        <p class="text-sm font-medium text-gray-500">Phone</p>
                        <p class="text-sm text-gray-900">{{ $student->phone ?? 'N/A' }}</p>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-500">Date of Birth</p>
                        <p class="text-sm text-gray-900">
                            @if($student->date_of_birth)
                                {{ $student->date_of_birth->format('M d, Y') }} ({{ $student->age }} years old)
                            @else
                                N/A
                            @endif
                        </p>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-500">Gender</p>
                        <p class="text-sm text-gray-900">{{ $student->gender ? ucfirst($student->gender) : 'N/A' }}</p>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-500">Nationality</p>
                        <p class="text-sm text-gray-900">{{ $student->nationality ?? 'N/A' }}</p>
                    </div>
                </div>

                @if($student->address)
                    <div class="pt-4 border-t">
                        <p class="text-sm font-medium text-gray-500">Address</p>
                        <p class="text-sm text-gray-900">{{ $student->address }}</p>
                    </div>
                @endif
            </div>
        </flux:card>

        <!-- Current Enrollments -->
        @if($student->activeEnrollments->count() > 0)
            <flux:card>
                <flux:heading size="lg">Current Enrollments</flux:heading>
                
                <div class="mt-6 overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Course</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Enrolled Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach($student->activeEnrollments as $enrollment)
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div>
                                            <div class="text-sm font-medium text-gray-900">{{ $enrollment->course->name }}</div>
                                            <div class="text-sm text-gray-500">{{ $enrollment->course->description }}</div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <flux:badge :class="$enrollment->status_badge_class">
                                            {{ ucfirst($enrollment->status) }}
                                        </flux:badge>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        {{ $enrollment->enrollment_date->format('M d, Y') }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <flux:button size="sm" variant="ghost" href="{{ route('enrollments.show', $enrollment) }}">
                                            View Details
                                        </flux:button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </flux:card>
        @endif

        <!-- Enrollment History -->
        @if($student->enrollments->count() > $student->activeEnrollments->count())
            <flux:card>
                <flux:heading size="lg">Enrollment History</flux:heading>
                
                <div class="mt-6 overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Course</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Enrolled Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Completion Date</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach($student->enrollments->reject(fn($enrollment) => $enrollment->isActive()) as $enrollment)
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900">{{ $enrollment->course->name }}</div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <flux:badge :class="$enrollment->status_badge_class">
                                            {{ ucfirst($enrollment->status) }}
                                        </flux:badge>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        {{ $enrollment->enrollment_date->format('M d, Y') }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        {{ $enrollment->completion_date?->format('M d, Y') ?? 'N/A' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </flux:card>
        @endif
    </div>
</div>