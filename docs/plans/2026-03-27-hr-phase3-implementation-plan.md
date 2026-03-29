# HR Phase 3: Payroll & Claims/Benefits/Assets — Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Build Module 5 (Payroll & Compensation) and Module 6 (Claims, Benefits & Asset Tracking) as Phase 3 of the HR system.

**Architecture:** Laravel API controllers + React SPA pages, following existing Phase 1 & 2 patterns. Payroll is the core — statutory calculations (EPF/SOCSO/EIS/PCB) built as a service class. Claims, Benefits, and Assets are simpler CRUD modules with approval workflows for claims. Module 6 has no dependency on Module 5 in initial build (payroll integration is future).

**Tech Stack:** Laravel 12, Pest tests, React 19, Shadcn/ui, TanStack Query, Recharts, Laravel DomPDF (payslips/EA Forms)

**Design Docs:**
- [Module 5 - Payroll](2026-03-27-hr-module5-payroll-design.md)
- [Module 6 - Claims, Benefits & Assets](2026-03-27-hr-module6-claims-design.md)

---

## Build Order Overview

```
Task 1-3:   Payroll Migrations (9 tables)
Task 4-6:   Payroll Models (9 models + relationships)
Task 7:     Payroll Settings Seeder (default data)
Task 8-9:   Statutory Calculation Service
Task 10-12: Payroll Controllers (runs, components, salaries)
Task 13-14: Payroll Controllers (tax profiles, payslips, reports, settings, dashboard)
Task 15:    Payroll Form Requests
Task 16:    Payroll API Routes
Task 17-18: Payroll Tests
Task 19-21: Claims/Benefits/Assets Migrations (8 tables)
Task 22-24: Claims/Benefits/Assets Models
Task 25-27: Claims/Benefits/Assets Controllers
Task 28:    Claims/Benefits/Assets Form Requests
Task 29:    Claims/Benefits/Assets API Routes
Task 30-31: Claims/Benefits/Assets Tests
Task 32:    React API Client (all Phase 3 endpoints)
Task 33-37: Payroll React Pages (10 admin + 1 self-service)
Task 38-41: Claims/Benefits/Assets React Pages (10 admin + 2 self-service)
Task 42:    React Router Updates
Task 43:    PDF Payslip Template
Task 44:    Phase 3 Seeder
Task 45:    Final Integration Test
```

---

## Task 1: Migration — Payroll Core Tables (salary_components, employee_salaries, salary_revisions)

**Files:**
- Create: `database/migrations/YYYY_MM_DD_HHMMSS_create_salary_components_table.php`
- Create: `database/migrations/YYYY_MM_DD_HHMMSS_create_employee_salaries_table.php`
- Create: `database/migrations/YYYY_MM_DD_HHMMSS_create_salary_revisions_table.php`

**Step 1: Create salary_components migration**

```bash
php artisan make:migration create_salary_components_table --no-interaction
```

```php
Schema::create('salary_components', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('code', 20)->unique();
    $table->enum('type', ['earning', 'deduction']);
    $table->enum('category', ['basic', 'fixed_allowance', 'variable_allowance', 'fixed_deduction', 'variable_deduction']);
    $table->boolean('is_taxable')->default(true);
    $table->boolean('is_epf_applicable')->default(true);
    $table->boolean('is_socso_applicable')->default(true);
    $table->boolean('is_eis_applicable')->default(true);
    $table->boolean('is_system')->default(false);
    $table->boolean('is_active')->default(true);
    $table->integer('sort_order')->default(0);
    $table->timestamps();
});
```

**Step 2: Create employee_salaries migration**

```bash
php artisan make:migration create_employee_salaries_table --no-interaction
```

```php
Schema::create('employee_salaries', function (Blueprint $table) {
    $table->id();
    $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
    $table->foreignId('salary_component_id')->constrained('salary_components')->cascadeOnDelete();
    $table->decimal('amount', 10, 2);
    $table->date('effective_from');
    $table->date('effective_to')->nullable();
    $table->timestamps();

    $table->index(['employee_id', 'effective_from']);
});
```

**Step 3: Create salary_revisions migration**

```bash
php artisan make:migration create_salary_revisions_table --no-interaction
```

```php
Schema::create('salary_revisions', function (Blueprint $table) {
    $table->id();
    $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
    $table->foreignId('salary_component_id')->constrained('salary_components')->cascadeOnDelete();
    $table->decimal('old_amount', 10, 2);
    $table->decimal('new_amount', 10, 2);
    $table->date('effective_date');
    $table->text('reason')->nullable();
    $table->foreignId('changed_by')->constrained('users');
    $table->timestamp('created_at')->useCurrent();
});
```

**Step 4: Run migrations**

```bash
php artisan migrate
```

**Step 5: Commit**

```bash
git add database/migrations/
git commit -m "feat(hr): add salary_components, employee_salaries, salary_revisions migrations"
```

---

## Task 2: Migration — Statutory & Tax Tables (statutory_rates, pcb_rates, employee_tax_profiles)

**Files:**
- Create: `database/migrations/YYYY_MM_DD_HHMMSS_create_statutory_rates_table.php`
- Create: `database/migrations/YYYY_MM_DD_HHMMSS_create_pcb_rates_table.php`
- Create: `database/migrations/YYYY_MM_DD_HHMMSS_create_employee_tax_profiles_table.php`

**Step 1: Create statutory_rates migration**

```bash
php artisan make:migration create_statutory_rates_table --no-interaction
```

```php
Schema::create('statutory_rates', function (Blueprint $table) {
    $table->id();
    $table->enum('type', ['epf_employee', 'epf_employer', 'socso_employee', 'socso_employer', 'eis_employee', 'eis_employer']);
    $table->decimal('min_salary', 10, 2);
    $table->decimal('max_salary', 10, 2)->nullable();
    $table->decimal('rate_percentage', 5, 2)->nullable();
    $table->decimal('fixed_amount', 8, 2)->nullable();
    $table->date('effective_from');
    $table->date('effective_to')->nullable();
    $table->timestamps();

    $table->index(['type', 'effective_from']);
});
```

**Step 2: Create pcb_rates migration**

```bash
php artisan make:migration create_pcb_rates_table --no-interaction
```

```php
Schema::create('pcb_rates', function (Blueprint $table) {
    $table->id();
    $table->enum('category', ['single', 'married_spouse_not_working', 'married_spouse_working']);
    $table->integer('num_children')->default(0);
    $table->decimal('min_monthly_income', 10, 2);
    $table->decimal('max_monthly_income', 10, 2)->nullable();
    $table->decimal('pcb_amount', 8, 2);
    $table->integer('year');
    $table->timestamps();

    $table->index(['category', 'year']);
});
```

**Step 3: Create employee_tax_profiles migration**

```bash
php artisan make:migration create_employee_tax_profiles_table --no-interaction
```

```php
Schema::create('employee_tax_profiles', function (Blueprint $table) {
    $table->id();
    $table->foreignId('employee_id')->unique()->constrained('employees')->cascadeOnDelete();
    $table->string('tax_number')->nullable();
    $table->enum('marital_status', ['single', 'married_spouse_not_working', 'married_spouse_working'])->default('single');
    $table->integer('num_children')->default(0);
    $table->integer('num_children_studying')->default(0);
    $table->boolean('disabled_individual')->default(false);
    $table->boolean('disabled_spouse')->default(false);
    $table->boolean('is_pcb_manual')->default(false);
    $table->decimal('manual_pcb_amount', 8, 2)->nullable();
    $table->timestamps();
});
```

**Step 4: Run migrations**

```bash
php artisan migrate
```

**Step 5: Commit**

```bash
git add database/migrations/
git commit -m "feat(hr): add statutory_rates, pcb_rates, employee_tax_profiles migrations"
```

---

## Task 3: Migration — Payroll Run Tables (payroll_runs, payroll_items, payslips, payroll_settings)

**Files:**
- Create: `database/migrations/YYYY_MM_DD_HHMMSS_create_payroll_runs_table.php`
- Create: `database/migrations/YYYY_MM_DD_HHMMSS_create_payroll_items_table.php`
- Create: `database/migrations/YYYY_MM_DD_HHMMSS_create_payslips_table.php`
- Create: `database/migrations/YYYY_MM_DD_HHMMSS_create_payroll_settings_table.php`

**Step 1: Create payroll_runs migration**

```bash
php artisan make:migration create_payroll_runs_table --no-interaction
```

```php
Schema::create('payroll_runs', function (Blueprint $table) {
    $table->id();
    $table->integer('month');
    $table->integer('year');
    $table->enum('status', ['draft', 'review', 'approved', 'finalized'])->default('draft');
    $table->decimal('total_gross', 12, 2)->default(0);
    $table->decimal('total_deductions', 12, 2)->default(0);
    $table->decimal('total_net', 12, 2)->default(0);
    $table->decimal('total_employer_cost', 12, 2)->default(0);
    $table->integer('employee_count')->default(0);
    $table->foreignId('prepared_by')->constrained('users');
    $table->foreignId('reviewed_by')->nullable()->constrained('users');
    $table->foreignId('approved_by')->nullable()->constrained('users');
    $table->timestamp('approved_at')->nullable();
    $table->timestamp('finalized_at')->nullable();
    $table->text('notes')->nullable();
    $table->timestamps();

    $table->unique(['month', 'year']);
});
```

**Step 2: Create payroll_items migration**

```bash
php artisan make:migration create_payroll_items_table --no-interaction
```

```php
Schema::create('payroll_items', function (Blueprint $table) {
    $table->id();
    $table->foreignId('payroll_run_id')->constrained('payroll_runs')->cascadeOnDelete();
    $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
    $table->foreignId('salary_component_id')->nullable()->constrained('salary_components')->nullOnDelete();
    $table->string('component_code');
    $table->string('component_name');
    $table->enum('type', ['earning', 'deduction', 'employer_contribution']);
    $table->decimal('amount', 10, 2);
    $table->boolean('is_statutory')->default(false);
    $table->timestamps();

    $table->index(['payroll_run_id', 'employee_id']);
});
```

**Step 3: Create payslips migration**

```bash
php artisan make:migration create_payslips_table --no-interaction
```

```php
Schema::create('payslips', function (Blueprint $table) {
    $table->id();
    $table->foreignId('payroll_run_id')->constrained('payroll_runs')->cascadeOnDelete();
    $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
    $table->integer('month');
    $table->integer('year');
    $table->decimal('gross_salary', 10, 2);
    $table->decimal('total_deductions', 10, 2);
    $table->decimal('net_salary', 10, 2);
    $table->decimal('epf_employee', 8, 2)->default(0);
    $table->decimal('epf_employer', 8, 2)->default(0);
    $table->decimal('socso_employee', 8, 2)->default(0);
    $table->decimal('socso_employer', 8, 2)->default(0);
    $table->decimal('eis_employee', 8, 2)->default(0);
    $table->decimal('eis_employer', 8, 2)->default(0);
    $table->decimal('pcb_amount', 8, 2)->default(0);
    $table->integer('unpaid_leave_days')->default(0);
    $table->decimal('unpaid_leave_deduction', 8, 2)->default(0);
    $table->string('pdf_path')->nullable();
    $table->timestamps();

    $table->unique(['employee_id', 'month', 'year']);
});
```

**Step 4: Create payroll_settings migration**

```bash
php artisan make:migration create_payroll_settings_table --no-interaction
```

```php
Schema::create('payroll_settings', function (Blueprint $table) {
    $table->id();
    $table->string('key')->unique();
    $table->string('value');
    $table->string('description');
    $table->timestamps();
});
```

**Step 5: Run migrations**

```bash
php artisan migrate
```

**Step 6: Commit**

```bash
git add database/migrations/
git commit -m "feat(hr): add payroll_runs, payroll_items, payslips, payroll_settings migrations"
```

---

## Task 4: Payroll Models — Core (SalaryComponent, EmployeeSalary, SalaryRevision)

**Files:**
- Create: `app/Models/SalaryComponent.php`
- Create: `app/Models/EmployeeSalary.php`
- Create: `app/Models/SalaryRevision.php`

**Step 1: Create models**

```bash
php artisan make:model SalaryComponent --factory --no-interaction
php artisan make:model EmployeeSalary --factory --no-interaction
php artisan make:model SalaryRevision --factory --no-interaction
```

**Step 2: Implement SalaryComponent model**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalaryComponent extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'code', 'type', 'category',
        'is_taxable', 'is_epf_applicable', 'is_socso_applicable', 'is_eis_applicable',
        'is_system', 'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_taxable' => 'boolean',
            'is_epf_applicable' => 'boolean',
            'is_socso_applicable' => 'boolean',
            'is_eis_applicable' => 'boolean',
            'is_system' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function employeeSalaries(): HasMany
    {
        return $this->hasMany(EmployeeSalary::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeEarnings($query)
    {
        return $query->where('type', 'earning');
    }

    public function scopeDeductions($query)
    {
        return $query->where('type', 'deduction');
    }

    public function scopeSystem($query)
    {
        return $query->where('is_system', true);
    }
}
```

**Step 3: Implement EmployeeSalary model**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeSalary extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id', 'salary_component_id', 'amount', 'effective_from', 'effective_to',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'effective_from' => 'date',
            'effective_to' => 'date',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function salaryComponent(): BelongsTo
    {
        return $this->belongsTo(SalaryComponent::class);
    }

    public function scopeActive($query)
    {
        return $query->whereNull('effective_to')
            ->orWhere('effective_to', '>=', now());
    }

    public function scopeForEmployee($query, int $employeeId)
    {
        return $query->where('employee_id', $employeeId);
    }
}
```

**Step 4: Implement SalaryRevision model**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalaryRevision extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'employee_id', 'salary_component_id', 'old_amount', 'new_amount',
        'effective_date', 'reason', 'changed_by',
    ];

    protected function casts(): array
    {
        return [
            'old_amount' => 'decimal:2',
            'new_amount' => 'decimal:2',
            'effective_date' => 'date',
            'created_at' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function salaryComponent(): BelongsTo
    {
        return $this->belongsTo(SalaryComponent::class);
    }

    public function changedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
```

**Step 5: Add relationships to Employee model**

In `app/Models/Employee.php`, add:

```php
public function salaries(): HasMany
{
    return $this->hasMany(EmployeeSalary::class);
}

public function activeSalaries(): HasMany
{
    return $this->hasMany(EmployeeSalary::class)
        ->whereNull('effective_to')
        ->orWhere('effective_to', '>=', now());
}

public function salaryRevisions(): HasMany
{
    return $this->hasMany(SalaryRevision::class);
}

public function taxProfile(): HasOne
{
    return $this->hasOne(EmployeeTaxProfile::class);
}

public function payslips(): HasMany
{
    return $this->hasMany(Payslip::class);
}
```

Also add `use Illuminate\Database\Eloquent\Relations\HasOne;` to the imports if not present.

**Step 6: Commit**

```bash
git add app/Models/SalaryComponent.php app/Models/EmployeeSalary.php app/Models/SalaryRevision.php app/Models/Employee.php database/factories/
git commit -m "feat(hr): add SalaryComponent, EmployeeSalary, SalaryRevision models"
```

---

## Task 5: Payroll Models — Statutory & Tax (StatutoryRate, PcbRate, EmployeeTaxProfile)

**Files:**
- Create: `app/Models/StatutoryRate.php`
- Create: `app/Models/PcbRate.php`
- Create: `app/Models/EmployeeTaxProfile.php`

**Step 1: Create models**

```bash
php artisan make:model StatutoryRate --factory --no-interaction
php artisan make:model PcbRate --factory --no-interaction
php artisan make:model EmployeeTaxProfile --factory --no-interaction
```

**Step 2: Implement StatutoryRate model**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StatutoryRate extends Model
{
    use HasFactory;

    protected $fillable = [
        'type', 'min_salary', 'max_salary', 'rate_percentage', 'fixed_amount',
        'effective_from', 'effective_to',
    ];

    protected function casts(): array
    {
        return [
            'min_salary' => 'decimal:2',
            'max_salary' => 'decimal:2',
            'rate_percentage' => 'decimal:2',
            'fixed_amount' => 'decimal:2',
            'effective_from' => 'date',
            'effective_to' => 'date',
        ];
    }

    public function scopeForType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeCurrent($query)
    {
        return $query->where('effective_from', '<=', now())
            ->where(function ($q) {
                $q->whereNull('effective_to')
                    ->orWhere('effective_to', '>=', now());
            });
    }
}
```

**Step 3: Implement PcbRate model**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PcbRate extends Model
{
    use HasFactory;

    protected $fillable = [
        'category', 'num_children', 'min_monthly_income', 'max_monthly_income',
        'pcb_amount', 'year',
    ];

    protected function casts(): array
    {
        return [
            'min_monthly_income' => 'decimal:2',
            'max_monthly_income' => 'decimal:2',
            'pcb_amount' => 'decimal:2',
        ];
    }

    public function scopeForYear($query, int $year)
    {
        return $query->where('year', $year);
    }

    public function scopeForCategory($query, string $category)
    {
        return $query->where('category', $category);
    }
}
```

**Step 4: Implement EmployeeTaxProfile model**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeTaxProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id', 'tax_number', 'marital_status', 'num_children',
        'num_children_studying', 'disabled_individual', 'disabled_spouse',
        'is_pcb_manual', 'manual_pcb_amount',
    ];

    protected function casts(): array
    {
        return [
            'disabled_individual' => 'boolean',
            'disabled_spouse' => 'boolean',
            'is_pcb_manual' => 'boolean',
            'manual_pcb_amount' => 'decimal:2',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
```

**Step 5: Commit**

```bash
git add app/Models/StatutoryRate.php app/Models/PcbRate.php app/Models/EmployeeTaxProfile.php database/factories/
git commit -m "feat(hr): add StatutoryRate, PcbRate, EmployeeTaxProfile models"
```

---

## Task 6: Payroll Models — Run & Output (PayrollRun, PayrollItem, Payslip, PayrollSetting)

**Files:**
- Create: `app/Models/PayrollRun.php`
- Create: `app/Models/PayrollItem.php`
- Create: `app/Models/Payslip.php`
- Create: `app/Models/PayrollSetting.php`

**Step 1: Create models**

```bash
php artisan make:model PayrollRun --factory --no-interaction
php artisan make:model PayrollItem --factory --no-interaction
php artisan make:model Payslip --factory --no-interaction
php artisan make:model PayrollSetting --no-interaction
```

**Step 2: Implement PayrollRun model**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayrollRun extends Model
{
    use HasFactory;

    protected $fillable = [
        'month', 'year', 'status', 'total_gross', 'total_deductions',
        'total_net', 'total_employer_cost', 'employee_count',
        'prepared_by', 'reviewed_by', 'approved_by', 'approved_at',
        'finalized_at', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'total_gross' => 'decimal:2',
            'total_deductions' => 'decimal:2',
            'total_net' => 'decimal:2',
            'total_employer_cost' => 'decimal:2',
            'approved_at' => 'datetime',
            'finalized_at' => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(PayrollItem::class);
    }

    public function payslips(): HasMany
    {
        return $this->hasMany(Payslip::class);
    }

    public function preparedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prepared_by');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function scopeForYear($query, int $year)
    {
        return $query->where('year', $year);
    }

    public function getMonthNameAttribute(): string
    {
        return date('F', mktime(0, 0, 0, $this->month, 1));
    }
}
```

**Step 3: Implement PayrollItem model**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'payroll_run_id', 'employee_id', 'salary_component_id',
        'component_code', 'component_name', 'type', 'amount', 'is_statutory',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'is_statutory' => 'boolean',
        ];
    }

    public function payrollRun(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function salaryComponent(): BelongsTo
    {
        return $this->belongsTo(SalaryComponent::class);
    }
}
```

**Step 4: Implement Payslip model**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payslip extends Model
{
    use HasFactory;

    protected $fillable = [
        'payroll_run_id', 'employee_id', 'month', 'year',
        'gross_salary', 'total_deductions', 'net_salary',
        'epf_employee', 'epf_employer', 'socso_employee', 'socso_employer',
        'eis_employee', 'eis_employer', 'pcb_amount',
        'unpaid_leave_days', 'unpaid_leave_deduction', 'pdf_path',
    ];

    protected function casts(): array
    {
        return [
            'gross_salary' => 'decimal:2',
            'total_deductions' => 'decimal:2',
            'net_salary' => 'decimal:2',
            'epf_employee' => 'decimal:2',
            'epf_employer' => 'decimal:2',
            'socso_employee' => 'decimal:2',
            'socso_employer' => 'decimal:2',
            'eis_employee' => 'decimal:2',
            'eis_employer' => 'decimal:2',
            'pcb_amount' => 'decimal:2',
            'unpaid_leave_deduction' => 'decimal:2',
        ];
    }

    public function payrollRun(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function scopeForEmployee($query, int $employeeId)
    {
        return $query->where('employee_id', $employeeId);
    }

    public function scopeForYear($query, int $year)
    {
        return $query->where('year', $year);
    }
}
```

**Step 5: Implement PayrollSetting model**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PayrollSetting extends Model
{
    protected $fillable = ['key', 'value', 'description'];

    public static function getValue(string $key, string $default = ''): string
    {
        return static::where('key', $key)->value('value') ?? $default;
    }

    public static function setValue(string $key, string $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
    }
}
```

**Step 6: Commit**

```bash
git add app/Models/PayrollRun.php app/Models/PayrollItem.php app/Models/Payslip.php app/Models/PayrollSetting.php database/factories/
git commit -m "feat(hr): add PayrollRun, PayrollItem, Payslip, PayrollSetting models"
```

---

## Task 7: Payroll Settings & Salary Components Seeder

**Files:**
- Create: `database/seeders/HrPayrollSeeder.php`

**Step 1: Create seeder**

```bash
php artisan make:seeder HrPayrollSeeder --no-interaction
```

**Step 2: Implement seeder**

```php
<?php

namespace Database\Seeders;

use App\Models\PayrollSetting;
use App\Models\SalaryComponent;
use Illuminate\Database\Seeder;

class HrPayrollSeeder extends Seeder
{
    public function run(): void
    {
        // Default salary components
        $components = [
            ['name' => 'Basic Salary', 'code' => 'BASIC', 'type' => 'earning', 'category' => 'basic', 'is_taxable' => true, 'is_epf_applicable' => true, 'is_socso_applicable' => true, 'is_eis_applicable' => true, 'is_system' => true, 'sort_order' => 1],
            ['name' => 'EPF (Employee)', 'code' => 'EPF_EE', 'type' => 'deduction', 'category' => 'fixed_deduction', 'is_taxable' => false, 'is_epf_applicable' => false, 'is_socso_applicable' => false, 'is_eis_applicable' => false, 'is_system' => true, 'sort_order' => 10],
            ['name' => 'EPF (Employer)', 'code' => 'EPF_ER', 'type' => 'deduction', 'category' => 'fixed_deduction', 'is_taxable' => false, 'is_epf_applicable' => false, 'is_socso_applicable' => false, 'is_eis_applicable' => false, 'is_system' => true, 'sort_order' => 11],
            ['name' => 'SOCSO (Employee)', 'code' => 'SOCSO_EE', 'type' => 'deduction', 'category' => 'fixed_deduction', 'is_taxable' => false, 'is_epf_applicable' => false, 'is_socso_applicable' => false, 'is_eis_applicable' => false, 'is_system' => true, 'sort_order' => 12],
            ['name' => 'SOCSO (Employer)', 'code' => 'SOCSO_ER', 'type' => 'deduction', 'category' => 'fixed_deduction', 'is_taxable' => false, 'is_epf_applicable' => false, 'is_socso_applicable' => false, 'is_eis_applicable' => false, 'is_system' => true, 'sort_order' => 13],
            ['name' => 'EIS (Employee)', 'code' => 'EIS_EE', 'type' => 'deduction', 'category' => 'fixed_deduction', 'is_taxable' => false, 'is_epf_applicable' => false, 'is_socso_applicable' => false, 'is_eis_applicable' => false, 'is_system' => true, 'sort_order' => 14],
            ['name' => 'EIS (Employer)', 'code' => 'EIS_ER', 'type' => 'deduction', 'category' => 'fixed_deduction', 'is_taxable' => false, 'is_epf_applicable' => false, 'is_socso_applicable' => false, 'is_eis_applicable' => false, 'is_system' => true, 'sort_order' => 15],
            ['name' => 'PCB / MTD', 'code' => 'PCB', 'type' => 'deduction', 'category' => 'fixed_deduction', 'is_taxable' => false, 'is_epf_applicable' => false, 'is_socso_applicable' => false, 'is_eis_applicable' => false, 'is_system' => true, 'sort_order' => 16],
        ];

        foreach ($components as $component) {
            SalaryComponent::firstOrCreate(
                ['code' => $component['code']],
                array_merge($component, ['is_active' => true])
            );
        }

        // Default payroll settings
        $settings = [
            ['key' => 'unpaid_leave_divisor', 'value' => '26', 'description' => 'Days divisor for unpaid leave daily rate (26 or 30)'],
            ['key' => 'pay_day', 'value' => '25', 'description' => 'Salary payment day of month'],
            ['key' => 'epf_employee_default_rate', 'value' => '11', 'description' => 'Default EPF employee percentage'],
            ['key' => 'company_name', 'value' => 'Mudeer Bedaie Sdn Bhd', 'description' => 'Company name for payslip header'],
            ['key' => 'company_address', 'value' => '', 'description' => 'Company address for payslip header'],
            ['key' => 'company_epf_number', 'value' => '', 'description' => 'Company EPF registration number'],
            ['key' => 'company_socso_number', 'value' => '', 'description' => 'Company SOCSO registration number'],
            ['key' => 'company_eis_number', 'value' => '', 'description' => 'Company EIS registration number'],
        ];

        foreach ($settings as $setting) {
            PayrollSetting::firstOrCreate(
                ['key' => $setting['key']],
                $setting
            );
        }
    }
}
```

**Step 3: Run seeder**

```bash
php artisan db:seed --class=HrPayrollSeeder
```

**Step 4: Commit**

```bash
git add database/seeders/HrPayrollSeeder.php
git commit -m "feat(hr): add payroll default components and settings seeder"
```

---

## Task 8: Statutory Calculation Service — EPF, SOCSO, EIS

**Files:**
- Create: `app/Services/Hr/StatutoryCalculationService.php`

**Step 1: Create service directory and file**

```bash
mkdir -p app/Services/Hr
```

**Step 2: Implement StatutoryCalculationService**

```php
<?php

namespace App\Services\Hr;

use App\Models\EmployeeTaxProfile;
use App\Models\PcbRate;
use App\Models\StatutoryRate;

class StatutoryCalculationService
{
    /**
     * Calculate EPF employee contribution.
     * Default: 11% of wages, rounded to nearest RM.
     */
    public function calculateEpfEmployee(float $wages, int $employeeAge = 30): float
    {
        if ($employeeAge >= 60) {
            return 0.00;
        }

        // Check statutory_rates table for custom rate
        $rate = StatutoryRate::forType('epf_employee')
            ->current()
            ->where('min_salary', '<=', $wages)
            ->where(function ($q) use ($wages) {
                $q->whereNull('max_salary')
                    ->orWhere('max_salary', '>=', $wages);
            })
            ->first();

        $percentage = $rate ? $rate->rate_percentage : 11.00;
        $maxWage = 20000.00; // EPF max contribution wage

        $applicableWage = min($wages, $maxWage);
        $amount = $applicableWage * ($percentage / 100);

        return $this->roundToNearestRinggit($amount);
    }

    /**
     * Calculate EPF employer contribution.
     * ≤ RM5,000: 13%, > RM5,000: 12%, Age 60+: 4%.
     */
    public function calculateEpfEmployer(float $wages, int $employeeAge = 30): float
    {
        $maxWage = 20000.00;
        $applicableWage = min($wages, $maxWage);

        if ($employeeAge >= 60) {
            $percentage = 4.00;
        } elseif ($applicableWage <= 5000) {
            $percentage = 13.00;
        } else {
            $percentage = 12.00;
        }

        // Check statutory_rates table for custom rate
        $rate = StatutoryRate::forType('epf_employer')
            ->current()
            ->where('min_salary', '<=', $wages)
            ->where(function ($q) use ($wages) {
                $q->whereNull('max_salary')
                    ->orWhere('max_salary', '>=', $wages);
            })
            ->first();

        if ($rate) {
            $percentage = $rate->rate_percentage;
        }

        $amount = $applicableWage * ($percentage / 100);

        return $this->roundToNearestRinggit($amount);
    }

    /**
     * Calculate SOCSO employee contribution (fixed amount from bracket table).
     */
    public function calculateSocsoEmployee(float $wages, int $employeeAge = 30): float
    {
        $type = $employeeAge >= 60 ? 'socso_employee' : 'socso_employee';

        $rate = StatutoryRate::forType($type)
            ->current()
            ->where('min_salary', '<=', $wages)
            ->where(function ($q) use ($wages) {
                $q->whereNull('max_salary')
                    ->orWhere('max_salary', '>=', $wages);
            })
            ->first();

        return $rate ? (float) $rate->fixed_amount : 0.00;
    }

    /**
     * Calculate SOCSO employer contribution (fixed amount from bracket table).
     */
    public function calculateSocsoEmployer(float $wages, int $employeeAge = 30): float
    {
        $type = $employeeAge >= 60 ? 'socso_employer' : 'socso_employer';

        $rate = StatutoryRate::forType($type)
            ->current()
            ->where('min_salary', '<=', $wages)
            ->where(function ($q) use ($wages) {
                $q->whereNull('max_salary')
                    ->orWhere('max_salary', '>=', $wages);
            })
            ->first();

        return $rate ? (float) $rate->fixed_amount : 0.00;
    }

    /**
     * Calculate EIS employee contribution.
     * 0.2% of salary, max salary RM6,000, max contribution RM12.
     */
    public function calculateEisEmployee(float $wages): float
    {
        $maxWage = 6000.00;
        $applicableWage = min($wages, $maxWage);
        $amount = $applicableWage * 0.002;

        return round(min($amount, 12.00), 2);
    }

    /**
     * Calculate EIS employer contribution (same as employee).
     */
    public function calculateEisEmployer(float $wages): float
    {
        return $this->calculateEisEmployee($wages);
    }

    /**
     * Calculate PCB (Monthly Tax Deduction).
     */
    public function calculatePcb(
        float $grossRemuneration,
        float $epfEmployee,
        EmployeeTaxProfile $taxProfile,
        int $year
    ): float {
        // Manual override
        if ($taxProfile->is_pcb_manual && $taxProfile->manual_pcb_amount !== null) {
            return (float) $taxProfile->manual_pcb_amount;
        }

        // Taxable income = gross - EPF
        $taxableIncome = $grossRemuneration - $epfEmployee;

        if ($taxableIncome <= 0) {
            return 0.00;
        }

        // Map marital status to PCB category
        $category = $taxProfile->marital_status;

        // Lookup PCB table
        $pcbRate = PcbRate::forYear($year)
            ->forCategory($category)
            ->where('num_children', $taxProfile->num_children)
            ->where('min_monthly_income', '<=', $taxableIncome)
            ->where(function ($q) use ($taxableIncome) {
                $q->whereNull('max_monthly_income')
                    ->orWhere('max_monthly_income', '>=', $taxableIncome);
            })
            ->first();

        $pcbAmount = $pcbRate ? (float) $pcbRate->pcb_amount : 0.00;

        // Additional relief deductions
        if ($taxProfile->disabled_individual) {
            $pcbAmount -= 100.00;
        }
        if ($taxProfile->disabled_spouse) {
            $pcbAmount -= 29.17;
        }
        if ($taxProfile->num_children_studying > 0) {
            $pcbAmount -= ($taxProfile->num_children_studying * 66.67);
        }

        return max(0, round($pcbAmount, 2));
    }

    /**
     * Calculate all statutory deductions for an employee.
     *
     * @return array{epf_ee: float, epf_er: float, socso_ee: float, socso_er: float, eis_ee: float, eis_er: float, pcb: float}
     */
    public function calculateAll(
        float $epfApplicableWages,
        float $socsoApplicableWages,
        float $eisApplicableWages,
        float $grossRemuneration,
        EmployeeTaxProfile $taxProfile,
        int $employeeAge,
        int $year
    ): array {
        $epfEe = $this->calculateEpfEmployee($epfApplicableWages, $employeeAge);
        $epfEr = $this->calculateEpfEmployer($epfApplicableWages, $employeeAge);
        $socsoEe = $this->calculateSocsoEmployee($socsoApplicableWages, $employeeAge);
        $socsoEr = $this->calculateSocsoEmployer($socsoApplicableWages, $employeeAge);
        $eisEe = $this->calculateEisEmployee($eisApplicableWages);
        $eisEr = $this->calculateEisEmployer($eisApplicableWages);
        $pcb = $this->calculatePcb($grossRemuneration, $epfEe, $taxProfile, $year);

        return [
            'epf_ee' => $epfEe,
            'epf_er' => $epfEr,
            'socso_ee' => $socsoEe,
            'socso_er' => $socsoEr,
            'eis_ee' => $eisEe,
            'eis_er' => $eisEr,
            'pcb' => $pcb,
        ];
    }

    /**
     * Round to nearest ringgit (Malaysian EPF rounding rule).
     * 50 sen or more rounds up.
     */
    private function roundToNearestRinggit(float $amount): float
    {
        return round($amount, 0);
    }
}
```

**Step 3: Commit**

```bash
git add app/Services/Hr/StatutoryCalculationService.php
git commit -m "feat(hr): add StatutoryCalculationService for EPF/SOCSO/EIS/PCB calculations"
```

---

## Task 9: Payroll Processing Service

**Files:**
- Create: `app/Services/Hr/PayrollProcessingService.php`

**Step 1: Implement PayrollProcessingService**

```php
<?php

namespace App\Services\Hr;

use App\Models\Employee;
use App\Models\EmployeeSalary;
use App\Models\EmployeeTaxProfile;
use App\Models\LeaveRequest;
use App\Models\PayrollItem;
use App\Models\PayrollRun;
use App\Models\PayrollSetting;
use App\Models\Payslip;
use App\Models\SalaryComponent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class PayrollProcessingService
{
    public function __construct(
        private StatutoryCalculationService $statutory
    ) {}

    /**
     * Calculate payroll for all active employees in a run.
     */
    public function calculateAll(PayrollRun $payrollRun): void
    {
        $employees = Employee::where('status', 'active')
            ->with(['activeSalaries.salaryComponent', 'taxProfile'])
            ->get();

        DB::transaction(function () use ($payrollRun, $employees) {
            // Clear existing items for this run
            $payrollRun->items()->delete();

            $totalGross = 0;
            $totalDeductions = 0;
            $totalNet = 0;
            $totalEmployerCost = 0;

            foreach ($employees as $employee) {
                $result = $this->calculateForEmployee($payrollRun, $employee);
                $totalGross += $result['gross'];
                $totalDeductions += $result['total_deductions'];
                $totalNet += $result['net'];
                $totalEmployerCost += $result['employer_cost'];
            }

            $payrollRun->update([
                'total_gross' => $totalGross,
                'total_deductions' => $totalDeductions,
                'total_net' => $totalNet,
                'total_employer_cost' => $totalEmployerCost,
                'employee_count' => $employees->count(),
            ]);
        });
    }

    /**
     * Calculate payroll for a single employee.
     *
     * @return array{gross: float, total_deductions: float, net: float, employer_cost: float}
     */
    public function calculateForEmployee(PayrollRun $payrollRun, Employee $employee): array
    {
        // Remove existing items for this employee in this run
        PayrollItem::where('payroll_run_id', $payrollRun->id)
            ->where('employee_id', $employee->id)
            ->where('is_statutory', true)
            ->delete();

        // 1. Get salary components
        $salaries = EmployeeSalary::forEmployee($employee->id)
            ->active()
            ->with('salaryComponent')
            ->get();

        $totalEarnings = 0;
        $epfApplicable = 0;
        $socsoApplicable = 0;
        $eisApplicable = 0;
        $basicSalary = 0;

        foreach ($salaries as $salary) {
            $component = $salary->salaryComponent;
            if ($component->type === 'earning') {
                // Create earning item (if not already exists as ad-hoc)
                $existing = PayrollItem::where('payroll_run_id', $payrollRun->id)
                    ->where('employee_id', $employee->id)
                    ->where('component_code', $component->code)
                    ->where('is_statutory', false)
                    ->first();

                if (! $existing) {
                    PayrollItem::create([
                        'payroll_run_id' => $payrollRun->id,
                        'employee_id' => $employee->id,
                        'salary_component_id' => $component->id,
                        'component_code' => $component->code,
                        'component_name' => $component->name,
                        'type' => 'earning',
                        'amount' => $salary->amount,
                        'is_statutory' => false,
                    ]);
                }

                $amount = $existing ? $existing->amount : $salary->amount;
                $totalEarnings += $amount;

                if ($component->code === 'BASIC') {
                    $basicSalary = $amount;
                }
                if ($component->is_epf_applicable) {
                    $epfApplicable += $amount;
                }
                if ($component->is_socso_applicable) {
                    $socsoApplicable += $amount;
                }
                if ($component->is_eis_applicable) {
                    $eisApplicable += $amount;
                }
            }
        }

        // 2. Calculate unpaid leave deduction
        $unpaidDays = $this->getUnpaidLeaveDays($employee->id, $payrollRun->month, $payrollRun->year);
        $divisor = (int) PayrollSetting::getValue('unpaid_leave_divisor', '26');
        $unpaidDeduction = $divisor > 0 ? ($basicSalary / $divisor) * $unpaidDays : 0;
        $unpaidDeduction = round($unpaidDeduction, 2);

        if ($unpaidDeduction > 0) {
            PayrollItem::create([
                'payroll_run_id' => $payrollRun->id,
                'employee_id' => $employee->id,
                'component_code' => 'UNPAID_LEAVE',
                'component_name' => "Unpaid Leave ({$unpaidDays} days)",
                'type' => 'deduction',
                'amount' => $unpaidDeduction,
                'is_statutory' => true,
            ]);
        }

        // 3. Gross = total earnings - unpaid leave deduction
        $gross = $totalEarnings - $unpaidDeduction;

        // 4. Calculate statutory deductions
        $taxProfile = $employee->taxProfile ?? new EmployeeTaxProfile([
            'marital_status' => 'single',
            'num_children' => 0,
            'num_children_studying' => 0,
            'disabled_individual' => false,
            'disabled_spouse' => false,
            'is_pcb_manual' => false,
        ]);

        $age = $employee->date_of_birth ? Carbon::parse($employee->date_of_birth)->age : 30;

        $statutory = $this->statutory->calculateAll(
            $epfApplicable - $unpaidDeduction,
            $socsoApplicable,
            $eisApplicable,
            $gross,
            $taxProfile,
            $age,
            $payrollRun->year
        );

        // Create statutory deduction items
        $statutoryItems = [
            ['code' => 'EPF_EE', 'name' => 'EPF (Employee)', 'type' => 'deduction', 'amount' => $statutory['epf_ee']],
            ['code' => 'EPF_ER', 'name' => 'EPF (Employer)', 'type' => 'employer_contribution', 'amount' => $statutory['epf_er']],
            ['code' => 'SOCSO_EE', 'name' => 'SOCSO (Employee)', 'type' => 'deduction', 'amount' => $statutory['socso_ee']],
            ['code' => 'SOCSO_ER', 'name' => 'SOCSO (Employer)', 'type' => 'employer_contribution', 'amount' => $statutory['socso_er']],
            ['code' => 'EIS_EE', 'name' => 'EIS (Employee)', 'type' => 'deduction', 'amount' => $statutory['eis_ee']],
            ['code' => 'EIS_ER', 'name' => 'EIS (Employer)', 'type' => 'employer_contribution', 'amount' => $statutory['eis_er']],
            ['code' => 'PCB', 'name' => 'PCB / MTD', 'type' => 'deduction', 'amount' => $statutory['pcb']],
        ];

        foreach ($statutoryItems as $item) {
            if ($item['amount'] > 0) {
                PayrollItem::create([
                    'payroll_run_id' => $payrollRun->id,
                    'employee_id' => $employee->id,
                    'component_code' => $item['code'],
                    'component_name' => $item['name'],
                    'type' => $item['type'],
                    'amount' => $item['amount'],
                    'is_statutory' => true,
                ]);
            }
        }

        // 5. Total deductions (employee portion only)
        $totalDeductions = $statutory['epf_ee'] + $statutory['socso_ee'] + $statutory['eis_ee'] + $statutory['pcb'] + $unpaidDeduction;

        // Add any non-statutory deduction items
        $adHocDeductions = PayrollItem::where('payroll_run_id', $payrollRun->id)
            ->where('employee_id', $employee->id)
            ->where('type', 'deduction')
            ->where('is_statutory', false)
            ->sum('amount');
        $totalDeductions += $adHocDeductions;

        // 6. Net = Gross - Total deductions
        $net = $gross - $totalDeductions;

        // 7. Employer cost = Gross + employer contributions
        $employerCost = $gross + $statutory['epf_er'] + $statutory['socso_er'] + $statutory['eis_er'];

        return [
            'gross' => $gross,
            'total_deductions' => $totalDeductions,
            'net' => $net,
            'employer_cost' => $employerCost,
        ];
    }

    /**
     * Generate payslip records for a finalized payroll run.
     */
    public function generatePayslips(PayrollRun $payrollRun): void
    {
        $employeeIds = $payrollRun->items()
            ->select('employee_id')
            ->distinct()
            ->pluck('employee_id');

        foreach ($employeeIds as $employeeId) {
            $items = $payrollRun->items()
                ->where('employee_id', $employeeId)
                ->get();

            $earnings = $items->where('type', 'earning')->sum('amount');
            $deductions = $items->where('type', 'deduction')->sum('amount');

            Payslip::updateOrCreate(
                [
                    'employee_id' => $employeeId,
                    'month' => $payrollRun->month,
                    'year' => $payrollRun->year,
                ],
                [
                    'payroll_run_id' => $payrollRun->id,
                    'gross_salary' => $earnings,
                    'total_deductions' => $deductions,
                    'net_salary' => $earnings - $deductions,
                    'epf_employee' => $items->firstWhere('component_code', 'EPF_EE')?->amount ?? 0,
                    'epf_employer' => $items->firstWhere('component_code', 'EPF_ER')?->amount ?? 0,
                    'socso_employee' => $items->firstWhere('component_code', 'SOCSO_EE')?->amount ?? 0,
                    'socso_employer' => $items->firstWhere('component_code', 'SOCSO_ER')?->amount ?? 0,
                    'eis_employee' => $items->firstWhere('component_code', 'EIS_EE')?->amount ?? 0,
                    'eis_employer' => $items->firstWhere('component_code', 'EIS_ER')?->amount ?? 0,
                    'pcb_amount' => $items->firstWhere('component_code', 'PCB')?->amount ?? 0,
                    'unpaid_leave_days' => 0, // TODO: pull from items
                    'unpaid_leave_deduction' => $items->firstWhere('component_code', 'UNPAID_LEAVE')?->amount ?? 0,
                ]
            );
        }
    }

    /**
     * Get unpaid leave days for an employee in a given month.
     */
    private function getUnpaidLeaveDays(int $employeeId, int $month, int $year): float
    {
        $startOfMonth = Carbon::create($year, $month, 1)->startOfMonth();
        $endOfMonth = $startOfMonth->copy()->endOfMonth();

        return LeaveRequest::where('employee_id', $employeeId)
            ->where('status', 'approved')
            ->whereHas('leaveType', function ($q) {
                $q->where('is_paid', false);
            })
            ->where(function ($q) use ($startOfMonth, $endOfMonth) {
                $q->whereBetween('start_date', [$startOfMonth, $endOfMonth])
                    ->orWhereBetween('end_date', [$startOfMonth, $endOfMonth]);
            })
            ->sum('total_days');
    }
}
```

**Step 2: Commit**

```bash
git add app/Services/Hr/PayrollProcessingService.php
git commit -m "feat(hr): add PayrollProcessingService for payroll calculation and payslip generation"
```

---

## Task 10: Payroll Run Controller

**Files:**
- Create: `app/Http/Controllers/Api/Hr/HrPayrollRunController.php`

**Step 1: Create controller**

```bash
php artisan make:controller Api/Hr/HrPayrollRunController --no-interaction
```

**Step 2: Implement controller**

```php
<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use App\Models\PayrollRun;
use App\Services\Hr\PayrollProcessingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HrPayrollRunController extends Controller
{
    public function __construct(
        private PayrollProcessingService $payrollService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = PayrollRun::query()
            ->with(['preparedBy:id,name', 'approvedBy:id,name']);

        if ($year = $request->get('year')) {
            $query->forYear($year);
        }

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        $runs = $query->orderByDesc('year')->orderByDesc('month')
            ->paginate($request->get('per_page', 15));

        return response()->json($runs);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'month' => ['required', 'integer', 'min:1', 'max:12'],
            'year' => ['required', 'integer', 'min:2020'],
            'notes' => ['nullable', 'string'],
        ]);

        // Check if run already exists for this month/year
        $exists = PayrollRun::where('month', $validated['month'])
            ->where('year', $validated['year'])
            ->exists();

        if ($exists) {
            return response()->json([
                'message' => 'Payroll run already exists for this month/year.',
            ], 422);
        }

        $run = PayrollRun::create(array_merge($validated, [
            'status' => 'draft',
            'prepared_by' => $request->user()->id,
        ]));

        return response()->json([
            'data' => $run->load('preparedBy:id,name'),
            'message' => 'Payroll run created successfully.',
        ], 201);
    }

    public function show(PayrollRun $payrollRun): JsonResponse
    {
        $payrollRun->load([
            'preparedBy:id,name',
            'reviewedBy:id,name',
            'approvedBy:id,name',
            'items' => function ($query) {
                $query->with('employee:id,employee_id,full_name,department_id')
                    ->orderBy('employee_id')
                    ->orderBy('type');
            },
        ]);

        return response()->json(['data' => $payrollRun]);
    }

    public function destroy(PayrollRun $payrollRun): JsonResponse
    {
        if ($payrollRun->status !== 'draft') {
            return response()->json([
                'message' => 'Only draft payroll runs can be deleted.',
            ], 422);
        }

        $payrollRun->delete();

        return response()->json(['message' => 'Payroll run deleted successfully.']);
    }

    public function calculate(PayrollRun $payrollRun): JsonResponse
    {
        if ($payrollRun->status !== 'draft') {
            return response()->json([
                'message' => 'Can only calculate draft payroll runs.',
            ], 422);
        }

        $this->payrollService->calculateAll($payrollRun);
        $payrollRun->refresh()->load('items.employee:id,employee_id,full_name');

        return response()->json([
            'data' => $payrollRun,
            'message' => 'Payroll calculated successfully.',
        ]);
    }

    public function calculateEmployee(PayrollRun $payrollRun, int $employeeId): JsonResponse
    {
        if (! in_array($payrollRun->status, ['draft', 'review'])) {
            return response()->json([
                'message' => 'Cannot recalculate in current status.',
            ], 422);
        }

        $employee = \App\Models\Employee::with(['activeSalaries.salaryComponent', 'taxProfile'])
            ->findOrFail($employeeId);

        $this->payrollService->calculateForEmployee($payrollRun, $employee);

        return response()->json(['message' => 'Employee payroll recalculated.']);
    }

    public function submitReview(PayrollRun $payrollRun, Request $request): JsonResponse
    {
        if ($payrollRun->status !== 'draft') {
            return response()->json(['message' => 'Can only submit draft runs for review.'], 422);
        }

        if ($payrollRun->employee_count === 0) {
            return response()->json(['message' => 'Calculate payroll before submitting for review.'], 422);
        }

        $payrollRun->update([
            'status' => 'review',
            'reviewed_by' => $request->user()->id,
        ]);

        return response()->json([
            'data' => $payrollRun,
            'message' => 'Payroll submitted for review.',
        ]);
    }

    public function approve(PayrollRun $payrollRun, Request $request): JsonResponse
    {
        if ($payrollRun->status !== 'review') {
            return response()->json(['message' => 'Can only approve runs in review status.'], 422);
        }

        $payrollRun->update([
            'status' => 'approved',
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
        ]);

        return response()->json([
            'data' => $payrollRun,
            'message' => 'Payroll approved.',
        ]);
    }

    public function returnToDraft(PayrollRun $payrollRun): JsonResponse
    {
        if ($payrollRun->status !== 'review') {
            return response()->json(['message' => 'Can only return runs in review status.'], 422);
        }

        $payrollRun->update([
            'status' => 'draft',
            'reviewed_by' => null,
        ]);

        return response()->json([
            'data' => $payrollRun,
            'message' => 'Payroll returned to draft.',
        ]);
    }

    public function finalize(PayrollRun $payrollRun): JsonResponse
    {
        if ($payrollRun->status !== 'approved') {
            return response()->json(['message' => 'Can only finalize approved runs.'], 422);
        }

        DB::transaction(function () use ($payrollRun) {
            $this->payrollService->generatePayslips($payrollRun);

            $payrollRun->update([
                'status' => 'finalized',
                'finalized_at' => now(),
            ]);
        });

        return response()->json([
            'data' => $payrollRun,
            'message' => 'Payroll finalized. Payslips generated.',
        ]);
    }
}
```

**Step 3: Commit**

```bash
git add app/Http/Controllers/Api/Hr/HrPayrollRunController.php
git commit -m "feat(hr): add HrPayrollRunController with full workflow actions"
```

---

## Task 11: Payroll Component & Salary Controllers

**Files:**
- Create: `app/Http/Controllers/Api/Hr/HrSalaryComponentController.php`
- Create: `app/Http/Controllers/Api/Hr/HrEmployeeSalaryController.php`
- Create: `app/Http/Controllers/Api/Hr/HrPayrollItemController.php`

**Step 1: Create controllers**

```bash
php artisan make:controller Api/Hr/HrSalaryComponentController --no-interaction
php artisan make:controller Api/Hr/HrEmployeeSalaryController --no-interaction
php artisan make:controller Api/Hr/HrPayrollItemController --no-interaction
```

**Step 2: Implement HrSalaryComponentController** — Standard CRUD following existing pattern (fetchAll, create, update, delete). System components cannot be deleted. Sort by `sort_order`.

**Step 3: Implement HrEmployeeSalaryController** — Index (list all with employee + component relations, filterable by employee_id). Show (single employee's salary breakdown). Store (create salary record). Update (end old record, create new, create revision). BulkRevision (increase/decrease for multiple employees). Revisions (history for one employee).

**Step 4: Implement HrPayrollItemController** — Store (add ad-hoc item to a payroll run, only for draft/review). Update (modify ad-hoc item amount). Destroy (remove ad-hoc item).

**Step 5: Commit**

```bash
git add app/Http/Controllers/Api/Hr/HrSalaryComponentController.php app/Http/Controllers/Api/Hr/HrEmployeeSalaryController.php app/Http/Controllers/Api/Hr/HrPayrollItemController.php
git commit -m "feat(hr): add salary component, employee salary, payroll item controllers"
```

---

## Task 12: Tax Profile & Statutory Rate Controllers

**Files:**
- Create: `app/Http/Controllers/Api/Hr/HrTaxProfileController.php`
- Create: `app/Http/Controllers/Api/Hr/HrStatutoryRateController.php`

**Step 1: Create controllers**

```bash
php artisan make:controller Api/Hr/HrTaxProfileController --no-interaction
php artisan make:controller Api/Hr/HrStatutoryRateController --no-interaction
```

**Step 2: Implement HrTaxProfileController** — Index (list all employees with tax profiles). Show (single employee tax profile). Update (upsert tax profile for employee).

**Step 3: Implement HrStatutoryRateController** — Index (list all rates, filterable by type). Update (modify rate). BulkUpdate (replace all rates of a type).

**Step 4: Commit**

```bash
git add app/Http/Controllers/Api/Hr/HrTaxProfileController.php app/Http/Controllers/Api/Hr/HrStatutoryRateController.php
git commit -m "feat(hr): add tax profile and statutory rate controllers"
```

---

## Task 13: Payslip & Report Controllers

**Files:**
- Create: `app/Http/Controllers/Api/Hr/HrPayslipController.php`
- Create: `app/Http/Controllers/Api/Hr/HrPayrollReportController.php`
- Create: `app/Http/Controllers/Api/Hr/HrMyPayslipController.php`

**Step 1: Create controllers**

```bash
php artisan make:controller Api/Hr/HrPayslipController --no-interaction
php artisan make:controller Api/Hr/HrPayrollReportController --no-interaction
php artisan make:controller Api/Hr/HrMyPayslipController --no-interaction
```

**Step 2: Implement HrPayslipController** — Index (list payslips, filterable by month/year/employee). Show (payslip detail with items). Pdf (generate/download payslip PDF). BulkPdf (download all payslips for a run as ZIP).

**Step 3: Implement HrPayrollReportController** — MonthlySummary (by department). Statutory (EPF/SOCSO/EIS totals). BankPayment (employee list with bank details + net salary). Ytd (year-to-date per employee). EaForm (single employee EA PDF). EaForms (bulk EA Forms ZIP).

**Step 4: Implement HrMyPayslipController** — Index (authenticated employee's payslips). Show (payslip detail). Pdf (download PDF). Ytd (YTD summary).

**Step 5: Commit**

```bash
git add app/Http/Controllers/Api/Hr/HrPayslipController.php app/Http/Controllers/Api/Hr/HrPayrollReportController.php app/Http/Controllers/Api/Hr/HrMyPayslipController.php
git commit -m "feat(hr): add payslip, report, and my-payslip controllers"
```

---

## Task 14: Payroll Dashboard & Settings Controllers

**Files:**
- Create: `app/Http/Controllers/Api/Hr/HrPayrollDashboardController.php`
- Create: `app/Http/Controllers/Api/Hr/HrPayrollSettingController.php`

**Step 1: Create controllers**

```bash
php artisan make:controller Api/Hr/HrPayrollDashboardController --no-interaction
php artisan make:controller Api/Hr/HrPayrollSettingController --no-interaction
```

**Step 2: Implement HrPayrollDashboardController** — Stats (total gross/deductions/net/employer cost for latest month, employee count). Trend (monthly totals for last 12 months). StatutoryBreakdown (EPF/SOCSO/EIS/PCB pie chart data).

**Step 3: Implement HrPayrollSettingController** — Index (all settings). Update (batch update settings).

**Step 4: Commit**

```bash
git add app/Http/Controllers/Api/Hr/HrPayrollDashboardController.php app/Http/Controllers/Api/Hr/HrPayrollSettingController.php
git commit -m "feat(hr): add payroll dashboard and settings controllers"
```

---

## Task 15: Payroll Form Requests

**Files:**
- Create: `app/Http/Requests/Hr/StorePayrollRunRequest.php`
- Create: `app/Http/Requests/Hr/StoreSalaryComponentRequest.php`
- Create: `app/Http/Requests/Hr/StoreEmployeeSalaryRequest.php`
- Create: `app/Http/Requests/Hr/UpdateTaxProfileRequest.php`
- Create: `app/Http/Requests/Hr/StorePayrollItemRequest.php`

**Step 1: Create form requests**

```bash
php artisan make:request Hr/StorePayrollRunRequest --no-interaction
php artisan make:request Hr/StoreSalaryComponentRequest --no-interaction
php artisan make:request Hr/StoreEmployeeSalaryRequest --no-interaction
php artisan make:request Hr/UpdateTaxProfileRequest --no-interaction
php artisan make:request Hr/StorePayrollItemRequest --no-interaction
```

**Step 2: Implement validation rules** — Follow existing pattern: `authorize()` with `$this->user() && $this->user()->isAdmin()`, array-format rules, custom messages.

Key validation rules:
- **StorePayrollRunRequest:** `month` (required, integer, 1-12), `year` (required, integer, min:2020), unique:payroll_runs,null,id,month,{month},year,{year}
- **StoreSalaryComponentRequest:** `name` (required, string, max:255), `code` (required, string, max:20, unique:salary_components), `type` (required, in:earning,deduction), `category` (required, in:basic,fixed_allowance,...)
- **StoreEmployeeSalaryRequest:** `employee_id` (required, exists:employees,id), `salary_component_id` (required, exists:salary_components,id), `amount` (required, numeric, min:0), `effective_from` (required, date)
- **UpdateTaxProfileRequest:** `marital_status` (required, in:single,...), `num_children` (required, integer, min:0, max:20), `num_children_studying` (required, integer, lte:num_children)
- **StorePayrollItemRequest:** `employee_id` (required, exists:employees,id), `component_name` (required, string), `type` (required, in:earning,deduction), `amount` (required, numeric, min:0)

**Step 3: Commit**

```bash
git add app/Http/Requests/Hr/
git commit -m "feat(hr): add payroll form request validations"
```

---

## Task 16: Payroll API Routes

**Files:**
- Modify: `routes/api.php`

**Step 1: Add payroll routes** inside the existing HR route group:

```php
// Payroll Dashboard
Route::get('payroll/dashboard/stats', [HrPayrollDashboardController::class, 'stats']);
Route::get('payroll/dashboard/trend', [HrPayrollDashboardController::class, 'trend']);
Route::get('payroll/dashboard/statutory-breakdown', [HrPayrollDashboardController::class, 'statutoryBreakdown']);

// Payroll Runs
Route::get('payroll/runs', [HrPayrollRunController::class, 'index']);
Route::post('payroll/runs', [HrPayrollRunController::class, 'store']);
Route::get('payroll/runs/{payrollRun}', [HrPayrollRunController::class, 'show']);
Route::delete('payroll/runs/{payrollRun}', [HrPayrollRunController::class, 'destroy']);
Route::post('payroll/runs/{payrollRun}/calculate', [HrPayrollRunController::class, 'calculate']);
Route::post('payroll/runs/{payrollRun}/calculate/{employeeId}', [HrPayrollRunController::class, 'calculateEmployee']);
Route::patch('payroll/runs/{payrollRun}/submit-review', [HrPayrollRunController::class, 'submitReview']);
Route::patch('payroll/runs/{payrollRun}/approve', [HrPayrollRunController::class, 'approve']);
Route::patch('payroll/runs/{payrollRun}/return-draft', [HrPayrollRunController::class, 'returnToDraft']);
Route::patch('payroll/runs/{payrollRun}/finalize', [HrPayrollRunController::class, 'finalize']);

// Payroll Items (ad-hoc)
Route::post('payroll/runs/{payrollRun}/items', [HrPayrollItemController::class, 'store']);
Route::put('payroll/runs/{payrollRun}/items/{payrollItem}', [HrPayrollItemController::class, 'update']);
Route::delete('payroll/runs/{payrollRun}/items/{payrollItem}', [HrPayrollItemController::class, 'destroy']);

// Salary Components
Route::apiResource('payroll/components', HrSalaryComponentController::class)->except('show');

// Employee Salaries
Route::get('payroll/salaries', [HrEmployeeSalaryController::class, 'index']);
Route::get('payroll/salaries/{employeeId}', [HrEmployeeSalaryController::class, 'show']);
Route::post('payroll/salaries', [HrEmployeeSalaryController::class, 'store']);
Route::put('payroll/salaries/{employeeSalary}', [HrEmployeeSalaryController::class, 'update']);
Route::get('payroll/salaries/{employeeId}/revisions', [HrEmployeeSalaryController::class, 'revisions']);
Route::post('payroll/salaries/bulk-revision', [HrEmployeeSalaryController::class, 'bulkRevision']);

// Tax Profiles
Route::get('payroll/tax-profiles', [HrTaxProfileController::class, 'index']);
Route::get('payroll/tax-profiles/{employeeId}', [HrTaxProfileController::class, 'show']);
Route::put('payroll/tax-profiles/{employeeId}', [HrTaxProfileController::class, 'update']);

// Statutory Rates
Route::get('payroll/statutory-rates', [HrStatutoryRateController::class, 'index']);
Route::put('payroll/statutory-rates/{statutoryRate}', [HrStatutoryRateController::class, 'update']);
Route::post('payroll/statutory-rates/bulk-update', [HrStatutoryRateController::class, 'bulkUpdate']);

// Payslips (Admin)
Route::get('payroll/payslips', [HrPayslipController::class, 'index']);
Route::get('payroll/payslips/{payslip}', [HrPayslipController::class, 'show']);
Route::get('payroll/payslips/{payslip}/pdf', [HrPayslipController::class, 'pdf']);
Route::get('payroll/payslips/bulk-pdf/{payrollRun}', [HrPayslipController::class, 'bulkPdf']);

// Reports
Route::get('payroll/reports/monthly-summary', [HrPayrollReportController::class, 'monthlySummary']);
Route::get('payroll/reports/statutory', [HrPayrollReportController::class, 'statutory']);
Route::get('payroll/reports/bank-payment', [HrPayrollReportController::class, 'bankPayment']);
Route::get('payroll/reports/ytd', [HrPayrollReportController::class, 'ytd']);
Route::get('payroll/reports/ea-form/{employeeId}', [HrPayrollReportController::class, 'eaForm']);
Route::get('payroll/reports/ea-forms/{year}', [HrPayrollReportController::class, 'eaForms']);

// Settings
Route::get('payroll/settings', [HrPayrollSettingController::class, 'index']);
Route::put('payroll/settings', [HrPayrollSettingController::class, 'update']);

// My Payslips (Employee Self-Service)
Route::get('me/payslips', [HrMyPayslipController::class, 'index']);
Route::get('me/payslips/ytd', [HrMyPayslipController::class, 'ytd']);
Route::get('me/payslips/{payslip}', [HrMyPayslipController::class, 'show']);
Route::get('me/payslips/{payslip}/pdf', [HrMyPayslipController::class, 'pdf']);
```

**Step 2: Add controller imports** at the top of routes/api.php.

**Step 3: Commit**

```bash
git add routes/api.php
git commit -m "feat(hr): add payroll API routes"
```

---

## Task 17: Payroll Model Factories

**Files:**
- Modify: `database/factories/SalaryComponentFactory.php`
- Modify: `database/factories/EmployeeSalaryFactory.php`
- Modify: `database/factories/PayrollRunFactory.php`
- Modify: `database/factories/PayrollItemFactory.php`
- Modify: `database/factories/PayslipFactory.php`
- Modify: `database/factories/EmployeeTaxProfileFactory.php`

**Step 1: Implement factories** following existing patterns — realistic Malaysian data, state methods (e.g., `->draft()`, `->finalized()`), counter for unique IDs.

**Step 2: Commit**

```bash
git add database/factories/
git commit -m "feat(hr): add payroll model factories"
```

---

## Task 18: Payroll Tests

**Files:**
- Create: `tests/Feature/Hr/HrPayrollApiTest.php`

**Step 1: Create test file**

```bash
php artisan make:test Hr/HrPayrollApiTest --pest --no-interaction
```

**Step 2: Implement tests** following existing HrApiTest.php pattern:

```php
<?php

declare(strict_types=1);

use App\Models\Employee;
use App\Models\EmployeeSalary;
use App\Models\EmployeeTaxProfile;
use App\Models\PayrollRun;
use App\Models\PayrollSetting;
use App\Models\SalaryComponent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createPayrollAdmin(): User
{
    return User::factory()->create(['role' => 'admin']);
}

function seedDefaultComponents(): void
{
    app(\Database\Seeders\HrPayrollSeeder::class)->run();
}

// Auth tests
test('unauthenticated users cannot access payroll endpoints', function () {
    $this->getJson('/api/hr/payroll/runs')->assertUnauthorized();
});

// Payroll Run CRUD
test('admin can create payroll run', function () {
    $admin = createPayrollAdmin();
    seedDefaultComponents();

    $response = $this->actingAs($admin)->postJson('/api/hr/payroll/runs', [
        'month' => 3,
        'year' => 2026,
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.month', 3)
        ->assertJsonPath('data.year', 2026)
        ->assertJsonPath('data.status', 'draft');
});

test('cannot create duplicate payroll run for same month/year', function () {
    $admin = createPayrollAdmin();
    PayrollRun::factory()->create(['month' => 3, 'year' => 2026, 'prepared_by' => $admin->id]);

    $this->actingAs($admin)->postJson('/api/hr/payroll/runs', [
        'month' => 3,
        'year' => 2026,
    ])->assertStatus(422);
});

// Payroll Calculation
test('can calculate payroll for employees', function () {
    $admin = createPayrollAdmin();
    seedDefaultComponents();

    $basic = SalaryComponent::where('code', 'BASIC')->first();
    $employee = Employee::factory()->create(['status' => 'active']);
    EmployeeSalary::factory()->create([
        'employee_id' => $employee->id,
        'salary_component_id' => $basic->id,
        'amount' => 5000,
        'effective_from' => now()->subYear(),
    ]);
    EmployeeTaxProfile::factory()->create(['employee_id' => $employee->id]);

    $run = PayrollRun::factory()->create([
        'month' => 3,
        'year' => 2026,
        'prepared_by' => $admin->id,
    ]);

    $response = $this->actingAs($admin)
        ->postJson("/api/hr/payroll/runs/{$run->id}/calculate");

    $response->assertSuccessful()
        ->assertJsonPath('data.employee_count', 1);
    expect($run->fresh()->total_gross)->toBeGreaterThan(0);
});

// Workflow tests
test('payroll workflow: draft -> review -> approve -> finalize', function () {
    $admin = createPayrollAdmin();
    seedDefaultComponents();

    $run = PayrollRun::factory()->create([
        'month' => 3,
        'year' => 2026,
        'status' => 'draft',
        'employee_count' => 5,
        'total_gross' => 25000,
        'prepared_by' => $admin->id,
    ]);

    // Submit for review
    $this->actingAs($admin)
        ->patchJson("/api/hr/payroll/runs/{$run->id}/submit-review")
        ->assertSuccessful();
    expect($run->fresh()->status)->toBe('review');

    // Approve
    $this->actingAs($admin)
        ->patchJson("/api/hr/payroll/runs/{$run->id}/approve")
        ->assertSuccessful();
    expect($run->fresh()->status)->toBe('approved');

    // Finalize
    $this->actingAs($admin)
        ->patchJson("/api/hr/payroll/runs/{$run->id}/finalize")
        ->assertSuccessful();
    expect($run->fresh()->status)->toBe('finalized');
});

// Settings
test('admin can view and update payroll settings', function () {
    $admin = createPayrollAdmin();
    seedDefaultComponents();

    $this->actingAs($admin)
        ->getJson('/api/hr/payroll/settings')
        ->assertSuccessful();

    $this->actingAs($admin)
        ->putJson('/api/hr/payroll/settings', [
            'unpaid_leave_divisor' => '30',
            'pay_day' => '28',
        ])
        ->assertSuccessful();
});

// Salary Components
test('admin can CRUD salary components', function () {
    $admin = createPayrollAdmin();

    $response = $this->actingAs($admin)->postJson('/api/hr/payroll/components', [
        'name' => 'Housing Allowance',
        'code' => 'HOUSING',
        'type' => 'earning',
        'category' => 'fixed_allowance',
        'is_taxable' => true,
        'is_epf_applicable' => true,
        'is_socso_applicable' => true,
        'is_eis_applicable' => true,
    ]);

    $response->assertCreated();
});

// My Payslips
test('employee can view own payslips', function () {
    $user = User::factory()->create(['role' => 'employee']);
    $employee = Employee::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->getJson('/api/hr/me/payslips')
        ->assertSuccessful();
});
```

**Step 3: Run tests**

```bash
php artisan test --compact tests/Feature/Hr/HrPayrollApiTest.php
```

**Step 4: Commit**

```bash
git add tests/Feature/Hr/HrPayrollApiTest.php
git commit -m "test(hr): add payroll API tests"
```

---

## Task 19: Migration — Claims Tables (claim_types, claim_requests, claim_approvers)

**Files:**
- Create: `database/migrations/YYYY_MM_DD_HHMMSS_create_claim_types_table.php`
- Create: `database/migrations/YYYY_MM_DD_HHMMSS_create_claim_requests_table.php`
- Create: `database/migrations/YYYY_MM_DD_HHMMSS_create_claim_approvers_table.php`

**Step 1: Create migrations**

```bash
php artisan make:migration create_claim_types_table --no-interaction
php artisan make:migration create_claim_requests_table --no-interaction
php artisan make:migration create_claim_approvers_table --no-interaction
```

**Step 2: Implement claim_types**

```php
Schema::create('claim_types', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('code', 20)->unique();
    $table->text('description')->nullable();
    $table->decimal('monthly_limit', 10, 2)->nullable();
    $table->decimal('yearly_limit', 10, 2)->nullable();
    $table->boolean('requires_receipt')->default(true);
    $table->boolean('is_active')->default(true);
    $table->integer('sort_order')->default(0);
    $table->timestamps();
});
```

**Step 3: Implement claim_requests**

```php
Schema::create('claim_requests', function (Blueprint $table) {
    $table->id();
    $table->string('claim_number')->unique();
    $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
    $table->foreignId('claim_type_id')->constrained('claim_types')->cascadeOnDelete();
    $table->decimal('amount', 10, 2);
    $table->decimal('approved_amount', 10, 2)->nullable();
    $table->date('claim_date');
    $table->text('description');
    $table->string('receipt_path');
    $table->enum('status', ['draft', 'pending', 'approved', 'rejected', 'paid'])->default('draft');
    $table->timestamp('submitted_at')->nullable();
    $table->foreignId('approved_by')->nullable()->constrained('employees')->nullOnDelete();
    $table->timestamp('approved_at')->nullable();
    $table->text('rejected_reason')->nullable();
    $table->timestamp('paid_at')->nullable();
    $table->string('paid_reference')->nullable();
    $table->timestamps();

    $table->index(['employee_id', 'status']);
    $table->index(['claim_type_id', 'status']);
    $table->index('claim_date');
});
```

**Step 4: Implement claim_approvers**

```php
Schema::create('claim_approvers', function (Blueprint $table) {
    $table->id();
    $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
    $table->foreignId('approver_id')->constrained('employees')->cascadeOnDelete();
    $table->boolean('is_active')->default(true);
    $table->timestamps();

    $table->unique(['employee_id', 'approver_id']);
});
```

**Step 5: Run migrations and commit**

```bash
php artisan migrate
git add database/migrations/
git commit -m "feat(hr): add claim_types, claim_requests, claim_approvers migrations"
```

---

## Task 20: Migration — Benefits Tables (benefit_types, employee_benefits)

**Files:**
- Create: `database/migrations/YYYY_MM_DD_HHMMSS_create_benefit_types_table.php`
- Create: `database/migrations/YYYY_MM_DD_HHMMSS_create_employee_benefits_table.php`

**Step 1: Create migrations**

```bash
php artisan make:migration create_benefit_types_table --no-interaction
php artisan make:migration create_employee_benefits_table --no-interaction
```

**Step 2: Implement benefit_types**

```php
Schema::create('benefit_types', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('code', 20)->unique();
    $table->text('description')->nullable();
    $table->enum('category', ['insurance', 'allowance', 'subsidy', 'other']);
    $table->boolean('is_active')->default(true);
    $table->integer('sort_order')->default(0);
    $table->timestamps();
});
```

**Step 3: Implement employee_benefits**

```php
Schema::create('employee_benefits', function (Blueprint $table) {
    $table->id();
    $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
    $table->foreignId('benefit_type_id')->constrained('benefit_types')->cascadeOnDelete();
    $table->string('provider')->nullable();
    $table->string('policy_number')->nullable();
    $table->decimal('coverage_amount', 10, 2)->nullable();
    $table->decimal('employer_contribution', 10, 2)->nullable();
    $table->decimal('employee_contribution', 10, 2)->nullable();
    $table->date('start_date');
    $table->date('end_date')->nullable();
    $table->text('notes')->nullable();
    $table->boolean('is_active')->default(true);
    $table->timestamps();
});
```

**Step 4: Run migrations and commit**

```bash
php artisan migrate
git add database/migrations/
git commit -m "feat(hr): add benefit_types, employee_benefits migrations"
```

---

## Task 21: Migration — Asset Tables (asset_categories, assets, asset_assignments)

**Files:**
- Create: `database/migrations/YYYY_MM_DD_HHMMSS_create_asset_categories_table.php`
- Create: `database/migrations/YYYY_MM_DD_HHMMSS_create_assets_table.php`
- Create: `database/migrations/YYYY_MM_DD_HHMMSS_create_asset_assignments_table.php`

**Step 1: Create migrations**

```bash
php artisan make:migration create_asset_categories_table --no-interaction
php artisan make:migration create_assets_table --no-interaction
php artisan make:migration create_asset_assignments_table --no-interaction
```

**Step 2: Implement asset_categories**

```php
Schema::create('asset_categories', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('code', 20)->unique();
    $table->text('description')->nullable();
    $table->boolean('requires_serial_number')->default(false);
    $table->boolean('is_active')->default(true);
    $table->integer('sort_order')->default(0);
    $table->timestamps();
});
```

**Step 3: Implement assets**

```php
Schema::create('assets', function (Blueprint $table) {
    $table->id();
    $table->string('asset_tag')->unique();
    $table->foreignId('asset_category_id')->constrained('asset_categories')->cascadeOnDelete();
    $table->string('name');
    $table->string('brand')->nullable();
    $table->string('model')->nullable();
    $table->string('serial_number')->nullable();
    $table->date('purchase_date')->nullable();
    $table->decimal('purchase_price', 10, 2)->nullable();
    $table->date('warranty_expiry')->nullable();
    $table->enum('condition', ['new', 'good', 'fair', 'poor', 'damaged', 'disposed'])->default('new');
    $table->enum('status', ['available', 'assigned', 'under_maintenance', 'disposed'])->default('available');
    $table->text('notes')->nullable();
    $table->timestamps();
});
```

**Step 4: Implement asset_assignments**

```php
Schema::create('asset_assignments', function (Blueprint $table) {
    $table->id();
    $table->foreignId('asset_id')->constrained('assets')->cascadeOnDelete();
    $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
    $table->foreignId('assigned_by')->constrained('employees');
    $table->date('assigned_date');
    $table->date('expected_return_date')->nullable();
    $table->date('returned_date')->nullable();
    $table->enum('returned_condition', ['good', 'fair', 'poor', 'damaged'])->nullable();
    $table->text('return_notes')->nullable();
    $table->enum('status', ['active', 'returned', 'lost', 'damaged'])->default('active');
    $table->text('notes')->nullable();
    $table->timestamps();

    $table->index(['asset_id', 'status']);
    $table->index(['employee_id', 'status']);
});
```

**Step 5: Run migrations and commit**

```bash
php artisan migrate
git add database/migrations/
git commit -m "feat(hr): add asset_categories, assets, asset_assignments migrations"
```

---

## Task 22: Claims Models (ClaimType, ClaimRequest, ClaimApprover)

**Files:**
- Create: `app/Models/ClaimType.php`
- Create: `app/Models/ClaimRequest.php`
- Create: `app/Models/ClaimApprover.php`

**Step 1: Create models with factories**

```bash
php artisan make:model ClaimType --factory --no-interaction
php artisan make:model ClaimRequest --factory --no-interaction
php artisan make:model ClaimApprover --factory --no-interaction
```

**Step 2: Implement models** following existing patterns — fillable, casts, relationships, scopes. ClaimRequest needs `generateClaimNumber()` static method (format: CLM-YYYYMM-0001). Add `claimRequests()`, `claimApprovers()`, `benefits()`, `assets()`, `assetAssignments()` relationships to Employee model.

**Step 3: Commit**

```bash
git add app/Models/ClaimType.php app/Models/ClaimRequest.php app/Models/ClaimApprover.php app/Models/Employee.php database/factories/
git commit -m "feat(hr): add ClaimType, ClaimRequest, ClaimApprover models"
```

---

## Task 23: Benefits Models (BenefitType, EmployeeBenefit)

**Files:**
- Create: `app/Models/BenefitType.php`
- Create: `app/Models/EmployeeBenefit.php`

**Step 1: Create models with factories**

```bash
php artisan make:model BenefitType --factory --no-interaction
php artisan make:model EmployeeBenefit --factory --no-interaction
```

**Step 2: Implement models** — Standard CRUD pattern. BenefitType has `employeeBenefits()` relationship. EmployeeBenefit belongs to employee and benefitType.

**Step 3: Commit**

```bash
git add app/Models/BenefitType.php app/Models/EmployeeBenefit.php database/factories/
git commit -m "feat(hr): add BenefitType, EmployeeBenefit models"
```

---

## Task 24: Asset Models (AssetCategory, Asset, AssetAssignment)

**Files:**
- Create: `app/Models/AssetCategory.php`
- Create: `app/Models/Asset.php`
- Create: `app/Models/AssetAssignment.php`

**Step 1: Create models with factories**

```bash
php artisan make:model AssetCategory --factory --no-interaction
php artisan make:model Asset --factory --no-interaction
php artisan make:model AssetAssignment --factory --no-interaction
```

**Step 2: Implement models** — Asset needs `generateAssetTag()` static method (format: AST-0001). AssetAssignment has `processReturn()` method that updates both assignment status and asset status. Asset has `assignments()` and `currentAssignment()` relationships.

**Step 3: Commit**

```bash
git add app/Models/AssetCategory.php app/Models/Asset.php app/Models/AssetAssignment.php database/factories/
git commit -m "feat(hr): add AssetCategory, Asset, AssetAssignment models"
```

---

## Task 25: Claims Controllers

**Files:**
- Create: `app/Http/Controllers/Api/Hr/HrClaimRequestController.php`
- Create: `app/Http/Controllers/Api/Hr/HrClaimTypeController.php`
- Create: `app/Http/Controllers/Api/Hr/HrClaimApproverController.php`
- Create: `app/Http/Controllers/Api/Hr/HrClaimDashboardController.php`
- Create: `app/Http/Controllers/Api/Hr/HrClaimReportController.php`
- Create: `app/Http/Controllers/Api/Hr/HrMyClaimController.php`

**Step 1: Create controllers**

```bash
php artisan make:controller Api/Hr/HrClaimRequestController --no-interaction
php artisan make:controller Api/Hr/HrClaimTypeController --no-interaction
php artisan make:controller Api/Hr/HrClaimApproverController --no-interaction
php artisan make:controller Api/Hr/HrClaimDashboardController --no-interaction
php artisan make:controller Api/Hr/HrClaimReportController --no-interaction
php artisan make:controller Api/Hr/HrMyClaimController --no-interaction
```

**Step 2: Implement controllers** following existing patterns:
- **HrClaimRequestController:** Index (filter by status/type/date/employee), show, store (with receipt upload via multipart/form-data), update (draft only), submit, approve (with approved_amount), reject (with reason), markPaid, destroy (draft only).
- **HrClaimTypeController:** Standard CRUD.
- **HrClaimApproverController:** Index, store, destroy.
- **HrClaimDashboardController:** Stats (pending count, monthly total, yearly total).
- **HrClaimReportController:** Reports (by employee, by type, monthly/yearly totals, CSV export).
- **HrMyClaimController:** Index (my claims), store (submit claim), show.

Key: Claim limit validation in store — check monthly/yearly usage before accepting.

**Step 3: Commit**

```bash
git add app/Http/Controllers/Api/Hr/HrClaim*.php app/Http/Controllers/Api/Hr/HrMyClaim*.php
git commit -m "feat(hr): add claims controllers with approval workflow"
```

---

## Task 26: Benefits Controllers

**Files:**
- Create: `app/Http/Controllers/Api/Hr/HrBenefitTypeController.php`
- Create: `app/Http/Controllers/Api/Hr/HrEmployeeBenefitController.php`

**Step 1: Create controllers**

```bash
php artisan make:controller Api/Hr/HrBenefitTypeController --no-interaction
php artisan make:controller Api/Hr/HrEmployeeBenefitController --no-interaction
```

**Step 2: Implement** — Standard CRUD for both. HrEmployeeBenefitController filters by employee_id and benefit_type_id.

**Step 3: Commit**

```bash
git add app/Http/Controllers/Api/Hr/HrBenefit*.php app/Http/Controllers/Api/Hr/HrEmployeeBenefit*.php
git commit -m "feat(hr): add benefit type and employee benefit controllers"
```

---

## Task 27: Asset Controllers

**Files:**
- Create: `app/Http/Controllers/Api/Hr/HrAssetCategoryController.php`
- Create: `app/Http/Controllers/Api/Hr/HrAssetController.php`
- Create: `app/Http/Controllers/Api/Hr/HrAssetAssignmentController.php`
- Create: `app/Http/Controllers/Api/Hr/HrMyAssetController.php`

**Step 1: Create controllers**

```bash
php artisan make:controller Api/Hr/HrAssetCategoryController --no-interaction
php artisan make:controller Api/Hr/HrAssetController --no-interaction
php artisan make:controller Api/Hr/HrAssetAssignmentController --no-interaction
php artisan make:controller Api/Hr/HrMyAssetController --no-interaction
```

**Step 2: Implement:**
- **HrAssetCategoryController:** Standard CRUD.
- **HrAssetController:** Index (filter by category/status/condition, search by tag/name/serial), store (auto-generate asset_tag), show (with assignment history), update, destroy (sets status to disposed).
- **HrAssetAssignmentController:** Index (filter by employee/category/status), store (assign asset, update asset status to assigned), returnAsset (process return, update asset status back).
- **HrMyAssetController:** Index (current employee's active assignments).

**Step 3: Commit**

```bash
git add app/Http/Controllers/Api/Hr/HrAsset*.php app/Http/Controllers/Api/Hr/HrMyAsset*.php
git commit -m "feat(hr): add asset inventory and assignment controllers"
```

---

## Task 28: Claims/Benefits/Assets Form Requests

**Files:**
- Create: `app/Http/Requests/Hr/StoreClaimTypeRequest.php`
- Create: `app/Http/Requests/Hr/StoreClaimRequestRequest.php`
- Create: `app/Http/Requests/Hr/StoreBenefitTypeRequest.php`
- Create: `app/Http/Requests/Hr/StoreEmployeeBenefitRequest.php`
- Create: `app/Http/Requests/Hr/StoreAssetCategoryRequest.php`
- Create: `app/Http/Requests/Hr/StoreAssetRequest.php`
- Create: `app/Http/Requests/Hr/StoreAssetAssignmentRequest.php`

**Step 1: Create form requests**

```bash
php artisan make:request Hr/StoreClaimTypeRequest --no-interaction
php artisan make:request Hr/StoreClaimRequestRequest --no-interaction
php artisan make:request Hr/StoreBenefitTypeRequest --no-interaction
php artisan make:request Hr/StoreEmployeeBenefitRequest --no-interaction
php artisan make:request Hr/StoreAssetCategoryRequest --no-interaction
php artisan make:request Hr/StoreAssetRequest --no-interaction
php artisan make:request Hr/StoreAssetAssignmentRequest --no-interaction
```

**Step 2: Implement validation** following existing patterns. Key rules:
- **StoreClaimRequestRequest:** `claim_type_id` (required, exists), `amount` (required, numeric, min:0.01), `claim_date` (required, date), `description` (required, string), `receipt` (required, file, max:5120, mimes:pdf,jpg,jpeg,png)
- **StoreAssetRequest:** `asset_category_id` (required, exists), `name` (required, string), `condition` (required, in:new,good,...), `serial_number` (required_if category requires serial number)

**Step 3: Commit**

```bash
git add app/Http/Requests/Hr/
git commit -m "feat(hr): add claims, benefits, assets form request validations"
```

---

## Task 29: Claims/Benefits/Assets API Routes

**Files:**
- Modify: `routes/api.php`

**Step 1: Add routes** inside the existing HR route group:

```php
// Claims Dashboard
Route::get('claims/dashboard', [HrClaimDashboardController::class, 'stats']);

// Claim Types
Route::apiResource('claims/types', HrClaimTypeController::class)->except('show');

// Claim Approvers
Route::get('claims/approvers', [HrClaimApproverController::class, 'index']);
Route::post('claims/approvers', [HrClaimApproverController::class, 'store']);
Route::delete('claims/approvers/{claimApprover}', [HrClaimApproverController::class, 'destroy']);

// Claim Requests (Admin)
Route::get('claims/requests', [HrClaimRequestController::class, 'index']);
Route::get('claims/requests/{claimRequest}', [HrClaimRequestController::class, 'show']);
Route::post('claims/requests/{claimRequest}/approve', [HrClaimRequestController::class, 'approve']);
Route::post('claims/requests/{claimRequest}/reject', [HrClaimRequestController::class, 'reject']);
Route::post('claims/requests/{claimRequest}/mark-paid', [HrClaimRequestController::class, 'markPaid']);

// Claims Reports
Route::get('claims/reports', [HrClaimReportController::class, 'index']);

// My Claims (Employee)
Route::get('me/claims', [HrMyClaimController::class, 'index']);
Route::post('me/claims', [HrMyClaimController::class, 'store']);
Route::get('me/claims/{claimRequest}', [HrMyClaimController::class, 'show']);
Route::put('me/claims/{claimRequest}', [HrMyClaimController::class, 'update']);
Route::post('me/claims/{claimRequest}/submit', [HrMyClaimController::class, 'submit']);
Route::delete('me/claims/{claimRequest}', [HrMyClaimController::class, 'destroy']);

// Benefit Types
Route::apiResource('benefits/types', HrBenefitTypeController::class)->except('show');

// Employee Benefits
Route::get('benefits', [HrEmployeeBenefitController::class, 'index']);
Route::post('benefits', [HrEmployeeBenefitController::class, 'store']);
Route::put('benefits/{employeeBenefit}', [HrEmployeeBenefitController::class, 'update']);
Route::delete('benefits/{employeeBenefit}', [HrEmployeeBenefitController::class, 'destroy']);

// Asset Categories
Route::apiResource('assets/categories', HrAssetCategoryController::class)->except('show');

// Assets
Route::get('assets', [HrAssetController::class, 'index']);
Route::post('assets', [HrAssetController::class, 'store']);
Route::get('assets/{asset}', [HrAssetController::class, 'show']);
Route::put('assets/{asset}', [HrAssetController::class, 'update']);
Route::delete('assets/{asset}', [HrAssetController::class, 'destroy']);

// Asset Assignments
Route::get('assets/assignments', [HrAssetAssignmentController::class, 'index']);
Route::post('assets/assignments', [HrAssetAssignmentController::class, 'store']);
Route::put('assets/assignments/{assetAssignment}/return', [HrAssetAssignmentController::class, 'returnAsset']);

// My Assets (Employee)
Route::get('me/assets', [HrMyAssetController::class, 'index']);
```

**Step 2: Add controller imports.**

**Step 3: Commit**

```bash
git add routes/api.php
git commit -m "feat(hr): add claims, benefits, assets API routes"
```

---

## Task 30: Claims/Benefits/Assets Factories

**Files:**
- Modify factories created in Tasks 22-24.

**Step 1: Implement factories** — ClaimRequest factory with `->pending()`, `->approved()`, `->paid()` states. Asset factory with counter for asset_tag (AST-0001). AssetAssignment factory with `->returned()` state.

**Step 2: Commit**

```bash
git add database/factories/
git commit -m "feat(hr): add claims, benefits, assets factories"
```

---

## Task 31: Claims/Benefits/Assets Tests

**Files:**
- Create: `tests/Feature/Hr/HrClaimsApiTest.php`
- Create: `tests/Feature/Hr/HrAssetsApiTest.php`

**Step 1: Create test files**

```bash
php artisan make:test Hr/HrClaimsApiTest --pest --no-interaction
php artisan make:test Hr/HrAssetsApiTest --pest --no-interaction
```

**Step 2: Implement HrClaimsApiTest**

```php
<?php

declare(strict_types=1);

use App\Models\ClaimApprover;
use App\Models\ClaimRequest;
use App\Models\ClaimType;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

// Claim Types CRUD
test('admin can create claim type', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)->postJson('/api/hr/claims/types', [
        'name' => 'Medical Claim',
        'code' => 'MEDICAL',
        'monthly_limit' => 500,
        'yearly_limit' => 3000,
        'requires_receipt' => true,
    ])->assertCreated();
});

// Claim Submission Flow
test('employee can submit claim', function () {
    Storage::fake('public');
    $user = User::factory()->create(['role' => 'employee']);
    $employee = Employee::factory()->create(['user_id' => $user->id]);
    $claimType = ClaimType::factory()->create();

    $this->actingAs($user)->postJson('/api/hr/me/claims', [
        'claim_type_id' => $claimType->id,
        'amount' => 150.00,
        'claim_date' => now()->format('Y-m-d'),
        'description' => 'Doctor visit',
        'receipt' => UploadedFile::fake()->image('receipt.jpg'),
    ])->assertCreated();
});

// Approval Flow
test('admin can approve claim with different amount', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $adminEmployee = Employee::factory()->create(['user_id' => $admin->id]);
    $claim = ClaimRequest::factory()->create(['status' => 'pending', 'amount' => 200]);

    $this->actingAs($admin)->postJson("/api/hr/claims/requests/{$claim->id}/approve", [
        'approved_amount' => 150,
    ])->assertSuccessful();

    expect($claim->fresh()->approved_amount)->toBe('150.00');
    expect($claim->fresh()->status)->toBe('approved');
});

// Limit Validation
test('claim warns when monthly limit exceeded', function () {
    $user = User::factory()->create(['role' => 'employee']);
    $employee = Employee::factory()->create(['user_id' => $user->id]);
    $claimType = ClaimType::factory()->create(['monthly_limit' => 500]);

    // Existing approved claim for RM400
    ClaimRequest::factory()->create([
        'employee_id' => $employee->id,
        'claim_type_id' => $claimType->id,
        'amount' => 400,
        'status' => 'approved',
        'claim_date' => now(),
    ]);

    // Try to claim RM200 more (exceeds RM500 limit)
    Storage::fake('public');
    $response = $this->actingAs($user)->postJson('/api/hr/me/claims', [
        'claim_type_id' => $claimType->id,
        'amount' => 200,
        'claim_date' => now()->format('Y-m-d'),
        'description' => 'Over limit',
        'receipt' => UploadedFile::fake()->image('receipt.jpg'),
    ]);

    // Should still create but with warning
    $response->assertCreated();
});
```

**Step 3: Implement HrAssetsApiTest** — Tests for: asset CRUD, assignment flow, return flow, my assets endpoint.

**Step 4: Run tests**

```bash
php artisan test --compact tests/Feature/Hr/HrClaimsApiTest.php tests/Feature/Hr/HrAssetsApiTest.php
```

**Step 5: Commit**

```bash
git add tests/Feature/Hr/
git commit -m "test(hr): add claims, benefits, assets API tests"
```

---

## Task 32: React API Client — All Phase 3 Endpoints

**Files:**
- Modify: `resources/js/hr/lib/api.js`

**Step 1: Add all Phase 3 endpoint functions** following existing naming pattern:

```javascript
// ========== Payroll Dashboard ==========
export const fetchPayrollDashboardStats = () => api.get('/payroll/dashboard/stats').then(r => r.data);
export const fetchPayrollTrend = () => api.get('/payroll/dashboard/trend').then(r => r.data);
export const fetchStatutoryBreakdown = () => api.get('/payroll/dashboard/statutory-breakdown').then(r => r.data);

// ========== Payroll Runs ==========
export const fetchPayrollRuns = (params) => api.get('/payroll/runs', { params }).then(r => r.data);
export const fetchPayrollRun = (id) => api.get(`/payroll/runs/${id}`).then(r => r.data);
export const createPayrollRun = (data) => api.post('/payroll/runs', data).then(r => r.data);
export const deletePayrollRun = (id) => api.delete(`/payroll/runs/${id}`).then(r => r.data);
export const calculatePayroll = (id) => api.post(`/payroll/runs/${id}/calculate`).then(r => r.data);
export const calculatePayrollEmployee = (runId, empId) => api.post(`/payroll/runs/${runId}/calculate/${empId}`).then(r => r.data);
export const submitPayrollReview = (id) => api.patch(`/payroll/runs/${id}/submit-review`).then(r => r.data);
export const approvePayroll = (id) => api.patch(`/payroll/runs/${id}/approve`).then(r => r.data);
export const returnPayrollDraft = (id) => api.patch(`/payroll/runs/${id}/return-draft`).then(r => r.data);
export const finalizePayroll = (id) => api.patch(`/payroll/runs/${id}/finalize`).then(r => r.data);

// ========== Payroll Items ==========
export const addPayrollItem = (runId, data) => api.post(`/payroll/runs/${runId}/items`, data).then(r => r.data);
export const updatePayrollItem = (runId, itemId, data) => api.put(`/payroll/runs/${runId}/items/${itemId}`, data).then(r => r.data);
export const deletePayrollItem = (runId, itemId) => api.delete(`/payroll/runs/${runId}/items/${itemId}`).then(r => r.data);

// ========== Salary Components ==========
export const fetchSalaryComponents = (params) => api.get('/payroll/components', { params }).then(r => r.data);
export const createSalaryComponent = (data) => api.post('/payroll/components', data).then(r => r.data);
export const updateSalaryComponent = (id, data) => api.put(`/payroll/components/${id}`, data).then(r => r.data);
export const deleteSalaryComponent = (id) => api.delete(`/payroll/components/${id}`).then(r => r.data);

// ========== Employee Salaries ==========
export const fetchEmployeeSalaries = (params) => api.get('/payroll/salaries', { params }).then(r => r.data);
export const fetchEmployeeSalary = (employeeId) => api.get(`/payroll/salaries/${employeeId}`).then(r => r.data);
export const createEmployeeSalary = (data) => api.post('/payroll/salaries', data).then(r => r.data);
export const updateEmployeeSalary = (id, data) => api.put(`/payroll/salaries/${id}`, data).then(r => r.data);
export const fetchSalaryRevisions = (employeeId) => api.get(`/payroll/salaries/${employeeId}/revisions`).then(r => r.data);
export const bulkSalaryRevision = (data) => api.post('/payroll/salaries/bulk-revision', data).then(r => r.data);

// ========== Tax Profiles ==========
export const fetchTaxProfiles = (params) => api.get('/payroll/tax-profiles', { params }).then(r => r.data);
export const fetchTaxProfile = (employeeId) => api.get(`/payroll/tax-profiles/${employeeId}`).then(r => r.data);
export const updateTaxProfile = (employeeId, data) => api.put(`/payroll/tax-profiles/${employeeId}`, data).then(r => r.data);

// ========== Statutory Rates ==========
export const fetchStatutoryRates = (params) => api.get('/payroll/statutory-rates', { params }).then(r => r.data);
export const updateStatutoryRate = (id, data) => api.put(`/payroll/statutory-rates/${id}`, data).then(r => r.data);
export const bulkUpdateStatutoryRates = (data) => api.post('/payroll/statutory-rates/bulk-update', data).then(r => r.data);

// ========== Payslips ==========
export const fetchPayslips = (params) => api.get('/payroll/payslips', { params }).then(r => r.data);
export const fetchPayslip = (id) => api.get(`/payroll/payslips/${id}`).then(r => r.data);
export const downloadPayslipPdf = (id) => api.get(`/payroll/payslips/${id}/pdf`, { responseType: 'blob' }).then(r => r.data);
export const downloadBulkPayslipsPdf = (runId) => api.get(`/payroll/payslips/bulk-pdf/${runId}`, { responseType: 'blob' }).then(r => r.data);

// ========== Payroll Reports ==========
export const fetchPayrollMonthlySummary = (params) => api.get('/payroll/reports/monthly-summary', { params }).then(r => r.data);
export const fetchPayrollStatutoryReport = (params) => api.get('/payroll/reports/statutory', { params }).then(r => r.data);
export const fetchPayrollBankPayment = (params) => api.get('/payroll/reports/bank-payment', { params }).then(r => r.data);
export const fetchPayrollYtd = (params) => api.get('/payroll/reports/ytd', { params }).then(r => r.data);
export const downloadEaForm = (employeeId) => api.get(`/payroll/reports/ea-form/${employeeId}`, { responseType: 'blob' }).then(r => r.data);
export const downloadEaForms = (year) => api.get(`/payroll/reports/ea-forms/${year}`, { responseType: 'blob' }).then(r => r.data);

// ========== Payroll Settings ==========
export const fetchPayrollSettings = () => api.get('/payroll/settings').then(r => r.data);
export const updatePayrollSettings = (data) => api.put('/payroll/settings', data).then(r => r.data);

// ========== My Payslips ==========
export const fetchMyPayslips = (params) => api.get('/me/payslips', { params }).then(r => r.data);
export const fetchMyPayslip = (id) => api.get(`/me/payslips/${id}`).then(r => r.data);
export const downloadMyPayslipPdf = (id) => api.get(`/me/payslips/${id}/pdf`, { responseType: 'blob' }).then(r => r.data);
export const fetchMyPayslipYtd = () => api.get('/me/payslips/ytd').then(r => r.data);

// ========== Claims ==========
export const fetchClaimsDashboard = () => api.get('/claims/dashboard').then(r => r.data);
export const fetchClaimTypes = (params) => api.get('/claims/types', { params }).then(r => r.data);
export const createClaimType = (data) => api.post('/claims/types', data).then(r => r.data);
export const updateClaimType = (id, data) => api.put(`/claims/types/${id}`, data).then(r => r.data);
export const deleteClaimType = (id) => api.delete(`/claims/types/${id}`).then(r => r.data);
export const fetchClaimApprovers = (params) => api.get('/claims/approvers', { params }).then(r => r.data);
export const createClaimApprover = (data) => api.post('/claims/approvers', data).then(r => r.data);
export const deleteClaimApprover = (id) => api.delete(`/claims/approvers/${id}`).then(r => r.data);
export const fetchClaimRequests = (params) => api.get('/claims/requests', { params }).then(r => r.data);
export const fetchClaimRequest = (id) => api.get(`/claims/requests/${id}`).then(r => r.data);
export const approveClaim = (id, data) => api.post(`/claims/requests/${id}/approve`, data).then(r => r.data);
export const rejectClaim = (id, data) => api.post(`/claims/requests/${id}/reject`, data).then(r => r.data);
export const markClaimPaid = (id, data) => api.post(`/claims/requests/${id}/mark-paid`, data).then(r => r.data);
export const fetchClaimsReport = (params) => api.get('/claims/reports', { params }).then(r => r.data);

// ========== My Claims ==========
export const fetchMyClaims = (params) => api.get('/me/claims', { params }).then(r => r.data);
export const fetchMyClaim = (id) => api.get(`/me/claims/${id}`).then(r => r.data);
export const createMyClaim = (data) => api.post('/me/claims', data, {
    headers: { 'Content-Type': 'multipart/form-data' },
}).then(r => r.data);
export const updateMyClaim = (id, data) => api.put(`/me/claims/${id}`, data).then(r => r.data);
export const submitMyClaim = (id) => api.post(`/me/claims/${id}/submit`).then(r => r.data);
export const deleteMyClaim = (id) => api.delete(`/me/claims/${id}`).then(r => r.data);

// ========== Benefits ==========
export const fetchBenefitTypes = (params) => api.get('/benefits/types', { params }).then(r => r.data);
export const createBenefitType = (data) => api.post('/benefits/types', data).then(r => r.data);
export const updateBenefitType = (id, data) => api.put(`/benefits/types/${id}`, data).then(r => r.data);
export const deleteBenefitType = (id) => api.delete(`/benefits/types/${id}`).then(r => r.data);
export const fetchEmployeeBenefits = (params) => api.get('/benefits', { params }).then(r => r.data);
export const createEmployeeBenefit = (data) => api.post('/benefits', data).then(r => r.data);
export const updateEmployeeBenefit = (id, data) => api.put(`/benefits/${id}`, data).then(r => r.data);
export const deleteEmployeeBenefit = (id) => api.delete(`/benefits/${id}`).then(r => r.data);

// ========== Assets ==========
export const fetchAssetCategories = (params) => api.get('/assets/categories', { params }).then(r => r.data);
export const createAssetCategory = (data) => api.post('/assets/categories', data).then(r => r.data);
export const updateAssetCategory = (id, data) => api.put(`/assets/categories/${id}`, data).then(r => r.data);
export const deleteAssetCategory = (id) => api.delete(`/assets/categories/${id}`).then(r => r.data);
export const fetchAssets = (params) => api.get('/assets', { params }).then(r => r.data);
export const fetchAsset = (id) => api.get(`/assets/${id}`).then(r => r.data);
export const createAsset = (data) => api.post('/assets', data).then(r => r.data);
export const updateAsset = (id, data) => api.put(`/assets/${id}`, data).then(r => r.data);
export const deleteAsset = (id) => api.delete(`/assets/${id}`).then(r => r.data);
export const fetchAssetAssignments = (params) => api.get('/assets/assignments', { params }).then(r => r.data);
export const createAssetAssignment = (data) => api.post('/assets/assignments', data).then(r => r.data);
export const returnAsset = (id, data) => api.put(`/assets/assignments/${id}/return`, data).then(r => r.data);
export const fetchMyAssets = () => api.get('/me/assets').then(r => r.data);
```

**Step 2: Commit**

```bash
git add resources/js/hr/lib/api.js
git commit -m "feat(hr): add Phase 3 API client functions (payroll, claims, benefits, assets)"
```

---

## Task 33: Payroll React Pages — Dashboard & Run

**Files:**
- Create: `resources/js/hr/pages/payroll/PayrollDashboard.jsx`
- Create: `resources/js/hr/pages/payroll/PayrollRun.jsx`

**Step 1: Implement PayrollDashboard** — Stats cards (Total Gross, Total Deductions, Total Net, Employer Cost), monthly trend bar chart (Recharts), statutory breakdown pie chart, recent runs table with quick actions.

**Step 2: Implement PayrollRun** — Core processing page. Header with month/year and status badge. Summary cards. Employee payroll table. Status-based action buttons (Calculate, Submit Review, Approve, Return to Draft, Finalize). Ad-hoc item modal. Per-employee recalculate.

**Step 3: Commit**

```bash
git add resources/js/hr/pages/payroll/
git commit -m "feat(hr): add PayrollDashboard and PayrollRun React pages"
```

---

## Task 34: Payroll React Pages — History & Components

**Files:**
- Create: `resources/js/hr/pages/payroll/PayrollHistory.jsx`
- Create: `resources/js/hr/pages/payroll/SalaryComponents.jsx`

**Step 1: Implement PayrollHistory** — Table of all payroll runs by year, filterable by year/status, with links to run detail page.

**Step 2: Implement SalaryComponents** — CRUD table for salary components. System components are read-only (cannot edit/delete). Toggle active status.

**Step 3: Commit**

```bash
git add resources/js/hr/pages/payroll/
git commit -m "feat(hr): add PayrollHistory and SalaryComponents React pages"
```

---

## Task 35: Payroll React Pages — Salaries & Tax Profiles

**Files:**
- Create: `resources/js/hr/pages/payroll/EmployeeSalaries.jsx`
- Create: `resources/js/hr/pages/payroll/TaxProfiles.jsx`

**Step 1: Implement EmployeeSalaries** — Employee list with salary breakdown. Click employee to edit salary components. Effective date management. Revision history per employee. Bulk revision capability.

**Step 2: Implement TaxProfiles** — Employee list with tax profile info. Edit modal for marital status, children, disabled status, manual PCB toggle.

**Step 3: Commit**

```bash
git add resources/js/hr/pages/payroll/
git commit -m "feat(hr): add EmployeeSalaries and TaxProfiles React pages"
```

---

## Task 36: Payroll React Pages — Statutory Rates, Reports, Settings

**Files:**
- Create: `resources/js/hr/pages/payroll/StatutoryRates.jsx`
- Create: `resources/js/hr/pages/payroll/PayrollReports.jsx`
- Create: `resources/js/hr/pages/payroll/PayrollSettings.jsx`

**Step 1: Implement StatutoryRates** — Tabbed view (EPF, SOCSO, EIS, PCB). Editable rate tables per type.

**Step 2: Implement PayrollReports** — Report selector with tabs: Monthly Summary, Statutory Contributions, Bank Payment List, YTD Report. Each with table and CSV export.

**Step 3: Implement PayrollSettings** — Form for unpaid leave divisor, pay day, company info, registration numbers.

**Step 4: Commit**

```bash
git add resources/js/hr/pages/payroll/
git commit -m "feat(hr): add StatutoryRates, PayrollReports, PayrollSettings React pages"
```

---

## Task 37: Payroll React Pages — EA Forms & My Payslips

**Files:**
- Create: `resources/js/hr/pages/payroll/EaForms.jsx`
- Create: `resources/js/hr/pages/my/MyPayslips.jsx`

**Step 1: Implement EaForms** — Year selector. Employee list with EA Form status. Generate button. Download individual or bulk ZIP.

**Step 2: Implement MyPayslips** — Payslip list (month, year, gross, net). Click to view detail. Download PDF button. YTD summary card at top.

**Step 3: Commit**

```bash
git add resources/js/hr/pages/payroll/ resources/js/hr/pages/my/
git commit -m "feat(hr): add EaForms and MyPayslips React pages"
```

---

## Task 38: Claims React Pages — Dashboard, Requests, Types, Approvers

**Files:**
- Create: `resources/js/hr/pages/claims/ClaimsDashboard.jsx`
- Create: `resources/js/hr/pages/claims/ClaimRequests.jsx`
- Create: `resources/js/hr/pages/claims/ClaimTypes.jsx`
- Create: `resources/js/hr/pages/claims/ClaimApprovers.jsx`

**Step 1: Implement pages** following existing Leave module pattern. ClaimsDashboard has stats + pending claims quick view. ClaimRequests has filterable table with approve/reject actions and receipt preview modal. ClaimTypes has CRUD with limit configuration. ClaimApprovers assigns approvers per employee.

**Step 2: Commit**

```bash
git add resources/js/hr/pages/claims/
git commit -m "feat(hr): add Claims admin React pages"
```

---

## Task 39: Claims React Pages — Reports, My Claims

**Files:**
- Create: `resources/js/hr/pages/claims/ClaimsReports.jsx`
- Create: `resources/js/hr/pages/my/MyClaims.jsx`

**Step 1: Implement ClaimsReports** — By employee, by type, monthly/yearly totals. CSV export.

**Step 2: Implement MyClaims** — My claims list with status filter. Submit new claim form (type, amount, date, description, receipt upload). Usage summary per claim type showing remaining limits.

**Step 3: Commit**

```bash
git add resources/js/hr/pages/claims/ resources/js/hr/pages/my/
git commit -m "feat(hr): add ClaimsReports and MyClaims React pages"
```

---

## Task 40: Benefits React Pages

**Files:**
- Create: `resources/js/hr/pages/benefits/BenefitsManagement.jsx`
- Create: `resources/js/hr/pages/benefits/BenefitTypes.jsx`

**Step 1: Implement BenefitsManagement** — List employee benefits, assign/edit/remove. Filter by employee, benefit type. Cost summary.

**Step 2: Implement BenefitTypes** — CRUD for benefit types with category selector.

**Step 3: Commit**

```bash
git add resources/js/hr/pages/benefits/
git commit -m "feat(hr): add Benefits React pages"
```

---

## Task 41: Assets React Pages

**Files:**
- Create: `resources/js/hr/pages/assets/AssetInventory.jsx`
- Create: `resources/js/hr/pages/assets/AssetCategories.jsx`
- Create: `resources/js/hr/pages/assets/AssetAssignments.jsx`
- Create: `resources/js/hr/pages/my/MyAssets.jsx`

**Step 1: Implement AssetInventory** — Asset table with search/filter. Add/edit/dispose assets. Click to view assignment history.

**Step 2: Implement AssetCategories** — CRUD with serial number requirement toggle.

**Step 3: Implement AssetAssignments** — Current assignments table. Assign modal (select asset + employee). Return modal (condition, notes).

**Step 4: Implement MyAssets** — Employee's currently assigned assets with details.

**Step 5: Commit**

```bash
git add resources/js/hr/pages/assets/ resources/js/hr/pages/my/
git commit -m "feat(hr): add Assets React pages"
```

---

## Task 42: React Router & Layout Updates

**Files:**
- Modify: `resources/js/hr/App.jsx`
- Modify: `resources/js/hr/layouts/HrLayout.jsx` (add sidebar navigation items)

**Step 1: Add imports** for all new pages in App.jsx.

**Step 2: Add admin routes** in AdminRoutes():

```jsx
{/* Payroll */}
<Route path="payroll" element={<PayrollDashboard />} />
<Route path="payroll/run/:id" element={<PayrollRun />} />
<Route path="payroll/history" element={<PayrollHistory />} />
<Route path="payroll/components" element={<SalaryComponents />} />
<Route path="payroll/salaries" element={<EmployeeSalaries />} />
<Route path="payroll/tax-profiles" element={<TaxProfiles />} />
<Route path="payroll/statutory-rates" element={<StatutoryRates />} />
<Route path="payroll/reports" element={<PayrollReports />} />
<Route path="payroll/settings" element={<PayrollSettings />} />
<Route path="payroll/ea-forms" element={<EaForms />} />

{/* Claims */}
<Route path="claims" element={<ClaimsDashboard />} />
<Route path="claims/requests" element={<ClaimRequests />} />
<Route path="claims/types" element={<ClaimTypes />} />
<Route path="claims/approvers" element={<ClaimApprovers />} />
<Route path="claims/reports" element={<ClaimsReports />} />

{/* Benefits */}
<Route path="benefits" element={<BenefitsManagement />} />
<Route path="benefits/types" element={<BenefitTypes />} />

{/* Assets */}
<Route path="assets" element={<AssetInventory />} />
<Route path="assets/categories" element={<AssetCategories />} />
<Route path="assets/assignments" element={<AssetAssignments />} />
```

**Step 3: Add employee routes** in EmployeeRoutes():

```jsx
<Route path="my/payslips" element={<MyPayslips />} />
<Route path="my/claims" element={<MyClaims />} />
<Route path="my/assets" element={<MyAssets />} />
```

**Step 4: Update HrLayout sidebar** — Add Payroll, Claims, Benefits, Assets navigation sections.

**Step 5: Commit**

```bash
git add resources/js/hr/App.jsx resources/js/hr/layouts/HrLayout.jsx
git commit -m "feat(hr): add Phase 3 routes and navigation to HR app"
```

---

## Task 43: PDF Payslip Template

**Files:**
- Create: `resources/views/pdf/payslip.blade.php`

**Step 1: Install DomPDF** (if not already installed)

```bash
composer require barryvdh/laravel-dompdf --no-interaction
```

**Step 2: Create payslip Blade template** — Company header, employee info, earnings column, deductions column, net pay, employer contributions section. Use inline CSS for PDF compatibility.

**Step 3: Update HrPayslipController** to use the template:

```php
use Barryvdh\DomPDF\Facade\Pdf;

public function pdf(Payslip $payslip): \Illuminate\Http\Response
{
    $payslip->load(['employee.department', 'employee.position', 'payrollRun.items' => function ($q) use ($payslip) {
        $q->where('employee_id', $payslip->employee_id);
    }]);

    $pdf = Pdf::loadView('pdf.payslip', [
        'payslip' => $payslip,
        'settings' => PayrollSetting::all()->pluck('value', 'key'),
    ]);

    return $pdf->download("payslip-{$payslip->employee->employee_id}-{$payslip->year}-{$payslip->month}.pdf");
}
```

**Step 4: Commit**

```bash
git add resources/views/pdf/payslip.blade.php app/Http/Controllers/Api/Hr/HrPayslipController.php composer.json composer.lock
git commit -m "feat(hr): add PDF payslip template with DomPDF"
```

---

## Task 44: Phase 3 Seeder

**Files:**
- Create: `database/seeders/HrPhase3Seeder.php`
- Modify: `database/seeders/HrSeeder.php` (call Phase 3 seeder)

**Step 1: Create seeder**

```bash
php artisan make:seeder HrPhase3Seeder --no-interaction
```

**Step 2: Implement** — Seed salary components (via HrPayrollSeeder), sample employee salaries, tax profiles, sample claim types (Medical, Transport, Parking, Meals), sample benefit types (Health Insurance, Dental, Life Insurance), sample asset categories (Laptop, Monitor, Phone, Office Chair, Access Card), sample assets and assignments.

**Step 3: Update HrSeeder** to call `$this->call(HrPhase3Seeder::class);`

**Step 4: Run seeder**

```bash
php artisan db:seed --class=HrPhase3Seeder
```

**Step 5: Commit**

```bash
git add database/seeders/HrPhase3Seeder.php database/seeders/HrSeeder.php
git commit -m "feat(hr): add Phase 3 seeder with sample payroll, claims, benefits, assets data"
```

---

## Task 45: Final Integration Test

**Files:**
- Create: `tests/Feature/Hr/HrPhase3IntegrationTest.php`

**Step 1: Create test**

```bash
php artisan make:test Hr/HrPhase3IntegrationTest --pest --no-interaction
```

**Step 2: Implement end-to-end tests:**

```php
<?php

declare(strict_types=1);

use App\Models\Employee;
use App\Models\EmployeeSalary;
use App\Models\EmployeeTaxProfile;
use App\Models\SalaryComponent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('complete payroll workflow: create run, calculate, approve, finalize', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    // Seed components
    app(\Database\Seeders\HrPayrollSeeder::class)->run();

    // Create employee with salary
    $employee = Employee::factory()->create(['status' => 'active']);
    $basic = SalaryComponent::where('code', 'BASIC')->first();
    EmployeeSalary::create([
        'employee_id' => $employee->id,
        'salary_component_id' => $basic->id,
        'amount' => 5000,
        'effective_from' => now()->subYear(),
    ]);
    EmployeeTaxProfile::create([
        'employee_id' => $employee->id,
        'marital_status' => 'single',
        'num_children' => 0,
    ]);

    // Create payroll run
    $response = $this->actingAs($admin)->postJson('/api/hr/payroll/runs', [
        'month' => 3, 'year' => 2026,
    ]);
    $runId = $response->json('data.id');

    // Calculate
    $this->actingAs($admin)->postJson("/api/hr/payroll/runs/{$runId}/calculate")
        ->assertSuccessful();

    // Submit review
    $this->actingAs($admin)->patchJson("/api/hr/payroll/runs/{$runId}/submit-review")
        ->assertSuccessful();

    // Approve
    $this->actingAs($admin)->patchJson("/api/hr/payroll/runs/{$runId}/approve")
        ->assertSuccessful();

    // Finalize
    $this->actingAs($admin)->patchJson("/api/hr/payroll/runs/{$runId}/finalize")
        ->assertSuccessful();

    // Verify payslip created
    $this->assertDatabaseHas('payslips', [
        'employee_id' => $employee->id,
        'month' => 3,
        'year' => 2026,
    ]);
});

test('complete claim workflow: submit, approve, mark paid', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $adminEmployee = Employee::factory()->create(['user_id' => $admin->id]);
    $user = User::factory()->create(['role' => 'employee']);
    $employee = Employee::factory()->create(['user_id' => $user->id]);

    $claimType = \App\Models\ClaimType::create([
        'name' => 'Medical', 'code' => 'MED',
        'monthly_limit' => 500, 'yearly_limit' => 3000,
    ]);

    \Illuminate\Support\Facades\Storage::fake('public');

    // Employee submits claim
    $response = $this->actingAs($user)->postJson('/api/hr/me/claims', [
        'claim_type_id' => $claimType->id,
        'amount' => 150,
        'claim_date' => now()->format('Y-m-d'),
        'description' => 'Doctor visit',
        'receipt' => \Illuminate\Http\UploadedFile::fake()->image('receipt.jpg'),
    ]);
    $claimId = $response->json('data.id');

    // Submit for approval
    $this->actingAs($user)->postJson("/api/hr/me/claims/{$claimId}/submit")
        ->assertSuccessful();

    // Admin approves
    $this->actingAs($admin)->postJson("/api/hr/claims/requests/{$claimId}/approve", [
        'approved_amount' => 150,
    ])->assertSuccessful();

    // Admin marks as paid
    $this->actingAs($admin)->postJson("/api/hr/claims/requests/{$claimId}/mark-paid", [
        'paid_reference' => 'TXN-001',
    ])->assertSuccessful();

    $this->assertDatabaseHas('claim_requests', [
        'id' => $claimId,
        'status' => 'paid',
    ]);
});

test('asset assignment and return flow', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $adminEmployee = Employee::factory()->create(['user_id' => $admin->id]);
    $employee = Employee::factory()->create(['status' => 'active']);

    $category = \App\Models\AssetCategory::create([
        'name' => 'Laptop', 'code' => 'LAPTOP',
    ]);

    // Create asset
    $response = $this->actingAs($admin)->postJson('/api/hr/assets', [
        'asset_category_id' => $category->id,
        'name' => 'MacBook Pro 14"',
        'brand' => 'Apple',
        'condition' => 'new',
    ]);
    $assetId = $response->json('data.id');

    // Assign to employee
    $assignResponse = $this->actingAs($admin)->postJson('/api/hr/assets/assignments', [
        'asset_id' => $assetId,
        'employee_id' => $employee->id,
        'assigned_date' => now()->format('Y-m-d'),
    ]);
    $assignmentId = $assignResponse->json('data.id');

    // Verify asset status changed
    $this->assertDatabaseHas('assets', ['id' => $assetId, 'status' => 'assigned']);

    // Return asset
    $this->actingAs($admin)->putJson("/api/hr/assets/assignments/{$assignmentId}/return", [
        'returned_condition' => 'good',
        'return_notes' => 'No damage',
    ])->assertSuccessful();

    // Verify statuses
    $this->assertDatabaseHas('assets', ['id' => $assetId, 'status' => 'available']);
    $this->assertDatabaseHas('asset_assignments', ['id' => $assignmentId, 'status' => 'returned']);
});
```

**Step 3: Run all Phase 3 tests**

```bash
php artisan test --compact tests/Feature/Hr/HrPayrollApiTest.php tests/Feature/Hr/HrClaimsApiTest.php tests/Feature/Hr/HrAssetsApiTest.php tests/Feature/Hr/HrPhase3IntegrationTest.php
```

**Step 4: Commit**

```bash
git add tests/Feature/Hr/HrPhase3IntegrationTest.php
git commit -m "test(hr): add Phase 3 end-to-end integration tests"
```

---

## Summary

| # | Task | Files | Est. |
|---|------|-------|------|
| 1-3 | Payroll Migrations (9 tables) | 10 migration files | Backend |
| 4-6 | Payroll Models (9 models) | 9 model files + Employee updates | Backend |
| 7 | Payroll Seeder | 1 seeder | Backend |
| 8-9 | Statutory + Payroll Services | 2 service files | Backend |
| 10-14 | Payroll Controllers (10) | 10 controller files | Backend |
| 15 | Payroll Form Requests | 5 request files | Backend |
| 16 | Payroll Routes | 1 route file update | Backend |
| 17-18 | Payroll Factories + Tests | 6 factories + 1 test file | Testing |
| 19-21 | Claims/Benefits/Assets Migrations (8 tables) | 8 migration files | Backend |
| 22-24 | Claims/Benefits/Assets Models (8) | 8 model files | Backend |
| 25-27 | Claims/Benefits/Assets Controllers (10) | 10 controller files | Backend |
| 28 | Claims/Benefits/Assets Form Requests | 7 request files | Backend |
| 29 | Claims/Benefits/Assets Routes | 1 route file update | Backend |
| 30-31 | Claims/Benefits/Assets Factories + Tests | 6 factories + 2 test files | Testing |
| 32 | React API Client | 1 file update (~80 functions) | Frontend |
| 33-37 | Payroll React Pages (11) | 11 page files | Frontend |
| 38-41 | Claims/Benefits/Assets React Pages (12) | 12 page files | Frontend |
| 42 | Router + Layout Updates | 2 file updates | Frontend |
| 43 | PDF Payslip Template | 1 Blade template + DomPDF | Backend |
| 44 | Phase 3 Seeder | 1 seeder + update existing | Backend |
| 45 | Integration Tests | 1 test file | Testing |

**Total: ~45 tasks, ~100 files**
