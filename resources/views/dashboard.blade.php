@php
    $user = auth()->user();
    $isAdmin = $user->isAdmin();
    $isTeacher = $user->isTeacher();
    $isStudent = $user->isStudent();
    
    if ($isAdmin) {
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
            <!-- Admin Dashboard -->
            <div class="grid gap-6 md:grid-cols-3">
                <!-- Stats Cards -->
                <flux:card>
                    <div class="flex items-center justify-between">
                        <div>
                            <flux:heading size="sm" class="text-gray-600 dark:text-gray-400">Total Courses</flux:heading>
                            <flux:heading size="xl">{{ $totalCourses }}</flux:heading>
                            <flux:text size="sm" class="text-emerald-600">{{ $activeCourses }} active</flux:text>
                        </div>
                        <flux:icon icon="academic-cap" class="w-8 h-8 text-blue-500" />
                    </div>
                </flux:card>

                <flux:card>
                    <div class="flex items-center justify-between">
                        <div>
                            <flux:heading size="sm" class="text-gray-600 dark:text-gray-400">Total Students</flux:heading>
                            <flux:heading size="xl">{{ $totalStudents }}</flux:heading>
                            <flux:text size="sm" class="text-emerald-600">{{ $activeStudents }} active</flux:text>
                        </div>
                        <flux:icon icon="users" class="w-8 h-8 text-emerald-500" />
                    </div>
                </flux:card>

                <flux:card>
                    <div class="flex items-center justify-between">
                        <div>
                            <flux:heading size="sm" class="text-gray-600 dark:text-gray-400">Total Enrollments</flux:heading>
                            <flux:heading size="xl">{{ $totalEnrollments }}</flux:heading>
                            <flux:text size="sm" class="text-emerald-600">{{ $activeEnrollments }} active</flux:text>
                        </div>
                        <flux:icon icon="clipboard" class="w-8 h-8 text-purple-500" />
                    </div>
                </flux:card>
            </div>


            <!-- Recent Enrollments -->
            <flux:card>
                <flux:header>
                    <flux:heading size="lg">Recent Enrollments</flux:heading>
                    <flux:link :href="route('enrollments.index')" variant="subtle">View all</flux:link>
                </flux:header>
                
                @if($recentEnrollments->isNotEmpty())
                    <div class="space-y-4">
                        @foreach($recentEnrollments as $enrollment)
                            <div class="flex items-center justify-between p-4 border rounded-lg dark:border-gray-700">
                                <div class="flex items-center space-x-4">
                                    <div class="w-10 h-10 bg-gray-200 dark:bg-gray-700 rounded-full flex items-center justify-center">
                                        <span class="text-sm font-medium">{{ $enrollment->student->user->initials() }}</span>
                                    </div>
                                    <div>
                                        <flux:text class="font-medium">{{ $enrollment->student->user->name }}</flux:text>
                                        <flux:text size="sm" class="text-gray-600 dark:text-gray-400">{{ $enrollment->course->name }}</flux:text>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <flux:badge :color="$enrollment->status === 'active' ? 'emerald' : ($enrollment->status === 'completed' ? 'blue' : 'gray')">
                                        {{ ucfirst($enrollment->status) }}
                                    </flux:badge>
                                    <flux:text size="sm" class="text-gray-600 dark:text-gray-400 block mt-1">
                                        {{ $enrollment->enrollment_date->format('M d, Y') }}
                                    </flux:text>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <flux:text class="text-gray-600 dark:text-gray-400">No enrollments yet.</flux:text>
                @endif
            </flux:card>

        @endif

        @if($isStudent && $user->student)
            <!-- Student Dashboard -->
            <div class="grid gap-6 md:grid-cols-3">
                <flux:card>
                    <div class="flex items-center justify-between">
                        <div>
                            <flux:heading size="sm" class="text-gray-600 dark:text-gray-400">Active Courses</flux:heading>
                            <flux:heading size="xl">{{ $activeEnrollmentsCount }}</flux:heading>
                            <flux:text size="sm" class="text-blue-600">Currently enrolled</flux:text>
                        </div>
                        <flux:icon icon="academic-cap" class="w-8 h-8 text-blue-500" />
                    </div>
                </flux:card>

                <flux:card>
                    <div class="flex items-center justify-between">
                        <div>
                            <flux:heading size="sm" class="text-gray-600 dark:text-gray-400">Completed Courses</flux:heading>
                            <flux:heading size="xl">{{ $completedEnrollmentsCount }}</flux:heading>
                            <flux:text size="sm" class="text-emerald-600">Successfully finished</flux:text>
                        </div>
                        <flux:icon icon="trophy" class="w-8 h-8 text-emerald-500" />
                    </div>
                </flux:card>


                <flux:card>
                    <div class="flex items-center justify-between">
                        <div>
                            <flux:heading size="sm" class="text-gray-600 dark:text-gray-400">Payment Methods</flux:heading>
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
                            <div class="p-4 border rounded-lg dark:border-gray-700">
                                <div class="flex items-start justify-between mb-3">
                                    <flux:heading size="sm">{{ $enrollment->course->name }}</flux:heading>
                                    <flux:badge :color="$enrollment->status === 'active' ? 'emerald' : ($enrollment->status === 'completed' ? 'blue' : 'gray')" size="sm">
                                        {{ ucfirst($enrollment->status) }}
                                    </flux:badge>
                                </div>
                                @if($enrollment->course->description)
                                    <flux:text size="sm" class="text-gray-600 dark:text-gray-400 mb-3">
                                        {{ Str::limit($enrollment->course->description, 100) }}
                                    </flux:text>
                                @endif
                                <div class="flex justify-between items-center text-sm text-gray-600 dark:text-gray-400">
                                    <span>Enrolled: {{ $enrollment->enrollment_date->format('M d, Y') }}</span>
                                    @if($enrollment->completion_date)
                                        <span>Completed: {{ $enrollment->completion_date->format('M d, Y') }}</span>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <flux:text class="text-gray-600 dark:text-gray-400">You're not enrolled in any courses yet.</flux:text>
                @endif
            </flux:card>
        @endif

        @if($isTeacher)
            <!-- Teacher Dashboard -->
            <div class="grid gap-6 md:grid-cols-2">
                <flux:card>
                    <div class="flex items-center justify-between">
                        <div>
                            <flux:heading size="sm" class="text-gray-600 dark:text-gray-400">My Courses</flux:heading>
                            <flux:heading size="xl">{{ $teacherCourses->count() }}</flux:heading>
                            <flux:text size="sm" class="text-blue-600">Courses created</flux:text>
                        </div>
                        <flux:icon icon="academic-cap" class="w-8 h-8 text-blue-500" />
                    </div>
                </flux:card>

                <flux:card>
                    <div class="flex items-center justify-between">
                        <div>
                            <flux:heading size="sm" class="text-gray-600 dark:text-gray-400">Total Enrollments</flux:heading>
                            <flux:heading size="xl">{{ $totalTeacherEnrollments }}</flux:heading>
                            <flux:text size="sm" class="text-emerald-600">Across all courses</flux:text>
                        </div>
                        <flux:icon icon="users" class="w-8 h-8 text-emerald-500" />
                    </div>
                </flux:card>
            </div>

            <!-- My Courses -->
            <flux:card>
                <flux:header>
                    <flux:heading size="lg">My Courses</flux:heading>
                    <flux:link :href="route('courses.create')" variant="subtle">Create new</flux:link>
                </flux:header>
                
                @if($teacherCourses->isNotEmpty())
                    <div class="space-y-4">
                        @foreach($teacherCourses as $course)
                            <div class="flex items-center justify-between p-4 border rounded-lg dark:border-gray-700">
                                <div>
                                    <flux:heading size="sm">{{ $course->name }}</flux:heading>
                                    @if($course->description)
                                        <flux:text size="sm" class="text-gray-600 dark:text-gray-400">{{ Str::limit($course->description, 100) }}</flux:text>
                                    @endif
                                    <div class="flex items-center space-x-4 mt-2">
                                        <flux:text size="sm" class="text-gray-600 dark:text-gray-400">{{ $course->enrollments_count }} enrollments</flux:text>
                                        <flux:text size="sm" class="text-emerald-600">{{ $course->active_enrollments_count }} active</flux:text>
                                    </div>
                                </div>
                                <div class="flex items-center space-x-2">
                                    <flux:badge :color="$course->status === 'active' ? 'emerald' : 'gray'">{{ ucfirst($course->status) }}</flux:badge>
                                    <flux:link :href="route('courses.edit', $course)" variant="ghost" size="sm">Edit</flux:link>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <flux:text class="text-gray-600 dark:text-gray-400">You haven't created any courses yet.</flux:text>
                @endif
            </flux:card>
        @endif
    </div>
</x-layouts.app>
