<?php

use App\Models\ClassModel;
use App\Models\Course;
use App\Models\Teacher;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;
    
    public $search = '';
    public $courseFilter = '';
    public $statusFilter = '';
    public $classTypeFilter = '';
    public $perPage = 10;
    
    public function updatingSearch()
    {
        $this->resetPage();
    }
    
    public function updatingCourseFilter()
    {
        $this->resetPage();
    }
    
    public function updatingStatusFilter()
    {
        $this->resetPage();
    }
    
    public function updatingClassTypeFilter()
    {
        $this->resetPage();
    }
    
    public function clearFilters()
    {
        $this->search = '';
        $this->courseFilter = '';
        $this->statusFilter = '';
        $this->classTypeFilter = '';
        $this->resetPage();
    }
    
    public function getClassesProperty()
    {
        return ClassModel::query()
            ->with(['course', 'teacher.user', 'sessions', 'activeStudents'])
            ->when($this->search, function ($query) {
                $query->where('title', 'like', '%' . $this->search . '%')
                    ->orWhereHas('course', function ($courseQuery) {
                        $courseQuery->where('name', 'like', '%' . $this->search . '%');
                    })
                    ->orWhereHas('teacher.user', function ($teacherQuery) {
                        $teacherQuery->where('name', 'like', '%' . $this->search . '%');
                    });
            })
            ->when($this->courseFilter, function ($query) {
                $query->where('course_id', $this->courseFilter);
            })
            ->when($this->statusFilter, function ($query) {
                $query->where('status', $this->statusFilter);
            })
            ->when($this->classTypeFilter, function ($query) {
                $query->where('class_type', $this->classTypeFilter);
            })
            ->orderBy('date_time', 'desc')
            ->paginate($this->perPage);
    }
    
    public function getTotalClassesProperty()
    {
        return ClassModel::count();
    }
    
    public function getActiveClassesProperty()
    {
        return ClassModel::where('status', 'active')->count();
    }
    
    public function getUpcomingClassesProperty()
    {
        return ClassModel::upcoming()->count();
    }
    
    public function getCoursesProperty()
    {
        return Course::where('status', 'active')->orderBy('name')->get();
    }
};

?>

<div>
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading size="xl">Classes</flux:heading>
            <flux:text class="mt-2">Manage classes and schedules</flux:text>
        </div>
        <flux:button variant="primary" href="{{ route('classes.create') }}" icon="plus">
            Schedule New Class
        </flux:button>
    </div>

    <div class="mt-6 space-y-6">
        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <flux:card class="p-6">
                <div class="flex items-center">
                    <div class="rounded-md bg-blue-50 p-3">
                        <flux:icon.calendar-days class="h-6 w-6 text-blue-600" />
                    </div>
                    <div class="ml-4">
                        <p class="text-2xl font-semibold text-gray-900">{{ $this->totalClasses }}</p>
                        <p class="text-sm text-gray-500">Total Classes</p>
                    </div>
                </div>
            </flux:card>
            
            <flux:card class="p-6">
                <div class="flex items-center">
                    <div class="rounded-md bg-green-50 p-3">
                        <flux:icon.clock class="h-6 w-6 text-green-600" />
                    </div>
                    <div class="ml-4">
                        <p class="text-2xl font-semibold text-gray-900">{{ $this->activeClasses }}</p>
                        <p class="text-sm text-gray-500">Active</p>
                    </div>
                </div>
            </flux:card>
            
            <flux:card class="p-6">
                <div class="flex items-center">
                    <div class="rounded-md bg-amber-50 p-3">
                        <flux:icon.forward class="h-6 w-6 text-amber-600" />
                    </div>
                    <div class="ml-4">
                        <p class="text-2xl font-semibold text-gray-900">{{ $this->upcomingClasses }}</p>
                        <p class="text-sm text-gray-500">Upcoming</p>
                    </div>
                </div>
            </flux:card>
        </div>

        <!-- Filters -->
        <flux:card>
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-5 gap-4 items-end">
                    <div class="md:col-span-2">
                        <flux:input 
                            wire:model.live="search" 
                            placeholder="Search classes..."
                            icon="magnifying-glass"
                        />
                    </div>
                    
                    <div>
                        <flux:select wire:model.live="courseFilter">
                            <flux:select.option value="">All Courses</flux:select.option>
                            @foreach($this->courses as $course)
                                <flux:select.option value="{{ $course->id }}">{{ $course->name }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    </div>
                    
                    <div>
                        <flux:select wire:model.live="statusFilter">
                            <flux:select.option value="">All Status</flux:select.option>
                            <flux:select.option value="draft">Draft</flux:select.option>
                            <flux:select.option value="active">Active</flux:select.option>
                            <flux:select.option value="completed">Completed</flux:select.option>
                            <flux:select.option value="suspended">Suspended</flux:select.option>
                            <flux:select.option value="cancelled">Cancelled</flux:select.option>
                        </flux:select>
                    </div>
                    
                    <div>
                        <flux:select wire:model.live="classTypeFilter">
                            <flux:select.option value="">All Types</flux:select.option>
                            <flux:select.option value="individual">Individual</flux:select.option>
                            <flux:select.option value="group">Group</flux:select.option>
                        </flux:select>
                    </div>
                </div>
                
                <div class="mt-4 flex justify-end">
                    <flux:button 
                        wire:click="clearFilters" 
                        variant="ghost" 
                        icon="x-mark"
                    >
                        Clear Filters
                    </flux:button>
                </div>
            </div>
        </flux:card>

        <!-- Classes List -->
        <flux:card>
            <div class="overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200">
                    <flux:heading size="lg">Classes List</flux:heading>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500  uppercase tracking-wider">Class</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500  uppercase tracking-wider">Course</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500  uppercase tracking-wider">Teacher</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500  uppercase tracking-wider">Schedule</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500  uppercase tracking-wider">Type</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500  uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500  uppercase tracking-wider">Students & Sessions</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500  uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white  divide-y divide-gray-200">
                            @forelse ($this->classes as $class)
                                <tr class="hover:bg-gray-50 :bg-gray-800 transition-colors duration-150">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div>
                                            <div class="font-medium text-gray-900">{{ $class->title }}</div>
                                            @if($class->description)
                                                <div class="text-sm text-gray-500  truncate max-w-xs">
                                                    {{ Str::limit($class->description, 50) }}
                                                </div>
                                            @endif
                                        </div>
                                    </td>
                                    
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900">{{ $class->course->name }}</div>
                                    </td>
                                    
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center gap-2">
                                            <flux:avatar size="sm" :name="$class->teacher->fullName" />
                                            <div class="text-sm text-gray-900">{{ $class->teacher->fullName }}</div>
                                        </div>
                                    </td>
                                    
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div>
                                            <div class="text-sm text-gray-900">{{ $class->date_time->format('M d, Y') }}</div>
                                            <div class="text-sm text-gray-500">
                                                {{ $class->date_time->format('g:i A') }} ({{ $class->formatted_duration }})
                                            </div>
                                        </div>
                                    </td>
                                    
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center gap-2">
                                            @if($class->isIndividual())
                                                <flux:icon.user class="h-4 w-4 text-blue-500" />
                                                <span class="text-sm text-gray-900">Individual</span>
                                            @else
                                                <flux:icon.users class="h-4 w-4 text-green-500" />
                                                <span class="text-sm text-gray-900">Group</span>
                                                @if($class->max_capacity)
                                                    <span class="text-xs text-gray-500">(Max: {{ $class->max_capacity }})</span>
                                                @endif
                                            @endif
                                        </div>
                                    </td>
                                    
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <flux:badge size="sm" :class="$class->status_badge_class">
                                            {{ ucfirst($class->status) }}
                                        </flux:badge>
                                    </td>
                                    
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900">
                                            {{ $class->active_student_count }} student(s)
                                        </div>
                                        <div class="text-xs text-gray-500">
                                            {{ $class->completed_sessions }}/{{ $class->total_sessions }} sessions
                                        </div>
                                        @if($class->completed_sessions > 0)
                                            <div class="text-xs text-gray-500">
                                                RM {{ number_format($class->calculateTotalTeacherAllowance(), 2) }}
                                            </div>
                                        @endif
                                    </td>
                                    
                                    <td class="px-6 py-4 whitespace-nowrap text-right">
                                        <div class="flex items-center justify-end gap-2">
                                            <flux:button 
                                                size="sm" 
                                                variant="ghost" 
                                                icon="eye"
                                                href="{{ route('classes.show', $class) }}"
                                            />
                                            <flux:button 
                                                size="sm" 
                                                variant="ghost" 
                                                icon="pencil"
                                                href="{{ route('classes.edit', $class) }}"
                                            />
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="px-6 py-12 text-center">
                                        <div class="text-gray-500">
                                            <flux:icon.calendar-days class="h-12 w-12 mx-auto mb-4 text-gray-300" />
                                            <p class="text-gray-600">No classes found</p>
                                            @if($search || $courseFilter || $statusFilter || $classTypeFilter)
                                                <flux:button 
                                                    wire:click="clearFilters" 
                                                    variant="ghost" 
                                                    size="sm" 
                                                    class="mt-2"
                                                >
                                                    Clear filters
                                                </flux:button>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                @if($this->classes->hasPages())
                    <div class="px-6 py-4 border-t border-gray-200">
                        {{ $this->classes->links() }}
                    </div>
                @endif
            </div>
        </flux:card>
    </div>
</div>