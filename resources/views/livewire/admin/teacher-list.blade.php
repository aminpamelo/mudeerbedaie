<?php

use App\Models\Teacher;
use App\Services\TeacherImportService;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;
    
    public $search = '';
    public $statusFilter = '';
    public $perPage = 10;
    
    public function updatingSearch()
    {
        $this->resetPage();
    }
    
    public function updatingStatusFilter()
    {
        $this->resetPage();
    }
    
    public function clearFilters()
    {
        $this->search = '';
        $this->statusFilter = '';
        $this->resetPage();
    }
    
    public function getTeachersProperty()
    {
        return Teacher::query()
            ->with('user')
            ->when($this->search, function ($query) {
                $query->whereHas('user', function ($userQuery) {
                    $userQuery->where('name', 'like', '%' . $this->search . '%')
                        ->orWhere('email', 'like', '%' . $this->search . '%');
                });
            })
            ->when($this->statusFilter, function ($query) {
                $query->where('status', $this->statusFilter);
            })
            ->paginate($this->perPage);
    }
    
    public function getTotalTeachersProperty()
    {
        return Teacher::count();
    }
    
    public function getActiveTeachersProperty()
    {
        return Teacher::where('status', 'active')->count();
    }

    public function exportTeachers(): void
    {
        // Store current filters in session for the download route
        session([
            'export_search' => $this->search,
            'export_status_filter' => $this->statusFilter
        ]);

        // Redirect to the download route
        $this->redirect(route('teachers.export'));
    }

    public function downloadSampleCsv(): void
    {
        // Redirect to the sample download route
        $this->redirect(route('teachers.sample-csv'));
    }
};

?>

<div>
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading size="xl">Teachers</flux:heading>
            <flux:text class="mt-2">Manage teachers in your system</flux:text>
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
                    <flux:menu.item wire:click="exportTeachers" icon="document-arrow-down">
                        Export Teachers (CSV)
                    </flux:menu.item>
                    <flux:menu.item href="{{ route('teachers.import') }}" icon="document-arrow-up">
                        Import Teachers
                    </flux:menu.item>
                    <flux:menu.item wire:click="downloadSampleCsv" icon="document-text">
                        Download Sample CSV
                    </flux:menu.item>
                </flux:menu>
            </flux:dropdown>
            <flux:button variant="primary" href="{{ route('teachers.create') }}" icon="user-plus">
                Add New Teacher
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
                        <p class="text-2xl font-semibold text-gray-900">{{ $this->totalTeachers }}</p>
                        <p class="text-sm text-gray-500">Total Teachers</p>
                    </div>
                </div>
            </flux:card>
            
            <flux:card class="p-6">
                <div class="flex items-center">
                    <div class="rounded-md bg-green-50 p-3">
                        <flux:icon.user-circle class="h-6 w-6 text-green-600" />
                    </div>
                    <div class="ml-4">
                        <p class="text-2xl font-semibold text-gray-900">{{ $this->activeTeachers }}</p>
                        <p class="text-sm text-gray-500">Active Teachers</p>
                    </div>
                </div>
            </flux:card>
        </div>

        <!-- Filters -->
        <flux:card>
            <div class="p-6">
                <div class="flex flex-col md:flex-row gap-4 items-end">
                    <div class="flex-1">
                        <flux:input 
                            wire:model.live="search" 
                            placeholder="Search teachers..."
                            icon="magnifying-glass"
                        />
                    </div>
                    
                    <div class="w-full md:w-48">
                        <flux:select wire:model.live="statusFilter">
                            <flux:select.option value="">All Status</flux:select.option>
                            <flux:select.option value="active">Active</flux:select.option>
                            <flux:select.option value="inactive">Inactive</flux:select.option>
                        </flux:select>
                    </div>
                    
                    <flux:button 
                        wire:click="clearFilters" 
                        variant="ghost" 
                        icon="x-mark"
                    >
                        Clear
                    </flux:button>
                </div>
            </div>
        </flux:card>

        <!-- Teachers List -->
        <flux:card>
            <div class="overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200">
                    <flux:heading size="lg">Teachers List</flux:heading>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500  uppercase tracking-wider">Teacher</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500  uppercase tracking-wider">Contact</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500  uppercase tracking-wider">Bank</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500  uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500  uppercase tracking-wider">Joined</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500  uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white  divide-y divide-gray-200">
                            @forelse ($this->teachers as $teacher)
                                <tr class="hover:bg-gray-50 :bg-gray-800 transition-colors duration-150">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center gap-3">
                                            <flux:avatar size="sm" :name="$teacher->fullName" />
                                            <div>
                                                <div class="font-medium text-gray-900">{{ $teacher->fullName }}</div>
                                                <div class="text-sm text-gray-500">
                                                    ID: {{ $teacher->teacher_id }}
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div>
                                            <div class="text-sm text-gray-900">{{ $teacher->email }}</div>
                                            @if($teacher->phone)
                                                <div class="text-sm text-gray-500">{{ $teacher->phone }}</div>
                                            @endif
                                        </div>
                                    </td>

                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900">
                                            @if($teacher->bank_name)
                                                <div class="flex items-center gap-2">
                                                    <flux:icon.credit-card class="h-4 w-4 text-gray-400" />
                                                    <span>{{ $teacher->bank_name }}</span>
                                                </div>
                                            @else
                                                <span class="text-gray-400">Not set</span>
                                            @endif
                                        </div>
                                    </td>
                                    
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <flux:badge 
                                            size="sm" 
                                            :color="$teacher->status === 'active' ? 'green' : 'red'"
                                        >
                                            {{ ucfirst($teacher->status) }}
                                        </flux:badge>
                                    </td>
                                    
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        @if($teacher->joined_at)
                                            {{ $teacher->joined_at->format('M d, Y') }}
                                        @else
                                            <span class="text-gray-400">N/A</span>
                                        @endif
                                    </td>
                                    
                                    <td class="px-6 py-4 whitespace-nowrap text-right">
                                        <div class="flex items-center justify-end gap-2">
                                            <flux:button 
                                                size="sm" 
                                                variant="ghost" 
                                                icon="eye"
                                                href="{{ route('teachers.show', $teacher) }}"
                                            />
                                            <flux:button 
                                                size="sm" 
                                                variant="ghost" 
                                                icon="pencil"
                                                href="{{ route('teachers.edit', $teacher) }}"
                                            />
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-6 py-12 text-center">
                                        <div class="text-gray-500">
                                            <flux:icon.users class="h-12 w-12 mx-auto mb-4 text-gray-300" />
                                            <p class="text-gray-600">No teachers found</p>
                                            @if($search || $statusFilter)
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
                @if($this->teachers->hasPages())
                    <div class="px-6 py-4 border-t border-gray-200">
                        {{ $this->teachers->links() }}
                    </div>
                @endif
            </div>
        </flux:card>
    </div>
</div>