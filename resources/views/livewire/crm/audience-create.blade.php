<?php

use App\Models\Audience;
use App\Models\Course;
use App\Models\Student;
use App\Services\AudienceRuleBuilder;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    public $name = '';

    public $description = '';

    public $status = 'active';

    public $selectedStudents = [];

    // Rule builder state
    public array $rules = [];

    public string $matchMode = 'all';

    public bool $rulesApplied = false;

    public function mount(): void
    {
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

        $audience = Audience::create([
            'name' => $this->name,
            'description' => $this->description,
            'status' => $this->status,
        ]);

        if (! empty($this->selectedStudents)) {
            foreach (array_chunk($this->selectedStudents, 500) as $chunk) {
                $audience->students()->attach($chunk);
            }
        }

        session()->flash('message', 'Audience created successfully.');
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
            <h1 class="text-lg font-semibold tracking-tight text-zinc-900 dark:text-zinc-100">Create Audience</h1>
            <p class="mt-0.5 text-[13px] text-zinc-500 dark:text-zinc-400">Create a new audience segment for your campaigns</p>
        </div>
        <flux:button variant="outline" href="{{ route('crm.audiences.index') }}">
            <div class="flex items-center justify-center">
                <flux:icon name="chevron-left" class="mr-1 h-4 w-4" />
                Back to Audiences
            </div>
        </flux:button>
    </div>

    <form wire:submit="save">
        <div class="rounded-lg border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
            <div class="space-y-6 p-6">
                {{-- Audience Details --}}
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

                {{-- Rule Builder Section --}}
                <div class="rounded-lg border border-zinc-200 bg-zinc-50/50 p-4 dark:border-zinc-700 dark:bg-zinc-800/50">
                    <div class="mb-4 flex items-center justify-between">
                        <h3 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">Segment Rules</h3>
                        <div class="flex items-center gap-2">
                            <span class="text-sm text-zinc-500 dark:text-zinc-400">Match</span>
                            <flux:select wire:model.live="matchMode" class="w-24">
                                <flux:select.option value="all">ALL</flux:select.option>
                                <flux:select.option value="any">ANY</flux:select.option>
                            </flux:select>
                            <span class="text-sm text-zinc-500 dark:text-zinc-400">rules</span>
                        </div>
                    </div>

                    {{-- Rule Rows --}}
                    <div class="space-y-3">
                        @foreach($rules as $index => $rule)
                            <div wire:key="rule-{{ $index }}" class="rounded-md border border-zinc-200 bg-white p-3 dark:border-zinc-700 dark:bg-zinc-800">
                                <div class="grid grid-cols-1 items-start gap-3 md:grid-cols-12">
                                    {{-- Field Dropdown --}}
                                    <div class="md:col-span-3">
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

                                    {{-- Operator Dropdown --}}
                                    <div class="md:col-span-2">
                                        @if(!empty($rule['field']) && isset($allFields[$rule['field']]))
                                            <flux:select wire:model.live="rules.{{ $index }}.operator" placeholder="Operator...">
                                                <flux:select.option value="">Operator...</flux:select.option>
                                                @foreach($allFields[$rule['field']]['operators'] as $opKey => $opLabel)
                                                    <flux:select.option value="{{ $opKey }}">{{ $opLabel }}</flux:select.option>
                                                @endforeach
                                            </flux:select>
                                        @else
                                            <flux:select disabled placeholder="Select field first">
                                                <flux:select.option value="">Select field first</flux:select.option>
                                            </flux:select>
                                        @endif
                                    </div>

                                    {{-- Value Input --}}
                                    <div class="{{ (!empty($rule['operator']) && $rule['operator'] === 'between') ? 'md:col-span-3' : 'md:col-span-6' }}">
                                        @if(!empty($rule['field']) && !empty($rule['operator']) && isset($allFields[$rule['field']]))
                                            @php $fieldType = $allFields[$rule['field']]['type']; @endphp

                                            @if($fieldType === 'number')
                                                <flux:input wire:model="rules.{{ $index }}.value" type="number" placeholder="Enter value..." />
                                            @elseif($fieldType === 'date' && $rule['operator'] === 'in_last_days')
                                                <div class="flex items-center gap-2">
                                                    <flux:input wire:model="rules.{{ $index }}.value" type="number" placeholder="Number of days" />
                                                    <span class="shrink-0 text-sm text-zinc-500 dark:text-zinc-400">days</span>
                                                </div>
                                            @elseif($fieldType === 'date')
                                                <flux:input wire:model="rules.{{ $index }}.value" type="date" />
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
                                                <flux:input wire:model="rules.{{ $index }}.value" type="text" placeholder="Enter value..." />
                                            @endif
                                        @else
                                            <flux:input disabled placeholder="Select field and operator first" />
                                        @endif
                                    </div>

                                    {{-- Value2 Input (for "between" operator) --}}
                                    @if(!empty($rule['operator']) && $rule['operator'] === 'between')
                                        <div class="md:col-span-3">
                                            <flux:input wire:model="rules.{{ $index }}.value2" type="number" placeholder="Max value..." />
                                        </div>
                                    @endif

                                    {{-- Remove Button --}}
                                    <div class="flex items-center justify-end md:col-span-1">
                                        @if(count($rules) > 1)
                                            <flux:button variant="ghost" size="sm" wire:click="removeRule({{ $index }})">
                                                <flux:icon name="x-mark" class="h-4 w-4 text-zinc-400 dark:text-zinc-500" />
                                            </flux:button>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    {{-- Add Rule Button --}}
                    <div class="mt-3">
                        <flux:button variant="outline" size="sm" wire:click="addRule">
                            <div class="flex items-center justify-center">
                                <flux:icon name="plus" class="mr-1 h-4 w-4" />
                                Add Rule
                            </div>
                        </flux:button>
                    </div>

                    {{-- Bottom Bar --}}
                    <div class="mt-4 flex items-center justify-between rounded-md border border-zinc-200 bg-white px-4 py-3 dark:border-zinc-700 dark:bg-zinc-800">
                        <div>
                            @if($rulesApplied)
                                <span class="text-sm font-medium tabular-nums text-zinc-600 dark:text-zinc-400">
                                    {{ $filteredCount }} of {{ $totalCount }} students
                                </span>
                            @else
                                <span class="text-sm text-zinc-500 dark:text-zinc-400">
                                    Apply rules to see results
                                </span>
                            @endif
                        </div>
                        <div class="flex items-center gap-2">
                            <flux:button variant="ghost" size="sm" wire:click="clearRules">
                                Clear Rules
                            </flux:button>
                            <flux:button variant="primary" size="sm" wire:click="applyRules">
                                Apply Rules
                            </flux:button>
                        </div>
                    </div>
                </div>

                {{-- Student List Section --}}
                <div>
                    <div class="mb-3 flex items-center justify-between">
                        <span class="text-sm font-medium tabular-nums text-zinc-600 dark:text-zinc-400">
                            {{ count($selectedStudents) }} students selected
                        </span>
                        <div class="flex gap-2">
                            <flux:button variant="outline" size="sm" wire:click="selectAll">
                                Select All ({{ $filteredCount }})
                            </flux:button>
                            <flux:button variant="outline" size="sm" wire:click="deselectAll">
                                Deselect All
                            </flux:button>
                        </div>
                    </div>

                    <div class="rounded-md border border-zinc-200 p-4 dark:border-zinc-700">
                        <div class="space-y-2">
                            @forelse($students as $student)
                                <div wire:key="student-{{ $student->id }}" class="flex items-center rounded p-2 transition-colors hover:bg-zinc-50 dark:hover:bg-zinc-800/50">
                                    <flux:checkbox
                                        wire:model="selectedStudents"
                                        value="{{ $student->id }}"
                                        id="student-{{ $student->id }}"
                                    />
                                    <label for="student-{{ $student->id }}" class="ml-3 flex-1 cursor-pointer">
                                        <div class="flex items-center justify-between">
                                            <div>
                                                <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $student->user->name ?? 'N/A' }}</div>
                                                <div class="text-[12px] text-zinc-500 dark:text-zinc-400">{{ $student->user->email ?? '' }}</div>
                                            </div>
                                            <div class="flex items-center gap-4">
                                                @if($student->country)
                                                    <span class="text-[12px] text-zinc-400">{{ $student->country }}</span>
                                                @endif
                                                @switch($student->status)
                                                    @case('active')
                                                        <span class="inline-flex items-center rounded px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wider bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400">
                                                            {{ ucfirst($student->status) }}
                                                        </span>
                                                        @break
                                                    @case('inactive')
                                                        <span class="inline-flex items-center rounded px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wider bg-zinc-100 text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400">
                                                            {{ ucfirst($student->status) }}
                                                        </span>
                                                        @break
                                                    @case('graduated')
                                                        <span class="inline-flex items-center rounded px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wider bg-sky-50 text-sky-600 dark:bg-sky-900/30 dark:text-sky-400">
                                                            {{ ucfirst($student->status) }}
                                                        </span>
                                                        @break
                                                    @case('suspended')
                                                        <span class="inline-flex items-center rounded px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wider bg-red-50 text-red-600 dark:bg-red-900/30 dark:text-red-400">
                                                            {{ ucfirst($student->status) }}
                                                        </span>
                                                        @break
                                                    @default
                                                        <span class="inline-flex items-center rounded px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wider bg-zinc-100 text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400">
                                                            {{ ucfirst($student->status) }}
                                                        </span>
                                                @endswitch
                                            </div>
                                        </div>
                                    </label>
                                </div>
                            @empty
                                <div class="py-8 text-center text-zinc-500 dark:text-zinc-400">
                                    <flux:icon.users class="mx-auto h-8 w-8 text-zinc-300 dark:text-zinc-600" />
                                    <p class="mt-2">No students found matching your rules</p>
                                </div>
                            @endforelse
                        </div>

                        @if($students->hasPages())
                            <div class="border-t border-zinc-200 pt-4 dark:border-zinc-700">
                                {{ $students->links() }}
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Footer --}}
            <div class="flex justify-end space-x-3 rounded-b-lg border-t border-zinc-200 bg-zinc-50/50 px-6 py-4 dark:border-zinc-700 dark:bg-zinc-800/30">
                <flux:button variant="ghost" href="{{ route('crm.audiences.index') }}">
                    Cancel
                </flux:button>
                <flux:button type="submit" variant="primary">
                    Create Audience
                </flux:button>
            </div>
        </div>
    </form>
</div>
