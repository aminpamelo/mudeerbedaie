<?php

use App\Models\ClassModel;
use App\Models\Course;
use App\Models\Teacher;
use Livewire\Volt\Component;

new class extends Component {
    public ClassModel $class;
    public $course_id;
    public $teacher_id;
    public $title;
    public $description;
    public $date_time;
    public $duration_minutes;
    public $class_type;
    public $max_capacity;
    public $location;
    public $meeting_url;
    public $teacher_rate;
    public $rate_type;
    public $commission_type;
    public $commission_value;
    public $notes;
    public $status;
    
    // Timetable properties
    public $enable_timetable = false;
    public $weekly_schedule = [];
    public $recurrence_pattern = 'weekly';
    public $start_date = '';
    public $end_date = '';
    
    public function mount(ClassModel $class): void
    {
        $this->class = $class->load(['course', 'teacher', 'timetable']);
        
        // Populate form fields
        $this->course_id = $class->course_id;
        $this->teacher_id = $class->teacher_id;
        $this->title = $class->title;
        $this->description = $class->description;
        $this->date_time = $class->date_time->format('Y-m-d\TH:i');
        $this->duration_minutes = $class->duration_minutes;
        $this->class_type = $class->class_type;
        $this->max_capacity = $class->max_capacity;
        $this->location = $class->location;
        $this->meeting_url = $class->meeting_url;
        $this->teacher_rate = $class->teacher_rate;
        $this->rate_type = $class->rate_type;
        $this->commission_type = $class->commission_type;
        $this->commission_value = $class->commission_value;
        $this->notes = $class->notes;
        $this->status = $class->status;
        
        // Populate timetable fields
        if ($class->timetable) {
            $this->enable_timetable = true;
            $this->weekly_schedule = $class->timetable->weekly_schedule ?? [];
            $this->recurrence_pattern = $class->timetable->recurrence_pattern;
            $this->start_date = $class->timetable->start_date->format('Y-m-d');
            $this->end_date = $class->timetable->end_date ? $class->timetable->end_date->format('Y-m-d') : '';
        } else {
            $this->initializeWeeklySchedule();
        }
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
        return [
            'course_id' => 'required|exists:courses,id',
            'teacher_id' => 'required|exists:teachers,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'date_time' => 'required|date',
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
            'status' => 'required|in:draft,active,completed,cancelled,suspended',
        ];
    }

    public function save(): void
    {
        $validated = $this->validate();

        // Additional validation for individual classes
        if ($this->class_type === 'individual' && !empty($this->max_capacity) && $this->max_capacity > 1) {
            $this->addError('max_capacity', 'Individual classes should not have capacity greater than 1.');
            return;
        }

        // Check if changing course affects existing attendance records
        if ($this->course_id != $this->class->course_id) {
            $this->addError('course_id', 'Cannot change course as attendance records already exist.');
            return;
        }

        $this->class->update([
            'course_id' => $validated['course_id'],
            'teacher_id' => $validated['teacher_id'],
            'title' => $validated['title'],
            'description' => $validated['description'],
            'date_time' => $validated['date_time'],
            'duration_minutes' => $validated['duration_minutes'],
            'class_type' => $validated['class_type'],
            'max_capacity' => $validated['class_type'] === 'individual' ? 1 : $validated['max_capacity'],
            'location' => $validated['location'],
            'meeting_url' => $validated['meeting_url'],
            'teacher_rate' => $validated['teacher_rate'],
            'rate_type' => $validated['rate_type'],
            'commission_type' => $validated['commission_type'],
            'commission_value' => $validated['commission_value'],
            'notes' => $validated['notes'],
            'status' => $validated['status'],
        ]);

        // Handle timetable
        if ($this->enable_timetable) {
            $timetableData = [
                'weekly_schedule' => array_filter($this->weekly_schedule, fn($times) => !empty($times)),
                'recurrence_pattern' => $this->recurrence_pattern,
                'start_date' => $this->start_date,
                'end_date' => !empty($this->end_date) ? $this->end_date : null,
                'is_active' => true,
            ];

            if ($this->class->timetable) {
                // Update existing timetable
                $this->class->timetable->update($timetableData);
            } else {
                // Create new timetable
                $this->class->timetable()->create($timetableData);
            }

            session()->flash('success', 'Class and timetable updated successfully.');
        } else {
            // Remove timetable if disabled
            if ($this->class->timetable) {
                $this->class->timetable->delete();
            }
            session()->flash('success', 'Class updated successfully.');
        }

        $this->redirect(route('classes.show', $this->class));
    }

    public function updatedClassType(): void
    {
        if ($this->class_type === 'individual') {
            $this->max_capacity = 1;
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

    public function cancelClass(): void
    {
        $this->class->update(['status' => 'cancelled']);
        
        session()->flash('success', 'Class has been cancelled.');
        $this->redirect(route('classes.show', $this->class));
    }

    public function initializeWeeklySchedule(): void
    {
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

    public function addTimeSlot($day): void
    {
        $this->weekly_schedule[$day][] = '09:00';
    }

    public function removeTimeSlot($day, $index): void
    {
        if (isset($this->weekly_schedule[$day][$index])) {
            array_splice($this->weekly_schedule[$day], $index, 1);
        }
    }

    public function updatedEnableTimetable(): void
    {
        if ($this->enable_timetable && empty($this->weekly_schedule)) {
            $this->initializeWeeklySchedule();
        }
        if ($this->enable_timetable && empty($this->start_date)) {
            $this->start_date = now()->addDay()->format('Y-m-d');
        }
    }

};

?>

<div class="space-y-6">
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading size="xl">Edit Class</flux:heading>
            <flux:text class="mt-2">Update class details and schedule</flux:text>
        </div>
        <div class="flex gap-2">
            <flux:button variant="ghost" href="{{ route('classes.show', $class) }}">
                Cancel
            </flux:button>
            <flux:button 
                wire:click="cancelClass" 
                variant="danger"
                wire:confirm="Are you sure you want to cancel this class? This action cannot be undone."
            >
                Cancel Class
            </flux:button>
        </div>
    </div>

    <!-- Current Class Info -->
    <flux:card>
        <div class="p-4 bg-blue-50">
            <div class="flex items-center gap-3">
                <flux:icon.information-circle class="h-5 w-5 text-blue-600" />
                <div>
                    <p class="font-medium text-blue-900">Currently editing: {{ $class->title }}</p>
                    <p class="text-sm text-blue-700">
                        {{ $class->course->name }} • {{ $class->formatted_date_time }} • 
                        {{ $class->attendances->count() }} students enrolled
                    </p>
                </div>
            </div>
        </div>
    </flux:card>

    <div class="bg-white shadow rounded-lg">
        <form wire:submit="save" class="p-6 space-y-6">
            
            <!-- Basic Information -->
            <div class="space-y-4">
                <flux:heading size="lg">Class Information</flux:heading>
                
                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                    <flux:field>
                        <flux:label>Course</flux:label>
                        <flux:select wire:model="course_id" disabled>
                            @foreach($courses as $course)
                                <flux:select.option value="{{ $course->id }}">{{ $course->name }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:description>Course cannot be changed after attendance records are created</flux:description>
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
                            <flux:input wire:model="title" type="text" />
                            <flux:error name="title" />
                        </flux:field>
                    </div>

                    <div class="sm:col-span-2">
                        <flux:field>
                            <flux:label>Description</flux:label>
                            <flux:textarea wire:model="description" rows="3"></flux:textarea>
                            <flux:error name="description" />
                        </flux:field>
                    </div>
                </div>
            </div>

            <!-- Schedule & Type -->
            <div class="border-t pt-6 space-y-4">
                <flux:heading size="lg">Schedule & Type</flux:heading>
                
                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                    <flux:field>
                        <flux:label>Date & Time</flux:label>
                        <flux:input wire:model="date_time" type="datetime-local" />
                        <flux:error name="date_time" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Duration (minutes)</flux:label>
                        <flux:input wire:model="duration_minutes" type="number" min="15" max="480" />
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
                            <flux:input wire:model="max_capacity" type="number" min="1" max="100" />
                            <flux:error name="max_capacity" />
                        </flux:field>
                    @endif

                    <flux:field>
                        <flux:label>Status</flux:label>
                        <flux:select wire:model="status">
                            <flux:select.option value="draft">Draft</flux:select.option>
                            <flux:select.option value="active">Active</flux:select.option>
                            <flux:select.option value="completed">Completed</flux:select.option>
                            <flux:select.option value="suspended">Suspended</flux:select.option>
                            <flux:select.option value="cancelled">Cancelled</flux:select.option>
                        </flux:select>
                        <flux:error name="status" />
                    </flux:field>
                </div>

                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                    <flux:field>
                        <flux:label>Location</flux:label>
                        <flux:input wire:model="location" type="text" />
                        <flux:error name="location" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Meeting URL</flux:label>
                        <flux:input wire:model="meeting_url" type="url" />
                        <flux:error name="meeting_url" />
                    </flux:field>
                </div>
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
                    </div>
                @endif
            </div>

            <!-- Timetable Configuration -->
            <div class="border-t pt-6 space-y-4">
                <div class="flex items-center justify-between">
                    <div>
                        <flux:heading size="lg">Recurring Timetable</flux:heading>
                        <flux:text class="text-gray-600">Configure automatic session scheduling for this class</flux:text>
                    </div>
                    <flux:switch wire:model.live="enable_timetable" />
                </div>

                @if($enable_timetable)
                    <!-- Timetable Configuration -->
                    <div class="p-4 bg-blue-50 dark:bg-blue-900/20 rounded-lg border border-blue-200 dark:border-blue-800 space-y-4">
                        <div class="grid grid-cols-1 gap-6 sm:grid-cols-3">
                            <flux:field>
                                <flux:label>Recurrence Pattern</flux:label>
                                <flux:select wire:model="recurrence_pattern">
                                    <flux:select.option value="weekly">Weekly</flux:select.option>
                                    <flux:select.option value="bi_weekly">Bi-weekly</flux:select.option>
                                </flux:select>
                            </flux:field>

                            <flux:field>
                                <flux:label>Start Date</flux:label>
                                <flux:input wire:model.live="start_date" type="date" />
                            </flux:field>

                            <flux:field>
                                <flux:label>End Date (Optional)</flux:label>
                                <flux:input wire:model.live="end_date" type="date" />
                            </flux:field>
                        </div>


                        <!-- Weekly Schedule Builder -->
                        <div class="space-y-4">
                            <flux:heading size="md">Weekly Schedule</flux:heading>
                            
                            <div class="grid grid-cols-1 md:grid-cols-7 gap-4">
                                @foreach(['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'] as $day)
                                    <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-3">
                                        <div class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3 text-center">
                                            {{ ucfirst($day) }}
                                        </div>
                                        
                                        @if(isset($weekly_schedule[$day]) && is_array($weekly_schedule[$day]))
                                            @foreach($weekly_schedule[$day] as $index => $time)
                                                <div class="flex items-center gap-2 mb-2">
                                                    <flux:input 
                                                        wire:model="weekly_schedule.{{ $day }}.{{ $index }}" 
                                                        type="time" 
                                                        class="text-sm" 
                                                    />
                                                    <flux:button 
                                                        wire:click="removeTimeSlot('{{ $day }}', {{ $index }})"
                                                        variant="ghost" 
                                                        size="sm"
                                                        icon="x-mark"
                                                        class="text-red-600 hover:text-red-800"
                                                    />
                                                </div>
                                            @endforeach
                                        @endif
                                        
                                        <flux:button 
                                            wire:click="addTimeSlot('{{ $day }}')"
                                            variant="outline" 
                                            size="sm"
                                            class="w-full text-xs"
                                            icon="plus"
                                        >
                                            Add Time
                                        </flux:button>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                    </div>
                @endif
            </div>

            <!-- Additional Notes -->
            <div class="border-t pt-6">
                <flux:field>
                    <flux:label>Notes</flux:label>
                    <flux:textarea wire:model="notes" rows="3"></flux:textarea>
                    <flux:error name="notes" />
                </flux:field>
            </div>

            <div class="flex items-center justify-end space-x-3 pt-6 border-t">
                <flux:button variant="ghost" href="{{ route('classes.show', $class) }}">
                    Cancel
                </flux:button>
                <flux:button type="submit" variant="primary">
                    Update Class
                </flux:button>
            </div>
        </form>
    </div>
</div>