<?php

use App\Models\Audience;
use App\Models\Course;
use App\Models\Student;
use App\Services\AudienceRuleBuilder;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public Audience $audience;
    public $name = '';
    public $description = '';
    public $status = 'active';
    public $selectedStudents = [];

    // Rule builder state
    public array $rules = [];
    public string $matchMode = 'all';
    public bool $rulesApplied = false;

    public function mount(Audience $audience): void
    {
        $this->audience = $audience;
        $this->name = $audience->name;
        $this->description = $audience->description;
        $this->status = $audience->status;
        $this->selectedStudents = $audience->students()->pluck('students.id')->toArray();
        $this->addRule();
    }

    public function addRule(): void
    {
        $this->rules[] = ['field' => '', 'operator' => '', 'value' => '', 'value2' => ''];
    }

    public function removeRule(int $index): void
    {
        unset($this->rules[$index]);
        $this->rules = array_values($this->rules);
        if (empty($this->rules)) {
            $this->rulesApplied = false;
        }
    }

    public function updatedRules(): void
    {
        $this->rulesApplied = false;
    }

    public function updatedMatchMode(): void
    {
        $this->rulesApplied = false;
    }

    public function applyRules(): void
    {
        $this->rulesApplied = true;
        $this->resetPage();
    }

    public function clearRules(): void
    {
        $this->rules = [['field' => '', 'operator' => '', 'value' => '', 'value2' => '']];
        $this->matchMode = 'all';
        $this->rulesApplied = false;
        $this->resetPage();
    }

    private function buildFilteredQuery()
    {
        if ($this->rulesApplied) {
            return AudienceRuleBuilder::buildQuery($this->rules, $this->matchMode);
        }

        return Student::query()->with(['user']);
    }

    public function with(): array
    {
        $query = $this->buildFilteredQuery();
        $filteredCount = $query->count();
        $students = $query->orderBy('created_at', 'desc')->paginate(50);

        $fields = AudienceRuleBuilder::availableFields();
        $groupedFields = [];
        foreach ($fields as $key => $config) {
            $groupedFields[$config['group']][$key] = $config['label'];
        }

        return [
            'students' => $students,
            'filteredCount' => $filteredCount,
            'totalCount' => Student::count(),
            'groupedFields' => $groupedFields,
            'allFields' => $fields,
            'courses' => Course::orderBy('name')->pluck('name', 'id'),
            'countries' => Student::distinct()->whereNotNull('country')->pluck('country')->filter()->sort(),
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
        $ids = $this->buildFilteredQuery()->pluck('id')->toArray();
        $this->selectedStudents = array_values(array_unique(
            array_merge($this->selectedStudents, $ids)
        ));
    }

    public function deselectAll(): void
    {
        $this->selectedStudents = [];
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

                        <!-- Rule Builder Section -->
                        <div class="mt-2 p-4 bg-gray-50 border border-gray-300 rounded-md space-y-4">
                            <div class="flex items-center justify-between">
                                <flux:text class="font-semibold">Rule Builder</flux:text>
                                <div class="flex items-center gap-2">
                                    <flux:text class="text-sm">Match</flux:text>
                                    <flux:select wire:model.live="matchMode" class="w-24">
                                        <flux:select.option value="all">ALL</flux:select.option>
                                        <flux:select.option value="any">ANY</flux:select.option>
                                    </flux:select>
                                    <flux:text class="text-sm">rules</flux:text>
                                </div>
                            </div>

                            <!-- Rule Rows -->
                            <div class="space-y-3">
                                @foreach($rules as $index => $rule)
                                    <div wire:key="rule-{{ $index }}" class="flex items-start gap-2 p-3 bg-white border border-gray-200 rounded-md">
                                        <!-- Field Select -->
                                        <div class="flex-1 min-w-0">
                                            <flux:select wire:model.live="rules.{{ $index }}.field" placeholder="Select field...">
                                                <flux:select.option value="">Select field...</flux:select.option>
                                                @foreach($groupedFields as $group => $fields)
                                                    <optgroup label="{{ $group }}">
                                                        @foreach($fields as $fieldKey => $fieldLabel)
                                                            <flux:select.option value="{{ $fieldKey }}">{{ $fieldLabel }}</flux:select.option>
                                                        @endforeach
                                                    </optgroup>
                                                @endforeach
                                            </flux:select>
                                        </div>

                                        <!-- Operator Select -->
                                        @if(!empty($rule['field']) && isset($allFields[$rule['field']]))
                                            <div class="flex-1 min-w-0">
                                                <flux:select wire:model.live="rules.{{ $index }}.operator" placeholder="Select operator...">
                                                    <flux:select.option value="">Select operator...</flux:select.option>
                                                    @foreach($allFields[$rule['field']]['operators'] as $opKey => $opLabel)
                                                        <flux:select.option value="{{ $opKey }}">{{ $opLabel }}</flux:select.option>
                                                    @endforeach
                                                </flux:select>
                                            </div>
                                        @endif

                                        <!-- Value Input -->
                                        @if(!empty($rule['field']) && !empty($rule['operator']) && isset($allFields[$rule['field']]))
                                            <div class="flex-1 min-w-0">
                                                @php $fieldType = $allFields[$rule['field']]['type']; @endphp

                                                @if($fieldType === 'number')
                                                    <flux:input type="number" wire:model="rules.{{ $index }}.value" placeholder="Value" />
                                                @elseif($fieldType === 'date' && $rule['operator'] === 'in_last_days')
                                                    <div class="flex items-center gap-2">
                                                        <flux:input type="number" wire:model="rules.{{ $index }}.value" placeholder="Number of days" />
                                                        <flux:text class="text-sm whitespace-nowrap">days</flux:text>
                                                    </div>
                                                @elseif($fieldType === 'date')
                                                    <flux:input type="date" wire:model="rules.{{ $index }}.value" />
                                                @elseif($fieldType === 'boolean')
                                                    <flux:select wire:model="rules.{{ $index }}.value">
                                                        <flux:select.option value="">Select...</flux:select.option>
                                                        <flux:select.option value="yes">Yes</flux:select.option>
                                                        <flux:select.option value="no">No</flux:select.option>
                                                    </flux:select>
                                                @elseif($fieldType === 'course')
                                                    <flux:select wire:model="rules.{{ $index }}.value">
                                                        <flux:select.option value="">Select course...</flux:select.option>
                                                        @foreach($courses as $courseId => $courseName)
                                                            <flux:select.option value="{{ $courseId }}">{{ $courseName }}</flux:select.option>
                                                        @endforeach
                                                    </flux:select>
                                                @elseif($fieldType === 'enrollment_status')
                                                    <flux:select wire:model="rules.{{ $index }}.value">
                                                        <flux:select.option value="">Select status...</flux:select.option>
                                                        <flux:select.option value="enrolled">Enrolled</flux:select.option>
                                                        <flux:select.option value="active">Active</flux:select.option>
                                                        <flux:select.option value="completed">Completed</flux:select.option>
                                                        <flux:select.option value="dropped">Dropped</flux:select.option>
                                                        <flux:select.option value="suspended">Suspended</flux:select.option>
                                                        <flux:select.option value="pending">Pending</flux:select.option>
                                                    </flux:select>
                                                @elseif($fieldType === 'subscription_status')
                                                    <flux:select wire:model="rules.{{ $index }}.value">
                                                        <flux:select.option value="">Select status...</flux:select.option>
                                                        <flux:select.option value="active">Active</flux:select.option>
                                                        <flux:select.option value="trialing">Trialing</flux:select.option>
                                                        <flux:select.option value="past_due">Past Due</flux:select.option>
                                                        <flux:select.option value="canceled">Canceled</flux:select.option>
                                                        <flux:select.option value="unpaid">Unpaid</flux:select.option>
                                                    </flux:select>
                                                @elseif($fieldType === 'student_status')
                                                    <flux:select wire:model="rules.{{ $index }}.value">
                                                        <flux:select.option value="">Select status...</flux:select.option>
                                                        <flux:select.option value="active">Active</flux:select.option>
                                                        <flux:select.option value="inactive">Inactive</flux:select.option>
                                                        <flux:select.option value="graduated">Graduated</flux:select.option>
                                                        <flux:select.option value="suspended">Suspended</flux:select.option>
                                                    </flux:select>
                                                @elseif($fieldType === 'country')
                                                    <flux:select wire:model="rules.{{ $index }}.value">
                                                        <flux:select.option value="">Select country...</flux:select.option>
                                                        @foreach($countries as $country)
                                                            <flux:select.option value="{{ $country }}">{{ $country }}</flux:select.option>
                                                        @endforeach
                                                    </flux:select>
                                                @elseif($fieldType === 'gender')
                                                    <flux:select wire:model="rules.{{ $index }}.value">
                                                        <flux:select.option value="">Select gender...</flux:select.option>
                                                        <flux:select.option value="male">Male</flux:select.option>
                                                        <flux:select.option value="female">Female</flux:select.option>
                                                    </flux:select>
                                                @else
                                                    <flux:input type="text" wire:model="rules.{{ $index }}.value" placeholder="Value" />
                                                @endif
                                            </div>

                                            <!-- Value2 for "between" operator -->
                                            @if($rule['operator'] === 'between')
                                                <div class="flex items-center gap-2">
                                                    <flux:text class="text-sm">and</flux:text>
                                                    <flux:input type="number" wire:model="rules.{{ $index }}.value2" placeholder="Value 2" />
                                                </div>
                                            @endif
                                        @endif

                                        <!-- Remove Button -->
                                        @if(count($rules) > 1)
                                            <flux:button variant="ghost" size="sm" wire:click="removeRule({{ $index }})">
                                                <flux:icon name="x-mark" class="w-4 h-4 text-red-500" />
                                            </flux:button>
                                        @endif
                                    </div>
                                @endforeach
                            </div>

                            <!-- Add Rule Button -->
                            <div>
                                <flux:button variant="outline" size="sm" wire:click="addRule">
                                    <div class="flex items-center justify-center">
                                        <flux:icon name="plus" class="w-4 h-4 mr-1" />
                                        Add Rule
                                    </div>
                                </flux:button>
                            </div>

                            <!-- Results Count + Apply/Clear -->
                            <div class="flex items-center justify-between text-sm text-gray-600 pt-2 border-t border-gray-200">
                                <span>
                                    @if($rulesApplied)
                                        Matching {{ $filteredCount }} of {{ $totalCount }} students
                                    @else
                                        Showing all {{ $totalCount }} students
                                    @endif
                                </span>
                                <div class="flex gap-2">
                                    <flux:button variant="primary" size="sm" wire:click="applyRules">
                                        Apply Rules
                                    </flux:button>
                                    <flux:button variant="outline" size="sm" wire:click="clearRules">
                                        Clear Rules
                                    </flux:button>
                                </div>
                            </div>
                        </div>

                        <!-- Selection Actions -->
                        <div class="mt-2 flex items-center justify-between">
                            <flux:text class="text-sm text-gray-600">
                                Use rules above to filter students, then select them to add to this audience
                            </flux:text>
                            <div class="flex gap-2">
                                <flux:button variant="outline" size="sm" wire:click="selectAll">
                                    Select All ({{ $filteredCount }})
                                </flux:button>
                                <flux:button variant="outline" size="sm" wire:click="deselectAll">
                                    Deselect All
                                </flux:button>
                            </div>
                        </div>

                        <!-- Student List -->
                        <div class="mt-2 border border-gray-300 rounded-md p-4 space-y-2">
                            @forelse($students as $student)
                                <div wire:key="student-{{ $student->id }}" class="flex items-center p-2 hover:bg-gray-50 rounded">
                                    <flux:checkbox
                                        wire:model="selectedStudents"
                                        value="{{ $student->id }}"
                                        id="student-{{ $student->id }}"
                                    />
                                    <label for="student-{{ $student->id }}" class="ml-3 flex-1 cursor-pointer">
                                        <div class="flex items-center justify-between">
                                            <div>
                                                <div class="text-sm font-medium text-gray-900">{{ $student->user->name ?? 'N/A' }}</div>
                                                <div class="text-xs text-gray-500">{{ $student->user->email ?? '' }}</div>
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
                                            </div>
                                        </div>
                                    </label>
                                </div>
                            @empty
                                <div class="text-center py-8 text-gray-500">
                                    <flux:icon.users class="mx-auto h-12 w-12 text-gray-400" />
                                    <p class="mt-2">No students found matching your rules</p>
                                </div>
                            @endforelse

                            @if($students->hasPages())
                                <div class="pt-4 border-t border-gray-200">
                                    {{ $students->links() }}
                                </div>
                            @endif
                        </div>
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
