# Tier Approval System Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Add sequential tier-based approval to department approver system so requests flow Tier 1 → Tier 2 → ... → Tier N before being fully approved.

**Architecture:** Add `tier` column to existing `department_approvers` table. Add `current_approval_tier` to all request tables. Create `approval_logs` polymorphic table for audit trail. Update controller to accept tier-grouped payloads. Update React modal to show per-tier approver selectors.

**Tech Stack:** Laravel 12, React (TanStack Query), MySQL/SQLite dual-compat migrations

---

### Task 1: Database Migration — Add `tier` to `department_approvers`

**Files:**
- Create: `database/migrations/2026_04_08_000001_add_tier_to_department_approvers_table.php`
- Modify: `app/Models/DepartmentApprover.php:15` (add `tier` to fillable)

**Step 1: Create migration**

```bash
php artisan make:migration add_tier_to_department_approvers_table --no-interaction
```

**Step 2: Write migration content**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('department_approvers', function (Blueprint $table) {
            $table->unsignedInteger('tier')->default(1)->after('approval_type');
        });
    }

    public function down(): void
    {
        Schema::table('department_approvers', function (Blueprint $table) {
            $table->dropColumn('tier');
        });
    }
};
```

**Step 3: Update DepartmentApprover model**

In `app/Models/DepartmentApprover.php`, add `'tier'` to the `$fillable` array:

```php
protected $fillable = [
    'department_id',
    'approver_employee_id',
    'approval_type',
    'tier',
];
```

Add a scope:

```php
public function scopeForTier(Builder $query, int $tier): Builder
{
    return $query->where('tier', $tier);
}
```

**Step 4: Run migration**

```bash
php artisan migrate
```

**Step 5: Commit**

```bash
git add database/migrations/*add_tier_to_department_approvers* app/Models/DepartmentApprover.php
git commit -m "feat(hr): add tier column to department_approvers table"
```

---

### Task 2: Database Migration — Create `approval_logs` table

**Files:**
- Create: `database/migrations/2026_04_08_000002_create_approval_logs_table.php`
- Create: `app/Models/ApprovalLog.php`

**Step 1: Create migration and model**

```bash
php artisan make:model ApprovalLog -m --no-interaction
```

**Step 2: Write migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_logs', function (Blueprint $table) {
            $table->id();
            $table->morphs('approvable');
            $table->unsignedInteger('tier');
            $table->foreignId('approver_id')->constrained('employees')->cascadeOnDelete();
            $table->string('action'); // approved, rejected
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['approvable_type', 'approvable_id', 'tier']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_logs');
    }
};
```

**Step 3: Write ApprovalLog model**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ApprovalLog extends Model
{
    protected $fillable = [
        'approvable_type',
        'approvable_id',
        'tier',
        'approver_id',
        'action',
        'notes',
    ];

    public function approvable(): MorphTo
    {
        return $this->morphTo();
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'approver_id');
    }
}
```

**Step 4: Run migration**

```bash
php artisan migrate
```

**Step 5: Commit**

```bash
git add database/migrations/*create_approval_logs* app/Models/ApprovalLog.php
git commit -m "feat(hr): create approval_logs table for tier audit trail"
```

---

### Task 3: Database Migration — Add `current_approval_tier` to request tables

**Files:**
- Create: `database/migrations/2026_04_08_000003_add_current_approval_tier_to_request_tables.php`
- Modify: `app/Models/OvertimeRequest.php` (add to fillable + casts)
- Modify: `app/Models/OvertimeClaimRequest.php` (add to fillable + casts)
- Modify: `app/Models/LeaveRequest.php` (add to fillable + casts)
- Modify: `app/Models/ClaimRequest.php` (add to fillable + casts)
- Modify: `app/Models/OfficeExitPermission.php` (add to fillable + casts)

**Step 1: Create migration**

```bash
php artisan make:migration add_current_approval_tier_to_request_tables --no-interaction
```

**Step 2: Write migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tables = [
            'overtime_requests',
            'overtime_claim_requests',
            'leave_requests',
            'claim_requests',
            'office_exit_permissions',
        ];

        foreach ($tables as $tableName) {
            if (Schema::hasTable($tableName) && !Schema::hasColumn($tableName, 'current_approval_tier')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->unsignedInteger('current_approval_tier')->default(1)->after('status');
                });
            }
        }
    }

    public function down(): void
    {
        $tables = [
            'overtime_requests',
            'overtime_claim_requests',
            'leave_requests',
            'claim_requests',
            'office_exit_permissions',
        ];

        foreach ($tables as $tableName) {
            if (Schema::hasTable($tableName) && Schema::hasColumn($tableName, 'current_approval_tier')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->dropColumn('current_approval_tier');
                });
            }
        }
    }
};
```

**Step 3: Update all 5 request models**

Add `'current_approval_tier'` to the `$fillable` array in each model. Also add an `approvalLogs` morphMany relationship to each:

```php
use Illuminate\Database\Eloquent\Relations\MorphMany;

public function approvalLogs(): MorphMany
{
    return $this->morphMany(ApprovalLog::class, 'approvable');
}
```

**Step 4: Run migration**

```bash
php artisan migrate
```

**Step 5: Commit**

```bash
git add database/migrations/*add_current_approval_tier* app/Models/OvertimeRequest.php app/Models/OvertimeClaimRequest.php app/Models/LeaveRequest.php app/Models/ClaimRequest.php app/Models/OfficeExitPermission.php
git commit -m "feat(hr): add current_approval_tier to all request tables"
```

---

### Task 4: Create Tier Approval Service

**Files:**
- Create: `app/Services/Hr/TierApprovalService.php`

**Step 1: Create service class**

This service encapsulates the tier advancement logic used by all approval controllers.

```php
<?php

namespace App\Services\Hr;

use App\Models\ApprovalLog;
use App\Models\DepartmentApprover;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Model;

class TierApprovalService
{
    /**
     * Get the max tier configured for a department + approval type.
     */
    public function getMaxTier(int $departmentId, string $approvalType): int
    {
        return DepartmentApprover::where('department_id', $departmentId)
            ->where('approval_type', $approvalType)
            ->max('tier') ?? 1;
    }

    /**
     * Check if the employee is an approver for the given department, type, and tier.
     */
    public function isApproverForTier(int $employeeId, int $departmentId, string $approvalType, int $tier): bool
    {
        return DepartmentApprover::where('approver_employee_id', $employeeId)
            ->where('department_id', $departmentId)
            ->where('approval_type', $approvalType)
            ->where('tier', $tier)
            ->exists();
    }

    /**
     * Get the tier(s) where an employee is assigned as approver for a department + type.
     */
    public function getApproverTiers(int $employeeId, int $departmentId, string $approvalType): array
    {
        return DepartmentApprover::where('approver_employee_id', $employeeId)
            ->where('department_id', $departmentId)
            ->where('approval_type', $approvalType)
            ->pluck('tier')
            ->toArray();
    }

    /**
     * Process an approval action. Returns the updated model.
     *
     * @param Model $request The approvable model (OvertimeRequest, LeaveRequest, etc.)
     * @param Employee $approver The employee performing the action
     * @param string $approvalType The type (overtime, leave, claims, exit_permission)
     * @param int $departmentId The department of the request's employee
     * @param string|null $notes Optional notes
     * @return array{advanced: bool, fully_approved: bool}
     */
    public function approve(Model $request, Employee $approver, string $approvalType, int $departmentId, ?string $notes = null): array
    {
        $currentTier = $request->current_approval_tier;
        $maxTier = $this->getMaxTier($departmentId, $approvalType);

        // Log the approval
        ApprovalLog::create([
            'approvable_type' => get_class($request),
            'approvable_id' => $request->id,
            'tier' => $currentTier,
            'approver_id' => $approver->id,
            'action' => 'approved',
            'notes' => $notes,
        ]);

        if ($currentTier >= $maxTier) {
            // Final tier — fully approved
            return ['advanced' => false, 'fully_approved' => true];
        }

        // Advance to next tier
        $request->update(['current_approval_tier' => $currentTier + 1]);

        return ['advanced' => true, 'fully_approved' => false];
    }

    /**
     * Process a rejection action. Immediately rejects the request.
     */
    public function reject(Model $request, Employee $approver, ?string $notes = null): void
    {
        ApprovalLog::create([
            'approvable_type' => get_class($request),
            'approvable_id' => $request->id,
            'tier' => $request->current_approval_tier,
            'approver_id' => $approver->id,
            'action' => 'rejected',
            'notes' => $notes,
        ]);
    }
}
```

**Step 2: Commit**

```bash
git add app/Services/Hr/TierApprovalService.php
git commit -m "feat(hr): create TierApprovalService for tier advancement logic"
```

---

### Task 5: Update HrDepartmentApproverController for Tier Support

**Files:**
- Modify: `app/Http/Controllers/Api/Hr/HrDepartmentApproverController.php`

**Step 1: Update `index()` to group by tier**

Change the response to nest approvers under their tier number:

```php
public function index(): JsonResponse
{
    $approvers = DepartmentApprover::query()
        ->with(['department', 'approver'])
        ->orderBy('department_id')
        ->orderBy('tier')
        ->get();

    $grouped = $approvers->groupBy('department_id')->map(function ($items, $departmentId) {
        $first = $items->first();

        $groupByType = function ($type) use ($items) {
            return $items->where('approval_type', $type)
                ->groupBy('tier')
                ->map(fn ($tierItems) => $tierItems->map(fn ($a) => $a->approver)->filter()->values())
                ->toArray();
        };

        return [
            'id' => $departmentId,
            'department_id' => (int) $departmentId,
            'department' => $first->department,
            'ot_approvers' => $groupByType('overtime'),
            'leave_approvers' => $groupByType('leave'),
            'claims_approvers' => $groupByType('claims'),
            'exit_permission_approvers' => $groupByType('exit_permission'),
        ];
    })->values();

    return response()->json(['data' => $grouped]);
}
```

**Step 2: Update `store()` and `update()` to accept tier-grouped data**

Update validation and insertion:

```php
public function store(Request $request): JsonResponse
{
    $validated = $request->validate([
        'department_id' => ['required', 'exists:departments,id'],
        'ot_approvers' => ['array'],
        'ot_approvers.*.tier' => ['required', 'integer', 'min:1'],
        'ot_approvers.*.employee_ids' => ['required', 'array'],
        'ot_approvers.*.employee_ids.*' => ['exists:employees,id'],
        'leave_approvers' => ['array'],
        'leave_approvers.*.tier' => ['required', 'integer', 'min:1'],
        'leave_approvers.*.employee_ids' => ['required', 'array'],
        'leave_approvers.*.employee_ids.*' => ['exists:employees,id'],
        'claims_approvers' => ['array'],
        'claims_approvers.*.tier' => ['required', 'integer', 'min:1'],
        'claims_approvers.*.employee_ids' => ['required', 'array'],
        'claims_approvers.*.employee_ids.*' => ['exists:employees,id'],
        'exit_permission_approvers' => ['array'],
        'exit_permission_approvers.*.tier' => ['required', 'integer', 'min:1'],
        'exit_permission_approvers.*.employee_ids' => ['required', 'array'],
        'exit_permission_approvers.*.employee_ids.*' => ['exists:employees,id'],
    ]);

    return DB::transaction(function () use ($validated) {
        $departmentId = $validated['department_id'];

        DepartmentApprover::where('department_id', $departmentId)->delete();

        $this->insertTieredApprovers($departmentId, 'overtime', $validated['ot_approvers'] ?? []);
        $this->insertTieredApprovers($departmentId, 'leave', $validated['leave_approvers'] ?? []);
        $this->insertTieredApprovers($departmentId, 'claims', $validated['claims_approvers'] ?? []);
        $this->insertTieredApprovers($departmentId, 'exit_permission', $validated['exit_permission_approvers'] ?? []);

        return response()->json([
            'message' => 'Department approver configuration saved successfully.',
        ], 201);
    });
}
```

Apply same changes to `update()`.

**Step 3: Replace `insertApprovers()` with `insertTieredApprovers()`**

```php
private function insertTieredApprovers(int $departmentId, string $type, array $tiers): void
{
    foreach ($tiers as $tierData) {
        $tier = $tierData['tier'];
        foreach ($tierData['employee_ids'] as $employeeId) {
            DepartmentApprover::create([
                'department_id' => $departmentId,
                'approver_employee_id' => $employeeId,
                'approval_type' => $type,
                'tier' => $tier,
            ]);
        }
    }
}
```

**Step 4: Run existing tests**

```bash
php artisan test --compact --filter=DepartmentApprover
```

**Step 5: Commit**

```bash
git add app/Http/Controllers/Api/Hr/HrDepartmentApproverController.php
git commit -m "feat(hr): update department approver controller for tier-based payload"
```

---

### Task 6: Update HrMyApprovalController for Tier-Aware Filtering

**Files:**
- Modify: `app/Http/Controllers/Api/Hr/HrMyApprovalController.php`

**Step 1: Update `getDeptIds()` to also return tier info**

Replace the simple `getDeptIds()` with a method that returns department+tier pairs:

```php
private function getDeptIdsForTier(int $employeeId, string $type): array
{
    return DepartmentApprover::where('approver_employee_id', $employeeId)
        ->where('approval_type', $type)
        ->get(['department_id', 'tier'])
        ->groupBy('department_id')
        ->map(fn ($items) => $items->pluck('tier')->toArray())
        ->toArray();
}
```

**Step 2: Update pending count queries**

For each approval type, filter requests where `current_approval_tier` matches one of the tiers where the approver is assigned. For example, for overtime:

```php
$otDeptTiers = $this->getDeptIdsForTier($employee->id, 'overtime');
$otDepts = array_keys($otDeptTiers);

$otRequestPending = empty($otDepts) ? 0
    : OvertimeRequest::whereHas('employee', fn ($q) => $q->whereIn('department_id', $otDepts))
        ->where('status', 'pending')
        ->where(function ($query) use ($otDeptTiers) {
            foreach ($otDeptTiers as $deptId => $tiers) {
                $query->orWhere(function ($q) use ($deptId, $tiers) {
                    $q->whereHas('employee', fn ($sq) => $sq->where('department_id', $deptId))
                      ->whereIn('current_approval_tier', $tiers);
                });
            }
        })
        ->count();
```

Apply the same pattern to leave, claims, exit permissions, and overtime claims.

**Step 3: Update list endpoints (overtime, leave, claims, exitPermissions)**

Same tier-aware filtering: only show requests where `current_approval_tier` matches the approver's tier for that department.

**Step 4: Update approve/reject actions to use TierApprovalService**

For `approveOvertime()`:

```php
public function approveOvertime(Request $request, OvertimeRequest $overtimeRequest): JsonResponse
{
    $employee = $this->getEmployee($request);
    if (!$employee) {
        return response()->json(['message' => 'Employee record not found.'], 404);
    }

    $service = app(TierApprovalService::class);
    $deptId = $overtimeRequest->employee->department_id;
    $currentTier = $overtimeRequest->current_approval_tier;

    if (!$service->isApproverForTier($employee->id, $deptId, 'overtime', $currentTier)) {
        return response()->json(['message' => 'Unauthorized.'], 403);
    }

    if ($overtimeRequest->status !== 'pending') {
        return response()->json(['message' => 'Only pending requests can be approved.'], 422);
    }

    $result = $service->approve($overtimeRequest, $employee, 'overtime', $deptId);

    if ($result['fully_approved']) {
        $overtimeRequest->update([
            'status' => 'completed',
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
            'actual_hours' => $overtimeRequest->estimated_hours,
            'replacement_hours_earned' => $overtimeRequest->estimated_hours,
        ]);

        if ($overtimeRequest->employee->user) {
            $overtimeRequest->employee->user->notify(
                new \App\Notifications\Hr\OvertimeRequestDecision($overtimeRequest, 'approved')
            );
        }
    }

    return response()->json($overtimeRequest->fresh([
        'employee:id,full_name,position_id,department_id',
        'employee.position:id,title',
        'employee.department:id,name',
    ]));
}
```

Apply same pattern to: `rejectOvertime`, `approveOvertimeClaim`, `rejectOvertimeClaim`, `approveLeave`, `rejectLeave`, `approveClaim`, `rejectClaim`, `approveExitPermission`, `rejectExitPermission`.

For rejections, call `$service->reject()` before updating the status.

**Step 5: Keep backward compat for `getDeptIds()`**

Keep the old method for any code that just needs department IDs (not tier-aware):

```php
private function getDeptIds(int $employeeId, string $type): array
{
    return DepartmentApprover::where('approver_employee_id', $employeeId)
        ->where('approval_type', $type)
        ->pluck('department_id')
        ->unique()
        ->toArray();
}
```

**Step 6: Commit**

```bash
git add app/Http/Controllers/Api/Hr/HrMyApprovalController.php
git commit -m "feat(hr): make approval workflow tier-aware with TierApprovalService"
```

---

### Task 7: Update React Frontend — DepartmentApprovers.jsx Modal

**Files:**
- Modify: `resources/js/hr/pages/attendance/DepartmentApprovers.jsx`

**Step 1: Update form state structure**

Change from flat arrays to tier-grouped arrays:

```js
const EMPTY_FORM = {
    department_id: '',
    ot_approvers: [{ tier: 1, employee_ids: [] }],
    leave_approvers: [{ tier: 1, employee_ids: [] }],
    claims_approvers: [{ tier: 1, employee_ids: [] }],
    exit_permission_approvers: [{ tier: 1, employee_ids: [] }],
};
```

**Step 2: Create TieredApproverSelector component**

Replace the existing `ApproverSelector` with a tier-aware version:

```jsx
function TieredApproverSelector({ label, field, form, setForm, employees }) {
    const tiers = form[field] || [{ tier: 1, employee_ids: [] }];

    function addTier() {
        const nextTier = tiers.length + 1;
        setForm(prev => ({
            ...prev,
            [field]: [...prev[field], { tier: nextTier, employee_ids: [] }],
        }));
    }

    function removeTier(index) {
        if (tiers.length <= 1) return;
        setForm(prev => ({
            ...prev,
            [field]: prev[field]
                .filter((_, i) => i !== index)
                .map((t, i) => ({ ...t, tier: i + 1 })),
        }));
    }

    function toggleEmployee(tierIndex, employeeId) {
        setForm(prev => ({
            ...prev,
            [field]: prev[field].map((t, i) =>
                i === tierIndex
                    ? {
                        ...t,
                        employee_ids: t.employee_ids.includes(employeeId)
                            ? t.employee_ids.filter(id => id !== employeeId)
                            : [...t.employee_ids, employeeId],
                    }
                    : t
            ),
        }));
    }

    const totalSelected = tiers.reduce((sum, t) => sum + t.employee_ids.length, 0);

    return (
        <div>
            <Label className="mb-2 block">{label} ({totalSelected} selected)</Label>
            <div className="space-y-3">
                {tiers.map((tierData, tierIndex) => (
                    <TierBlock
                        key={tierIndex}
                        tierData={tierData}
                        tierIndex={tierIndex}
                        totalTiers={tiers.length}
                        employees={employees}
                        onToggle={(empId) => toggleEmployee(tierIndex, empId)}
                        onRemove={() => removeTier(tierIndex)}
                    />
                ))}
            </div>
            <Button
                type="button"
                variant="outline"
                size="sm"
                onClick={addTier}
                className="mt-2 w-full"
            >
                <Plus className="mr-1 h-3.5 w-3.5" />
                Add Tier {tiers.length + 1}
            </Button>
        </div>
    );
}
```

**Step 3: Create TierBlock component**

```jsx
function TierBlock({ tierData, tierIndex, totalTiers, employees, onToggle, onRemove }) {
    const [search, setSearch] = useState('');
    const filtered = employees.filter((emp) => {
        const q = search.toLowerCase();
        return (
            emp.full_name?.toLowerCase().includes(q) ||
            emp.department?.name?.toLowerCase().includes(q)
        );
    });

    return (
        <div className="rounded-lg border border-zinc-200">
            <div className="flex items-center justify-between bg-zinc-50 px-3 py-1.5 rounded-t-lg border-b border-zinc-200">
                <span className="text-xs font-semibold text-zinc-600">
                    Tier {tierData.tier}
                    <span className="ml-1.5 text-zinc-400 font-normal">
                        ({tierData.employee_ids.length} approver{tierData.employee_ids.length !== 1 ? 's' : ''})
                    </span>
                </span>
                {totalTiers > 1 && (
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        onClick={onRemove}
                        className="h-6 w-6 p-0 text-red-400 hover:text-red-600"
                    >
                        <Trash2 className="h-3.5 w-3.5" />
                    </Button>
                )}
            </div>
            <div className="relative border-b border-zinc-100 px-2 py-1.5">
                <Search className="absolute left-3.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-zinc-400" />
                <Input
                    value={search}
                    onChange={(e) => setSearch(e.target.value)}
                    placeholder="Search employee..."
                    className="h-7 border-0 pl-7 text-xs shadow-none focus-visible:ring-0"
                />
            </div>
            <div className="max-h-28 space-y-1 overflow-y-auto p-2">
                {filtered.length === 0 ? (
                    <p className="py-2 text-center text-xs text-zinc-400">No employees found</p>
                ) : (
                    filtered.map((emp) => (
                        <label
                            key={emp.id}
                            className="flex cursor-pointer items-center gap-2 rounded px-2 py-1 hover:bg-zinc-50"
                        >
                            <Checkbox
                                checked={tierData.employee_ids.includes(emp.id)}
                                onCheckedChange={() => onToggle(emp.id)}
                            />
                            <span className="text-sm text-zinc-900">{emp.full_name}</span>
                            <span className="text-xs text-zinc-400">{emp.department?.name}</span>
                        </label>
                    ))
                )}
            </div>
        </div>
    );
}
```

**Step 4: Update `openEdit()` to parse tier-grouped data from API**

```js
function openEdit(item) {
    setEditTarget(item);

    const parseTiers = (approversByTier) => {
        if (!approversByTier || typeof approversByTier !== 'object') {
            return [{ tier: 1, employee_ids: [] }];
        }
        const tiers = Object.entries(approversByTier).map(([tier, approvers]) => ({
            tier: parseInt(tier),
            employee_ids: approvers.map(a => a.id),
        }));
        return tiers.length > 0 ? tiers.sort((a, b) => a.tier - b.tier) : [{ tier: 1, employee_ids: [] }];
    };

    setForm({
        department_id: String(item.department_id || ''),
        ot_approvers: parseTiers(item.ot_approvers),
        leave_approvers: parseTiers(item.leave_approvers),
        claims_approvers: parseTiers(item.claims_approvers),
        exit_permission_approvers: parseTiers(item.exit_permission_approvers),
    });
    setShowDialog(true);
}
```

**Step 5: Update the modal body to use TieredApproverSelector**

Replace the 4 `<ApproverSelector>` calls in the dialog with:

```jsx
<TieredApproverSelector label="OT Approvers" field="ot_approvers" form={form} setForm={setForm} employees={employees} />
<TieredApproverSelector label="Leave Approvers" field="leave_approvers" form={form} setForm={setForm} employees={employees} />
<TieredApproverSelector label="Claims Approvers" field="claims_approvers" form={form} setForm={setForm} employees={employees} />
<TieredApproverSelector label="Exit Permission Approvers" field="exit_permission_approvers" form={form} setForm={setForm} employees={employees} />
```

**Step 6: Update ApproverBadges to show tiers in the table**

```jsx
function TieredApproverBadges({ approversByTier }) {
    if (!approversByTier || Object.keys(approversByTier).length === 0) {
        return <span className="text-sm text-zinc-400">Not assigned</span>;
    }

    const sortedTiers = Object.entries(approversByTier).sort(([a], [b]) => Number(a) - Number(b));

    return (
        <div className="space-y-1">
            {sortedTiers.map(([tier, approvers]) => (
                <div key={tier} className="flex items-center gap-1.5">
                    <span className="text-[10px] font-semibold text-zinc-400 uppercase shrink-0">T{tier}</span>
                    <div className="flex flex-wrap gap-1">
                        {approvers.map((approver) => (
                            <Badge key={approver.id} variant="secondary" className="text-xs">
                                {approver.full_name}
                            </Badge>
                        ))}
                    </div>
                </div>
            ))}
        </div>
    );
}
```

Replace `<ApproverBadges>` usage in the table with `<TieredApproverBadges>`.

**Step 7: Remove old `toggleApprover` and `ApproverSelector` functions**

They are no longer needed.

**Step 8: Build and verify**

```bash
npm run build
```

**Step 9: Commit**

```bash
git add resources/js/hr/pages/attendance/DepartmentApprovers.jsx
git commit -m "feat(hr): update DepartmentApprovers UI for tier-based approval"
```

---

### Task 8: Update DepartmentApproverFactory for Tier Support

**Files:**
- Modify: `database/factories/DepartmentApproverFactory.php`

**Step 1: Add tier to factory definition**

Add `'tier' => 1` to the factory's `definition()` method.

**Step 2: Commit**

```bash
git add database/factories/DepartmentApproverFactory.php
git commit -m "chore(hr): add tier to DepartmentApproverFactory"
```

---

### Task 9: Write Tests for Tier Approval System

**Files:**
- Create: `tests/Feature/Hr/TierApprovalTest.php`

**Step 1: Create test file**

```bash
php artisan make:test Hr/TierApprovalTest --pest --no-interaction
```

**Step 2: Write tests covering:**

1. **Store/update with tiers** — Create department approver config with multiple tiers, verify DB records have correct tier values
2. **Index returns tier-grouped data** — API response groups approvers by tier
3. **Tier-aware approval flow** — Approve at tier 1 advances to tier 2; approve at max tier fully approves
4. **Immediate rejection** — Reject at any tier immediately rejects the request
5. **Unauthorized tier** — Approver for tier 2 cannot approve a request at tier 1
6. **Approval logs** — Each approve/reject creates an audit log entry

**Step 3: Run tests**

```bash
php artisan test --compact tests/Feature/Hr/TierApprovalTest.php
```

**Step 4: Fix any failures, then commit**

```bash
git add tests/Feature/Hr/TierApprovalTest.php
git commit -m "test(hr): add tests for tier approval system"
```

---

### Task 10: Update LeaveApprovers.jsx Page (if needed)

**Files:**
- Modify: `resources/js/hr/pages/leave/LeaveApprovers.jsx`

**Step 1: Review if this page shares the same API endpoint**

Check if it calls `fetchDepartmentApprovers` or has its own. If it shares the API, update to handle tier-grouped response format. If it has its own endpoint, adjust accordingly.

**Step 2: Build and verify**

```bash
npm run build
```

**Step 3: Commit if changes were made**

```bash
git add resources/js/hr/pages/leave/LeaveApprovers.jsx
git commit -m "feat(hr): update LeaveApprovers page for tier support"
```

---

### Task 11: Final Integration Testing & Build

**Step 1: Run full test suite**

```bash
php artisan test --compact
```

**Step 2: Build frontend**

```bash
npm run build
```

**Step 3: Manual verification checklist**

- [ ] Create new department approver config with 2 tiers for OT
- [ ] Edit existing config, add a Tier 3
- [ ] Table shows T1, T2, T3 badges correctly
- [ ] Submit an OT request as an employee, verify it shows for Tier 1 approver
- [ ] Approve at Tier 1, verify it advances to Tier 2 approver's dashboard
- [ ] Approve at Tier 2, verify fully approved
- [ ] Test rejection at Tier 1 — request immediately rejected
- [ ] Check `approval_logs` table has entries

**Step 4: Final commit**

```bash
git add -A
git commit -m "feat(hr): complete tier approval system implementation"
```
