# Recruitment Form Builder Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Replace the hardcoded recruitment application form with an admin-authored drag-and-drop form builder (pure JSON rewrite) while keeping the hire flow and existing applicants intact.

**Architecture:** Campaigns gain a `form_schema` JSON column; applicants gain `form_data` + `form_schema_snapshot` JSON. Eight of the hardcoded applicant columns are dropped (email stays as an indexed dedupe anchor, auto-mirrored from the email-role field via a model observer). Public form renders from schema; validation rules are built dynamically. Admin builds the form in a 3-panel React UI embedded in the campaign edit page.

**Tech Stack:** Laravel 12, Livewire/Inertia dual setup, React 19, `@hello-pangea/dnd` (already installed) for drag-and-drop, Pest 4, MySQL + SQLite compat per `CLAUDE.md`.

**Design doc:** [docs/plans/2026-04-24-recruitment-form-builder-design.md](2026-04-24-recruitment-form-builder-design.md)

---

## Conventions for this plan

- **Field ID naming in default backfill:** `f_name`, `f_email`, `f_phone`, `f_ic_number`, `f_location`, `f_platforms`, `f_experience`, `f_motivation`, `f_resume`
- **Role vocabulary:** `name`, `email`, `phone`, `resume` (these are the only valid values for the optional `role` attribute)
- **Field type vocabulary:** `text`, `textarea`, `email`, `phone`, `number`, `url`, `select`, `radio`, `checkbox_group`, `file`, `date`, `datetime`, `heading`, `paragraph`
- **Migrations:** MySQL + SQLite compatible. Use `DB::getDriverName()` branching for enum-like changes. **No `migrate:fresh`.** Use `php artisan migrate` only.
- **Tests:** Pest feature tests under `tests/Feature/LiveHost/Recruitment/`. Run with `php artisan test --compact --filter=<name>`.
- **Format:** Run `vendor/bin/pint --dirty` before each commit.
- **Staging:** Never `git add .` — explicitly stage touched files (main has unrelated in-progress work).
- **Existing test count baseline:** 55 recruitment tests pass today. Each task must leave the suite green.

---

# Milestone 1 — Data foundation + services

Migrations, model updates, JSON-building and rule-building services. No user-visible changes yet.

## Task 1.1 — Migration: add JSON columns, backfill, drop old columns

**Files:**
- Create: `database/migrations/2026_04_24_200000_refactor_live_host_recruitment_to_form_schema.php`

This migration is the biggest risk item in the whole plan. Do it in explicit, testable stages.

### Step 1 — Generate migration

```bash
php artisan make:migration refactor_live_host_recruitment_to_form_schema --no-interaction
```

Rename the generated file so its timestamp is `2026_04_24_200000_*` to match this plan.

### Step 2 — Write the migration

The `up()` does six things in order:

1. Add `form_schema` (json, nullable) to `live_host_recruitment_campaigns`
2. Add `form_data` (json, nullable) and `form_schema_snapshot` (json, nullable) to `live_host_applicants`
3. Backfill `form_schema` on every existing campaign with the default 9-field layout
4. Backfill `form_data` + `form_schema_snapshot` on every existing applicant from the old columns
5. Make the three new columns `NOT NULL` (MySQL + SQLite compatible)
6. Drop the 8 old columns (keep `email`)

Full code:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add nullable JSON columns
        Schema::table('live_host_recruitment_campaigns', function (Blueprint $table) {
            $table->json('form_schema')->nullable()->after('description');
        });

        Schema::table('live_host_applicants', function (Blueprint $table) {
            $table->json('form_data')->nullable()->after('platforms');
            $table->json('form_schema_snapshot')->nullable()->after('form_data');
        });

        // 2. Backfill campaigns
        $defaultSchema = self::defaultSchema();
        DB::table('live_host_recruitment_campaigns')
            ->whereNull('form_schema')
            ->update(['form_schema' => json_encode($defaultSchema)]);

        // 3. Backfill applicants
        DB::table('live_host_applicants')->orderBy('id')->chunkById(200, function ($rows) use ($defaultSchema) {
            foreach ($rows as $row) {
                $formData = [
                    'f_name' => $row->full_name,
                    'f_email' => $row->email,
                    'f_phone' => $row->phone,
                    'f_ic_number' => $row->ic_number,
                    'f_location' => $row->location,
                    'f_platforms' => $row->platforms ? (json_decode($row->platforms, true) ?: []) : [],
                    'f_experience' => $row->experience_summary,
                    'f_motivation' => $row->motivation,
                    'f_resume' => $row->resume_path,
                ];

                DB::table('live_host_applicants')->where('id', $row->id)->update([
                    'form_data' => json_encode($formData),
                    'form_schema_snapshot' => json_encode($defaultSchema),
                ]);
            }
        });

        // 4. Enforce NOT NULL (SQLite-safe: change() on json works)
        Schema::table('live_host_recruitment_campaigns', function (Blueprint $table) {
            $table->json('form_schema')->nullable(false)->change();
        });
        Schema::table('live_host_applicants', function (Blueprint $table) {
            $table->json('form_data')->nullable(false)->change();
            $table->json('form_schema_snapshot')->nullable(false)->change();
        });

        // 5. Drop old columns (8 of 9 — email stays)
        Schema::table('live_host_applicants', function (Blueprint $table) {
            $table->dropColumn([
                'full_name', 'phone', 'ic_number', 'location',
                'platforms', 'experience_summary', 'motivation', 'resume_path',
            ]);
        });
    }

    public function down(): void
    {
        // Restore the dropped columns (as nullable) so rollback is possible
        Schema::table('live_host_applicants', function (Blueprint $table) {
            $table->string('full_name')->nullable()->after('applicant_number');
            $table->string('phone')->nullable()->after('email');
            $table->string('ic_number')->nullable()->after('phone');
            $table->string('location')->nullable()->after('ic_number');
            $table->json('platforms')->nullable()->after('location');
            $table->text('experience_summary')->nullable()->after('platforms');
            $table->text('motivation')->nullable()->after('experience_summary');
            $table->string('resume_path')->nullable()->after('motivation');
        });

        // Restore data from form_data
        DB::table('live_host_applicants')->orderBy('id')->chunkById(200, function ($rows) {
            foreach ($rows as $row) {
                $data = $row->form_data ? (json_decode($row->form_data, true) ?: []) : [];
                DB::table('live_host_applicants')->where('id', $row->id)->update([
                    'full_name' => $data['f_name'] ?? null,
                    'phone' => $data['f_phone'] ?? null,
                    'ic_number' => $data['f_ic_number'] ?? null,
                    'location' => $data['f_location'] ?? null,
                    'platforms' => isset($data['f_platforms']) ? json_encode($data['f_platforms']) : null,
                    'experience_summary' => $data['f_experience'] ?? null,
                    'motivation' => $data['f_motivation'] ?? null,
                    'resume_path' => $data['f_resume'] ?? null,
                ]);
            }
        });

        Schema::table('live_host_recruitment_campaigns', function (Blueprint $table) {
            $table->dropColumn('form_schema');
        });
        Schema::table('live_host_applicants', function (Blueprint $table) {
            $table->dropColumn(['form_data', 'form_schema_snapshot']);
        });
    }

    private static function defaultSchema(): array
    {
        return [
            'version' => 1,
            'pages' => [
                [
                    'id' => 'page-1',
                    'title' => 'About you',
                    'fields' => [
                        ['id' => 'f_name', 'type' => 'text', 'label' => 'Full name', 'required' => true, 'role' => 'name'],
                        ['id' => 'f_email', 'type' => 'email', 'label' => 'Email', 'required' => true, 'role' => 'email'],
                        ['id' => 'f_phone', 'type' => 'phone', 'label' => 'Phone', 'required' => true, 'role' => 'phone'],
                        ['id' => 'f_ic_number', 'type' => 'text', 'label' => 'IC number', 'required' => false],
                        ['id' => 'f_location', 'type' => 'text', 'label' => 'Location', 'required' => false],
                    ],
                ],
                [
                    'id' => 'page-2',
                    'title' => 'Platforms',
                    'fields' => [
                        [
                            'id' => 'f_platforms',
                            'type' => 'checkbox_group',
                            'label' => 'Platforms you can live on',
                            'required' => true,
                            'options' => [
                                ['value' => 'tiktok', 'label' => 'TikTok'],
                                ['value' => 'shopee', 'label' => 'Shopee'],
                                ['value' => 'facebook', 'label' => 'Facebook'],
                            ],
                        ],
                    ],
                ],
                [
                    'id' => 'page-3',
                    'title' => 'Your story',
                    'fields' => [
                        ['id' => 'f_experience', 'type' => 'textarea', 'label' => 'Experience', 'required' => false, 'rows' => 4],
                        ['id' => 'f_motivation', 'type' => 'textarea', 'label' => 'Why do you want to join?', 'required' => false, 'rows' => 4],
                        ['id' => 'f_resume', 'type' => 'file', 'label' => 'Resume', 'required' => false, 'role' => 'resume', 'accept' => ['pdf', 'doc', 'docx'], 'max_size_kb' => 5120],
                    ],
                ],
            ],
        ];
    }
};
```

### Step 3 — Run migration

```bash
php artisan migrate
```

Expected: the migration runs cleanly on dev SQLite. Verify via tinker:

```bash
php artisan tinker --execute="
echo \Schema::hasColumn('live_host_recruitment_campaigns', 'form_schema') ? 'campaigns.form_schema OK' : 'MISSING';
echo PHP_EOL;
echo \Schema::hasColumn('live_host_applicants', 'form_data') ? 'applicants.form_data OK' : 'MISSING';
echo PHP_EOL;
echo \Schema::hasColumn('live_host_applicants', 'full_name') ? 'full_name STILL EXISTS (BAD)' : 'full_name dropped OK';
echo PHP_EOL;
echo \Schema::hasColumn('live_host_applicants', 'email') ? 'email retained OK' : 'email MISSING (BAD)';
"
```

### Step 4 — Commit

```bash
git add database/migrations/2026_04_24_200000_refactor_live_host_recruitment_to_form_schema.php
git commit -m "feat(livehost): migrate recruitment to schema-driven form_data"
```

---

## Task 1.2 — Default schema constant + model casts

**Files:**
- Create: `app/Support/Recruitment/DefaultFormSchema.php`
- Modify: `app/Models/LiveHostRecruitmentCampaign.php`
- Modify: `app/Models/LiveHostApplicant.php`

**Step 1: Create the constant**

```php
<?php

namespace App\Support\Recruitment;

class DefaultFormSchema
{
    public static function get(): array
    {
        return [
            'version' => 1,
            'pages' => [
                [
                    'id' => 'page-1',
                    'title' => 'About you',
                    'fields' => [
                        ['id' => 'f_name', 'type' => 'text', 'label' => 'Full name', 'required' => true, 'role' => 'name'],
                        ['id' => 'f_email', 'type' => 'email', 'label' => 'Email', 'required' => true, 'role' => 'email'],
                        ['id' => 'f_phone', 'type' => 'phone', 'label' => 'Phone', 'required' => true, 'role' => 'phone'],
                        ['id' => 'f_ic_number', 'type' => 'text', 'label' => 'IC number', 'required' => false],
                        ['id' => 'f_location', 'type' => 'text', 'label' => 'Location', 'required' => false],
                    ],
                ],
                [
                    'id' => 'page-2',
                    'title' => 'Platforms',
                    'fields' => [
                        [
                            'id' => 'f_platforms',
                            'type' => 'checkbox_group',
                            'label' => 'Platforms you can live on',
                            'required' => true,
                            'options' => [
                                ['value' => 'tiktok', 'label' => 'TikTok'],
                                ['value' => 'shopee', 'label' => 'Shopee'],
                                ['value' => 'facebook', 'label' => 'Facebook'],
                            ],
                        ],
                    ],
                ],
                [
                    'id' => 'page-3',
                    'title' => 'Your story',
                    'fields' => [
                        ['id' => 'f_experience', 'type' => 'textarea', 'label' => 'Experience', 'required' => false, 'rows' => 4],
                        ['id' => 'f_motivation', 'type' => 'textarea', 'label' => 'Why do you want to join?', 'required' => false, 'rows' => 4],
                        ['id' => 'f_resume', 'type' => 'file', 'label' => 'Resume', 'required' => false, 'role' => 'resume', 'accept' => ['pdf', 'doc', 'docx'], 'max_size_kb' => 5120],
                    ],
                ],
            ],
        ];
    }
}
```

**Step 2: Add cast and helper to campaign model**

In `LiveHostRecruitmentCampaign.php`:

```php
protected function casts(): array
{
    return [
        'opens_at' => 'datetime',
        'closes_at' => 'datetime',
        'target_count' => 'integer',
        'form_schema' => 'array',  // NEW
    ];
}

public function getAllFields(): array
{
    $fields = [];
    foreach (($this->form_schema['pages'] ?? []) as $page) {
        foreach (($page['fields'] ?? []) as $field) {
            $fields[] = $field;
        }
    }
    return $fields;
}

public function getFieldByRole(string $role): ?array
{
    foreach ($this->getAllFields() as $field) {
        if (($field['role'] ?? null) === $role) {
            return $field;
        }
    }
    return null;
}
```

**Step 3: Add cast and helpers to applicant model**

In `LiveHostApplicant.php`, add to `$fillable`: `'form_data', 'form_schema_snapshot'`. Remove the dropped columns from fillable (`full_name`, `phone`, `ic_number`, `location`, `platforms`, `experience_summary`, `motivation`, `resume_path`).

Update casts:
```php
protected function casts(): array
{
    return [
        'rating' => 'integer',
        'applied_at' => 'datetime',
        'hired_at' => 'datetime',
        'form_data' => 'array',             // NEW
        'form_schema_snapshot' => 'array',  // NEW
    ];
}
```

Add helpers:
```php
public function valueByRole(string $role): mixed
{
    $schema = $this->form_schema_snapshot ?? [];
    foreach (($schema['pages'] ?? []) as $page) {
        foreach (($page['fields'] ?? []) as $field) {
            if (($field['role'] ?? null) === $role) {
                return $this->form_data[$field['id']] ?? null;
            }
        }
    }
    return null;
}

public function getNameAttribute(): ?string
{
    return $this->valueByRole('name') ?? $this->email;
}

public function getPhoneAttribute(): ?string
{
    return $this->valueByRole('phone');
}

public function getResumePathAttribute(): ?string
{
    return $this->valueByRole('resume');
}
```

**Step 4: Feature test**

Create `tests/Feature/LiveHost/Recruitment/SchemaHelpersTest.php`:

```php
<?php

use App\Models\LiveHostApplicant;
use App\Models\LiveHostRecruitmentCampaign;
use App\Support\Recruitment\DefaultFormSchema;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('casts form_schema as array on campaign', function () {
    $campaign = LiveHostRecruitmentCampaign::factory()->create();

    expect($campaign->form_schema)->toBeArray();
    expect($campaign->form_schema['version'])->toBe(1);
});

it('finds a field by role on the campaign', function () {
    $campaign = LiveHostRecruitmentCampaign::factory()->create();

    $email = $campaign->getFieldByRole('email');

    expect($email)->not->toBeNull();
    expect($email['id'])->toBe('f_email');
});

it('resolves applicant attributes via snapshot + form_data roles', function () {
    $campaign = LiveHostRecruitmentCampaign::factory()->create();
    $applicant = LiveHostApplicant::factory()->create([
        'campaign_id' => $campaign->id,
        'email' => 'ahmad@example.com',
        'form_data' => [
            'f_name' => 'Ahmad Rahman',
            'f_email' => 'ahmad@example.com',
            'f_phone' => '60123456789',
        ],
        'form_schema_snapshot' => DefaultFormSchema::get(),
    ]);

    expect($applicant->name)->toBe('Ahmad Rahman');
    expect($applicant->phone)->toBe('60123456789');
    expect($applicant->valueByRole('email'))->toBe('ahmad@example.com');
});
```

Run: `php artisan test --compact --filter=SchemaHelpersTest`
Expected: 3 passed.

**Step 5: Commit**

```bash
git add app/Support/Recruitment/DefaultFormSchema.php \
        app/Models/LiveHostRecruitmentCampaign.php \
        app/Models/LiveHostApplicant.php \
        tests/Feature/LiveHost/Recruitment/SchemaHelpersTest.php
git commit -m "feat(livehost): default schema constant + model casts for form_data"
```

---

## Task 1.3 — Update factories

**Files:**
- Modify: `database/factories/LiveHostRecruitmentCampaignFactory.php`
- Modify: `database/factories/LiveHostApplicantFactory.php`

Campaign factory: set `form_schema` to `DefaultFormSchema::get()`. Remove the `booted()` stage seeding dependency? No — that stays; stages and form schema are independent concerns.

```php
use App\Support\Recruitment\DefaultFormSchema;

public function definition(): array
{
    $title = $this->faker->unique()->sentence(3);
    return [
        'title' => $title,
        'slug' => \Illuminate\Support\Str::slug($title).'-'.$this->faker->unique()->numberBetween(1000, 9999),
        'description' => $this->faker->paragraph(),
        'status' => 'draft',
        'target_count' => $this->faker->optional()->numberBetween(1, 20),
        'opens_at' => null,
        'closes_at' => null,
        'created_by' => User::factory(),
        'form_schema' => DefaultFormSchema::get(),  // NEW
    ];
}
```

Applicant factory:

```php
use App\Support\Recruitment\DefaultFormSchema;

public function definition(): array
{
    $name = $this->faker->name();
    $email = $this->faker->unique()->safeEmail();
    return [
        'campaign_id' => LiveHostRecruitmentCampaign::factory(),
        'applicant_number' => 'LHA-'.now()->format('Ym').'-'.str_pad((string) $this->faker->unique()->numberBetween(1, 9999), 4, '0', STR_PAD_LEFT),
        'email' => $email,
        'form_data' => [
            'f_name' => $name,
            'f_email' => $email,
            'f_phone' => $this->faker->phoneNumber(),
            'f_platforms' => ['tiktok'],
        ],
        'form_schema_snapshot' => DefaultFormSchema::get(),
        'status' => 'active',
        'applied_at' => now(),
    ];
}
```

Remove factory state references to dropped columns.

**Verify existing tests still work:**

```bash
php artisan test --compact tests/Feature/LiveHost/Recruitment/
```

Expect most tests to break (we haven't updated callers yet). The test count baseline is 55 — we'll track degradation and recovery through the plan.

Stage the tests but DO NOT try to fix all 55 tests in this task. Just make sure the factories produce valid records. Spot-check via tinker:

```bash
php artisan tinker --execute="dd(App\Models\LiveHostApplicant::factory()->make()->toArray());"
```

**Commit:** `feat(livehost): update recruitment factories for form_data shape`

---

## Task 1.4 — Email mirror observer

**Files:**
- Modify: `app/Models/LiveHostApplicant.php` — add a `booted()` saving listener
- Create: `tests/Feature/LiveHost/Recruitment/EmailMirrorTest.php`

The `email` column must always match `form_data[role=email]`. Enforced via model event.

**Step 1: Write the failing test**

```php
<?php

use App\Models\LiveHostApplicant;
use App\Models\LiveHostRecruitmentCampaign;
use App\Support\Recruitment\DefaultFormSchema;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('mirrors form_data email-role value into the email column', function () {
    $campaign = LiveHostRecruitmentCampaign::factory()->create();

    $applicant = LiveHostApplicant::create([
        'campaign_id' => $campaign->id,
        'applicant_number' => LiveHostApplicant::generateApplicantNumber(),
        'email' => 'ignored@initial.example',
        'form_data' => [
            'f_email' => 'real@example.com',
            'f_name' => 'Ahmad',
        ],
        'form_schema_snapshot' => DefaultFormSchema::get(),
        'status' => 'active',
        'applied_at' => now(),
    ]);

    expect($applicant->fresh()->email)->toBe('real@example.com');
});

it('does not overwrite email when no role=email field exists in snapshot', function () {
    $campaign = LiveHostRecruitmentCampaign::factory()->create();
    $snapshotWithoutEmailRole = DefaultFormSchema::get();
    // strip role=email
    foreach ($snapshotWithoutEmailRole['pages'] as &$page) {
        foreach ($page['fields'] as &$field) {
            if (($field['role'] ?? null) === 'email') {
                unset($field['role']);
            }
        }
    }

    $applicant = LiveHostApplicant::create([
        'campaign_id' => $campaign->id,
        'applicant_number' => LiveHostApplicant::generateApplicantNumber(),
        'email' => 'keep@example.com',
        'form_data' => ['f_email' => 'different@example.com'],
        'form_schema_snapshot' => $snapshotWithoutEmailRole,
        'status' => 'active',
        'applied_at' => now(),
    ]);

    expect($applicant->fresh()->email)->toBe('keep@example.com');
});
```

Run to confirm FAIL (observer not wired yet).

**Step 2: Wire the observer**

In `LiveHostApplicant::booted()` (keep the existing stage-creation if any, or add alongside):

```php
protected static function booted(): void
{
    static::saving(function (self $applicant) {
        $snapshot = $applicant->form_schema_snapshot ?? [];
        $emailField = null;
        foreach (($snapshot['pages'] ?? []) as $page) {
            foreach (($page['fields'] ?? []) as $field) {
                if (($field['role'] ?? null) === 'email') {
                    $emailField = $field;
                    break 2;
                }
            }
        }

        if ($emailField) {
            $value = $applicant->form_data[$emailField['id']] ?? null;
            if ($value !== null) {
                $applicant->email = (string) $value;
            }
        }
    });
}
```

Run tests: expect PASS.

**Commit:** `feat(livehost): mirror form_data email role into applicant email column`

---

## Task 1.5 — FormSchemaValidator service

**Files:**
- Create: `app/Services/Recruitment/FormSchemaValidator.php`
- Create: `tests/Feature/LiveHost/Recruitment/FormSchemaValidatorTest.php`

Validates that a schema is well-formed before it's saved on a campaign.

Rules:
- `version` is 1
- `pages` exists and is a non-empty array
- each page has `id`, `title`, `fields`
- page ids unique across schema
- each field has `id`, `type`, `label` (except heading/paragraph which use `text`)
- field ids unique across schema
- field types must be from the allowed vocabulary
- choice fields have at least 1 option; option values unique within a field
- exactly one field has `role: "email"`
- at most one field per other role (name/phone/resume)
- role-tagged fields have compatible types:
  - `email` ↔ type `email`
  - `phone` ↔ type `phone`
  - `name` ↔ type `text`
  - `resume` ↔ type `file`

Raise `\App\Exceptions\Recruitment\InvalidFormSchemaException` with an array of error messages keyed by field/page id.

**Implementation:**

```php
<?php

namespace App\Services\Recruitment;

use App\Exceptions\Recruitment\InvalidFormSchemaException;

class FormSchemaValidator
{
    public const FIELD_TYPES = [
        'text', 'textarea', 'email', 'phone', 'number', 'url',
        'select', 'radio', 'checkbox_group',
        'file', 'date', 'datetime',
        'heading', 'paragraph',
    ];

    public const DATA_TYPES = [
        'text', 'textarea', 'email', 'phone', 'number', 'url',
        'select', 'radio', 'checkbox_group',
        'file', 'date', 'datetime',
    ];

    public const CHOICE_TYPES = ['select', 'radio', 'checkbox_group'];

    public const ROLE_COMPAT = [
        'name' => ['text'],
        'email' => ['email'],
        'phone' => ['phone'],
        'resume' => ['file'],
    ];

    public function validate(array $schema): void
    {
        $errors = [];

        if (($schema['version'] ?? null) !== 1) {
            $errors[] = 'schema.version must be 1';
        }

        $pages = $schema['pages'] ?? null;
        if (! is_array($pages) || count($pages) === 0) {
            $errors[] = 'schema.pages must be a non-empty array';
            throw new InvalidFormSchemaException($errors);
        }

        $pageIds = [];
        $fieldIds = [];
        $rolesSeen = [];
        $hasDataField = false;

        foreach ($pages as $pi => $page) {
            $pid = $page['id'] ?? null;
            if (! $pid) {
                $errors[] = "page[{$pi}].id is required";
            } elseif (in_array($pid, $pageIds, true)) {
                $errors[] = "page[{$pi}].id '{$pid}' is duplicated";
            } else {
                $pageIds[] = $pid;
            }

            if (empty($page['title'])) {
                $errors[] = "page[{$pi}].title is required";
            }

            foreach (($page['fields'] ?? []) as $fi => $field) {
                $fid = $field['id'] ?? null;
                $type = $field['type'] ?? null;

                if (! $fid) {
                    $errors[] = "page[{$pi}].field[{$fi}].id is required";
                    continue;
                }
                if (in_array($fid, $fieldIds, true)) {
                    $errors[] = "field id '{$fid}' is duplicated";
                }
                $fieldIds[] = $fid;

                if (! in_array($type, self::FIELD_TYPES, true)) {
                    $errors[] = "field '{$fid}' has invalid type '{$type}'";
                    continue;
                }

                $isDisplayOnly = in_array($type, ['heading', 'paragraph'], true);

                if ($isDisplayOnly) {
                    if (empty($field['text'])) {
                        $errors[] = "field '{$fid}' of type {$type} requires 'text'";
                    }
                } else {
                    $hasDataField = true;
                    if (empty($field['label'])) {
                        $errors[] = "field '{$fid}' label is required";
                    }

                    if (in_array($type, self::CHOICE_TYPES, true)) {
                        $opts = $field['options'] ?? [];
                        if (! is_array($opts) || count($opts) === 0) {
                            $errors[] = "field '{$fid}' must have at least one option";
                        } else {
                            $values = [];
                            foreach ($opts as $oi => $opt) {
                                if (empty($opt['value'])) {
                                    $errors[] = "field '{$fid}' option[{$oi}] value is required";
                                } elseif (in_array($opt['value'], $values, true)) {
                                    $errors[] = "field '{$fid}' option value '{$opt['value']}' is duplicated";
                                } else {
                                    $values[] = $opt['value'];
                                }
                                if (empty($opt['label'])) {
                                    $errors[] = "field '{$fid}' option[{$oi}] label is required";
                                }
                            }
                        }
                    }

                    $role = $field['role'] ?? null;
                    if ($role !== null && $role !== '') {
                        if (! isset(self::ROLE_COMPAT[$role])) {
                            $errors[] = "field '{$fid}' has unknown role '{$role}'";
                        } else {
                            if (isset($rolesSeen[$role])) {
                                $errors[] = "role '{$role}' is used by more than one field (also on '{$rolesSeen[$role]}')";
                            } else {
                                $rolesSeen[$role] = $fid;
                            }
                            if (! in_array($type, self::ROLE_COMPAT[$role], true)) {
                                $errors[] = "field '{$fid}' role '{$role}' is incompatible with type '{$type}'";
                            }
                        }
                    }
                }
            }
        }

        if (! $hasDataField) {
            $errors[] = 'schema must contain at least one data-collecting field';
        }

        if (! isset($rolesSeen['email'])) {
            $errors[] = 'schema must contain exactly one field with role "email"';
        }

        if ($errors !== []) {
            throw new InvalidFormSchemaException($errors);
        }
    }
}
```

**Exception:**

Create `app/Exceptions/Recruitment/InvalidFormSchemaException.php`:

```php
<?php

namespace App\Exceptions\Recruitment;

class InvalidFormSchemaException extends \RuntimeException
{
    /** @var string[] */
    public array $errors;

    public function __construct(array $errors)
    {
        $this->errors = $errors;
        parent::__construct('Invalid form schema: '.implode('; ', $errors));
    }
}
```

**Test file:**

Cover: valid default schema, missing email role, duplicate field ids, invalid type, incompatible role/type, empty options, missing label on data field.

```php
<?php

use App\Exceptions\Recruitment\InvalidFormSchemaException;
use App\Services\Recruitment\FormSchemaValidator;
use App\Support\Recruitment\DefaultFormSchema;

it('accepts the default schema', function () {
    (new FormSchemaValidator())->validate(DefaultFormSchema::get());

    expect(true)->toBeTrue();  // no throw
});

it('rejects a schema with no email role', function () {
    $schema = DefaultFormSchema::get();
    // strip the email role
    foreach ($schema['pages'] as &$page) {
        foreach ($page['fields'] as &$field) {
            if (($field['role'] ?? null) === 'email') {
                unset($field['role']);
            }
        }
    }
    unset($page, $field);

    expect(fn () => (new FormSchemaValidator())->validate($schema))
        ->toThrow(InvalidFormSchemaException::class, 'role "email"');
});

it('rejects duplicate field ids', function () {
    $schema = [
        'version' => 1,
        'pages' => [[
            'id' => 'p1', 'title' => 'P',
            'fields' => [
                ['id' => 'dup', 'type' => 'text', 'label' => 'A'],
                ['id' => 'dup', 'type' => 'email', 'label' => 'B', 'role' => 'email'],
            ],
        ]],
    ];

    expect(fn () => (new FormSchemaValidator())->validate($schema))
        ->toThrow(InvalidFormSchemaException::class, "'dup' is duplicated");
});

it('rejects incompatible role and type', function () {
    $schema = [
        'version' => 1,
        'pages' => [[
            'id' => 'p1', 'title' => 'P',
            'fields' => [
                ['id' => 'x', 'type' => 'text', 'label' => 'X', 'role' => 'email'],
            ],
        ]],
    ];

    expect(fn () => (new FormSchemaValidator())->validate($schema))
        ->toThrow(InvalidFormSchemaException::class, 'incompatible');
});

it('rejects choice field with no options', function () {
    $schema = [
        'version' => 1,
        'pages' => [[
            'id' => 'p1', 'title' => 'P',
            'fields' => [
                ['id' => 'e', 'type' => 'email', 'label' => 'E', 'role' => 'email'],
                ['id' => 's', 'type' => 'select', 'label' => 'S'],
            ],
        ]],
    ];

    expect(fn () => (new FormSchemaValidator())->validate($schema))
        ->toThrow(InvalidFormSchemaException::class, 'at least one option');
});
```

Run: all green. Commit: `feat(livehost): FormSchemaValidator service`

---

## Task 1.6 — FormRuleBuilder service

**Files:**
- Create: `app/Services/Recruitment/FormRuleBuilder.php`
- Create: `tests/Feature/LiveHost/Recruitment/FormRuleBuilderTest.php`

Turns a schema into a dynamic Laravel validation ruleset. Given a schema, `build($schema)` returns `['f_name' => ['required', 'string', 'max:255'], 'f_email' => [...], ...]`.

Rules per type:

| Type | Rules |
|---|---|
| text | string, max:255 |
| textarea | string, max:5000 |
| email | email, max:255 |
| phone | string, max:50 |
| number | numeric |
| url | url, max:1000 |
| select | string, in:<values> |
| radio | string, in:<values> |
| checkbox_group | array, array.* in:<values>, array.min:1 if required |
| file | file, mimes:<accept>, max:<max_size_kb> |
| date | date |
| datetime | date |
| heading | (skipped — no rule) |
| paragraph | (skipped — no rule) |

Prepend `required` or `nullable` based on the field's `required` flag.

Skip display-only types entirely.

Implementation outline:

```php
<?php

namespace App\Services\Recruitment;

class FormRuleBuilder
{
    public function build(array $schema): array
    {
        $rules = [];
        foreach (($schema['pages'] ?? []) as $page) {
            foreach (($page['fields'] ?? []) as $field) {
                if (in_array($field['type'], ['heading', 'paragraph'], true)) {
                    continue;
                }
                $rules[$field['id']] = $this->rulesForField($field);
            }
        }
        return $rules;
    }

    private function rulesForField(array $field): array
    {
        $required = (bool) ($field['required'] ?? false);
        $rules = [$required ? 'required' : 'nullable'];

        switch ($field['type']) {
            case 'text':
                $rules[] = 'string';
                $rules[] = 'max:255';
                break;
            case 'textarea':
                $rules[] = 'string';
                $rules[] = 'max:5000';
                break;
            case 'email':
                $rules[] = 'email';
                $rules[] = 'max:255';
                break;
            case 'phone':
                $rules[] = 'string';
                $rules[] = 'max:50';
                break;
            case 'number':
                $rules[] = 'numeric';
                break;
            case 'url':
                $rules[] = 'url';
                $rules[] = 'max:1000';
                break;
            case 'select':
            case 'radio':
                $rules[] = 'string';
                $values = array_column($field['options'] ?? [], 'value');
                if ($values) {
                    $rules[] = \Illuminate\Validation\Rule::in($values);
                }
                break;
            case 'checkbox_group':
                $rules[] = 'array';
                if ($required) {
                    $rules[] = 'min:1';
                }
                break;
            case 'date':
            case 'datetime':
                $rules[] = 'date';
                break;
            case 'file':
                $rules[] = 'file';
                if (! empty($field['accept'])) {
                    $rules[] = 'mimes:'.implode(',', $field['accept']);
                }
                if (! empty($field['max_size_kb'])) {
                    $rules[] = 'max:'.(int) $field['max_size_kb'];
                }
                break;
        }

        return $rules;
    }

    public function buildArrayItemRules(array $schema): array
    {
        // For checkbox_group fields, return per-item rules (e.g., f_platforms.*)
        $rules = [];
        foreach (($schema['pages'] ?? []) as $page) {
            foreach (($page['fields'] ?? []) as $field) {
                if ($field['type'] === 'checkbox_group') {
                    $values = array_column($field['options'] ?? [], 'value');
                    $itemRules = ['string'];
                    if ($values) {
                        $itemRules[] = \Illuminate\Validation\Rule::in($values);
                    }
                    $rules["{$field['id']}.*"] = $itemRules;
                }
            }
        }
        return $rules;
    }
}
```

**Test file:** covers at least: default schema returns full rule set; required toggles `required` ↔ `nullable`; file field emits mimes + max; choice fields emit `in:` correctly; display-only fields excluded.

Commit: `feat(livehost): FormRuleBuilder service`

---

# Milestone 2 — Public form backend

Rewrite submission to be schema-driven. Restore all existing recruitment feature tests to green.

## Task 2.1 — Rewrite ApplyRequest

**Files:**
- Modify: `app/Http/Requests/LiveHost/Recruitment/ApplyRequest.php`

Strip the hardcoded rules. Resolve the campaign from the route parameter, pass its `form_schema` to `FormRuleBuilder`, merge the generated rules with a catch-all:

```php
public function rules(): array
{
    $slug = $this->route('slug');
    $campaign = \App\Models\LiveHostRecruitmentCampaign::where('slug', $slug)->firstOrFail();

    $builder = new \App\Services\Recruitment\FormRuleBuilder();
    $rules = $builder->build($campaign->form_schema);
    $rules = array_merge($rules, $builder->buildArrayItemRules($campaign->form_schema));

    return $rules;
}
```

**Commit:** `feat(livehost): schema-driven validation in ApplyRequest`

---

## Task 2.2 — Rewrite `apply()` controller action

**Files:**
- Modify: `app/Http/Controllers/LiveHost/PublicRecruitmentController.php`

The `apply()` method now:

1. Loads campaign + schema
2. Validates via `ApplyRequest`
3. For each file-typed field, if a file was uploaded, stores it and replaces the request value with the path
4. Builds `form_data` dict from validated input keyed by field id
5. Email dedupe uses `applicant.email` column via the role-resolved email value
6. Inserts applicant with `form_data` + `form_schema_snapshot` = current campaign schema
7. Writes `applied` history, queues mail, redirects

Reference code — replace the entire existing method:

```php
public function apply(ApplyRequest $request, string $slug): RedirectResponse
{
    $campaign = LiveHostRecruitmentCampaign::with('stages')->where('slug', $slug)->firstOrFail();
    abort_unless($campaign->isAcceptingApplications(), 410);

    $validated = $request->validated();
    $schema = $campaign->form_schema;

    // Resolve email value via role
    $emailFieldId = null;
    foreach ($campaign->getAllFields() as $field) {
        if (($field['role'] ?? null) === 'email') {
            $emailFieldId = $field['id'];
            break;
        }
    }
    $emailValue = $emailFieldId ? ($validated[$emailFieldId] ?? null) : null;

    abort_if($emailValue === null, 422, 'Campaign schema is missing an email-role field.');

    // Dedupe
    if (LiveHostApplicant::where('campaign_id', $campaign->id)->where('email', $emailValue)->exists()) {
        return back()->withInput()->withErrors([
            $emailFieldId => 'You have already applied to this campaign with this email.',
        ]);
    }

    // Handle file uploads
    $uploadedPaths = [];
    foreach ($campaign->getAllFields() as $field) {
        if ($field['type'] === 'file' && $request->hasFile($field['id'])) {
            $path = $request->file($field['id'])->store('recruitment/resumes', 'local');
            $uploadedPaths[$field['id']] = $path;
            $validated[$field['id']] = $path;
        }
    }

    try {
        $applicant = DB::transaction(function () use ($validated, $campaign, $schema) {
            $firstStage = $campaign->stages->sortBy('position')->first();
            $applicant = LiveHostApplicant::create([
                'campaign_id' => $campaign->id,
                'applicant_number' => LiveHostApplicant::generateApplicantNumber(),
                'email' => $validated[$this->emailFieldIdFromSchema($schema)] ?? '',
                'form_data' => $validated,
                'form_schema_snapshot' => $schema,
                'current_stage_id' => $firstStage?->id,
                'status' => 'active',
                'applied_at' => now(),
            ]);
            $applicant->history()->create([
                'to_stage_id' => $firstStage?->id,
                'action' => 'applied',
            ]);
            return $applicant;
        });
    } catch (\Illuminate\Database\QueryException $e) {
        foreach ($uploadedPaths as $path) {
            \Illuminate\Support\Facades\Storage::disk('local')->delete($path);
        }
        if ($e->getCode() === '23000' && $emailFieldId) {
            return back()->withInput()->withErrors([
                $emailFieldId => 'You have already applied to this campaign with this email.',
            ]);
        }
        throw $e;
    }

    \Illuminate\Support\Facades\Mail::to((string) $applicant->email)
        ->queue(new \App\Mail\LiveHost\Recruitment\ApplicationReceivedMail($applicant));

    return redirect()
        ->route('recruitment.thank-you', $slug)
        ->with('applicant_number', $applicant->applicant_number)
        ->with('applicant_name', $applicant->valueByRole('name') ?? '')
        ->with('applicant_email', $applicant->email);
}

private function emailFieldIdFromSchema(array $schema): ?string
{
    foreach (($schema['pages'] ?? []) as $page) {
        foreach (($page['fields'] ?? []) as $field) {
            if (($field['role'] ?? null) === 'email') {
                return $field['id'];
            }
        }
    }
    return null;
}
```

**Commit:** `feat(livehost): schema-driven public recruitment submission`

---

## Task 2.3 — Rewrite the public form blade (renderer)

**Files:**
- Modify: `resources/views/recruitment/show.blade.php`
- Create: `resources/views/recruitment/fields/text.blade.php`
- Create: `resources/views/recruitment/fields/textarea.blade.php`
- Create: `resources/views/recruitment/fields/email.blade.php`
- Create: `resources/views/recruitment/fields/phone.blade.php`
- Create: `resources/views/recruitment/fields/number.blade.php`
- Create: `resources/views/recruitment/fields/url.blade.php`
- Create: `resources/views/recruitment/fields/select.blade.php`
- Create: `resources/views/recruitment/fields/radio.blade.php`
- Create: `resources/views/recruitment/fields/checkbox_group.blade.php`
- Create: `resources/views/recruitment/fields/file.blade.php`
- Create: `resources/views/recruitment/fields/date.blade.php`
- Create: `resources/views/recruitment/fields/datetime.blade.php`
- Create: `resources/views/recruitment/fields/heading.blade.php`
- Create: `resources/views/recruitment/fields/paragraph.blade.php`

Keep all existing styling (Geist, emerald accent, modern design from v2). Only the inner structure becomes schema-driven.

Inside `show.blade.php`, replace the body of the `<form>` with:

```blade
@foreach (($campaign->form_schema['pages'] ?? []) as $page)
    <div class="section-label" @if ($loop->first) style="margin-top: 0;" @endif>
        <span class="section-label-text">{{ sprintf('%02d', $loop->iteration) }} · {{ $page['title'] }}</span>
    </div>

    <div class="space-y-4">
        @foreach (($page['fields'] ?? []) as $field)
            @include("recruitment.fields.{$field['type']}", ['field' => $field])
        @endforeach
    </div>
@endforeach
```

Each field partial follows the same pattern. Example — `fields/text.blade.php`:

```blade
<div>
    <label for="{{ $field['id'] }}" class="field-label">
        {{ $field['label'] }}
        @if ($field['required'] ?? false)<span class="required-dot" aria-label="required"></span>@endif
    </label>
    <input type="text" id="{{ $field['id'] }}" name="{{ $field['id'] }}"
           value="{{ old($field['id']) }}"
           placeholder="{{ $field['placeholder'] ?? '' }}"
           @if ($field['required'] ?? false) required @endif
           class="input-base">
    @if (! empty($field['help_text']))
        <p class="hint-text">{{ $field['help_text'] }}</p>
    @endif
    @error($field['id'])
        <p class="hint-text" style="color: var(--rec-danger);">{{ $message }}</p>
    @enderror
</div>
```

Repeat pattern for each type. `checkbox_group.blade.php` iterates `options`; `select.blade.php` emits `<select>`; `file.blade.php` keeps the nice drop-zone; heading/paragraph are pure display.

**Commit:** `feat(livehost): schema-driven public form renderer with field partials`

---

## Task 2.4 — Restore recruitment test suite

**Files:**
- Modify: `tests/Feature/LiveHost/Recruitment/PublicApplicationTest.php`
- Modify: `tests/Feature/LiveHost/Recruitment/ApplicantReviewTest.php`
- Modify: `tests/Feature/LiveHost/Recruitment/CampaignCrudTest.php`
- Modify: `tests/Feature/LiveHost/Recruitment/HireActionTest.php`
- Modify: `tests/Feature/LiveHost/Recruitment/StageEditorTest.php`
- Modify: `tests/Feature/LiveHost/Recruitment/CampaignStagesTest.php` (if affected)

Each test that references dropped columns must be rewritten:
- `full_name` → `form_data.f_name` or via `$applicant->name` accessor
- `phone` → `form_data.f_phone` or `$applicant->phone`
- `platforms` → `form_data.f_platforms`
- All POST payloads to `recruitment.apply` now key by field id, not column name

Example — happy path test:

```php
it('accepts a valid application', function () {
    $campaign = LiveHostRecruitmentCampaign::factory()->open()->create();

    $response = $this->post(route('recruitment.apply', $campaign->slug), [
        'f_name' => 'Ahmad Test',
        'f_email' => 'ahmad.test@example.com',
        'f_phone' => '60123456789',
        'f_platforms' => ['tiktok'],
        'f_experience' => 'Some experience',
        'f_motivation' => 'Because I love live selling',
    ]);

    $response->assertRedirect(route('recruitment.thank-you', $campaign->slug));

    $applicant = LiveHostApplicant::where('email', 'ahmad.test@example.com')->firstOrFail();
    expect($applicant->campaign_id)->toBe($campaign->id);
    expect($applicant->form_data['f_name'])->toBe('Ahmad Test');
    expect($applicant->form_data['f_platforms'])->toEqual(['tiktok']);
});
```

Iterate through every test file; run `php artisan test --compact tests/Feature/LiveHost/Recruitment/` at the end. Must be green (target: 55+ tests passing).

**Commit:** `test(livehost): migrate recruitment tests to schema-driven form_data`

---

# Milestone 3 — Admin review + hire updates

## Task 3.1 — Update index controller to resolve name/platforms via roles

**Files:**
- Modify: `app/Http/Controllers/LiveHost/RecruitmentApplicantController.php`

In `index()`, change the applicant map to resolve name and platforms from `form_data` + snapshot. Add a `valueByRole` helper call if not already in model (from Task 1.2).

```php
'applicants' => $applicants->map(fn (LiveHostApplicant $a) => [
    'id' => $a->id,
    'applicant_number' => $a->applicant_number,
    'full_name' => $a->name,  // accessor from model
    'email' => $a->email,
    'platforms' => $a->valueByRole('platforms') ?? [],
    'rating' => $a->rating,
    'current_stage_id' => $a->current_stage_id,
    'status' => $a->status,
    'applied_at' => $a->applied_at?->toIso8601String(),
    'applied_at_human' => $a->applied_at?->diffForHumans(),
])->values(),
```

The Inertia prop shape is unchanged, so `Index.jsx` doesn't need changes.

Note the existing plan referenced `role: "platforms"` — we didn't put that role in the validator's allowed list. Either add it (and to DefaultFormSchema's platform field), or map platforms implicitly by checking the first `checkbox_group` field. Going with the former: add `platforms` to `FormSchemaValidator::ROLE_COMPAT` as `['checkbox_group']`. Add `role: 'platforms'` to DefaultFormSchema.

**Commit:** `feat(livehost): resolve applicant name/platforms via role tags`

---

## Task 3.2 — Rewrite applicant show to render from snapshot

**Files:**
- Modify: `app/Http/Controllers/LiveHost/RecruitmentApplicantController.php` — `show()`
- Modify: `resources/js/livehost/pages/recruitment/applicants/Show.jsx`

Controller `show()` passes the snapshot + form_data to Inertia. Remove references to dropped columns:

```php
return Inertia::render('recruitment/applicants/Show', [
    'applicant' => [
        'id' => $applicant->id,
        'applicant_number' => $applicant->applicant_number,
        'full_name' => $applicant->name,
        'email' => $applicant->email,
        'status' => $applicant->status,
        'rating' => $applicant->rating,
        'notes' => $applicant->notes,
        'applied_at' => $applicant->applied_at?->toIso8601String(),
        'hired_user_id' => $applicant->hired_user_id,
        'current_stage' => $applicant->currentStage?->only(['id', 'name', 'position', 'is_final']),
        'form_data' => $applicant->form_data,
        'form_schema_snapshot' => $applicant->form_schema_snapshot,
    ],
    // ... existing history + stages
]);
```

`Show.jsx` Application tab: iterate `form_schema_snapshot.pages[].fields[]`, for each data field render label + the value formatted by type. For `select`/`radio`/`checkbox_group` look up option labels from the snapshot. For `file` render a download link (`/storage/…`). For `heading`/`paragraph` skip.

Keep tabs, action bar, history timeline, hire flow UI as-is.

**Commit:** `feat(livehost): applicant show renders from form_schema_snapshot`

---

## Task 3.3 — Update hire controller + tests

**Files:**
- Modify: `app/Http/Controllers/LiveHost/RecruitmentApplicantController.php` — `hire()`
- Modify: `tests/Feature/LiveHost/Recruitment/HireActionTest.php`

`hire()` already validates `full_name`, `email`, `phone` from the admin confirm modal — those still come from the admin's form input, not from applicant data. But the modal's *pre-filled values* should come from `form_data` via roles.

The change: in `Show.jsx`, when opening the hire modal, use `applicant.name` / `applicant.email` / `applicant.phone` (accessors) to prefill. No backend change required for the hire call itself — the payload shape is unchanged.

Add a guard to `hire()`: if the campaign's snapshot has no `role: "email"` (shouldn't happen since validator prevents it, but belt-and-suspenders), return 422.

Tests unchanged in shape; adjust any references to dropped columns.

**Commit:** `feat(livehost): hire reads prefill from form_data via role accessors`

---

# Milestone 4 — Form builder UI

The biggest milestone. Build the 3-panel React form builder embedded in the campaign Edit page.

## Task 4.1 — Campaign update accepts form_schema

**Files:**
- Modify: `app/Http/Requests/LiveHost/Recruitment/CampaignRequest.php`
- Modify: `app/Http/Controllers/LiveHost/RecruitmentCampaignController.php` — `update()`
- Create: `tests/Feature/LiveHost/Recruitment/CampaignFormSchemaTest.php`

Add `form_schema` to the request's rules:

```php
'form_schema' => ['nullable', 'array'],
```

In `update()`, if `form_schema` is present, validate it via `FormSchemaValidator` and 422 with its error messages if invalid. Block status transition to `open` (`publish`) if campaign has no schema or schema lacks email role.

Test cases:
1. Valid schema saves successfully
2. Invalid schema returns 422 with error list
3. Cannot publish without valid schema

**Commit:** `feat(livehost): campaign update accepts and validates form_schema`

---

## Task 4.2 — Field type registry (JS)

**Files:**
- Create: `resources/js/livehost/components/form-builder/fieldTypes.js`

Defines the 14 field types as objects with:
- `type` — string id
- `label` — human label in the picker ("Short text", "Email", …)
- `group` — "Text" / "Choice" / "Other" / "Display"
- `icon` — lucide icon name
- `isData` — whether it collects data (false for heading/paragraph)
- `defaultSettings(id)` — returns the starter field object when added
- `validateSettings(field)` — returns `{}` or error map

```js
import { Type, FileText, Mail, Phone, Hash, Link2, ListChecks, CircleCheck, CheckSquare, Paperclip, Calendar, CalendarClock, Heading2, AlignLeft } from 'lucide-react';

const nextId = (prefix) => `${prefix}_${Math.random().toString(36).slice(2, 8)}`;

export const FIELD_TYPES = [
  { type: 'text',            label: 'Short text',  group: 'Text',   icon: Type,         isData: true,  defaultSettings: () => ({ id: nextId('f'), type: 'text', label: 'New field', required: false }) },
  { type: 'textarea',        label: 'Long text',   group: 'Text',   icon: AlignLeft,    isData: true,  defaultSettings: () => ({ id: nextId('f'), type: 'textarea', label: 'New field', required: false, rows: 4 }) },
  { type: 'email',           label: 'Email',       group: 'Text',   icon: Mail,         isData: true,  defaultSettings: () => ({ id: nextId('f'), type: 'email', label: 'Email', required: true, role: 'email' }) },
  { type: 'phone',           label: 'Phone',       group: 'Text',   icon: Phone,        isData: true,  defaultSettings: () => ({ id: nextId('f'), type: 'phone', label: 'Phone', required: false }) },
  { type: 'number',          label: 'Number',      group: 'Text',   icon: Hash,         isData: true,  defaultSettings: () => ({ id: nextId('f'), type: 'number', label: 'Number', required: false }) },
  { type: 'url',             label: 'URL',         group: 'Text',   icon: Link2,        isData: true,  defaultSettings: () => ({ id: nextId('f'), type: 'url', label: 'URL', required: false }) },
  { type: 'select',          label: 'Dropdown',    group: 'Choice', icon: ListChecks,   isData: true,  defaultSettings: () => ({ id: nextId('f'), type: 'select', label: 'Dropdown', required: false, options: [{ label: 'Option 1', value: 'opt_1' }] }) },
  { type: 'radio',           label: 'Single choice', group: 'Choice', icon: CircleCheck, isData: true, defaultSettings: () => ({ id: nextId('f'), type: 'radio', label: 'Single choice', required: false, options: [{ label: 'Option 1', value: 'opt_1' }] }) },
  { type: 'checkbox_group',  label: 'Multi choice', group: 'Choice', icon: CheckSquare, isData: true,  defaultSettings: () => ({ id: nextId('f'), type: 'checkbox_group', label: 'Multi choice', required: false, options: [{ label: 'Option 1', value: 'opt_1' }] }) },
  { type: 'file',            label: 'File upload', group: 'Other',  icon: Paperclip,    isData: true,  defaultSettings: () => ({ id: nextId('f'), type: 'file', label: 'Upload', required: false, accept: ['pdf'], max_size_kb: 5120 }) },
  { type: 'date',            label: 'Date',        group: 'Other',  icon: Calendar,     isData: true,  defaultSettings: () => ({ id: nextId('f'), type: 'date', label: 'Date', required: false }) },
  { type: 'datetime',        label: 'Date & time', group: 'Other',  icon: CalendarClock, isData: true, defaultSettings: () => ({ id: nextId('f'), type: 'datetime', label: 'Date & time', required: false }) },
  { type: 'heading',         label: 'Heading',     group: 'Display', icon: Heading2,    isData: false, defaultSettings: () => ({ id: nextId('b'), type: 'heading', text: 'Section heading' }) },
  { type: 'paragraph',       label: 'Paragraph',   group: 'Display', icon: AlignLeft,   isData: false, defaultSettings: () => ({ id: nextId('b'), type: 'paragraph', text: 'Explanatory text.' }) },
];

export const ROLE_COMPAT = {
  name: ['text'],
  email: ['email'],
  phone: ['phone'],
  resume: ['file'],
  platforms: ['checkbox_group'],
};

export function findFieldType(type) {
  return FIELD_TYPES.find((t) => t.type === type);
}
```

**Commit:** `feat(livehost): form builder field type registry`

---

## Task 4.3 — PageList component

**Files:**
- Create: `resources/js/livehost/components/form-builder/PageList.jsx`

Left rail. Props:
- `pages` — array of `{id, title, fields}`
- `selectedPageId`
- `onSelect(id)`
- `onReorder(pageIds)` — called after drag
- `onAdd()`
- `onRename(id, newTitle)`
- `onDelete(id)`

Uses `@hello-pangea/dnd` for drag-to-reorder. Render each page as a compact row with title + field count + ⋯ menu. "Add page" button at bottom.

**Commit:** `feat(livehost): form builder PageList component`

---

## Task 4.4 — FieldCanvas component

**Files:**
- Create: `resources/js/livehost/components/form-builder/FieldCanvas.jsx`

Middle panel. Props:
- `page` — the selected page object
- `selectedFieldId`
- `onSelectField(id)`
- `onReorderFields(fieldIds)`
- `onAddField(type)`
- `onDeleteField(id, isUsed)` — `isUsed` disables delete with tooltip
- `applicantCounts` — `Map<fieldId, count>` from backend (for delete guards)

Each field renders as a non-interactive preview card. Dragging reorders. Clicking selects.

"+ Add field" opens a dropdown with the 14 types grouped by category. Type picker uses `fieldTypes.js`.

Headings/paragraphs render as their actual heading/paragraph (not input preview).

**Commit:** `feat(livehost): form builder FieldCanvas component`

---

## Task 4.5 — FieldSettings component

**Files:**
- Create: `resources/js/livehost/components/form-builder/FieldSettings.jsx`

Right rail. Props:
- `field` — the selected field
- `onChange(field)` — updates via callback
- `canChangeType` — boolean (false if field has submitted data)

Renders form controls based on `field.type`:
- Common: Label, Placeholder, Help text, Required toggle
- Role dropdown (only options compatible with current type)
- Choice types: options editor (list with drag-reorder, add, remove)
- File: accept extensions multi-select, max size input
- Textarea: rows slider
- Heading/paragraph: single `text` field

Emits changes via `onChange` with the full updated field object.

**Commit:** `feat(livehost): form builder FieldSettings component`

---

## Task 4.6 — FormBuilder (root component) + schema validator (JS)

**Files:**
- Create: `resources/js/livehost/components/form-builder/FormBuilder.jsx`
- Create: `resources/js/livehost/components/form-builder/validateSchema.js`

Root component composing PageList + FieldCanvas + FieldSettings. Holds schema state, propagates changes upward via `onChange(schema)`.

Includes a **frontend validator** that mirrors the PHP `FormSchemaValidator`. Runs on save attempt; shows errors inline (per-field) before the HTTP round-trip.

**Commit:** `feat(livehost): form builder root component with frontend validation`

---

## Task 4.7 — Integrate FormBuilder into campaign Edit page

**Files:**
- Modify: `resources/js/livehost/pages/recruitment/campaigns/Edit.jsx`

Add a new tab labelled "Application form" alongside the existing campaign fields + stage editor. Host the `<FormBuilder>` inside it.

Add a Preview button (links to `/recruitment/{slug}?preview=1` — opens public form in new tab; campaign must be published for this to render, otherwise show a notice to publish first).

Wire the schema to form submission: include `form_schema` in the Inertia `useForm` data; the existing update PUT carries it to the backend.

**Commit:** `feat(livehost): Application form tab with drag-and-drop builder`

---

## Task 4.8 — Feature test: campaign save round-trip

**Files:**
- Create: `tests/Feature/LiveHost/Recruitment/FormBuilderIntegrationTest.php`

Posts a custom schema via the update endpoint, then fetches the public form and asserts that:
- The custom fields render
- Submitting the form with matching field IDs creates an applicant
- Applicant's `form_schema_snapshot` equals the schema at submit time, not the current campaign schema after a later edit

**Commit:** `test(livehost): form builder round-trip + schema snapshotting`

---

# Milestone 5 — Browser E2E + regression

## Task 5.1 — Pest 4 browser test

**Files:**
- Create: `tests/Browser/LiveHost/Recruitment/FormBuilderE2ETest.php`

Scenario:
1. As admin: create campaign → open application form tab
2. Add a page, add a text field, rename to "TikTok handle"
3. Save campaign, publish
4. Log out, visit public URL
5. Fill form, submit
6. Log back in as admin, view applicant → confirm "TikTok handle" shows with the submitted value

This test likely depends on authenticated cookies / fresh session. Use the same `visit(...)` flow that the existing public browser test uses.

**Commit:** `test(livehost): browse end-to-end form builder flow`

---

## Task 5.2 — Full regression pass

```bash
vendor/bin/pint --dirty
php artisan test --compact tests/Feature/LiveHost/Recruitment/
php artisan test --compact tests/Feature/LiveHost/
npm run build
```

All green. Any failures resolved in-place (don't defer).

**Commit:** `chore(livehost): pint + final regression for form builder`

---

## Task 5.3 — Drop old feature-test references

**Files:**
- Modify: `tests/Feature/LiveHost/Recruitment/*.php`

Search and remove any lingering references to the dropped columns:
```bash
grep -rn "full_name\|ic_number\|experience_summary\|motivation\|resume_path" tests/Feature/LiveHost/Recruitment/
```

Expect 0 matches when done.

**Commit:** `chore(livehost): remove dead references to dropped applicant columns`

---

# Final checklist

- [ ] Migration runs cleanly forward + backward on both SQLite and (if available) MySQL
- [ ] 55 pre-existing recruitment tests pass after refactor
- [ ] New tests: SchemaHelpersTest, EmailMirrorTest, FormSchemaValidatorTest, FormRuleBuilderTest, FormBuilderIntegrationTest, FormBuilderE2ETest all green
- [ ] Public form renders every field type correctly
- [ ] Admin form builder: add page → add field → configure → save → edit → reorder all work
- [ ] Schema snapshot preserved per-applicant even when campaign schema later changes
- [ ] Hire flow resolves name/email/phone via roles
- [ ] Cannot publish a campaign with no `role: "email"` field
- [ ] Pint clean
- [ ] `npm run build` succeeds

---

## Execution handoff

Plan complete and saved to `docs/plans/2026-04-24-recruitment-form-builder.md`. Two execution options:

**1. Subagent-Driven (this session)** — dispatch fresh subagent per milestone, review between, fast iteration.

**2. Parallel Session (separate)** — open a new session with executing-plans, batch execution with checkpoints.

Which approach?
