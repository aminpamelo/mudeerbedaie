# HR Phase 4: Recruitment, Onboarding & Performance — Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Build Module 7 (Recruitment & Onboarding) and Module 8 (Performance Management) — adding ATS with public careers page, onboarding checklists, configurable review cycles, KPI-based scoring, and PIP tracking.

**Architecture:** React 19 SPA at `/hr/*` with Laravel 12 JSON API at `/api/hr/*`. Follows existing patterns from Phases 1-3: models in `app/Models/`, controllers in `app/Http/Controllers/Api/Hr/`, React pages in `resources/js/hr/pages/`, API client in `resources/js/hr/lib/api.js`. All models use `HasFactory`, `$fillable`, `casts()` method, typed relationships, and query scopes.

**Tech Stack:** Laravel 12 (PHP 8.3) · React 19 + Shadcn/ui + Tailwind CSS v4 · TanStack Query · Laravel DomPDF · Pest

**Design Docs:**
- [Module 7 — Recruitment & Onboarding](2026-03-28-hr-module7-recruitment-onboarding-design.md)
- [Module 8 — Performance Management](2026-03-28-hr-module8-performance-management-design.md)

---

## Build Order Overview

```
Module 7 — Recruitment & Onboarding (~55 files)
  Tasks 1-3:   Migrations (8 tables)
  Tasks 4-5:   Models (8 models + factories)
  Task 6:      Seeder
  Tasks 7-12:  Controllers + Form Requests (12 controllers)
  Task 13:     Routes
  Task 14:     Feature Tests
  Task 15:     PDF generation (offer letter)

Module 8 — Performance Management (~45 files)
  Tasks 16-17: Migrations (7 tables)
  Tasks 18-19: Models (7 models + factories)
  Task 20:     Seeder (rating scales)
  Tasks 21-26: Controllers + Form Requests (10 controllers)
  Task 27:     Routes
  Task 28:     Feature Tests

React Frontend (~40 files)
  Task 29:     API client functions (~60 new functions)
  Tasks 30-35: Recruitment pages (8 pages)
  Tasks 36-39: Performance pages (8 pages)
  Tasks 40-41: Employee self-service pages (4 pages)
  Task 42:     Router update (App.jsx)

Integration
  Task 43:     Integration tests
  Task 44:     Seeder update + final verification
```

---

## Task 1: Create Recruitment Migrations (Part 1)

**Files:**
- Create: `database/migrations/2026_03_28_100001_create_job_postings_table.php`
- Create: `database/migrations/2026_03_28_100002_create_applicants_table.php`
- Create: `database/migrations/2026_03_28_100003_create_applicant_stages_table.php`
- Create: `database/migrations/2026_03_28_100004_create_interviews_table.php`

**Step 1: Generate migrations**

```bash
php artisan make:migration create_job_postings_table --no-interaction
php artisan make:migration create_applicants_table --no-interaction
php artisan make:migration create_applicant_stages_table --no-interaction
php artisan make:migration create_interviews_table --no-interaction
```

**Step 2: Implement job_postings migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_postings', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->foreignId('department_id')->constrained('departments')->cascadeOnDelete();
            $table->foreignId('position_id')->nullable()->constrained('positions')->nullOnDelete();
            $table->text('description');
            $table->text('requirements');
            $table->enum('employment_type', ['full_time', 'part_time', 'contract', 'intern']);
            $table->decimal('salary_range_min', 10, 2)->nullable();
            $table->decimal('salary_range_max', 10, 2)->nullable();
            $table->boolean('show_salary')->default(false);
            $table->integer('vacancies')->default(1);
            $table->enum('status', ['draft', 'open', 'closed', 'filled'])->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->date('closing_date')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_postings');
    }
};
```

**Step 3: Implement applicants migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('applicants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_posting_id')->constrained('job_postings')->cascadeOnDelete();
            $table->string('applicant_number')->unique();
            $table->string('full_name');
            $table->string('email');
            $table->string('phone');
            $table->string('ic_number')->nullable();
            $table->string('resume_path');
            $table->text('cover_letter')->nullable();
            $table->enum('source', ['website', 'referral', 'jobstreet', 'linkedin', 'walk_in', 'other'])->default('website');
            $table->enum('current_stage', ['applied', 'screening', 'interview', 'assessment', 'offer', 'hired', 'rejected', 'withdrawn'])->default('applied');
            $table->integer('rating')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('applied_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('applicants');
    }
};
```

**Step 4: Implement applicant_stages migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('applicant_stages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('applicant_id')->constrained('applicants')->cascadeOnDelete();
            $table->enum('stage', ['applied', 'screening', 'interview', 'assessment', 'offer', 'hired', 'rejected', 'withdrawn']);
            $table->text('notes')->nullable();
            $table->foreignId('changed_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('applicant_stages');
    }
};
```

**Step 5: Implement interviews migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('interviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('applicant_id')->constrained('applicants')->cascadeOnDelete();
            $table->foreignId('interviewer_id')->constrained('employees')->cascadeOnDelete();
            $table->date('interview_date');
            $table->time('start_time');
            $table->time('end_time');
            $table->enum('type', ['phone', 'video', 'in_person']);
            $table->string('location')->nullable();
            $table->enum('status', ['scheduled', 'completed', 'cancelled', 'no_show'])->default('scheduled');
            $table->text('feedback')->nullable();
            $table->integer('rating')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('interviews');
    }
};
```

**Step 6: Run migrations**

```bash
php artisan migrate --no-interaction
```

**Step 7: Commit**

```bash
git add database/migrations/
git commit -m "feat(hr): add recruitment migrations — job_postings, applicants, applicant_stages, interviews"
```

---

## Task 2: Create Recruitment Migrations (Part 2)

**Files:**
- Create: `database/migrations/2026_03_28_100005_create_offer_letters_table.php`
- Create: `database/migrations/2026_03_28_100006_create_onboarding_templates_table.php`
- Create: `database/migrations/2026_03_28_100007_create_onboarding_template_items_table.php`
- Create: `database/migration/2026_03_28_100008_create_onboarding_tasks_table.php`

**Step 1: Generate migrations**

```bash
php artisan make:migration create_offer_letters_table --no-interaction
php artisan make:migration create_onboarding_templates_table --no-interaction
php artisan make:migration create_onboarding_template_items_table --no-interaction
php artisan make:migration create_onboarding_tasks_table --no-interaction
```

**Step 2: Implement offer_letters migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offer_letters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('applicant_id')->constrained('applicants')->cascadeOnDelete();
            $table->foreignId('position_id')->constrained('positions')->cascadeOnDelete();
            $table->decimal('offered_salary', 10, 2);
            $table->date('start_date');
            $table->enum('employment_type', ['full_time', 'part_time', 'contract', 'intern']);
            $table->enum('status', ['draft', 'sent', 'accepted', 'rejected', 'expired'])->default('draft');
            $table->json('template_data')->nullable();
            $table->string('pdf_path')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offer_letters');
    }
};
```

**Step 3: Implement onboarding_templates migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('onboarding_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('onboarding_templates');
    }
};
```

**Step 4: Implement onboarding_template_items migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('onboarding_template_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('onboarding_template_id')->constrained('onboarding_templates')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('assigned_role')->nullable();
            $table->integer('due_days')->default(7);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('onboarding_template_items');
    }
};
```

**Step 5: Implement onboarding_tasks migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('onboarding_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('template_item_id')->nullable()->constrained('onboarding_template_items')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('employees')->nullOnDelete();
            $table->date('due_date');
            $table->enum('status', ['pending', 'in_progress', 'completed', 'skipped'])->default('pending');
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('onboarding_tasks');
    }
};
```

**Step 6: Run migrations**

```bash
php artisan migrate --no-interaction
```

**Step 7: Commit**

```bash
git add database/migrations/
git commit -m "feat(hr): add onboarding migrations — offer_letters, onboarding_templates, template_items, tasks"
```

---

## Task 3: Create Performance Management Migrations

**Files:**
- Create: `database/migrations/2026_03_28_100009_create_review_cycles_table.php`
- Create: `database/migrations/2026_03_28_100010_create_kpi_templates_table.php`
- Create: `database/migrations/2026_03_28_100011_create_performance_reviews_table.php`
- Create: `database/migrations/2026_03_28_100012_create_review_kpis_table.php`
- Create: `database/migrations/2026_03_28_100013_create_rating_scales_table.php`
- Create: `database/migrations/2026_03_28_100014_create_performance_improvement_plans_table.php`
- Create: `database/migrations/2026_03_28_100015_create_pip_goals_table.php`

**Step 1: Generate all 7 migrations**

```bash
php artisan make:migration create_review_cycles_table --no-interaction
php artisan make:migration create_kpi_templates_table --no-interaction
php artisan make:migration create_performance_reviews_table --no-interaction
php artisan make:migration create_review_kpis_table --no-interaction
php artisan make:migration create_rating_scales_table --no-interaction
php artisan make:migration create_performance_improvement_plans_table --no-interaction
php artisan make:migration create_pip_goals_table --no-interaction
```

**Step 2: Implement review_cycles migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('review_cycles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('type', ['monthly', 'quarterly', 'semi_annual', 'annual']);
            $table->date('start_date');
            $table->date('end_date');
            $table->date('submission_deadline');
            $table->enum('status', ['draft', 'active', 'in_review', 'completed', 'cancelled'])->default('draft');
            $table->text('description')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('review_cycles');
    }
};
```

**Step 3: Implement kpi_templates migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kpi_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('position_id')->nullable()->constrained('positions')->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('target');
            $table->decimal('weight', 5, 2);
            $table->enum('category', ['quantitative', 'qualitative', 'behavioral']);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kpi_templates');
    }
};
```

**Step 4: Implement performance_reviews migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('performance_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('review_cycle_id')->constrained('review_cycles')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('reviewer_id')->constrained('employees')->cascadeOnDelete();
            $table->enum('status', ['draft', 'self_assessment', 'manager_review', 'completed'])->default('draft');
            $table->text('self_assessment_notes')->nullable();
            $table->text('manager_notes')->nullable();
            $table->decimal('overall_rating', 3, 1)->nullable();
            $table->string('rating_label')->nullable();
            $table->boolean('employee_acknowledged')->default(false);
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['review_cycle_id', 'employee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('performance_reviews');
    }
};
```

**Step 5: Implement review_kpis migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('review_kpis', function (Blueprint $table) {
            $table->id();
            $table->foreignId('performance_review_id')->constrained('performance_reviews')->cascadeOnDelete();
            $table->foreignId('kpi_template_id')->nullable()->constrained('kpi_templates')->nullOnDelete();
            $table->string('title');
            $table->string('target');
            $table->decimal('weight', 5, 2);
            $table->integer('self_score')->nullable();
            $table->text('self_comments')->nullable();
            $table->integer('manager_score')->nullable();
            $table->text('manager_comments')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('review_kpis');
    }
};
```

**Step 6: Implement rating_scales migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rating_scales', function (Blueprint $table) {
            $table->id();
            $table->integer('score')->unique();
            $table->string('label');
            $table->text('description')->nullable();
            $table->string('color');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rating_scales');
    }
};
```

**Step 7: Implement performance_improvement_plans migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('performance_improvement_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('initiated_by')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('performance_review_id')->nullable()->constrained('performance_reviews')->nullOnDelete();
            $table->text('reason');
            $table->date('start_date');
            $table->date('end_date');
            $table->enum('status', ['active', 'extended', 'completed_improved', 'completed_not_improved', 'cancelled'])->default('active');
            $table->text('outcome_notes')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('performance_improvement_plans');
    }
};
```

**Step 8: Implement pip_goals migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pip_goals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pip_id')->constrained('performance_improvement_plans')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->date('target_date');
            $table->enum('status', ['pending', 'in_progress', 'achieved', 'not_achieved'])->default('pending');
            $table->text('check_in_notes')->nullable();
            $table->timestamp('checked_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pip_goals');
    }
};
```

**Step 9: Run migrations**

```bash
php artisan migrate --no-interaction
```

**Step 10: Commit**

```bash
git add database/migrations/
git commit -m "feat(hr): add performance management migrations — review_cycles, kpi_templates, reviews, rating_scales, PIPs"
```

---

## Task 4: Create Recruitment Models + Factories

**Files:**
- Create: `app/Models/JobPosting.php`
- Create: `app/Models/Applicant.php`
- Create: `app/Models/ApplicantStage.php`
- Create: `app/Models/Interview.php`
- Create: `app/Models/OfferLetter.php`
- Create: `app/Models/OnboardingTemplate.php`
- Create: `app/Models/OnboardingTemplateItem.php`
- Create: `app/Models/OnboardingTask.php`
- Create: `database/factories/JobPostingFactory.php`
- Create: `database/factories/ApplicantFactory.php`
- Create: `database/factories/InterviewFactory.php`
- Create: `database/factories/OfferLetterFactory.php`
- Create: `database/factories/OnboardingTemplateFactory.php`

**Step 1: Generate models and factories**

```bash
php artisan make:model JobPosting -f --no-interaction
php artisan make:model Applicant -f --no-interaction
php artisan make:model ApplicantStage --no-interaction
php artisan make:model Interview -f --no-interaction
php artisan make:model OfferLetter -f --no-interaction
php artisan make:model OnboardingTemplate -f --no-interaction
php artisan make:model OnboardingTemplateItem --no-interaction
php artisan make:model OnboardingTask --no-interaction
```

**Step 2: Implement JobPosting model**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JobPosting extends Model
{
    /** @use HasFactory<\Database\Factories\JobPostingFactory> */
    use HasFactory;

    protected $fillable = [
        'title',
        'department_id',
        'position_id',
        'description',
        'requirements',
        'employment_type',
        'salary_range_min',
        'salary_range_max',
        'show_salary',
        'vacancies',
        'status',
        'published_at',
        'closing_date',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'salary_range_min' => 'decimal:2',
            'salary_range_max' => 'decimal:2',
            'show_salary' => 'boolean',
            'published_at' => 'datetime',
            'closing_date' => 'date',
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function applicants(): HasMany
    {
        return $this->hasMany(Applicant::class);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', 'open');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'open')->whereNotNull('published_at');
    }
}
```

**Step 3: Implement Applicant model**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Applicant extends Model
{
    /** @use HasFactory<\Database\Factories\ApplicantFactory> */
    use HasFactory;

    protected $fillable = [
        'job_posting_id',
        'applicant_number',
        'full_name',
        'email',
        'phone',
        'ic_number',
        'resume_path',
        'cover_letter',
        'source',
        'current_stage',
        'rating',
        'notes',
        'applied_at',
    ];

    protected function casts(): array
    {
        return [
            'applied_at' => 'datetime',
        ];
    }

    public function jobPosting(): BelongsTo
    {
        return $this->belongsTo(JobPosting::class);
    }

    public function stages(): HasMany
    {
        return $this->hasMany(ApplicantStage::class);
    }

    public function interviews(): HasMany
    {
        return $this->hasMany(Interview::class);
    }

    public function offerLetter(): HasOne
    {
        return $this->hasOne(OfferLetter::class);
    }

    public function scopeAtStage(Builder $query, string $stage): Builder
    {
        return $query->where('current_stage', $stage);
    }

    /**
     * Generate the next applicant number in APP-YYYYMM-0001 format.
     */
    public static function generateApplicantNumber(): string
    {
        $yearMonth = now()->format('Ym');
        $prefix = "APP-{$yearMonth}-";

        $last = static::query()
            ->where('applicant_number', 'like', $prefix.'%')
            ->orderByDesc('applicant_number')
            ->first();

        if ($last) {
            $lastNumber = (int) substr($last->applicant_number, -4);
            $nextNumber = $lastNumber + 1;
        } else {
            $nextNumber = 1;
        }

        return $prefix.str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
    }
}
```

**Step 4: Implement ApplicantStage model**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApplicantStage extends Model
{
    protected $fillable = [
        'applicant_id',
        'stage',
        'notes',
        'changed_by',
    ];

    public function applicant(): BelongsTo
    {
        return $this->belongsTo(Applicant::class);
    }

    public function changedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
```

**Step 5: Implement Interview model**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Interview extends Model
{
    /** @use HasFactory<\Database\Factories\InterviewFactory> */
    use HasFactory;

    protected $fillable = [
        'applicant_id',
        'interviewer_id',
        'interview_date',
        'start_time',
        'end_time',
        'type',
        'location',
        'status',
        'feedback',
        'rating',
    ];

    protected function casts(): array
    {
        return [
            'interview_date' => 'date',
        ];
    }

    public function applicant(): BelongsTo
    {
        return $this->belongsTo(Applicant::class);
    }

    public function interviewer(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'interviewer_id');
    }
}
```

**Step 6: Implement OfferLetter model**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OfferLetter extends Model
{
    /** @use HasFactory<\Database\Factories\OfferLetterFactory> */
    use HasFactory;

    protected $fillable = [
        'applicant_id',
        'position_id',
        'offered_salary',
        'start_date',
        'employment_type',
        'status',
        'template_data',
        'pdf_path',
        'sent_at',
        'responded_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'offered_salary' => 'decimal:2',
            'start_date' => 'date',
            'template_data' => 'array',
            'sent_at' => 'datetime',
            'responded_at' => 'datetime',
        ];
    }

    public function applicant(): BelongsTo
    {
        return $this->belongsTo(Applicant::class);
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
```

**Step 7: Implement OnboardingTemplate, OnboardingTemplateItem, OnboardingTask models**

```php
<?php
// app/Models/OnboardingTemplate.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OnboardingTemplate extends Model
{
    /** @use HasFactory<\Database\Factories\OnboardingTemplateFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'department_id',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OnboardingTemplateItem::class)->orderBy('sort_order');
    }
}
```

```php
<?php
// app/Models/OnboardingTemplateItem.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OnboardingTemplateItem extends Model
{
    protected $fillable = [
        'onboarding_template_id',
        'title',
        'description',
        'assigned_role',
        'due_days',
        'sort_order',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(OnboardingTemplate::class, 'onboarding_template_id');
    }
}
```

```php
<?php
// app/Models/OnboardingTask.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OnboardingTask extends Model
{
    protected $fillable = [
        'employee_id',
        'template_item_id',
        'title',
        'description',
        'assigned_to',
        'due_date',
        'status',
        'completed_at',
        'completed_by',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'completed_at' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function assignedEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'assigned_to');
    }
}
```

**Step 8: Implement factories**

```php
<?php
// database/factories/JobPostingFactory.php

namespace Database\Factories;

use App\Models\Department;
use App\Models\Position;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class JobPostingFactory extends Factory
{
    public function definition(): array
    {
        return [
            'title' => fake()->jobTitle(),
            'department_id' => Department::factory(),
            'position_id' => Position::factory(),
            'description' => fake()->paragraphs(3, true),
            'requirements' => fake()->paragraphs(2, true),
            'employment_type' => fake()->randomElement(['full_time', 'part_time', 'contract', 'intern']),
            'salary_range_min' => fake()->numberBetween(2000, 5000),
            'salary_range_max' => fake()->numberBetween(5000, 15000),
            'show_salary' => fake()->boolean(),
            'vacancies' => fake()->numberBetween(1, 5),
            'status' => 'draft',
            'created_by' => User::factory(),
        ];
    }

    public function open(): static
    {
        return $this->state(fn () => [
            'status' => 'open',
            'published_at' => now(),
        ]);
    }
}
```

```php
<?php
// database/factories/ApplicantFactory.php

namespace Database\Factories;

use App\Models\Applicant;
use App\Models\JobPosting;
use Illuminate\Database\Eloquent\Factories\Factory;

class ApplicantFactory extends Factory
{
    public function definition(): array
    {
        return [
            'job_posting_id' => JobPosting::factory(),
            'applicant_number' => Applicant::generateApplicantNumber(),
            'full_name' => fake()->name(),
            'email' => fake()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'ic_number' => fake()->numerify('######-##-####'),
            'resume_path' => 'resumes/'.fake()->uuid().'.pdf',
            'source' => fake()->randomElement(['website', 'referral', 'jobstreet', 'linkedin', 'walk_in']),
            'current_stage' => 'applied',
            'applied_at' => now(),
        ];
    }
}
```

```php
<?php
// database/factories/InterviewFactory.php

namespace Database\Factories;

use App\Models\Applicant;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

class InterviewFactory extends Factory
{
    public function definition(): array
    {
        return [
            'applicant_id' => Applicant::factory(),
            'interviewer_id' => Employee::factory(),
            'interview_date' => fake()->dateTimeBetween('+1 day', '+30 days'),
            'start_time' => '10:00',
            'end_time' => '11:00',
            'type' => fake()->randomElement(['phone', 'video', 'in_person']),
            'status' => 'scheduled',
        ];
    }
}
```

```php
<?php
// database/factories/OfferLetterFactory.php

namespace Database\Factories;

use App\Models\Applicant;
use App\Models\Position;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class OfferLetterFactory extends Factory
{
    public function definition(): array
    {
        return [
            'applicant_id' => Applicant::factory(),
            'position_id' => Position::factory(),
            'offered_salary' => fake()->numberBetween(3000, 12000),
            'start_date' => fake()->dateTimeBetween('+14 days', '+60 days'),
            'employment_type' => 'full_time',
            'status' => 'draft',
            'created_by' => User::factory(),
        ];
    }
}
```

```php
<?php
// database/factories/OnboardingTemplateFactory.php

namespace Database\Factories;

use App\Models\Department;
use Illuminate\Database\Eloquent\Factories\Factory;

class OnboardingTemplateFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true).' Onboarding',
            'department_id' => Department::factory(),
            'is_active' => true,
        ];
    }
}
```

**Step 9: Commit**

```bash
git add app/Models/ database/factories/
git commit -m "feat(hr): add recruitment & onboarding models with factories"
```

---

## Task 5: Create Performance Management Models + Factories

**Files:**
- Create: `app/Models/ReviewCycle.php`
- Create: `app/Models/KpiTemplate.php`
- Create: `app/Models/PerformanceReview.php`
- Create: `app/Models/ReviewKpi.php`
- Create: `app/Models/RatingScale.php`
- Create: `app/Models/PerformanceImprovementPlan.php`
- Create: `app/Models/PipGoal.php`
- Create: `database/factories/ReviewCycleFactory.php`
- Create: `database/factories/KpiTemplateFactory.php`
- Create: `database/factories/PerformanceReviewFactory.php`
- Create: `database/factories/PerformanceImprovementPlanFactory.php`

**Step 1: Generate models and factories**

```bash
php artisan make:model ReviewCycle -f --no-interaction
php artisan make:model KpiTemplate -f --no-interaction
php artisan make:model PerformanceReview -f --no-interaction
php artisan make:model ReviewKpi --no-interaction
php artisan make:model RatingScale --no-interaction
php artisan make:model PerformanceImprovementPlan -f --no-interaction
php artisan make:model PipGoal --no-interaction
```

**Step 2: Implement ReviewCycle model**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReviewCycle extends Model
{
    /** @use HasFactory<\Database\Factories\ReviewCycleFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'type',
        'start_date',
        'end_date',
        'submission_deadline',
        'status',
        'description',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'submission_deadline' => 'date',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(PerformanceReview::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }
}
```

**Step 3: Implement KpiTemplate model**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KpiTemplate extends Model
{
    /** @use HasFactory<\Database\Factories\KpiTemplateFactory> */
    use HasFactory;

    protected $fillable = [
        'position_id',
        'department_id',
        'title',
        'description',
        'target',
        'weight',
        'category',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'weight' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }
}
```

**Step 4: Implement PerformanceReview model**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PerformanceReview extends Model
{
    /** @use HasFactory<\Database\Factories\PerformanceReviewFactory> */
    use HasFactory;

    protected $fillable = [
        'review_cycle_id',
        'employee_id',
        'reviewer_id',
        'status',
        'self_assessment_notes',
        'manager_notes',
        'overall_rating',
        'rating_label',
        'employee_acknowledged',
        'acknowledged_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'overall_rating' => 'decimal:1',
            'employee_acknowledged' => 'boolean',
            'acknowledged_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function reviewCycle(): BelongsTo
    {
        return $this->belongsTo(ReviewCycle::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'reviewer_id');
    }

    public function kpis(): HasMany
    {
        return $this->hasMany(ReviewKpi::class);
    }

    /**
     * Calculate the weighted average of manager scores.
     */
    public function calculateOverallRating(): ?float
    {
        $kpis = $this->kpis()->whereNotNull('manager_score')->get();

        if ($kpis->isEmpty()) {
            return null;
        }

        $totalWeightedScore = $kpis->sum(fn ($kpi) => $kpi->manager_score * ($kpi->weight / 100));
        $totalWeight = $kpis->sum(fn ($kpi) => $kpi->weight / 100);

        if ($totalWeight === 0.0) {
            return null;
        }

        return round($totalWeightedScore / $totalWeight, 1);
    }
}
```

**Step 5: Implement ReviewKpi model**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReviewKpi extends Model
{
    protected $fillable = [
        'performance_review_id',
        'kpi_template_id',
        'title',
        'target',
        'weight',
        'self_score',
        'self_comments',
        'manager_score',
        'manager_comments',
    ];

    protected function casts(): array
    {
        return [
            'weight' => 'decimal:2',
        ];
    }

    public function performanceReview(): BelongsTo
    {
        return $this->belongsTo(PerformanceReview::class);
    }

    public function kpiTemplate(): BelongsTo
    {
        return $this->belongsTo(KpiTemplate::class);
    }
}
```

**Step 6: Implement RatingScale model**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RatingScale extends Model
{
    protected $fillable = [
        'score',
        'label',
        'description',
        'color',
    ];
}
```

**Step 7: Implement PerformanceImprovementPlan + PipGoal models**

```php
<?php
// app/Models/PerformanceImprovementPlan.php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PerformanceImprovementPlan extends Model
{
    /** @use HasFactory<\Database\Factories\PerformanceImprovementPlanFactory> */
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'initiated_by',
        'performance_review_id',
        'reason',
        'start_date',
        'end_date',
        'status',
        'outcome_notes',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'completed_at' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function initiator(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'initiated_by');
    }

    public function performanceReview(): BelongsTo
    {
        return $this->belongsTo(PerformanceReview::class);
    }

    public function goals(): HasMany
    {
        return $this->hasMany(PipGoal::class, 'pip_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }
}
```

```php
<?php
// app/Models/PipGoal.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PipGoal extends Model
{
    protected $fillable = [
        'pip_id',
        'title',
        'description',
        'target_date',
        'status',
        'check_in_notes',
        'checked_at',
    ];

    protected function casts(): array
    {
        return [
            'target_date' => 'date',
            'checked_at' => 'datetime',
        ];
    }

    public function pip(): BelongsTo
    {
        return $this->belongsTo(PerformanceImprovementPlan::class, 'pip_id');
    }
}
```

**Step 8: Implement factories**

```php
<?php
// database/factories/ReviewCycleFactory.php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ReviewCycleFactory extends Factory
{
    public function definition(): array
    {
        $startDate = fake()->dateTimeBetween('-3 months', 'now');
        $endDate = (clone $startDate)->modify('+3 months');

        return [
            'name' => 'Q'.fake()->numberBetween(1, 4).' '.date('Y').' Review',
            'type' => fake()->randomElement(['monthly', 'quarterly', 'semi_annual', 'annual']),
            'start_date' => $startDate,
            'end_date' => $endDate,
            'submission_deadline' => (clone $endDate)->modify('+14 days'),
            'status' => 'draft',
            'created_by' => User::factory(),
        ];
    }
}
```

```php
<?php
// database/factories/KpiTemplateFactory.php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class KpiTemplateFactory extends Factory
{
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(4),
            'target' => fake()->randomElement(['90% completion', 'RM500k revenue', '95% accuracy', '4.0 rating']),
            'weight' => fake()->randomElement([10, 15, 20, 25, 30]),
            'category' => fake()->randomElement(['quantitative', 'qualitative', 'behavioral']),
            'is_active' => true,
        ];
    }
}
```

```php
<?php
// database/factories/PerformanceReviewFactory.php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\ReviewCycle;
use Illuminate\Database\Eloquent\Factories\Factory;

class PerformanceReviewFactory extends Factory
{
    public function definition(): array
    {
        return [
            'review_cycle_id' => ReviewCycle::factory(),
            'employee_id' => Employee::factory(),
            'reviewer_id' => Employee::factory(),
            'status' => 'draft',
        ];
    }
}
```

```php
<?php
// database/factories/PerformanceImprovementPlanFactory.php

namespace Database\Factories;

use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

class PerformanceImprovementPlanFactory extends Factory
{
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'initiated_by' => Employee::factory(),
            'reason' => fake()->paragraph(),
            'start_date' => now(),
            'end_date' => now()->addMonths(3),
            'status' => 'active',
        ];
    }
}
```

**Step 9: Commit**

```bash
git add app/Models/ database/factories/
git commit -m "feat(hr): add performance management models with factories — reviews, KPIs, PIPs, rating scales"
```

---

## Task 6: Create Seeders

**Files:**
- Create: `database/seeders/HrRecruitmentSeeder.php`
- Create: `database/seeders/HrPerformanceSeeder.php`

**Step 1: Create seeders**

```bash
php artisan make:seeder HrRecruitmentSeeder --no-interaction
php artisan make:seeder HrPerformanceSeeder --no-interaction
```

**Step 2: Implement HrRecruitmentSeeder** — seed default onboarding template + sample job postings

```php
<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\OnboardingTemplate;
use App\Models\OnboardingTemplateItem;
use Illuminate\Database\Seeder;

class HrRecruitmentSeeder extends Seeder
{
    public function run(): void
    {
        // Default onboarding template (applies to all departments)
        $template = OnboardingTemplate::create([
            'name' => 'Default Onboarding Checklist',
            'department_id' => null,
            'is_active' => true,
        ]);

        $items = [
            ['title' => 'Setup workstation/laptop', 'assigned_role' => 'IT', 'due_days' => 1, 'sort_order' => 1],
            ['title' => 'Create email account', 'assigned_role' => 'IT', 'due_days' => 1, 'sort_order' => 2],
            ['title' => 'System access setup', 'assigned_role' => 'IT', 'due_days' => 1, 'sort_order' => 3],
            ['title' => 'Issue access card', 'assigned_role' => 'Admin', 'due_days' => 1, 'sort_order' => 4],
            ['title' => 'HR orientation briefing', 'assigned_role' => 'HR', 'due_days' => 1, 'sort_order' => 5],
            ['title' => 'Sign employment contract', 'assigned_role' => 'HR', 'due_days' => 1, 'sort_order' => 6],
            ['title' => 'Submit personal documents (IC, bank details, EPF)', 'assigned_role' => 'HR', 'due_days' => 3, 'sort_order' => 7],
            ['title' => 'Department introduction & team meet', 'assigned_role' => 'Manager', 'due_days' => 1, 'sort_order' => 8],
            ['title' => 'Role briefing & KPIs review', 'assigned_role' => 'Manager', 'due_days' => 3, 'sort_order' => 9],
            ['title' => 'Safety & compliance training', 'assigned_role' => 'HR', 'due_days' => 7, 'sort_order' => 10],
            ['title' => 'Probation review schedule confirmed', 'assigned_role' => 'HR', 'due_days' => 7, 'sort_order' => 11],
        ];

        foreach ($items as $item) {
            OnboardingTemplateItem::create(array_merge($item, [
                'onboarding_template_id' => $template->id,
            ]));
        }
    }
}
```

**Step 3: Implement HrPerformanceSeeder** — seed default rating scales

```php
<?php

namespace Database\Seeders;

use App\Models\RatingScale;
use Illuminate\Database\Seeder;

class HrPerformanceSeeder extends Seeder
{
    public function run(): void
    {
        $scales = [
            ['score' => 1, 'label' => 'Unsatisfactory', 'description' => 'Performance is significantly below expectations', 'color' => '#EF4444'],
            ['score' => 2, 'label' => 'Needs Improvement', 'description' => 'Performance does not consistently meet expectations', 'color' => '#F97316'],
            ['score' => 3, 'label' => 'Meets Expectations', 'description' => 'Performance consistently meets job requirements', 'color' => '#EAB308'],
            ['score' => 4, 'label' => 'Exceeds Expectations', 'description' => 'Performance frequently exceeds job requirements', 'color' => '#22C55E'],
            ['score' => 5, 'label' => 'Outstanding', 'description' => 'Performance is exceptional in all areas', 'color' => '#3B82F6'],
        ];

        foreach ($scales as $scale) {
            RatingScale::updateOrCreate(['score' => $scale['score']], $scale);
        }
    }
}
```

**Step 4: Run seeders**

```bash
php artisan db:seed --class=HrRecruitmentSeeder --no-interaction
php artisan db:seed --class=HrPerformanceSeeder --no-interaction
```

**Step 5: Commit**

```bash
git add database/seeders/
git commit -m "feat(hr): add recruitment & performance seeders — onboarding template, rating scales"
```

---

## Task 7: Recruitment Controllers — Job Postings & Dashboard

**Files:**
- Create: `app/Http/Controllers/Api/Hr/HrRecruitmentDashboardController.php`
- Create: `app/Http/Controllers/Api/Hr/HrJobPostingController.php`
- Create: `app/Http/Requests/Hr/StoreJobPostingRequest.php`
- Create: `app/Http/Requests/Hr/UpdateJobPostingRequest.php`

**Step 1: Generate files**

```bash
php artisan make:controller Api/Hr/HrRecruitmentDashboardController --no-interaction
php artisan make:controller Api/Hr/HrJobPostingController --no-interaction
php artisan make:request Hr/StoreJobPostingRequest --no-interaction
php artisan make:request Hr/UpdateJobPostingRequest --no-interaction
```

**Step 2: Implement HrRecruitmentDashboardController**

```php
<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use App\Models\Applicant;
use App\Models\JobPosting;
use Illuminate\Http\JsonResponse;

class HrRecruitmentDashboardController extends Controller
{
    public function stats(): JsonResponse
    {
        return response()->json([
            'data' => [
                'open_positions' => JobPosting::where('status', 'open')->count(),
                'total_applicants' => Applicant::count(),
                'active_applicants' => Applicant::whereNotIn('current_stage', ['hired', 'rejected', 'withdrawn'])->count(),
                'hired_this_month' => Applicant::where('current_stage', 'hired')
                    ->whereMonth('updated_at', now()->month)
                    ->whereYear('updated_at', now()->year)
                    ->count(),
                'pipeline' => [
                    'applied' => Applicant::where('current_stage', 'applied')->count(),
                    'screening' => Applicant::where('current_stage', 'screening')->count(),
                    'interview' => Applicant::where('current_stage', 'interview')->count(),
                    'assessment' => Applicant::where('current_stage', 'assessment')->count(),
                    'offer' => Applicant::where('current_stage', 'offer')->count(),
                ],
            ],
        ]);
    }
}
```

**Step 3: Implement StoreJobPostingRequest**

```php
<?php

namespace App\Http\Requests\Hr;

use Illuminate\Foundation\Http\FormRequest;

class StoreJobPostingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->role === 'admin';
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'department_id' => ['required', 'exists:departments,id'],
            'position_id' => ['nullable', 'exists:positions,id'],
            'description' => ['required', 'string'],
            'requirements' => ['required', 'string'],
            'employment_type' => ['required', 'in:full_time,part_time,contract,intern'],
            'salary_range_min' => ['nullable', 'numeric', 'min:0'],
            'salary_range_max' => ['nullable', 'numeric', 'min:0', 'gte:salary_range_min'],
            'show_salary' => ['boolean'],
            'vacancies' => ['required', 'integer', 'min:1'],
            'closing_date' => ['nullable', 'date', 'after:today'],
        ];
    }
}
```

**Step 4: Implement HrJobPostingController**

```php
<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\StoreJobPostingRequest;
use App\Http\Requests\Hr\UpdateJobPostingRequest;
use App\Models\JobPosting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HrJobPostingController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = JobPosting::query()
            ->with(['department:id,name', 'position:id,title'])
            ->withCount('applicants');

        if ($search = $request->get('search')) {
            $query->where('title', 'like', "%{$search}%");
        }

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        if ($departmentId = $request->get('department_id')) {
            $query->where('department_id', $departmentId);
        }

        $postings = $query->orderByDesc('created_at')->paginate($request->get('per_page', 15));

        return response()->json($postings);
    }

    public function store(StoreJobPostingRequest $request): JsonResponse
    {
        $posting = JobPosting::create(array_merge(
            $request->validated(),
            ['created_by' => $request->user()->id]
        ));

        return response()->json([
            'message' => 'Job posting created successfully.',
            'data' => $posting->load(['department:id,name', 'position:id,title']),
        ], 201);
    }

    public function show(JobPosting $jobPosting): JsonResponse
    {
        return response()->json([
            'data' => $jobPosting->load([
                'department:id,name',
                'position:id,title',
                'applicants' => fn ($q) => $q->latest()->limit(50),
            ])->loadCount('applicants'),
        ]);
    }

    public function update(UpdateJobPostingRequest $request, JobPosting $jobPosting): JsonResponse
    {
        $jobPosting->update($request->validated());

        return response()->json([
            'message' => 'Job posting updated successfully.',
            'data' => $jobPosting->fresh(['department:id,name', 'position:id,title']),
        ]);
    }

    public function destroy(JobPosting $jobPosting): JsonResponse
    {
        if ($jobPosting->status !== 'draft') {
            return response()->json(['message' => 'Only draft postings can be deleted.'], 422);
        }

        $jobPosting->delete();

        return response()->json(['message' => 'Job posting deleted successfully.']);
    }

    public function publish(JobPosting $jobPosting): JsonResponse
    {
        $jobPosting->update([
            'status' => 'open',
            'published_at' => now(),
        ]);

        return response()->json([
            'message' => 'Job posting published successfully.',
            'data' => $jobPosting,
        ]);
    }

    public function close(JobPosting $jobPosting): JsonResponse
    {
        $jobPosting->update(['status' => 'closed']);

        return response()->json([
            'message' => 'Job posting closed.',
            'data' => $jobPosting,
        ]);
    }
}
```

**Step 5: Implement UpdateJobPostingRequest** — same rules as Store but all nullable

```php
<?php

namespace App\Http\Requests\Hr;

use Illuminate\Foundation\Http\FormRequest;

class UpdateJobPostingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->role === 'admin';
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'max:255'],
            'department_id' => ['sometimes', 'exists:departments,id'],
            'position_id' => ['nullable', 'exists:positions,id'],
            'description' => ['sometimes', 'string'],
            'requirements' => ['sometimes', 'string'],
            'employment_type' => ['sometimes', 'in:full_time,part_time,contract,intern'],
            'salary_range_min' => ['nullable', 'numeric', 'min:0'],
            'salary_range_max' => ['nullable', 'numeric', 'min:0'],
            'show_salary' => ['boolean'],
            'vacancies' => ['sometimes', 'integer', 'min:1'],
            'closing_date' => ['nullable', 'date'],
        ];
    }
}
```

**Step 6: Commit**

```bash
git add app/Http/Controllers/Api/Hr/HrRecruitmentDashboardController.php app/Http/Controllers/Api/Hr/HrJobPostingController.php app/Http/Requests/Hr/
git commit -m "feat(hr): add recruitment dashboard and job posting controllers"
```

---

## Task 8: Recruitment Controllers — Applicants

**Files:**
- Create: `app/Http/Controllers/Api/Hr/HrApplicantController.php`
- Create: `app/Http/Requests/Hr/StoreApplicantRequest.php`

**Step 1: Generate files**

```bash
php artisan make:controller Api/Hr/HrApplicantController --no-interaction
php artisan make:request Hr/StoreApplicantRequest --no-interaction
```

**Step 2: Implement StoreApplicantRequest**

```php
<?php

namespace App\Http\Requests\Hr;

use Illuminate\Foundation\Http\FormRequest;

class StoreApplicantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->role === 'admin';
    }

    public function rules(): array
    {
        return [
            'job_posting_id' => ['required', 'exists:job_postings,id'],
            'full_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:20'],
            'ic_number' => ['nullable', 'string', 'max:20'],
            'resume' => ['required', 'file', 'mimes:pdf,doc,docx', 'max:5120'],
            'cover_letter' => ['nullable', 'string'],
            'source' => ['required', 'in:website,referral,jobstreet,linkedin,walk_in,other'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
```

**Step 3: Implement HrApplicantController**

```php
<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\StoreApplicantRequest;
use App\Models\Applicant;
use App\Models\ApplicantStage;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class HrApplicantController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Applicant::query()
            ->with(['jobPosting:id,title']);

        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('applicant_number', 'like', "%{$search}%");
            });
        }

        if ($stage = $request->get('stage')) {
            $query->where('current_stage', $stage);
        }

        if ($jobPostingId = $request->get('job_posting_id')) {
            $query->where('job_posting_id', $jobPostingId);
        }

        if ($source = $request->get('source')) {
            $query->where('source', $source);
        }

        $applicants = $query->orderByDesc('applied_at')->paginate($request->get('per_page', 15));

        return response()->json($applicants);
    }

    public function store(StoreApplicantRequest $request): JsonResponse
    {
        return DB::transaction(function () use ($request) {
            $resumePath = $request->file('resume')->store('resumes', 'public');

            $applicant = Applicant::create([
                'job_posting_id' => $request->job_posting_id,
                'applicant_number' => Applicant::generateApplicantNumber(),
                'full_name' => $request->full_name,
                'email' => $request->email,
                'phone' => $request->phone,
                'ic_number' => $request->ic_number,
                'resume_path' => $resumePath,
                'cover_letter' => $request->cover_letter,
                'source' => $request->source,
                'current_stage' => 'applied',
                'notes' => $request->notes,
                'applied_at' => now(),
            ]);

            ApplicantStage::create([
                'applicant_id' => $applicant->id,
                'stage' => 'applied',
                'notes' => 'Application received',
                'changed_by' => $request->user()->id,
            ]);

            return response()->json([
                'message' => 'Applicant added successfully.',
                'data' => $applicant,
            ], 201);
        });
    }

    public function show(Applicant $applicant): JsonResponse
    {
        return response()->json([
            'data' => $applicant->load([
                'jobPosting:id,title,department_id',
                'jobPosting.department:id,name',
                'stages' => fn ($q) => $q->latest(),
                'stages.changedByUser:id,name',
                'interviews.interviewer:id,full_name',
                'offerLetter',
            ]),
        ]);
    }

    public function update(Request $request, Applicant $applicant): JsonResponse
    {
        $validated = $request->validate([
            'rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'notes' => ['nullable', 'string'],
        ]);

        $applicant->update($validated);

        return response()->json([
            'message' => 'Applicant updated successfully.',
            'data' => $applicant,
        ]);
    }

    public function moveStage(Request $request, Applicant $applicant): JsonResponse
    {
        $validated = $request->validate([
            'stage' => ['required', 'in:applied,screening,interview,assessment,offer,hired,rejected,withdrawn'],
            'notes' => ['nullable', 'string'],
        ]);

        return DB::transaction(function () use ($validated, $applicant, $request) {
            $applicant->update(['current_stage' => $validated['stage']]);

            ApplicantStage::create([
                'applicant_id' => $applicant->id,
                'stage' => $validated['stage'],
                'notes' => $validated['notes'] ?? null,
                'changed_by' => $request->user()->id,
            ]);

            return response()->json([
                'message' => 'Applicant moved to '.$validated['stage'].' stage.',
                'data' => $applicant->fresh(),
            ]);
        });
    }

    public function hire(Request $request, Applicant $applicant): JsonResponse
    {
        $validated = $request->validate([
            'department_id' => ['required', 'exists:departments,id'],
            'position_id' => ['required', 'exists:positions,id'],
            'employment_type' => ['required', 'in:full_time,part_time,contract,intern'],
            'join_date' => ['required', 'date'],
            'basic_salary' => ['required', 'numeric', 'min:0'],
        ]);

        return DB::transaction(function () use ($validated, $applicant, $request) {
            // Create user account
            $user = User::create([
                'name' => $applicant->full_name,
                'email' => $applicant->email,
                'password' => bcrypt('password'),
                'role' => 'employee',
            ]);

            // Generate employee ID
            $lastEmployee = Employee::orderByDesc('employee_id')->first();
            $lastNum = $lastEmployee ? (int) substr($lastEmployee->employee_id, 4) : 0;
            $employeeId = 'BDE-'.str_pad($lastNum + 1, 4, '0', STR_PAD_LEFT);

            // Create employee record
            $employee = Employee::create([
                'user_id' => $user->id,
                'employee_id' => $employeeId,
                'full_name' => $applicant->full_name,
                'ic_number' => $applicant->ic_number ?? 'PENDING',
                'date_of_birth' => '1990-01-01',
                'gender' => 'male',
                'religion' => 'islam',
                'race' => 'malay',
                'marital_status' => 'single',
                'phone' => $applicant->phone,
                'personal_email' => $applicant->email,
                'address_line_1' => 'PENDING',
                'city' => 'PENDING',
                'state' => 'PENDING',
                'postcode' => '00000',
                'department_id' => $validated['department_id'],
                'position_id' => $validated['position_id'],
                'employment_type' => $validated['employment_type'],
                'join_date' => $validated['join_date'],
                'status' => 'probation',
            ]);

            // Update applicant
            $applicant->update(['current_stage' => 'hired']);
            ApplicantStage::create([
                'applicant_id' => $applicant->id,
                'stage' => 'hired',
                'notes' => 'Converted to employee: '.$employeeId,
                'changed_by' => $request->user()->id,
            ]);

            return response()->json([
                'message' => 'Applicant hired successfully. Employee record created.',
                'data' => [
                    'applicant' => $applicant->fresh(),
                    'employee' => $employee,
                ],
            ], 201);
        });
    }
}
```

**Step 4: Commit**

```bash
git add app/Http/Controllers/Api/Hr/HrApplicantController.php app/Http/Requests/Hr/StoreApplicantRequest.php
git commit -m "feat(hr): add applicant controller with stage management and hire-to-employee conversion"
```

---

## Task 9: Recruitment Controllers — Interviews & Offers

**Files:**
- Create: `app/Http/Controllers/Api/Hr/HrInterviewController.php`
- Create: `app/Http/Controllers/Api/Hr/HrOfferLetterController.php`

**Step 1: Generate files**

```bash
php artisan make:controller Api/Hr/HrInterviewController --no-interaction
php artisan make:controller Api/Hr/HrOfferLetterController --no-interaction
```

**Step 2: Implement HrInterviewController**

```php
<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use App\Models\Interview;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HrInterviewController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Interview::query()
            ->with(['applicant:id,full_name,applicant_number', 'interviewer:id,full_name']);

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        if ($from = $request->get('from')) {
            $query->where('interview_date', '>=', $from);
        }

        if ($to = $request->get('to')) {
            $query->where('interview_date', '<=', $to);
        }

        $interviews = $query->orderBy('interview_date')->orderBy('start_time')->paginate($request->get('per_page', 15));

        return response()->json($interviews);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'applicant_id' => ['required', 'exists:applicants,id'],
            'interviewer_id' => ['required', 'exists:employees,id'],
            'interview_date' => ['required', 'date'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'type' => ['required', 'in:phone,video,in_person'],
            'location' => ['nullable', 'string', 'max:255'],
        ]);

        $interview = Interview::create($validated);

        return response()->json([
            'message' => 'Interview scheduled successfully.',
            'data' => $interview->load(['applicant:id,full_name', 'interviewer:id,full_name']),
        ], 201);
    }

    public function update(Request $request, Interview $interview): JsonResponse
    {
        $validated = $request->validate([
            'interview_date' => ['sometimes', 'date'],
            'start_time' => ['sometimes', 'date_format:H:i'],
            'end_time' => ['sometimes', 'date_format:H:i'],
            'type' => ['sometimes', 'in:phone,video,in_person'],
            'location' => ['nullable', 'string', 'max:255'],
            'status' => ['sometimes', 'in:scheduled,completed,cancelled,no_show'],
        ]);

        $interview->update($validated);

        return response()->json([
            'message' => 'Interview updated successfully.',
            'data' => $interview,
        ]);
    }

    public function destroy(Interview $interview): JsonResponse
    {
        $interview->delete();

        return response()->json(['message' => 'Interview cancelled.']);
    }

    public function feedback(Request $request, Interview $interview): JsonResponse
    {
        $validated = $request->validate([
            'feedback' => ['required', 'string'],
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
        ]);

        $interview->update(array_merge($validated, ['status' => 'completed']));

        return response()->json([
            'message' => 'Interview feedback submitted.',
            'data' => $interview,
        ]);
    }
}
```

**Step 3: Implement HrOfferLetterController**

```php
<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use App\Models\OfferLetter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HrOfferLetterController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'applicant_id' => ['required', 'exists:applicants,id'],
            'position_id' => ['required', 'exists:positions,id'],
            'offered_salary' => ['required', 'numeric', 'min:0'],
            'start_date' => ['required', 'date', 'after:today'],
            'employment_type' => ['required', 'in:full_time,part_time,contract,intern'],
            'template_data' => ['nullable', 'array'],
        ]);

        $offer = OfferLetter::create(array_merge($validated, [
            'created_by' => $request->user()->id,
        ]));

        return response()->json([
            'message' => 'Offer letter created.',
            'data' => $offer->load(['applicant:id,full_name', 'position:id,title']),
        ], 201);
    }

    public function show(OfferLetter $offerLetter): JsonResponse
    {
        return response()->json([
            'data' => $offerLetter->load(['applicant:id,full_name,email', 'position:id,title']),
        ]);
    }

    public function update(Request $request, OfferLetter $offerLetter): JsonResponse
    {
        if ($offerLetter->status !== 'draft') {
            return response()->json(['message' => 'Only draft offers can be updated.'], 422);
        }

        $validated = $request->validate([
            'offered_salary' => ['sometimes', 'numeric', 'min:0'],
            'start_date' => ['sometimes', 'date'],
            'employment_type' => ['sometimes', 'in:full_time,part_time,contract,intern'],
            'template_data' => ['nullable', 'array'],
        ]);

        $offerLetter->update($validated);

        return response()->json([
            'message' => 'Offer letter updated.',
            'data' => $offerLetter,
        ]);
    }

    public function send(OfferLetter $offerLetter): JsonResponse
    {
        $offerLetter->update([
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        return response()->json([
            'message' => 'Offer letter marked as sent.',
            'data' => $offerLetter,
        ]);
    }

    public function respond(Request $request, OfferLetter $offerLetter): JsonResponse
    {
        $validated = $request->validate([
            'response' => ['required', 'in:accepted,rejected'],
        ]);

        $offerLetter->update([
            'status' => $validated['response'],
            'responded_at' => now(),
        ]);

        return response()->json([
            'message' => 'Offer response recorded.',
            'data' => $offerLetter,
        ]);
    }
}
```

**Step 4: Commit**

```bash
git add app/Http/Controllers/Api/Hr/HrInterviewController.php app/Http/Controllers/Api/Hr/HrOfferLetterController.php
git commit -m "feat(hr): add interview and offer letter controllers"
```

---

## Task 10: Recruitment Controllers — Onboarding

**Files:**
- Create: `app/Http/Controllers/Api/Hr/HrOnboardingController.php`
- Create: `app/Http/Controllers/Api/Hr/HrOnboardingTemplateController.php`

**Step 1: Generate files**

```bash
php artisan make:controller Api/Hr/HrOnboardingController --no-interaction
php artisan make:controller Api/Hr/HrOnboardingTemplateController --no-interaction
```

**Step 2: Implement HrOnboardingTemplateController**

```php
<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use App\Models\OnboardingTemplate;
use App\Models\OnboardingTemplateItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HrOnboardingTemplateController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $templates = OnboardingTemplate::query()
            ->with(['department:id,name', 'items'])
            ->withCount('items')
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $templates]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'is_active' => ['boolean'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.title' => ['required', 'string', 'max:255'],
            'items.*.description' => ['nullable', 'string'],
            'items.*.assigned_role' => ['nullable', 'string', 'max:50'],
            'items.*.due_days' => ['required', 'integer', 'min:1'],
            'items.*.sort_order' => ['required', 'integer'],
        ]);

        return DB::transaction(function () use ($validated) {
            $template = OnboardingTemplate::create([
                'name' => $validated['name'],
                'department_id' => $validated['department_id'] ?? null,
                'is_active' => $validated['is_active'] ?? true,
            ]);

            foreach ($validated['items'] as $item) {
                OnboardingTemplateItem::create(array_merge($item, [
                    'onboarding_template_id' => $template->id,
                ]));
            }

            return response()->json([
                'message' => 'Onboarding template created.',
                'data' => $template->load('items'),
            ], 201);
        });
    }

    public function update(Request $request, OnboardingTemplate $onboardingTemplate): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'is_active' => ['boolean'],
            'items' => ['sometimes', 'array', 'min:1'],
            'items.*.id' => ['nullable', 'exists:onboarding_template_items,id'],
            'items.*.title' => ['required', 'string', 'max:255'],
            'items.*.description' => ['nullable', 'string'],
            'items.*.assigned_role' => ['nullable', 'string', 'max:50'],
            'items.*.due_days' => ['required', 'integer', 'min:1'],
            'items.*.sort_order' => ['required', 'integer'],
        ]);

        return DB::transaction(function () use ($validated, $onboardingTemplate) {
            $onboardingTemplate->update(collect($validated)->only(['name', 'department_id', 'is_active'])->toArray());

            if (isset($validated['items'])) {
                $existingIds = collect($validated['items'])->pluck('id')->filter();
                $onboardingTemplate->items()->whereNotIn('id', $existingIds)->delete();

                foreach ($validated['items'] as $item) {
                    if (! empty($item['id'])) {
                        OnboardingTemplateItem::where('id', $item['id'])->update($item);
                    } else {
                        OnboardingTemplateItem::create(array_merge($item, [
                            'onboarding_template_id' => $onboardingTemplate->id,
                        ]));
                    }
                }
            }

            return response()->json([
                'message' => 'Onboarding template updated.',
                'data' => $onboardingTemplate->fresh('items'),
            ]);
        });
    }

    public function destroy(OnboardingTemplate $onboardingTemplate): JsonResponse
    {
        $onboardingTemplate->delete();

        return response()->json(['message' => 'Onboarding template deleted.']);
    }
}
```

**Step 3: Implement HrOnboardingController**

```php
<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\OnboardingTask;
use App\Models\OnboardingTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HrOnboardingController extends Controller
{
    public function dashboard(): JsonResponse
    {
        $tasks = OnboardingTask::query()
            ->selectRaw('employee_id, COUNT(*) as total, SUM(CASE WHEN status = "completed" THEN 1 ELSE 0 END) as completed')
            ->groupBy('employee_id')
            ->with('employee:id,full_name,employee_id,department_id')
            ->get()
            ->map(fn ($row) => [
                'employee' => $row->employee,
                'total_tasks' => $row->total,
                'completed_tasks' => $row->completed,
                'progress' => $row->total > 0 ? round(($row->completed / $row->total) * 100) : 0,
            ]);

        return response()->json(['data' => $tasks]);
    }

    public function assign(Request $request, int $employeeId): JsonResponse
    {
        $employee = Employee::findOrFail($employeeId);

        $request->validate([
            'template_id' => ['nullable', 'exists:onboarding_templates,id'],
        ]);

        return DB::transaction(function () use ($request, $employee) {
            $template = null;

            if ($request->template_id) {
                $template = OnboardingTemplate::with('items')->findOrFail($request->template_id);
            } else {
                // Find matching template by department, or use default
                $template = OnboardingTemplate::with('items')
                    ->where('is_active', true)
                    ->where(function ($q) use ($employee) {
                        $q->where('department_id', $employee->department_id)
                            ->orWhereNull('department_id');
                    })
                    ->orderByRaw('department_id IS NULL')
                    ->first();
            }

            if (! $template) {
                return response()->json(['message' => 'No onboarding template found.'], 404);
            }

            $tasks = [];
            foreach ($template->items as $item) {
                $tasks[] = OnboardingTask::create([
                    'employee_id' => $employee->id,
                    'template_item_id' => $item->id,
                    'title' => $item->title,
                    'description' => $item->description,
                    'assigned_to' => null,
                    'due_date' => $employee->join_date
                        ? $employee->join_date->addDays($item->due_days)
                        : now()->addDays($item->due_days),
                    'status' => 'pending',
                ]);
            }

            return response()->json([
                'message' => 'Onboarding checklist assigned.',
                'data' => $tasks,
            ], 201);
        });
    }

    public function tasks(int $employeeId): JsonResponse
    {
        $tasks = OnboardingTask::where('employee_id', $employeeId)
            ->with('assignedEmployee:id,full_name')
            ->orderBy('due_date')
            ->get();

        return response()->json(['data' => $tasks]);
    }

    public function updateTask(Request $request, OnboardingTask $onboardingTask): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['sometimes', 'in:pending,in_progress,completed,skipped'],
            'notes' => ['nullable', 'string'],
            'assigned_to' => ['nullable', 'exists:employees,id'],
        ]);

        if (isset($validated['status']) && $validated['status'] === 'completed') {
            $validated['completed_at'] = now();
            $validated['completed_by'] = $request->user()->id;
        }

        $onboardingTask->update($validated);

        return response()->json([
            'message' => 'Onboarding task updated.',
            'data' => $onboardingTask,
        ]);
    }
}
```

**Step 4: Commit**

```bash
git add app/Http/Controllers/Api/Hr/HrOnboardingController.php app/Http/Controllers/Api/Hr/HrOnboardingTemplateController.php
git commit -m "feat(hr): add onboarding controllers — templates, task assignment, progress tracking"
```

---

## Task 11: Recruitment Controllers — Public Careers API

**Files:**
- Create: `app/Http/Controllers/Api/Hr/HrCareersController.php`

**Step 1: Generate file**

```bash
php artisan make:controller Api/Hr/HrCareersController --no-interaction
```

**Step 2: Implement HrCareersController** — public endpoints, no auth required

```php
<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use App\Models\Applicant;
use App\Models\ApplicantStage;
use App\Models\JobPosting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HrCareersController extends Controller
{
    public function index(): JsonResponse
    {
        $postings = JobPosting::query()
            ->published()
            ->with(['department:id,name', 'position:id,title'])
            ->select([
                'id', 'title', 'department_id', 'position_id', 'description', 'requirements',
                'employment_type', 'salary_range_min', 'salary_range_max', 'show_salary',
                'vacancies', 'published_at', 'closing_date',
            ])
            ->orderByDesc('published_at')
            ->get()
            ->map(function ($posting) {
                if (! $posting->show_salary) {
                    $posting->salary_range_min = null;
                    $posting->salary_range_max = null;
                }

                return $posting;
            });

        return response()->json(['data' => $postings]);
    }

    public function show(int $id): JsonResponse
    {
        $posting = JobPosting::query()
            ->published()
            ->with(['department:id,name', 'position:id,title'])
            ->findOrFail($id);

        if (! $posting->show_salary) {
            $posting->salary_range_min = null;
            $posting->salary_range_max = null;
        }

        return response()->json(['data' => $posting]);
    }

    public function apply(Request $request, int $id): JsonResponse
    {
        $posting = JobPosting::published()->findOrFail($id);

        $validated = $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:20'],
            'ic_number' => ['nullable', 'string', 'max:20'],
            'resume' => ['required', 'file', 'mimes:pdf,doc,docx', 'max:5120'],
            'cover_letter' => ['nullable', 'string'],
        ]);

        return DB::transaction(function () use ($validated, $posting, $request) {
            $resumePath = $request->file('resume')->store('resumes', 'public');

            $applicant = Applicant::create([
                'job_posting_id' => $posting->id,
                'applicant_number' => Applicant::generateApplicantNumber(),
                'full_name' => $validated['full_name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'],
                'ic_number' => $validated['ic_number'],
                'resume_path' => $resumePath,
                'cover_letter' => $validated['cover_letter'],
                'source' => 'website',
                'current_stage' => 'applied',
                'applied_at' => now(),
            ]);

            ApplicantStage::create([
                'applicant_id' => $applicant->id,
                'stage' => 'applied',
                'notes' => 'Applied via careers page',
                'changed_by' => 1, // System user
            ]);

            return response()->json([
                'message' => 'Application submitted successfully.',
                'data' => [
                    'applicant_number' => $applicant->applicant_number,
                ],
            ], 201);
        });
    }
}
```

**Step 3: Commit**

```bash
git add app/Http/Controllers/Api/Hr/HrCareersController.php
git commit -m "feat(hr): add public careers API — list open positions, view details, apply"
```

---

## Task 12: Performance Management Controllers

**Files:**
- Create: `app/Http/Controllers/Api/Hr/HrPerformanceDashboardController.php`
- Create: `app/Http/Controllers/Api/Hr/HrReviewCycleController.php`
- Create: `app/Http/Controllers/Api/Hr/HrKpiTemplateController.php`
- Create: `app/Http/Controllers/Api/Hr/HrPerformanceReviewController.php`
- Create: `app/Http/Controllers/Api/Hr/HrPipController.php`
- Create: `app/Http/Controllers/Api/Hr/HrRatingScaleController.php`

**Step 1: Generate all controllers**

```bash
php artisan make:controller Api/Hr/HrPerformanceDashboardController --no-interaction
php artisan make:controller Api/Hr/HrReviewCycleController --no-interaction
php artisan make:controller Api/Hr/HrKpiTemplateController --no-interaction
php artisan make:controller Api/Hr/HrPerformanceReviewController --no-interaction
php artisan make:controller Api/Hr/HrPipController --no-interaction
php artisan make:controller Api/Hr/HrRatingScaleController --no-interaction
```

**Step 2: Implement HrPerformanceDashboardController**

```php
<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use App\Models\PerformanceImprovementPlan;
use App\Models\PerformanceReview;
use App\Models\ReviewCycle;
use Illuminate\Http\JsonResponse;

class HrPerformanceDashboardController extends Controller
{
    public function stats(): JsonResponse
    {
        $activeCycles = ReviewCycle::whereIn('status', ['active', 'in_review'])->count();
        $totalReviews = PerformanceReview::count();
        $completedReviews = PerformanceReview::where('status', 'completed')->count();
        $activePips = PerformanceImprovementPlan::where('status', 'active')->count();

        $ratingDistribution = PerformanceReview::where('status', 'completed')
            ->whereNotNull('overall_rating')
            ->selectRaw('
                CASE
                    WHEN overall_rating < 1.5 THEN 1
                    WHEN overall_rating < 2.5 THEN 2
                    WHEN overall_rating < 3.5 THEN 3
                    WHEN overall_rating < 4.5 THEN 4
                    ELSE 5
                END as rating_bucket,
                COUNT(*) as count
            ')
            ->groupBy('rating_bucket')
            ->pluck('count', 'rating_bucket');

        return response()->json([
            'data' => [
                'active_cycles' => $activeCycles,
                'total_reviews' => $totalReviews,
                'completed_reviews' => $completedReviews,
                'completion_rate' => $totalReviews > 0 ? round(($completedReviews / $totalReviews) * 100) : 0,
                'active_pips' => $activePips,
                'rating_distribution' => $ratingDistribution,
            ],
        ]);
    }
}
```

**Step 3: Implement HrReviewCycleController**

```php
<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\KpiTemplate;
use App\Models\PerformanceReview;
use App\Models\ReviewCycle;
use App\Models\ReviewKpi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HrReviewCycleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $cycles = ReviewCycle::query()
            ->withCount('reviews')
            ->orderByDesc('start_date')
            ->paginate($request->get('per_page', 15));

        return response()->json($cycles);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:monthly,quarterly,semi_annual,annual'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
            'submission_deadline' => ['required', 'date', 'after:end_date'],
            'description' => ['nullable', 'string'],
        ]);

        $cycle = ReviewCycle::create(array_merge($validated, [
            'created_by' => $request->user()->id,
        ]));

        return response()->json([
            'message' => 'Review cycle created.',
            'data' => $cycle,
        ], 201);
    }

    public function show(ReviewCycle $reviewCycle): JsonResponse
    {
        return response()->json([
            'data' => $reviewCycle->load([
                'reviews' => fn ($q) => $q->with([
                    'employee:id,full_name,employee_id,department_id,position_id',
                    'employee.department:id,name',
                    'reviewer:id,full_name',
                ]),
            ])->loadCount('reviews'),
        ]);
    }

    public function update(Request $request, ReviewCycle $reviewCycle): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'type' => ['sometimes', 'in:monthly,quarterly,semi_annual,annual'],
            'start_date' => ['sometimes', 'date'],
            'end_date' => ['sometimes', 'date'],
            'submission_deadline' => ['sometimes', 'date'],
            'description' => ['nullable', 'string'],
        ]);

        $reviewCycle->update($validated);

        return response()->json([
            'message' => 'Review cycle updated.',
            'data' => $reviewCycle,
        ]);
    }

    public function destroy(ReviewCycle $reviewCycle): JsonResponse
    {
        if ($reviewCycle->status !== 'draft') {
            return response()->json(['message' => 'Only draft cycles can be deleted.'], 422);
        }

        $reviewCycle->delete();

        return response()->json(['message' => 'Review cycle deleted.']);
    }

    public function activate(ReviewCycle $reviewCycle): JsonResponse
    {
        return DB::transaction(function () use ($reviewCycle) {
            $reviewCycle->update(['status' => 'active']);

            // Auto-create reviews for all active employees
            $employees = Employee::where('status', 'active')->get();

            foreach ($employees as $employee) {
                // Find the employee's manager (department head or any admin for now)
                $reviewer = Employee::where('department_id', $employee->department_id)
                    ->where('id', '!=', $employee->id)
                    ->first() ?? $employee;

                $review = PerformanceReview::firstOrCreate(
                    [
                        'review_cycle_id' => $reviewCycle->id,
                        'employee_id' => $employee->id,
                    ],
                    [
                        'reviewer_id' => $reviewer->id,
                        'status' => 'draft',
                    ]
                );

                // Auto-assign KPIs from templates matching position/department
                $kpiTemplates = KpiTemplate::where('is_active', true)
                    ->where(function ($q) use ($employee) {
                        $q->where('position_id', $employee->position_id)
                            ->orWhere('department_id', $employee->department_id)
                            ->orWhere(function ($q2) {
                                $q2->whereNull('position_id')->whereNull('department_id');
                            });
                    })
                    ->get();

                foreach ($kpiTemplates as $template) {
                    ReviewKpi::firstOrCreate(
                        [
                            'performance_review_id' => $review->id,
                            'kpi_template_id' => $template->id,
                        ],
                        [
                            'title' => $template->title,
                            'target' => $template->target,
                            'weight' => $template->weight,
                        ]
                    );
                }
            }

            return response()->json([
                'message' => 'Review cycle activated. Reviews created for '.count($employees).' employees.',
                'data' => $reviewCycle->fresh()->loadCount('reviews'),
            ]);
        });
    }

    public function complete(ReviewCycle $reviewCycle): JsonResponse
    {
        $reviewCycle->update(['status' => 'completed']);

        return response()->json([
            'message' => 'Review cycle marked as completed.',
            'data' => $reviewCycle,
        ]);
    }
}
```

**Step 4: Implement HrKpiTemplateController**

```php
<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use App\Models\KpiTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HrKpiTemplateController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = KpiTemplate::query()
            ->with(['position:id,title', 'department:id,name']);

        if ($positionId = $request->get('position_id')) {
            $query->where('position_id', $positionId);
        }

        if ($departmentId = $request->get('department_id')) {
            $query->where('department_id', $departmentId);
        }

        if ($category = $request->get('category')) {
            $query->where('category', $category);
        }

        return response()->json(['data' => $query->orderBy('title')->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'position_id' => ['nullable', 'exists:positions,id'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'target' => ['required', 'string', 'max:255'],
            'weight' => ['required', 'numeric', 'min:1', 'max:100'],
            'category' => ['required', 'in:quantitative,qualitative,behavioral'],
        ]);

        $kpi = KpiTemplate::create($validated);

        return response()->json([
            'message' => 'KPI template created.',
            'data' => $kpi,
        ], 201);
    }

    public function update(Request $request, KpiTemplate $kpiTemplate): JsonResponse
    {
        $validated = $request->validate([
            'position_id' => ['nullable', 'exists:positions,id'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'target' => ['sometimes', 'string', 'max:255'],
            'weight' => ['sometimes', 'numeric', 'min:1', 'max:100'],
            'category' => ['sometimes', 'in:quantitative,qualitative,behavioral'],
            'is_active' => ['boolean'],
        ]);

        $kpiTemplate->update($validated);

        return response()->json([
            'message' => 'KPI template updated.',
            'data' => $kpiTemplate,
        ]);
    }

    public function destroy(KpiTemplate $kpiTemplate): JsonResponse
    {
        $kpiTemplate->delete();

        return response()->json(['message' => 'KPI template deleted.']);
    }
}
```

**Step 5: Implement HrPerformanceReviewController**

```php
<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use App\Models\PerformanceReview;
use App\Models\RatingScale;
use App\Models\ReviewKpi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HrPerformanceReviewController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = PerformanceReview::query()
            ->with([
                'employee:id,full_name,employee_id,department_id',
                'employee.department:id,name',
                'reviewer:id,full_name',
                'reviewCycle:id,name',
            ]);

        if ($cycleId = $request->get('review_cycle_id')) {
            $query->where('review_cycle_id', $cycleId);
        }

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        $reviews = $query->orderByDesc('created_at')->paginate($request->get('per_page', 15));

        return response()->json($reviews);
    }

    public function show(PerformanceReview $performanceReview): JsonResponse
    {
        return response()->json([
            'data' => $performanceReview->load([
                'employee:id,full_name,employee_id,department_id,position_id',
                'employee.department:id,name',
                'employee.position:id,title',
                'reviewer:id,full_name',
                'reviewCycle:id,name,type,start_date,end_date',
                'kpis',
            ]),
        ]);
    }

    public function addKpi(Request $request, PerformanceReview $performanceReview): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'target' => ['required', 'string', 'max:255'],
            'weight' => ['required', 'numeric', 'min:1', 'max:100'],
            'kpi_template_id' => ['nullable', 'exists:kpi_templates,id'],
        ]);

        $kpi = ReviewKpi::create(array_merge($validated, [
            'performance_review_id' => $performanceReview->id,
        ]));

        return response()->json([
            'message' => 'KPI added to review.',
            'data' => $kpi,
        ], 201);
    }

    public function selfAssessment(Request $request, PerformanceReview $performanceReview): JsonResponse
    {
        $validated = $request->validate([
            'self_assessment_notes' => ['nullable', 'string'],
            'kpis' => ['required', 'array'],
            'kpis.*.id' => ['required', 'exists:review_kpis,id'],
            'kpis.*.self_score' => ['required', 'integer', 'min:1', 'max:5'],
            'kpis.*.self_comments' => ['nullable', 'string'],
        ]);

        return DB::transaction(function () use ($validated, $performanceReview) {
            $performanceReview->update([
                'self_assessment_notes' => $validated['self_assessment_notes'],
                'status' => 'self_assessment',
            ]);

            foreach ($validated['kpis'] as $kpiData) {
                ReviewKpi::where('id', $kpiData['id'])->update([
                    'self_score' => $kpiData['self_score'],
                    'self_comments' => $kpiData['self_comments'] ?? null,
                ]);
            }

            return response()->json([
                'message' => 'Self-assessment submitted.',
                'data' => $performanceReview->fresh('kpis'),
            ]);
        });
    }

    public function managerReview(Request $request, PerformanceReview $performanceReview): JsonResponse
    {
        $validated = $request->validate([
            'manager_notes' => ['nullable', 'string'],
            'kpis' => ['required', 'array'],
            'kpis.*.id' => ['required', 'exists:review_kpis,id'],
            'kpis.*.manager_score' => ['required', 'integer', 'min:1', 'max:5'],
            'kpis.*.manager_comments' => ['nullable', 'string'],
        ]);

        return DB::transaction(function () use ($validated, $performanceReview) {
            foreach ($validated['kpis'] as $kpiData) {
                ReviewKpi::where('id', $kpiData['id'])->update([
                    'manager_score' => $kpiData['manager_score'],
                    'manager_comments' => $kpiData['manager_comments'] ?? null,
                ]);
            }

            $performanceReview->update([
                'manager_notes' => $validated['manager_notes'],
                'status' => 'manager_review',
            ]);

            return response()->json([
                'message' => 'Manager review submitted.',
                'data' => $performanceReview->fresh('kpis'),
            ]);
        });
    }

    public function complete(PerformanceReview $performanceReview): JsonResponse
    {
        $overallRating = $performanceReview->calculateOverallRating();
        $ratingLabel = null;

        if ($overallRating) {
            $ratingScale = RatingScale::where('score', round($overallRating))->first();
            $ratingLabel = $ratingScale?->label;
        }

        $performanceReview->update([
            'overall_rating' => $overallRating,
            'rating_label' => $ratingLabel,
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        return response()->json([
            'message' => 'Review completed.',
            'data' => $performanceReview,
        ]);
    }

    public function acknowledge(PerformanceReview $performanceReview): JsonResponse
    {
        $performanceReview->update([
            'employee_acknowledged' => true,
            'acknowledged_at' => now(),
        ]);

        return response()->json([
            'message' => 'Review acknowledged.',
            'data' => $performanceReview,
        ]);
    }
}
```

**Step 6: Implement HrPipController**

```php
<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use App\Models\PerformanceImprovementPlan;
use App\Models\PipGoal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HrPipController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = PerformanceImprovementPlan::query()
            ->with(['employee:id,full_name,employee_id', 'initiator:id,full_name'])
            ->withCount('goals');

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        $pips = $query->orderByDesc('created_at')->paginate($request->get('per_page', 15));

        return response()->json($pips);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'employee_id' => ['required', 'exists:employees,id'],
            'performance_review_id' => ['nullable', 'exists:performance_reviews,id'],
            'reason' => ['required', 'string'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
            'goals' => ['required', 'array', 'min:1'],
            'goals.*.title' => ['required', 'string', 'max:255'],
            'goals.*.description' => ['nullable', 'string'],
            'goals.*.target_date' => ['required', 'date'],
        ]);

        return DB::transaction(function () use ($validated, $request) {
            // Find the initiator employee record
            $initiator = \App\Models\Employee::where('user_id', $request->user()->id)->first();

            $pip = PerformanceImprovementPlan::create([
                'employee_id' => $validated['employee_id'],
                'initiated_by' => $initiator?->id ?? $validated['employee_id'],
                'performance_review_id' => $validated['performance_review_id'] ?? null,
                'reason' => $validated['reason'],
                'start_date' => $validated['start_date'],
                'end_date' => $validated['end_date'],
            ]);

            foreach ($validated['goals'] as $goal) {
                PipGoal::create(array_merge($goal, ['pip_id' => $pip->id]));
            }

            return response()->json([
                'message' => 'PIP created.',
                'data' => $pip->load('goals'),
            ], 201);
        });
    }

    public function show(PerformanceImprovementPlan $pip): JsonResponse
    {
        return response()->json([
            'data' => $pip->load([
                'employee:id,full_name,employee_id,department_id',
                'employee.department:id,name',
                'initiator:id,full_name',
                'performanceReview',
                'goals',
            ]),
        ]);
    }

    public function update(Request $request, PerformanceImprovementPlan $pip): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['sometimes', 'string'],
            'end_date' => ['sometimes', 'date'],
        ]);

        $pip->update($validated);

        return response()->json([
            'message' => 'PIP updated.',
            'data' => $pip,
        ]);
    }

    public function extend(Request $request, PerformanceImprovementPlan $pip): JsonResponse
    {
        $validated = $request->validate([
            'end_date' => ['required', 'date', 'after:today'],
        ]);

        $pip->update([
            'end_date' => $validated['end_date'],
            'status' => 'extended',
        ]);

        return response()->json([
            'message' => 'PIP extended.',
            'data' => $pip,
        ]);
    }

    public function complete(Request $request, PerformanceImprovementPlan $pip): JsonResponse
    {
        $validated = $request->validate([
            'outcome' => ['required', 'in:completed_improved,completed_not_improved'],
            'outcome_notes' => ['nullable', 'string'],
        ]);

        $pip->update([
            'status' => $validated['outcome'],
            'outcome_notes' => $validated['outcome_notes'],
            'completed_at' => now(),
        ]);

        return response()->json([
            'message' => 'PIP completed.',
            'data' => $pip,
        ]);
    }

    public function addGoal(Request $request, PerformanceImprovementPlan $pip): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'target_date' => ['required', 'date'],
        ]);

        $goal = PipGoal::create(array_merge($validated, ['pip_id' => $pip->id]));

        return response()->json([
            'message' => 'PIP goal added.',
            'data' => $goal,
        ], 201);
    }

    public function updateGoal(Request $request, PerformanceImprovementPlan $pip, PipGoal $goal): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['sometimes', 'in:pending,in_progress,achieved,not_achieved'],
            'check_in_notes' => ['nullable', 'string'],
        ]);

        if (isset($validated['check_in_notes'])) {
            $validated['checked_at'] = now();
        }

        $goal->update($validated);

        return response()->json([
            'message' => 'PIP goal updated.',
            'data' => $goal,
        ]);
    }
}
```

**Step 7: Implement HrRatingScaleController**

```php
<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use App\Models\RatingScale;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HrRatingScaleController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => RatingScale::orderBy('score')->get(),
        ]);
    }

    public function bulkUpdate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'scales' => ['required', 'array', 'min:1'],
            'scales.*.score' => ['required', 'integer', 'min:1', 'max:5'],
            'scales.*.label' => ['required', 'string', 'max:50'],
            'scales.*.description' => ['nullable', 'string'],
            'scales.*.color' => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ]);

        foreach ($validated['scales'] as $scale) {
            RatingScale::updateOrCreate(
                ['score' => $scale['score']],
                $scale
            );
        }

        return response()->json([
            'message' => 'Rating scales updated.',
            'data' => RatingScale::orderBy('score')->get(),
        ]);
    }
}
```

**Step 8: Commit**

```bash
git add app/Http/Controllers/Api/Hr/
git commit -m "feat(hr): add performance management controllers — cycles, reviews, KPIs, PIPs, rating scales"
```

---

## Task 13: Add Employee Self-Service Controllers + Routes

**Files:**
- Create: `app/Http/Controllers/Api/Hr/HrMyReviewController.php`
- Create: `app/Http/Controllers/Api/Hr/HrMyOnboardingController.php`
- Modify: `routes/api.php`

**Step 1: Generate self-service controllers**

```bash
php artisan make:controller Api/Hr/HrMyReviewController --no-interaction
php artisan make:controller Api/Hr/HrMyOnboardingController --no-interaction
```

**Step 2: Implement HrMyReviewController**

```php
<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\PerformanceImprovementPlan;
use App\Models\PerformanceReview;
use App\Models\ReviewKpi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HrMyReviewController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $employee = Employee::where('user_id', $request->user()->id)->firstOrFail();

        $reviews = PerformanceReview::where('employee_id', $employee->id)
            ->with(['reviewCycle:id,name,type,start_date,end_date', 'reviewer:id,full_name'])
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['data' => $reviews]);
    }

    public function show(Request $request, PerformanceReview $performanceReview): JsonResponse
    {
        $employee = Employee::where('user_id', $request->user()->id)->firstOrFail();

        if ($performanceReview->employee_id !== $employee->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json([
            'data' => $performanceReview->load([
                'reviewCycle:id,name,type,start_date,end_date',
                'reviewer:id,full_name',
                'kpis',
            ]),
        ]);
    }

    public function selfAssessment(Request $request, PerformanceReview $performanceReview): JsonResponse
    {
        $employee = Employee::where('user_id', $request->user()->id)->firstOrFail();

        if ($performanceReview->employee_id !== $employee->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'self_assessment_notes' => ['nullable', 'string'],
            'kpis' => ['required', 'array'],
            'kpis.*.id' => ['required', 'exists:review_kpis,id'],
            'kpis.*.self_score' => ['required', 'integer', 'min:1', 'max:5'],
            'kpis.*.self_comments' => ['nullable', 'string'],
        ]);

        return DB::transaction(function () use ($validated, $performanceReview) {
            $performanceReview->update([
                'self_assessment_notes' => $validated['self_assessment_notes'],
                'status' => 'self_assessment',
            ]);

            foreach ($validated['kpis'] as $kpiData) {
                ReviewKpi::where('id', $kpiData['id'])
                    ->where('performance_review_id', $performanceReview->id)
                    ->update([
                        'self_score' => $kpiData['self_score'],
                        'self_comments' => $kpiData['self_comments'] ?? null,
                    ]);
            }

            return response()->json([
                'message' => 'Self-assessment submitted.',
                'data' => $performanceReview->fresh('kpis'),
            ]);
        });
    }

    public function myPip(Request $request): JsonResponse
    {
        $employee = Employee::where('user_id', $request->user()->id)->firstOrFail();

        $pip = PerformanceImprovementPlan::where('employee_id', $employee->id)
            ->where('status', 'active')
            ->with('goals')
            ->first();

        return response()->json(['data' => $pip]);
    }
}
```

**Step 3: Implement HrMyOnboardingController**

```php
<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\OnboardingTask;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HrMyOnboardingController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $employee = Employee::where('user_id', $request->user()->id)->firstOrFail();

        $tasks = OnboardingTask::where('employee_id', $employee->id)
            ->with('assignedEmployee:id,full_name')
            ->orderBy('due_date')
            ->get();

        $total = $tasks->count();
        $completed = $tasks->where('status', 'completed')->count();

        return response()->json([
            'data' => [
                'tasks' => $tasks,
                'progress' => $total > 0 ? round(($completed / $total) * 100) : 0,
                'total' => $total,
                'completed' => $completed,
            ],
        ]);
    }
}
```

**Step 4: Add ALL Phase 4 routes to `routes/api.php`**

Add the following inside the existing HR route group (before the closing `});`):

```php
    // ========== MODULE 7: RECRUITMENT & ONBOARDING ==========

    // Recruitment Dashboard
    Route::get('recruitment/dashboard', [HrRecruitmentDashboardController::class, 'stats'])->name('api.hr.recruitment.dashboard');

    // Job Postings
    Route::get('recruitment/postings', [HrJobPostingController::class, 'index'])->name('api.hr.recruitment.postings.index');
    Route::post('recruitment/postings', [HrJobPostingController::class, 'store'])->name('api.hr.recruitment.postings.store');
    Route::get('recruitment/postings/{jobPosting}', [HrJobPostingController::class, 'show'])->name('api.hr.recruitment.postings.show');
    Route::put('recruitment/postings/{jobPosting}', [HrJobPostingController::class, 'update'])->name('api.hr.recruitment.postings.update');
    Route::delete('recruitment/postings/{jobPosting}', [HrJobPostingController::class, 'destroy'])->name('api.hr.recruitment.postings.destroy');
    Route::patch('recruitment/postings/{jobPosting}/publish', [HrJobPostingController::class, 'publish'])->name('api.hr.recruitment.postings.publish');
    Route::patch('recruitment/postings/{jobPosting}/close', [HrJobPostingController::class, 'close'])->name('api.hr.recruitment.postings.close');

    // Applicants
    Route::get('recruitment/applicants', [HrApplicantController::class, 'index'])->name('api.hr.recruitment.applicants.index');
    Route::post('recruitment/applicants', [HrApplicantController::class, 'store'])->name('api.hr.recruitment.applicants.store');
    Route::get('recruitment/applicants/{applicant}', [HrApplicantController::class, 'show'])->name('api.hr.recruitment.applicants.show');
    Route::put('recruitment/applicants/{applicant}', [HrApplicantController::class, 'update'])->name('api.hr.recruitment.applicants.update');
    Route::patch('recruitment/applicants/{applicant}/stage', [HrApplicantController::class, 'moveStage'])->name('api.hr.recruitment.applicants.stage');
    Route::post('recruitment/applicants/{applicant}/hire', [HrApplicantController::class, 'hire'])->name('api.hr.recruitment.applicants.hire');

    // Interviews
    Route::get('recruitment/interviews', [HrInterviewController::class, 'index'])->name('api.hr.recruitment.interviews.index');
    Route::post('recruitment/interviews', [HrInterviewController::class, 'store'])->name('api.hr.recruitment.interviews.store');
    Route::put('recruitment/interviews/{interview}', [HrInterviewController::class, 'update'])->name('api.hr.recruitment.interviews.update');
    Route::delete('recruitment/interviews/{interview}', [HrInterviewController::class, 'destroy'])->name('api.hr.recruitment.interviews.destroy');
    Route::put('recruitment/interviews/{interview}/feedback', [HrInterviewController::class, 'feedback'])->name('api.hr.recruitment.interviews.feedback');

    // Offer Letters
    Route::post('recruitment/offers', [HrOfferLetterController::class, 'store'])->name('api.hr.recruitment.offers.store');
    Route::get('recruitment/offers/{offerLetter}', [HrOfferLetterController::class, 'show'])->name('api.hr.recruitment.offers.show');
    Route::put('recruitment/offers/{offerLetter}', [HrOfferLetterController::class, 'update'])->name('api.hr.recruitment.offers.update');
    Route::post('recruitment/offers/{offerLetter}/send', [HrOfferLetterController::class, 'send'])->name('api.hr.recruitment.offers.send');
    Route::patch('recruitment/offers/{offerLetter}/respond', [HrOfferLetterController::class, 'respond'])->name('api.hr.recruitment.offers.respond');

    // Onboarding
    Route::get('onboarding/dashboard', [HrOnboardingController::class, 'dashboard'])->name('api.hr.onboarding.dashboard');
    Route::post('onboarding/assign/{employeeId}', [HrOnboardingController::class, 'assign'])->name('api.hr.onboarding.assign');
    Route::get('onboarding/tasks/{employeeId}', [HrOnboardingController::class, 'tasks'])->name('api.hr.onboarding.tasks');
    Route::patch('onboarding/tasks/{onboardingTask}', [HrOnboardingController::class, 'updateTask'])->name('api.hr.onboarding.tasks.update');

    // Onboarding Templates
    Route::get('onboarding/templates', [HrOnboardingTemplateController::class, 'index'])->name('api.hr.onboarding.templates.index');
    Route::post('onboarding/templates', [HrOnboardingTemplateController::class, 'store'])->name('api.hr.onboarding.templates.store');
    Route::put('onboarding/templates/{onboardingTemplate}', [HrOnboardingTemplateController::class, 'update'])->name('api.hr.onboarding.templates.update');
    Route::delete('onboarding/templates/{onboardingTemplate}', [HrOnboardingTemplateController::class, 'destroy'])->name('api.hr.onboarding.templates.destroy');

    // My Onboarding (Employee Self-Service)
    Route::get('me/onboarding', [HrMyOnboardingController::class, 'index'])->name('api.hr.me.onboarding');

    // ========== MODULE 8: PERFORMANCE MANAGEMENT ==========

    // Performance Dashboard
    Route::get('performance/dashboard', [HrPerformanceDashboardController::class, 'stats'])->name('api.hr.performance.dashboard');

    // Review Cycles
    Route::get('performance/cycles', [HrReviewCycleController::class, 'index'])->name('api.hr.performance.cycles.index');
    Route::post('performance/cycles', [HrReviewCycleController::class, 'store'])->name('api.hr.performance.cycles.store');
    Route::get('performance/cycles/{reviewCycle}', [HrReviewCycleController::class, 'show'])->name('api.hr.performance.cycles.show');
    Route::put('performance/cycles/{reviewCycle}', [HrReviewCycleController::class, 'update'])->name('api.hr.performance.cycles.update');
    Route::delete('performance/cycles/{reviewCycle}', [HrReviewCycleController::class, 'destroy'])->name('api.hr.performance.cycles.destroy');
    Route::patch('performance/cycles/{reviewCycle}/activate', [HrReviewCycleController::class, 'activate'])->name('api.hr.performance.cycles.activate');
    Route::patch('performance/cycles/{reviewCycle}/complete', [HrReviewCycleController::class, 'complete'])->name('api.hr.performance.cycles.complete');

    // KPI Templates
    Route::get('performance/kpis', [HrKpiTemplateController::class, 'index'])->name('api.hr.performance.kpis.index');
    Route::post('performance/kpis', [HrKpiTemplateController::class, 'store'])->name('api.hr.performance.kpis.store');
    Route::put('performance/kpis/{kpiTemplate}', [HrKpiTemplateController::class, 'update'])->name('api.hr.performance.kpis.update');
    Route::delete('performance/kpis/{kpiTemplate}', [HrKpiTemplateController::class, 'destroy'])->name('api.hr.performance.kpis.destroy');

    // Performance Reviews
    Route::get('performance/reviews', [HrPerformanceReviewController::class, 'index'])->name('api.hr.performance.reviews.index');
    Route::get('performance/reviews/{performanceReview}', [HrPerformanceReviewController::class, 'show'])->name('api.hr.performance.reviews.show');
    Route::post('performance/reviews/{performanceReview}/kpis', [HrPerformanceReviewController::class, 'addKpi'])->name('api.hr.performance.reviews.kpis.store');
    Route::put('performance/reviews/{performanceReview}/self-assessment', [HrPerformanceReviewController::class, 'selfAssessment'])->name('api.hr.performance.reviews.self-assessment');
    Route::put('performance/reviews/{performanceReview}/manager-review', [HrPerformanceReviewController::class, 'managerReview'])->name('api.hr.performance.reviews.manager-review');
    Route::patch('performance/reviews/{performanceReview}/complete', [HrPerformanceReviewController::class, 'complete'])->name('api.hr.performance.reviews.complete');
    Route::patch('performance/reviews/{performanceReview}/acknowledge', [HrPerformanceReviewController::class, 'acknowledge'])->name('api.hr.performance.reviews.acknowledge');

    // PIPs
    Route::get('performance/pips', [HrPipController::class, 'index'])->name('api.hr.performance.pips.index');
    Route::post('performance/pips', [HrPipController::class, 'store'])->name('api.hr.performance.pips.store');
    Route::get('performance/pips/{pip}', [HrPipController::class, 'show'])->name('api.hr.performance.pips.show');
    Route::put('performance/pips/{pip}', [HrPipController::class, 'update'])->name('api.hr.performance.pips.update');
    Route::patch('performance/pips/{pip}/extend', [HrPipController::class, 'extend'])->name('api.hr.performance.pips.extend');
    Route::patch('performance/pips/{pip}/complete', [HrPipController::class, 'complete'])->name('api.hr.performance.pips.complete');
    Route::post('performance/pips/{pip}/goals', [HrPipController::class, 'addGoal'])->name('api.hr.performance.pips.goals.store');
    Route::put('performance/pips/{pip}/goals/{goal}', [HrPipController::class, 'updateGoal'])->name('api.hr.performance.pips.goals.update');

    // Rating Scales
    Route::get('performance/rating-scales', [HrRatingScaleController::class, 'index'])->name('api.hr.performance.rating-scales.index');
    Route::put('performance/rating-scales', [HrRatingScaleController::class, 'bulkUpdate'])->name('api.hr.performance.rating-scales.update');

    // My Reviews (Employee Self-Service)
    Route::get('me/reviews', [HrMyReviewController::class, 'index'])->name('api.hr.me.reviews.index');
    Route::get('me/reviews/{performanceReview}', [HrMyReviewController::class, 'show'])->name('api.hr.me.reviews.show');
    Route::put('me/reviews/{performanceReview}/self-assessment', [HrMyReviewController::class, 'selfAssessment'])->name('api.hr.me.reviews.self-assessment');
    Route::get('me/pip', [HrMyReviewController::class, 'myPip'])->name('api.hr.me.pip');
```

Also add the **public careers routes** outside the auth middleware group:

```php
// Public Careers API (no auth required)
Route::prefix('careers')->group(function () {
    Route::get('/', [HrCareersController::class, 'index'])->name('api.careers.index');
    Route::get('/{id}', [HrCareersController::class, 'show'])->name('api.careers.show');
    Route::post('/{id}/apply', [HrCareersController::class, 'apply'])->name('api.careers.apply');
});
```

Don't forget to add all the new `use` statements at the top of `routes/api.php`.

**Step 5: Commit**

```bash
git add routes/api.php app/Http/Controllers/Api/Hr/HrMyReviewController.php app/Http/Controllers/Api/Hr/HrMyOnboardingController.php
git commit -m "feat(hr): add Phase 4 routes — recruitment, onboarding, performance, self-service, public careers"
```

---

## Task 14: Feature Tests

**Files:**
- Create: `tests/Feature/Hr/HrRecruitmentApiTest.php`
- Create: `tests/Feature/Hr/HrPerformanceApiTest.php`

**Step 1: Create test files**

```bash
php artisan make:test Hr/HrRecruitmentApiTest --pest --no-interaction
php artisan make:test Hr/HrPerformanceApiTest --pest --no-interaction
```

**Step 2: Implement HrRecruitmentApiTest**

```php
<?php

declare(strict_types=1);

use App\Models\Applicant;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Interview;
use App\Models\JobPosting;
use App\Models\OfferLetter;
use App\Models\OnboardingTemplate;
use App\Models\OnboardingTemplateItem;
use App\Models\Position;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function createRecruitmentAdmin(): User
{
    return User::factory()->create(['role' => 'admin']);
}

function createRecruitmentSetup(): array
{
    $department = Department::factory()->create();
    $position = Position::factory()->create(['department_id' => $department->id]);
    $admin = User::factory()->create(['role' => 'admin']);

    return compact('department', 'position', 'admin');
}

test('unauthenticated users get 401 on recruitment endpoints', function () {
    $this->getJson('/api/hr/recruitment/dashboard')->assertUnauthorized();
    $this->getJson('/api/hr/recruitment/postings')->assertUnauthorized();
    $this->getJson('/api/hr/recruitment/applicants')->assertUnauthorized();
});

test('admin can create job posting', function () {
    $setup = createRecruitmentSetup();

    $response = $this->actingAs($setup['admin'])->postJson('/api/hr/recruitment/postings', [
        'title' => 'Software Engineer',
        'department_id' => $setup['department']->id,
        'position_id' => $setup['position']->id,
        'description' => 'We are looking for a software engineer.',
        'requirements' => 'PHP, Laravel, React',
        'employment_type' => 'full_time',
        'vacancies' => 2,
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.title', 'Software Engineer');
});

test('admin can list job postings', function () {
    $admin = createRecruitmentAdmin();
    JobPosting::factory()->count(3)->create(['created_by' => $admin->id]);

    $response = $this->actingAs($admin)->getJson('/api/hr/recruitment/postings');

    $response->assertSuccessful()
        ->assertJsonCount(3, 'data');
});

test('admin can publish job posting', function () {
    $admin = createRecruitmentAdmin();
    $posting = JobPosting::factory()->create(['created_by' => $admin->id, 'status' => 'draft']);

    $response = $this->actingAs($admin)->patchJson("/api/hr/recruitment/postings/{$posting->id}/publish");

    $response->assertSuccessful()
        ->assertJsonPath('data.status', 'open');
});

test('admin can add applicant manually', function () {
    Storage::fake('public');
    $setup = createRecruitmentSetup();
    $posting = JobPosting::factory()->open()->create(['created_by' => $setup['admin']->id]);

    $response = $this->actingAs($setup['admin'])->postJson('/api/hr/recruitment/applicants', [
        'job_posting_id' => $posting->id,
        'full_name' => 'Ahmad Ali',
        'email' => 'ahmad@example.com',
        'phone' => '0123456789',
        'source' => 'referral',
        'resume' => UploadedFile::fake()->create('resume.pdf', 500),
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.full_name', 'Ahmad Ali');
});

test('admin can move applicant stage', function () {
    $admin = createRecruitmentAdmin();
    $applicant = Applicant::factory()->create();

    $response = $this->actingAs($admin)->patchJson("/api/hr/recruitment/applicants/{$applicant->id}/stage", [
        'stage' => 'screening',
        'notes' => 'Resume looks good',
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('data.current_stage', 'screening');
});

test('admin can schedule interview', function () {
    $setup = createRecruitmentSetup();
    $employee = Employee::factory()->create(['department_id' => $setup['department']->id, 'position_id' => $setup['position']->id]);
    $applicant = Applicant::factory()->create();

    $response = $this->actingAs($setup['admin'])->postJson('/api/hr/recruitment/interviews', [
        'applicant_id' => $applicant->id,
        'interviewer_id' => $employee->id,
        'interview_date' => now()->addDays(7)->toDateString(),
        'start_time' => '10:00',
        'end_time' => '11:00',
        'type' => 'in_person',
    ]);

    $response->assertCreated();
});

test('public careers page lists only open positions', function () {
    JobPosting::factory()->create(['status' => 'draft']);
    JobPosting::factory()->open()->create();

    $response = $this->getJson('/api/careers');

    $response->assertSuccessful()
        ->assertJsonCount(1, 'data');
});

test('public can apply to open position', function () {
    Storage::fake('public');
    $posting = JobPosting::factory()->open()->create();

    $response = $this->postJson("/api/careers/{$posting->id}/apply", [
        'full_name' => 'Test Applicant',
        'email' => 'test@example.com',
        'phone' => '0123456789',
        'resume' => UploadedFile::fake()->create('resume.pdf', 500),
    ]);

    $response->assertCreated()
        ->assertJsonStructure(['data' => ['applicant_number']]);
});

test('admin can get recruitment dashboard stats', function () {
    $admin = createRecruitmentAdmin();

    $response = $this->actingAs($admin)->getJson('/api/hr/recruitment/dashboard');

    $response->assertSuccessful()
        ->assertJsonStructure(['data' => ['open_positions', 'total_applicants', 'pipeline']]);
});
```

**Step 3: Implement HrPerformanceApiTest**

```php
<?php

declare(strict_types=1);

use App\Models\Department;
use App\Models\Employee;
use App\Models\KpiTemplate;
use App\Models\PerformanceImprovementPlan;
use App\Models\PerformanceReview;
use App\Models\PipGoal;
use App\Models\Position;
use App\Models\RatingScale;
use App\Models\ReviewCycle;
use App\Models\ReviewKpi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createPerformanceAdmin(): User
{
    return User::factory()->create(['role' => 'admin']);
}

function createPerformanceSetup(): array
{
    $department = Department::factory()->create();
    $position = Position::factory()->create(['department_id' => $department->id]);
    $admin = User::factory()->create(['role' => 'admin']);

    return compact('department', 'position', 'admin');
}

function seedRatingScales(): void
{
    $scales = [
        ['score' => 1, 'label' => 'Unsatisfactory', 'color' => '#EF4444'],
        ['score' => 2, 'label' => 'Needs Improvement', 'color' => '#F97316'],
        ['score' => 3, 'label' => 'Meets Expectations', 'color' => '#EAB308'],
        ['score' => 4, 'label' => 'Exceeds Expectations', 'color' => '#22C55E'],
        ['score' => 5, 'label' => 'Outstanding', 'color' => '#3B82F6'],
    ];

    foreach ($scales as $scale) {
        RatingScale::create($scale);
    }
}

test('unauthenticated users get 401 on performance endpoints', function () {
    $this->getJson('/api/hr/performance/dashboard')->assertUnauthorized();
    $this->getJson('/api/hr/performance/cycles')->assertUnauthorized();
    $this->getJson('/api/hr/performance/reviews')->assertUnauthorized();
});

test('admin can create review cycle', function () {
    $admin = createPerformanceAdmin();

    $response = $this->actingAs($admin)->postJson('/api/hr/performance/cycles', [
        'name' => 'Q1 2026 Review',
        'type' => 'quarterly',
        'start_date' => '2026-01-01',
        'end_date' => '2026-03-31',
        'submission_deadline' => '2026-04-14',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.name', 'Q1 2026 Review');
});

test('admin can activate review cycle and auto-create reviews', function () {
    $setup = createPerformanceSetup();
    $employee = Employee::factory()->create([
        'department_id' => $setup['department']->id,
        'position_id' => $setup['position']->id,
        'status' => 'active',
    ]);

    $cycle = ReviewCycle::factory()->create(['created_by' => $setup['admin']->id]);

    $response = $this->actingAs($setup['admin'])->patchJson("/api/hr/performance/cycles/{$cycle->id}/activate");

    $response->assertSuccessful();
    expect(PerformanceReview::where('review_cycle_id', $cycle->id)->count())->toBeGreaterThanOrEqual(1);
});

test('admin can create KPI template', function () {
    $admin = createPerformanceAdmin();

    $response = $this->actingAs($admin)->postJson('/api/hr/performance/kpis', [
        'title' => 'Customer Satisfaction',
        'target' => '90% satisfaction rate',
        'weight' => 25,
        'category' => 'quantitative',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.title', 'Customer Satisfaction');
});

test('admin can submit manager review and complete with rating', function () {
    seedRatingScales();
    $admin = createPerformanceAdmin();
    $review = PerformanceReview::factory()->create(['status' => 'self_assessment']);

    $kpi1 = ReviewKpi::create([
        'performance_review_id' => $review->id,
        'title' => 'KPI 1',
        'target' => 'Target 1',
        'weight' => 60,
        'self_score' => 4,
    ]);
    $kpi2 = ReviewKpi::create([
        'performance_review_id' => $review->id,
        'title' => 'KPI 2',
        'target' => 'Target 2',
        'weight' => 40,
        'self_score' => 3,
    ]);

    // Submit manager review
    $response = $this->actingAs($admin)->putJson("/api/hr/performance/reviews/{$review->id}/manager-review", [
        'manager_notes' => 'Good performance overall.',
        'kpis' => [
            ['id' => $kpi1->id, 'manager_score' => 4, 'manager_comments' => 'Excellent'],
            ['id' => $kpi2->id, 'manager_score' => 3, 'manager_comments' => 'Good'],
        ],
    ]);
    $response->assertSuccessful();

    // Complete review
    $response = $this->actingAs($admin)->patchJson("/api/hr/performance/reviews/{$review->id}/complete");
    $response->assertSuccessful()
        ->assertJsonPath('data.status', 'completed');

    // Rating = (4*60 + 3*40) / (60+40) = 3.6
    expect($response->json('data.overall_rating'))->toBe('3.6');
});

test('admin can create PIP with goals', function () {
    $setup = createPerformanceSetup();
    $employee = Employee::factory()->create([
        'department_id' => $setup['department']->id,
        'position_id' => $setup['position']->id,
    ]);
    $adminEmployee = Employee::factory()->create([
        'user_id' => $setup['admin']->id,
        'department_id' => $setup['department']->id,
        'position_id' => $setup['position']->id,
    ]);

    $response = $this->actingAs($setup['admin'])->postJson('/api/hr/performance/pips', [
        'employee_id' => $employee->id,
        'reason' => 'Performance below expectations in Q1.',
        'start_date' => '2026-04-01',
        'end_date' => '2026-06-30',
        'goals' => [
            ['title' => 'Improve response time', 'target_date' => '2026-05-15'],
            ['title' => 'Complete training modules', 'target_date' => '2026-05-30'],
        ],
    ]);

    $response->assertCreated()
        ->assertJsonCount(2, 'data.goals');
});

test('admin can get performance dashboard stats', function () {
    $admin = createPerformanceAdmin();

    $response = $this->actingAs($admin)->getJson('/api/hr/performance/dashboard');

    $response->assertSuccessful()
        ->assertJsonStructure(['data' => ['active_cycles', 'total_reviews', 'completion_rate', 'active_pips']]);
});

test('admin can get rating scales', function () {
    seedRatingScales();
    $admin = createPerformanceAdmin();

    $response = $this->actingAs($admin)->getJson('/api/hr/performance/rating-scales');

    $response->assertSuccessful()
        ->assertJsonCount(5, 'data');
});

test('employee can view own reviews', function () {
    $user = User::factory()->create(['role' => 'employee']);
    $department = Department::factory()->create();
    $position = Position::factory()->create(['department_id' => $department->id]);
    $employee = Employee::factory()->create([
        'user_id' => $user->id,
        'department_id' => $department->id,
        'position_id' => $position->id,
    ]);
    $cycle = ReviewCycle::factory()->create();
    PerformanceReview::factory()->create([
        'review_cycle_id' => $cycle->id,
        'employee_id' => $employee->id,
    ]);

    $response = $this->actingAs($user)->getJson('/api/hr/me/reviews');

    $response->assertSuccessful()
        ->assertJsonCount(1, 'data');
});
```

**Step 4: Run tests**

```bash
php artisan test --compact tests/Feature/Hr/HrRecruitmentApiTest.php
php artisan test --compact tests/Feature/Hr/HrPerformanceApiTest.php
```

**Step 5: Commit**

```bash
git add tests/Feature/Hr/
git commit -m "test(hr): add Phase 4 feature tests — recruitment, performance management"
```

---

## Tasks 15-42: React Frontend

Due to the large size of the React frontend (40+ files), these tasks are grouped by module. Each task creates 2-4 page components following the established pattern from `EmployeeList.jsx`.

**Task 15:** Add API client functions to `resources/js/hr/lib/api.js` (~60 new functions for recruitment, performance, self-service)

**Tasks 16-19:** Recruitment pages — `RecruitmentDashboard.jsx`, `JobPostings.jsx`, `JobPostingDetail.jsx`, `Applicants.jsx`, `ApplicantDetail.jsx`, `Interviews.jsx`, `OnboardingDashboard.jsx`, `OnboardingTemplates.jsx`

**Tasks 20-23:** Performance pages — `PerformanceDashboard.jsx`, `ReviewCycles.jsx`, `ReviewCycleDetail.jsx`, `KpiTemplates.jsx`, `ReviewDetail.jsx`, `PipManagement.jsx`, `PipDetail.jsx`, `RatingScaleConfig.jsx`

**Tasks 24-26:** Self-service pages — `MyReviews.jsx`, `MyPip.jsx`, `MyOnboarding.jsx`, `CareersPage.jsx`

**Task 27:** Update `App.jsx` router with all new routes

> **For the implementing engineer:** Follow the exact same patterns from existing pages like `EmployeeList.jsx`, `ClaimsDashboard.jsx`, etc. Use `useQuery` from TanStack Query, Shadcn Card/Table/Badge components, `PageHeader` component, loading skeletons, and the established search/filter/pagination pattern.

---

## Task 43: Integration Tests

**Files:**
- Create: `tests/Feature/Hr/HrPhase4IntegrationTest.php`

Test the full recruitment pipeline: create posting → publish → apply → screen → interview → offer → hire → verify employee created → verify onboarding assigned.

Test the full review cycle: create cycle → activate → self-assessment → manager review → complete → verify rating calculation.

**Step 1: Run all HR tests**

```bash
php artisan test --compact tests/Feature/Hr/
```

---

## Task 44: Final Verification

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
git commit -m "feat(hr): complete Phase 4 — recruitment, onboarding, performance management"
```

---

## Summary

| Task | Description | Files |
|------|-------------|-------|
| 1-2 | Recruitment migrations (8 tables) | 8 |
| 3 | Performance migrations (7 tables) | 7 |
| 4-5 | Models + factories (15 models, 9 factories) | 24 |
| 6 | Seeders (onboarding template, rating scales) | 2 |
| 7-12 | Controllers + form requests | 16 |
| 13 | Routes + self-service controllers | 3 |
| 14 | Feature tests | 2 |
| 15-27 | React frontend (API client, pages, router) | ~40 |
| 43-44 | Integration tests + verification | 2 |
| **Total** | | **~100** |
