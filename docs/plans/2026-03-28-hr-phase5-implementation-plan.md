# HR Phase 5: Training, Disciplinary & Offboarding — Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Build Module 10 (Disciplinary & Offboarding) and Module 9 (Training & Development) — adding Malaysian-standard disciplinary escalation, resignation workflow, exit checklists, final settlement calculation, training programs, certifications, and budget tracking.

**Architecture:** React 19 SPA at `/hr/*` with Laravel 12 JSON API at `/api/hr/*`. Follows existing patterns from Phases 1-4: models in `app/Models/`, controllers in `app/Http/Controllers/Api/Hr/`, React pages in `resources/js/hr/pages/`, API client in `resources/js/hr/lib/api.js`. All models use `HasFactory`, `$fillable`, `casts()` method, typed relationships, and query scopes.

**Tech Stack:** Laravel 12 (PHP 8.3) · React 19 + Shadcn/ui + Tailwind CSS v4 · TanStack Query · Laravel DomPDF · Pest

**Design Docs:**
- [Module 10 — Disciplinary & Offboarding](2026-03-28-hr-module10-disciplinary-offboarding-design.md)
- [Module 9 — Training & Development](2026-03-28-hr-module9-training-development-design.md)

---

## Build Order Overview

```
Module 10 — Disciplinary & Offboarding (~60 files)
  Tasks 1-2:   Migrations (8 tables)
  Tasks 3-4:   Models (8 models + factories)
  Task 5:      Seeder (default exit checklist items, letter templates)
  Tasks 6-12:  Controllers + Form Requests (14 controllers)
  Task 13:     Routes
  Task 14:     Feature Tests

Module 9 — Training & Development (~40 files)
  Tasks 15-16: Migrations (6 tables)
  Tasks 17-18: Models (6 models + factories)
  Task 19:     Seeder (sample certification types)
  Tasks 20-24: Controllers + Form Requests (10 controllers)
  Task 25:     Routes
  Task 26:     Feature Tests

React Frontend (~45 files)
  Task 27:     API client functions (~70 new functions)
  Tasks 28-33: Disciplinary & Offboarding pages (11 pages)
  Tasks 34-37: Training pages (8 pages)
  Tasks 38-39: Employee self-service pages (4 pages)
  Task 40:     Router update (App.jsx)

Integration
  Task 41:     Integration tests
  Task 42:     Final verification
```

---

## Task 1: Create Disciplinary & Offboarding Migrations (Part 1)

**Files:**
- Create: `database/migrations/2026_03_28_200001_create_letter_templates_table.php`
- Create: `database/migrations/2026_03_28_200002_create_disciplinary_actions_table.php`
- Create: `database/migrations/2026_03_28_200003_create_disciplinary_inquiries_table.php`
- Create: `database/migrations/2026_03_28_200004_create_resignation_requests_table.php`

**Step 1: Generate migrations**

```bash
php artisan make:migration create_letter_templates_table --no-interaction
php artisan make:migration create_disciplinary_actions_table --no-interaction
php artisan make:migration create_disciplinary_inquiries_table --no-interaction
php artisan make:migration create_resignation_requests_table --no-interaction
```

**Step 2: Implement letter_templates migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('letter_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type'); // verbal_warning, first_written, second_written, show_cause, termination, offer_letter, resignation_acceptance
            $table->text('content'); // HTML template with {{placeholders}}
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('letter_templates');
    }
};
```

**Step 3: Implement disciplinary_actions migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('disciplinary_actions', function (Blueprint $table) {
            $table->id();
            $table->string('reference_number')->unique();
            $table->foreignId('employee_id')->constrained('employees');
            $table->string('type'); // verbal_warning, first_written, second_written, show_cause, suspension, termination
            $table->text('reason');
            $table->date('incident_date');
            $table->date('issued_date')->nullable();
            $table->foreignId('issued_by')->constrained('employees');
            $table->boolean('response_required')->default(false);
            $table->date('response_deadline')->nullable();
            $table->text('employee_response')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->text('outcome')->nullable();
            $table->string('letter_pdf_path')->nullable();
            $table->string('status')->default('draft'); // draft, issued, pending_response, responded, closed
            $table->foreignId('previous_action_id')->nullable()->constrained('disciplinary_actions');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('disciplinary_actions');
    }
};
```

**Step 4: Implement disciplinary_inquiries migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('disciplinary_inquiries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('disciplinary_action_id')->constrained('disciplinary_actions');
            $table->date('hearing_date');
            $table->time('hearing_time');
            $table->string('location');
            $table->json('panel_members'); // Array of employee IDs
            $table->text('minutes')->nullable();
            $table->text('findings')->nullable();
            $table->string('decision')->nullable(); // guilty, not_guilty, partially_guilty
            $table->text('penalty')->nullable();
            $table->string('status')->default('scheduled'); // scheduled, completed, postponed, cancelled
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('disciplinary_inquiries');
    }
};
```

**Step 5: Implement resignation_requests migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resignation_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees');
            $table->date('submitted_date');
            $table->text('reason');
            $table->integer('notice_period_days');
            $table->date('last_working_date'); // Calculated: submitted + notice period
            $table->date('requested_last_date')->nullable(); // If employee requests early release
            $table->string('status')->default('pending'); // pending, approved, rejected, withdrawn, completed
            $table->foreignId('approved_by')->nullable()->constrained('employees');
            $table->timestamp('approved_at')->nullable();
            $table->date('final_last_date')->nullable(); // Actual approved last date
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resignation_requests');
    }
};
```

**Step 6: Run migrations**

```bash
php artisan migrate
```

**Step 7: Commit**

```bash
git add database/migrations/
git commit -m "feat(hr): add disciplinary & offboarding migrations (part 1) — letter_templates, disciplinary_actions, inquiries, resignations"
```

---

## Task 2: Create Disciplinary & Offboarding Migrations (Part 2)

**Files:**
- Create: `database/migrations/2026_03_28_200005_create_exit_checklists_table.php`
- Create: `database/migrations/2026_03_28_200006_create_exit_checklist_items_table.php`
- Create: `database/migrations/2026_03_28_200007_create_exit_interviews_table.php`
- Create: `database/migrations/2026_03_28_200008_create_final_settlements_table.php`

**Step 1: Generate migrations**

```bash
php artisan make:migration create_exit_checklists_table --no-interaction
php artisan make:migration create_exit_checklist_items_table --no-interaction
php artisan make:migration create_exit_interviews_table --no-interaction
php artisan make:migration create_final_settlements_table --no-interaction
```

**Step 2: Implement exit_checklists migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exit_checklists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees');
            $table->foreignId('resignation_request_id')->nullable()->constrained('resignation_requests');
            $table->string('status')->default('in_progress'); // in_progress, completed
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exit_checklists');
    }
};
```

**Step 3: Implement exit_checklist_items migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exit_checklist_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exit_checklist_id')->constrained('exit_checklists')->cascadeOnDelete();
            $table->string('title');
            $table->string('category'); // asset_return, system_access, documentation, clearance, other
            $table->foreignId('assigned_to')->nullable()->constrained('employees');
            $table->string('status')->default('pending'); // pending, completed, not_applicable
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users');
            $table->text('notes')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exit_checklist_items');
    }
};
```

**Step 4: Implement exit_interviews migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exit_interviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees');
            $table->foreignId('conducted_by')->constrained('employees');
            $table->date('interview_date');
            $table->string('reason_for_leaving'); // better_opportunity, salary, work_environment, personal, relocation, career_change, management, other
            $table->integer('overall_satisfaction'); // 1-5
            $table->boolean('would_recommend');
            $table->text('feedback')->nullable();
            $table->text('improvements')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exit_interviews');
    }
};
```

**Step 5: Implement final_settlements migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('final_settlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees');
            $table->foreignId('resignation_request_id')->nullable()->constrained('resignation_requests');
            $table->decimal('prorated_salary', 10, 2)->default(0);
            $table->decimal('leave_encashment', 10, 2)->default(0);
            $table->decimal('leave_encashment_days', 5, 1)->default(0);
            $table->decimal('other_earnings', 10, 2)->default(0);
            $table->decimal('other_deductions', 10, 2)->default(0);
            $table->decimal('epf_employee', 8, 2)->default(0);
            $table->decimal('epf_employer', 8, 2)->default(0);
            $table->decimal('socso_employee', 8, 2)->default(0);
            $table->decimal('eis_employee', 8, 2)->default(0);
            $table->decimal('pcb_amount', 8, 2)->default(0);
            $table->decimal('total_gross', 10, 2)->default(0);
            $table->decimal('total_deductions', 10, 2)->default(0);
            $table->decimal('net_amount', 10, 2)->default(0);
            $table->string('status')->default('draft'); // draft, calculated, approved, paid
            $table->text('notes')->nullable();
            $table->string('pdf_path')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('final_settlements');
    }
};
```

**Step 6: Run migrations**

```bash
php artisan migrate
```

**Step 7: Commit**

```bash
git add database/migrations/
git commit -m "feat(hr): add disciplinary & offboarding migrations (part 2) — exit checklists, interviews, final settlements"
```

---

## Task 3: Create Disciplinary & Offboarding Models + Factories (Part 1)

**Files:**
- Create: `app/Models/LetterTemplate.php`
- Create: `app/Models/DisciplinaryAction.php`
- Create: `app/Models/DisciplinaryInquiry.php`
- Create: `app/Models/ResignationRequest.php`
- Create: `database/factories/LetterTemplateFactory.php`
- Create: `database/factories/DisciplinaryActionFactory.php`
- Create: `database/factories/DisciplinaryInquiryFactory.php`
- Create: `database/factories/ResignationRequestFactory.php`

**Step 1: Generate models with factories**

```bash
php artisan make:model LetterTemplate -f --no-interaction
php artisan make:model DisciplinaryAction -f --no-interaction
php artisan make:model DisciplinaryInquiry -f --no-interaction
php artisan make:model ResignationRequest -f --no-interaction
```

**Step 2: Implement LetterTemplate model**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LetterTemplate extends Model
{
    /** @use HasFactory<\Database\Factories\LetterTemplateFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'type',
        'content',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    /**
     * Render template with placeholder values.
     */
    public function render(array $data): string
    {
        $content = $this->content;

        foreach ($data as $key => $value) {
            $content = str_replace("{{{$key}}}", (string) $value, $content);
        }

        return $content;
    }
}
```

**Step 3: Implement DisciplinaryAction model**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class DisciplinaryAction extends Model
{
    /** @use HasFactory<\Database\Factories\DisciplinaryActionFactory> */
    use HasFactory;

    protected $fillable = [
        'reference_number',
        'employee_id',
        'type',
        'reason',
        'incident_date',
        'issued_date',
        'issued_by',
        'response_required',
        'response_deadline',
        'employee_response',
        'responded_at',
        'outcome',
        'letter_pdf_path',
        'status',
        'previous_action_id',
    ];

    protected function casts(): array
    {
        return [
            'incident_date' => 'date',
            'issued_date' => 'date',
            'response_required' => 'boolean',
            'response_deadline' => 'date',
            'responded_at' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'issued_by');
    }

    public function previousAction(): BelongsTo
    {
        return $this->belongsTo(DisciplinaryAction::class, 'previous_action_id');
    }

    public function inquiry(): HasOne
    {
        return $this->hasOne(DisciplinaryInquiry::class);
    }

    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('status', 'draft');
    }

    public function scopeIssued(Builder $query): Builder
    {
        return $query->where('status', 'issued');
    }

    public function scopePendingResponse(Builder $query): Builder
    {
        return $query->where('status', 'pending_response');
    }

    /**
     * Generate reference number: DA-YYYYMM-0001
     */
    public static function generateReferenceNumber(): string
    {
        $prefix = 'DA-' . now()->format('Ym') . '-';
        $lastAction = static::where('reference_number', 'like', $prefix . '%')
            ->orderByDesc('reference_number')
            ->first();

        if ($lastAction) {
            $lastNumber = (int) substr($lastAction->reference_number, -4);
            $nextNumber = $lastNumber + 1;
        } else {
            $nextNumber = 1;
        }

        return $prefix . str_pad((string) $nextNumber, 4, '0', STR_PAD_LEFT);
    }
}
```

**Step 4: Implement DisciplinaryInquiry model**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DisciplinaryInquiry extends Model
{
    /** @use HasFactory<\Database\Factories\DisciplinaryInquiryFactory> */
    use HasFactory;

    protected $fillable = [
        'disciplinary_action_id',
        'hearing_date',
        'hearing_time',
        'location',
        'panel_members',
        'minutes',
        'findings',
        'decision',
        'penalty',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'hearing_date' => 'date',
            'panel_members' => 'array',
        ];
    }

    public function disciplinaryAction(): BelongsTo
    {
        return $this->belongsTo(DisciplinaryAction::class);
    }
}
```

**Step 5: Implement ResignationRequest model**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ResignationRequest extends Model
{
    /** @use HasFactory<\Database\Factories\ResignationRequestFactory> */
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'submitted_date',
        'reason',
        'notice_period_days',
        'last_working_date',
        'requested_last_date',
        'status',
        'approved_by',
        'approved_at',
        'final_last_date',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'submitted_date' => 'date',
            'last_working_date' => 'date',
            'requested_last_date' => 'date',
            'final_last_date' => 'date',
            'approved_at' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'approved_by');
    }

    public function exitChecklist(): HasOne
    {
        return $this->hasOne(ExitChecklist::class);
    }

    public function finalSettlement(): HasOne
    {
        return $this->hasOne(FinalSettlement::class);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', 'approved');
    }

    /**
     * Calculate notice period based on Malaysian Employment Act 1955.
     * Full-time < 2 years: 30 days
     * Full-time 2-5 years: 60 days
     * Full-time > 5 years: 90 days
     * Probation: 14 days
     * Contract: per contract terms (default 30 days)
     */
    public static function calculateNoticePeriod(Employee $employee): int
    {
        if ($employee->employment_type === 'probation') {
            return 14;
        }

        if (in_array($employee->employment_type, ['contract', 'intern'])) {
            return 30;
        }

        $yearsOfService = $employee->join_date->diffInYears(now());

        if ($yearsOfService < 2) {
            return 30;
        } elseif ($yearsOfService <= 5) {
            return 60;
        } else {
            return 90;
        }
    }
}
```

**Step 6: Implement factories**

```php
// database/factories/LetterTemplateFactory.php
<?php

namespace Database\Factories;

use App\Models\LetterTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<LetterTemplate> */
class LetterTemplateFactory extends Factory
{
    protected $model = LetterTemplate::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->sentence(3),
            'type' => $this->faker->randomElement(['verbal_warning', 'first_written', 'second_written', 'show_cause', 'termination', 'offer_letter', 'resignation_acceptance']),
            'content' => '<p>Dear {{employee_name}},</p><p>{{reason}}</p>',
            'is_active' => true,
        ];
    }
}
```

```php
// database/factories/DisciplinaryActionFactory.php
<?php

namespace Database\Factories;

use App\Models\DisciplinaryAction;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<DisciplinaryAction> */
class DisciplinaryActionFactory extends Factory
{
    protected $model = DisciplinaryAction::class;

    public function definition(): array
    {
        return [
            'reference_number' => DisciplinaryAction::generateReferenceNumber(),
            'employee_id' => Employee::factory(),
            'type' => $this->faker->randomElement(['verbal_warning', 'first_written', 'second_written', 'show_cause']),
            'reason' => $this->faker->paragraph(),
            'incident_date' => $this->faker->dateTimeBetween('-30 days', 'now'),
            'issued_date' => now(),
            'issued_by' => Employee::factory(),
            'response_required' => false,
            'status' => 'draft',
        ];
    }

    public function issued(): static
    {
        return $this->state(fn () => [
            'status' => 'issued',
            'issued_date' => now(),
        ]);
    }

    public function showCause(): static
    {
        return $this->state(fn () => [
            'type' => 'show_cause',
            'response_required' => true,
            'response_deadline' => now()->addDays(7),
            'status' => 'pending_response',
        ]);
    }
}
```

```php
// database/factories/DisciplinaryInquiryFactory.php
<?php

namespace Database\Factories;

use App\Models\DisciplinaryAction;
use App\Models\DisciplinaryInquiry;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<DisciplinaryInquiry> */
class DisciplinaryInquiryFactory extends Factory
{
    protected $model = DisciplinaryInquiry::class;

    public function definition(): array
    {
        return [
            'disciplinary_action_id' => DisciplinaryAction::factory(),
            'hearing_date' => $this->faker->dateTimeBetween('now', '+30 days'),
            'hearing_time' => '10:00',
            'location' => $this->faker->city() . ' Conference Room',
            'panel_members' => [1, 2, 3],
            'status' => 'scheduled',
        ];
    }

    public function completed(): static
    {
        return $this->state(fn () => [
            'status' => 'completed',
            'minutes' => $this->faker->paragraphs(3, true),
            'findings' => $this->faker->paragraph(),
            'decision' => $this->faker->randomElement(['guilty', 'not_guilty', 'partially_guilty']),
        ]);
    }
}
```

```php
// database/factories/ResignationRequestFactory.php
<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\ResignationRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ResignationRequest> */
class ResignationRequestFactory extends Factory
{
    protected $model = ResignationRequest::class;

    public function definition(): array
    {
        $submittedDate = now();
        $noticePeriod = 30;

        return [
            'employee_id' => Employee::factory(),
            'submitted_date' => $submittedDate,
            'reason' => $this->faker->paragraph(),
            'notice_period_days' => $noticePeriod,
            'last_working_date' => $submittedDate->copy()->addDays($noticePeriod),
            'status' => 'pending',
        ];
    }

    public function approved(): static
    {
        return $this->state(fn () => [
            'status' => 'approved',
            'approved_by' => Employee::factory(),
            'approved_at' => now(),
            'final_last_date' => now()->addDays(30),
        ]);
    }
}
```

**Step 7: Commit**

```bash
git add app/Models/ database/factories/
git commit -m "feat(hr): add disciplinary & offboarding models + factories (part 1)"
```

---

## Task 4: Create Disciplinary & Offboarding Models + Factories (Part 2)

**Files:**
- Create: `app/Models/ExitChecklist.php`
- Create: `app/Models/ExitChecklistItem.php`
- Create: `app/Models/ExitInterview.php`
- Create: `app/Models/FinalSettlement.php`
- Create: `database/factories/ExitChecklistFactory.php`
- Create: `database/factories/ExitChecklistItemFactory.php`
- Create: `database/factories/ExitInterviewFactory.php`
- Create: `database/factories/FinalSettlementFactory.php`

**Step 1: Generate models with factories**

```bash
php artisan make:model ExitChecklist -f --no-interaction
php artisan make:model ExitChecklistItem -f --no-interaction
php artisan make:model ExitInterview -f --no-interaction
php artisan make:model FinalSettlement -f --no-interaction
```

**Step 2: Implement ExitChecklist model**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExitChecklist extends Model
{
    /** @use HasFactory<\Database\Factories\ExitChecklistFactory> */
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'resignation_request_id',
        'status',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'completed_at' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function resignationRequest(): BelongsTo
    {
        return $this->belongsTo(ResignationRequest::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ExitChecklistItem::class)->orderBy('sort_order');
    }

    /**
     * Create default exit checklist items from the standard list.
     */
    public function createDefaultItems(): void
    {
        $defaults = [
            ['title' => 'Return laptop/PC', 'category' => 'asset_return', 'sort_order' => 1],
            ['title' => 'Return access card', 'category' => 'asset_return', 'sort_order' => 2],
            ['title' => 'Return office keys', 'category' => 'asset_return', 'sort_order' => 3],
            ['title' => 'Return uniform', 'category' => 'asset_return', 'sort_order' => 4],
            ['title' => 'Return company phone', 'category' => 'asset_return', 'sort_order' => 5],
            ['title' => 'Revoke email access', 'category' => 'system_access', 'sort_order' => 6],
            ['title' => 'Revoke system login', 'category' => 'system_access', 'sort_order' => 7],
            ['title' => 'Remove VPN access', 'category' => 'system_access', 'sort_order' => 8],
            ['title' => 'Handover documents', 'category' => 'documentation', 'sort_order' => 9],
            ['title' => 'Knowledge transfer session', 'category' => 'documentation', 'sort_order' => 10],
            ['title' => 'Return signed resignation acceptance', 'category' => 'documentation', 'sort_order' => 11],
            ['title' => 'Department head clearance', 'category' => 'clearance', 'sort_order' => 12],
            ['title' => 'Finance clearance (no outstanding)', 'category' => 'clearance', 'sort_order' => 13],
            ['title' => 'HR clearance', 'category' => 'clearance', 'sort_order' => 14],
        ];

        foreach ($defaults as $item) {
            $this->items()->create(array_merge($item, ['status' => 'pending']));
        }
    }

    /**
     * Add asset return items from employee's assigned assets (Module 6 integration).
     */
    public function addAssetReturnItems(): void
    {
        $assignments = AssetAssignment::where('employee_id', $this->employee_id)
            ->whereNull('returned_date')
            ->with('asset:id,name,asset_tag')
            ->get();

        $sortOrder = $this->items()->max('sort_order') ?? 0;

        foreach ($assignments as $assignment) {
            $sortOrder++;
            $this->items()->create([
                'title' => "Return {$assignment->asset->name} ({$assignment->asset->asset_tag})",
                'category' => 'asset_return',
                'status' => 'pending',
                'sort_order' => $sortOrder,
            ]);
        }
    }
}
```

**Step 3: Implement ExitChecklistItem model**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExitChecklistItem extends Model
{
    /** @use HasFactory<\Database\Factories\ExitChecklistItemFactory> */
    use HasFactory;

    protected $fillable = [
        'exit_checklist_id',
        'title',
        'category',
        'assigned_to',
        'status',
        'completed_at',
        'completed_by',
        'notes',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'completed_at' => 'datetime',
            'sort_order' => 'integer',
        ];
    }

    public function exitChecklist(): BelongsTo
    {
        return $this->belongsTo(ExitChecklist::class);
    }

    public function assignedEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'assigned_to');
    }

    public function completedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }
}
```

**Step 4: Implement ExitInterview model**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExitInterview extends Model
{
    /** @use HasFactory<\Database\Factories\ExitInterviewFactory> */
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'conducted_by',
        'interview_date',
        'reason_for_leaving',
        'overall_satisfaction',
        'would_recommend',
        'feedback',
        'improvements',
    ];

    protected function casts(): array
    {
        return [
            'interview_date' => 'date',
            'overall_satisfaction' => 'integer',
            'would_recommend' => 'boolean',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function conductor(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'conducted_by');
    }
}
```

**Step 5: Implement FinalSettlement model**

```php
<?php

namespace App\Models;

use App\Services\Hr\StatutoryCalculationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinalSettlement extends Model
{
    /** @use HasFactory<\Database\Factories\FinalSettlementFactory> */
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'resignation_request_id',
        'prorated_salary',
        'leave_encashment',
        'leave_encashment_days',
        'other_earnings',
        'other_deductions',
        'epf_employee',
        'epf_employer',
        'socso_employee',
        'eis_employee',
        'pcb_amount',
        'total_gross',
        'total_deductions',
        'net_amount',
        'status',
        'notes',
        'pdf_path',
        'approved_by',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'prorated_salary' => 'decimal:2',
            'leave_encashment' => 'decimal:2',
            'leave_encashment_days' => 'decimal:1',
            'other_earnings' => 'decimal:2',
            'other_deductions' => 'decimal:2',
            'epf_employee' => 'decimal:2',
            'epf_employer' => 'decimal:2',
            'socso_employee' => 'decimal:2',
            'eis_employee' => 'decimal:2',
            'pcb_amount' => 'decimal:2',
            'total_gross' => 'decimal:2',
            'total_deductions' => 'decimal:2',
            'net_amount' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function resignationRequest(): BelongsTo
    {
        return $this->belongsTo(ResignationRequest::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('status', 'draft');
    }

    /**
     * Calculate final settlement for an employee.
     *
     * @param  int  $employeeId
     * @param  string  $finalLastDate  (Y-m-d)
     */
    public static function calculate(int $employeeId, string $finalLastDate): self
    {
        $employee = Employee::with('salaries.salaryComponent')->findOrFail($employeeId);
        $statutory = app(StatutoryCalculationService::class);

        // 1. Get total monthly earnings (active salary components)
        $totalMonthly = EmployeeSalary::forEmployee($employeeId)
            ->active()
            ->sum('amount');

        $basicSalary = EmployeeSalary::forEmployee($employeeId)
            ->active()
            ->whereHas('salaryComponent', fn ($q) => $q->where('is_basic', true))
            ->sum('amount');

        // 2. Calculate prorated salary
        $finalDate = \Carbon\Carbon::parse($finalLastDate);
        $daysInMonth = $finalDate->daysInMonth;
        $daysWorked = $finalDate->day;
        $proratedSalary = ($totalMonthly / $daysInMonth) * $daysWorked;

        // 3. Calculate leave encashment (unused annual leave)
        $annualLeaveType = LeaveType::where('code', 'AL')->first();
        $unusedDays = 0;

        if ($annualLeaveType) {
            $balance = LeaveBalance::where('employee_id', $employeeId)
                ->where('leave_type_id', $annualLeaveType->id)
                ->where('year', now()->year)
                ->first();

            $unusedDays = $balance ? (float) $balance->available_days : 0;
        }

        $dailyRate = $basicSalary / 26; // Standard Malaysian divisor
        $leaveEncashment = $unusedDays * $dailyRate;

        // 4. Total gross
        $totalGross = $proratedSalary + $leaveEncashment;

        // 5. Statutory deductions on prorated earnings
        $epfEmployee = $statutory->calculateEpfEmployee($proratedSalary);
        $epfEmployer = $statutory->calculateEpfEmployer($proratedSalary);
        $socsoEmployee = $statutory->calculateSocsoEmployee($proratedSalary);
        $eisEmployee = $statutory->calculateEisEmployee($proratedSalary);
        $pcbAmount = 0; // PCB on final month typically handled separately

        // 6. Total deductions
        $totalDeductions = $epfEmployee + $socsoEmployee + $eisEmployee + $pcbAmount;

        // 7. Net amount
        $netAmount = $totalGross - $totalDeductions;

        return new self([
            'employee_id' => $employeeId,
            'prorated_salary' => round($proratedSalary, 2),
            'leave_encashment' => round($leaveEncashment, 2),
            'leave_encashment_days' => $unusedDays,
            'other_earnings' => 0,
            'other_deductions' => 0,
            'epf_employee' => $epfEmployee,
            'epf_employer' => $epfEmployer,
            'socso_employee' => $socsoEmployee,
            'eis_employee' => $eisEmployee,
            'pcb_amount' => $pcbAmount,
            'total_gross' => round($totalGross, 2),
            'total_deductions' => round($totalDeductions, 2),
            'net_amount' => round($netAmount, 2),
            'status' => 'calculated',
        ]);
    }
}
```

**Step 6: Implement factories**

```php
// database/factories/ExitChecklistFactory.php
<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\ExitChecklist;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ExitChecklist> */
class ExitChecklistFactory extends Factory
{
    protected $model = ExitChecklist::class;

    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'status' => 'in_progress',
        ];
    }
}
```

```php
// database/factories/ExitChecklistItemFactory.php
<?php

namespace Database\Factories;

use App\Models\ExitChecklist;
use App\Models\ExitChecklistItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ExitChecklistItem> */
class ExitChecklistItemFactory extends Factory
{
    protected $model = ExitChecklistItem::class;

    public function definition(): array
    {
        return [
            'exit_checklist_id' => ExitChecklist::factory(),
            'title' => $this->faker->sentence(3),
            'category' => $this->faker->randomElement(['asset_return', 'system_access', 'documentation', 'clearance']),
            'status' => 'pending',
            'sort_order' => $this->faker->numberBetween(1, 20),
        ];
    }
}
```

```php
// database/factories/ExitInterviewFactory.php
<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\ExitInterview;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ExitInterview> */
class ExitInterviewFactory extends Factory
{
    protected $model = ExitInterview::class;

    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'conducted_by' => Employee::factory(),
            'interview_date' => $this->faker->date(),
            'reason_for_leaving' => $this->faker->randomElement(['better_opportunity', 'salary', 'work_environment', 'personal', 'relocation', 'career_change', 'management', 'other']),
            'overall_satisfaction' => $this->faker->numberBetween(1, 5),
            'would_recommend' => $this->faker->boolean(),
            'feedback' => $this->faker->paragraph(),
            'improvements' => $this->faker->paragraph(),
        ];
    }
}
```

```php
// database/factories/FinalSettlementFactory.php
<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\FinalSettlement;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<FinalSettlement> */
class FinalSettlementFactory extends Factory
{
    protected $model = FinalSettlement::class;

    public function definition(): array
    {
        $prorated = $this->faker->randomFloat(2, 1000, 5000);
        $encashment = $this->faker->randomFloat(2, 0, 2000);
        $gross = $prorated + $encashment;
        $deductions = $gross * 0.15;

        return [
            'employee_id' => Employee::factory(),
            'prorated_salary' => $prorated,
            'leave_encashment' => $encashment,
            'leave_encashment_days' => $this->faker->randomFloat(1, 0, 15),
            'total_gross' => $gross,
            'total_deductions' => round($deductions, 2),
            'net_amount' => round($gross - $deductions, 2),
            'status' => 'draft',
        ];
    }

    public function calculated(): static
    {
        return $this->state(fn () => ['status' => 'calculated']);
    }
}
```

**Step 7: Commit**

```bash
git add app/Models/ database/factories/
git commit -m "feat(hr): add exit checklist, interview, final settlement models + factories"
```

---

## Task 5: Create Seeder for Default Letter Templates

**Files:**
- Create: `database/seeders/LetterTemplateSeeder.php`

**Step 1: Generate seeder**

```bash
php artisan make:seeder LetterTemplateSeeder --no-interaction
```

**Step 2: Implement LetterTemplateSeeder**

```php
<?php

namespace Database\Seeders;

use App\Models\LetterTemplate;
use Illuminate\Database\Seeder;

class LetterTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            [
                'name' => 'Verbal Warning Letter',
                'type' => 'verbal_warning',
                'content' => '<div style="font-family: Arial, sans-serif; max-width: 700px; margin: 0 auto;">
<p style="text-align: right;">Date: {{issued_date}}</p>
<p><strong>VERBAL WARNING</strong></p>
<p>To: {{employee_name}}<br>Employee ID: {{employee_id}}<br>Position: {{position}}<br>Department: {{department}}</p>
<p>Dear {{employee_name}},</p>
<p>This letter serves as a formal verbal warning regarding the following matter:</p>
<p><strong>Incident Date:</strong> {{incident_date}}</p>
<p><strong>Details:</strong> {{reason}}</p>
<p>This is a verbal warning and is the first step in our disciplinary process. We expect immediate improvement in your conduct/performance. Failure to improve may result in further disciplinary action.</p>
<p>Yours sincerely,<br>{{company_name}} HR Department</p>
</div>',
            ],
            [
                'name' => 'First Written Warning Letter',
                'type' => 'first_written',
                'content' => '<div style="font-family: Arial, sans-serif; max-width: 700px; margin: 0 auto;">
<p style="text-align: right;">Date: {{issued_date}}</p>
<p><strong>FIRST WRITTEN WARNING</strong></p>
<p>To: {{employee_name}}<br>Employee ID: {{employee_id}}<br>Position: {{position}}<br>Department: {{department}}</p>
<p>Dear {{employee_name}},</p>
<p>Further to the verbal warning previously issued, this letter serves as a formal first written warning regarding:</p>
<p><strong>Incident Date:</strong> {{incident_date}}</p>
<p><strong>Details:</strong> {{reason}}</p>
<p>This warning will be placed in your personnel file. Continued failure to improve may lead to further disciplinary action up to and including termination of employment.</p>
<p>Yours sincerely,<br>{{company_name}} HR Department</p>
</div>',
            ],
            [
                'name' => 'Show Cause Letter',
                'type' => 'show_cause',
                'content' => '<div style="font-family: Arial, sans-serif; max-width: 700px; margin: 0 auto;">
<p style="text-align: right;">Date: {{issued_date}}</p>
<p><strong>SHOW CAUSE LETTER</strong></p>
<p>To: {{employee_name}}<br>Employee ID: {{employee_id}}<br>Position: {{position}}<br>Department: {{department}}</p>
<p>Dear {{employee_name}},</p>
<p>You are hereby required to show cause as to why disciplinary action should not be taken against you for the following:</p>
<p><strong>Incident Date:</strong> {{incident_date}}</p>
<p><strong>Details:</strong> {{reason}}</p>
<p>You are required to submit your written explanation by <strong>{{response_deadline}}</strong>. Failure to respond by the deadline will be taken as an admission of the allegations and the company will proceed with appropriate disciplinary action.</p>
<p>Yours sincerely,<br>{{company_name}} HR Department</p>
</div>',
            ],
            [
                'name' => 'Termination Letter',
                'type' => 'termination',
                'content' => '<div style="font-family: Arial, sans-serif; max-width: 700px; margin: 0 auto;">
<p style="text-align: right;">Date: {{issued_date}}</p>
<p><strong>TERMINATION OF EMPLOYMENT</strong></p>
<p>To: {{employee_name}}<br>Employee ID: {{employee_id}}<br>Position: {{position}}<br>Department: {{department}}</p>
<p>Dear {{employee_name}},</p>
<p>Following the domestic inquiry conducted and after careful consideration, the company has decided to terminate your employment effective immediately.</p>
<p><strong>Reason:</strong> {{reason}}</p>
<p>You are required to return all company property and complete the exit process. Your final settlement will be calculated and paid according to the Employment Act 1955.</p>
<p>Yours sincerely,<br>{{company_name}} HR Department</p>
</div>',
            ],
            [
                'name' => 'Resignation Acceptance Letter',
                'type' => 'resignation_acceptance',
                'content' => '<div style="font-family: Arial, sans-serif; max-width: 700px; margin: 0 auto;">
<p style="text-align: right;">Date: {{issued_date}}</p>
<p><strong>ACCEPTANCE OF RESIGNATION</strong></p>
<p>To: {{employee_name}}<br>Employee ID: {{employee_id}}<br>Position: {{position}}<br>Department: {{department}}</p>
<p>Dear {{employee_name}},</p>
<p>We acknowledge receipt of your resignation letter dated {{incident_date}}. Your resignation has been accepted.</p>
<p>Your last working day will be <strong>{{response_deadline}}</strong>.</p>
<p>Please ensure all company property is returned and handover documentation is completed before your last day. Your final settlement will be processed accordingly.</p>
<p>We wish you all the best in your future endeavors.</p>
<p>Yours sincerely,<br>{{company_name}} HR Department</p>
</div>',
            ],
        ];

        foreach ($templates as $template) {
            LetterTemplate::updateOrCreate(
                ['name' => $template['name']],
                array_merge($template, ['is_active' => true])
            );
        }
    }
}
```

**Step 3: Run seeder**

```bash
php artisan db:seed --class=LetterTemplateSeeder
```

**Step 4: Commit**

```bash
git add database/seeders/LetterTemplateSeeder.php
git commit -m "feat(hr): add letter template seeder with Malaysian-standard templates"
```

---

## Task 6: Create Disciplinary Dashboard & Actions Controllers

**Files:**
- Create: `app/Http/Controllers/Api/Hr/HrDisciplinaryDashboardController.php`
- Create: `app/Http/Requests/Hr/StoreDisciplinaryActionRequest.php`
- Create: `app/Http/Controllers/Api/Hr/HrDisciplinaryActionController.php`

**Step 1: Generate files**

```bash
php artisan make:controller Api/Hr/HrDisciplinaryDashboardController --no-interaction
php artisan make:request Hr/StoreDisciplinaryActionRequest --no-interaction
php artisan make:controller Api/Hr/HrDisciplinaryActionController --no-interaction
```

**Step 2: Implement HrDisciplinaryDashboardController**

```php
<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use App\Models\DisciplinaryAction;
use Illuminate\Http\JsonResponse;

class HrDisciplinaryDashboardController extends Controller
{
    public function stats(): JsonResponse
    {
        return response()->json([
            'data' => [
                'active_cases' => DisciplinaryAction::whereNotIn('status', ['closed'])->count(),
                'warnings_this_month' => DisciplinaryAction::whereMonth('created_at', now()->month)
                    ->whereYear('created_at', now()->year)
                    ->count(),
                'pending_responses' => DisciplinaryAction::where('status', 'pending_response')->count(),
                'cases_by_type' => [
                    'verbal_warning' => DisciplinaryAction::where('type', 'verbal_warning')->count(),
                    'first_written' => DisciplinaryAction::where('type', 'first_written')->count(),
                    'second_written' => DisciplinaryAction::where('type', 'second_written')->count(),
                    'show_cause' => DisciplinaryAction::where('type', 'show_cause')->count(),
                    'suspension' => DisciplinaryAction::where('type', 'suspension')->count(),
                    'termination' => DisciplinaryAction::where('type', 'termination')->count(),
                ],
            ],
        ]);
    }
}
```

**Step 3: Implement StoreDisciplinaryActionRequest**

```php
<?php

namespace App\Http\Requests\Hr;

use Illuminate\Foundation\Http\FormRequest;

class StoreDisciplinaryActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->role === 'admin';
    }

    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'exists:employees,id'],
            'type' => ['required', 'in:verbal_warning,first_written,second_written,show_cause,suspension,termination'],
            'reason' => ['required', 'string'],
            'incident_date' => ['required', 'date'],
            'issued_date' => ['nullable', 'date'],
            'response_required' => ['boolean'],
            'response_deadline' => ['nullable', 'date', 'after:today'],
            'previous_action_id' => ['nullable', 'exists:disciplinary_actions,id'],
        ];
    }
}
```

**Step 4: Implement HrDisciplinaryActionController**

```php
<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\StoreDisciplinaryActionRequest;
use App\Models\DisciplinaryAction;
use App\Models\Employee;
use App\Models\LetterTemplate;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class HrDisciplinaryActionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = DisciplinaryAction::query()
            ->with(['employee:id,full_name,employee_id,department_id', 'employee.department:id,name', 'issuer:id,full_name']);

        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('reference_number', 'like', "%{$search}%")
                    ->orWhereHas('employee', fn ($q) => $q->where('full_name', 'like', "%{$search}%"));
            });
        }

        if ($type = $request->get('type')) {
            $query->where('type', $type);
        }

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        if ($employeeId = $request->get('employee_id')) {
            $query->where('employee_id', $employeeId);
        }

        $actions = $query->orderByDesc('created_at')->paginate($request->get('per_page', 15));

        return response()->json($actions);
    }

    public function store(StoreDisciplinaryActionRequest $request): JsonResponse
    {
        $validated = $request->validated();

        // Find the issuer employee record from the authenticated user
        $issuer = Employee::where('user_id', $request->user()->id)->first();

        $action = DisciplinaryAction::create(array_merge($validated, [
            'reference_number' => DisciplinaryAction::generateReferenceNumber(),
            'issued_by' => $issuer?->id ?? $validated['employee_id'],
            'status' => 'draft',
            'response_required' => $validated['type'] === 'show_cause' ? true : ($validated['response_required'] ?? false),
        ]));

        return response()->json([
            'message' => 'Disciplinary action created.',
            'data' => $action->load(['employee:id,full_name,employee_id', 'issuer:id,full_name']),
        ], 201);
    }

    public function show(DisciplinaryAction $disciplinaryAction): JsonResponse
    {
        return response()->json([
            'data' => $disciplinaryAction->load([
                'employee:id,full_name,employee_id,department_id,position_id',
                'employee.department:id,name',
                'employee.position:id,title',
                'issuer:id,full_name',
                'previousAction:id,reference_number,type,status',
                'inquiry',
            ]),
        ]);
    }

    public function update(Request $request, DisciplinaryAction $disciplinaryAction): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['sometimes', 'string'],
            'incident_date' => ['sometimes', 'date'],
            'response_deadline' => ['nullable', 'date'],
            'outcome' => ['nullable', 'string'],
        ]);

        $disciplinaryAction->update($validated);

        return response()->json([
            'message' => 'Disciplinary action updated.',
            'data' => $disciplinaryAction,
        ]);
    }

    public function issue(DisciplinaryAction $disciplinaryAction): JsonResponse
    {
        $disciplinaryAction->update([
            'status' => $disciplinaryAction->response_required ? 'pending_response' : 'issued',
            'issued_date' => now(),
        ]);

        return response()->json([
            'message' => 'Disciplinary action issued.',
            'data' => $disciplinaryAction,
        ]);
    }

    public function close(DisciplinaryAction $disciplinaryAction): JsonResponse
    {
        $disciplinaryAction->update(['status' => 'closed']);

        return response()->json([
            'message' => 'Case closed.',
            'data' => $disciplinaryAction,
        ]);
    }

    public function pdf(DisciplinaryAction $disciplinaryAction): \Illuminate\Http\Response
    {
        $disciplinaryAction->load(['employee.department', 'employee.position']);

        $template = LetterTemplate::active()
            ->ofType($disciplinaryAction->type)
            ->first();

        if (! $template) {
            abort(404, 'No active letter template found for this type.');
        }

        $html = $template->render([
            'employee_name' => $disciplinaryAction->employee->full_name,
            'employee_id' => $disciplinaryAction->employee->employee_id,
            'position' => $disciplinaryAction->employee->position?->title ?? 'N/A',
            'department' => $disciplinaryAction->employee->department?->name ?? 'N/A',
            'incident_date' => $disciplinaryAction->incident_date->format('d/m/Y'),
            'issued_date' => ($disciplinaryAction->issued_date ?? now())->format('d/m/Y'),
            'reason' => $disciplinaryAction->reason,
            'response_deadline' => $disciplinaryAction->response_deadline?->format('d/m/Y') ?? 'N/A',
            'company_name' => config('app.name'),
        ]);

        $pdf = Pdf::loadHTML($html)->setPaper('a4');

        $filename = "disciplinary_{$disciplinaryAction->reference_number}.pdf";
        $path = "hr/disciplinary/{$filename}";
        Storage::disk('public')->put($path, $pdf->output());

        $disciplinaryAction->update(['letter_pdf_path' => $path]);

        return $pdf->download($filename);
    }

    public function employeeHistory(int $employeeId): JsonResponse
    {
        $actions = DisciplinaryAction::where('employee_id', $employeeId)
            ->with(['issuer:id,full_name', 'inquiry'])
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['data' => $actions]);
    }
}
```

**Step 5: Commit**

```bash
git add app/Http/Controllers/Api/Hr/ app/Http/Requests/Hr/
git commit -m "feat(hr): add disciplinary dashboard + action controllers with PDF generation"
```

---

## Task 7: Create Disciplinary Inquiry Controller

**Files:**
- Create: `app/Http/Controllers/Api/Hr/HrDisciplinaryInquiryController.php`

**Step 1: Generate controller**

```bash
php artisan make:controller Api/Hr/HrDisciplinaryInquiryController --no-interaction
```

**Step 2: Implement HrDisciplinaryInquiryController**

```php
<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use App\Models\DisciplinaryInquiry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HrDisciplinaryInquiryController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'disciplinary_action_id' => ['required', 'exists:disciplinary_actions,id'],
            'hearing_date' => ['required', 'date'],
            'hearing_time' => ['required', 'date_format:H:i'],
            'location' => ['required', 'string', 'max:255'],
            'panel_members' => ['required', 'array', 'min:1'],
            'panel_members.*' => ['integer', 'exists:employees,id'],
        ]);

        $inquiry = DisciplinaryInquiry::create(array_merge($validated, [
            'status' => 'scheduled',
        ]));

        return response()->json([
            'message' => 'Domestic inquiry scheduled.',
            'data' => $inquiry,
        ], 201);
    }

    public function show(DisciplinaryInquiry $disciplinaryInquiry): JsonResponse
    {
        return response()->json([
            'data' => $disciplinaryInquiry->load([
                'disciplinaryAction.employee:id,full_name,employee_id',
            ]),
        ]);
    }

    public function update(Request $request, DisciplinaryInquiry $disciplinaryInquiry): JsonResponse
    {
        $validated = $request->validate([
            'hearing_date' => ['sometimes', 'date'],
            'hearing_time' => ['sometimes', 'date_format:H:i'],
            'location' => ['sometimes', 'string', 'max:255'],
            'panel_members' => ['sometimes', 'array'],
            'panel_members.*' => ['integer', 'exists:employees,id'],
            'minutes' => ['nullable', 'string'],
            'findings' => ['nullable', 'string'],
        ]);

        $disciplinaryInquiry->update($validated);

        return response()->json([
            'message' => 'Inquiry updated.',
            'data' => $disciplinaryInquiry,
        ]);
    }

    public function complete(Request $request, DisciplinaryInquiry $disciplinaryInquiry): JsonResponse
    {
        $validated = $request->validate([
            'decision' => ['required', 'in:guilty,not_guilty,partially_guilty'],
            'findings' => ['required', 'string'],
            'penalty' => ['nullable', 'string'],
        ]);

        return DB::transaction(function () use ($validated, $disciplinaryInquiry) {
            $disciplinaryInquiry->update(array_merge($validated, [
                'status' => 'completed',
            ]));

            // Update the parent disciplinary action based on decision
            $action = $disciplinaryInquiry->disciplinaryAction;
            if ($validated['decision'] === 'not_guilty') {
                $action->update([
                    'outcome' => 'Acquitted after domestic inquiry.',
                    'status' => 'closed',
                ]);
            } else {
                $action->update([
                    'outcome' => "Decision: {$validated['decision']}. Penalty: " . ($validated['penalty'] ?? 'N/A'),
                ]);
            }

            return response()->json([
                'message' => 'Inquiry completed.',
                'data' => $disciplinaryInquiry->fresh('disciplinaryAction'),
            ]);
        });
    }
}
```

**Step 3: Commit**

```bash
git add app/Http/Controllers/Api/Hr/HrDisciplinaryInquiryController.php
git commit -m "feat(hr): add disciplinary inquiry controller with decision handling"
```

---

## Task 8: Create Resignation & Exit Checklist Controllers

**Files:**
- Create: `app/Http/Controllers/Api/Hr/HrResignationController.php`
- Create: `app/Http/Controllers/Api/Hr/HrExitChecklistController.php`

**Step 1: Generate controllers**

```bash
php artisan make:controller Api/Hr/HrResignationController --no-interaction
php artisan make:controller Api/Hr/HrExitChecklistController --no-interaction
```

**Step 2: Implement HrResignationController**

```php
<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\ExitChecklist;
use App\Models\ResignationRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HrResignationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = ResignationRequest::query()
            ->with(['employee:id,full_name,employee_id,department_id', 'employee.department:id,name']);

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        $resignations = $query->orderByDesc('created_at')->paginate($request->get('per_page', 15));

        return response()->json($resignations);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'employee_id' => ['required', 'exists:employees,id'],
            'submitted_date' => ['required', 'date'],
            'reason' => ['required', 'string'],
            'requested_last_date' => ['nullable', 'date'],
        ]);

        $employee = Employee::findOrFail($validated['employee_id']);
        $noticePeriod = ResignationRequest::calculateNoticePeriod($employee);
        $submittedDate = \Carbon\Carbon::parse($validated['submitted_date']);

        $resignation = ResignationRequest::create(array_merge($validated, [
            'notice_period_days' => $noticePeriod,
            'last_working_date' => $submittedDate->copy()->addDays($noticePeriod),
            'status' => 'pending',
        ]));

        return response()->json([
            'message' => 'Resignation request submitted.',
            'data' => $resignation->load('employee:id,full_name,employee_id'),
        ], 201);
    }

    public function show(ResignationRequest $resignationRequest): JsonResponse
    {
        return response()->json([
            'data' => $resignationRequest->load([
                'employee:id,full_name,employee_id,department_id,position_id,employment_type,join_date',
                'employee.department:id,name',
                'employee.position:id,title',
                'approver:id,full_name',
                'exitChecklist.items',
                'finalSettlement',
            ]),
        ]);
    }

    public function approve(Request $request, ResignationRequest $resignationRequest): JsonResponse
    {
        $validated = $request->validate([
            'final_last_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        $approver = Employee::where('user_id', $request->user()->id)->first();

        return DB::transaction(function () use ($validated, $resignationRequest, $approver) {
            $resignationRequest->update([
                'status' => 'approved',
                'approved_by' => $approver?->id,
                'approved_at' => now(),
                'final_last_date' => $validated['final_last_date'] ?? $resignationRequest->last_working_date,
                'notes' => $validated['notes'] ?? $resignationRequest->notes,
            ]);

            // Auto-create exit checklist
            $checklist = ExitChecklist::create([
                'employee_id' => $resignationRequest->employee_id,
                'resignation_request_id' => $resignationRequest->id,
                'status' => 'in_progress',
            ]);

            $checklist->createDefaultItems();
            $checklist->addAssetReturnItems();

            return response()->json([
                'message' => 'Resignation approved. Exit checklist created.',
                'data' => $resignationRequest->fresh(['exitChecklist.items']),
            ]);
        });
    }

    public function reject(Request $request, ResignationRequest $resignationRequest): JsonResponse
    {
        $validated = $request->validate([
            'notes' => ['nullable', 'string'],
        ]);

        $resignationRequest->update([
            'status' => 'rejected',
            'notes' => $validated['notes'] ?? null,
        ]);

        return response()->json([
            'message' => 'Resignation rejected.',
            'data' => $resignationRequest,
        ]);
    }

    public function complete(Request $request, ResignationRequest $resignationRequest): JsonResponse
    {
        return DB::transaction(function () use ($resignationRequest) {
            $resignationRequest->update(['status' => 'completed']);

            // Update employee status to resigned
            $resignationRequest->employee->update([
                'status' => 'resigned',
                'resignation_date' => $resignationRequest->submitted_date,
                'last_working_date' => $resignationRequest->final_last_date ?? $resignationRequest->last_working_date,
            ]);

            return response()->json([
                'message' => 'Offboarding completed. Employee status updated to resigned.',
                'data' => $resignationRequest,
            ]);
        });
    }
}
```

**Step 3: Implement HrExitChecklistController**

```php
<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use App\Models\ExitChecklist;
use App\Models\ExitChecklistItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HrExitChecklistController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $checklists = ExitChecklist::query()
            ->with(['employee:id,full_name,employee_id,department_id', 'employee.department:id,name'])
            ->withCount([
                'items as total_items',
                'items as completed_items' => fn ($q) => $q->where('status', 'completed'),
            ])
            ->orderByDesc('created_at')
            ->paginate($request->get('per_page', 15));

        return response()->json($checklists);
    }

    public function createForEmployee(int $employeeId): JsonResponse
    {
        $checklist = ExitChecklist::create([
            'employee_id' => $employeeId,
            'status' => 'in_progress',
        ]);

        $checklist->createDefaultItems();
        $checklist->addAssetReturnItems();

        return response()->json([
            'message' => 'Exit checklist created.',
            'data' => $checklist->load('items'),
        ], 201);
    }

    public function show(ExitChecklist $exitChecklist): JsonResponse
    {
        return response()->json([
            'data' => $exitChecklist->load([
                'employee:id,full_name,employee_id,department_id',
                'employee.department:id,name',
                'resignationRequest',
                'items.assignedEmployee:id,full_name',
                'items.completedByUser:id,name',
            ]),
        ]);
    }

    public function updateItem(Request $request, ExitChecklist $exitChecklist, ExitChecklistItem $item): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'in:pending,completed,not_applicable'],
            'notes' => ['nullable', 'string'],
        ]);

        $item->update(array_merge($validated, [
            'completed_at' => $validated['status'] === 'completed' ? now() : null,
            'completed_by' => $validated['status'] === 'completed' ? $request->user()->id : null,
        ]));

        // Check if all items completed
        $allCompleted = $exitChecklist->items()
            ->whereNotIn('status', ['completed', 'not_applicable'])
            ->count() === 0;

        if ($allCompleted) {
            $exitChecklist->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);
        }

        return response()->json([
            'message' => 'Checklist item updated.',
            'data' => $item,
            'checklist_completed' => $allCompleted,
        ]);
    }
}
```

**Step 4: Commit**

```bash
git add app/Http/Controllers/Api/Hr/
git commit -m "feat(hr): add resignation + exit checklist controllers with auto-checklist creation"
```

---

## Task 9: Create Exit Interview & Final Settlement Controllers

**Files:**
- Create: `app/Http/Controllers/Api/Hr/HrExitInterviewController.php`
- Create: `app/Http/Controllers/Api/Hr/HrFinalSettlementController.php`

**Step 1: Generate controllers**

```bash
php artisan make:controller Api/Hr/HrExitInterviewController --no-interaction
php artisan make:controller Api/Hr/HrFinalSettlementController --no-interaction
```

**Step 2: Implement HrExitInterviewController**

```php
<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\ExitInterview;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HrExitInterviewController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $interviews = ExitInterview::query()
            ->with(['employee:id,full_name,employee_id', 'conductor:id,full_name'])
            ->orderByDesc('interview_date')
            ->paginate($request->get('per_page', 15));

        return response()->json($interviews);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'employee_id' => ['required', 'exists:employees,id'],
            'interview_date' => ['required', 'date'],
            'reason_for_leaving' => ['required', 'in:better_opportunity,salary,work_environment,personal,relocation,career_change,management,other'],
            'overall_satisfaction' => ['required', 'integer', 'min:1', 'max:5'],
            'would_recommend' => ['required', 'boolean'],
            'feedback' => ['nullable', 'string'],
            'improvements' => ['nullable', 'string'],
        ]);

        $conductor = Employee::where('user_id', $request->user()->id)->first();

        $interview = ExitInterview::create(array_merge($validated, [
            'conducted_by' => $conductor?->id ?? $validated['employee_id'],
        ]));

        return response()->json([
            'message' => 'Exit interview recorded.',
            'data' => $interview->load(['employee:id,full_name', 'conductor:id,full_name']),
        ], 201);
    }

    public function show(ExitInterview $exitInterview): JsonResponse
    {
        return response()->json([
            'data' => $exitInterview->load([
                'employee:id,full_name,employee_id,department_id',
                'employee.department:id,name',
                'conductor:id,full_name',
            ]),
        ]);
    }

    public function update(Request $request, ExitInterview $exitInterview): JsonResponse
    {
        $validated = $request->validate([
            'reason_for_leaving' => ['sometimes', 'in:better_opportunity,salary,work_environment,personal,relocation,career_change,management,other'],
            'overall_satisfaction' => ['sometimes', 'integer', 'min:1', 'max:5'],
            'would_recommend' => ['sometimes', 'boolean'],
            'feedback' => ['nullable', 'string'],
            'improvements' => ['nullable', 'string'],
        ]);

        $exitInterview->update($validated);

        return response()->json([
            'message' => 'Exit interview updated.',
            'data' => $exitInterview,
        ]);
    }

    public function analytics(): JsonResponse
    {
        $interviews = ExitInterview::all();

        $reasonCounts = $interviews->groupBy('reason_for_leaving')
            ->map(fn ($group) => $group->count());

        $avgSatisfaction = $interviews->avg('overall_satisfaction');
        $recommendRate = $interviews->count() > 0
            ? round(($interviews->where('would_recommend', true)->count() / $interviews->count()) * 100, 1)
            : 0;

        return response()->json([
            'data' => [
                'total_interviews' => $interviews->count(),
                'reasons' => $reasonCounts,
                'average_satisfaction' => round($avgSatisfaction ?? 0, 1),
                'recommendation_rate' => $recommendRate,
            ],
        ]);
    }
}
```

**Step 3: Implement HrFinalSettlementController**

```php
<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use App\Models\FinalSettlement;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class HrFinalSettlementController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $settlements = FinalSettlement::query()
            ->with(['employee:id,full_name,employee_id,department_id', 'employee.department:id,name'])
            ->orderByDesc('created_at')
            ->paginate($request->get('per_page', 15));

        return response()->json($settlements);
    }

    public function calculate(Request $request, int $employeeId): JsonResponse
    {
        $validated = $request->validate([
            'final_last_date' => ['required', 'date'],
            'resignation_request_id' => ['nullable', 'exists:resignation_requests,id'],
            'other_earnings' => ['nullable', 'numeric', 'min:0'],
            'other_deductions' => ['nullable', 'numeric', 'min:0'],
        ]);

        $settlement = FinalSettlement::calculate($employeeId, $validated['final_last_date']);
        $settlement->resignation_request_id = $validated['resignation_request_id'] ?? null;
        $settlement->other_earnings = $validated['other_earnings'] ?? 0;
        $settlement->other_deductions = $validated['other_deductions'] ?? 0;

        // Recalculate totals with other earnings/deductions
        $settlement->total_gross = $settlement->prorated_salary + $settlement->leave_encashment + $settlement->other_earnings;
        $settlement->total_deductions = $settlement->epf_employee + $settlement->socso_employee + $settlement->eis_employee + $settlement->pcb_amount + $settlement->other_deductions;
        $settlement->net_amount = $settlement->total_gross - $settlement->total_deductions;

        $settlement->save();

        return response()->json([
            'message' => 'Final settlement calculated.',
            'data' => $settlement->load('employee:id,full_name,employee_id'),
        ], 201);
    }

    public function show(FinalSettlement $finalSettlement): JsonResponse
    {
        return response()->json([
            'data' => $finalSettlement->load([
                'employee:id,full_name,employee_id,department_id,position_id',
                'employee.department:id,name',
                'employee.position:id,title',
                'resignationRequest',
                'approver:id,name',
            ]),
        ]);
    }

    public function update(Request $request, FinalSettlement $finalSettlement): JsonResponse
    {
        $validated = $request->validate([
            'other_earnings' => ['nullable', 'numeric', 'min:0'],
            'other_deductions' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ]);

        $finalSettlement->update($validated);

        // Recalculate totals
        $finalSettlement->total_gross = $finalSettlement->prorated_salary + $finalSettlement->leave_encashment + $finalSettlement->other_earnings;
        $finalSettlement->total_deductions = $finalSettlement->epf_employee + $finalSettlement->socso_employee + $finalSettlement->eis_employee + $finalSettlement->pcb_amount + $finalSettlement->other_deductions;
        $finalSettlement->net_amount = $finalSettlement->total_gross - $finalSettlement->total_deductions;
        $finalSettlement->save();

        return response()->json([
            'message' => 'Settlement updated.',
            'data' => $finalSettlement,
        ]);
    }

    public function approve(Request $request, FinalSettlement $finalSettlement): JsonResponse
    {
        $finalSettlement->update([
            'status' => 'approved',
            'approved_by' => $request->user()->id,
        ]);

        return response()->json([
            'message' => 'Settlement approved.',
            'data' => $finalSettlement,
        ]);
    }

    public function markPaid(FinalSettlement $finalSettlement): JsonResponse
    {
        $finalSettlement->update([
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        return response()->json([
            'message' => 'Settlement marked as paid.',
            'data' => $finalSettlement,
        ]);
    }

    public function pdf(FinalSettlement $finalSettlement): \Illuminate\Http\Response
    {
        $finalSettlement->load(['employee.department', 'employee.position']);

        $html = view('hr.pdf.final-settlement', [
            'settlement' => $finalSettlement,
            'employee' => $finalSettlement->employee,
        ])->render();

        $pdf = Pdf::loadHTML($html)->setPaper('a4');

        $filename = "final_settlement_{$finalSettlement->employee->employee_id}.pdf";
        $path = "hr/settlements/{$filename}";
        Storage::disk('public')->put($path, $pdf->output());

        $finalSettlement->update(['pdf_path' => $path]);

        return $pdf->download($filename);
    }
}
```

**Step 4: Commit**

```bash
git add app/Http/Controllers/Api/Hr/
git commit -m "feat(hr): add exit interview + final settlement controllers with auto-calculation"
```

---

## Task 10: Create Letter Template Controller

**Files:**
- Create: `app/Http/Controllers/Api/Hr/HrLetterTemplateController.php`

**Step 1: Generate controller**

```bash
php artisan make:controller Api/Hr/HrLetterTemplateController --no-interaction
```

**Step 2: Implement HrLetterTemplateController**

```php
<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use App\Models\LetterTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HrLetterTemplateController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = LetterTemplate::query();

        if ($type = $request->get('type')) {
            $query->where('type', $type);
        }

        $templates = $query->orderBy('type')->orderBy('name')->get();

        return response()->json(['data' => $templates]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:verbal_warning,first_written,second_written,show_cause,termination,offer_letter,resignation_acceptance'],
            'content' => ['required', 'string'],
            'is_active' => ['boolean'],
        ]);

        $template = LetterTemplate::create($validated);

        return response()->json([
            'message' => 'Letter template created.',
            'data' => $template,
        ], 201);
    }

    public function update(Request $request, LetterTemplate $letterTemplate): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'type' => ['sometimes', 'in:verbal_warning,first_written,second_written,show_cause,termination,offer_letter,resignation_acceptance'],
            'content' => ['sometimes', 'string'],
            'is_active' => ['boolean'],
        ]);

        $letterTemplate->update($validated);

        return response()->json([
            'message' => 'Letter template updated.',
            'data' => $letterTemplate,
        ]);
    }

    public function destroy(LetterTemplate $letterTemplate): JsonResponse
    {
        $letterTemplate->delete();

        return response()->json(['message' => 'Letter template deleted.']);
    }
}
```

**Step 3: Commit**

```bash
git add app/Http/Controllers/Api/Hr/HrLetterTemplateController.php
git commit -m "feat(hr): add letter template CRUD controller"
```

---

## Task 11: Create Employee Self-Service Controllers (Disciplinary & Resignation)

**Files:**
- Create: `app/Http/Controllers/Api/Hr/HrMyDisciplinaryController.php`
- Create: `app/Http/Controllers/Api/Hr/HrMyResignationController.php`

**Step 1: Generate controllers**

```bash
php artisan make:controller Api/Hr/HrMyDisciplinaryController --no-interaction
php artisan make:controller Api/Hr/HrMyResignationController --no-interaction
```

**Step 2: Implement HrMyDisciplinaryController**

```php
<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use App\Models\DisciplinaryAction;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HrMyDisciplinaryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $employee = Employee::where('user_id', $request->user()->id)->firstOrFail();

        $actions = DisciplinaryAction::where('employee_id', $employee->id)
            ->with('issuer:id,full_name')
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['data' => $actions]);
    }

    public function respond(Request $request, DisciplinaryAction $disciplinaryAction): JsonResponse
    {
        $employee = Employee::where('user_id', $request->user()->id)->firstOrFail();

        if ($disciplinaryAction->employee_id !== $employee->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($disciplinaryAction->status !== 'pending_response') {
            return response()->json(['message' => 'This action does not require a response.'], 422);
        }

        $validated = $request->validate([
            'employee_response' => ['required', 'string'],
        ]);

        $disciplinaryAction->update([
            'employee_response' => $validated['employee_response'],
            'responded_at' => now(),
            'status' => 'responded',
        ]);

        return response()->json([
            'message' => 'Response submitted.',
            'data' => $disciplinaryAction,
        ]);
    }
}
```

**Step 3: Implement HrMyResignationController**

```php
<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\ResignationRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HrMyResignationController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $employee = Employee::where('user_id', $request->user()->id)->firstOrFail();

        // Check if already has pending/approved resignation
        $existing = ResignationRequest::where('employee_id', $employee->id)
            ->whereIn('status', ['pending', 'approved'])
            ->first();

        if ($existing) {
            return response()->json(['message' => 'You already have an active resignation request.'], 422);
        }

        $validated = $request->validate([
            'reason' => ['required', 'string'],
            'requested_last_date' => ['nullable', 'date'],
        ]);

        $noticePeriod = ResignationRequest::calculateNoticePeriod($employee);
        $submittedDate = now();

        $resignation = ResignationRequest::create([
            'employee_id' => $employee->id,
            'submitted_date' => $submittedDate,
            'reason' => $validated['reason'],
            'notice_period_days' => $noticePeriod,
            'last_working_date' => $submittedDate->copy()->addDays($noticePeriod),
            'requested_last_date' => $validated['requested_last_date'] ?? null,
            'status' => 'pending',
        ]);

        return response()->json([
            'message' => 'Resignation submitted. Notice period: ' . $noticePeriod . ' days.',
            'data' => $resignation,
        ], 201);
    }

    public function show(Request $request): JsonResponse
    {
        $employee = Employee::where('user_id', $request->user()->id)->firstOrFail();

        $resignation = ResignationRequest::where('employee_id', $employee->id)
            ->with(['approver:id,full_name', 'exitChecklist.items'])
            ->latest()
            ->first();

        return response()->json(['data' => $resignation]);
    }
}
```

**Step 4: Commit**

```bash
git add app/Http/Controllers/Api/Hr/
git commit -m "feat(hr): add employee self-service controllers — my disciplinary + my resignation"
```

---

## Task 12: Create Final Settlement PDF View

**Files:**
- Create: `resources/views/hr/pdf/final-settlement.blade.php`

**Step 1: Create the PDF Blade template**

```blade
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Final Settlement - {{ $employee->full_name }}</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; margin: 40px; }
        .header { text-align: center; margin-bottom: 30px; }
        .header h1 { font-size: 18px; margin: 0; }
        .header h2 { font-size: 14px; margin: 5px 0; color: #555; }
        .info-table { width: 100%; margin-bottom: 20px; }
        .info-table td { padding: 4px 8px; }
        .info-label { font-weight: bold; width: 200px; }
        .breakdown { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .breakdown th, .breakdown td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        .breakdown th { background-color: #f5f5f5; }
        .amount { text-align: right; }
        .total-row { font-weight: bold; background-color: #f0f0f0; }
        .net-row { font-weight: bold; background-color: #e8f5e9; font-size: 14px; }
        .footer { margin-top: 40px; }
        .signature-line { border-top: 1px solid #000; width: 200px; margin-top: 60px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ config('app.name') }}</h1>
        <h2>FINAL SETTLEMENT STATEMENT</h2>
    </div>

    <table class="info-table">
        <tr>
            <td class="info-label">Employee Name:</td>
            <td>{{ $employee->full_name }}</td>
            <td class="info-label">Employee ID:</td>
            <td>{{ $employee->employee_id }}</td>
        </tr>
        <tr>
            <td class="info-label">Department:</td>
            <td>{{ $employee->department?->name ?? 'N/A' }}</td>
            <td class="info-label">Position:</td>
            <td>{{ $employee->position?->title ?? 'N/A' }}</td>
        </tr>
        <tr>
            <td class="info-label">Settlement Date:</td>
            <td>{{ now()->format('d/m/Y') }}</td>
            <td class="info-label">Status:</td>
            <td>{{ ucfirst($settlement->status) }}</td>
        </tr>
    </table>

    <table class="breakdown">
        <thead>
            <tr>
                <th>Description</th>
                <th class="amount">Amount (RM)</th>
            </tr>
        </thead>
        <tbody>
            <tr><td colspan="2" style="background-color: #e3f2fd;"><strong>Earnings</strong></td></tr>
            <tr>
                <td>Prorated Salary</td>
                <td class="amount">{{ number_format($settlement->prorated_salary, 2) }}</td>
            </tr>
            <tr>
                <td>Leave Encashment ({{ $settlement->leave_encashment_days }} days)</td>
                <td class="amount">{{ number_format($settlement->leave_encashment, 2) }}</td>
            </tr>
            @if($settlement->other_earnings > 0)
            <tr>
                <td>Other Earnings</td>
                <td class="amount">{{ number_format($settlement->other_earnings, 2) }}</td>
            </tr>
            @endif
            <tr class="total-row">
                <td>Total Gross</td>
                <td class="amount">{{ number_format($settlement->total_gross, 2) }}</td>
            </tr>

            <tr><td colspan="2" style="background-color: #fce4ec;"><strong>Deductions</strong></td></tr>
            <tr>
                <td>EPF (Employee)</td>
                <td class="amount">{{ number_format($settlement->epf_employee, 2) }}</td>
            </tr>
            <tr>
                <td>SOCSO (Employee)</td>
                <td class="amount">{{ number_format($settlement->socso_employee, 2) }}</td>
            </tr>
            <tr>
                <td>EIS (Employee)</td>
                <td class="amount">{{ number_format($settlement->eis_employee, 2) }}</td>
            </tr>
            <tr>
                <td>PCB (Tax)</td>
                <td class="amount">{{ number_format($settlement->pcb_amount, 2) }}</td>
            </tr>
            @if($settlement->other_deductions > 0)
            <tr>
                <td>Other Deductions</td>
                <td class="amount">{{ number_format($settlement->other_deductions, 2) }}</td>
            </tr>
            @endif
            <tr class="total-row">
                <td>Total Deductions</td>
                <td class="amount">{{ number_format($settlement->total_deductions, 2) }}</td>
            </tr>

            <tr class="net-row">
                <td>NET PAYABLE</td>
                <td class="amount">RM {{ number_format($settlement->net_amount, 2) }}</td>
            </tr>
        </tbody>
    </table>

    <p><strong>EPF Employer Contribution:</strong> RM {{ number_format($settlement->epf_employer, 2) }}</p>

    @if($settlement->notes)
    <p><strong>Notes:</strong> {{ $settlement->notes }}</p>
    @endif

    <div class="footer">
        <div style="display: inline-block; width: 45%;">
            <div class="signature-line"></div>
            <p>Prepared by (HR)</p>
        </div>
        <div style="display: inline-block; width: 45%; float: right;">
            <div class="signature-line"></div>
            <p>Approved by</p>
        </div>
    </div>
</body>
</html>
```

**Step 2: Commit**

```bash
git add resources/views/hr/pdf/
git commit -m "feat(hr): add final settlement PDF template"
```

---

## Task 13: Add Module 10 Routes to api.php

**Files:**
- Modify: `routes/api.php`

**Step 1: Add the following inside the existing HR route group** (before the closing `});`):

```php
    // ========== MODULE 10: DISCIPLINARY & OFFBOARDING ==========

    // Disciplinary Dashboard
    Route::get('disciplinary/dashboard', [HrDisciplinaryDashboardController::class, 'stats'])->name('api.hr.disciplinary.dashboard');

    // Disciplinary Actions
    Route::get('disciplinary/actions', [HrDisciplinaryActionController::class, 'index'])->name('api.hr.disciplinary.actions.index');
    Route::post('disciplinary/actions', [HrDisciplinaryActionController::class, 'store'])->name('api.hr.disciplinary.actions.store');
    Route::get('disciplinary/actions/{disciplinaryAction}', [HrDisciplinaryActionController::class, 'show'])->name('api.hr.disciplinary.actions.show');
    Route::put('disciplinary/actions/{disciplinaryAction}', [HrDisciplinaryActionController::class, 'update'])->name('api.hr.disciplinary.actions.update');
    Route::patch('disciplinary/actions/{disciplinaryAction}/issue', [HrDisciplinaryActionController::class, 'issue'])->name('api.hr.disciplinary.actions.issue');
    Route::patch('disciplinary/actions/{disciplinaryAction}/close', [HrDisciplinaryActionController::class, 'close'])->name('api.hr.disciplinary.actions.close');
    Route::get('disciplinary/actions/{disciplinaryAction}/pdf', [HrDisciplinaryActionController::class, 'pdf'])->name('api.hr.disciplinary.actions.pdf');
    Route::get('disciplinary/employee/{employeeId}', [HrDisciplinaryActionController::class, 'employeeHistory'])->name('api.hr.disciplinary.employee');

    // Disciplinary Inquiries
    Route::post('disciplinary/inquiries', [HrDisciplinaryInquiryController::class, 'store'])->name('api.hr.disciplinary.inquiries.store');
    Route::get('disciplinary/inquiries/{disciplinaryInquiry}', [HrDisciplinaryInquiryController::class, 'show'])->name('api.hr.disciplinary.inquiries.show');
    Route::put('disciplinary/inquiries/{disciplinaryInquiry}', [HrDisciplinaryInquiryController::class, 'update'])->name('api.hr.disciplinary.inquiries.update');
    Route::patch('disciplinary/inquiries/{disciplinaryInquiry}/complete', [HrDisciplinaryInquiryController::class, 'complete'])->name('api.hr.disciplinary.inquiries.complete');

    // Resignations
    Route::get('offboarding/resignations', [HrResignationController::class, 'index'])->name('api.hr.offboarding.resignations.index');
    Route::post('offboarding/resignations', [HrResignationController::class, 'store'])->name('api.hr.offboarding.resignations.store');
    Route::get('offboarding/resignations/{resignationRequest}', [HrResignationController::class, 'show'])->name('api.hr.offboarding.resignations.show');
    Route::patch('offboarding/resignations/{resignationRequest}/approve', [HrResignationController::class, 'approve'])->name('api.hr.offboarding.resignations.approve');
    Route::patch('offboarding/resignations/{resignationRequest}/reject', [HrResignationController::class, 'reject'])->name('api.hr.offboarding.resignations.reject');
    Route::patch('offboarding/resignations/{resignationRequest}/complete', [HrResignationController::class, 'complete'])->name('api.hr.offboarding.resignations.complete');

    // Exit Checklists
    Route::get('offboarding/checklists', [HrExitChecklistController::class, 'index'])->name('api.hr.offboarding.checklists.index');
    Route::post('offboarding/checklists/{employeeId}', [HrExitChecklistController::class, 'createForEmployee'])->name('api.hr.offboarding.checklists.create');
    Route::get('offboarding/checklists/{exitChecklist}', [HrExitChecklistController::class, 'show'])->name('api.hr.offboarding.checklists.show');
    Route::patch('offboarding/checklists/{exitChecklist}/items/{item}', [HrExitChecklistController::class, 'updateItem'])->name('api.hr.offboarding.checklists.items.update');

    // Exit Interviews
    Route::get('offboarding/exit-interviews', [HrExitInterviewController::class, 'index'])->name('api.hr.offboarding.exit-interviews.index');
    Route::post('offboarding/exit-interviews', [HrExitInterviewController::class, 'store'])->name('api.hr.offboarding.exit-interviews.store');
    Route::get('offboarding/exit-interviews/analytics', [HrExitInterviewController::class, 'analytics'])->name('api.hr.offboarding.exit-interviews.analytics');
    Route::get('offboarding/exit-interviews/{exitInterview}', [HrExitInterviewController::class, 'show'])->name('api.hr.offboarding.exit-interviews.show');
    Route::put('offboarding/exit-interviews/{exitInterview}', [HrExitInterviewController::class, 'update'])->name('api.hr.offboarding.exit-interviews.update');

    // Final Settlements
    Route::get('offboarding/settlements', [HrFinalSettlementController::class, 'index'])->name('api.hr.offboarding.settlements.index');
    Route::post('offboarding/settlements/{employeeId}/calculate', [HrFinalSettlementController::class, 'calculate'])->name('api.hr.offboarding.settlements.calculate');
    Route::get('offboarding/settlements/{finalSettlement}', [HrFinalSettlementController::class, 'show'])->name('api.hr.offboarding.settlements.show');
    Route::put('offboarding/settlements/{finalSettlement}', [HrFinalSettlementController::class, 'update'])->name('api.hr.offboarding.settlements.update');
    Route::patch('offboarding/settlements/{finalSettlement}/approve', [HrFinalSettlementController::class, 'approve'])->name('api.hr.offboarding.settlements.approve');
    Route::patch('offboarding/settlements/{finalSettlement}/paid', [HrFinalSettlementController::class, 'markPaid'])->name('api.hr.offboarding.settlements.paid');
    Route::get('offboarding/settlements/{finalSettlement}/pdf', [HrFinalSettlementController::class, 'pdf'])->name('api.hr.offboarding.settlements.pdf');

    // Letter Templates
    Route::get('letter-templates', [HrLetterTemplateController::class, 'index'])->name('api.hr.letter-templates.index');
    Route::post('letter-templates', [HrLetterTemplateController::class, 'store'])->name('api.hr.letter-templates.store');
    Route::put('letter-templates/{letterTemplate}', [HrLetterTemplateController::class, 'update'])->name('api.hr.letter-templates.update');
    Route::delete('letter-templates/{letterTemplate}', [HrLetterTemplateController::class, 'destroy'])->name('api.hr.letter-templates.destroy');

    // My Disciplinary (Employee Self-Service)
    Route::get('me/disciplinary', [HrMyDisciplinaryController::class, 'index'])->name('api.hr.me.disciplinary');
    Route::post('me/disciplinary/{disciplinaryAction}/respond', [HrMyDisciplinaryController::class, 'respond'])->name('api.hr.me.disciplinary.respond');

    // My Resignation (Employee Self-Service)
    Route::post('me/resignation', [HrMyResignationController::class, 'store'])->name('api.hr.me.resignation.store');
    Route::get('me/resignation', [HrMyResignationController::class, 'show'])->name('api.hr.me.resignation.show');
```

**Step 2: Add use statements** at the top of routes/api.php for all new controllers:

```php
use App\Http\Controllers\Api\Hr\HrDisciplinaryDashboardController;
use App\Http\Controllers\Api\Hr\HrDisciplinaryActionController;
use App\Http\Controllers\Api\Hr\HrDisciplinaryInquiryController;
use App\Http\Controllers\Api\Hr\HrResignationController;
use App\Http\Controllers\Api\Hr\HrExitChecklistController;
use App\Http\Controllers\Api\Hr\HrExitInterviewController;
use App\Http\Controllers\Api\Hr\HrFinalSettlementController;
use App\Http\Controllers\Api\Hr\HrLetterTemplateController;
use App\Http\Controllers\Api\Hr\HrMyDisciplinaryController;
use App\Http\Controllers\Api\Hr\HrMyResignationController;
```

**Step 3: Commit**

```bash
git add routes/api.php
git commit -m "feat(hr): add Module 10 routes — disciplinary, offboarding, letter templates, self-service"
```

---

## Task 14: Create Module 10 Feature Tests

**Files:**
- Create: `tests/Feature/Hr/HrDisciplinaryApiTest.php`
- Create: `tests/Feature/Hr/HrOffboardingApiTest.php`

**Step 1: Generate tests**

```bash
php artisan make:test Hr/HrDisciplinaryApiTest --pest --no-interaction
php artisan make:test Hr/HrOffboardingApiTest --pest --no-interaction
```

**Step 2: Implement HrDisciplinaryApiTest**

```php
<?php

declare(strict_types=1);

use App\Models\Department;
use App\Models\DisciplinaryAction;
use App\Models\DisciplinaryInquiry;
use App\Models\Employee;
use App\Models\LetterTemplate;
use App\Models\Position;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createDisciplinaryAdmin(): User
{
    return User::factory()->create(['role' => 'admin']);
}

function createDisciplinarySetup(): array
{
    $department = Department::factory()->create();
    $position = Position::factory()->create(['department_id' => $department->id]);
    $admin = User::factory()->create(['role' => 'admin']);
    $adminEmployee = Employee::factory()->create([
        'user_id' => $admin->id,
        'department_id' => $department->id,
        'position_id' => $position->id,
    ]);
    $employee = Employee::factory()->create([
        'department_id' => $department->id,
        'position_id' => $position->id,
        'status' => 'active',
    ]);

    return compact('department', 'position', 'admin', 'adminEmployee', 'employee');
}

test('unauthenticated users get 401 on disciplinary endpoints', function () {
    $this->getJson('/api/hr/disciplinary/dashboard')->assertUnauthorized();
    $this->getJson('/api/hr/disciplinary/actions')->assertUnauthorized();
});

test('admin can get disciplinary dashboard stats', function () {
    $admin = createDisciplinaryAdmin();

    $response = $this->actingAs($admin)->getJson('/api/hr/disciplinary/dashboard');

    $response->assertSuccessful()
        ->assertJsonStructure(['data' => ['active_cases', 'warnings_this_month', 'pending_responses', 'cases_by_type']]);
});

test('admin can create disciplinary action', function () {
    $setup = createDisciplinarySetup();

    $response = $this->actingAs($setup['admin'])->postJson('/api/hr/disciplinary/actions', [
        'employee_id' => $setup['employee']->id,
        'type' => 'verbal_warning',
        'reason' => 'Late to work three times this month.',
        'incident_date' => '2026-03-20',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.type', 'verbal_warning')
        ->assertJsonStructure(['data' => ['reference_number']]);
});

test('admin can issue disciplinary action', function () {
    $setup = createDisciplinarySetup();
    $action = DisciplinaryAction::factory()->create([
        'employee_id' => $setup['employee']->id,
        'issued_by' => $setup['adminEmployee']->id,
        'status' => 'draft',
    ]);

    $response = $this->actingAs($setup['admin'])->patchJson("/api/hr/disciplinary/actions/{$action->id}/issue");

    $response->assertSuccessful()
        ->assertJsonPath('data.status', 'issued');
});

test('show cause action sets pending_response status on issue', function () {
    $setup = createDisciplinarySetup();
    $action = DisciplinaryAction::factory()->create([
        'employee_id' => $setup['employee']->id,
        'issued_by' => $setup['adminEmployee']->id,
        'type' => 'show_cause',
        'response_required' => true,
        'response_deadline' => now()->addDays(7),
        'status' => 'draft',
    ]);

    $response = $this->actingAs($setup['admin'])->patchJson("/api/hr/disciplinary/actions/{$action->id}/issue");

    $response->assertSuccessful()
        ->assertJsonPath('data.status', 'pending_response');
});

test('admin can schedule domestic inquiry', function () {
    $setup = createDisciplinarySetup();
    $action = DisciplinaryAction::factory()->create([
        'employee_id' => $setup['employee']->id,
        'issued_by' => $setup['adminEmployee']->id,
    ]);

    $response = $this->actingAs($setup['admin'])->postJson('/api/hr/disciplinary/inquiries', [
        'disciplinary_action_id' => $action->id,
        'hearing_date' => '2026-04-15',
        'hearing_time' => '10:00',
        'location' => 'HR Meeting Room',
        'panel_members' => [$setup['adminEmployee']->id],
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.status', 'scheduled');
});

test('admin can complete inquiry with decision', function () {
    $setup = createDisciplinarySetup();
    $action = DisciplinaryAction::factory()->create([
        'employee_id' => $setup['employee']->id,
        'issued_by' => $setup['adminEmployee']->id,
    ]);
    $inquiry = DisciplinaryInquiry::factory()->create([
        'disciplinary_action_id' => $action->id,
    ]);

    $response = $this->actingAs($setup['admin'])->patchJson("/api/hr/disciplinary/inquiries/{$inquiry->id}/complete", [
        'decision' => 'guilty',
        'findings' => 'Employee found guilty of repeated misconduct.',
        'penalty' => '2-week suspension',
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('data.decision', 'guilty');
});

test('employee can view own disciplinary records', function () {
    $user = User::factory()->create(['role' => 'employee']);
    $department = Department::factory()->create();
    $position = Position::factory()->create(['department_id' => $department->id]);
    $employee = Employee::factory()->create([
        'user_id' => $user->id,
        'department_id' => $department->id,
        'position_id' => $position->id,
    ]);
    $issuer = Employee::factory()->create([
        'department_id' => $department->id,
        'position_id' => $position->id,
    ]);
    DisciplinaryAction::factory()->issued()->create([
        'employee_id' => $employee->id,
        'issued_by' => $issuer->id,
    ]);

    $response = $this->actingAs($user)->getJson('/api/hr/me/disciplinary');

    $response->assertSuccessful()
        ->assertJsonCount(1, 'data');
});

test('employee can respond to show cause', function () {
    $user = User::factory()->create(['role' => 'employee']);
    $department = Department::factory()->create();
    $position = Position::factory()->create(['department_id' => $department->id]);
    $employee = Employee::factory()->create([
        'user_id' => $user->id,
        'department_id' => $department->id,
        'position_id' => $position->id,
    ]);
    $issuer = Employee::factory()->create([
        'department_id' => $department->id,
        'position_id' => $position->id,
    ]);
    $action = DisciplinaryAction::factory()->showCause()->create([
        'employee_id' => $employee->id,
        'issued_by' => $issuer->id,
    ]);

    $response = $this->actingAs($user)->postJson("/api/hr/me/disciplinary/{$action->id}/respond", [
        'employee_response' => 'I was stuck in traffic due to an accident on the highway.',
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('data.status', 'responded');
});
```

**Step 3: Implement HrOffboardingApiTest**

```php
<?php

declare(strict_types=1);

use App\Models\Department;
use App\Models\Employee;
use App\Models\ExitChecklist;
use App\Models\ExitChecklistItem;
use App\Models\ExitInterview;
use App\Models\FinalSettlement;
use App\Models\Position;
use App\Models\ResignationRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createOffboardingAdmin(): User
{
    return User::factory()->create(['role' => 'admin']);
}

function createOffboardingSetup(): array
{
    $department = Department::factory()->create();
    $position = Position::factory()->create(['department_id' => $department->id]);
    $admin = User::factory()->create(['role' => 'admin']);
    $adminEmployee = Employee::factory()->create([
        'user_id' => $admin->id,
        'department_id' => $department->id,
        'position_id' => $position->id,
    ]);
    $employee = Employee::factory()->create([
        'department_id' => $department->id,
        'position_id' => $position->id,
        'status' => 'active',
        'employment_type' => 'full_time',
        'join_date' => now()->subYear(),
    ]);

    return compact('department', 'position', 'admin', 'adminEmployee', 'employee');
}

test('unauthenticated users get 401 on offboarding endpoints', function () {
    $this->getJson('/api/hr/offboarding/resignations')->assertUnauthorized();
    $this->getJson('/api/hr/offboarding/checklists')->assertUnauthorized();
});

test('admin can submit resignation on behalf of employee', function () {
    $setup = createOffboardingSetup();

    $response = $this->actingAs($setup['admin'])->postJson('/api/hr/offboarding/resignations', [
        'employee_id' => $setup['employee']->id,
        'submitted_date' => '2026-03-28',
        'reason' => 'Moving to a different city.',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.notice_period_days', 30) // < 2 years = 30 days
        ->assertJsonPath('data.status', 'pending');
});

test('admin can approve resignation and exit checklist is created', function () {
    $setup = createOffboardingSetup();
    $resignation = ResignationRequest::factory()->create([
        'employee_id' => $setup['employee']->id,
    ]);

    $response = $this->actingAs($setup['admin'])->patchJson("/api/hr/offboarding/resignations/{$resignation->id}/approve", [
        'notes' => 'Approved. Best wishes.',
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('data.status', 'approved');

    // Verify exit checklist was created with default items
    expect(ExitChecklist::where('employee_id', $setup['employee']->id)->exists())->toBeTrue();
    $checklist = ExitChecklist::where('employee_id', $setup['employee']->id)->first();
    expect($checklist->items()->count())->toBeGreaterThanOrEqual(14); // 14 default items
});

test('admin can update exit checklist item status', function () {
    $setup = createOffboardingSetup();
    $checklist = ExitChecklist::factory()->create([
        'employee_id' => $setup['employee']->id,
    ]);
    $item = ExitChecklistItem::factory()->create([
        'exit_checklist_id' => $checklist->id,
        'status' => 'pending',
    ]);

    $response = $this->actingAs($setup['admin'])->patchJson(
        "/api/hr/offboarding/checklists/{$checklist->id}/items/{$item->id}",
        ['status' => 'completed']
    );

    $response->assertSuccessful()
        ->assertJsonPath('data.status', 'completed');
});

test('admin can create exit interview', function () {
    $setup = createOffboardingSetup();

    $response = $this->actingAs($setup['admin'])->postJson('/api/hr/offboarding/exit-interviews', [
        'employee_id' => $setup['employee']->id,
        'interview_date' => '2026-04-20',
        'reason_for_leaving' => 'better_opportunity',
        'overall_satisfaction' => 4,
        'would_recommend' => true,
        'feedback' => 'Great company, but looking for new challenges.',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.reason_for_leaving', 'better_opportunity');
});

test('admin can get exit interview analytics', function () {
    $admin = createOffboardingAdmin();
    ExitInterview::factory()->count(5)->create();

    $response = $this->actingAs($admin)->getJson('/api/hr/offboarding/exit-interviews/analytics');

    $response->assertSuccessful()
        ->assertJsonStructure(['data' => ['total_interviews', 'reasons', 'average_satisfaction', 'recommendation_rate']]);
});

test('admin can get final settlement list', function () {
    $admin = createOffboardingAdmin();

    $response = $this->actingAs($admin)->getJson('/api/hr/offboarding/settlements');

    $response->assertSuccessful();
});

test('admin can approve and mark settlement as paid', function () {
    $setup = createOffboardingSetup();
    $settlement = FinalSettlement::factory()->calculated()->create([
        'employee_id' => $setup['employee']->id,
    ]);

    $response = $this->actingAs($setup['admin'])->patchJson("/api/hr/offboarding/settlements/{$settlement->id}/approve");
    $response->assertSuccessful()->assertJsonPath('data.status', 'approved');

    $response = $this->actingAs($setup['admin'])->patchJson("/api/hr/offboarding/settlements/{$settlement->id}/paid");
    $response->assertSuccessful()->assertJsonPath('data.status', 'paid');
});

test('admin can complete offboarding and employee status changes to resigned', function () {
    $setup = createOffboardingSetup();
    $resignation = ResignationRequest::factory()->approved()->create([
        'employee_id' => $setup['employee']->id,
        'approved_by' => $setup['adminEmployee']->id,
    ]);

    $response = $this->actingAs($setup['admin'])->patchJson("/api/hr/offboarding/resignations/{$resignation->id}/complete");

    $response->assertSuccessful();
    expect($setup['employee']->fresh()->status)->toBe('resigned');
});

test('employee can submit own resignation', function () {
    $user = User::factory()->create(['role' => 'employee']);
    $department = Department::factory()->create();
    $position = Position::factory()->create(['department_id' => $department->id]);
    Employee::factory()->create([
        'user_id' => $user->id,
        'department_id' => $department->id,
        'position_id' => $position->id,
        'employment_type' => 'full_time',
        'join_date' => now()->subYear(),
    ]);

    $response = $this->actingAs($user)->postJson('/api/hr/me/resignation', [
        'reason' => 'Personal reasons.',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.notice_period_days', 30);
});

test('employee can view own resignation status', function () {
    $user = User::factory()->create(['role' => 'employee']);
    $department = Department::factory()->create();
    $position = Position::factory()->create(['department_id' => $department->id]);
    $employee = Employee::factory()->create([
        'user_id' => $user->id,
        'department_id' => $department->id,
        'position_id' => $position->id,
    ]);
    ResignationRequest::factory()->create(['employee_id' => $employee->id]);

    $response = $this->actingAs($user)->getJson('/api/hr/me/resignation');

    $response->assertSuccessful()
        ->assertJsonStructure(['data' => ['status', 'notice_period_days']]);
});
```

**Step 4: Run tests**

```bash
php artisan test --compact tests/Feature/Hr/HrDisciplinaryApiTest.php
php artisan test --compact tests/Feature/Hr/HrOffboardingApiTest.php
```

**Step 5: Commit**

```bash
git add tests/Feature/Hr/
git commit -m "test(hr): add Module 10 feature tests — disciplinary, offboarding, self-service"
```

---

## Task 15: Create Training Migrations (Part 1)

**Files:**
- Create: `database/migrations/2026_03_28_200009_create_training_programs_table.php`
- Create: `database/migrations/2026_03_28_200010_create_training_enrollments_table.php`
- Create: `database/migrations/2026_03_28_200011_create_training_costs_table.php`

**Step 1: Generate migrations**

```bash
php artisan make:migration create_training_programs_table --no-interaction
php artisan make:migration create_training_enrollments_table --no-interaction
php artisan make:migration create_training_costs_table --no-interaction
```

**Step 2: Implement training_programs migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_programs', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('type'); // internal, external
            $table->string('category'); // mandatory, technical, soft_skill, compliance, other
            $table->string('provider')->nullable();
            $table->string('location')->nullable();
            $table->date('start_date');
            $table->date('end_date');
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->integer('max_participants')->nullable();
            $table->decimal('cost_per_person', 10, 2)->nullable();
            $table->decimal('total_budget', 10, 2)->nullable();
            $table->string('status')->default('planned'); // planned, ongoing, completed, cancelled
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_programs');
    }
};
```

**Step 3: Implement training_enrollments migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('training_program_id')->constrained('training_programs')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees');
            $table->foreignId('enrolled_by')->constrained('users');
            $table->string('status')->default('enrolled'); // enrolled, attended, absent, cancelled
            $table->timestamp('attendance_confirmed_at')->nullable();
            $table->text('feedback')->nullable();
            $table->integer('feedback_rating')->nullable(); // 1-5
            $table->string('certificate_path')->nullable();
            $table->timestamps();

            $table->unique(['training_program_id', 'employee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_enrollments');
    }
};
```

**Step 4: Implement training_costs migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_costs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('training_program_id')->constrained('training_programs')->cascadeOnDelete();
            $table->string('description');
            $table->decimal('amount', 10, 2);
            $table->string('receipt_path')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_costs');
    }
};
```

**Step 5: Run migrations**

```bash
php artisan migrate
```

**Step 6: Commit**

```bash
git add database/migrations/
git commit -m "feat(hr): add training migrations — programs, enrollments, costs"
```

---

## Task 16: Create Training Migrations (Part 2) — Certifications & Budgets

**Files:**
- Create: `database/migrations/2026_03_28_200012_create_certifications_table.php`
- Create: `database/migrations/2026_03_28_200013_create_employee_certifications_table.php`
- Create: `database/migrations/2026_03_28_200014_create_training_budgets_table.php`

**Step 1: Generate migrations**

```bash
php artisan make:migration create_certifications_table --no-interaction
php artisan make:migration create_employee_certifications_table --no-interaction
php artisan make:migration create_training_budgets_table --no-interaction
```

**Step 2: Implement certifications migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('certifications', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('issuing_body')->nullable();
            $table->text('description')->nullable();
            $table->integer('validity_months')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certifications');
    }
};
```

**Step 3: Implement employee_certifications migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_certifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees');
            $table->foreignId('certification_id')->constrained('certifications');
            $table->string('certificate_number')->nullable();
            $table->date('issued_date');
            $table->date('expiry_date')->nullable();
            $table->string('certificate_path')->nullable();
            $table->string('status')->default('active'); // active, expired, revoked
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_certifications');
    }
};
```

**Step 4: Implement training_budgets migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_budgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->constrained('departments');
            $table->integer('year');
            $table->decimal('allocated_amount', 10, 2);
            $table->decimal('spent_amount', 10, 2)->default(0);
            $table->timestamps();

            $table->unique(['department_id', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_budgets');
    }
};
```

**Step 5: Run migrations**

```bash
php artisan migrate
```

**Step 6: Commit**

```bash
git add database/migrations/
git commit -m "feat(hr): add training migrations — certifications, employee certs, budgets"
```

---

## Task 17: Create Training Models + Factories (Part 1)

**Files:**
- Create: `app/Models/TrainingProgram.php`
- Create: `app/Models/TrainingEnrollment.php`
- Create: `app/Models/TrainingCost.php`
- Create: `database/factories/TrainingProgramFactory.php`
- Create: `database/factories/TrainingEnrollmentFactory.php`
- Create: `database/factories/TrainingCostFactory.php`

**Step 1: Generate models with factories**

```bash
php artisan make:model TrainingProgram -f --no-interaction
php artisan make:model TrainingEnrollment -f --no-interaction
php artisan make:model TrainingCost -f --no-interaction
```

**Step 2: Implement TrainingProgram model**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TrainingProgram extends Model
{
    /** @use HasFactory<\Database\Factories\TrainingProgramFactory> */
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'type',
        'category',
        'provider',
        'location',
        'start_date',
        'end_date',
        'start_time',
        'end_time',
        'max_participants',
        'cost_per_person',
        'total_budget',
        'status',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'cost_per_person' => 'decimal:2',
            'total_budget' => 'decimal:2',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(TrainingEnrollment::class);
    }

    public function costs(): HasMany
    {
        return $this->hasMany(TrainingCost::class);
    }

    public function scopePlanned(Builder $query): Builder
    {
        return $query->where('status', 'planned');
    }

    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->where('start_date', '>', now())->where('status', 'planned');
    }
}
```

**Step 3: Implement TrainingEnrollment model**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrainingEnrollment extends Model
{
    /** @use HasFactory<\Database\Factories\TrainingEnrollmentFactory> */
    use HasFactory;

    protected $fillable = [
        'training_program_id',
        'employee_id',
        'enrolled_by',
        'status',
        'attendance_confirmed_at',
        'feedback',
        'feedback_rating',
        'certificate_path',
    ];

    protected function casts(): array
    {
        return [
            'attendance_confirmed_at' => 'datetime',
            'feedback_rating' => 'integer',
        ];
    }

    public function trainingProgram(): BelongsTo
    {
        return $this->belongsTo(TrainingProgram::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function enrolledByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'enrolled_by');
    }
}
```

**Step 4: Implement TrainingCost model**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrainingCost extends Model
{
    /** @use HasFactory<\Database\Factories\TrainingCostFactory> */
    use HasFactory;

    protected $fillable = [
        'training_program_id',
        'description',
        'amount',
        'receipt_path',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    public function trainingProgram(): BelongsTo
    {
        return $this->belongsTo(TrainingProgram::class);
    }
}
```

**Step 5: Implement factories**

```php
// database/factories/TrainingProgramFactory.php
<?php

namespace Database\Factories;

use App\Models\TrainingProgram;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TrainingProgram> */
class TrainingProgramFactory extends Factory
{
    protected $model = TrainingProgram::class;

    public function definition(): array
    {
        $start = $this->faker->dateTimeBetween('now', '+3 months');

        return [
            'title' => $this->faker->sentence(4),
            'description' => $this->faker->paragraph(),
            'type' => $this->faker->randomElement(['internal', 'external']),
            'category' => $this->faker->randomElement(['mandatory', 'technical', 'soft_skill', 'compliance']),
            'start_date' => $start,
            'end_date' => (clone $start)->modify('+1 day'),
            'max_participants' => $this->faker->numberBetween(10, 50),
            'cost_per_person' => $this->faker->randomFloat(2, 50, 500),
            'status' => 'planned',
            'created_by' => User::factory(),
        ];
    }

    public function completed(): static
    {
        return $this->state(fn () => [
            'status' => 'completed',
            'start_date' => now()->subMonth(),
            'end_date' => now()->subMonth()->addDay(),
        ]);
    }
}
```

```php
// database/factories/TrainingEnrollmentFactory.php
<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\TrainingEnrollment;
use App\Models\TrainingProgram;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TrainingEnrollment> */
class TrainingEnrollmentFactory extends Factory
{
    protected $model = TrainingEnrollment::class;

    public function definition(): array
    {
        return [
            'training_program_id' => TrainingProgram::factory(),
            'employee_id' => Employee::factory(),
            'enrolled_by' => User::factory(),
            'status' => 'enrolled',
        ];
    }
}
```

```php
// database/factories/TrainingCostFactory.php
<?php

namespace Database\Factories;

use App\Models\TrainingCost;
use App\Models\TrainingProgram;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TrainingCost> */
class TrainingCostFactory extends Factory
{
    protected $model = TrainingCost::class;

    public function definition(): array
    {
        return [
            'training_program_id' => TrainingProgram::factory(),
            'description' => $this->faker->sentence(3),
            'amount' => $this->faker->randomFloat(2, 100, 5000),
        ];
    }
}
```

**Step 6: Commit**

```bash
git add app/Models/ database/factories/
git commit -m "feat(hr): add training program, enrollment, cost models + factories"
```

---

## Task 18: Create Certification & Budget Models + Factories

**Files:**
- Create: `app/Models/Certification.php`
- Create: `app/Models/EmployeeCertification.php`
- Create: `app/Models/TrainingBudget.php`
- Create: `database/factories/CertificationFactory.php`
- Create: `database/factories/EmployeeCertificationFactory.php`
- Create: `database/factories/TrainingBudgetFactory.php`

**Step 1: Generate models with factories**

```bash
php artisan make:model Certification -f --no-interaction
php artisan make:model EmployeeCertification -f --no-interaction
php artisan make:model TrainingBudget -f --no-interaction
```

**Step 2: Implement Certification model**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Certification extends Model
{
    /** @use HasFactory<\Database\Factories\CertificationFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'issuing_body',
        'description',
        'validity_months',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'validity_months' => 'integer',
        ];
    }

    public function employeeCertifications(): HasMany
    {
        return $this->hasMany(EmployeeCertification::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
```

**Step 3: Implement EmployeeCertification model**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeCertification extends Model
{
    /** @use HasFactory<\Database\Factories\EmployeeCertificationFactory> */
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'certification_id',
        'certificate_number',
        'issued_date',
        'expiry_date',
        'certificate_path',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'issued_date' => 'date',
            'expiry_date' => 'date',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function certification(): BelongsTo
    {
        return $this->belongsTo(Certification::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function scopeExpiringSoon(Builder $query, int $days = 90): Builder
    {
        return $query->where('status', 'active')
            ->whereNotNull('expiry_date')
            ->where('expiry_date', '<=', now()->addDays($days))
            ->where('expiry_date', '>', now());
    }

    public function scopeExpired(Builder $query): Builder
    {
        return $query->where('status', 'active')
            ->whereNotNull('expiry_date')
            ->where('expiry_date', '<', now());
    }
}
```

**Step 4: Implement TrainingBudget model**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrainingBudget extends Model
{
    /** @use HasFactory<\Database\Factories\TrainingBudgetFactory> */
    use HasFactory;

    protected $fillable = [
        'department_id',
        'year',
        'allocated_amount',
        'spent_amount',
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'allocated_amount' => 'decimal:2',
            'spent_amount' => 'decimal:2',
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function getUtilizationPercentageAttribute(): float
    {
        if ($this->allocated_amount <= 0) {
            return 0;
        }

        return round(($this->spent_amount / $this->allocated_amount) * 100, 1);
    }
}
```

**Step 5: Implement factories**

```php
// database/factories/CertificationFactory.php
<?php

namespace Database\Factories;

use App\Models\Certification;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Certification> */
class CertificationFactory extends Factory
{
    protected $model = Certification::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->sentence(3),
            'issuing_body' => $this->faker->company(),
            'validity_months' => $this->faker->randomElement([12, 24, 36]),
            'is_active' => true,
        ];
    }
}
```

```php
// database/factories/EmployeeCertificationFactory.php
<?php

namespace Database\Factories;

use App\Models\Certification;
use App\Models\Employee;
use App\Models\EmployeeCertification;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<EmployeeCertification> */
class EmployeeCertificationFactory extends Factory
{
    protected $model = EmployeeCertification::class;

    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'certification_id' => Certification::factory(),
            'certificate_number' => strtoupper($this->faker->bothify('CERT-####')),
            'issued_date' => $this->faker->dateTimeBetween('-2 years', 'now'),
            'expiry_date' => $this->faker->dateTimeBetween('now', '+2 years'),
            'status' => 'active',
        ];
    }

    public function expiringSoon(): static
    {
        return $this->state(fn () => [
            'expiry_date' => now()->addDays(30),
        ]);
    }
}
```

```php
// database/factories/TrainingBudgetFactory.php
<?php

namespace Database\Factories;

use App\Models\Department;
use App\Models\TrainingBudget;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TrainingBudget> */
class TrainingBudgetFactory extends Factory
{
    protected $model = TrainingBudget::class;

    public function definition(): array
    {
        return [
            'department_id' => Department::factory(),
            'year' => now()->year,
            'allocated_amount' => $this->faker->randomFloat(2, 5000, 50000),
            'spent_amount' => $this->faker->randomFloat(2, 0, 20000),
        ];
    }
}
```

**Step 6: Commit**

```bash
git add app/Models/ database/factories/
git commit -m "feat(hr): add certification, employee cert, training budget models + factories"
```

---

## Task 19: Create Certification Types Seeder

**Files:**
- Create: `database/seeders/CertificationSeeder.php`

**Step 1: Implement seeder**

```php
<?php

namespace Database\Seeders;

use App\Models\Certification;
use Illuminate\Database\Seeder;

class CertificationSeeder extends Seeder
{
    public function run(): void
    {
        $certifications = [
            ['name' => 'First Aid & CPR', 'issuing_body' => 'Red Crescent Malaysia', 'validity_months' => 24],
            ['name' => 'Fire Safety Training', 'issuing_body' => 'BOMBA', 'validity_months' => 12],
            ['name' => 'Occupational Safety & Health', 'issuing_body' => 'DOSH', 'validity_months' => 36],
            ['name' => 'ISO 9001 Internal Auditor', 'issuing_body' => 'SIRIM', 'validity_months' => 36],
            ['name' => 'Food Handler Certificate', 'issuing_body' => 'MOH', 'validity_months' => 24],
        ];

        foreach ($certifications as $cert) {
            Certification::updateOrCreate(
                ['name' => $cert['name']],
                array_merge($cert, ['is_active' => true])
            );
        }
    }
}
```

**Step 2: Run seeder**

```bash
php artisan db:seed --class=CertificationSeeder
```

**Step 3: Commit**

```bash
git add database/seeders/CertificationSeeder.php
git commit -m "feat(hr): add certification types seeder — Malaysian standard certs"
```

---

## Task 20: Create Training Dashboard & Program Controllers

**Files:**
- Create: `app/Http/Controllers/Api/Hr/HrTrainingDashboardController.php`
- Create: `app/Http/Controllers/Api/Hr/HrTrainingProgramController.php`

**Step 1: Generate controllers**

```bash
php artisan make:controller Api/Hr/HrTrainingDashboardController --no-interaction
php artisan make:controller Api/Hr/HrTrainingProgramController --no-interaction
```

**Step 2: Implement HrTrainingDashboardController**

```php
<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use App\Models\EmployeeCertification;
use App\Models\TrainingBudget;
use App\Models\TrainingCost;
use App\Models\TrainingProgram;
use Illuminate\Http\JsonResponse;

class HrTrainingDashboardController extends Controller
{
    public function stats(): JsonResponse
    {
        $currentYear = now()->year;

        return response()->json([
            'data' => [
                'upcoming_trainings' => TrainingProgram::where('start_date', '>', now())
                    ->where('status', 'planned')
                    ->count(),
                'completed_this_year' => TrainingProgram::where('status', 'completed')
                    ->whereYear('end_date', $currentYear)
                    ->count(),
                'total_spend' => TrainingCost::whereHas('trainingProgram', fn ($q) => $q->whereYear('start_date', $currentYear))->sum('amount'),
                'certs_expiring_soon' => EmployeeCertification::expiringSoon(90)->count(),
                'budget_utilization' => TrainingBudget::where('year', $currentYear)->get()->map(fn ($b) => [
                    'department_id' => $b->department_id,
                    'allocated' => $b->allocated_amount,
                    'spent' => $b->spent_amount,
                    'percentage' => $b->utilization_percentage,
                ]),
            ],
        ]);
    }
}
```

**Step 3: Implement HrTrainingProgramController**

```php
<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use App\Models\TrainingProgram;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HrTrainingProgramController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = TrainingProgram::query()
            ->withCount('enrollments')
            ->withSum('costs', 'amount');

        if ($search = $request->get('search')) {
            $query->where('title', 'like', "%{$search}%");
        }

        if ($type = $request->get('type')) {
            $query->where('type', $type);
        }

        if ($category = $request->get('category')) {
            $query->where('category', $category);
        }

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        $programs = $query->orderByDesc('start_date')->paginate($request->get('per_page', 15));

        return response()->json($programs);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'type' => ['required', 'in:internal,external'],
            'category' => ['required', 'in:mandatory,technical,soft_skill,compliance,other'],
            'provider' => ['nullable', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i'],
            'max_participants' => ['nullable', 'integer', 'min:1'],
            'cost_per_person' => ['nullable', 'numeric', 'min:0'],
            'total_budget' => ['nullable', 'numeric', 'min:0'],
        ]);

        $program = TrainingProgram::create(array_merge($validated, [
            'status' => 'planned',
            'created_by' => $request->user()->id,
        ]));

        return response()->json([
            'message' => 'Training program created.',
            'data' => $program,
        ], 201);
    }

    public function show(TrainingProgram $trainingProgram): JsonResponse
    {
        return response()->json([
            'data' => $trainingProgram->load([
                'enrollments.employee:id,full_name,employee_id,department_id',
                'enrollments.employee.department:id,name',
                'costs',
                'creator:id,name',
            ]),
        ]);
    }

    public function update(Request $request, TrainingProgram $trainingProgram): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'type' => ['sometimes', 'in:internal,external'],
            'category' => ['sometimes', 'in:mandatory,technical,soft_skill,compliance,other'],
            'provider' => ['nullable', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'start_date' => ['sometimes', 'date'],
            'end_date' => ['sometimes', 'date'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i'],
            'max_participants' => ['nullable', 'integer', 'min:1'],
            'cost_per_person' => ['nullable', 'numeric', 'min:0'],
            'total_budget' => ['nullable', 'numeric', 'min:0'],
            'status' => ['sometimes', 'in:planned,ongoing,completed,cancelled'],
        ]);

        $trainingProgram->update($validated);

        return response()->json([
            'message' => 'Training program updated.',
            'data' => $trainingProgram,
        ]);
    }

    public function destroy(TrainingProgram $trainingProgram): JsonResponse
    {
        if ($trainingProgram->status !== 'planned') {
            return response()->json(['message' => 'Only planned programs can be deleted.'], 422);
        }

        $trainingProgram->delete();

        return response()->json(['message' => 'Training program deleted.']);
    }

    public function complete(TrainingProgram $trainingProgram): JsonResponse
    {
        $trainingProgram->update(['status' => 'completed']);

        return response()->json([
            'message' => 'Training program marked as completed.',
            'data' => $trainingProgram,
        ]);
    }
}
```

**Step 4: Commit**

```bash
git add app/Http/Controllers/Api/Hr/
git commit -m "feat(hr): add training dashboard + program controllers"
```

---

## Task 21: Create Training Enrollment & Cost Controllers

**Files:**
- Create: `app/Http/Controllers/Api/Hr/HrTrainingEnrollmentController.php`
- Create: `app/Http/Controllers/Api/Hr/HrTrainingCostController.php`

**Step 1: Generate controllers**

```bash
php artisan make:controller Api/Hr/HrTrainingEnrollmentController --no-interaction
php artisan make:controller Api/Hr/HrTrainingCostController --no-interaction
```

**Step 2: Implement HrTrainingEnrollmentController**

```php
<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use App\Models\TrainingEnrollment;
use App\Models\TrainingProgram;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HrTrainingEnrollmentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $enrollments = TrainingEnrollment::query()
            ->with([
                'employee:id,full_name,employee_id',
                'trainingProgram:id,title,start_date,end_date',
            ])
            ->orderByDesc('created_at')
            ->paginate($request->get('per_page', 15));

        return response()->json($enrollments);
    }

    public function enroll(Request $request, TrainingProgram $trainingProgram): JsonResponse
    {
        $validated = $request->validate([
            'employee_ids' => ['required', 'array', 'min:1'],
            'employee_ids.*' => ['integer', 'exists:employees,id'],
        ]);

        $enrolled = [];

        return DB::transaction(function () use ($validated, $trainingProgram, $request) {
            $enrolled = [];

            foreach ($validated['employee_ids'] as $employeeId) {
                $existing = TrainingEnrollment::where('training_program_id', $trainingProgram->id)
                    ->where('employee_id', $employeeId)
                    ->first();

                if (! $existing) {
                    $enrolled[] = TrainingEnrollment::create([
                        'training_program_id' => $trainingProgram->id,
                        'employee_id' => $employeeId,
                        'enrolled_by' => $request->user()->id,
                        'status' => 'enrolled',
                    ]);
                }
            }

            return response()->json([
                'message' => count($enrolled) . ' employee(s) enrolled.',
                'data' => $enrolled,
            ], 201);
        });
    }

    public function update(Request $request, TrainingEnrollment $trainingEnrollment): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'in:enrolled,attended,absent,cancelled'],
        ]);

        $trainingEnrollment->update(array_merge($validated, [
            'attendance_confirmed_at' => in_array($validated['status'], ['attended', 'absent']) ? now() : null,
        ]));

        return response()->json([
            'message' => 'Enrollment updated.',
            'data' => $trainingEnrollment,
        ]);
    }

    public function destroy(TrainingEnrollment $trainingEnrollment): JsonResponse
    {
        $trainingEnrollment->delete();

        return response()->json(['message' => 'Enrollment cancelled.']);
    }

    public function feedback(Request $request, TrainingEnrollment $trainingEnrollment): JsonResponse
    {
        $validated = $request->validate([
            'feedback' => ['required', 'string'],
            'feedback_rating' => ['required', 'integer', 'min:1', 'max:5'],
        ]);

        $trainingEnrollment->update($validated);

        return response()->json([
            'message' => 'Feedback submitted.',
            'data' => $trainingEnrollment,
        ]);
    }
}
```

**Step 3: Implement HrTrainingCostController**

```php
<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use App\Models\TrainingCost;
use App\Models\TrainingProgram;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HrTrainingCostController extends Controller
{
    public function index(TrainingProgram $trainingProgram): JsonResponse
    {
        return response()->json([
            'data' => $trainingProgram->costs,
        ]);
    }

    public function store(Request $request, TrainingProgram $trainingProgram): JsonResponse
    {
        $validated = $request->validate([
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0'],
            'receipt_path' => ['nullable', 'string'],
        ]);

        $cost = $trainingProgram->costs()->create($validated);

        return response()->json([
            'message' => 'Cost added.',
            'data' => $cost,
        ], 201);
    }

    public function update(Request $request, TrainingCost $trainingCost): JsonResponse
    {
        $validated = $request->validate([
            'description' => ['sometimes', 'string', 'max:255'],
            'amount' => ['sometimes', 'numeric', 'min:0'],
            'receipt_path' => ['nullable', 'string'],
        ]);

        $trainingCost->update($validated);

        return response()->json([
            'message' => 'Cost updated.',
            'data' => $trainingCost,
        ]);
    }

    public function destroy(TrainingCost $trainingCost): JsonResponse
    {
        $trainingCost->delete();

        return response()->json(['message' => 'Cost deleted.']);
    }
}
```

**Step 4: Commit**

```bash
git add app/Http/Controllers/Api/Hr/
git commit -m "feat(hr): add training enrollment + cost controllers"
```

---

## Task 22: Create Certification Controllers

**Files:**
- Create: `app/Http/Controllers/Api/Hr/HrCertificationController.php`
- Create: `app/Http/Controllers/Api/Hr/HrEmployeeCertificationController.php`

**Step 1: Generate controllers**

```bash
php artisan make:controller Api/Hr/HrCertificationController --no-interaction
php artisan make:controller Api/Hr/HrEmployeeCertificationController --no-interaction
```

**Step 2: Implement HrCertificationController**

```php
<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use App\Models\Certification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HrCertificationController extends Controller
{
    public function index(): JsonResponse
    {
        $certifications = Certification::withCount('employeeCertifications')
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $certifications]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'issuing_body' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'validity_months' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['boolean'],
        ]);

        $certification = Certification::create($validated);

        return response()->json([
            'message' => 'Certification type created.',
            'data' => $certification,
        ], 201);
    }

    public function update(Request $request, Certification $certification): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'issuing_body' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'validity_months' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['boolean'],
        ]);

        $certification->update($validated);

        return response()->json([
            'message' => 'Certification updated.',
            'data' => $certification,
        ]);
    }

    public function destroy(Certification $certification): JsonResponse
    {
        $certification->delete();

        return response()->json(['message' => 'Certification deleted.']);
    }
}
```

**Step 3: Implement HrEmployeeCertificationController**

```php
<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use App\Models\EmployeeCertification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HrEmployeeCertificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = EmployeeCertification::query()
            ->with([
                'employee:id,full_name,employee_id,department_id',
                'employee.department:id,name',
                'certification:id,name,issuing_body,validity_months',
            ]);

        if ($employeeId = $request->get('employee_id')) {
            $query->where('employee_id', $employeeId);
        }

        if ($certificationId = $request->get('certification_id')) {
            $query->where('certification_id', $certificationId);
        }

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        $certs = $query->orderByDesc('created_at')->paginate($request->get('per_page', 15));

        return response()->json($certs);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'employee_id' => ['required', 'exists:employees,id'],
            'certification_id' => ['required', 'exists:certifications,id'],
            'certificate_number' => ['nullable', 'string', 'max:255'],
            'issued_date' => ['required', 'date'],
            'expiry_date' => ['nullable', 'date', 'after:issued_date'],
            'certificate_path' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ]);

        $cert = EmployeeCertification::create(array_merge($validated, [
            'status' => 'active',
        ]));

        return response()->json([
            'message' => 'Employee certification added.',
            'data' => $cert->load(['employee:id,full_name', 'certification:id,name']),
        ], 201);
    }

    public function update(Request $request, EmployeeCertification $employeeCertification): JsonResponse
    {
        $validated = $request->validate([
            'certificate_number' => ['nullable', 'string', 'max:255'],
            'issued_date' => ['sometimes', 'date'],
            'expiry_date' => ['nullable', 'date'],
            'certificate_path' => ['nullable', 'string'],
            'status' => ['sometimes', 'in:active,expired,revoked'],
            'notes' => ['nullable', 'string'],
        ]);

        $employeeCertification->update($validated);

        return response()->json([
            'message' => 'Certification updated.',
            'data' => $employeeCertification,
        ]);
    }

    public function destroy(EmployeeCertification $employeeCertification): JsonResponse
    {
        $employeeCertification->delete();

        return response()->json(['message' => 'Certification removed.']);
    }

    public function expiring(Request $request): JsonResponse
    {
        $days = $request->get('days', 90);

        $certs = EmployeeCertification::expiringSoon($days)
            ->with([
                'employee:id,full_name,employee_id',
                'certification:id,name',
            ])
            ->orderBy('expiry_date')
            ->get();

        return response()->json(['data' => $certs]);
    }
}
```

**Step 4: Commit**

```bash
git add app/Http/Controllers/Api/Hr/
git commit -m "feat(hr): add certification + employee certification controllers with expiry tracking"
```

---

## Task 23: Create Training Budget & Report Controllers

**Files:**
- Create: `app/Http/Controllers/Api/Hr/HrTrainingBudgetController.php`
- Create: `app/Http/Controllers/Api/Hr/HrTrainingReportController.php`

**Step 1: Generate controllers**

```bash
php artisan make:controller Api/Hr/HrTrainingBudgetController --no-interaction
php artisan make:controller Api/Hr/HrTrainingReportController --no-interaction
```

**Step 2: Implement HrTrainingBudgetController**

```php
<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use App\Models\TrainingBudget;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HrTrainingBudgetController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $year = $request->get('year', now()->year);

        $budgets = TrainingBudget::where('year', $year)
            ->with('department:id,name')
            ->get()
            ->map(fn ($b) => array_merge($b->toArray(), [
                'utilization_percentage' => $b->utilization_percentage,
            ]));

        return response()->json(['data' => $budgets]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'department_id' => ['required', 'exists:departments,id'],
            'year' => ['required', 'integer', 'min:2020', 'max:2050'],
            'allocated_amount' => ['required', 'numeric', 'min:0'],
        ]);

        $budget = TrainingBudget::updateOrCreate(
            ['department_id' => $validated['department_id'], 'year' => $validated['year']],
            ['allocated_amount' => $validated['allocated_amount']]
        );

        return response()->json([
            'message' => 'Budget set.',
            'data' => $budget->load('department:id,name'),
        ], 201);
    }

    public function update(Request $request, TrainingBudget $trainingBudget): JsonResponse
    {
        $validated = $request->validate([
            'allocated_amount' => ['sometimes', 'numeric', 'min:0'],
            'spent_amount' => ['sometimes', 'numeric', 'min:0'],
        ]);

        $trainingBudget->update($validated);

        return response()->json([
            'message' => 'Budget updated.',
            'data' => $trainingBudget,
        ]);
    }
}
```

**Step 3: Implement HrTrainingReportController**

```php
<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use App\Models\TrainingEnrollment;
use App\Models\TrainingProgram;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HrTrainingReportController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $year = $request->get('year', now()->year);

        // Training hours per department
        $programsByDepartment = TrainingEnrollment::where('status', 'attended')
            ->whereHas('trainingProgram', fn ($q) => $q->whereYear('start_date', $year))
            ->with(['employee:id,department_id', 'employee.department:id,name', 'trainingProgram:id,start_date,end_date'])
            ->get()
            ->groupBy('employee.department.name')
            ->map(fn ($group) => $group->count());

        // Total cost
        $totalCost = TrainingProgram::whereYear('start_date', $year)
            ->withSum('costs', 'amount')
            ->get()
            ->sum('costs_sum_amount');

        // Completion rate
        $totalEnrollments = TrainingEnrollment::whereHas('trainingProgram', fn ($q) => $q->whereYear('start_date', $year))->count();
        $attendedEnrollments = TrainingEnrollment::where('status', 'attended')
            ->whereHas('trainingProgram', fn ($q) => $q->whereYear('start_date', $year))
            ->count();

        return response()->json([
            'data' => [
                'year' => $year,
                'training_by_department' => $programsByDepartment,
                'total_cost' => round($totalCost, 2),
                'total_enrollments' => $totalEnrollments,
                'attended' => $attendedEnrollments,
                'attendance_rate' => $totalEnrollments > 0
                    ? round(($attendedEnrollments / $totalEnrollments) * 100, 1)
                    : 0,
            ],
        ]);
    }
}
```

**Step 4: Commit**

```bash
git add app/Http/Controllers/Api/Hr/
git commit -m "feat(hr): add training budget + report controllers"
```

---

## Task 24: Create Employee Self-Service Training Controller

**Files:**
- Create: `app/Http/Controllers/Api/Hr/HrMyTrainingController.php`

**Step 1: Generate controller**

```bash
php artisan make:controller Api/Hr/HrMyTrainingController --no-interaction
```

**Step 2: Implement HrMyTrainingController**

```php
<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeCertification;
use App\Models\TrainingEnrollment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HrMyTrainingController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $employee = Employee::where('user_id', $request->user()->id)->firstOrFail();

        $trainings = TrainingEnrollment::where('employee_id', $employee->id)
            ->with(['trainingProgram:id,title,type,category,start_date,end_date,location,status'])
            ->orderByDesc('created_at')
            ->get();

        $certifications = EmployeeCertification::where('employee_id', $employee->id)
            ->with('certification:id,name,issuing_body')
            ->orderByDesc('expiry_date')
            ->get();

        return response()->json([
            'data' => [
                'trainings' => $trainings,
                'certifications' => $certifications,
            ],
        ]);
    }

    public function feedback(Request $request, TrainingEnrollment $trainingEnrollment): JsonResponse
    {
        $employee = Employee::where('user_id', $request->user()->id)->firstOrFail();

        if ($trainingEnrollment->employee_id !== $employee->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'feedback' => ['required', 'string'],
            'feedback_rating' => ['required', 'integer', 'min:1', 'max:5'],
        ]);

        $trainingEnrollment->update($validated);

        return response()->json([
            'message' => 'Feedback submitted.',
            'data' => $trainingEnrollment,
        ]);
    }
}
```

**Step 3: Commit**

```bash
git add app/Http/Controllers/Api/Hr/HrMyTrainingController.php
git commit -m "feat(hr): add employee self-service training controller"
```

---

## Task 25: Add Module 9 Routes to api.php

**Files:**
- Modify: `routes/api.php`

**Step 1: Add the following inside the existing HR route group** (after Module 10 routes):

```php
    // ========== MODULE 9: TRAINING & DEVELOPMENT ==========

    // Training Dashboard
    Route::get('training/dashboard', [HrTrainingDashboardController::class, 'stats'])->name('api.hr.training.dashboard');

    // Training Programs
    Route::get('training/programs', [HrTrainingProgramController::class, 'index'])->name('api.hr.training.programs.index');
    Route::post('training/programs', [HrTrainingProgramController::class, 'store'])->name('api.hr.training.programs.store');
    Route::get('training/programs/{trainingProgram}', [HrTrainingProgramController::class, 'show'])->name('api.hr.training.programs.show');
    Route::put('training/programs/{trainingProgram}', [HrTrainingProgramController::class, 'update'])->name('api.hr.training.programs.update');
    Route::delete('training/programs/{trainingProgram}', [HrTrainingProgramController::class, 'destroy'])->name('api.hr.training.programs.destroy');
    Route::patch('training/programs/{trainingProgram}/complete', [HrTrainingProgramController::class, 'complete'])->name('api.hr.training.programs.complete');

    // Enrollments
    Route::get('training/enrollments', [HrTrainingEnrollmentController::class, 'index'])->name('api.hr.training.enrollments.index');
    Route::post('training/programs/{trainingProgram}/enroll', [HrTrainingEnrollmentController::class, 'enroll'])->name('api.hr.training.enrollments.enroll');
    Route::patch('training/enrollments/{trainingEnrollment}', [HrTrainingEnrollmentController::class, 'update'])->name('api.hr.training.enrollments.update');
    Route::delete('training/enrollments/{trainingEnrollment}', [HrTrainingEnrollmentController::class, 'destroy'])->name('api.hr.training.enrollments.destroy');
    Route::put('training/enrollments/{trainingEnrollment}/feedback', [HrTrainingEnrollmentController::class, 'feedback'])->name('api.hr.training.enrollments.feedback');

    // Training Costs
    Route::get('training/programs/{trainingProgram}/costs', [HrTrainingCostController::class, 'index'])->name('api.hr.training.costs.index');
    Route::post('training/programs/{trainingProgram}/costs', [HrTrainingCostController::class, 'store'])->name('api.hr.training.costs.store');
    Route::put('training/costs/{trainingCost}', [HrTrainingCostController::class, 'update'])->name('api.hr.training.costs.update');
    Route::delete('training/costs/{trainingCost}', [HrTrainingCostController::class, 'destroy'])->name('api.hr.training.costs.destroy');

    // Certifications
    Route::get('training/certifications', [HrCertificationController::class, 'index'])->name('api.hr.training.certifications.index');
    Route::post('training/certifications', [HrCertificationController::class, 'store'])->name('api.hr.training.certifications.store');
    Route::put('training/certifications/{certification}', [HrCertificationController::class, 'update'])->name('api.hr.training.certifications.update');
    Route::delete('training/certifications/{certification}', [HrCertificationController::class, 'destroy'])->name('api.hr.training.certifications.destroy');

    // Employee Certifications
    Route::get('training/employee-certifications', [HrEmployeeCertificationController::class, 'index'])->name('api.hr.training.employee-certifications.index');
    Route::post('training/employee-certifications', [HrEmployeeCertificationController::class, 'store'])->name('api.hr.training.employee-certifications.store');
    Route::put('training/employee-certifications/{employeeCertification}', [HrEmployeeCertificationController::class, 'update'])->name('api.hr.training.employee-certifications.update');
    Route::delete('training/employee-certifications/{employeeCertification}', [HrEmployeeCertificationController::class, 'destroy'])->name('api.hr.training.employee-certifications.destroy');
    Route::get('training/employee-certifications/expiring', [HrEmployeeCertificationController::class, 'expiring'])->name('api.hr.training.employee-certifications.expiring');

    // Training Budget
    Route::get('training/budgets', [HrTrainingBudgetController::class, 'index'])->name('api.hr.training.budgets.index');
    Route::post('training/budgets', [HrTrainingBudgetController::class, 'store'])->name('api.hr.training.budgets.store');
    Route::put('training/budgets/{trainingBudget}', [HrTrainingBudgetController::class, 'update'])->name('api.hr.training.budgets.update');

    // Training Reports
    Route::get('training/reports', [HrTrainingReportController::class, 'index'])->name('api.hr.training.reports');

    // My Training (Employee Self-Service)
    Route::get('me/training', [HrMyTrainingController::class, 'index'])->name('api.hr.me.training');
    Route::put('me/training/{trainingEnrollment}/feedback', [HrMyTrainingController::class, 'feedback'])->name('api.hr.me.training.feedback');
```

**Step 2: Add use statements** at the top of routes/api.php:

```php
use App\Http\Controllers\Api\Hr\HrTrainingDashboardController;
use App\Http\Controllers\Api\Hr\HrTrainingProgramController;
use App\Http\Controllers\Api\Hr\HrTrainingEnrollmentController;
use App\Http\Controllers\Api\Hr\HrTrainingCostController;
use App\Http\Controllers\Api\Hr\HrCertificationController;
use App\Http\Controllers\Api\Hr\HrEmployeeCertificationController;
use App\Http\Controllers\Api\Hr\HrTrainingBudgetController;
use App\Http\Controllers\Api\Hr\HrTrainingReportController;
use App\Http\Controllers\Api\Hr\HrMyTrainingController;
```

**Important:** Place the `training/employee-certifications/expiring` route BEFORE `training/employee-certifications/{employeeCertification}` to avoid route conflicts.

**Step 3: Commit**

```bash
git add routes/api.php
git commit -m "feat(hr): add Module 9 routes — training, certifications, budgets, self-service"
```

---

## Task 26: Create Module 9 Feature Tests

**Files:**
- Create: `tests/Feature/Hr/HrTrainingApiTest.php`

**Step 1: Generate test**

```bash
php artisan make:test Hr/HrTrainingApiTest --pest --no-interaction
```

**Step 2: Implement HrTrainingApiTest**

```php
<?php

declare(strict_types=1);

use App\Models\Certification;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeCertification;
use App\Models\Position;
use App\Models\TrainingBudget;
use App\Models\TrainingEnrollment;
use App\Models\TrainingProgram;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createTrainingAdmin(): User
{
    return User::factory()->create(['role' => 'admin']);
}

function createTrainingSetup(): array
{
    $department = Department::factory()->create();
    $position = Position::factory()->create(['department_id' => $department->id]);
    $admin = User::factory()->create(['role' => 'admin']);
    $employee = Employee::factory()->create([
        'department_id' => $department->id,
        'position_id' => $position->id,
        'status' => 'active',
    ]);

    return compact('department', 'position', 'admin', 'employee');
}

test('unauthenticated users get 401 on training endpoints', function () {
    $this->getJson('/api/hr/training/dashboard')->assertUnauthorized();
    $this->getJson('/api/hr/training/programs')->assertUnauthorized();
});

test('admin can get training dashboard stats', function () {
    $admin = createTrainingAdmin();

    $response = $this->actingAs($admin)->getJson('/api/hr/training/dashboard');

    $response->assertSuccessful()
        ->assertJsonStructure(['data' => ['upcoming_trainings', 'completed_this_year', 'total_spend', 'certs_expiring_soon']]);
});

test('admin can create training program', function () {
    $admin = createTrainingAdmin();

    $response = $this->actingAs($admin)->postJson('/api/hr/training/programs', [
        'title' => 'Fire Safety Training',
        'type' => 'internal',
        'category' => 'mandatory',
        'start_date' => '2026-05-01',
        'end_date' => '2026-05-01',
        'max_participants' => 30,
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.title', 'Fire Safety Training')
        ->assertJsonPath('data.status', 'planned');
});

test('admin can enroll employees in training', function () {
    $setup = createTrainingSetup();
    $program = TrainingProgram::factory()->create(['created_by' => $setup['admin']->id]);

    $response = $this->actingAs($setup['admin'])->postJson("/api/hr/training/programs/{$program->id}/enroll", [
        'employee_ids' => [$setup['employee']->id],
    ]);

    $response->assertCreated();
    expect(TrainingEnrollment::where('training_program_id', $program->id)->count())->toBe(1);
});

test('admin can update enrollment attendance', function () {
    $setup = createTrainingSetup();
    $enrollment = TrainingEnrollment::factory()->create([
        'enrolled_by' => $setup['admin']->id,
        'employee_id' => $setup['employee']->id,
    ]);

    $response = $this->actingAs($setup['admin'])->patchJson("/api/hr/training/enrollments/{$enrollment->id}", [
        'status' => 'attended',
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('data.status', 'attended');
});

test('admin can add training cost', function () {
    $admin = createTrainingAdmin();
    $program = TrainingProgram::factory()->create(['created_by' => $admin->id]);

    $response = $this->actingAs($admin)->postJson("/api/hr/training/programs/{$program->id}/costs", [
        'description' => 'Venue rental',
        'amount' => 1500.00,
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.amount', '1500.00');
});

test('admin can CRUD certification types', function () {
    $admin = createTrainingAdmin();

    $response = $this->actingAs($admin)->postJson('/api/hr/training/certifications', [
        'name' => 'ISO 9001 Auditor',
        'issuing_body' => 'SIRIM',
        'validity_months' => 36,
    ]);

    $response->assertCreated();
    $certId = $response->json('data.id');

    $response = $this->actingAs($admin)->getJson('/api/hr/training/certifications');
    $response->assertSuccessful();

    $response = $this->actingAs($admin)->putJson("/api/hr/training/certifications/{$certId}", [
        'validity_months' => 24,
    ]);
    $response->assertSuccessful();
});

test('admin can add employee certification', function () {
    $setup = createTrainingSetup();
    $cert = Certification::factory()->create();

    $response = $this->actingAs($setup['admin'])->postJson('/api/hr/training/employee-certifications', [
        'employee_id' => $setup['employee']->id,
        'certification_id' => $cert->id,
        'issued_date' => '2026-01-15',
        'expiry_date' => '2028-01-15',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.status', 'active');
});

test('admin can get expiring certifications', function () {
    $admin = createTrainingAdmin();
    EmployeeCertification::factory()->expiringSoon()->create();

    $response = $this->actingAs($admin)->getJson('/api/hr/training/employee-certifications/expiring?days=90');

    $response->assertSuccessful()
        ->assertJsonCount(1, 'data');
});

test('admin can set training budget', function () {
    $setup = createTrainingSetup();

    $response = $this->actingAs($setup['admin'])->postJson('/api/hr/training/budgets', [
        'department_id' => $setup['department']->id,
        'year' => 2026,
        'allocated_amount' => 50000,
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.allocated_amount', '50000.00');
});

test('admin can get training reports', function () {
    $admin = createTrainingAdmin();

    $response = $this->actingAs($admin)->getJson('/api/hr/training/reports');

    $response->assertSuccessful()
        ->assertJsonStructure(['data' => ['year', 'total_cost', 'total_enrollments', 'attendance_rate']]);
});

test('only planned programs can be deleted', function () {
    $admin = createTrainingAdmin();
    $program = TrainingProgram::factory()->completed()->create(['created_by' => $admin->id]);

    $response = $this->actingAs($admin)->deleteJson("/api/hr/training/programs/{$program->id}");

    $response->assertUnprocessable();
});

test('employee can view own training and certifications', function () {
    $user = User::factory()->create(['role' => 'employee']);
    $department = Department::factory()->create();
    $position = Position::factory()->create(['department_id' => $department->id]);
    $employee = Employee::factory()->create([
        'user_id' => $user->id,
        'department_id' => $department->id,
        'position_id' => $position->id,
    ]);
    TrainingEnrollment::factory()->create([
        'employee_id' => $employee->id,
    ]);

    $response = $this->actingAs($user)->getJson('/api/hr/me/training');

    $response->assertSuccessful()
        ->assertJsonStructure(['data' => ['trainings', 'certifications']]);
});

test('employee can submit training feedback', function () {
    $user = User::factory()->create(['role' => 'employee']);
    $department = Department::factory()->create();
    $position = Position::factory()->create(['department_id' => $department->id]);
    $employee = Employee::factory()->create([
        'user_id' => $user->id,
        'department_id' => $department->id,
        'position_id' => $position->id,
    ]);
    $enrollment = TrainingEnrollment::factory()->create([
        'employee_id' => $employee->id,
        'status' => 'attended',
    ]);

    $response = $this->actingAs($user)->putJson("/api/hr/me/training/{$enrollment->id}/feedback", [
        'feedback' => 'Very useful training session!',
        'feedback_rating' => 5,
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('data.feedback_rating', 5);
});
```

**Step 3: Run tests**

```bash
php artisan test --compact tests/Feature/Hr/HrTrainingApiTest.php
```

**Step 4: Commit**

```bash
git add tests/Feature/Hr/HrTrainingApiTest.php
git commit -m "test(hr): add Module 9 feature tests — training, certifications, budgets, self-service"
```

---

## Tasks 27-40: React Frontend

Due to the large size of the React frontend (45+ files), these tasks are grouped by module. Each task creates 2-4 page components following the established pattern from `EmployeeList.jsx`.

**Task 27:** Add API client functions to `resources/js/hr/lib/api.js` (~70 new functions for disciplinary, offboarding, training, self-service)

**Tasks 28-30:** Disciplinary pages — `DisciplinaryDashboard.jsx`, `DisciplinaryRecords.jsx`, `DisciplinaryDetail.jsx`, `CreateDisciplinaryAction.jsx`, `InquiryManagement.jsx`

**Tasks 31-33:** Offboarding pages — `ResignationRequests.jsx`, `ResignationDetail.jsx`, `ExitChecklists.jsx`, `ExitInterviews.jsx`, `FinalSettlements.jsx`, `LetterTemplates.jsx`

**Tasks 34-36:** Training pages — `TrainingDashboard.jsx`, `TrainingPrograms.jsx`, `TrainingDetail.jsx`, `Certifications.jsx`, `EmployeeCertifications.jsx`, `TrainingBudgets.jsx`, `TrainingReports.jsx`

**Task 37:** Certification Expiry page — `CertExpiryAlerts.jsx` (badge-based warning list)

**Tasks 38-39:** Self-service pages — `MyDisciplinary.jsx`, `SubmitResignation.jsx`, `MyTraining.jsx`, `MyOnboarding.jsx`

**Task 40:** Update `App.jsx` router with all new routes

> **For the implementing engineer:** Follow the exact same patterns from existing pages like `EmployeeList.jsx`, `ClaimsDashboard.jsx`, etc. Use `useQuery` from TanStack Query, Shadcn Card/Table/Badge components, `PageHeader` component, loading skeletons, and the established search/filter/pagination pattern.

---

## Task 41: Integration Tests

**Files:**
- Create: `tests/Feature/Hr/HrPhase5IntegrationTest.php`

Test the full disciplinary escalation: create verbal warning → issue → create written warning (link to previous) → create show cause → employee responds → schedule inquiry → complete with decision.

Test the full offboarding pipeline: submit resignation → approve → verify checklist created → update checklist items → conduct exit interview → calculate final settlement → approve → mark paid → complete → verify employee status = resigned.

Test the training lifecycle: create program → enroll employees → mark attendance → submit feedback → add costs → complete program.

**Step 1: Run all HR tests**

```bash
php artisan test --compact tests/Feature/Hr/
```

---

## Task 42: Final Verification

**Step 1: Run full test suite**

```bash
php artisan test --compact
```

**Step 2: Run Pint**

```bash
vendor/bin/pint --dirty
```

**Step 3: Commit any fixes**

```bash
git add -A
git commit -m "feat(hr): complete Phase 5 — disciplinary, offboarding, training & development"
```

---

## Summary

| Task | Description | Files |
|------|-------------|-------|
| 1-2 | Disciplinary & offboarding migrations (8 tables) | 8 |
| 3-4 | Models + factories (8 models, 8 factories) | 16 |
| 5 | Letter template seeder | 1 |
| 6-11 | Controllers + form requests (14 controllers) | 15 |
| 12 | Final settlement PDF view | 1 |
| 13 | Module 10 routes | 1 |
| 14 | Module 10 feature tests | 2 |
| 15-16 | Training migrations (6 tables) | 6 |
| 17-18 | Training models + factories (6 models, 6 factories) | 12 |
| 19 | Certification seeder | 1 |
| 20-24 | Training controllers (9 controllers) | 9 |
| 25 | Module 9 routes | 1 |
| 26 | Module 9 feature tests | 1 |
| 27-40 | React frontend (API client, pages, router) | ~45 |
| 41-42 | Integration tests + verification | 2 |
| **Total** | | **~120** |
