<?php

use App\Models\Course;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public $search = '';

    public function with(): array
    {
        return [
            'courses' => Course::query()
                ->with(['creator', 'feeSettings', 'classSettings'])
                ->when($this->search, fn($query) => $query->where('name', 'like', '%' . $this->search . '%'))
                ->latest()
                ->paginate(10),
        ];
    }

    public function delete(Course $course): void
    {
        $course->delete();
        
        $this->dispatch('course-deleted');
    }

    public function toggleStatus(Course $course): void
    {
        $course->update([
            'status' => $course->status === 'active' ? 'inactive' : 'active'
        ]);
    }
}; ?>

<div>
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading size="xl">Courses</flux:heading>
            <flux:text class="mt-2">Manage course content and settings</flux:text>
        </div>
        <flux:button variant="primary" href="{{ route('courses.create') }}" icon="plus">
            Create Course
        </flux:button>
    </div>

    <div class="mt-6 space-y-6">
        <!-- Search -->
        <flux:card>
            <div class="p-6 border-b border-gray-200">
                <flux:input wire:model.live="search" placeholder="Search courses..." icon="magnifying-glass" />
            </div>
            
            <!-- Courses Table -->
            <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-300">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Course Name</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fee</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Teaching Mode</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created By</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse ($courses as $course)
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div>
                                    <div class="font-medium text-gray-900">{{ $course->name }}</div>
                                    @if($course->description)
                                        <div class="text-sm text-gray-500">{{ Str::limit($course->description, 50) }}</div>
                                    @endif
                                </div>
                            </td>
                            
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                @if($course->feeSettings)
                                    <div>{{ $course->feeSettings->formatted_fee }}</div>
                                    <div class="text-sm text-gray-500">{{ $course->feeSettings->billing_cycle_label }}</div>
                                @else
                                    <span class="text-gray-400">Not set</span>
                                @endif
                            </td>
                            
                            <td class="px-6 py-4 whitespace-nowrap">
                                @if($course->classSettings)
                                    <flux:badge size="sm" color="{{ $course->classSettings->teaching_mode === 'online' ? 'blue' : ($course->classSettings->teaching_mode === 'offline' ? 'green' : 'orange') }}">
                                        {{ $course->classSettings->teaching_mode_label }}
                                    </flux:badge>
                                @else
                                    <span class="text-gray-400">Not set</span>
                                @endif
                            </td>
                            
                            <td class="px-6 py-4 whitespace-nowrap">
                                <flux:badge size="sm" color="{{ $course->status === 'active' ? 'green' : 'red' }}">
                                    {{ ucfirst($course->status) }}
                                </flux:badge>
                            </td>
                            
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $course->creator->name }}</td>
                            
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <div class="flex gap-2">
                                    <flux:button size="sm" href="{{ route('courses.show', $course) }}" icon="eye">
                                        View
                                    </flux:button>
                                    
                                    <flux:button size="sm" href="{{ route('courses.edit', $course) }}" icon="pencil">
                                        Edit
                                    </flux:button>
                                    
                                    <flux:button 
                                        size="sm" 
                                        color="{{ $course->status === 'active' ? 'orange' : 'green' }}"
                                        wire:click="toggleStatus({{ $course->id }})"
                                    >
                                        {{ $course->status === 'active' ? 'Deactivate' : 'Activate' }}
                                    </flux:button>
                                    
                                    <flux:button 
                                        size="sm" 
                                        color="red" 
                                        wire:click="delete({{ $course->id }})"
                                        wire:confirm="Are you sure you want to delete this course?"
                                        icon="trash"
                                    >
                                        Delete
                                    </flux:button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-8 text-center text-gray-500">
                                No courses found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
            </div>
            
            @if($courses->hasPages())
                <div class="px-6 py-4 border-t border-gray-200">
                    {{ $courses->links() }}
                </div>
            @endif
        </flux:card>
    </div>
</div>