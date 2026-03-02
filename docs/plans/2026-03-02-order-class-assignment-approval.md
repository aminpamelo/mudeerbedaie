# Order-to-Class Assignment with Approval List — Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Allow admins to assign product orders to classes from the order detail page, with an approval queue on the class page where admins can approve (enroll) or reject assignments.

**Architecture:** New `class_assignment_approvals` table stores pending assignments. Order detail page gets an "Assign to Class" card with a modal. Class show page gets an "Approval List" tab between Students and Timetable. Approval creates `class_students` record and optionally an `Enrollment`.

**Tech Stack:** Laravel 12, Livewire Volt (class-based), Flux UI Free, Pest 4

---

### Task 1: Migration — Create `class_assignment_approvals` Table

**Files:**
- Create: `database/migrations/YYYY_MM_DD_HHMMSS_create_class_assignment_approvals_table.php`

**Step 1: Create migration**

Run:
```bash
php artisan make:migration create_class_assignment_approvals_table --no-interaction
```

**Step 2: Write migration schema**

Edit the generated migration file:

```php
public function up(): void
{
    Schema::create('class_assignment_approvals', function (Blueprint $table) {
        $table->id();
        $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
        $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
        $table->foreignId('product_order_id')->constrained('product_orders')->cascadeOnDelete();
        $table->string('status')->default('pending'); // pending, approved, rejected
        $table->boolean('enroll_with_subscription')->default(false);
        $table->foreignId('assigned_by')->constrained('users');
        $table->foreignId('approved_by')->nullable()->constrained('users');
        $table->text('notes')->nullable();
        $table->timestamp('approved_at')->nullable();
        $table->timestamps();

        $table->unique(['class_id', 'student_id', 'product_order_id'], 'class_student_order_unique');
    });
}

public function down(): void
{
    Schema::dropIfExists('class_assignment_approvals');
}
```

**Step 3: Run migration**

Run: `php artisan migrate`
Expected: Migration runs successfully.

**Step 4: Commit**

```bash
git add database/migrations/*create_class_assignment_approvals*
git commit -m "feat: add class_assignment_approvals migration"
```

---

### Task 2: Model + Factory — `ClassAssignmentApproval`

**Files:**
- Create: `app/Models/ClassAssignmentApproval.php`
- Create: `database/factories/ClassAssignmentApprovalFactory.php`

**Step 1: Create model with factory**

Run:
```bash
php artisan make:model ClassAssignmentApproval --factory --no-interaction
```

**Step 2: Write the model**

Edit `app/Models/ClassAssignmentApproval.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClassAssignmentApproval extends Model
{
    use HasFactory;

    protected $fillable = [
        'class_id',
        'student_id',
        'product_order_id',
        'status',
        'enroll_with_subscription',
        'assigned_by',
        'approved_by',
        'notes',
        'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'enroll_with_subscription' => 'boolean',
            'approved_at' => 'datetime',
        ];
    }

    // Relationships

    public function class(): BelongsTo
    {
        return $this->belongsTo(ClassModel::class, 'class_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function productOrder(): BelongsTo
    {
        return $this->belongsTo(ProductOrder::class);
    }

    public function assignedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function approvedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    // Scopes

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }

    // Actions

    public function approve(User $approvedBy, bool $enrollWithSubscription = false): void
    {
        $class = $this->class;
        $student = $this->student;
        $order = $this->productOrder;

        // Create class_students record
        $class->addStudent($student, $order->order_number);

        // Optionally create course-level enrollment
        if ($enrollWithSubscription && $class->course_id) {
            $existingEnrollment = Enrollment::where('student_id', $student->id)
                ->where('course_id', $class->course_id)
                ->first();

            if (!$existingEnrollment) {
                Enrollment::create([
                    'student_id' => $student->id,
                    'course_id' => $class->course_id,
                    'enrolled_by' => $approvedBy->id,
                    'status' => 'active',
                    'academic_status' => \App\Enums\AcademicStatus::ACTIVE,
                    'payment_method_type' => 'manual',
                    'enrollment_date' => now(),
                    'start_date' => now(),
                ]);
            }
        }

        $this->update([
            'status' => 'approved',
            'enroll_with_subscription' => $enrollWithSubscription,
            'approved_by' => $approvedBy->id,
            'approved_at' => now(),
        ]);
    }

    public function reject(User $rejectedBy, ?string $notes = null): void
    {
        $this->update([
            'status' => 'rejected',
            'approved_by' => $rejectedBy->id,
            'approved_at' => now(),
            'notes' => $notes,
        ]);
    }
}
```

**Step 3: Write the factory**

Edit `database/factories/ClassAssignmentApprovalFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\ClassAssignmentApproval;
use App\Models\ClassModel;
use App\Models\ProductOrder;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ClassAssignmentApprovalFactory extends Factory
{
    protected $model = ClassAssignmentApproval::class;

    public function definition(): array
    {
        return [
            'class_id' => ClassModel::factory(),
            'student_id' => Student::factory(),
            'product_order_id' => ProductOrder::factory(),
            'status' => 'pending',
            'enroll_with_subscription' => false,
            'assigned_by' => User::factory(),
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'approved',
            'approved_by' => User::factory(),
            'approved_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'rejected',
            'approved_by' => User::factory(),
            'approved_at' => now(),
            'notes' => $this->faker->sentence(),
        ]);
    }
}
```

**Step 4: Run tests to verify model works**

Run: `php artisan tinker --execute="App\Models\ClassAssignmentApproval::query()->count()"`
Expected: Returns `0` (table exists, no records).

**Step 5: Commit**

```bash
git add app/Models/ClassAssignmentApproval.php database/factories/ClassAssignmentApprovalFactory.php
git commit -m "feat: add ClassAssignmentApproval model and factory"
```

---

### Task 3: Add Relationships to Existing Models

**Files:**
- Modify: `app/Models/ProductOrder.php` (add `classAssignmentApprovals` relationship)
- Modify: `app/Models/ClassModel.php` (add `assignmentApprovals` and `pendingApprovals` relationships)
- Modify: `app/Models/Student.php` (add `classAssignmentApprovals` relationship)

**Step 1: Add relationship to ProductOrder**

In `app/Models/ProductOrder.php`, add after the existing relationships (around line 195):

```php
public function classAssignmentApprovals(): HasMany
{
    return $this->hasMany(ClassAssignmentApproval::class);
}
```

Add `use Illuminate\Database\Eloquent\Relations\HasMany;` import if not already present (check existing imports). Also add `use App\Models\ClassAssignmentApproval;` import.

**Step 2: Add relationships to ClassModel**

In `app/Models/ClassModel.php`, add after the existing relationships (around line 120):

```php
public function assignmentApprovals(): HasMany
{
    return $this->hasMany(ClassAssignmentApproval::class, 'class_id');
}

public function pendingApprovals(): HasMany
{
    return $this->hasMany(ClassAssignmentApproval::class, 'class_id')->where('status', 'pending');
}
```

Add `use App\Models\ClassAssignmentApproval;` import.

**Step 3: Add relationship to Student**

In `app/Models/Student.php`, add after the existing relationships (around line 165):

```php
public function classAssignmentApprovals(): HasMany
{
    return $this->hasMany(ClassAssignmentApproval::class);
}
```

Add `use App\Models\ClassAssignmentApproval;` import.

**Step 4: Commit**

```bash
git add app/Models/ProductOrder.php app/Models/ClassModel.php app/Models/Student.php
git commit -m "feat: add ClassAssignmentApproval relationships to existing models"
```

---

### Task 4: Tests — ClassAssignmentApproval Model

**Files:**
- Create: `tests/Feature/ClassAssignmentApprovalTest.php`

**Step 1: Create the test file**

Run:
```bash
php artisan make:test ClassAssignmentApprovalTest --pest --no-interaction
```

**Step 2: Write tests**

Edit `tests/Feature/ClassAssignmentApprovalTest.php`:

```php
<?php

declare(strict_types=1);

use App\Enums\AcademicStatus;
use App\Models\ClassAssignmentApproval;
use App\Models\ClassModel;
use App\Models\ClassStudent;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\ProductOrder;
use App\Models\Student;
use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('can create a pending class assignment approval', function () {
    $approval = ClassAssignmentApproval::factory()->create();

    expect($approval->status)->toBe('pending')
        ->and($approval->class)->toBeInstanceOf(ClassModel::class)
        ->and($approval->student)->toBeInstanceOf(Student::class)
        ->and($approval->productOrder)->toBeInstanceOf(ProductOrder::class)
        ->and($approval->assignedByUser)->toBeInstanceOf(User::class)
        ->and($approval->approved_by)->toBeNull()
        ->and($approval->approved_at)->toBeNull();
});

test('approving an assignment enrolls student in class', function () {
    $class = ClassModel::factory()->create(['status' => 'active']);
    $student = Student::factory()->create();
    $order = ProductOrder::factory()->create(['student_id' => $student->id]);
    $admin = User::factory()->create();

    $approval = ClassAssignmentApproval::factory()->create([
        'class_id' => $class->id,
        'student_id' => $student->id,
        'product_order_id' => $order->id,
    ]);

    $approval->approve($admin);

    expect($approval->fresh()->status)->toBe('approved')
        ->and($approval->fresh()->approved_by)->toBe($admin->id)
        ->and($approval->fresh()->approved_at)->not->toBeNull();

    $classStudent = ClassStudent::where('class_id', $class->id)
        ->where('student_id', $student->id)
        ->first();

    expect($classStudent)->not->toBeNull()
        ->and($classStudent->status)->toBe('active')
        ->and($classStudent->order_id)->toBe($order->order_number);
});

test('approving with subscription creates course enrollment', function () {
    $course = Course::factory()->create();
    $class = ClassModel::factory()->create(['course_id' => $course->id, 'status' => 'active']);
    $student = Student::factory()->create();
    $order = ProductOrder::factory()->create(['student_id' => $student->id]);
    $admin = User::factory()->create();

    $approval = ClassAssignmentApproval::factory()->create([
        'class_id' => $class->id,
        'student_id' => $student->id,
        'product_order_id' => $order->id,
    ]);

    $approval->approve($admin, enrollWithSubscription: true);

    $enrollment = Enrollment::where('student_id', $student->id)
        ->where('course_id', $course->id)
        ->first();

    expect($enrollment)->not->toBeNull()
        ->and($enrollment->academic_status)->toBe(AcademicStatus::ACTIVE)
        ->and($enrollment->payment_method_type)->toBe('manual');
});

test('approving with subscription does not duplicate existing enrollment', function () {
    $course = Course::factory()->create();
    $class = ClassModel::factory()->create(['course_id' => $course->id, 'status' => 'active']);
    $student = Student::factory()->create();
    $order = ProductOrder::factory()->create(['student_id' => $student->id]);
    $admin = User::factory()->create();

    // Pre-existing enrollment
    Enrollment::factory()->create([
        'student_id' => $student->id,
        'course_id' => $course->id,
        'academic_status' => AcademicStatus::ACTIVE,
    ]);

    $approval = ClassAssignmentApproval::factory()->create([
        'class_id' => $class->id,
        'student_id' => $student->id,
        'product_order_id' => $order->id,
    ]);

    $approval->approve($admin, enrollWithSubscription: true);

    $enrollmentCount = Enrollment::where('student_id', $student->id)
        ->where('course_id', $course->id)
        ->count();

    expect($enrollmentCount)->toBe(1);
});

test('rejecting an assignment updates status and notes', function () {
    $admin = User::factory()->create();
    $approval = ClassAssignmentApproval::factory()->create();

    $approval->reject($admin, 'Student does not meet requirements');

    expect($approval->fresh()->status)->toBe('rejected')
        ->and($approval->fresh()->approved_by)->toBe($admin->id)
        ->and($approval->fresh()->approved_at)->not->toBeNull()
        ->and($approval->fresh()->notes)->toBe('Student does not meet requirements');
});

test('scopes filter by status correctly', function () {
    ClassAssignmentApproval::factory()->count(3)->create(['status' => 'pending']);
    ClassAssignmentApproval::factory()->count(2)->approved()->create();
    ClassAssignmentApproval::factory()->count(1)->rejected()->create();

    expect(ClassAssignmentApproval::pending()->count())->toBe(3)
        ->and(ClassAssignmentApproval::approved()->count())->toBe(2)
        ->and(ClassAssignmentApproval::rejected()->count())->toBe(1);
});

test('product order has class assignment approvals relationship', function () {
    $order = ProductOrder::factory()->create();
    ClassAssignmentApproval::factory()->count(2)->create(['product_order_id' => $order->id]);

    expect($order->classAssignmentApprovals)->toHaveCount(2);
});

test('class model has pending approvals relationship', function () {
    $class = ClassModel::factory()->create();
    ClassAssignmentApproval::factory()->count(2)->create(['class_id' => $class->id, 'status' => 'pending']);
    ClassAssignmentApproval::factory()->approved()->create(['class_id' => $class->id]);

    expect($class->pendingApprovals)->toHaveCount(2)
        ->and($class->assignmentApprovals)->toHaveCount(3);
});
```

**Step 3: Run tests**

Run: `php artisan test --compact tests/Feature/ClassAssignmentApprovalTest.php`
Expected: All tests pass.

**Step 4: Commit**

```bash
git add tests/Feature/ClassAssignmentApprovalTest.php
git commit -m "test: add ClassAssignmentApproval model tests"
```

---

### Task 5: Order Detail Page — "Class Assignment" Card

**Files:**
- Modify: `resources/views/livewire/admin/orders/order-show.blade.php`

**Step 1: Add properties and methods to the PHP class**

In the Volt component's PHP class section (top of `order-show.blade.php`), add these properties and methods:

```php
// Properties (add alongside existing properties)
public bool $showAssignClassModal = false;
public string $classSearch = '';
public array $selectedClassIds = [];

// Method: open assign modal
public function openAssignClassModal(): void
{
    $this->showAssignClassModal = true;
    $this->classSearch = '';
    $this->selectedClassIds = [];
}

// Computed: available classes grouped by course
public function getAvailableClassesProperty()
{
    $alreadyAssignedClassIds = $this->order->classAssignmentApprovals()
        ->whereIn('status', ['pending', 'approved'])
        ->pluck('class_id')
        ->toArray();

    $query = \App\Models\ClassModel::query()
        ->where('status', 'active')
        ->whereNotIn('id', $alreadyAssignedClassIds)
        ->with('course');

    if ($this->classSearch) {
        $query->where(function ($q) {
            $q->where('title', 'like', "%{$this->classSearch}%")
              ->orWhereHas('course', fn ($cq) => $cq->where('name', 'like', "%{$this->classSearch}%"));
        });
    }

    return $query->get()->groupBy(fn ($class) => $class->course?->name ?? 'No Course');
}

// Computed: suggested class IDs (classes linked to courses of ordered products)
public function getSuggestedClassIdsProperty(): array
{
    // Get course IDs from the order's products if any product-to-course mapping exists
    // Check if products link to classes via shipment_product_id on classes table
    $productIds = $this->order->items->pluck('product_id')->filter()->toArray();

    if (empty($productIds)) {
        return [];
    }

    return \App\Models\ClassModel::whereIn('shipment_product_id', $productIds)
        ->where('status', 'active')
        ->pluck('id')
        ->toArray();
}

// Method: assign order to selected classes
public function assignToClasses(): void
{
    if (empty($this->selectedClassIds)) {
        return;
    }

    $student = $this->order->student;

    if (!$student) {
        session()->flash('error', 'This order has no student linked. Please link a student first.');
        return;
    }

    foreach ($this->selectedClassIds as $classId) {
        \App\Models\ClassAssignmentApproval::firstOrCreate(
            [
                'class_id' => $classId,
                'student_id' => $student->id,
                'product_order_id' => $this->order->id,
            ],
            [
                'status' => 'pending',
                'assigned_by' => auth()->id(),
            ]
        );
    }

    $this->showAssignClassModal = false;
    $this->selectedClassIds = [];
    session()->flash('message', 'Order assigned to ' . count($this->selectedClassIds) . ' class(es) for approval.');
}

// Method: toggle class selection
public function toggleClassSelection(int $classId): void
{
    if (in_array($classId, $this->selectedClassIds)) {
        $this->selectedClassIds = array_values(array_diff($this->selectedClassIds, [$classId]));
    } else {
        $this->selectedClassIds[] = $classId;
    }
}
```

**Step 2: Add the "Class Assignment" card to the Blade template**

Insert this card AFTER the Order Items section (after the `</div>` that closes the Order Items card, around line 900):

```blade
<!-- Class Assignment -->
<div class="bg-white dark:bg-zinc-800 rounded-lg shadow-sm border border-gray-200 dark:border-zinc-700 p-6">
    <div class="flex items-center justify-between mb-4">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Class Assignment</h3>
        <flux:button variant="primary" size="sm" wire:click="openAssignClassModal">
            <div class="flex items-center justify-center">
                <flux:icon name="academic-cap" class="w-4 h-4 mr-1" />
                Assign to Class
            </div>
        </flux:button>
    </div>

    @php
        $assignments = $order->classAssignmentApprovals()->with(['class.course', 'assignedByUser'])->latest()->get();
    @endphp

    @if($assignments->isNotEmpty())
        <div class="space-y-3">
            @foreach($assignments as $assignment)
                <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-zinc-700/50 rounded-lg">
                    <div>
                        <p class="text-sm font-medium text-gray-900 dark:text-white">
                            {{ $assignment->class->title }}
                        </p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            {{ $assignment->class->course?->name ?? 'No Course' }}
                            &middot; Assigned by {{ $assignment->assignedByUser->name }}
                            &middot; {{ $assignment->created_at->diffForHumans() }}
                        </p>
                    </div>
                    <flux:badge
                        :variant="match($assignment->status) {
                            'pending' => 'warning',
                            'approved' => 'success',
                            'rejected' => 'danger',
                            default => 'default',
                        }"
                        size="sm"
                    >
                        {{ ucfirst($assignment->status) }}
                    </flux:badge>
                </div>
            @endforeach
        </div>
    @else
        <p class="text-sm text-gray-500 dark:text-gray-400">No class assignments yet.</p>
    @endif
</div>

<!-- Assign to Class Modal -->
<flux:modal wire:model="showAssignClassModal" class="max-w-lg">
    <div class="space-y-4">
        <flux:heading size="lg">Assign Order to Class</flux:heading>
        <flux:text>Select classes to assign this order for approval.</flux:text>

        @if(!$order->student)
            <flux:callout variant="danger">
                This order has no student linked. Please link a student before assigning to a class.
            </flux:callout>
        @else
            <flux:input
                wire:model.live.debounce.300ms="classSearch"
                placeholder="Search classes or courses..."
                icon="magnifying-glass"
            />

            <div class="max-h-80 overflow-y-auto space-y-4">
                @forelse($this->availableClasses as $courseName => $classes)
                    <div>
                        <h4 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2">
                            {{ $courseName }}
                        </h4>
                        <div class="space-y-1">
                            @foreach($classes as $class)
                                <label
                                    class="flex items-center gap-3 p-2 rounded-lg cursor-pointer hover:bg-gray-50 dark:hover:bg-zinc-700/50 transition-colors"
                                    wire:key="class-{{ $class->id }}"
                                >
                                    <flux:checkbox
                                        wire:click="toggleClassSelection({{ $class->id }})"
                                        :checked="in_array($class->id, $selectedClassIds)"
                                    />
                                    <div class="flex-1">
                                        <span class="text-sm font-medium text-gray-900 dark:text-white">{{ $class->title }}</span>
                                        @if($class->max_capacity)
                                            <span class="text-xs text-gray-500 dark:text-gray-400 ml-2">
                                                ({{ $class->activeStudents->count() }}/{{ $class->max_capacity }})
                                            </span>
                                        @endif
                                    </div>
                                    @if(in_array($class->id, $this->suggestedClassIds))
                                        <flux:badge variant="success" size="sm">Suggested</flux:badge>
                                    @endif
                                </label>
                            @endforeach
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-gray-500 dark:text-gray-400 text-center py-4">No classes available.</p>
                @endforelse
            </div>

            <div class="flex justify-end gap-2 pt-4 border-t dark:border-zinc-700">
                <flux:button variant="ghost" wire:click="$set('showAssignClassModal', false)">Cancel</flux:button>
                <flux:button
                    variant="primary"
                    wire:click="assignToClasses"
                    :disabled="empty($selectedClassIds)"
                >
                    Assign to {{ count($selectedClassIds) }} Class(es)
                </flux:button>
            </div>
        @endif
    </div>
</flux:modal>
```

**Step 3: Run lint**

Run: `vendor/bin/pint --dirty`

**Step 4: Commit**

```bash
git add resources/views/livewire/admin/orders/order-show.blade.php
git commit -m "feat: add class assignment card and modal to order detail page"
```

---

### Task 6: Class Show Page — "Approval List" Tab

**Files:**
- Modify: `resources/views/livewire/admin/class-show.blade.php`

**Step 1: Add properties and methods to the PHP class**

In the Volt component's PHP class section, add:

```php
// Properties
public array $approvalSubscriptionToggles = [];
public array $selectedApprovalIds = [];

// Computed: pending approvals for this class
public function getPendingApprovalsProperty()
{
    return \App\Models\ClassAssignmentApproval::where('class_id', $this->class->id)
        ->pending()
        ->with(['student', 'productOrder', 'assignedByUser'])
        ->latest()
        ->get();
}

// Method: approve a single assignment
public function approveAssignment(int $approvalId): void
{
    $approval = \App\Models\ClassAssignmentApproval::findOrFail($approvalId);
    $enrollWithSubscription = $this->approvalSubscriptionToggles[$approvalId] ?? false;
    $approval->approve(auth()->user(), $enrollWithSubscription);
    session()->flash('message', 'Student enrolled successfully.');
}

// Method: reject a single assignment
public function rejectAssignment(int $approvalId, ?string $notes = null): void
{
    $approval = \App\Models\ClassAssignmentApproval::findOrFail($approvalId);
    $approval->reject(auth()->user(), $notes);
    session()->flash('message', 'Assignment rejected.');
}

// Method: bulk approve
public function bulkApproveAssignments(): void
{
    foreach ($this->selectedApprovalIds as $id) {
        $approval = \App\Models\ClassAssignmentApproval::find($id);
        if ($approval && $approval->status === 'pending') {
            $enrollWithSubscription = $this->approvalSubscriptionToggles[$id] ?? false;
            $approval->approve(auth()->user(), $enrollWithSubscription);
        }
    }
    $this->selectedApprovalIds = [];
    session()->flash('message', 'Selected students enrolled successfully.');
}

// Method: bulk reject
public function bulkRejectAssignments(): void
{
    foreach ($this->selectedApprovalIds as $id) {
        $approval = \App\Models\ClassAssignmentApproval::find($id);
        if ($approval && $approval->status === 'pending') {
            $approval->reject(auth()->user());
        }
    }
    $this->selectedApprovalIds = [];
    session()->flash('message', 'Selected assignments rejected.');
}

// Method: toggle approval selection
public function toggleApprovalSelection(int $approvalId): void
{
    if (in_array($approvalId, $this->selectedApprovalIds)) {
        $this->selectedApprovalIds = array_values(array_diff($this->selectedApprovalIds, [$approvalId]));
    } else {
        $this->selectedApprovalIds[] = $approvalId;
    }
}

// Method: toggle subscription for an approval
public function toggleApprovalSubscription(int $approvalId): void
{
    $this->approvalSubscriptionToggles[$approvalId] = !($this->approvalSubscriptionToggles[$approvalId] ?? false);
}
```

**Step 2: Add the "Approval List" tab button**

In the tab header nav (around line 3529, between the Students and Timetable buttons), add:

```blade
<button
    wire:click="setActiveTab('approval-list')"
    class="whitespace-nowrap pb-4 px-1 border-b-2 font-medium text-sm
           {{ $activeTab === 'approval-list' ? 'border-blue-500 text-blue-600'
                                              : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}"
>
    <div class="flex items-center gap-2">
        <flux:icon.clipboard-document-check class="h-4 w-4" />
        Approval List
        @if($this->pendingApprovals->count() > 0)
            <span class="ml-1 px-2 py-0.5 text-xs font-semibold bg-amber-100 text-amber-800 rounded-full">
                {{ $this->pendingApprovals->count() }}
            </span>
        @endif
    </div>
</button>
```

**Step 3: Add the "Approval List" tab content**

After the Students tab content div and before the Timetable tab content div, add:

```blade
<!-- Approval List Tab -->
<div class="{{ $activeTab === 'approval-list' ? 'block' : 'hidden' }}">
    <div class="bg-white dark:bg-zinc-800 rounded-lg shadow-sm border border-gray-200 dark:border-zinc-700 p-6">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Pending Approvals</h3>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                    Students assigned from orders awaiting enrollment approval.
                </p>
            </div>
            @if($this->pendingApprovals->count() > 0 && count($selectedApprovalIds) > 0)
                <div class="flex gap-2">
                    <flux:button variant="primary" size="sm" wire:click="bulkApproveAssignments">
                        Approve Selected ({{ count($selectedApprovalIds) }})
                    </flux:button>
                    <flux:button variant="danger" size="sm" wire:click="bulkRejectAssignments">
                        Reject Selected ({{ count($selectedApprovalIds) }})
                    </flux:button>
                </div>
            @endif
        </div>

        @if($this->pendingApprovals->count() > 0)
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-zinc-700">
                    <thead>
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">
                                <flux:checkbox
                                    wire:click="$set('selectedApprovalIds', count($selectedApprovalIds) === $this->pendingApprovals->count() ? [] : $this->pendingApprovals->pluck('id')->toArray())"
                                    :checked="count($selectedApprovalIds) > 0 && count($selectedApprovalIds) === $this->pendingApprovals->count()"
                                />
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Student</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Order</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Assigned By</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Assigned Date</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Subscription</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-zinc-700">
                        @foreach($this->pendingApprovals as $approval)
                            <tr wire:key="approval-{{ $approval->id }}">
                                <td class="px-4 py-3">
                                    <flux:checkbox
                                        wire:click="toggleApprovalSelection({{ $approval->id }})"
                                        :checked="in_array($approval->id, $selectedApprovalIds)"
                                    />
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-3">
                                        <flux:avatar size="sm" name="{{ $approval->student->user?->name ?? $approval->student->name ?? 'Unknown' }}" />
                                        <div>
                                            <p class="text-sm font-medium text-gray-900 dark:text-white">
                                                {{ $approval->student->user?->name ?? $approval->student->name ?? 'Unknown' }}
                                            </p>
                                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                                {{ $approval->student->phone ?? $approval->student->user?->phone ?? '' }}
                                            </p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <a href="{{ route('admin.orders.show', $approval->productOrder) }}"
                                       class="text-sm text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300 font-medium">
                                        {{ $approval->productOrder->order_number }}
                                    </a>
                                </td>
                                <td class="px-4 py-3">
                                    <p class="text-sm text-gray-900 dark:text-white">{{ $approval->assignedByUser->name }}</p>
                                </td>
                                <td class="px-4 py-3">
                                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ $approval->created_at->format('M d, Y') }}</p>
                                </td>
                                <td class="px-4 py-3">
                                    <flux:switch
                                        wire:click="toggleApprovalSubscription({{ $approval->id }})"
                                        :checked="$approvalSubscriptionToggles[$approval->id] ?? false"
                                    />
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex gap-2">
                                        <flux:button variant="primary" size="sm" wire:click="approveAssignment({{ $approval->id }})">
                                            Approve
                                        </flux:button>
                                        <flux:button variant="danger" size="sm" wire:click="rejectAssignment({{ $approval->id }})">
                                            Reject
                                        </flux:button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="text-center py-8">
                <flux:icon.clipboard-document-check class="h-12 w-12 text-gray-300 dark:text-zinc-600 mx-auto mb-3" />
                <p class="text-sm text-gray-500 dark:text-gray-400">No pending approvals.</p>
                <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">Assignments from orders will appear here.</p>
            </div>
        @endif
    </div>
</div>
```

**Step 4: Run lint**

Run: `vendor/bin/pint --dirty`

**Step 5: Commit**

```bash
git add resources/views/livewire/admin/class-show.blade.php
git commit -m "feat: add Approval List tab to class show page"
```

---

### Task 7: Feature Test — Order Assignment Flow

**Files:**
- Create: `tests/Feature/OrderClassAssignmentFlowTest.php`

**Step 1: Create the test file**

Run:
```bash
php artisan make:test OrderClassAssignmentFlowTest --pest --no-interaction
```

**Step 2: Write integration tests**

```php
<?php

declare(strict_types=1);

use App\Models\ClassAssignmentApproval;
use App\Models\ClassModel;
use App\Models\ClassStudent;
use App\Models\Course;
use App\Models\ProductOrder;
use App\Models\Student;
use App\Models\User;
use Livewire\Volt\Volt;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('admin can assign an order to classes from order detail page', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $student = Student::factory()->create();
    $order = ProductOrder::factory()->create(['student_id' => $student->id]);
    $class1 = ClassModel::factory()->create(['status' => 'active']);
    $class2 = ClassModel::factory()->create(['status' => 'active']);

    $this->actingAs($admin);

    // Test the Volt component
    Volt::test('admin.orders.order-show', ['order' => $order])
        ->call('openAssignClassModal')
        ->assertSet('showAssignClassModal', true)
        ->call('toggleClassSelection', $class1->id)
        ->call('toggleClassSelection', $class2->id)
        ->call('assignToClasses');

    expect(ClassAssignmentApproval::where('product_order_id', $order->id)->count())->toBe(2);
    expect(ClassAssignmentApproval::where('product_order_id', $order->id)->first()->status)->toBe('pending');
});

test('admin can approve assignment from class approval list tab', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $class = ClassModel::factory()->create(['status' => 'active']);
    $student = Student::factory()->create();
    $order = ProductOrder::factory()->create(['student_id' => $student->id]);

    $approval = ClassAssignmentApproval::factory()->create([
        'class_id' => $class->id,
        'student_id' => $student->id,
        'product_order_id' => $order->id,
    ]);

    $this->actingAs($admin);

    Volt::test('admin.class-show', ['class' => $class])
        ->call('setActiveTab', 'approval-list')
        ->call('approveAssignment', $approval->id);

    expect($approval->fresh()->status)->toBe('approved');
    expect(ClassStudent::where('class_id', $class->id)->where('student_id', $student->id)->exists())->toBeTrue();
});

test('admin can reject assignment from class approval list tab', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $class = ClassModel::factory()->create(['status' => 'active']);

    $approval = ClassAssignmentApproval::factory()->create([
        'class_id' => $class->id,
    ]);

    $this->actingAs($admin);

    Volt::test('admin.class-show', ['class' => $class])
        ->call('setActiveTab', 'approval-list')
        ->call('rejectAssignment', $approval->id);

    expect($approval->fresh()->status)->toBe('rejected');
});

test('duplicate assignment to same class is prevented', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $student = Student::factory()->create();
    $order = ProductOrder::factory()->create(['student_id' => $student->id]);
    $class = ClassModel::factory()->create(['status' => 'active']);

    ClassAssignmentApproval::factory()->create([
        'class_id' => $class->id,
        'student_id' => $student->id,
        'product_order_id' => $order->id,
        'status' => 'pending',
    ]);

    $this->actingAs($admin);

    Volt::test('admin.orders.order-show', ['order' => $order])
        ->call('openAssignClassModal')
        ->call('toggleClassSelection', $class->id)
        ->call('assignToClasses');

    // Should still be just 1 record (firstOrCreate prevents duplicate)
    expect(ClassAssignmentApproval::where('product_order_id', $order->id)->count())->toBe(1);
});
```

**Step 3: Run tests**

Run: `php artisan test --compact tests/Feature/OrderClassAssignmentFlowTest.php`
Expected: All tests pass.

**Step 4: Commit**

```bash
git add tests/Feature/OrderClassAssignmentFlowTest.php
git commit -m "test: add order-to-class assignment flow integration tests"
```

---

### Task 8: Final Polish and Full Test Run

**Step 1: Run Pint on all changed files**

Run: `vendor/bin/pint --dirty`

**Step 2: Run all related tests**

Run:
```bash
php artisan test --compact tests/Feature/ClassAssignmentApprovalTest.php tests/Feature/OrderClassAssignmentFlowTest.php
```
Expected: All tests pass.

**Step 3: Ask user if they want to run the full test suite**

Run: `php artisan test --compact`
Expected: All tests pass with no regressions.

**Step 4: Final commit if any lint fixes**

```bash
git add -A
git commit -m "style: apply Pint formatting to assignment approval feature"
```
