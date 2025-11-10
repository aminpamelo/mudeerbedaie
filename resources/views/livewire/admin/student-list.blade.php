<?php

use App\Models\Student;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    public $search = '';

    public $statusFilter = '';

    public $perPage = 20;

    public function mount(): void
    {
        //
    }

    public function with(): array
    {
        $query = Student::query()
            ->with(['user', 'activeEnrollments'])
            ->orderBy('created_at', 'desc');

        if ($this->search) {
            $query->whereHas('user', function ($q) {
                $q->where('name', 'like', '%'.$this->search.'%')
                    ->orWhere('email', 'like', '%'.$this->search.'%');
            })
                ->orWhere('student_id', 'like', '%'.$this->search.'%')
                ->orWhere('ic_number', 'like', '%'.$this->search.'%')
                ->orWhere('phone', 'like', '%'.$this->search.'%');
        }

        if ($this->statusFilter) {
            $query->where('status', $this->statusFilter);
        }

        return [
            'students' => $query->paginate($this->perPage),
            'totalStudents' => Student::count(),
            'activeStudents' => Student::where('status', 'active')->count(),
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

    public function updatingPerPage(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->search = '';
        $this->statusFilter = '';
        $this->resetPage();
    }

    public function exportStudents(): void
    {
        // Store current filters in session for the download route
        session([
            'export_search' => $this->search,
            'export_status_filter' => $this->statusFilter,
        ]);

        // Redirect to the download route
        $this->redirect(route('students.export'));
    }

    public function downloadSampleCsv(): void
    {
        // Redirect to the sample download route
        $this->redirect(route('students.sample-csv'));
    }
}; ?>

<div>
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading size="xl">Students</flux:heading>
            <flux:text class="mt-2">Manage student profiles and information</flux:text>
        </div>
        <div class="flex space-x-3">
            <flux:dropdown>
                <flux:button variant="outline" icon="document-arrow-down">
                    <div class="flex items-center justify-center">
                        <flux:icon name="document-arrow-down" class="w-4 h-4 mr-1" />
                        Export/Import
                    </div>
                </flux:button>
                <flux:menu>
                    <flux:menu.item wire:click="exportStudents" icon="document-arrow-down">
                        Export Students (CSV)
                    </flux:menu.item>
                    <flux:menu.item href="{{ route('students.import') }}" icon="document-arrow-up">
                        Import Students
                    </flux:menu.item>
                    <flux:menu.item wire:click="downloadSampleCsv" icon="document-text">
                        Download Sample CSV
                    </flux:menu.item>
                </flux:menu>
            </flux:dropdown>
            <flux:button variant="primary" href="{{ route('students.create') }}" icon="user-plus">
                Add New Student
            </flux:button>
        </div>
    </div>

    <div class="mt-6 space-y-6">
        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <flux:card class="p-6">
                <div class="flex items-center">
                    <div class="rounded-md bg-blue-50 p-3">
                        <flux:icon.users class="h-6 w-6 text-blue-600" />
                    </div>
                    <div class="ml-4">
                        <p class="text-2xl font-semibold text-gray-900">{{ $totalStudents }}</p>
                        <p class="text-sm text-gray-500">Total Students</p>
                    </div>
                </div>
            </flux:card>
            
            <flux:card class="p-6">
                <div class="flex items-center">
                    <div class="rounded-md bg-green-50 p-3">
                        <flux:icon.user-circle class="h-6 w-6 text-green-600" />
                    </div>
                    <div class="ml-4">
                        <p class="text-2xl font-semibold text-gray-900">{{ $activeStudents }}</p>
                        <p class="text-sm text-gray-500">Active Students</p>
                    </div>
                </div>
            </flux:card>
        </div>

        <!-- Search and Filters -->
        <flux:card>
            <div class="p-6 border-b border-gray-200">
                <div class="flex flex-col sm:flex-row gap-4">
                    <div class="flex-1">
                        <flux:input
                            wire:model.live.debounce.300ms="search"
                            placeholder="Search students by name, email, student ID, IC number, or phone..."
                            icon="magnifying-glass" />
                    </div>
                    <div class="w-full sm:w-48">
                        <flux:select wire:model.live="statusFilter" placeholder="Filter by status">
                            <flux:select.option value="">All Statuses</flux:select.option>
                            <flux:select.option value="active">Active</flux:select.option>
                            <flux:select.option value="inactive">Inactive</flux:select.option>
                            <flux:select.option value="graduated">Graduated</flux:select.option>
                            <flux:select.option value="suspended">Suspended</flux:select.option>
                        </flux:select>
                    </div>
                    <div class="w-full sm:w-40">
                        <flux:select wire:model.live="perPage">
                            <flux:select.option value="20">20 per page</flux:select.option>
                            <flux:select.option value="30">30 per page</flux:select.option>
                            <flux:select.option value="50">50 per page</flux:select.option>
                            <flux:select.option value="100">100 per page</flux:select.option>
                            <flux:select.option value="200">200 per page</flux:select.option>
                            <flux:select.option value="300">300 per page</flux:select.option>
                        </flux:select>
                    </div>
                    @if($search || $statusFilter)
                        <flux:button wire:click="clearFilters" variant="ghost">
                            Clear Filters
                        </flux:button>
                    @endif
                </div>
            </div>

            <!-- Students Table -->
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Student</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Student ID</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">IC Number</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Contact</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Active Enrollments</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse($students as $student)
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <flux:avatar size="sm" class="mr-3">
                                            {{ $student->user->initials() }}
                                        </flux:avatar>
                                        <div>
                                            <div class="text-sm font-medium text-gray-900">{{ $student->user->name }}</div>
                                            <div class="text-sm text-gray-500">{{ $student->user->email }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900">{{ $student->student_id }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">{{ $student->ic_number ?? 'N/A' }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">{{ $student->phone ?? 'N/A' }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900">{{ $student->activeEnrollments->count() }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <flux:badge :class="match($student->status) {
                                        'active' => 'badge-green',
                                        'inactive' => 'badge-gray',
                                        'graduated' => 'badge-blue',
                                        'suspended' => 'badge-red',
                                        default => 'badge-gray'
                                    }">
                                        {{ ucfirst($student->status) }}
                                    </flux:badge>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <div class="flex space-x-2">
                                        <flux:button size="sm" variant="ghost" href="{{ route('students.show', $student) }}">
                                            View
                                        </flux:button>
                                        <flux:button size="sm" variant="ghost" href="{{ route('students.edit', $student) }}">
                                            Edit
                                        </flux:button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-6 py-12 text-center">
                                    <flux:icon.users class="mx-auto h-12 w-12 text-gray-400" />
                                    <h3 class="mt-2 text-sm font-medium text-gray-900">No students found</h3>
                                    <p class="mt-1 text-sm text-gray-500">
                                        @if($search || $statusFilter)
                                            Try adjusting your search or filter criteria.
                                        @else
                                            Get started by adding your first student.
                                        @endif
                                    </p>
                                    @if(!$search && !$statusFilter)
                                        <div class="mt-6">
                                            <flux:button variant="primary" href="{{ route('students.create') }}">
                                                Add New Student
                                            </flux:button>
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($students->hasPages())
                <div class="px-6 py-4 border-t border-gray-200">
                    {{ $students->links() }}
                </div>
            @endif
        </flux:card>
    </div>
</div>