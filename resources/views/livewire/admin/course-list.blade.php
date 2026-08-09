<?php

use App\Models\Course;
use App\Models\Enrollment;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public string $search = '';
    public string $statusFilter = '';
    public string $modeFilter = '';
    public int $perPage = 20;

    public function with(): array
    {
        $query = Course::query()
            ->with(['creator', 'feeSettings', 'classSettings'])
            ->withCount(['enrollments', 'activeEnrollments', 'classes'])
            ->when($this->search, fn ($q) => $q->where('name', 'like', '%' . $this->search . '%'))
            ->when($this->statusFilter, fn ($q) => $q->where('status', $this->statusFilter))
            ->when($this->modeFilter, fn ($q) => $q->whereHas('classSettings', fn ($cs) => $cs->where('teaching_mode', $this->modeFilter)))
            ->latest();

        return [
            'courses' => $query->paginate($this->perPage),
            'totalCourses' => Course::count(),
            'activeCourses' => Course::where('status', 'active')->count(),
            'totalEnrollments' => Enrollment::whereIn('status', ['enrolled', 'active'])->count(),
            'totalClasses' => \App\Models\ClassModel::count(),
        ];
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatingModeFilter(): void
    {
        $this->resetPage();
    }

    public function updatingPerPage(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->search = '';
        $this->statusFilter = '';
        $this->modeFilter = '';
        $this->resetPage();
    }

    public function delete(Course $course): void
    {
        $course->delete();
        $this->dispatch('course-deleted');
    }

    public function toggleStatus(Course $course): void
    {
        $course->update([
            'status' => $course->status === 'active' ? 'inactive' : 'active',
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
        <!-- Stats Cards -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            <flux:card class="p-6">
                <div class="flex items-center">
                    <div class="rounded-md bg-blue-50 dark:bg-blue-900/30 p-3">
                        <flux:icon.book-open class="h-6 w-6 text-blue-600 dark:text-blue-400" />
                    </div>
                    <div class="ml-4">
                        <p class="text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ $totalCourses }}</p>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Total Courses</p>
                    </div>
                </div>
            </flux:card>

            <flux:card class="p-6">
                <div class="flex items-center">
                    <div class="rounded-md bg-green-50 dark:bg-green-900/30 p-3">
                        <flux:icon.check-circle class="h-6 w-6 text-green-600 dark:text-green-400" />
                    </div>
                    <div class="ml-4">
                        <p class="text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ $activeCourses }}</p>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Active</p>
                    </div>
                </div>
            </flux:card>

            <flux:card class="p-6">
                <div class="flex items-center">
                    <div class="rounded-md bg-purple-50 dark:bg-purple-900/30 p-3">
                        <flux:icon.users class="h-6 w-6 text-purple-600 dark:text-purple-400" />
                    </div>
                    <div class="ml-4">
                        <p class="text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ $totalEnrollments }}</p>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Enrollments</p>
                    </div>
                </div>
            </flux:card>

            <flux:card class="p-6">
                <div class="flex items-center">
                    <div class="rounded-md bg-amber-50 dark:bg-amber-900/30 p-3">
                        <flux:icon.academic-cap class="h-6 w-6 text-amber-600 dark:text-amber-400" />
                    </div>
                    <div class="ml-4">
                        <p class="text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ $totalClasses }}</p>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Classes</p>
                    </div>
                </div>
            </flux:card>
        </div>

        <!-- Search and Filters -->
        <flux:card>
            <div class="p-6 border-b border-gray-200 dark:border-zinc-700">
                <div class="flex flex-col sm:flex-row gap-4">
                    <div class="flex-1">
                        <flux:input
                            wire:model.live.debounce.300ms="search"
                            placeholder="Search by course name..."
                            icon="magnifying-glass"
                            autocomplete="off" />
                    </div>
                    <div class="w-full sm:w-44">
                        <flux:select wire:model.live="statusFilter" placeholder="Status">
                            <flux:select.option value="">All Statuses</flux:select.option>
                            <flux:select.option value="active">Active</flux:select.option>
                            <flux:select.option value="inactive">Inactive</flux:select.option>
                        </flux:select>
                    </div>
                    <div class="w-full sm:w-44">
                        <flux:select wire:model.live="modeFilter" placeholder="Teaching Mode">
                            <flux:select.option value="">All Modes</flux:select.option>
                            <flux:select.option value="online">Online</flux:select.option>
                            <flux:select.option value="offline">Offline</flux:select.option>
                            <flux:select.option value="hybrid">Hybrid</flux:select.option>
                        </flux:select>
                    </div>
                    <div class="w-full sm:w-40">
                        <flux:select wire:model.live="perPage">
                            <flux:select.option value="20">20 per page</flux:select.option>
                            <flux:select.option value="50">50 per page</flux:select.option>
                            <flux:select.option value="100">100 per page</flux:select.option>
                        </flux:select>
                    </div>
                </div>

                <!-- Active Filters Display -->
                @if($search || $statusFilter || $modeFilter)
                    <div class="mt-4 flex flex-wrap items-center gap-2">
                        <span class="text-sm text-gray-500 dark:text-gray-400">Active filters:</span>

                        @if($search)
                            <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-sm bg-blue-100 dark:bg-blue-900/50 text-blue-800 dark:text-blue-300">
                                <flux:icon name="magnifying-glass" class="w-3 h-3" />
                                Search: "{{ $search }}"
                                <button wire:click="$set('search', '')" class="ml-1 hover:text-blue-600 dark:hover:text-blue-400">
                                    <flux:icon name="x-mark" class="w-3 h-3" />
                                </button>
                            </span>
                        @endif

                        @if($statusFilter)
                            <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-sm bg-purple-100 dark:bg-purple-900/50 text-purple-800 dark:text-purple-300">
                                <flux:icon name="funnel" class="w-3 h-3" />
                                Status: {{ ucfirst($statusFilter) }}
                                <button wire:click="$set('statusFilter', '')" class="ml-1 hover:text-purple-600 dark:hover:text-purple-400">
                                    <flux:icon name="x-mark" class="w-3 h-3" />
                                </button>
                            </span>
                        @endif

                        @if($modeFilter)
                            <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-sm bg-amber-100 dark:bg-amber-900/50 text-amber-800 dark:text-amber-300">
                                <flux:icon name="funnel" class="w-3 h-3" />
                                Mode: {{ ucfirst($modeFilter) }}
                                <button wire:click="$set('modeFilter', '')" class="ml-1 hover:text-amber-600 dark:hover:text-amber-400">
                                    <flux:icon name="x-mark" class="w-3 h-3" />
                                </button>
                            </span>
                        @endif

                        <button wire:click="clearFilters" class="text-sm text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 underline">
                            Clear all
                        </button>
                    </div>
                @endif
            </div>

            <!-- Results Count -->
            <div class="px-6 py-3 bg-gray-50 dark:bg-zinc-700/50 border-b border-gray-200 dark:border-zinc-700">
                <p class="text-sm text-gray-600 dark:text-gray-300">
                    @if($search || $statusFilter || $modeFilter)
                        Showing <span class="font-medium">{{ $courses->total() }}</span> results
                        @if($courses->total() !== $totalCourses)
                            out of <span class="font-medium">{{ $totalCourses }}</span> courses
                        @endif
                    @else
                        Showing <span class="font-medium">{{ $courses->total() }}</span> courses
                    @endif
                </p>
            </div>

            <!-- Courses Table -->
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-zinc-700">
                    <thead class="bg-gray-50 dark:bg-zinc-700/50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Course</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Fee</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Mode</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Students</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Classes</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-zinc-800 divide-y divide-gray-200 dark:divide-zinc-700">
                        @forelse ($courses as $course)
                            <tr wire:key="course-{{ $course->id }}" class="hover:bg-gray-50 dark:hover:bg-zinc-700/50 transition-colors">
                                <td class="px-6 py-4">
                                    <div class="flex items-center">
                                        <div class="shrink-0 h-10 w-10 rounded-lg bg-blue-50 dark:bg-blue-900/30 flex items-center justify-center">
                                            <flux:icon.book-open class="h-5 w-5 text-blue-600 dark:text-blue-400" />
                                        </div>
                                        <div class="ml-3 min-w-0">
                                            <a href="{{ route('courses.show', $course) }}" class="text-sm font-medium text-gray-900 dark:text-gray-100 hover:text-blue-600 dark:hover:text-blue-400 transition-colors">
                                                {{ $course->name }}
                                            </a>
                                            @if($course->description)
                                                <p class="text-xs text-gray-500 dark:text-gray-400 truncate max-w-xs">{{ Str::limit($course->description, 60) }}</p>
                                            @endif
                                            <p class="text-xs text-gray-400 dark:text-gray-500">by {{ $course->creator?->name ?? 'Unknown' }}</p>
                                        </div>
                                    </div>
                                </td>

                                <td class="px-6 py-4 whitespace-nowrap">
                                    @if($course->feeSettings)
                                        <div class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $course->feeSettings->formatted_fee }}</div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">{{ $course->feeSettings->billing_cycle_label }}</div>
                                    @else
                                        <span class="text-sm text-gray-400 dark:text-gray-500">Not set</span>
                                    @endif
                                </td>

                                <td class="px-6 py-4 whitespace-nowrap">
                                    @if($course->classSettings)
                                        <flux:badge size="sm" color="{{ match($course->classSettings->teaching_mode) { 'online' => 'blue', 'offline' => 'green', default => 'orange' } }}">
                                            {{ $course->classSettings->teaching_mode_label }}
                                        </flux:badge>
                                    @else
                                        <span class="text-sm text-gray-400 dark:text-gray-500">-</span>
                                    @endif
                                </td>

                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <span class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $course->active_enrollments_count }}</span>
                                    @if($course->enrollments_count > $course->active_enrollments_count)
                                        <span class="text-xs text-gray-400 dark:text-gray-500">/ {{ $course->enrollments_count }}</span>
                                    @endif
                                </td>

                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <span class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $course->classes_count }}</span>
                                </td>

                                <td class="px-6 py-4 whitespace-nowrap">
                                    <flux:badge size="sm" color="{{ $course->status === 'active' ? 'green' : 'red' }}">
                                        {{ ucfirst($course->status) }}
                                    </flux:badge>
                                </td>

                                <td class="px-6 py-4 whitespace-nowrap text-right">
                                    <flux:dropdown>
                                        <flux:button size="sm" variant="ghost" icon="ellipsis-vertical" />
                                        <flux:menu>
                                            <flux:menu.item href="{{ route('courses.show', $course) }}" icon="eye">
                                                View
                                            </flux:menu.item>
                                            <flux:menu.item href="{{ route('courses.edit', $course) }}" icon="pencil">
                                                Edit
                                            </flux:menu.item>
                                            <flux:menu.separator />
                                            <flux:menu.item
                                                wire:click="toggleStatus({{ $course->id }})"
                                                icon="{{ $course->status === 'active' ? 'x-circle' : 'check-circle' }}"
                                            >
                                                {{ $course->status === 'active' ? 'Deactivate' : 'Activate' }}
                                            </flux:menu.item>
                                            <flux:menu.item
                                                wire:click="delete({{ $course->id }})"
                                                wire:confirm="Are you sure you want to delete this course? This action cannot be undone."
                                                icon="trash"
                                                variant="danger"
                                            >
                                                Delete
                                            </flux:menu.item>
                                        </flux:menu>
                                    </flux:dropdown>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-6 py-12 text-center">
                                    <flux:icon.book-open class="mx-auto h-12 w-12 text-gray-400 dark:text-gray-500" />
                                    <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-gray-100">No courses found</h3>
                                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                        @if($search || $statusFilter || $modeFilter)
                                            Try adjusting your search or filter criteria.
                                        @else
                                            Get started by creating your first course.
                                        @endif
                                    </p>
                                    @if(!$search && !$statusFilter && !$modeFilter)
                                        <div class="mt-6">
                                            <flux:button variant="primary" href="{{ route('courses.create') }}" icon="plus">
                                                Create Course
                                            </flux:button>
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($courses->hasPages())
                <div class="px-6 py-4 border-t border-gray-200 dark:border-zinc-700">
                    {{ $courses->links() }}
                </div>
            @endif
        </flux:card>
    </div>
</div>
