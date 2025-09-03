<?php

use App\Models\Course;
use Livewire\Volt\Component;

new class extends Component {
    public Course $course;

    public function mount(): void
    {
        $this->course->load(['feeSettings', 'classSettings', 'creator']);
    }
}; ?>

<div>
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ $course->name }}</flux:heading>
            <flux:text class="mt-2">Course details and enrollment information</flux:text>
        </div>
        <div class="flex space-x-3">
            <flux:button href="{{ route('courses.index') }}" variant="ghost">
                Back to Courses
            </flux:button>
            <flux:button href="{{ route('courses.edit', $course) }}" variant="primary" icon="pencil">
                Edit Course
            </flux:button>
        </div>
    </div>

    <div class="mt-6 space-y-8">
        <!-- Course Status -->
        <div class="flex items-center gap-4">
            <flux:badge size="lg" color="{{ $course->status === 'active' ? 'green' : ($course->status === 'inactive' ? 'red' : 'gray') }}">
                {{ ucfirst($course->status) }}
            </flux:badge>
            <div class="text-sm text-gray-500">
                Created {{ $course->created_at->format('M j, Y') }} by {{ $course->creator->name }}
            </div>
        </div>

        <!-- Course Basic Information -->
        <flux:card>
            <flux:heading size="lg">Course Information</flux:heading>
            
            <div class="mt-6 space-y-4">
                <div>
                    <flux:label>Course Name</flux:label>
                    <div class="mt-1 text-gray-900">{{ $course->name }}</div>
                </div>

                @if($course->description)
                    <div>
                        <flux:label>Description</flux:label>
                        <div class="mt-1 text-gray-900">{{ $course->description }}</div>
                    </div>
                @endif
            </div>
        </flux:card>

        <!-- Fee Settings -->
        <flux:card>
            <flux:heading size="lg">Fee Settings</flux:heading>
            
            <div class="mt-6">
                @if($course->feeSettings)
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div>
                            <flux:label>Fee Amount</flux:label>
                            <div class="mt-1 text-2xl font-semibold text-gray-900">
                                {{ $course->feeSettings->formatted_fee }}
                            </div>
                        </div>
                        
                        <div>
                            <flux:label>Billing Cycle</flux:label>
                            <div class="mt-1 text-gray-900">
                                {{ $course->feeSettings->billing_cycle_label }}
                            </div>
                        </div>
                        
                        <div>
                            <flux:label>Recurring Billing</flux:label>
                            <div class="mt-1">
                                <flux:badge color="{{ $course->feeSettings->is_recurring ? 'green' : 'red' }}">
                                    {{ $course->feeSettings->is_recurring ? 'Enabled' : 'Disabled' }}
                                </flux:badge>
                            </div>
                        </div>
                    </div>
                @else
                    <div class="text-gray-500 italic">
                        No fee settings configured for this course.
                    </div>
                @endif
            </div>
        </flux:card>

        <!-- Class Settings -->
        <flux:card>
            <flux:heading size="lg">Class Settings</flux:heading>
            
            <div class="mt-6">
                @if($course->classSettings)
                    <div class="space-y-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <flux:label>Teaching Mode</flux:label>
                                <div class="mt-1">
                                    <flux:badge color="{{ $course->classSettings->teaching_mode === 'online' ? 'blue' : ($course->classSettings->teaching_mode === 'offline' ? 'green' : 'orange') }}">
                                        {{ $course->classSettings->teaching_mode_label }}
                                    </flux:badge>
                                </div>
                            </div>
                            
                            <div>
                                <flux:label>Billing Type</flux:label>
                                <div class="mt-1 text-gray-900">
                                    {{ $course->classSettings->billing_type_label }}
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <flux:label>Session Duration</flux:label>
                                <div class="mt-1 text-gray-900">
                                    {{ $course->classSettings->formatted_duration }}
                                </div>
                            </div>

                            @if($course->classSettings->sessions_per_month)
                                <div>
                                    <flux:label>Sessions Per Month</flux:label>
                                    <div class="mt-1 text-gray-900">{{ $course->classSettings->sessions_per_month }}</div>
                                </div>
                            @endif
                        </div>

                        <!-- Pricing Information -->
                        <div class="border-t pt-6">
                            <flux:heading size="md">Pricing Details</flux:heading>
                            <div class="mt-4 grid grid-cols-1 md:grid-cols-3 gap-6">
                                @if($course->classSettings->price_per_session)
                                    <div>
                                        <flux:label>Price Per Session</flux:label>
                                        <div class="mt-1 text-lg font-semibold text-gray-900">
                                            RM {{ number_format($course->classSettings->price_per_session, 2) }}
                                        </div>
                                    </div>
                                @endif

                                @if($course->classSettings->price_per_month)
                                    <div>
                                        <flux:label>Price Per Month</flux:label>
                                        <div class="mt-1 text-lg font-semibold text-gray-900">
                                            RM {{ number_format($course->classSettings->price_per_month, 2) }}
                                        </div>
                                    </div>
                                @endif

                                @if($course->classSettings->price_per_minute)
                                    <div>
                                        <flux:label>Price Per Minute</flux:label>
                                        <div class="mt-1 text-lg font-semibold text-gray-900">
                                            RM {{ number_format($course->classSettings->price_per_minute, 2) }}
                                        </div>
                                    </div>
                                @endif
                            </div>
                        </div>

                        <!-- Class Description and Instructions -->
                        @if($course->classSettings->class_description || $course->classSettings->class_instructions)
                            <div class="border-t pt-6 space-y-4">
                                @if($course->classSettings->class_description)
                                    <div>
                                        <flux:label>Class Description</flux:label>
                                        <div class="mt-1 text-gray-900">{{ $course->classSettings->class_description }}</div>
                                    </div>
                                @endif

                                @if($course->classSettings->class_instructions)
                                    <div>
                                        <flux:label>Class Instructions</flux:label>
                                        <div class="mt-1 text-gray-900">{{ $course->classSettings->class_instructions }}</div>
                                    </div>
                                @endif
                            </div>
                        @endif
                    </div>
                @else
                    <div class="text-gray-500 italic">
                        No class settings configured for this course.
                    </div>
                @endif
            </div>
        </flux:card>
    </div>
</div>