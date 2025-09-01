<?php

use App\Models\ClassModel;
use App\Models\Course;
use App\Models\Teacher;
use App\Models\Student;
use App\Models\Enrollment;
use Livewire\Volt\Component;

new class extends Component {
    public $course_id = '';
    public $teacher_id = '';
    public $title = '';
    public $description = '';
    public $date_time = '';
    public $duration_minutes = 60;
    public $class_type = 'group';
    public $max_capacity = '';
    public $location = '';
    public $meeting_url = '';
    public $teacher_rate = 0;
    public $rate_type = 'per_class';
    public $commission_type = 'fixed';
    public $commission_value = 0;
    public $notes = '';
    
    // Timetable properties
    public $enable_timetable = false;
    public $weekly_schedule = [];
    public $recurrence_pattern = 'weekly';
    public $start_date = '';
    public $end_date = '';
    
    public function mount(): void
    {
        $this->date_time = now()->addDay()->format('Y-m-d\TH:i');
        $this->start_date = now()->addDay()->format('Y-m-d');
        $this->weekly_schedule = [
            'monday' => [],
            'tuesday' => [],
            'wednesday' => [],
            'thursday' => [],
            'friday' => [],
            'saturday' => [],
            'sunday' => [],
        ];
    }

    public function with(): array
    {
        return [
            'courses' => Course::where('status', 'active')->orderBy('name')->get(),
            'teachers' => Teacher::where('status', 'active')->with('user')->orderBy('teacher_id')->get(),
        ];
    }

    public function rules(): array
    {
        $rules = [
            'course_id' => 'required|exists:courses,id',
            'teacher_id' => 'required|exists:teachers,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'duration_minutes' => 'required|integer|min:15|max:480',
            'class_type' => 'required|in:individual,group',
            'max_capacity' => 'nullable|integer|min:1|max:100',
            'location' => 'nullable|string|max:255',
            'meeting_url' => 'nullable|url|max:255',
            'teacher_rate' => 'required|numeric|min:0',
            'rate_type' => 'required|in:per_class,per_student,per_session',
            'commission_type' => 'required_if:rate_type,per_session|in:percentage,fixed',
            'commission_value' => 'required_if:rate_type,per_session|numeric|min:0',
            'notes' => 'nullable|string',
            'enable_timetable' => 'boolean',
        ];

        if (!$this->enable_timetable) {
            $rules['date_time'] = 'required|date|after:now';
        } else {
            $rules['start_date'] = 'required|date|after_or_equal:today';
            $rules['end_date'] = 'nullable|date|after:start_date';
            $rules['recurrence_pattern'] = 'required|in:weekly,bi_weekly';
            $rules['weekly_schedule'] = 'required|array';
        }

        return $rules;
    }

    public function save(): void
    {
        $validated = $this->validate();

        // Additional validation for individual classes
        if ($this->class_type === 'individual' && !empty($this->max_capacity) && $this->max_capacity > 1) {
            $this->addError('max_capacity', 'Individual classes should not have capacity greater than 1.');
            return;
        }

        // Validate timetable schedule if enabled
        if ($this->enable_timetable) {
            $hasSchedule = false;
            foreach ($this->weekly_schedule as $times) {
                if (!empty($times)) {
                    $hasSchedule = true;
                    break;
                }
            }
            
            if (!$hasSchedule) {
                $this->addError('weekly_schedule', 'Please select at least one day and time for the timetable.');
                return;
            }
        }

        $class = ClassModel::create([
            'course_id' => $validated['course_id'],
            'teacher_id' => $validated['teacher_id'],
            'title' => $validated['title'],
            'description' => !empty($validated['description']) ? $validated['description'] : null,
            'date_time' => $this->enable_timetable ? now() : $validated['date_time'],
            'duration_minutes' => $validated['duration_minutes'],
            'class_type' => $validated['class_type'],
            'max_capacity' => $validated['class_type'] === 'individual' ? 1 : (!empty($validated['max_capacity']) ? $validated['max_capacity'] : null),
            'location' => !empty($validated['location']) ? $validated['location'] : null,
            'meeting_url' => !empty($validated['meeting_url']) ? $validated['meeting_url'] : null,
            'teacher_rate' => $validated['teacher_rate'],
            'rate_type' => $validated['rate_type'],
            'commission_type' => $validated['commission_type'],
            'commission_value' => $validated['commission_value'],
            'status' => 'draft',
            'notes' => !empty($validated['notes']) ? $validated['notes'] : null,
        ]);

        // Create timetable and sessions if enabled
        if ($this->enable_timetable) {
            $timetable = $class->timetable()->create([
                'weekly_schedule' => array_filter($this->weekly_schedule, fn($times) => !empty($times)),
                'recurrence_pattern' => $this->recurrence_pattern,
                'start_date' => $this->start_date,
                'end_date' => !empty($this->end_date) ? $this->end_date : null,
                'is_active' => true,
            ]);

            session()->flash('success', 'Class created with timetable successfully.');
        } else {
            // Create attendance records for enrolled students (single session)
            $this->createAttendanceRecords($class);
            session()->flash('success', 'Class scheduled successfully.');
        }

        $this->redirect(route('classes.index'));
    }

    private function createAttendanceRecords(ClassModel $class): void
    {
        $enrollments = Enrollment::where('course_id', $class->course_id)
            ->where('status', 'active')
            ->with('student')
            ->get();

        foreach ($enrollments as $enrollment) {
            \App\Models\ClassAttendance::create([
                'class_id' => $class->id,
                'student_id' => $enrollment->student_id,
                'enrollment_id' => $enrollment->id,
                'status' => 'absent',
            ]);
        }
    }

    public function updatedCourseId(): void
    {
        // Auto-select course teacher if available
        if ($this->course_id) {
            $course = Course::find($this->course_id);
            if ($course && $course->teacher_id) {
                $this->teacher_id = $course->teacher_id;
            }
        }
    }

    public function updatedClassType(): void
    {
        if ($this->class_type === 'individual') {
            $this->max_capacity = 1;
        } else {
            $this->max_capacity = '';
        }
    }

    public function updatedRateType(): void
    {
        if ($this->rate_type !== 'per_session') {
            $this->commission_type = 'fixed';
            $this->commission_value = 0;
        }
    }

    public function getEstimatedAllowanceProperty(): float
    {
        if (empty($this->teacher_rate) || empty($this->rate_type)) {
            return 0;
        }

        return match($this->rate_type) {
            'per_class' => (float) $this->teacher_rate,
            'per_student' => (float) $this->teacher_rate * ($this->max_capacity ?: 1),
            'per_session' => $this->calculateSessionAllowance(),
            default => 0,
        };
    }

    private function calculateSessionAllowance(): float
    {
        if (empty($this->course_id) || empty($this->commission_value)) {
            return 0;
        }

        $course = Course::with('classSettings')->find($this->course_id);
        if (!$course || !$course->classSettings) {
            return 0;
        }

        $sessionFee = match($course->classSettings->billing_type) {
            'per_session' => $course->classSettings->price_per_session ?? 0,
            'per_month' => ($course->classSettings->price_per_month ?? 0) / ($course->classSettings->sessions_per_month ?? 1),
            'per_minute' => ($course->classSettings->price_per_minute ?? 0) * $this->duration_minutes,
            default => 0,
        };

        return match($this->commission_type) {
            'percentage' => $sessionFee * ($this->commission_value / 100),
            'fixed' => (float) $this->commission_value,
            default => 0,
        };
    }

    public function addTimeSlot(string $day): void
    {
        if (!isset($this->weekly_schedule[$day])) {
            $this->weekly_schedule[$day] = [];
        }
        
        $this->weekly_schedule[$day][] = '09:00';
    }

    public function removeTimeSlot(string $day, int $index): void
    {
        if (isset($this->weekly_schedule[$day][$index])) {
            unset($this->weekly_schedule[$day][$index]);
            $this->weekly_schedule[$day] = array_values($this->weekly_schedule[$day]);
        }
    }

};

?>

<div class="space-y-6">
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading size="xl">Schedule New Class</flux:heading>
            <flux:text class="mt-2">Create a new class session</flux:text>
        </div>
        <flux:button variant="ghost" href="{{ route('classes.index') }}">
            Back to Classes
        </flux:button>
    </div>

    <div class="bg-white shadow rounded-lg">
        <form wire:submit="save" class="p-6 space-y-6">
            
            <!-- Basic Information -->
            <div class="space-y-4">
                <flux:heading size="lg">Class Information</flux:heading>
                
                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                    <flux:field>
                        <flux:label>Course</flux:label>
                        <flux:select wire:model.live="course_id" placeholder="Select course">
                            @foreach($courses as $course)
                                <flux:select.option value="{{ $course->id }}">{{ $course->name }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:error name="course_id" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Teacher</flux:label>
                        <flux:select wire:model="teacher_id" placeholder="Select teacher">
                            @foreach($teachers as $teacher)
                                <flux:select.option value="{{ $teacher->id }}">{{ $teacher->fullName }} ({{ $teacher->teacher_id }})</flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:error name="teacher_id" />
                    </flux:field>

                    <div class="sm:col-span-2">
                        <flux:field>
                            <flux:label>Class Title</flux:label>
                            <flux:input wire:model="title" type="text" placeholder="e.g., Introduction to Laravel" />
                            <flux:error name="title" />
                        </flux:field>
                    </div>

                    <div class="sm:col-span-2">
                        <flux:field>
                            <flux:label>Description</flux:label>
                            <flux:textarea wire:model="description" rows="3" placeholder="Optional class description"></flux:textarea>
                            <flux:error name="description" />
                        </flux:field>
                    </div>
                </div>
            </div>

            <!-- Schedule & Type -->
            <div class="border-t pt-6 space-y-4">
                <flux:heading size="lg">Schedule & Type</flux:heading>
                
                <!-- Timetable Toggle -->
                <flux:field>
                    <flux:checkbox wire:model.live="enable_timetable" label="Enable Recurring Timetable" />
                    <flux:description>Create multiple sessions with a weekly schedule instead of a single session</flux:description>
                    <flux:error name="enable_timetable" />
                </flux:field>

                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                    @if(!$enable_timetable)
                        <flux:field>
                            <flux:label>Date & Time</flux:label>
                            <flux:input wire:model="date_time" type="datetime-local" />
                            <flux:error name="date_time" />
                        </flux:field>
                    @endif

                    <flux:field>
                        <flux:label>Duration (minutes)</flux:label>
                        <flux:input wire:model="duration_minutes" type="number" min="15" max="480" />
                        <flux:description>Duration in minutes (15-480)</flux:description>
                        <flux:error name="duration_minutes" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Class Type</flux:label>
                        <flux:select wire:model.live="class_type">
                            <flux:select.option value="group">Group Class</flux:select.option>
                            <flux:select.option value="individual">Individual Class</flux:select.option>
                        </flux:select>
                        <flux:error name="class_type" />
                    </flux:field>

                    @if($class_type === 'group')
                        <flux:field>
                            <flux:label>Max Capacity</flux:label>
                            <flux:input wire:model="max_capacity" type="number" min="1" max="100" placeholder="Optional" />
                            <flux:description>Maximum number of students (optional)</flux:description>
                            <flux:error name="max_capacity" />
                        </flux:field>
                    @endif
                </div>

                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                    <flux:field>
                        <flux:label>Location</flux:label>
                        <flux:input wire:model="location" type="text" placeholder="e.g., Room 101 or Online" />
                        <flux:error name="location" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Meeting URL</flux:label>
                        <flux:input wire:model="meeting_url" type="url" placeholder="https://zoom.us/j/..." />
                        <flux:description>For online classes</flux:description>
                        <flux:error name="meeting_url" />
                    </flux:field>
                </div>

                <!-- Timetable Configuration -->
                @if($enable_timetable)
                    <div class="bg-gray-50 p-4 rounded-lg space-y-4">
                        <flux:heading size="md">Timetable Configuration</flux:heading>
                        
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                            <flux:field>
                                <flux:label>Start Date</flux:label>
                                <flux:input wire:model="start_date" type="date" />
                                <flux:error name="start_date" />
                            </flux:field>

                            <flux:field>
                                <flux:label>End Date (Optional)</flux:label>
                                <flux:input wire:model="end_date" type="date" />
                                <flux:error name="end_date" />
                            </flux:field>

                        </div>

                        <flux:field>
                            <flux:label>Recurrence Pattern</flux:label>
                            <flux:select wire:model="recurrence_pattern">
                                <flux:select.option value="weekly">Weekly</flux:select.option>
                                <flux:select.option value="bi_weekly">Bi-Weekly</flux:select.option>
                            </flux:select>
                            <flux:error name="recurrence_pattern" />
                        </flux:field>

                        <!-- Weekly Schedule -->
                        <div>
                            <flux:label class="block text-sm font-medium text-gray-900 mb-3">Weekly Schedule</flux:label>
                            <div class="space-y-3">
                                @foreach(['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'] as $day)
                                    <div class="flex items-center space-x-2">
                                        <div class="w-24 text-sm font-medium text-gray-700">{{ ucfirst($day) }}</div>
                                        <div class="flex-1 space-y-2">
                                            @if(isset($weekly_schedule[$day]))
                                                @foreach($weekly_schedule[$day] as $index => $time)
                                                    <div class="flex items-center space-x-2">
                                                        <flux:input 
                                                            wire:model="weekly_schedule.{{ $day }}.{{ $index }}" 
                                                            type="time" 
                                                            class="w-32"
                                                        />
                                                        <flux:button 
                                                            type="button" 
                                                            variant="danger" 
                                                            size="sm"
                                                            wire:click="removeTimeSlot('{{ $day }}', {{ $index }})"
                                                        >
                                                            Remove
                                                        </flux:button>
                                                    </div>
                                                @endforeach
                                            @endif
                                            <flux:button 
                                                type="button" 
                                                variant="outline" 
                                                size="sm"
                                                wire:click="addTimeSlot('{{ $day }}')"
                                            >
                                                + Add Time
                                            </flux:button>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                            <flux:error name="weekly_schedule" />
                        </div>

                        @if($this->preview_sessions > 0)
                            <div class="bg-blue-50 p-3 rounded border border-blue-200">
                                <flux:text class="text-sm text-blue-700">
                                    📅 Preview: Approximately {{ $this->preview_sessions }} sessions will be generated
                                </flux:text>
                            </div>
                        @endif
                    </div>
                @endif
            </div>

            <!-- Teacher Allowance Configuration -->
            <div class="border-t pt-6 space-y-4">
                <flux:heading size="lg">Teacher Allowance</flux:heading>
                
                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                    <flux:field>
                        <flux:label>Rate Type</flux:label>
                        <flux:select wire:model.live="rate_type">
                            <flux:select.option value="per_class">Per Class (Fixed)</flux:select.option>
                            <flux:select.option value="per_student">Per Student</flux:select.option>
                            <flux:select.option value="per_session">Per Session (Commission)</flux:select.option>
                        </flux:select>
                        <flux:error name="rate_type" />
                    </flux:field>

                    @if($rate_type !== 'per_session')
                        <flux:field>
                            <flux:label>
                                @if($rate_type === 'per_class')
                                    Fixed Rate (RM)
                                @elseif($rate_type === 'per_student')
                                    Rate per Student (RM)
                                @else
                                    Base Rate (RM)
                                @endif
                            </flux:label>
                            <flux:input wire:model.live="teacher_rate" type="number" step="0.01" min="0" />
                            <flux:error name="teacher_rate" />
                        </flux:field>
                    @endif

                    @if($rate_type === 'per_session')
                        <flux:field>
                            <flux:label>Commission Type</flux:label>
                            <flux:select wire:model.live="commission_type">
                                <flux:select.option value="percentage">Percentage of Course Fee</flux:select.option>
                                <flux:select.option value="fixed">Fixed Amount</flux:select.option>
                            </flux:select>
                            <flux:error name="commission_type" />
                        </flux:field>

                        <flux:field>
                            <flux:label>
                                @if($commission_type === 'percentage')
                                    Commission Percentage (%)
                                @else
                                    Fixed Commission (RM)
                                @endif
                            </flux:label>
                            <flux:input wire:model.live="commission_value" type="number" step="0.01" min="0" />
                            @if($commission_type === 'percentage')
                                <flux:description>Percentage of course session fee</flux:description>
                            @endif
                            <flux:error name="commission_value" />
                        </flux:field>
                    @endif
                </div>

                <!-- Estimated Allowance Preview -->
                @if($teacher_rate > 0)
                    <div class="bg-blue-50 p-4 rounded-lg">
                        <div class="flex items-center gap-2">
                            <flux:icon.calculator class="h-5 w-5 text-blue-600" />
                            <span class="font-medium text-blue-900">Estimated Teacher Allowance: RM {{ number_format($this->estimatedAllowance, 2) }}</span>
                        </div>
                        <p class="text-sm text-blue-700 mt-1">
                            @if($rate_type === 'per_class')
                                Fixed rate per class
                            @elseif($rate_type === 'per_student')
                                Based on {{ $max_capacity ?: 1 }} {{ $max_capacity == 1 ? 'student' : 'students' }}
                            @else
                                Based on course fee commission
                            @endif
                        </p>
                    </div>
                @endif
            </div>

            <!-- Additional Notes -->
            <div class="border-t pt-6">
                <flux:field>
                    <flux:label>Notes</flux:label>
                    <flux:textarea wire:model="notes" rows="3" placeholder="Optional notes for the class"></flux:textarea>
                    <flux:error name="notes" />
                </flux:field>
            </div>

            <div class="flex items-center justify-end space-x-3 pt-6 border-t">
                <flux:button variant="ghost" href="{{ route('classes.index') }}">
                    Cancel
                </flux:button>
                <flux:button type="submit" variant="primary">
                    Schedule Class
                </flux:button>
            </div>
        </form>
    </div>
</div>