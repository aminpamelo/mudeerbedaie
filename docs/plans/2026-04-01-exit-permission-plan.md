# Office Exit Permission (Surat Pelepasan) Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Build a digital "Borang Permohonan Kebenaran Meninggalkan Pejabat" module where employees submit office exit requests, an assigned approver approves/rejects them, per-department notifiers receive CC emails on approval, the exit is flagged in the attendance log, and a printable BeDaie-branded PDF is generated.

**Architecture:** Standalone module following the exact same pattern as the Overtime module — model + admin controller + employee self-service controller + approvals extension + React pages. Uses existing `department_approvers` table (extended for `exit_permission` type) and `attendance_logs` for the attendance note. PDF generated via already-installed `barryvdh/laravel-dompdf`.

**Tech Stack:** Laravel 12, Eloquent, DomPDF (`barryvdh/laravel-dompdf` v3.1 already installed), React 19, React Query (TanStack Query), Tailwind CSS v4, Lucide React icons.

---

## Task 1: Extend DepartmentApprover enum + create exit_permission_notifiers table

**Files:**
- Create: `database/migrations/2026_04_01_000001_add_exit_permission_to_department_approvers.php`
- Create: `database/migrations/2026_04_01_000002_create_exit_permission_notifiers_table.php`

**Step 1: Generate first migration**
```bash
php artisan make:migration add_exit_permission_to_department_approvers --no-interaction
```

**Step 2: Edit the migration to alter the enum**

Open the generated migration and replace its content with:
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE department_approvers MODIFY COLUMN approval_type ENUM('overtime', 'leave', 'claims', 'exit_permission') NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE department_approvers MODIFY COLUMN approval_type ENUM('overtime', 'leave', 'claims') NOT NULL");
    }
};
```

> **Note:** The project uses SQLite in development. SQLite does not support `ALTER COLUMN` for enums. Since the existing column is effectively a string-constrained enum, for SQLite you can skip the ALTER and rely on application-level validation. For production MySQL, the ALTER is needed. A safe approach for both: add the column change for MySQL only, guarded by a database driver check.

Replace with the SQLite-safe version:
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE department_approvers MODIFY COLUMN approval_type ENUM('overtime', 'leave', 'claims', 'exit_permission') NOT NULL");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE department_approvers MODIFY COLUMN approval_type ENUM('overtime', 'leave', 'claims') NOT NULL");
        }
    }
};
```

**Step 3: Generate second migration**
```bash
php artisan make:migration create_exit_permission_notifiers_table --no-interaction
```

Replace its content with:
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exit_permission_notifiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->constrained('departments')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['department_id', 'employee_id']);
            $table->index('department_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exit_permission_notifiers');
    }
};
```

**Step 4: Run migrations**
```bash
php artisan migrate --no-interaction
```
Expected output: `2 migrations run successfully`

---

## Task 2: Create office_exit_permissions table migration

**Files:**
- Create: `database/migrations/2026_04_01_000003_create_office_exit_permissions_table.php`

**Step 1: Generate migration**
```bash
php artisan make:migration create_office_exit_permissions_table --no-interaction
```

**Step 2: Replace content with**
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('office_exit_permissions', function (Blueprint $table) {
            $table->id();
            $table->string('permission_number')->unique();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->date('exit_date');
            $table->time('exit_time');
            $table->time('return_time');
            $table->enum('errand_type', ['company', 'personal']);
            $table->text('purpose');
            $table->string('addressed_to');
            $table->enum('status', ['pending', 'approved', 'rejected', 'cancelled'])->default('pending');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('cc_notified_at')->nullable();
            $table->boolean('attendance_note_created')->default(false);
            $table->timestamps();

            $table->index(['employee_id', 'status']);
            $table->index('exit_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('office_exit_permissions');
    }
};
```

**Step 3: Run migration**
```bash
php artisan migrate --no-interaction
```
Expected: `1 migration run`

---

## Task 3: Create OfficeExitPermission and ExitPermissionNotifier models

**Files:**
- Create: `app/Models/OfficeExitPermission.php`
- Create: `app/Models/ExitPermissionNotifier.php`

**Step 1: Generate models**
```bash
php artisan make:model OfficeExitPermission --no-interaction
php artisan make:model ExitPermissionNotifier --no-interaction
```

**Step 2: Replace OfficeExitPermission.php with**
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OfficeExitPermission extends Model
{
    protected $fillable = [
        'permission_number',
        'employee_id',
        'exit_date',
        'exit_time',
        'return_time',
        'errand_type',
        'purpose',
        'addressed_to',
        'status',
        'approved_by',
        'approved_at',
        'rejection_reason',
        'cc_notified_at',
        'attendance_note_created',
    ];

    protected function casts(): array
    {
        return [
            'exit_date' => 'date',
            'approved_at' => 'datetime',
            'cc_notified_at' => 'datetime',
            'attendance_note_created' => 'boolean',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (OfficeExitPermission $permission): void {
            if (empty($permission->permission_number)) {
                $permission->permission_number = static::generatePermissionNumber();
            }
        });
    }

    public static function generatePermissionNumber(): string
    {
        $prefix = 'OEP-'.now()->format('Ym').'-';
        $last = static::where('permission_number', 'like', $prefix.'%')
            ->orderByDesc('permission_number')
            ->value('permission_number');

        $next = $last ? (int) substr($last, -4) + 1 : 1;

        return $prefix.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }
}
```

**Step 3: Replace ExitPermissionNotifier.php with**
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExitPermissionNotifier extends Model
{
    protected $fillable = [
        'department_id',
        'employee_id',
    ];

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
```

---

## Task 4: Create form request validation

**Files:**
- Create: `app/Http/Requests/Hr/StoreExitPermissionRequest.php`

**Step 1: Generate**
```bash
php artisan make:request Hr/StoreExitPermissionRequest --no-interaction
```

**Step 2: Replace with**
```php
<?php

namespace App\Http\Requests\Hr;

use Illuminate\Foundation\Http\FormRequest;

class StoreExitPermissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'exit_date' => ['required', 'date', 'after_or_equal:today'],
            'exit_time' => ['required', 'date_format:H:i'],
            'return_time' => ['required', 'date_format:H:i', 'after:exit_time'],
            'errand_type' => ['required', 'in:company,personal'],
            'purpose' => ['required', 'string', 'min:10', 'max:1000'],
            'addressed_to' => ['required', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'exit_date.after_or_equal' => 'Exit date must be today or in the future.',
            'return_time.after' => 'Return time must be after exit time.',
            'purpose.min' => 'Please provide more detail (at least 10 characters).',
        ];
    }
}
```

---

## Task 5: Create notifications

**Files:**
- Create: `app/Notifications/Hr/ExitPermissionApproved.php`
- Create: `app/Notifications/Hr/ExitPermissionRejected.php`

**Step 1: Check the BaseHrNotification signature**

Open `app/Notifications/Hr/BaseHrNotification.php` and read how other notifications extend it (e.g. look at an existing notification like `OvertimeRequestDecision.php` or similar). Match that pattern exactly.

**Step 2: Create ExitPermissionApproved.php**

```bash
php artisan make:notification Hr/ExitPermissionApproved --no-interaction
```

Replace with:
```php
<?php

namespace App\Notifications\Hr;

use App\Models\OfficeExitPermission;
use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;

class ExitPermissionApproved extends BaseHrNotification
{
    public function __construct(
        public readonly OfficeExitPermission $permission,
        public readonly User $approvedByUser,
        public readonly bool $isCc = false,
    ) {}

    public function toMail(mixed $notifiable): MailMessage
    {
        $employee = $this->permission->employee;
        $subject = $this->isCc
            ? '[CC] Exit Permission Approved — '.$employee->full_name
            : 'Your Exit Permission Has Been Approved';

        $message = (new MailMessage)
            ->subject($subject)
            ->greeting('Hello'.($this->isCc ? '' : ', '.$employee->full_name).'!')
            ->line($this->isCc
                ? 'FYI: The following exit permission has been approved.'
                : 'Your office exit permission has been approved.')
            ->line('**Permission No:** '.$this->permission->permission_number)
            ->line('**Date:** '.$this->permission->exit_date->format('d M Y'))
            ->line('**Exit Time:** '.$this->permission->exit_time.' → '.$this->permission->return_time)
            ->line('**Type:** '.($this->permission->errand_type === 'company' ? 'Company Business' : 'Personal Business'))
            ->line('**Purpose:** '.$this->permission->purpose)
            ->line('**Approved by:** '.$this->approvedByUser->name);

        return $message;
    }

    public function toArray(mixed $notifiable): array
    {
        return [
            'type' => 'exit_permission_approved',
            'permission_id' => $this->permission->id,
            'permission_number' => $this->permission->permission_number,
            'message' => 'Exit permission '.$this->permission->permission_number.' has been approved.',
        ];
    }
}
```

**Step 3: Create ExitPermissionRejected.php**

```bash
php artisan make:notification Hr/ExitPermissionRejected --no-interaction
```

Replace with:
```php
<?php

namespace App\Notifications\Hr;

use App\Models\OfficeExitPermission;
use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;

class ExitPermissionRejected extends BaseHrNotification
{
    public function __construct(
        public readonly OfficeExitPermission $permission,
        public readonly User $rejectedByUser,
    ) {}

    public function toMail(mixed $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your Exit Permission Has Been Rejected')
            ->greeting('Hello, '.$this->permission->employee->full_name.'!')
            ->line('Unfortunately, your office exit permission request has been rejected.')
            ->line('**Permission No:** '.$this->permission->permission_number)
            ->line('**Date:** '.$this->permission->exit_date->format('d M Y'))
            ->line('**Reason:** '.$this->permission->rejection_reason)
            ->line('Rejected by: '.$this->rejectedByUser->name);
    }

    public function toArray(mixed $notifiable): array
    {
        return [
            'type' => 'exit_permission_rejected',
            'permission_id' => $this->permission->id,
            'permission_number' => $this->permission->permission_number,
            'message' => 'Exit permission '.$this->permission->permission_number.' was rejected.',
        ];
    }
}
```

---

## Task 6: Create admin controller HrOfficeExitPermissionController

**Files:**
- Create: `app/Http/Controllers/Api/Hr/HrOfficeExitPermissionController.php`

**Step 1: Generate**
```bash
php artisan make:controller Api/Hr/HrOfficeExitPermissionController --no-interaction
```

**Step 2: Replace with**
```php
<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use App\Models\AttendanceLog;
use App\Models\DepartmentApprover;
use App\Models\ExitPermissionNotifier;
use App\Models\OfficeExitPermission;
use App\Notifications\Hr\ExitPermissionApproved;
use App\Notifications\Hr\ExitPermissionRejected;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HrOfficeExitPermissionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = OfficeExitPermission::with(['employee.department', 'approver'])
            ->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('department_id')) {
            $query->whereHas('employee', fn ($q) => $q->where('department_id', $request->department_id));
        }

        if ($request->filled('errand_type')) {
            $query->where('errand_type', $request->errand_type);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('exit_date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('exit_date', '<=', $request->date_to);
        }

        return response()->json([
            'data' => $query->paginate(20),
        ]);
    }

    public function show(OfficeExitPermission $officeExitPermission): JsonResponse
    {
        $officeExitPermission->load(['employee.department', 'approver']);

        return response()->json(['data' => $officeExitPermission]);
    }

    public function approve(Request $request, OfficeExitPermission $officeExitPermission): JsonResponse
    {
        if (! $officeExitPermission->isPending()) {
            return response()->json(['message' => 'Only pending requests can be approved.'], 422);
        }

        return DB::transaction(function () use ($request, $officeExitPermission): JsonResponse {
            $officeExitPermission->update([
                'status' => 'approved',
                'approved_by' => $request->user()->id,
                'approved_at' => now(),
            ]);

            $this->createAttendanceNote($officeExitPermission);
            $this->sendApprovalNotifications($officeExitPermission, $request->user());

            return response()->json([
                'data' => $officeExitPermission->fresh(['employee', 'approver']),
                'message' => 'Exit permission approved successfully.',
            ]);
        });
    }

    public function reject(Request $request, OfficeExitPermission $officeExitPermission): JsonResponse
    {
        if (! $officeExitPermission->isPending()) {
            return response()->json(['message' => 'Only pending requests can be rejected.'], 422);
        }

        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'min:5'],
        ]);

        return DB::transaction(function () use ($request, $officeExitPermission, $validated): JsonResponse {
            $officeExitPermission->update([
                'status' => 'rejected',
                'rejection_reason' => $validated['rejection_reason'],
            ]);

            $officeExitPermission->load('employee.user');
            if ($officeExitPermission->employee->user) {
                $officeExitPermission->employee->user->notify(
                    new ExitPermissionRejected($officeExitPermission, $request->user())
                );
            }

            return response()->json([
                'data' => $officeExitPermission->fresh(['employee', 'approver']),
                'message' => 'Exit permission rejected.',
            ]);
        });
    }

    public function pdf(OfficeExitPermission $officeExitPermission): mixed
    {
        if (! $officeExitPermission->isApproved()) {
            return response()->json(['message' => 'PDF is only available for approved permissions.'], 403);
        }

        $officeExitPermission->load(['employee.department', 'approver']);

        $pdf = app('dompdf.wrapper')->loadView('pdf.exit-permission', [
            'permission' => $officeExitPermission,
        ]);

        $filename = $officeExitPermission->permission_number.'.pdf';

        return $pdf->download($filename);
    }

    private function createAttendanceNote(OfficeExitPermission $permission): void
    {
        $note = 'Exit: '.$permission->exit_time.' - '.$permission->return_time
            .' ('.($permission->errand_type === 'company' ? 'Company' : 'Personal').')';

        AttendanceLog::where('employee_id', $permission->employee_id)
            ->whereDate('date', $permission->exit_date)
            ->update([
                'remarks' => DB::raw("CASE WHEN remarks IS NULL OR remarks = '' THEN '{$note}' ELSE CONCAT(remarks, ' | {$note}') END"),
            ]);

        $permission->update(['attendance_note_created' => true]);
    }

    private function sendApprovalNotifications(OfficeExitPermission $permission, mixed $approver): void
    {
        $permission->load('employee.user');

        if ($permission->employee->user) {
            $permission->employee->user->notify(
                new ExitPermissionApproved($permission, $approver)
            );
        }

        $notifiers = ExitPermissionNotifier::with('employee.user')
            ->where('department_id', $permission->employee->department_id)
            ->get();

        foreach ($notifiers as $notifier) {
            if ($notifier->employee->user && $notifier->employee->id !== $permission->employee_id) {
                $notifier->employee->user->notify(
                    new ExitPermissionApproved($permission, $approver, isCc: true)
                );
            }
        }

        $permission->update(['cc_notified_at' => now()]);
    }
}
```

---

## Task 7: Create employee self-service controller HrMyExitPermissionController

**Files:**
- Create: `app/Http/Controllers/Api/Hr/HrMyExitPermissionController.php`

**Step 1: Generate**
```bash
php artisan make:controller Api/Hr/HrMyExitPermissionController --no-interaction
```

**Step 2: Replace with**
```php
<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\StoreExitPermissionRequest;
use App\Models\OfficeExitPermission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HrMyExitPermissionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $employee = $request->user()->employee;

        if (! $employee) {
            return response()->json(['data' => []], 200);
        }

        $query = OfficeExitPermission::where('employee_id', $employee->id)
            ->with('approver')
            ->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return response()->json(['data' => $query->paginate(15)]);
    }

    public function store(StoreExitPermissionRequest $request): JsonResponse
    {
        $employee = $request->user()->employee;

        if (! $employee) {
            return response()->json(['message' => 'Employee profile not found.'], 422);
        }

        $permission = OfficeExitPermission::create([
            ...$request->validated(),
            'employee_id' => $employee->id,
            'status' => 'pending',
        ]);

        return response()->json([
            'data' => $permission->load('employee'),
            'message' => 'Exit permission request submitted successfully.',
        ], 201);
    }

    public function show(Request $request, OfficeExitPermission $officeExitPermission): JsonResponse
    {
        $employee = $request->user()->employee;

        if ($officeExitPermission->employee_id !== $employee?->id) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        return response()->json([
            'data' => $officeExitPermission->load(['employee.department', 'approver']),
        ]);
    }

    public function cancel(Request $request, OfficeExitPermission $officeExitPermission): JsonResponse
    {
        $employee = $request->user()->employee;

        if ($officeExitPermission->employee_id !== $employee?->id) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        if (! $officeExitPermission->isPending()) {
            return response()->json(['message' => 'Only pending requests can be cancelled.'], 422);
        }

        $officeExitPermission->update(['status' => 'cancelled']);

        return response()->json(['message' => 'Exit permission request cancelled.']);
    }
}
```

---

## Task 8: Extend HrMyApprovalController for exit permissions

**Files:**
- Modify: `app/Http/Controllers/Api/Hr/HrMyApprovalController.php`

**Step 1: Read the existing file**

Open `app/Http/Controllers/Api/Hr/HrMyApprovalController.php`. You need to:
1. Add the `exit_permission` block to `summary()`
2. Add three new methods: `exitPermissions()`, `approveExitPermission()`, `rejectExitPermission()`

**Step 2: In the `summary()` method**, add after the claims block:

```php
// Add these imports at top of file:
use App\Models\OfficeExitPermission;

// Inside summary(), after claims block:
$exitDepts = DepartmentApprover::where('approver_employee_id', $employee->id)
    ->where('approval_type', 'exit_permission')
    ->pluck('department_id')
    ->toArray();

$exitPending = 0;
if (!empty($exitDepts)) {
    $exitPending = OfficeExitPermission::whereHas('employee', fn ($q) => $q->whereIn('department_id', $exitDepts))
        ->where('status', 'pending')
        ->count();
}
```

And in the return array, add:
```php
'exit_permission' => [
    'pending' => $exitPending,
    'isAssigned' => !empty($exitDepts),
],
```

Also update the `isApprover` check to include exit permissions:
```php
'isApprover' => !empty($otDepts) || !empty($leaveDepts) || $isClaimsAssigned || !empty($exitDepts),
```

**Step 3: Add new methods to HrMyApprovalController**

```php
public function exitPermissions(Request $request): JsonResponse
{
    $employee = $request->user()->employee;

    $depts = DepartmentApprover::where('approver_employee_id', $employee->id)
        ->where('approval_type', 'exit_permission')
        ->pluck('department_id')
        ->toArray();

    if (empty($depts)) {
        return response()->json(['data' => []]);
    }

    $query = OfficeExitPermission::whereHas('employee', fn ($q) => $q->whereIn('department_id', $depts))
        ->with(['employee.department', 'approver'])
        ->latest();

    if ($request->filled('status')) {
        $query->where('status', $request->status);
    }

    return response()->json(['data' => $query->paginate(20)]);
}

public function approveExitPermission(Request $request, OfficeExitPermission $officeExitPermission): JsonResponse
{
    $employee = $request->user()->employee;

    $isAssigned = DepartmentApprover::where('approver_employee_id', $employee->id)
        ->where('approval_type', 'exit_permission')
        ->where('department_id', $officeExitPermission->employee->department_id)
        ->exists();

    if (! $isAssigned) {
        return response()->json(['message' => 'Unauthorized.'], 403);
    }

    if (! $officeExitPermission->isPending()) {
        return response()->json(['message' => 'Only pending requests can be approved.'], 422);
    }

    // Delegate to admin controller logic
    return app(HrOfficeExitPermissionController::class)->approve($request, $officeExitPermission);
}

public function rejectExitPermission(Request $request, OfficeExitPermission $officeExitPermission): JsonResponse
{
    $employee = $request->user()->employee;

    $isAssigned = DepartmentApprover::where('approver_employee_id', $employee->id)
        ->where('approval_type', 'exit_permission')
        ->where('department_id', $officeExitPermission->employee->department_id)
        ->exists();

    if (! $isAssigned) {
        return response()->json(['message' => 'Unauthorized.'], 403);
    }

    if (! $officeExitPermission->isPending()) {
        return response()->json(['message' => 'Only pending requests can be rejected.'], 422);
    }

    return app(HrOfficeExitPermissionController::class)->reject($request, $officeExitPermission);
}
```

Also add to imports at top:
```php
use App\Http\Controllers\Api\Hr\HrOfficeExitPermissionController;
```

---

## Task 9: Create notifier admin controller

**Files:**
- Create: `app/Http/Controllers/Api/Hr/HrExitPermissionNotifierController.php`

**Step 1: Generate**
```bash
php artisan make:controller Api/Hr/HrExitPermissionNotifierController --no-interaction
```

**Step 2: Replace with**
```php
<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use App\Models\ExitPermissionNotifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HrExitPermissionNotifierController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = ExitPermissionNotifier::with(['department', 'employee'])
            ->orderBy('department_id');

        if ($request->filled('department_id')) {
            $query->where('department_id', $request->department_id);
        }

        return response()->json(['data' => $query->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'department_id' => ['required', 'exists:departments,id'],
            'employee_id' => ['required', 'exists:employees,id'],
        ]);

        $notifier = ExitPermissionNotifier::firstOrCreate($validated);

        return response()->json([
            'data' => $notifier->load(['department', 'employee']),
            'message' => 'Notifier added.',
        ], 201);
    }

    public function destroy(ExitPermissionNotifier $exitPermissionNotifier): JsonResponse
    {
        $exitPermissionNotifier->delete();

        return response()->json(['message' => 'Notifier removed.']);
    }
}
```

---

## Task 10: Register API routes

**Files:**
- Modify: `routes/api.php`

**Step 1: Find the HR routes group**

Open `routes/api.php`. Search for the HR middleware group (look for `Route::middleware` with `hr` or similar that wraps all the `/hr/` routes). Add the following new route blocks inside that group, near the end (after claims, before payroll or wherever makes sense):

```php
// Office Exit Permissions - Admin
Route::get('exit-permissions', [HrOfficeExitPermissionController::class, 'index']);
Route::get('exit-permissions/{officeExitPermission}', [HrOfficeExitPermissionController::class, 'show']);
Route::patch('exit-permissions/{officeExitPermission}/approve', [HrOfficeExitPermissionController::class, 'approve']);
Route::patch('exit-permissions/{officeExitPermission}/reject', [HrOfficeExitPermissionController::class, 'reject']);
Route::get('exit-permissions/{officeExitPermission}/pdf', [HrOfficeExitPermissionController::class, 'pdf']);

// Exit Permission Notifiers - Admin
Route::get('exit-permission-notifiers', [HrExitPermissionNotifierController::class, 'index']);
Route::post('exit-permission-notifiers', [HrExitPermissionNotifierController::class, 'store']);
Route::delete('exit-permission-notifiers/{exitPermissionNotifier}', [HrExitPermissionNotifierController::class, 'destroy']);

// Exit Permissions - Employee Self-Service
Route::get('my/exit-permissions', [HrMyExitPermissionController::class, 'index']);
Route::post('my/exit-permissions', [HrMyExitPermissionController::class, 'store']);
Route::get('my/exit-permissions/{officeExitPermission}', [HrMyExitPermissionController::class, 'show']);
Route::delete('my/exit-permissions/{officeExitPermission}', [HrMyExitPermissionController::class, 'cancel']);

// Exit Permissions - HOD Approvals
Route::get('my-approvals/exit-permissions', [HrMyApprovalController::class, 'exitPermissions']);
Route::patch('my-approvals/exit-permissions/{officeExitPermission}/approve', [HrMyApprovalController::class, 'approveExitPermission']);
Route::patch('my-approvals/exit-permissions/{officeExitPermission}/reject', [HrMyApprovalController::class, 'rejectExitPermission']);
```

**Step 2: Add missing imports at top of routes/api.php**
```php
use App\Http\Controllers\Api\Hr\HrOfficeExitPermissionController;
use App\Http\Controllers\Api\Hr\HrMyExitPermissionController;
use App\Http\Controllers\Api\Hr\HrExitPermissionNotifierController;
```

**Step 3: Verify routes registered**
```bash
php artisan route:list --path=exit-permission --no-interaction
```
Expected: ~10+ routes listed

---

## Task 11: Create PDF Blade template

**Files:**
- Create: `resources/views/pdf/exit-permission.blade.php`

**Step 1: Create the views/pdf directory if it doesn't exist**
```bash
mkdir -p resources/views/pdf
```

**Step 2: Create the template**

Create `resources/views/pdf/exit-permission.blade.php`:
```html
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1a1a1a; margin: 0; padding: 0; }
        .container { width: 90%; margin: 20px auto; }
        .header { text-align: center; margin-bottom: 16px; }
        .logo { font-size: 22px; font-weight: bold; color: #4f46e5; }
        .logo span { color: #e11d48; }
        .company { font-size: 10px; margin: 4px 0; }
        .title { font-size: 13px; font-weight: bold; text-transform: uppercase; border-top: 2px solid #1a1a1a; border-bottom: 2px solid #1a1a1a; padding: 5px 0; margin: 10px 0; text-align: center; }
        .section { margin: 12px 0; }
        .row { display: flex; margin: 6px 0; }
        .label { width: 200px; font-weight: bold; }
        .value { flex: 1; border-bottom: 1px solid #555; padding-bottom: 2px; }
        .checkbox-row { margin: 12px 0; }
        .checkbox { display: inline-block; width: 14px; height: 14px; border: 1px solid #333; margin-right: 6px; text-align: center; line-height: 14px; font-size: 10px; }
        .divider { border-top: 1px dashed #666; margin: 14px 0; }
        .approval-section { margin: 12px 0; }
        .sig-row { margin: 6px 0; }
        .approved-stamp { color: #16a34a; font-weight: bold; font-size: 12px; }
        .rejected-stamp { color: #dc2626; font-weight: bold; font-size: 12px; }
        .notes { font-size: 10px; margin-top: 14px; }
        .notes ol { margin: 4px 0; padding-left: 18px; }
    </style>
</head>
<body>
<div class="container">
    <!-- Header -->
    <div class="header">
        <div class="logo"><span>Be</span>Daie</div>
        <div class="company">Online Islamic Education Platform by <strong>DAKWAH DIGITAL NETWORK SDN BHD (1431941-W)</strong></div>
    </div>

    <div class="title">Borang Permohonan Kebenaran Meninggalkan Pejabat Dalam Waktu Bekerja</div>

    <!-- Addressed To -->
    <div class="section">
        <div class="row">
            <span class="label">Kepada:</span>
            <span class="value">{{ $permission->addressed_to }}</span>
        </div>
        <p style="margin:6px 0;">Saya dengan ini memohon kebenaran pihak Tuan/Puan sepertimana di atas untuk meninggalkan pejabat bagi tujuan:</p>
        <div style="border-bottom:1px solid #555; min-height:20px; margin:4px 0;">{{ $permission->purpose }}</div>
    </div>

    <!-- Errand Type -->
    <div class="checkbox-row">
        <span class="checkbox">{{ $permission->errand_type === 'company' ? '✓' : '' }}</span> Urusan Syarikat
        &nbsp;&nbsp;&nbsp;
        <span class="checkbox">{{ $permission->errand_type === 'personal' ? '✓' : '' }}</span> Urusan Peribadi
    </div>

    <!-- Time & Applicant -->
    <div class="section">
        <div class="row">
            <span class="label">Tempoh/jam yang diperlukan:</span>
            <span class="value">{{ \Carbon\Carbon::parse($permission->exit_time)->format('h:i A') }} hingga {{ \Carbon\Carbon::parse($permission->return_time)->format('h:i A') }}</span>
        </div>
        <div class="row">
            <span class="label">Nama Penuh Pemohon:</span>
            <span class="value">{{ $permission->employee->full_name }}</span>
        </div>
        <div class="sig-row">
            <div class="row"><span class="label">Tandatangan Pemohon:</span><span class="value"></span></div>
            <div class="row"><span class="label">Jawatan:</span><span class="value">{{ $permission->employee->position?->name ?? '—' }}</span></div>
            <div class="row"><span class="label">Tarikh:</span><span class="value">{{ $permission->exit_date->format('d/m/Y') }}</span></div>
        </div>
    </div>

    <div class="divider"></div>

    <!-- Approval Section -->
    <div class="approval-section">
        <p>Permohonan pelepasan waktu bekerja *<strong>{{ $permission->status === 'approved' ? 'diluluskan' : ($permission->status === 'rejected' ? 'tidak diluluskan' : '___________') }}</strong></p>
        <p style="font-size:10px;color:#666;">*potong mana yang tidak berkenaan</p>

        @if($permission->isApproved())
        <p class="approved-stamp">✓ DILULUSKAN</p>
        @elseif($permission->status === 'rejected')
        <p class="rejected-stamp">✗ TIDAK DILULUSKAN — {{ $permission->rejection_reason }}</p>
        @endif

        <div class="row"><span class="label">Nama Penuh:</span><span class="value">{{ $permission->approver?->name ?? '—' }}</span></div>
        <div class="row"><span class="label">Jawatan:</span><span class="value"></span></div>
        <div class="row"><span class="label">Tandatangan:</span><span class="value"></span></div>
        <div class="row"><span class="label">Tarikh:</span><span class="value">{{ $permission->approved_at?->format('d/m/Y') ?? '—' }}</span></div>
    </div>

    <div class="divider"></div>

    <!-- Notes -->
    <div class="notes">
        <strong>Catatan:</strong>
        <ol>
            <li>Sebarang urusan peribadi dalam waktu bekerja hendaklah dimaklumkan dan mendapat kebenaran terlebih dahulu daripada pihak pengurusan.</li>
            <li>Masa yang digunakan atas urusan rasmi syarikat dianggap sebagai waktu bekerja dan akan direkodkan seperti biasa.</li>
        </ol>
    </div>

    <p style="font-size:9px; color:#999; text-align:right; margin-top:16px;">
        Generated: {{ now()->format('d M Y H:i') }} | {{ $permission->permission_number }}
    </p>
</div>
</body>
</html>
```

---

## Task 12: Run Pint and verify backend compiles

**Step 1: Run Pint**
```bash
./vendor/bin/pint --dirty
```

**Step 2: Verify no syntax errors**
```bash
php artisan route:list --path=exit-permission --no-interaction
```
Expected: All routes listed cleanly.

---

## Task 13: Write backend feature tests

**Files:**
- Create: `tests/Feature/Hr/ExitPermissionTest.php`

**Step 1: Generate**
```bash
php artisan make:test Hr/ExitPermissionTest --pest --no-interaction
```

**Step 2: Replace with**
```php
<?php

declare(strict_types=1);

use App\Models\AttendanceLog;
use App\Models\Department;
use App\Models\DepartmentApprover;
use App\Models\Employee;
use App\Models\ExitPermissionNotifier;
use App\Models\OfficeExitPermission;
use App\Models\User;
use App\Notifications\Hr\ExitPermissionApproved;
use App\Notifications\Hr\ExitPermissionRejected;
use Illuminate\Support\Facades\Notification;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->department = Department::factory()->create();

    $this->employeeUser = User::factory()->create();
    $this->employee = Employee::factory()->create([
        'user_id' => $this->employeeUser->id,
        'department_id' => $this->department->id,
    ]);

    $this->approverUser = User::factory()->create();
    $this->approverEmployee = Employee::factory()->create([
        'user_id' => $this->approverUser->id,
        'department_id' => $this->department->id,
    ]);

    DepartmentApprover::create([
        'department_id' => $this->department->id,
        'approver_employee_id' => $this->approverEmployee->id,
        'approval_type' => 'exit_permission',
    ]);
});

// --- Employee self-service ---

it('employee can submit an exit permission request', function (): void {
    $response = actingAs($this->employeeUser)
        ->postJson('/api/hr/my/exit-permissions', [
            'exit_date' => now()->addDay()->toDateString(),
            'exit_time' => '14:00',
            'return_time' => '16:00',
            'errand_type' => 'personal',
            'purpose' => 'Personal medical appointment downtown.',
            'addressed_to' => 'Manager HR',
        ]);

    $response->assertCreated();
    expect(OfficeExitPermission::count())->toBe(1);
    expect(OfficeExitPermission::first()->permission_number)->toStartWith('OEP-');
});

it('employee cannot submit with return_time before exit_time', function (): void {
    actingAs($this->employeeUser)
        ->postJson('/api/hr/my/exit-permissions', [
            'exit_date' => now()->addDay()->toDateString(),
            'exit_time' => '16:00',
            'return_time' => '14:00',
            'errand_type' => 'company',
            'purpose' => 'Company errand to government office.',
            'addressed_to' => 'Manager',
        ])
        ->assertUnprocessable();
});

it('employee can list their own requests', function (): void {
    OfficeExitPermission::factory()->create(['employee_id' => $this->employee->id]);

    actingAs($this->employeeUser)
        ->getJson('/api/hr/my/exit-permissions')
        ->assertOk()
        ->assertJsonCount(1, 'data.data');
});

it('employee can cancel a pending request', function (): void {
    $permission = OfficeExitPermission::factory()->create([
        'employee_id' => $this->employee->id,
        'status' => 'pending',
    ]);

    actingAs($this->employeeUser)
        ->deleteJson("/api/hr/my/exit-permissions/{$permission->id}")
        ->assertOk();

    expect($permission->fresh()->status)->toBe('cancelled');
});

it('employee cannot cancel an approved request', function (): void {
    $permission = OfficeExitPermission::factory()->create([
        'employee_id' => $this->employee->id,
        'status' => 'approved',
    ]);

    actingAs($this->employeeUser)
        ->deleteJson("/api/hr/my/exit-permissions/{$permission->id}")
        ->assertUnprocessable();
});

// --- Admin approval ---

it('admin can approve a pending exit permission', function (): void {
    Notification::fake();

    $permission = OfficeExitPermission::factory()->create([
        'employee_id' => $this->employee->id,
        'status' => 'pending',
    ]);

    actingAs($this->admin)
        ->patchJson("/api/hr/exit-permissions/{$permission->id}/approve")
        ->assertOk();

    expect($permission->fresh()->status)->toBe('approved');
    Notification::assertSentTo($this->employeeUser, ExitPermissionApproved::class);
});

it('admin cannot approve an already approved permission', function (): void {
    $permission = OfficeExitPermission::factory()->create([
        'employee_id' => $this->employee->id,
        'status' => 'approved',
    ]);

    actingAs($this->admin)
        ->patchJson("/api/hr/exit-permissions/{$permission->id}/approve")
        ->assertUnprocessable();
});

it('admin can reject with a reason', function (): void {
    Notification::fake();

    $permission = OfficeExitPermission::factory()->create([
        'employee_id' => $this->employee->id,
        'status' => 'pending',
    ]);

    actingAs($this->admin)
        ->patchJson("/api/hr/exit-permissions/{$permission->id}/reject", [
            'rejection_reason' => 'Busy period, please reschedule.',
        ])
        ->assertOk();

    expect($permission->fresh()->status)->toBe('rejected');
    Notification::assertSentTo($this->employeeUser, ExitPermissionRejected::class);
});

it('admin cannot reject without a reason', function (): void {
    $permission = OfficeExitPermission::factory()->create([
        'employee_id' => $this->employee->id,
        'status' => 'pending',
    ]);

    actingAs($this->admin)
        ->patchJson("/api/hr/exit-permissions/{$permission->id}/reject", [])
        ->assertUnprocessable();
});

// --- Attendance note ---

it('attendance log gets a note when permission is approved', function (): void {
    $exitDate = now()->addDay()->toDateString();

    AttendanceLog::create([
        'employee_id' => $this->employee->id,
        'date' => $exitDate,
        'status' => 'present',
    ]);

    $permission = OfficeExitPermission::factory()->create([
        'employee_id' => $this->employee->id,
        'status' => 'pending',
        'exit_date' => $exitDate,
        'exit_time' => '14:00:00',
        'return_time' => '16:00:00',
        'errand_type' => 'company',
    ]);

    actingAs($this->admin)
        ->patchJson("/api/hr/exit-permissions/{$permission->id}/approve")
        ->assertOk();

    expect($permission->fresh()->attendance_note_created)->toBeTrue();
});

// --- CC Notifiers ---

it('cc notifiers receive email when permission is approved', function (): void {
    Notification::fake();

    $notifierUser = User::factory()->create();
    $notifierEmployee = Employee::factory()->create([
        'user_id' => $notifierUser->id,
        'department_id' => $this->department->id,
    ]);

    ExitPermissionNotifier::create([
        'department_id' => $this->department->id,
        'employee_id' => $notifierEmployee->id,
    ]);

    $permission = OfficeExitPermission::factory()->create([
        'employee_id' => $this->employee->id,
        'status' => 'pending',
    ]);

    actingAs($this->admin)
        ->patchJson("/api/hr/exit-permissions/{$permission->id}/approve")
        ->assertOk();

    Notification::assertSentTo($notifierUser, ExitPermissionApproved::class);
});

// --- PDF ---

it('admin can download pdf for approved permission', function (): void {
    $permission = OfficeExitPermission::factory()->create([
        'employee_id' => $this->employee->id,
        'status' => 'approved',
        'approved_by' => $this->admin->id,
        'approved_at' => now(),
    ]);

    actingAs($this->admin)
        ->get("/api/hr/exit-permissions/{$permission->id}/pdf")
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');
});

it('pdf is not available for pending permissions', function (): void {
    $permission = OfficeExitPermission::factory()->create([
        'employee_id' => $this->employee->id,
        'status' => 'pending',
    ]);

    actingAs($this->admin)
        ->get("/api/hr/exit-permissions/{$permission->id}/pdf")
        ->assertForbidden();
});

// --- HOD Approval ---

it('assigned hod can approve exit permission in their department', function (): void {
    Notification::fake();

    $permission = OfficeExitPermission::factory()->create([
        'employee_id' => $this->employee->id,
        'status' => 'pending',
    ]);

    actingAs($this->approverUser)
        ->patchJson("/api/hr/my-approvals/exit-permissions/{$permission->id}/approve")
        ->assertOk();

    expect($permission->fresh()->status)->toBe('approved');
});

it('non-assigned hod cannot approve exit permission', function (): void {
    $otherUser = User::factory()->create();
    Employee::factory()->create(['user_id' => $otherUser->id]);

    $permission = OfficeExitPermission::factory()->create([
        'employee_id' => $this->employee->id,
        'status' => 'pending',
    ]);

    actingAs($otherUser)
        ->patchJson("/api/hr/my-approvals/exit-permissions/{$permission->id}/approve")
        ->assertForbidden();
});
```

**Step 3: Create factory**
```bash
php artisan make:factory OfficeExitPermissionFactory --model=OfficeExitPermission --no-interaction
```

Edit the generated factory to add sensible defaults:
```php
<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\OfficeExitPermission;
use Illuminate\Database\Eloquent\Factories\Factory;

class OfficeExitPermissionFactory extends Factory
{
    protected $model = OfficeExitPermission::class;

    public function definition(): array
    {
        return [
            'permission_number' => OfficeExitPermission::generatePermissionNumber(),
            'employee_id' => Employee::factory(),
            'exit_date' => now()->addDay()->toDateString(),
            'exit_time' => '14:00:00',
            'return_time' => '16:00:00',
            'errand_type' => fake()->randomElement(['company', 'personal']),
            'purpose' => fake()->sentence(15),
            'addressed_to' => fake()->name(),
            'status' => 'pending',
        ];
    }
}
```

**Step 4: Run the tests**
```bash
php artisan test --compact tests/Feature/Hr/ExitPermissionTest.php
```
Expected: All tests pass.

**Step 5: Commit backend**
```bash
git add app/ database/ routes/ resources/views/pdf/ tests/
git commit -m "feat(hr): add Office Exit Permission backend — model, controllers, notifications, PDF, tests"
```

---

## Task 14: Add React API functions

**Files:**
- Modify: `resources/js/hr/lib/api.js`

**Step 1: Read the end of api.js to find the right place to append**

Open `resources/js/hr/lib/api.js` and scroll to the bottom. Add these functions following the exact same pattern as the existing overtime functions:

```js
// ============================================================
// Exit Permissions - Admin
// ============================================================
export const getExitPermissions = (params) =>
    api.get('/exit-permissions', { params }).then(r => r.data);

export const approveExitPermission = (id) =>
    api.patch(`/exit-permissions/${id}/approve`).then(r => r.data);

export const rejectExitPermission = (id, data) =>
    api.patch(`/exit-permissions/${id}/reject`, data).then(r => r.data);

export const getExitPermissionPdfUrl = (id) => `/api/hr/exit-permissions/${id}/pdf`;

// ============================================================
// Exit Permission Notifiers - Admin
// ============================================================
export const getExitPermissionNotifiers = (params) =>
    api.get('/exit-permission-notifiers', { params }).then(r => r.data);

export const addExitPermissionNotifier = (data) =>
    api.post('/exit-permission-notifiers', data).then(r => r.data);

export const removeExitPermissionNotifier = (id) =>
    api.delete(`/exit-permission-notifiers/${id}`).then(r => r.data);

// ============================================================
// Exit Permissions - Employee Self-Service
// ============================================================
export const getMyExitPermissions = (params) =>
    api.get('/my/exit-permissions', { params }).then(r => r.data);

export const submitExitPermission = (data) =>
    api.post('/my/exit-permissions', data).then(r => r.data);

export const cancelMyExitPermission = (id) =>
    api.delete(`/my/exit-permissions/${id}`).then(r => r.data);

// ============================================================
// Exit Permissions - HOD Approvals
// ============================================================
export const getMyApprovalsExitPermissions = (params) =>
    api.get('/my-approvals/exit-permissions', { params }).then(r => r.data);

export const approveMyExitPermission = (id) =>
    api.patch(`/my-approvals/exit-permissions/${id}/approve`).then(r => r.data);

export const rejectMyExitPermission = (id, data) =>
    api.patch(`/my-approvals/exit-permissions/${id}/reject`, data).then(r => r.data);
```

---

## Task 15: Create admin ExitPermissions.jsx page

**Files:**
- Create: `resources/js/hr/pages/exitpermissions/ExitPermissions.jsx`

**Step 1: Create the directory**
```bash
mkdir -p resources/js/hr/pages/exitpermissions
```

**Step 2: Reference MyApprovalsOvertime.jsx pattern**

Open `resources/js/hr/pages/my/MyApprovalsOvertime.jsx` as a reference. The admin page follows the same pattern but with additional department filter and access to approve/reject from admin.

Create `resources/js/hr/pages/exitpermissions/ExitPermissions.jsx`:

```jsx
import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { DoorOpen, Check, X, Download } from 'lucide-react';
import {
    getExitPermissions,
    approveExitPermission,
    rejectExitPermission,
    getExitPermissionPdfUrl,
} from '../../lib/api';

const STATUS_COLORS = {
    pending: 'bg-amber-100 text-amber-800',
    approved: 'bg-emerald-100 text-emerald-800',
    rejected: 'bg-red-100 text-red-800',
    cancelled: 'bg-gray-100 text-gray-600',
};

const ERRAND_LABELS = {
    company: 'Company Business',
    personal: 'Personal Business',
};

export default function ExitPermissions() {
    const [statusFilter, setStatusFilter] = useState('');
    const [errandFilter, setErrandFilter] = useState('');
    const [rejectingId, setRejectingId] = useState(null);
    const [rejectReason, setRejectReason] = useState('');
    const queryClient = useQueryClient();

    const { data, isLoading } = useQuery({
        queryKey: ['exit-permissions', statusFilter, errandFilter],
        queryFn: () => getExitPermissions({ status: statusFilter || undefined, errand_type: errandFilter || undefined }),
    });

    const approveMutation = useMutation({
        mutationFn: approveExitPermission,
        onSuccess: () => queryClient.invalidateQueries({ queryKey: ['exit-permissions'] }),
    });

    const rejectMutation = useMutation({
        mutationFn: ({ id, reason }) => rejectExitPermission(id, { rejection_reason: reason }),
        onSuccess: () => {
            setRejectingId(null);
            setRejectReason('');
            queryClient.invalidateQueries({ queryKey: ['exit-permissions'] });
        },
    });

    const permissions = data?.data?.data ?? [];

    return (
        <div className="p-6">
            <div className="mb-6 flex items-center justify-between">
                <div>
                    <h1 className="text-2xl font-bold text-gray-900">Exit Permissions</h1>
                    <p className="mt-1 text-sm text-gray-500">Manage office exit permission requests</p>
                </div>
            </div>

            {/* Filters */}
            <div className="mb-4 flex gap-3">
                <select
                    value={statusFilter}
                    onChange={e => setStatusFilter(e.target.value)}
                    className="rounded-md border border-gray-300 px-3 py-2 text-sm"
                >
                    <option value="">All Statuses</option>
                    <option value="pending">Pending</option>
                    <option value="approved">Approved</option>
                    <option value="rejected">Rejected</option>
                    <option value="cancelled">Cancelled</option>
                </select>
                <select
                    value={errandFilter}
                    onChange={e => setErrandFilter(e.target.value)}
                    className="rounded-md border border-gray-300 px-3 py-2 text-sm"
                >
                    <option value="">All Types</option>
                    <option value="company">Company Business</option>
                    <option value="personal">Personal Business</option>
                </select>
            </div>

            {/* Table */}
            {isLoading ? (
                <div className="text-center py-10 text-gray-400">Loading...</div>
            ) : (
                <div className="overflow-x-auto rounded-lg border border-gray-200">
                    <table className="min-w-full divide-y divide-gray-200 text-sm">
                        <thead className="bg-gray-50">
                            <tr>
                                <th className="px-4 py-3 text-left font-medium text-gray-600">Ref No</th>
                                <th className="px-4 py-3 text-left font-medium text-gray-600">Employee</th>
                                <th className="px-4 py-3 text-left font-medium text-gray-600">Date</th>
                                <th className="px-4 py-3 text-left font-medium text-gray-600">Time</th>
                                <th className="px-4 py-3 text-left font-medium text-gray-600">Type</th>
                                <th className="px-4 py-3 text-left font-medium text-gray-600">Status</th>
                                <th className="px-4 py-3 text-left font-medium text-gray-600">Actions</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-200 bg-white">
                            {permissions.length === 0 && (
                                <tr><td colSpan={7} className="text-center py-8 text-gray-400">No records found.</td></tr>
                            )}
                            {permissions.map(p => (
                                <tr key={p.id} className="hover:bg-gray-50">
                                    <td className="px-4 py-3 font-mono text-xs">{p.permission_number}</td>
                                    <td className="px-4 py-3">
                                        <div className="font-medium">{p.employee?.full_name}</div>
                                        <div className="text-xs text-gray-400">{p.employee?.department?.name}</div>
                                    </td>
                                    <td className="px-4 py-3">{p.exit_date}</td>
                                    <td className="px-4 py-3 text-xs">{p.exit_time} – {p.return_time}</td>
                                    <td className="px-4 py-3">
                                        <span className="rounded-full bg-blue-50 text-blue-700 px-2 py-0.5 text-xs">
                                            {ERRAND_LABELS[p.errand_type]}
                                        </span>
                                    </td>
                                    <td className="px-4 py-3">
                                        <span className={`rounded-full px-2 py-0.5 text-xs font-medium ${STATUS_COLORS[p.status]}`}>
                                            {p.status}
                                        </span>
                                    </td>
                                    <td className="px-4 py-3">
                                        <div className="flex items-center gap-2">
                                            {p.status === 'pending' && (
                                                <>
                                                    <button
                                                        onClick={() => approveMutation.mutate(p.id)}
                                                        className="rounded bg-emerald-600 px-2 py-1 text-xs text-white hover:bg-emerald-700"
                                                    >
                                                        <Check className="inline h-3 w-3" /> Approve
                                                    </button>
                                                    <button
                                                        onClick={() => { setRejectingId(p.id); setRejectReason(''); }}
                                                        className="rounded bg-red-600 px-2 py-1 text-xs text-white hover:bg-red-700"
                                                    >
                                                        <X className="inline h-3 w-3" /> Reject
                                                    </button>
                                                </>
                                            )}
                                            {p.status === 'approved' && (
                                                <a
                                                    href={getExitPermissionPdfUrl(p.id)}
                                                    target="_blank"
                                                    rel="noreferrer"
                                                    className="rounded bg-indigo-600 px-2 py-1 text-xs text-white hover:bg-indigo-700"
                                                >
                                                    <Download className="inline h-3 w-3" /> PDF
                                                </a>
                                            )}
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}

            {/* Reject Modal */}
            {rejectingId && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
                    <div className="w-full max-w-md rounded-lg bg-white p-6 shadow-xl">
                        <h3 className="mb-3 text-base font-semibold">Reject Exit Permission</h3>
                        <textarea
                            value={rejectReason}
                            onChange={e => setRejectReason(e.target.value)}
                            placeholder="Reason for rejection (min 5 characters)..."
                            rows={3}
                            className="w-full rounded border border-gray-300 p-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-400"
                        />
                        <div className="mt-4 flex justify-end gap-2">
                            <button onClick={() => setRejectingId(null)} className="rounded border px-4 py-2 text-sm">Cancel</button>
                            <button
                                disabled={rejectReason.length < 5 || rejectMutation.isPending}
                                onClick={() => rejectMutation.mutate({ id: rejectingId, reason: rejectReason })}
                                className="rounded bg-red-600 px-4 py-2 text-sm text-white hover:bg-red-700 disabled:opacity-50"
                            >
                                Confirm Reject
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}
```

---

## Task 16: Create admin ExitPermissionNotifiers.jsx page

**Files:**
- Create: `resources/js/hr/pages/exitpermissions/ExitPermissionNotifiers.jsx`

Follow the same pattern as existing approver management pages (e.g. `resources/js/hr/pages/attendance/DepartmentApprovers.jsx`). Read that file first to match its UI pattern exactly.

The page should:
1. Show a list of all configured notifiers grouped or filtered by department
2. Allow adding a new notifier: select department + select employee
3. Allow removing a notifier with a confirm button

Use `getExitPermissionNotifiers`, `addExitPermissionNotifier`, `removeExitPermissionNotifier` from api.js. Reuse the same department/employee data fetching pattern as DepartmentApprovers.jsx.

---

## Task 17: Create employee MyExitPermissions.jsx and ApplyExitPermission.jsx

**Files:**
- Create: `resources/js/hr/pages/my/MyExitPermissions.jsx`
- Create: `resources/js/hr/pages/my/ApplyExitPermission.jsx`

**MyExitPermissions.jsx**: Follow `MyOvertime.jsx` pattern exactly:
- List own requests with status badges
- "Apply for Exit Permission" button → navigate to `/my/exit-permissions/apply`
- Cancel button on pending requests (calls `cancelMyExitPermission`)
- Download PDF button for approved requests (uses `getExitPermissionPdfUrl`)
- Use `getMyExitPermissions` query

**ApplyExitPermission.jsx**: Follow `ApplyLeave.jsx` pattern:
- Form fields: addressed_to (text), exit_date (date, min=today), exit_time (time), return_time (time), errand_type (radio: company/personal), purpose (textarea, min 10 chars)
- Submit calls `submitExitPermission`
- On success, navigate to `/my/exit-permissions`
- Show validation errors inline

---

## Task 18: Create MyApprovalsExitPermissions.jsx

**Files:**
- Create: `resources/js/hr/pages/my/MyApprovalsExitPermissions.jsx`

Copy the structure of `MyApprovalsOvertime.jsx` exactly. Replace:
- API calls: `getMyApprovalsExitPermissions`, `approveMyExitPermission`, `rejectMyExitPermission`
- Display fields: employee, exit_date, exit_time–return_time, errand_type, purpose
- Status tabs: pending, approved, rejected, all

---

## Task 19: Update MyApprovals.jsx — add Exit Permissions card

**Files:**
- Modify: `resources/js/hr/pages/my/MyApprovals.jsx`

**Step 1: Read MyApprovals.jsx**

Find the `MODULES` array. Add this entry:
```jsx
{
    key: 'exit_permission',
    label: 'Exit Permissions',
    description: 'Review office exit requests',
    icon: DoorOpen,
    route: '/my/approvals/exit-permissions',
    pendingKey: 'exit_permission',
},
```

Also add the `DoorOpen` icon import from `lucide-react`.

---

## Task 20: Update App.jsx — add new routes

**Files:**
- Modify: `resources/js/hr/App.jsx`

**Step 1: Read App.jsx**

Find the admin routes section and the employee routes section. Add:

**Admin routes** (in AdminRoutes function):
```jsx
import ExitPermissions from './pages/exitpermissions/ExitPermissions';
import ExitPermissionNotifiers from './pages/exitpermissions/ExitPermissionNotifiers';

// Inside AdminRoutes:
<Route path="exit-permissions" element={<ExitPermissions />} />
<Route path="exit-permissions/notifiers" element={<ExitPermissionNotifiers />} />
```

**Employee routes** (in EmployeeRoutes function):
```jsx
import MyExitPermissions from './pages/my/MyExitPermissions';
import ApplyExitPermission from './pages/my/ApplyExitPermission';
import MyApprovalsExitPermissions from './pages/my/MyApprovalsExitPermissions';

// Inside EmployeeRoutes:
<Route path="my/exit-permissions" element={<MyExitPermissions />} />
<Route path="my/exit-permissions/apply" element={<ApplyExitPermission />} />
<Route path="my/approvals/exit-permissions" element={<MyApprovalsExitPermissions />} />
```

---

## Task 21: Update HrLayout.jsx — add admin sidebar entry

**Files:**
- Modify: `resources/js/hr/layouts/HrLayout.jsx`

**Step 1: Read HrLayout.jsx**

Find the `navigation` array. Add a new top-level entry (after Claims, before Payroll or wherever makes organizational sense):

```jsx
import { DoorOpen } from 'lucide-react';

// In navigation array:
{
    name: 'Exit Permissions',
    icon: DoorOpen,
    prefix: '/exit-permissions',
    children: [
        { name: 'Requests', to: '/exit-permissions' },
        { name: 'Notifiers', to: '/exit-permissions/notifiers' },
    ],
},
```

---

## Task 22: Update EmployeeAppLayout.jsx — add employee sidebar entry

**Files:**
- Modify: `resources/js/hr/layouts/EmployeeAppLayout.jsx`

**Step 1: Read EmployeeAppLayout.jsx**

Find the `sidebarNav` array. Add after "My Claims":
```jsx
import { DoorOpen } from 'lucide-react';

{ name: 'My Exit Permissions', to: '/my/exit-permissions', icon: DoorOpen },
```

Also update `useApprovalSummary` if it maps `exit_permission` pending count — ensure the `isApprover` check includes `data?.exit_permission?.isAssigned`.

---

## Task 23: Build assets and final verification

**Step 1: Build**
```bash
npm run build
```
Expected: Build completes with no errors.

**Step 2: Run Pint**
```bash
./vendor/bin/pint --dirty
```

**Step 3: Run all HR tests**
```bash
php artisan test --compact tests/Feature/Hr/
```
Expected: All tests pass.

**Step 4: Commit frontend**
```bash
git add resources/js/ resources/views/
git commit -m "feat(hr): add Office Exit Permission frontend — admin, self-service, approvals, PDF download"
```

**Step 5: Final commit**
```bash
git add .
git commit -m "feat(hr): complete Office Exit Permission (Surat Pelepasan) module"
```

---

## Summary

| # | Task | Files |
|---|------|-------|
| 1 | DB migrations (enum extension + notifiers table) | 2 migrations |
| 2 | DB migration (exit permissions table) | 1 migration |
| 3 | Models | OfficeExitPermission, ExitPermissionNotifier |
| 4 | Form request | StoreExitPermissionRequest |
| 5 | Notifications | ExitPermissionApproved, ExitPermissionRejected |
| 6 | Admin controller | HrOfficeExitPermissionController |
| 7 | Employee controller | HrMyExitPermissionController |
| 8 | Extend approvals controller | HrMyApprovalController |
| 9 | Notifier controller | HrExitPermissionNotifierController |
| 10 | Routes | routes/api.php |
| 11 | PDF template | resources/views/pdf/exit-permission.blade.php |
| 12 | Pint + route verify | — |
| 13 | Backend tests + factory | ExitPermissionTest.php |
| 14 | React API functions | lib/api.js |
| 15 | Admin page | ExitPermissions.jsx |
| 16 | Admin notifiers page | ExitPermissionNotifiers.jsx |
| 17 | Employee pages | MyExitPermissions.jsx, ApplyExitPermission.jsx |
| 18 | Approver page | MyApprovalsExitPermissions.jsx |
| 19 | Update approvals hub | MyApprovals.jsx |
| 20 | Update routing | App.jsx |
| 21 | Update admin nav | HrLayout.jsx |
| 22 | Update employee nav | EmployeeAppLayout.jsx |
| 23 | Build + final tests | — |
