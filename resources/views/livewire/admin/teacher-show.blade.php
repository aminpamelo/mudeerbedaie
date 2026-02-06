<?php

use App\Models\Teacher;
use Livewire\Volt\Component;

new class extends Component {
    public Teacher $teacher;

    public function mount(Teacher $teacher): void
    {
        $this->teacher = $teacher->load(['user', 'courses.feeSettings', 'courses.enrollments']);
    }

    public function deleteTeacher(): void
    {
        $this->teacher->delete();
        
        session()->flash('success', 'Teacher deleted successfully.');
        $this->redirect(route('teachers.index'));
    }
};
?>

<div class="space-y-6">
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ $teacher->user->name }}</flux:heading>
            <flux:text class="mt-2">Teacher Details</flux:text>
        </div>
        <div class="flex space-x-3">
            <flux:button variant="primary" href="{{ route('teachers.edit', $teacher) }}">
                Edit Teacher
            </flux:button>
            <flux:button variant="ghost" href="{{ route('teachers.index') }}">
                Back to Teachers
            </flux:button>
        </div>
    </div>

    <!-- Teacher Information -->
    <div class="bg-white dark:bg-zinc-800 shadow rounded-lg">
        <div class="px-6 py-4 border-b border-gray-200 dark:border-zinc-700">
            <flux:heading size="lg">Teacher Information</flux:heading>
        </div>
        <div class="p-6">
            <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                <div>
                    <flux:text class="text-sm font-medium text-gray-500 dark:text-gray-400">Teacher ID</flux:text>
                    <flux:text class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $teacher->teacher_id }}</flux:text>
                </div>
                
                <div>
                    <flux:text class="text-sm font-medium text-gray-500 dark:text-gray-400">Status</flux:text>
                    <div class="mt-1">
                        <flux:badge :variant="$teacher->status === 'active' ? 'lime' : 'zinc'">
                            {{ ucfirst($teacher->status) }}
                        </flux:badge>
                    </div>
                </div>

                <div>
                    <flux:text class="text-sm font-medium text-gray-500 dark:text-gray-400">Full Name</flux:text>
                    <flux:text class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $teacher->user->name }}</flux:text>
                </div>

                <div>
                    <flux:text class="text-sm font-medium text-gray-500 dark:text-gray-400">Email Address</flux:text>
                    <flux:text class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $teacher->user->email }}</flux:text>
                </div>

                <div>
                    <flux:text class="text-sm font-medium text-gray-500 dark:text-gray-400">IC Number</flux:text>
                    <flux:text class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $teacher->ic_number ?? 'Not provided' }}</flux:text>
                </div>

                <div>
                    <flux:text class="text-sm font-medium text-gray-500 dark:text-gray-400">Phone Number</flux:text>
                    <flux:text class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $teacher->phone ?? 'Not provided' }}</flux:text>
                </div>

                <div>
                    <flux:text class="text-sm font-medium text-gray-500 dark:text-gray-400">Joined Date</flux:text>
                    <flux:text class="mt-1 text-sm text-gray-900 dark:text-gray-100">
                        {{ $teacher->joined_at ? $teacher->joined_at->format('F j, Y') : 'Not set' }}
                    </flux:text>
                </div>

                <div>
                    <flux:text class="text-sm font-medium text-gray-500 dark:text-gray-400">Account Created</flux:text>
                    <flux:text class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $teacher->created_at->format('F j, Y') }}</flux:text>
                </div>
            </div>
        </div>
    </div>

    <!-- Banking Information -->
    <div class="bg-white dark:bg-zinc-800 shadow rounded-lg">
        <div class="px-6 py-4 border-b border-gray-200 dark:border-zinc-700">
            <flux:heading size="lg">Banking Information</flux:heading>
        </div>
        <div class="p-6">
            @if($teacher->bank_account_holder || $teacher->bank_account_number || $teacher->bank_name)
                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                    <div>
                        <flux:text class="text-sm font-medium text-gray-500 dark:text-gray-400">Account Holder Name</flux:text>
                        <flux:text class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $teacher->bank_account_holder ?? 'Not provided' }}</flux:text>
                    </div>

                    <div>
                        <flux:text class="text-sm font-medium text-gray-500 dark:text-gray-400">Bank Name</flux:text>
                        <flux:text class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $teacher->bank_name ?? 'Not provided' }}</flux:text>
                    </div>

                    <div class="sm:col-span-2">
                        <flux:text class="text-sm font-medium text-gray-500 dark:text-gray-400">Account Number</flux:text>
                        <flux:text class="mt-1 text-sm text-gray-900 dark:text-gray-100 font-mono">
                            {{ $teacher->masked_account_number ?? 'Not provided' }}
                        </flux:text>
                        @if($teacher->bank_account_number)
                            <flux:text class="text-xs text-gray-400 dark:text-gray-500 mt-1">
                                Account number is encrypted in the database
                            </flux:text>
                        @endif
                    </div>
                </div>
            @else
                <div class="text-center py-8">
                    <flux:icon.credit-card class="mx-auto h-12 w-12 text-gray-300 dark:text-gray-600" />
                    <flux:text class="mt-2 text-lg font-medium text-gray-900 dark:text-gray-100">No banking information</flux:text>
                    <flux:text class="text-sm text-gray-500 dark:text-gray-400">No bank account details have been provided for this teacher.</flux:text>
                </div>
            @endif
        </div>
    </div>

    <!-- Assigned Courses -->
    <div class="bg-white dark:bg-zinc-800 shadow rounded-lg">
        <div class="px-6 py-4 border-b border-gray-200 dark:border-zinc-700">
            <flux:heading size="lg">Assigned Courses ({{ $teacher->courses->count() }})</flux:heading>
        </div>
        
        @if($teacher->courses->count() > 0)
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-zinc-700">
                    <thead class="bg-gray-50 dark:bg-zinc-700/50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Course Name</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Fee</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Students</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Created</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-zinc-800 divide-y divide-gray-200 dark:divide-zinc-700">
                        @foreach($teacher->courses as $course)
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div>
                                        <div class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $course->name }}</div>
                                        @if($course->description)
                                            <div class="text-sm text-gray-500 dark:text-gray-400">{{ Str::limit($course->description, 60) }}</div>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <flux:badge :variant="$course->status === 'active' ? 'lime' : 'zinc'">
                                        {{ ucfirst($course->status) }}
                                    </flux:badge>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                    {{ $course->formatted_fee }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                    {{ $course->enrollments->count() }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                    {{ $course->created_at->format('M j, Y') }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <a href="{{ route('courses.show', $course) }}" class="text-indigo-600 hover:text-indigo-900">
                                        View
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="text-center py-8">
                <flux:icon.academic-cap class="mx-auto h-12 w-12 text-gray-300 dark:text-gray-600" />
                <flux:text class="mt-2 text-lg font-medium text-gray-900 dark:text-gray-100">No courses assigned</flux:text>
                <flux:text class="text-sm text-gray-500 dark:text-gray-400">This teacher hasn't been assigned to any courses yet.</flux:text>
            </div>
        @endif
    </div>

    <!-- Danger Zone -->
    <div class="bg-white dark:bg-zinc-800 shadow rounded-lg border border-red-200 dark:border-red-900">
        <div class="px-6 py-4 border-b border-red-200 dark:border-red-900">
            <flux:heading size="lg" class="text-red-700">Danger Zone</flux:heading>
        </div>
        <div class="p-6">
            <div class="flex items-center justify-between">
                <div>
                    <flux:text class="font-medium text-red-700">Delete Teacher</flux:text>
                    <flux:text class="text-sm text-red-600">
                        This action cannot be undone. This will permanently delete the teacher and remove them from all assigned courses.
                    </flux:text>
                </div>
                <flux:button 
                    variant="danger" 
                    wire:click="deleteTeacher"
                    wire:confirm="Are you sure you want to delete this teacher? This action cannot be undone."
                >
                    Delete Teacher
                </flux:button>
            </div>
        </div>
    </div>
</div>