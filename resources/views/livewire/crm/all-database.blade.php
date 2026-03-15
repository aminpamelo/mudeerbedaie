<?php

use App\Models\ClassModel;
use App\Models\Student;
use App\Services\AudienceRuleBuilder;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public $search = '';
    public $countryFilter = '';
    public $perPage = 10;

    // Advanced filter (rule builder) state
    public bool $showAdvancedFilter = false;
    public array $rules = [];
    public string $matchMode = 'all';
    public bool $rulesApplied = false;

    public function mount(): void
    {
        $this->rules = [['field' => '', 'operator' => '', 'value' => '', 'value2' => '']];
    }

    public function toggleAdvancedFilter(): void
    {
        $this->showAdvancedFilter = !$this->showAdvancedFilter;
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

    public function with(): array
    {
        // Build base query - use rule builder if rules are applied
        if ($this->rulesApplied) {
            $query = AudienceRuleBuilder::buildQuery($this->rules, $this->matchMode);
        } else {
            $query = Student::query()->with(['user']);
        }

        // Eager load relationships for display
        $query->with([
            'orders' => fn($q) => $q->whereNotNull('paid_time'),
            'orders.items.product',
            'activeClasses',
        ]);

        // Apply text search
        if ($this->search) {
            $query->where(function ($q) {
                $q->whereHas('user', function($uq) {
                    $uq->where('name', 'like', '%' . $this->search . '%')
                       ->orWhere('email', 'like', '%' . $this->search . '%');
                })
                ->orWhere('student_id', 'like', '%' . $this->search . '%')
                ->orWhere('phone', 'like', '%' . $this->search . '%');
            });
        }

        // Apply country filter
        if ($this->countryFilter) {
            $query->where('country', $this->countryFilter);
        }

        $query->orderBy('created_at', 'desc');

        $filteredCount = $query->count();
        $students = $query->paginate($this->perPage);

        // Calculate aggregated stats - only count actually paid orders
        $totalRevenue = \App\Models\ProductOrder::query()
            ->whereNotNull('student_id')
            ->whereNotNull('paid_time')
            ->sum('total_amount');

        // Rule builder field config
        $fields = AudienceRuleBuilder::availableFields();
        $groupedFields = [];
        foreach ($fields as $key => $config) {
            $groupedFields[$config['group']][$key] = $config['label'];
        }

        return [
            'students' => $students,
            'totalContacts' => Student::count(),
            'filteredCount' => $filteredCount,
            'countries' => Student::distinct()->whereNotNull('country')->pluck('country')->filter()->sort(),
            'totalRevenue' => $totalRevenue,
            'groupedFields' => $groupedFields,
            'allFields' => $fields,
            'classes' => ClassModel::orderBy('title')->pluck('title', 'id'),
        ];
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingCountryFilter(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->search = '';
        $this->countryFilter = '';
        $this->clearRules();
        $this->resetPage();
    }

    public function exportContacts(): void
    {
        // Store current filters in session for the download route
        session([
            'crm_export_search' => $this->search,
            'crm_export_country_filter' => $this->countryFilter
        ]);

        // Redirect to the download route
        $this->redirect(route('crm.export'));
    }

}; ?>

<div>
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading size="xl">All Database</flux:heading>
            <flux:text class="mt-2">Complete student contact database with revenue tracking</flux:text>
        </div>
        <div class="flex space-x-3">
            <flux:button variant="outline" wire:click="exportContacts">
                <div class="flex items-center justify-center">
                    <flux:icon name="document-arrow-down" class="w-4 h-4 mr-1" />
                    Export
                </div>
            </flux:button>
        </div>
    </div>

    <div class="mt-6 space-y-6">
        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <flux:card class="p-6">
                <div class="flex items-center">
                    <div class="rounded-md bg-blue-50 dark:bg-blue-900/30 p-3">
                        <flux:icon.users class="h-6 w-6 text-blue-600 dark:text-blue-400" />
                    </div>
                    <div class="ml-4">
                        <p class="text-2xl font-semibold text-gray-900 dark:text-white">{{ number_format($totalContacts) }}</p>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Total Contacts</p>
                    </div>
                </div>
            </flux:card>

            <flux:card class="p-6">
                <div class="flex items-center">
                    <div class="rounded-md bg-green-50 dark:bg-green-900/30 p-3">
                        <flux:icon.currency-dollar class="h-6 w-6 text-green-600 dark:text-green-400" />
                    </div>
                    <div class="ml-4">
                        <p class="text-2xl font-semibold text-gray-900 dark:text-white">RM {{ number_format($totalRevenue, 2) }}</p>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Total Revenue</p>
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
                            placeholder="Search by name, email, student ID, or phone..."
                            icon="magnifying-glass" />
                    </div>
                    <div class="w-full sm:w-48">
                        <flux:select wire:model.live="countryFilter" placeholder="All Countries">
                            <flux:select.option value="">All Countries</flux:select.option>
                            @foreach($countries as $country)
                                <flux:select.option value="{{ $country }}">{{ $country }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    </div>
                    <flux:button wire:click="toggleAdvancedFilter" variant="{{ $showAdvancedFilter ? 'primary' : 'outline' }}">
                        <div class="flex items-center justify-center">
                            <flux:icon name="funnel" class="w-4 h-4 mr-1" />
                            Advanced Filter
                            @if($rulesApplied)
                                <flux:badge size="sm" color="lime" class="ml-1">On</flux:badge>
                            @endif
                        </div>
                    </flux:button>
                    @if($search || $countryFilter || $rulesApplied)
                        <flux:button wire:click="clearFilters" variant="ghost">
                            Clear Filters
                        </flux:button>
                    @endif
                </div>

                {{-- Advanced Filter (Rule Builder) --}}
                @if($showAdvancedFilter)
                    <div class="mt-4 rounded-lg border border-gray-200 dark:border-zinc-600 bg-gray-50 dark:bg-zinc-800 p-4">
                        <div class="mb-4 flex items-center justify-between">
                            <flux:heading size="lg">Segment Rules</flux:heading>
                            <div class="flex items-center gap-2">
                                <flux:text class="text-sm">Match</flux:text>
                                <flux:select wire:model.live="matchMode" class="w-24">
                                    <flux:select.option value="all">ALL</flux:select.option>
                                    <flux:select.option value="any">ANY</flux:select.option>
                                </flux:select>
                                <flux:text class="text-sm">rules</flux:text>
                            </div>
                        </div>

                        {{-- Rule Rows --}}
                        <div class="space-y-3">
                            @foreach($rules as $index => $rule)
                                <div wire:key="rule-{{ $index }}" class="rounded-md border border-gray-200 dark:border-zinc-600 bg-white dark:bg-zinc-700 p-3">
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
                                                        <flux:text class="shrink-0 text-sm">days</flux:text>
                                                    </div>
                                                @elseif($fieldType === 'date')
                                                    <flux:input wire:model="rules.{{ $index }}.value" type="date" />
                                                @elseif($fieldType === 'boolean')
                                                    <flux:select wire:model="rules.{{ $index }}.value">
                                                        <flux:select.option value="">Select...</flux:select.option>
                                                        <flux:select.option value="yes">Yes</flux:select.option>
                                                        <flux:select.option value="no">No</flux:select.option>
                                                    </flux:select>
                                                @elseif($fieldType === 'class')
                                                    <flux:select wire:model="rules.{{ $index }}.value">
                                                        <flux:select.option value="">Select class...</flux:select.option>
                                                        @foreach($classes as $classId => $classTitle)
                                                            <flux:select.option value="{{ $classId }}">{{ $classTitle }}</flux:select.option>
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
                                                    <flux:icon name="x-mark" class="h-4 w-4 text-gray-400" />
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
                        <div class="mt-4 flex items-center justify-between rounded-md border border-gray-200 dark:border-zinc-600 bg-white dark:bg-zinc-700 px-4 py-3">
                            <div>
                                @if($rulesApplied)
                                    <flux:text class="text-sm font-medium">
                                        {{ number_format($filteredCount) }} of {{ number_format($totalContacts) }} students match
                                    </flux:text>
                                @else
                                    <flux:text class="text-sm text-gray-500">
                                        Apply rules to filter results
                                    </flux:text>
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
                @endif
            </div>

            <!-- Contacts Table -->
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-zinc-700">
                    <thead class="bg-gray-50 dark:bg-zinc-800">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Name</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Classes</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Country/Region</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Total Revenue</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Phone</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Purchased Products</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-zinc-800 divide-y divide-gray-200 dark:divide-zinc-700">
                        @forelse($students as $student)
                            <tr class="hover:bg-gray-50 dark:hover:bg-zinc-700">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <a href="{{ route('students.show', $student) }}" class="flex items-center group">
                                        <flux:avatar size="sm" class="mr-3">
                                            {{ $student->user->initials() }}
                                        </flux:avatar>
                                        <div>
                                            <div class="text-sm font-medium text-gray-900 dark:text-white group-hover:text-blue-600 dark:group-hover:text-blue-400 transition-colors">{{ $student->user->name }}</div>
                                            <div class="text-sm text-gray-500 dark:text-gray-400">{{ $student->user->email }}</div>
                                        </div>
                                    </a>
                                </td>
                                <td class="px-6 py-4">
                                    @if($student->activeClasses->count() > 0)
                                        <div class="flex flex-wrap gap-1">
                                            @foreach($student->activeClasses->take(2) as $class)
                                                <flux:badge size="sm" color="purple">{{ Str::limit($class->title, 20) }}</flux:badge>
                                            @endforeach
                                            @if($student->activeClasses->count() > 2)
                                                <flux:badge size="sm" color="gray">+{{ $student->activeClasses->count() - 2 }}</flux:badge>
                                            @endif
                                        </div>
                                    @else
                                        <span class="text-sm text-gray-400 dark:text-gray-500">No classes</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        @if($student->country === 'Malaysia')
                                            <span class="mr-2">🇲🇾</span>
                                        @endif
                                        <div class="text-sm text-gray-900 dark:text-gray-200">{{ $student->country ?? 'N/A' }}</div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-semibold text-green-600">
                                        RM {{ number_format($student->orders->sum('total_amount'), 2) }}
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900 dark:text-gray-200">{{ $student->phone ?? 'N/A' }}</div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm text-gray-900 dark:text-gray-200">
                                        @php
                                            $products = $student->orders
                                                ->flatMap(fn($order) => $order->items)
                                                ->map(fn($item) => $item->product?->name ?? $item->product_name)
                                                ->filter()
                                                ->unique()
                                                ->take(3);
                                            $totalProducts = $student->orders
                                                ->flatMap(fn($order) => $order->items)
                                                ->map(fn($item) => $item->product?->name ?? $item->product_name)
                                                ->filter()
                                                ->unique()
                                                ->count();
                                        @endphp

                                        @if($products->count() > 0)
                                            <div class="flex flex-wrap gap-1">
                                                @foreach($products as $productName)
                                                    <flux:badge size="sm" color="blue">{{ Str::limit($productName, 20) }}</flux:badge>
                                                @endforeach
                                                @if($totalProducts > 3)
                                                    <flux:badge size="sm" color="gray">+{{ $totalProducts - 3 }}</flux:badge>
                                                @endif
                                            </div>
                                        @else
                                            <span class="text-gray-400 dark:text-gray-500">No purchases</span>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right">
                                    <flux:dropdown position="bottom end">
                                        <flux:button variant="ghost" size="sm" icon="ellipsis-vertical" />
                                        <flux:menu>
                                            <flux:menu.item href="{{ route('students.show', $student) }}" icon="eye">
                                                View Details
                                            </flux:menu.item>
                                            <flux:menu.item href="{{ route('students.edit', $student) }}" icon="pencil">
                                                Edit Student
                                            </flux:menu.item>
                                            <flux:menu.separator />
                                            <flux:menu.item href="{{ route('admin.students.payment-methods', $student) }}" icon="credit-card">
                                                Payment Methods
                                            </flux:menu.item>
                                        </flux:menu>
                                    </flux:dropdown>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-6 py-12 text-center">
                                    <flux:icon.users class="mx-auto h-12 w-12 text-gray-400 dark:text-gray-500" />
                                    <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">No contacts found</h3>
                                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                        @if($search || $countryFilter)
                                            Try adjusting your search or filter criteria.
                                        @else
                                            No student contacts available in the database.
                                        @endif
                                    </p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($students->hasPages())
                <div class="px-6 py-4 border-t border-gray-200 dark:border-zinc-700">
                    {{ $students->links() }}
                </div>
            @endif
        </flux:card>
    </div>
</div>
