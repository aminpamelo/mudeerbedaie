<?php

use App\Models\Course;
use App\Models\CourseFeeSettings;
use App\Models\CourseClassSettings;
use App\Models\Teacher;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;

new class extends Component {
    public $step = 1;
    
    // Course basic info
    public $name = '';
    public $description = '';
    public $teacher_id = '';
    
    // Fee settings
    public $fee_amount = '';
    public $billing_cycle = 'monthly';
    public $is_recurring = true;
    
    // Class settings
    public $teaching_mode = 'online';
    public $billing_type = 'per_month';
    public $sessions_per_month = '';
    public $session_duration_minutes = 60;
    public $price_per_session = '';
    public $price_per_month = '';
    public $price_per_minute = '';
    public $class_description = '';
    public $class_instructions = '';

    public function nextStep(): void
    {
        $this->validate($this->getValidationRules());
        $this->step++;
    }

    public function previousStep(): void
    {
        $this->step--;
    }

    public function with(): array
    {
        return [
            'teachers' => Teacher::with('user')->where('status', 'active')->get(),
        ];
    }

    public function create(): void
    {
        $this->validate($this->getValidationRules());

        $course = Course::create([
            'name' => $this->name,
            'description' => $this->description,
            'teacher_id' => $this->teacher_id ?: null,
            'created_by' => Auth::id(),
        ]);

        CourseFeeSettings::create([
            'course_id' => $course->id,
            'fee_amount' => $this->fee_amount,
            'billing_cycle' => $this->billing_cycle,
            'is_recurring' => $this->is_recurring,
        ]);

        CourseClassSettings::create([
            'course_id' => $course->id,
            'teaching_mode' => $this->teaching_mode,
            'billing_type' => $this->billing_type,
            'sessions_per_month' => $this->sessions_per_month ?: null,
            'session_duration_minutes' => $this->session_duration_minutes,
            'price_per_session' => $this->price_per_session ?: null,
            'price_per_month' => $this->price_per_month ?: null,
            'price_per_minute' => $this->price_per_minute ?: null,
            'class_description' => $this->class_description,
            'class_instructions' => $this->class_instructions,
        ]);

        session()->flash('success', 'Course created successfully!');
        
        $this->redirect(route('courses.index'));
    }

    protected function getValidationRules(): array
    {
        $rules = [];

        if ($this->step >= 1) {
            $rules = array_merge($rules, [
                'name' => 'required|string|min:3|max:255',
                'description' => 'nullable|string|max:1000',
                'teacher_id' => 'nullable|exists:teachers,id',
            ]);
        }

        if ($this->step >= 2) {
            $rules = array_merge($rules, [
                'fee_amount' => 'required|numeric|min:0',
                'billing_cycle' => 'required|in:monthly,quarterly,yearly',
            ]);
        }

        if ($this->step >= 3) {
            $rules = array_merge($rules, [
                'teaching_mode' => 'required|in:online,offline,hybrid',
                'billing_type' => 'required|in:per_month,per_session,per_minute',
                'session_duration_minutes' => 'required|integer|min:5|max:480',
            ]);

            if ($this->billing_type === 'per_session') {
                $rules['sessions_per_month'] = 'required|integer|min:1';
                $rules['price_per_session'] = 'required|numeric|min:0';
            } elseif ($this->billing_type === 'per_month') {
                $rules['price_per_month'] = 'required|numeric|min:0';
            } elseif ($this->billing_type === 'per_minute') {
                $rules['price_per_minute'] = 'required|numeric|min:0';
            }
        }

        return $rules;
    }
}; ?>

<div>
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Create New Course</h1>
    </div>

    <div class="mt-6">
        <!-- Progress Steps -->
        <div class="flex items-center justify-center mb-8">
            <div class="flex items-center space-x-4">
                <div class="flex items-center">
                    <div class="w-8 h-8 rounded-full {{ $step >= 1 ? 'bg-blue-600' : 'bg-gray-300' }} flex items-center justify-center text-white text-sm font-medium">
                        1
                    </div>
                    <span class="ml-2 text-sm font-medium {{ $step >= 1 ? 'text-blue-600' : 'text-gray-500' }}">Course Info</span>
                </div>
                
                <div class="w-16 h-1 {{ $step >= 2 ? 'bg-blue-600' : 'bg-gray-300' }}"></div>
                
                <div class="flex items-center">
                    <div class="w-8 h-8 rounded-full {{ $step >= 2 ? 'bg-blue-600' : 'bg-gray-300' }} flex items-center justify-center text-white text-sm font-medium">
                        2
                    </div>
                    <span class="ml-2 text-sm font-medium {{ $step >= 2 ? 'text-blue-600' : 'text-gray-500' }}">Fee Settings</span>
                </div>
                
                <div class="w-16 h-1 {{ $step >= 3 ? 'bg-blue-600' : 'bg-gray-300' }}"></div>
                
                <div class="flex items-center">
                    <div class="w-8 h-8 rounded-full {{ $step >= 3 ? 'bg-blue-600' : 'bg-gray-300' }} flex items-center justify-center text-white text-sm font-medium">
                        3
                    </div>
                    <span class="ml-2 text-sm font-medium {{ $step >= 3 ? 'text-blue-600' : 'text-gray-500' }}">Class Settings</span>
                </div>
            </div>
        </div>

        <!-- Step 1: Course Basic Info -->
        @if($step === 1)
            <flux:card>
                <div class="space-y-6">
                    <flux:input wire:model="name" label="Course Name" placeholder="Enter course name" />
                    
                    <flux:textarea wire:model="description" label="Description" placeholder="Course description (optional)" rows="4" />

                    <flux:field>
                        <flux:label>Assign Teacher (Optional)</flux:label>
                        <flux:select wire:model="teacher_id" placeholder="Select a teacher">
                            @foreach($teachers as $teacher)
                                <flux:select.option value="{{ $teacher->id }}">{{ $teacher->user->name }} ({{ $teacher->teacher_id }})</flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:error name="teacher_id" />
                    </flux:field>

                    <div class="flex justify-end">
                        <flux:button wire:click="nextStep">Next</flux:button>
                    </div>
                </div>
            </flux:card>
        @endif

        <!-- Step 2: Fee Settings -->
        @if($step === 2)
            <flux:card>
                <div class="space-y-6">
                    <flux:input type="number" step="0.01" wire:model="fee_amount" label="Fee Amount (MYR)" placeholder="0.00" />

                    <flux:select wire:model="billing_cycle" label="Billing Cycle">
                        <flux:select.option value="monthly">Monthly</flux:select.option>
                        <flux:select.option value="quarterly">Quarterly</flux:select.option>
                        <flux:select.option value="yearly">Yearly</flux:select.option>
                    </flux:select>

                    <flux:field variant="inline">
                        <flux:checkbox wire:model="is_recurring" />
                        <flux:label>Enable recurring billing</flux:label>
                    </flux:field>

                    <div class="flex justify-between">
                        <flux:button variant="ghost" wire:click="previousStep">Previous</flux:button>
                        <flux:button wire:click="nextStep">Next</flux:button>
                    </div>
                </div>
            </flux:card>
        @endif

        <!-- Step 3: Class Settings -->
        @if($step === 3)
            <flux:card>
                <div class="space-y-6">
                    <flux:select wire:model="teaching_mode" label="Teaching Mode">
                        <flux:select.option value="online">Online</flux:select.option>
                        <flux:select.option value="offline">Offline</flux:select.option>
                        <flux:select.option value="hybrid">Hybrid</flux:select.option>
                    </flux:select>

                    <flux:select wire:model.live="billing_type" label="Billing Type">
                        <flux:select.option value="per_month">Per Month</flux:select.option>
                        <flux:select.option value="per_session">Per Session</flux:select.option>
                        <flux:select.option value="per_minute">Per Minute</flux:select.option>
                    </flux:select>

                    @if($billing_type === 'per_session')
                        <flux:input type="number" wire:model="sessions_per_month" label="Sessions Per Month" placeholder="4" />
                    @endif

                    <div class="grid grid-cols-2 gap-4">
                        <flux:input type="number" wire:model="session_duration_hours" label="Session Duration (Hours)" placeholder="1" />
                        <flux:input type="number" wire:model="session_duration_minutes" label="Session Duration (Minutes)" placeholder="30" />
                    </div>

                    @if($billing_type === 'per_session')
                        <flux:input type="number" step="0.01" wire:model="price_per_session" label="Price Per Session (MYR)" placeholder="0.00" />
                    @elseif($billing_type === 'per_month')
                        <flux:input type="number" step="0.01" wire:model="price_per_month" label="Price Per Month (MYR)" placeholder="0.00" />
                    @elseif($billing_type === 'per_minute')
                        <flux:input type="number" step="0.01" wire:model="price_per_minute" label="Price Per Minute (MYR)" placeholder="0.00" />
                    @endif

                    <flux:textarea wire:model="class_description" label="Class Description" placeholder="Describe what this class covers..." rows="3" />

                    <flux:textarea wire:model="class_instructions" label="Class Instructions" placeholder="Special instructions for students..." rows="3" />

                    <div class="flex justify-between">
                        <flux:button variant="ghost" wire:click="previousStep">Previous</flux:button>
                        <flux:button wire:click="create" variant="primary">Create Course</flux:button>
                    </div>
                </div>
            </flux:card>
        @endif
    </div>
</div>