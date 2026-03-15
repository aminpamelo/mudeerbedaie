# Audience Rule Builder Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Replace simple audience filters with a powerful rule builder supporting spending, enrollment, and demographic segmentation criteria.

**Architecture:** Inline Livewire rule builder in both audience-create and audience-edit Volt components. Rules are transient UI state (array of conditions with field/operator/value). A `buildRulesQuery()` method translates rules into Eloquent subqueries for performance. Static audience - saves fixed student list.

**Tech Stack:** Livewire Volt (class-based), Flux UI components, Eloquent subqueries, WithPagination

---

### Task 1: Create AudienceRuleBuilder Service Class

**Files:**
- Create: `app/Services/AudienceRuleBuilder.php`

**Step 1: Create the service class**

```php
<?php

namespace App\Services;

use App\Models\Student;
use Illuminate\Database\Eloquent\Builder;

class AudienceRuleBuilder
{
    /**
     * Available rule fields with their configuration.
     *
     * @return array<string, array{label: string, group: string, operators: array<string, string>, type: string}>
     */
    public static function availableFields(): array
    {
        return [
            // Spending & Orders
            'total_spending' => [
                'label' => 'Total Spending (RM)',
                'group' => 'Spending & Orders',
                'operators' => ['>' => 'greater than', '<' => 'less than', '>=' => 'at least', '<=' => 'at most', '=' => 'equals', 'between' => 'between'],
                'type' => 'number',
            ],
            'order_count' => [
                'label' => 'Order Count',
                'group' => 'Spending & Orders',
                'operators' => ['>' => 'greater than', '<' => 'less than', '>=' => 'at least', '<=' => 'at most', '=' => 'equals', 'between' => 'between'],
                'type' => 'number',
            ],
            'last_order_date' => [
                'label' => 'Last Order Date',
                'group' => 'Spending & Orders',
                'operators' => ['before' => 'before', 'after' => 'after', 'in_last_days' => 'in last X days'],
                'type' => 'date',
            ],
            'has_paid_orders' => [
                'label' => 'Has Paid Orders',
                'group' => 'Spending & Orders',
                'operators' => ['is' => 'is'],
                'type' => 'boolean',
            ],

            // Enrollment & Courses
            'enrollment_count' => [
                'label' => 'Enrollment Count',
                'group' => 'Enrollment & Courses',
                'operators' => ['>' => 'greater than', '<' => 'less than', '>=' => 'at least', '<=' => 'at most', '=' => 'equals'],
                'type' => 'number',
            ],
            'enrolled_in_course' => [
                'label' => 'Enrolled In Course',
                'group' => 'Enrollment & Courses',
                'operators' => ['is' => 'is', 'is_not' => 'is not'],
                'type' => 'course',
            ],
            'enrollment_status' => [
                'label' => 'Enrollment Status',
                'group' => 'Enrollment & Courses',
                'operators' => ['is' => 'is', 'is_not' => 'is not'],
                'type' => 'enrollment_status',
            ],
            'subscription_status' => [
                'label' => 'Subscription Status',
                'group' => 'Enrollment & Courses',
                'operators' => ['is' => 'is', 'is_not' => 'is not'],
                'type' => 'subscription_status',
            ],

            // Demographics & Profile
            'student_status' => [
                'label' => 'Student Status',
                'group' => 'Demographics & Profile',
                'operators' => ['is' => 'is', 'is_not' => 'is not'],
                'type' => 'student_status',
            ],
            'country' => [
                'label' => 'Country',
                'group' => 'Demographics & Profile',
                'operators' => ['is' => 'is', 'is_not' => 'is not'],
                'type' => 'country',
            ],
            'state' => [
                'label' => 'State',
                'group' => 'Demographics & Profile',
                'operators' => ['is' => 'is', 'is_not' => 'is not'],
                'type' => 'text',
            ],
            'gender' => [
                'label' => 'Gender',
                'group' => 'Demographics & Profile',
                'operators' => ['is' => 'is', 'is_not' => 'is not'],
                'type' => 'gender',
            ],
            'age' => [
                'label' => 'Age',
                'group' => 'Demographics & Profile',
                'operators' => ['>' => 'greater than', '<' => 'less than', '>=' => 'at least', '<=' => 'at most', 'between' => 'between'],
                'type' => 'number',
            ],
            'registered_date' => [
                'label' => 'Registered Date',
                'group' => 'Demographics & Profile',
                'operators' => ['before' => 'before', 'after' => 'after', 'in_last_days' => 'in last X days'],
                'type' => 'date',
            ],
        ];
    }

    /**
     * Build an Eloquent query from an array of rules.
     *
     * @param  array<int, array{field: string, operator: string, value: mixed, value2?: mixed}>  $rules
     * @param  string  $matchMode  'all' (AND) or 'any' (OR)
     */
    public static function buildQuery(array $rules, string $matchMode = 'all'): Builder
    {
        $query = Student::query()->with(['user']);

        if (empty($rules)) {
            return $query;
        }

        $validRules = array_filter($rules, fn ($r) => ! empty($r['field']) && ! empty($r['operator']) && (isset($r['value']) && $r['value'] !== ''));

        if (empty($validRules)) {
            return $query;
        }

        $method = $matchMode === 'any' ? 'orWhere' : 'where';

        $query->where(function (Builder $q) use ($validRules, $method) {
            foreach ($validRules as $rule) {
                $q->$method(function (Builder $subQ) use ($rule) {
                    self::applyRule($subQ, $rule);
                });
            }
        });

        return $query;
    }

    private static function applyRule(Builder $query, array $rule): void
    {
        $field = $rule['field'];
        $operator = $rule['operator'];
        $value = $rule['value'];
        $value2 = $rule['value2'] ?? null;

        match ($field) {
            'total_spending' => self::applySpendingRule($query, $operator, $value, $value2),
            'order_count' => self::applyOrderCountRule($query, $operator, $value, $value2),
            'last_order_date' => self::applyLastOrderDateRule($query, $operator, $value),
            'has_paid_orders' => self::applyHasPaidOrdersRule($query, $value),
            'enrollment_count' => self::applyEnrollmentCountRule($query, $operator, $value),
            'enrolled_in_course' => self::applyEnrolledInCourseRule($query, $operator, $value),
            'enrollment_status' => self::applyEnrollmentStatusRule($query, $operator, $value),
            'subscription_status' => self::applySubscriptionStatusRule($query, $operator, $value),
            'student_status' => self::applyStudentStatusRule($query, $operator, $value),
            'country' => self::applyCountryRule($query, $operator, $value),
            'state' => self::applyStateRule($query, $operator, $value),
            'gender' => self::applyGenderRule($query, $operator, $value),
            'age' => self::applyAgeRule($query, $operator, $value, $value2),
            'registered_date' => self::applyRegisteredDateRule($query, $operator, $value),
            default => null,
        };
    }

    private static function applySpendingRule(Builder $query, string $operator, mixed $value, mixed $value2): void
    {
        $subquery = \App\Models\ProductOrder::selectRaw('COALESCE(SUM(total_amount), 0)')
            ->whereColumn('student_id', 'students.id')
            ->whereNotNull('paid_time');

        if ($operator === 'between') {
            $query->whereRaw("({$subquery->toSql()}) >= ?", array_merge($subquery->getBindings(), [(float) $value]))
                  ->whereRaw("({$subquery->toSql()}) <= ?", array_merge($subquery->getBindings(), [(float) $value2]));
        } else {
            $sqlOp = self::mapOperator($operator);
            $query->whereRaw("({$subquery->toSql()}) {$sqlOp} ?", array_merge($subquery->getBindings(), [(float) $value]));
        }
    }

    private static function applyOrderCountRule(Builder $query, string $operator, mixed $value, mixed $value2): void
    {
        $subquery = \App\Models\ProductOrder::selectRaw('COUNT(*)')
            ->whereColumn('student_id', 'students.id')
            ->whereNotNull('paid_time');

        if ($operator === 'between') {
            $query->whereRaw("({$subquery->toSql()}) >= ?", array_merge($subquery->getBindings(), [(int) $value]))
                  ->whereRaw("({$subquery->toSql()}) <= ?", array_merge($subquery->getBindings(), [(int) $value2]));
        } else {
            $sqlOp = self::mapOperator($operator);
            $query->whereRaw("({$subquery->toSql()}) {$sqlOp} ?", array_merge($subquery->getBindings(), [(int) $value]));
        }
    }

    private static function applyLastOrderDateRule(Builder $query, string $operator, mixed $value): void
    {
        if ($operator === 'in_last_days') {
            $date = now()->subDays((int) $value)->startOfDay();
            $query->whereHas('paidOrders', function (Builder $q) use ($date) {
                $q->where('paid_time', '>=', $date);
            });
        } elseif ($operator === 'before') {
            $query->whereHas('paidOrders', function (Builder $q) use ($value) {
                $q->where('paid_time', '<', $value);
            });
        } elseif ($operator === 'after') {
            $query->whereHas('paidOrders', function (Builder $q) use ($value) {
                $q->where('paid_time', '>', $value);
            });
        }
    }

    private static function applyHasPaidOrdersRule(Builder $query, mixed $value): void
    {
        if ($value === 'yes') {
            $query->has('paidOrders');
        } else {
            $query->doesntHave('paidOrders');
        }
    }

    private static function applyEnrollmentCountRule(Builder $query, string $operator, mixed $value): void
    {
        $sqlOp = self::mapOperator($operator);
        $query->has('enrollments', $sqlOp, (int) $value);
    }

    private static function applyEnrolledInCourseRule(Builder $query, string $operator, mixed $value): void
    {
        if ($operator === 'is') {
            $query->whereHas('enrollments', fn (Builder $q) => $q->where('course_id', $value));
        } else {
            $query->whereDoesntHave('enrollments', fn (Builder $q) => $q->where('course_id', $value));
        }
    }

    private static function applyEnrollmentStatusRule(Builder $query, string $operator, mixed $value): void
    {
        if ($operator === 'is') {
            $query->whereHas('enrollments', fn (Builder $q) => $q->where('status', $value));
        } else {
            $query->whereDoesntHave('enrollments', fn (Builder $q) => $q->where('status', $value));
        }
    }

    private static function applySubscriptionStatusRule(Builder $query, string $operator, mixed $value): void
    {
        if ($operator === 'is') {
            $query->whereHas('enrollments', fn (Builder $q) => $q->where('subscription_status', $value));
        } else {
            $query->whereDoesntHave('enrollments', fn (Builder $q) => $q->where('subscription_status', $value));
        }
    }

    private static function applyStudentStatusRule(Builder $query, string $operator, mixed $value): void
    {
        if ($operator === 'is') {
            $query->where('status', $value);
        } else {
            $query->where('status', '!=', $value);
        }
    }

    private static function applyCountryRule(Builder $query, string $operator, mixed $value): void
    {
        if ($operator === 'is') {
            $query->where('country', $value);
        } else {
            $query->where('country', '!=', $value);
        }
    }

    private static function applyStateRule(Builder $query, string $operator, mixed $value): void
    {
        if ($operator === 'is') {
            $query->where('state', $value);
        } else {
            $query->where('state', '!=', $value);
        }
    }

    private static function applyGenderRule(Builder $query, string $operator, mixed $value): void
    {
        if ($operator === 'is') {
            $query->where('gender', $value);
        } else {
            $query->where('gender', '!=', $value);
        }
    }

    private static function applyAgeRule(Builder $query, string $operator, mixed $value, mixed $value2): void
    {
        // Age is calculated from date_of_birth
        // age > X means date_of_birth < now - X years
        if ($operator === 'between') {
            $olderDate = now()->subYears((int) $value2)->startOfDay();
            $youngerDate = now()->subYears((int) $value)->startOfDay();
            $query->whereNotNull('date_of_birth')
                  ->where('date_of_birth', '<=', $youngerDate)
                  ->where('date_of_birth', '>=', $olderDate);
        } else {
            $sqlOp = self::mapOperator($operator);
            // Reverse logic: age > 30 means DOB < (now - 30 years)
            $reversedOp = match ($sqlOp) {
                '>' => '<',
                '<' => '>',
                '>=' => '<=',
                '<=' => '>=',
                default => $sqlOp,
            };
            $date = now()->subYears((int) $value)->startOfDay();
            $query->whereNotNull('date_of_birth')->where('date_of_birth', $reversedOp, $date);
        }
    }

    private static function applyRegisteredDateRule(Builder $query, string $operator, mixed $value): void
    {
        if ($operator === 'in_last_days') {
            $query->where('created_at', '>=', now()->subDays((int) $value)->startOfDay());
        } elseif ($operator === 'before') {
            $query->where('created_at', '<', $value);
        } elseif ($operator === 'after') {
            $query->where('created_at', '>', $value);
        }
    }

    private static function mapOperator(string $operator): string
    {
        return match ($operator) {
            '>' => '>',
            '<' => '<',
            '>=' => '>=',
            '<=' => '<=',
            '=' => '=',
            default => '=',
        };
    }
}
```

**Step 2: Verify with tinker**

Run: `php artisan tinker` and test:
```php
use App\Services\AudienceRuleBuilder;
$rules = [['field' => 'total_spending', 'operator' => '>=', 'value' => '4000', 'value2' => '']];
$count = AudienceRuleBuilder::buildQuery($rules, 'all')->count();
echo "Students with spending >= RM 4000: $count";
```

**Step 3: Commit**

```bash
git add app/Services/AudienceRuleBuilder.php
git commit -m "feat: add AudienceRuleBuilder service for advanced segmentation queries"
```

---

### Task 2: Rewrite audience-create.blade.php PHP Logic

**Files:**
- Modify: `resources/views/livewire/crm/audience-create.blade.php`

**Step 1: Replace PHP section with rule builder logic**

Replace the entire `<?php ... ?>` section with:

```php
<?php

use App\Models\Audience;
use App\Models\Course;
use App\Models\Student;
use App\Services\AudienceRuleBuilder;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
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

    public function getOperatorsForField(string $field): array
    {
        $fields = AudienceRuleBuilder::availableFields();
        return $fields[$field]['operators'] ?? [];
    }

    public function getFieldType(string $field): string
    {
        $fields = AudienceRuleBuilder::availableFields();
        return $fields[$field]['type'] ?? 'text';
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

        if (!empty($this->selectedStudents)) {
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
```

**Step 2: Verify no PHP syntax errors**

Run: `php -l resources/views/livewire/crm/audience-create.blade.php`
Expected: No syntax errors detected

**Step 3: Commit**

```bash
git add resources/views/livewire/crm/audience-create.blade.php
git commit -m "feat: replace simple filters with rule builder logic in audience-create"
```

---

### Task 3: Rewrite audience-create.blade.php Template (Rule Builder UI)

**Files:**
- Modify: `resources/views/livewire/crm/audience-create.blade.php` (template section only)

**Step 1: Replace the template section**

Replace everything from `<div>` (line after `?>`) to end of file with the rule builder UI template. Key sections:

1. **Header** - Same as current (heading + back button)
2. **Form fields** - Name, Description, Status (same as current)
3. **Rule Builder** - Replace the old filter section with:
   - Match mode toggle (ALL/ANY)
   - Dynamic rule rows with field/operator/value dropdowns
   - Add Rule / Clear Rules / Apply Rules buttons
   - Matching count display
4. **Student List** - Same paginated list with select/deselect all
5. **Footer** - Cancel and Create buttons

The rule builder section replaces the old `<!-- Filter Section -->` block. Each rule row has:
- Field dropdown (grouped by category: Spending & Orders, Enrollment & Courses, Demographics & Profile)
- Operator dropdown (changes based on selected field)
- Value input (changes based on field type: number, date, select, text)
- Optional value2 input (for "between" operator)
- Remove button

The value input dynamically renders based on `$allFields[$rule['field']]['type']`:
- `number` → `<flux:input type="number">`
- `date` → `<flux:input type="date">` (or number input for "in last X days")
- `boolean` → select with Yes/No
- `course` → select with courses list
- `enrollment_status` → select with enrollment statuses
- `subscription_status` → select with subscription statuses
- `student_status` → select with student statuses
- `country` → select with countries list
- `gender` → select with Male/Female
- `text` → `<flux:input type="text">`

**Step 2: Verify page loads**

Visit: `https://mudeerbedaie.test/admin/crm/audiences/create`
Expected: Rule builder UI renders with one empty rule row

**Step 3: Commit**

```bash
git add resources/views/livewire/crm/audience-create.blade.php
git commit -m "feat: add rule builder UI template to audience-create page"
```

---

### Task 4: Test Rule Builder End-to-End

**Step 1: Test in browser**

Visit `https://mudeerbedaie.test/admin/crm/audiences/create` and verify:
1. Add rule: Total Spending >= 4000 → Apply → shows filtered count
2. Add second rule: Student Status is Active → Apply → further filters
3. Toggle match mode between ALL/ANY → Apply → count changes
4. Select All → students selected across pages
5. Create audience → saves correctly with selected students

**Step 2: Verify with tinker**

```php
$audience = \App\Models\Audience::latest()->first();
echo "Audience: {$audience->name}, Students: {$audience->students()->count()}";
```

**Step 3: Commit any fixes**

```bash
git add -A
git commit -m "fix: address any issues found during rule builder testing"
```

---

### Task 5: Apply Same Changes to audience-edit.blade.php

**Files:**
- Modify: `resources/views/livewire/crm/audience-edit.blade.php`

**Step 1: Update PHP logic**

Mirror the same rule builder logic from audience-create, but keep the `mount()` method that loads existing audience data and pre-selects students:

```php
public function mount(Audience $audience): void
{
    $this->audience = $audience;
    $this->name = $audience->name;
    $this->description = $audience->description;
    $this->status = $audience->status;
    $this->selectedStudents = $audience->students()->pluck('students.id')->toArray();
    $this->addRule();
}
```

Keep the `save()` method using `update()` and `sync()` instead of `create()` and `attach()`.

**Step 2: Update template**

Copy the same rule builder template from audience-create, but change:
- Heading: "Edit Audience" instead of "Create Audience"
- Submit button: "Update Audience" instead of "Create Audience"

**Step 3: Test edit page**

Visit an existing audience edit page and verify rules work the same way.

**Step 4: Commit**

```bash
git add resources/views/livewire/crm/audience-edit.blade.php
git commit -m "feat: add rule builder to audience-edit page"
```

---

### Task 6: Write Tests

**Files:**
- Create: `tests/Feature/AudienceRuleBuilderTest.php`

**Step 1: Create test file**

Run: `php artisan make:test AudienceRuleBuilderTest --pest`

**Step 2: Write tests**

```php
<?php

use App\Models\Student;
use App\Models\User;
use App\Models\ProductOrder;
use App\Models\Enrollment;
use App\Models\Course;
use App\Services\AudienceRuleBuilder;

beforeEach(function () {
    // Create test students with users
    $this->user1 = User::factory()->create(['name' => 'High Spender']);
    $this->student1 = Student::factory()->create([
        'user_id' => $this->user1->id,
        'status' => 'active',
        'country' => 'Malaysia',
        'gender' => 'male',
    ]);

    $this->user2 = User::factory()->create(['name' => 'Low Spender']);
    $this->student2 = Student::factory()->create([
        'user_id' => $this->user2->id,
        'status' => 'inactive',
        'country' => 'Singapore',
        'gender' => 'female',
    ]);

    // Create paid orders for student1
    ProductOrder::factory()->create([
        'student_id' => $this->student1->id,
        'total_amount' => 5000,
        'paid_time' => now(),
    ]);
});

test('filters by total spending greater than', function () {
    $rules = [['field' => 'total_spending', 'operator' => '>=', 'value' => '4000', 'value2' => '']];
    $results = AudienceRuleBuilder::buildQuery($rules)->get();

    expect($results)->toHaveCount(1);
    expect($results->first()->id)->toBe($this->student1->id);
});

test('filters by student status', function () {
    $rules = [['field' => 'student_status', 'operator' => 'is', 'value' => 'active', 'value2' => '']];
    $results = AudienceRuleBuilder::buildQuery($rules)->get();

    expect($results)->toHaveCount(1);
    expect($results->first()->id)->toBe($this->student1->id);
});

test('filters by country', function () {
    $rules = [['field' => 'country', 'operator' => 'is', 'value' => 'Malaysia', 'value2' => '']];
    $results = AudienceRuleBuilder::buildQuery($rules)->get();

    expect($results)->toHaveCount(1);
    expect($results->first()->id)->toBe($this->student1->id);
});

test('filters by has paid orders', function () {
    $rules = [['field' => 'has_paid_orders', 'operator' => 'is', 'value' => 'no', 'value2' => '']];
    $results = AudienceRuleBuilder::buildQuery($rules)->get();

    expect($results)->toHaveCount(1);
    expect($results->first()->id)->toBe($this->student2->id);
});

test('combines rules with ALL match mode', function () {
    $rules = [
        ['field' => 'student_status', 'operator' => 'is', 'value' => 'active', 'value2' => ''],
        ['field' => 'country', 'operator' => 'is', 'value' => 'Malaysia', 'value2' => ''],
    ];
    $results = AudienceRuleBuilder::buildQuery($rules, 'all')->get();

    expect($results)->toHaveCount(1);
    expect($results->first()->id)->toBe($this->student1->id);
});

test('combines rules with ANY match mode', function () {
    $rules = [
        ['field' => 'student_status', 'operator' => 'is', 'value' => 'active', 'value2' => ''],
        ['field' => 'country', 'operator' => 'is', 'value' => 'Singapore', 'value2' => ''],
    ];
    $results = AudienceRuleBuilder::buildQuery($rules, 'any')->get();

    expect($results)->toHaveCount(2);
});

test('returns all students when no rules applied', function () {
    $results = AudienceRuleBuilder::buildQuery([])->get();
    expect($results)->toHaveCount(2);
});

test('ignores incomplete rules', function () {
    $rules = [['field' => '', 'operator' => '', 'value' => '', 'value2' => '']];
    $results = AudienceRuleBuilder::buildQuery($rules)->get();
    expect($results)->toHaveCount(2);
});
```

**Step 3: Run tests**

Run: `php artisan test --compact tests/Feature/AudienceRuleBuilderTest.php`
Expected: All tests pass

**Step 4: Commit**

```bash
git add tests/Feature/AudienceRuleBuilderTest.php
git commit -m "test: add AudienceRuleBuilder service tests"
```

---

### Task 7: Run Pint and Final Verification

**Step 1: Format code**

Run: `vendor/bin/pint --dirty`

**Step 2: Run full test suite**

Run: `php artisan test --compact`
Expected: All tests pass

**Step 3: Final commit**

```bash
git add -A
git commit -m "style: format code with Pint"
```
