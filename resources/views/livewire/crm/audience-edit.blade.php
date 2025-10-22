<?php

use App\Models\Audience;
use App\Models\Student;
use Livewire\Volt\Component;

new class extends Component {
    public Audience $audience;
    public $name = '';
    public $description = '';
    public $status = 'active';
    public $selectedStudents = [];

    // Filter and search properties
    public $studentSearch = '';
    public $statusFilter = '';
    public $countryFilter = '';
    public $hasOrdersFilter = '';

    public function mount(Audience $audience): void
    {
        $this->audience = $audience;
        $this->name = $audience->name;
        $this->description = $audience->description;
        $this->status = $audience->status;
        $this->selectedStudents = $audience->students()->pluck('students.id')->toArray();
    }

    public function with(): array
    {
        $query = Student::query()->with(['user', 'paidOrders']);

        // Apply search
        if ($this->studentSearch) {
            $query->whereHas('user', function($q) {
                $q->where('name', 'like', '%' . $this->studentSearch . '%')
                  ->orWhere('email', 'like', '%' . $this->studentSearch . '%');
            })
            ->orWhere('student_id', 'like', '%' . $this->studentSearch . '%')
            ->orWhere('phone', 'like', '%' . $this->studentSearch . '%');
        }

        // Apply status filter
        if ($this->statusFilter) {
            $query->where('status', $this->statusFilter);
        }

        // Apply country filter
        if ($this->countryFilter) {
            $query->where('country', $this->countryFilter);
        }

        // Apply has orders filter
        if ($this->hasOrdersFilter === 'yes') {
            $query->has('paidOrders');
        } elseif ($this->hasOrdersFilter === 'no') {
            $query->doesntHave('paidOrders');
        }

        $students = $query->orderBy('created_at', 'desc')->get();

        return [
            'students' => $students,
            'countries' => Student::distinct()->whereNotNull('country')->pluck('country')->filter(),
            'filteredCount' => $students->count(),
            'totalCount' => Student::count(),
        ];
    }

    public function save(): void
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'required|in:active,inactive',
        ]);

        $this->audience->update([
            'name' => $this->name,
            'description' => $this->description,
            'status' => $this->status,
        ]);

        $this->audience->students()->sync($this->selectedStudents);

        session()->flash('message', 'Audience updated successfully.');
        $this->redirect(route('crm.audiences.index'));
    }

    public function selectAll(): void
    {
        $query = Student::query()->with(['user', 'paidOrders']);

        // Apply same filters as with()
        if ($this->studentSearch) {
            $query->whereHas('user', function($q) {
                $q->where('name', 'like', '%' . $this->studentSearch . '%')
                  ->orWhere('email', 'like', '%' . $this->studentSearch . '%');
            })
            ->orWhere('student_id', 'like', '%' . $this->studentSearch . '%')
            ->orWhere('phone', 'like', '%' . $this->studentSearch . '%');
        }

        if ($this->statusFilter) {
            $query->where('status', $this->statusFilter);
        }

        if ($this->countryFilter) {
            $query->where('country', $this->countryFilter);
        }

        if ($this->hasOrdersFilter === 'yes') {
            $query->has('paidOrders');
        } elseif ($this->hasOrdersFilter === 'no') {
            $query->doesntHave('paidOrders');
        }

        $this->selectedStudents = $query->pluck('id')->toArray();
    }

    public function deselectAll(): void
    {
        $this->selectedStudents = [];
    }

    public function clearFilters(): void
    {
        $this->studentSearch = '';
        $this->statusFilter = '';
        $this->countryFilter = '';
        $this->hasOrdersFilter = '';
    }

}; ?>

<div>
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading size="xl">Edit Audience</flux:heading>
            <flux:text class="mt-2">Update audience segment details</flux:text>
        </div>
        <flux:button variant="outline" href="{{ route('crm.audiences.index') }}">
            <div class="flex items-center justify-center">
                <flux:icon name="chevron-left" class="w-4 h-4 mr-1" />
                Back to Audiences
            </div>
        </flux:button>
    </div>

    <form wire:submit="save">
        <flux:card class="space-y-6">
            <div class="p-6 space-y-6">
                <div>
                    <flux:field>
                        <flux:label>Name</flux:label>
                        <flux:input wire:model="name" placeholder="Enter audience name" />
                        <flux:error name="name" />
                    </flux:field>
                </div>

                <div>
                    <flux:field>
                        <flux:label>Description</flux:label>
                        <flux:textarea wire:model="description" placeholder="Enter audience description (optional)" rows="3" />
                        <flux:error name="description" />
                    </flux:field>
                </div>

                <div>
                    <flux:field>
                        <flux:label>Status</flux:label>
                        <flux:select wire:model="status">
                            <flux:select.option value="active">Active</flux:select.option>
                            <flux:select.option value="inactive">Inactive</flux:select.option>
                        </flux:select>
                        <flux:error name="status" />
                    </flux:field>
                </div>

                <div>
                    <flux:field>
                        <flux:label>Select Students ({{ count($selectedStudents) }} of {{ $filteredCount }} selected)</flux:label>

                        <!-- Filter Section -->
                        <div class="mt-2 p-4 bg-gray-50 border border-gray-300 rounded-md space-y-4">
                            <div class="flex items-center justify-between">
                                <flux:text class="font-semibold">Filter Students</flux:text>
                                @if($studentSearch || $statusFilter || $countryFilter || $hasOrdersFilter)
                                    <flux:button variant="ghost" size="sm" wire:click="clearFilters">
                                        Clear Filters
                                    </flux:button>
                                @endif
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                                <!-- Search -->
                                <div>
                                    <flux:input
                                        wire:model.live.debounce.300ms="studentSearch"
                                        placeholder="Search students..."
                                        icon="magnifying-glass" />
                                </div>

                                <!-- Status Filter -->
                                <div>
                                    <flux:select wire:model.live="statusFilter" placeholder="All Statuses">
                                        <flux:select.option value="">All Statuses</flux:select.option>
                                        <flux:select.option value="active">Active</flux:select.option>
                                        <flux:select.option value="inactive">Inactive</flux:select.option>
                                        <flux:select.option value="graduated">Graduated</flux:select.option>
                                        <flux:select.option value="suspended">Suspended</flux:select.option>
                                    </flux:select>
                                </div>

                                <!-- Country Filter -->
                                <div>
                                    <flux:select wire:model.live="countryFilter" placeholder="All Countries">
                                        <flux:select.option value="">All Countries</flux:select.option>
                                        @foreach($countries as $country)
                                            <flux:select.option value="{{ $country }}">{{ $country }}</flux:select.option>
                                        @endforeach
                                    </flux:select>
                                </div>

                                <!-- Has Orders Filter -->
                                <div>
                                    <flux:select wire:model.live="hasOrdersFilter" placeholder="All Students">
                                        <flux:select.option value="">All Students</flux:select.option>
                                        <flux:select.option value="yes">With Orders</flux:select.option>
                                        <flux:select.option value="no">Without Orders</flux:select.option>
                                    </flux:select>
                                </div>
                            </div>

                            <!-- Results Count -->
                            <div class="flex items-center justify-between text-sm text-gray-600">
                                <span>Showing {{ $filteredCount }} of {{ $totalCount }} students</span>
                                <div class="flex gap-2">
                                    <flux:button variant="outline" size="sm" wire:click="selectAll">
                                        Select All ({{ $filteredCount }})
                                    </flux:button>
                                    <flux:button variant="outline" size="sm" wire:click="deselectAll">
                                        Deselect All
                                    </flux:button>
                                </div>
                            </div>
                        </div>

                        <!-- Student List -->
                        <div class="mt-2 max-h-96 overflow-y-auto border border-gray-300 rounded-md p-4 space-y-2">
                            @forelse($students as $student)
                                <div class="flex items-center p-2 hover:bg-gray-50 rounded">
                                    <flux:checkbox
                                        wire:model.live="selectedStudents"
                                        value="{{ $student->id }}"
                                        id="student-{{ $student->id }}"
                                    />
                                    <label for="student-{{ $student->id }}" class="ml-3 flex-1 cursor-pointer">
                                        <div class="flex items-center justify-between">
                                            <div>
                                                <div class="text-sm font-medium text-gray-900">{{ $student->user->name }}</div>
                                                <div class="text-xs text-gray-500">{{ $student->user->email }}</div>
                                            </div>
                                            <div class="flex items-center gap-4">
                                                @if($student->country)
                                                    <span class="text-xs text-gray-500">{{ $student->country }}</span>
                                                @endif
                                                <flux:badge size="sm" :class="match($student->status) {
                                                    'active' => 'badge-green',
                                                    'inactive' => 'badge-gray',
                                                    'graduated' => 'badge-blue',
                                                    'suspended' => 'badge-red',
                                                    default => 'badge-gray'
                                                }">
                                                    {{ ucfirst($student->status) }}
                                                </flux:badge>
                                                @if($student->paidOrders->count() > 0)
                                                    <span class="text-xs text-green-600 font-semibold">RM {{ number_format($student->paidOrders->sum('total_amount'), 2) }}</span>
                                                @endif
                                            </div>
                                        </div>
                                    </label>
                                </div>
                            @empty
                                <div class="text-center py-8 text-gray-500">
                                    <flux:icon.users class="mx-auto h-12 w-12 text-gray-400" />
                                    <p class="mt-2">No students found matching your filters</p>
                                </div>
                            @endforelse
                        </div>

                        <flux:text class="mt-1 text-sm">
                            Use filters above to find specific students, then select them to add to this audience
                        </flux:text>
                    </flux:field>
                </div>
            </div>

            <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 flex justify-end space-x-3">
                <flux:button variant="ghost" href="{{ route('crm.audiences.index') }}">
                    Cancel
                </flux:button>
                <flux:button type="submit" variant="primary">
                    Update Audience
                </flux:button>
            </div>
        </flux:card>
    </form>
</div>
