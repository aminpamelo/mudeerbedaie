<?php

use App\Models\Certificate;
use App\Models\Course;
use App\Models\ClassModel;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public Certificate $certificate;

    public string $assignmentType = 'course';

    public ?int $selectedCourseId = null;

    public ?int $selectedClassId = null;

    public bool $isDefault = false;

    public string $searchCourses = '';

    public string $searchClasses = '';

    public function mount(Certificate $certificate): void
    {
        $this->certificate = $certificate;
    }

    public function with(): array
    {
        return [
            'assignedCourses' => $this->certificate->courses()
                ->withCount('students')
                ->get(),
            'assignedClasses' => $this->certificate->classes()
                ->with('course')
                ->withCount('enrollments')
                ->get(),
            'availableCourses' => Course::query()
                ->when($this->searchCourses, fn ($q) => $q->where('name', 'like', "%{$this->searchCourses}%"))
                ->whereNotIn('id', $this->certificate->courses()->pluck('courses.id'))
                ->get(),
            'availableClasses' => ClassModel::query()
                ->with('course')
                ->when($this->searchClasses, function ($q) {
                    $q->where('title', 'like', "%{$this->searchClasses}%")
                        ->orWhereHas('course', fn ($q) => $q->where('name', 'like', "%{$this->searchClasses}%"));
                })
                ->whereNotIn('id', $this->certificate->classes()->pluck('classes.id'))
                ->get(),
        ];
    }

    public function assignToCourse(): void
    {
        $this->validate([
            'selectedCourseId' => 'required|exists:courses,id',
        ]);

        $course = Course::find($this->selectedCourseId);

        if ($this->certificate->isAssignedToCourse($course)) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Certificate is already assigned to this course.',
            ]);

            return;
        }

        $this->certificate->assignToCourse($course, $this->isDefault);

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => 'Certificate assigned to course successfully.',
        ]);

        $this->reset(['selectedCourseId', 'isDefault', 'searchCourses']);
    }

    public function assignToClass(): void
    {
        $this->validate([
            'selectedClassId' => 'required|exists:classes,id',
        ]);

        $class = ClassModel::find($this->selectedClassId);

        if ($this->certificate->isAssignedToClass($class)) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Certificate is already assigned to this class.',
            ]);

            return;
        }

        $this->certificate->assignToClass($class, $this->isDefault);

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => 'Certificate assigned to class successfully.',
        ]);

        $this->reset(['selectedClassId', 'isDefault', 'searchClasses']);
    }

    public function unassignCourse(int $courseId): void
    {
        $this->certificate->courses()->detach($courseId);

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => 'Certificate unassigned from course successfully.',
        ]);
    }

    public function unassignClass(int $classId): void
    {
        $this->certificate->classes()->detach($classId);

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => 'Certificate unassigned from class successfully.',
        ]);
    }

    public function toggleDefaultCourse(int $courseId): void
    {
        $assignment = $this->certificate->courses()->where('course_id', $courseId)->first();

        if ($assignment) {
            $this->certificate->courses()->updateExistingPivot($courseId, [
                'is_default' => ! $assignment->pivot->is_default,
            ]);

            $this->dispatch('notify', [
                'type' => 'success',
                'message' => 'Default status updated successfully.',
            ]);
        }
    }

    public function toggleDefaultClass(int $classId): void
    {
        $assignment = $this->certificate->classes()->where('class_id', $classId)->first();

        if ($assignment) {
            $this->certificate->classes()->updateExistingPivot($classId, [
                'is_default' => ! $assignment->pivot->is_default,
            ]);

            $this->dispatch('notify', [
                'type' => 'success',
                'message' => 'Default status updated successfully.',
            ]);
        }
    }
} ?>

<div>
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ $certificate->name }} - Assignments</flux:heading>
            <flux:text class="mt-2">Assign this certificate to courses or classes</flux:text>
        </div>
        <div class="flex items-center gap-3">
            <flux:button variant="outline" href="{{ route('certificates.preview', $certificate) }}" icon="eye">
                Preview
            </flux:button>
            <flux:button variant="outline" href="{{ route('certificates.index') }}" icon="arrow-left">
                Back to List
            </flux:button>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Assignment Form -->
        <div>
            <flux:card>
                <flux:heading size="lg" class="mb-4">Add Assignment</flux:heading>

                <div class="space-y-4">
                    <!-- Assignment Type Selector -->
                    <div>
                        <flux:text variant="sm" class="font-medium mb-2">Assignment Type</flux:text>
                        <flux:radio.group wire:model.live="assignmentType">
                            <flux:radio value="course" label="Assign to Course" />
                            <flux:radio value="class" label="Assign to Class" />
                        </flux:radio.group>
                    </div>

                    @if($assignmentType === 'course')
                        <!-- Course Assignment -->
                        <div>
                            <flux:field>
                                <flux:label>Search Course</flux:label>
                                <flux:input
                                    wire:model.live.debounce.300ms="searchCourses"
                                    placeholder="Search by course name..."
                                    icon="magnifying-glass"
                                />
                            </flux:field>
                        </div>

                        <div>
                            <flux:field>
                                <flux:label>Select Course</flux:label>
                                <flux:select wire:model="selectedCourseId">
                                    <option value="">Choose a course...</option>
                                    @foreach($availableCourses as $course)
                                        <option value="{{ $course->id }}">{{ $course->name }}</option>
                                    @endforeach
                                </flux:select>
                            </flux:field>
                        </div>

                        <div>
                            <flux:checkbox wire:model="isDefault" label="Set as default certificate for this course" />
                        </div>

                        <flux:button variant="primary" wire:click="assignToCourse" class="w-full">
                            Assign to Course
                        </flux:button>
                    @else
                        <!-- Class Assignment -->
                        <div>
                            <flux:field>
                                <flux:label>Search Class</flux:label>
                                <flux:input
                                    wire:model.live.debounce.300ms="searchClasses"
                                    placeholder="Search by class or course name..."
                                    icon="magnifying-glass"
                                />
                            </flux:field>
                        </div>

                        <div>
                            <flux:field>
                                <flux:label>Select Class</flux:label>
                                <flux:select wire:model="selectedClassId">
                                    <option value="">Choose a class...</option>
                                    @foreach($availableClasses as $class)
                                        <option value="{{ $class->id }}">
                                            {{ $class->title }} ({{ $class->course->name }})
                                        </option>
                                    @endforeach
                                </flux:select>
                            </flux:field>
                        </div>

                        <div>
                            <flux:checkbox wire:model="isDefault" label="Set as default certificate for this class" />
                        </div>

                        <flux:button variant="primary" wire:click="assignToClass" class="w-full">
                            Assign to Class
                        </flux:button>
                    @endif
                </div>
            </flux:card>

            <!-- Certificate Info -->
            <flux:card class="mt-6">
                <flux:heading size="lg" class="mb-4">Certificate Status</flux:heading>

                <div class="space-y-3">
                    <div class="flex justify-between items-center">
                        <flux:text variant="sm" class="text-gray-500 dark:text-gray-400">Status</flux:text>
                        <flux:badge :variant="$certificate->status === 'active' ? 'success' : ($certificate->status === 'draft' ? 'warning' : 'default')">
                            {{ ucfirst($certificate->status) }}
                        </flux:badge>
                    </div>

                    <div class="flex justify-between items-center">
                        <flux:text variant="sm" class="text-gray-500 dark:text-gray-400">Total Issues</flux:text>
                        <flux:badge variant="neutral">{{ $certificate->issues()->count() }}</flux:badge>
                    </div>
                </div>
            </flux:card>
        </div>

        <!-- Assigned Courses & Classes -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Assigned Courses -->
            <flux:card>
                <div class="flex items-center justify-between mb-4">
                    <flux:heading size="lg">Assigned Courses ({{ $assignedCourses->count() }})</flux:heading>
                </div>

                @if($assignedCourses->isEmpty())
                    <div class="text-center py-8">
                        <flux:icon name="document-text" class="w-12 h-12 mx-auto mb-3 text-gray-400" />
                        <flux:text class="text-gray-500 dark:text-gray-400">No courses assigned yet</flux:text>
                    </div>
                @else
                    <div class="space-y-3">
                        @foreach($assignedCourses as $course)
                            <div class="flex items-center justify-between p-4 border border-gray-200 dark:border-gray-700 rounded-lg">
                                <div class="flex-1">
                                    <div class="flex items-center gap-2">
                                        <flux:heading size="sm">{{ $course->name }}</flux:heading>
                                        @if($course->pivot->is_default)
                                            <flux:badge variant="primary" size="sm">Default</flux:badge>
                                        @endif
                                    </div>
                                    <flux:text variant="sm" class="text-gray-500 dark:text-gray-400 mt-1">
                                        {{ $course->students_count }} student(s) enrolled
                                    </flux:text>
                                </div>
                                <div class="flex items-center gap-2">
                                    <flux:button
                                        variant="ghost"
                                        size="sm"
                                        wire:click="toggleDefaultCourse({{ $course->id }})"
                                    >
                                        <flux:icon :name="$course->pivot->is_default ? 'star-solid' : 'star'" class="w-4 h-4" />
                                    </flux:button>
                                    <flux:button
                                        variant="ghost"
                                        size="sm"
                                        wire:click="unassignCourse({{ $course->id }})"
                                        wire:confirm="Are you sure you want to unassign this certificate from the course?"
                                    >
                                        <flux:icon name="x-mark" class="w-4 h-4" />
                                    </flux:button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </flux:card>

            <!-- Assigned Classes -->
            <flux:card>
                <div class="flex items-center justify-between mb-4">
                    <flux:heading size="lg">Assigned Classes ({{ $assignedClasses->count() }})</flux:heading>
                </div>

                @if($assignedClasses->isEmpty())
                    <div class="text-center py-8">
                        <flux:icon name="user-group" class="w-12 h-12 mx-auto mb-3 text-gray-400" />
                        <flux:text class="text-gray-500 dark:text-gray-400">No classes assigned yet</flux:text>
                    </div>
                @else
                    <div class="space-y-3">
                        @foreach($assignedClasses as $class)
                            <div class="flex items-center justify-between p-4 border border-gray-200 dark:border-gray-700 rounded-lg">
                                <div class="flex-1">
                                    <div class="flex items-center gap-2">
                                        <flux:heading size="sm">{{ $class->title }}</flux:heading>
                                        @if($class->pivot->is_default)
                                            <flux:badge variant="primary" size="sm">Default</flux:badge>
                                        @endif
                                    </div>
                                    <flux:text variant="sm" class="text-gray-500 dark:text-gray-400 mt-1">
                                        {{ $class->course->name }} • {{ $class->enrollments_count }} student(s)
                                    </flux:text>
                                </div>
                                <div class="flex items-center gap-2">
                                    <flux:button
                                        variant="ghost"
                                        size="sm"
                                        wire:click="toggleDefaultClass({{ $class->id }})"
                                    >
                                        <flux:icon :name="$class->pivot->is_default ? 'star-solid' : 'star'" class="w-4 h-4" />
                                    </flux:button>
                                    <flux:button
                                        variant="ghost"
                                        size="sm"
                                        wire:click="unassignClass({{ $class->id }})"
                                        wire:confirm="Are you sure you want to unassign this certificate from the class?"
                                    >
                                        <flux:icon name="x-mark" class="w-4 h-4" />
                                    </flux:button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </flux:card>
        </div>
    </div>
</div>
