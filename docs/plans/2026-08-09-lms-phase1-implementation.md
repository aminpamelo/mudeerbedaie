# LMS Phase 1 Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Build 4 foundation LMS modules — all class-centric: Content Library, Progress Dashboard, Announcements, and Class Storefront Revamp.

**Architecture:** Livewire Volt components following existing tab-based patterns in student/teacher class-show pages. New Eloquent models with factories. Migrations compatible with MySQL + SQLite. Tests with Pest.

**Tech Stack:** Laravel 12, Livewire Volt (class-based), Flux UI, Tailwind CSS v4, Pest PHP 4, SQLite (dev) / MySQL (prod)

---

## Task 1: Migration — Add storefront fields to classes table

**Files:**
- Create: `database/migrations/2026_08_09_000001_add_storefront_fields_to_classes_table.php`

**Step 1: Create migration**

```bash
php artisan make:migration add_storefront_fields_to_classes_table --table=classes --no-interaction
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
        Schema::table('classes', function (Blueprint $table): void {
            $table->boolean('show_on_storefront')->default(false)->index()->after('status');
            $table->text('storefront_description')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('classes', function (Blueprint $table): void {
            $table->dropColumn(['show_on_storefront', 'storefront_description']);
        });
    }
};
```

**Step 3: Run migration**

```bash
php artisan migrate
```

**Step 4: Update ClassModel fillable + casts**

In `app/Models/ClassModel.php`, add `'show_on_storefront'` and `'storefront_description'` to `$fillable`. Add `'show_on_storefront' => 'boolean'` to `casts()`.

**Step 5: Add scope to ClassModel**

```php
public function scopeStorefrontVisible($query)
{
    return $query->where('status', 'active')->where('show_on_storefront', true);
}
```

**Step 6: Commit**

```bash
git add -A && git commit -m "feat(lms): add storefront fields to classes table"
```

---

## Task 2: Migration — Create class_resources table

**Files:**
- Create: `database/migrations/2026_08_09_000002_create_class_resources_table.php`

**Step 1: Create migration**

```bash
php artisan make:migration create_class_resources_table --no-interaction
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
        Schema::create('class_resources', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignId('session_id')->nullable()->constrained('class_sessions')->nullOnDelete();
            $table->foreignId('uploaded_by')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->string('type'); // recording, pdf, audio, image, link, note
            $table->string('file_path')->nullable();
            $table->string('url')->nullable();
            $table->text('content')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_published')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['class_id', 'is_published']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_resources');
    }
};
```

**Step 3: Run migration**

```bash
php artisan migrate
```

**Step 4: Commit**

```bash
git add -A && git commit -m "feat(lms): create class_resources table"
```

---

## Task 3: Migration — Create class_resource_views table

**Files:**
- Create: `database/migrations/2026_08_09_000003_create_class_resource_views_table.php`

**Step 1: Create migration**

```bash
php artisan make:migration create_class_resource_views_table --no-interaction
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
        Schema::create('class_resource_views', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('class_resource_id')->constrained('class_resources')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->timestamp('first_viewed_at');
            $table->timestamp('last_viewed_at');
            $table->unsignedInteger('view_count')->default(1);
            $table->timestamps();

            $table->unique(['class_resource_id', 'student_id'], 'crv_resource_student_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_resource_views');
    }
};
```

**Step 3: Run migration**

```bash
php artisan migrate
```

**Step 4: Commit**

```bash
git add -A && git commit -m "feat(lms): create class_resource_views table"
```

---

## Task 4: Migration — Create student_milestones table

**Files:**
- Create: `database/migrations/2026_08_09_000004_create_student_milestones_table.php`

**Step 1: Create migration**

```bash
php artisan make:migration create_student_milestones_table --no-interaction
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
        Schema::create('student_milestones', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('class_student_id')->constrained('class_students')->cascadeOnDelete();
            $table->string('title');
            $table->timestamp('achieved_at');
            $table->foreignId('awarded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type'); // attendance, syllabus, custom
            $table->timestamps();

            $table->index(['class_student_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_milestones');
    }
};
```

**Step 3: Run migration**

```bash
php artisan migrate
```

**Step 4: Commit**

```bash
git add -A && git commit -m "feat(lms): create student_milestones table"
```

---

## Task 5: Migration — Create class_announcements + class_announcement_reads tables

**Files:**
- Create: `database/migrations/2026_08_09_000005_create_class_announcements_table.php`

**Step 1: Create migration**

```bash
php artisan make:migration create_class_announcements_table --no-interaction
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
        Schema::create('class_announcements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignId('author_id')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->text('body');
            $table->boolean('is_pinned')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['class_id', 'is_pinned', 'published_at']);
        });

        Schema::create('class_announcement_reads', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('announcement_id')->constrained('class_announcements')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->timestamp('read_at');
            $table->timestamps();

            $table->unique(['announcement_id', 'student_id'], 'car_announcement_student_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_announcement_reads');
        Schema::dropIfExists('class_announcements');
    }
};
```

**Step 3: Run migration**

```bash
php artisan migrate
```

**Step 4: Commit**

```bash
git add -A && git commit -m "feat(lms): create class_announcements and reads tables"
```

---

## Task 6: Models — ClassResource + ClassResourceView

**Files:**
- Create: `app/Models/ClassResource.php`
- Create: `app/Models/ClassResourceView.php`
- Create: `database/factories/ClassResourceFactory.php`

**Step 1: Create model with factory**

```bash
php artisan make:model ClassResource --factory --no-interaction
php artisan make:model ClassResourceView --no-interaction
```

**Step 2: Write ClassResource model**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class ClassResource extends Model
{
    use HasFactory;

    protected $fillable = [
        'class_id',
        'session_id',
        'uploaded_by',
        'title',
        'type',
        'file_path',
        'url',
        'content',
        'sort_order',
        'is_published',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'published_at' => 'datetime',
            'sort_order' => 'integer',
        ];
    }

    public function class(): BelongsTo
    {
        return $this->belongsTo(ClassModel::class);
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(ClassSession::class, 'session_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function views(): HasMany
    {
        return $this->hasMany(ClassResourceView::class, 'class_resource_id');
    }

    public function scopePublished($query)
    {
        return $query->where('is_published', true)
            ->where(function ($q) {
                $q->whereNull('published_at')
                    ->orWhere('published_at', '<=', now());
            });
    }

    public function scopeForClass($query, int $classId)
    {
        return $query->where('class_id', $classId);
    }

    public function isAvailable(): bool
    {
        return $this->is_published
            && (blank($this->published_at) || $this->published_at->isPast());
    }

    public function getFileUrlAttribute(): ?string
    {
        return $this->file_path ? Storage::disk('public')->url($this->file_path) : null;
    }

    public function getAccessibleUrlAttribute(): ?string
    {
        return $this->file_url ?? $this->url;
    }

    public function recordView(Student $student): ClassResourceView
    {
        return ClassResourceView::updateOrCreate(
            [
                'class_resource_id' => $this->id,
                'student_id' => $student->id,
            ],
            [
                'last_viewed_at' => now(),
                'first_viewed_at' => now(),
            ]
        );
    }

    public function getViewCountAttribute(): int
    {
        return $this->views()->count();
    }

    public function getIconAttribute(): string
    {
        return match ($this->type) {
            'recording' => 'video-camera',
            'pdf' => 'document-text',
            'audio' => 'musical-note',
            'image' => 'photo',
            'link' => 'link',
            'note' => 'pencil-square',
            default => 'document',
        };
    }
}
```

**Step 3: Write ClassResourceView model**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClassResourceView extends Model
{
    protected $fillable = [
        'class_resource_id',
        'student_id',
        'first_viewed_at',
        'last_viewed_at',
        'view_count',
    ];

    protected function casts(): array
    {
        return [
            'first_viewed_at' => 'datetime',
            'last_viewed_at' => 'datetime',
            'view_count' => 'integer',
        ];
    }

    public function resource(): BelongsTo
    {
        return $this->belongsTo(ClassResource::class, 'class_resource_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
```

**Step 4: Write ClassResourceFactory**

```php
<?php

namespace Database\Factories;

use App\Models\ClassModel;
use App\Models\ClassSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ClassResourceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'class_id' => ClassModel::factory(),
            'session_id' => null,
            'uploaded_by' => User::factory(),
            'title' => fake()->sentence(3),
            'type' => fake()->randomElement(['recording', 'pdf', 'audio', 'link', 'note']),
            'file_path' => null,
            'url' => fake()->url(),
            'content' => null,
            'sort_order' => 0,
            'is_published' => true,
            'published_at' => now(),
        ];
    }

    public function unpublished(): static
    {
        return $this->state(fn () => [
            'is_published' => false,
            'published_at' => null,
        ]);
    }

    public function scheduledRelease(): static
    {
        return $this->state(fn () => [
            'is_published' => true,
            'published_at' => now()->addDay(),
        ]);
    }

    public function note(): static
    {
        return $this->state(fn () => [
            'type' => 'note',
            'url' => null,
            'content' => fake()->paragraphs(3, true),
        ]);
    }

    public function recording(): static
    {
        return $this->state(fn () => [
            'type' => 'recording',
            'url' => 'https://youtube.com/watch?v=' . fake()->regexify('[A-Za-z0-9]{11}'),
        ]);
    }
}
```

**Step 5: Add relationships to ClassModel**

In `app/Models/ClassModel.php`, add:

```php
public function resources(): HasMany
{
    return $this->hasMany(ClassResource::class, 'class_id');
}

public function publishedResources(): HasMany
{
    return $this->hasMany(ClassResource::class, 'class_id')->published();
}

public function announcements(): HasMany
{
    return $this->hasMany(ClassAnnouncement::class, 'class_id');
}
```

**Step 6: Commit**

```bash
git add -A && git commit -m "feat(lms): add ClassResource and ClassResourceView models with factory"
```

---

## Task 7: Models — StudentMilestone + ClassAnnouncement + ClassAnnouncementRead

**Files:**
- Create: `app/Models/StudentMilestone.php`
- Create: `app/Models/ClassAnnouncement.php`
- Create: `app/Models/ClassAnnouncementRead.php`
- Create: `database/factories/ClassAnnouncementFactory.php`
- Create: `database/factories/StudentMilestoneFactory.php`

**Step 1: Create models**

```bash
php artisan make:model StudentMilestone --factory --no-interaction
php artisan make:model ClassAnnouncement --factory --no-interaction
php artisan make:model ClassAnnouncementRead --no-interaction
```

**Step 2: Write StudentMilestone model**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentMilestone extends Model
{
    use HasFactory;

    protected $fillable = [
        'class_student_id',
        'title',
        'achieved_at',
        'awarded_by',
        'type',
    ];

    protected function casts(): array
    {
        return [
            'achieved_at' => 'datetime',
        ];
    }

    public function classStudent(): BelongsTo
    {
        return $this->belongsTo(ClassStudent::class);
    }

    public function awardedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'awarded_by');
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }
}
```

**Step 3: Write ClassAnnouncement model**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClassAnnouncement extends Model
{
    use HasFactory;

    protected $fillable = [
        'class_id',
        'author_id',
        'title',
        'body',
        'is_pinned',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'is_pinned' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    public function class(): BelongsTo
    {
        return $this->belongsTo(ClassModel::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function reads(): HasMany
    {
        return $this->hasMany(ClassAnnouncementRead::class, 'announcement_id');
    }

    public function scopePublished($query)
    {
        return $query->where('published_at', '<=', now());
    }

    public function scopeOrdered($query)
    {
        return $query->orderByDesc('is_pinned')->orderByDesc('published_at');
    }

    public function isReadBy(Student $student): bool
    {
        return $this->reads()->where('student_id', $student->id)->exists();
    }

    public function markAsRead(Student $student): ClassAnnouncementRead
    {
        return ClassAnnouncementRead::firstOrCreate(
            [
                'announcement_id' => $this->id,
                'student_id' => $student->id,
            ],
            ['read_at' => now()]
        );
    }

    public function getReadCountAttribute(): int
    {
        return $this->reads()->count();
    }
}
```

**Step 4: Write ClassAnnouncementRead model**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClassAnnouncementRead extends Model
{
    protected $fillable = [
        'announcement_id',
        'student_id',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
        ];
    }

    public function announcement(): BelongsTo
    {
        return $this->belongsTo(ClassAnnouncement::class, 'announcement_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
```

**Step 5: Write factories**

ClassAnnouncementFactory:

```php
<?php

namespace Database\Factories;

use App\Models\ClassModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ClassAnnouncementFactory extends Factory
{
    public function definition(): array
    {
        return [
            'class_id' => ClassModel::factory(),
            'author_id' => User::factory(),
            'title' => fake()->sentence(),
            'body' => fake()->paragraphs(2, true),
            'is_pinned' => false,
            'published_at' => now(),
        ];
    }

    public function pinned(): static
    {
        return $this->state(fn () => ['is_pinned' => true]);
    }
}
```

StudentMilestoneFactory:

```php
<?php

namespace Database\Factories;

use App\Models\ClassStudent;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class StudentMilestoneFactory extends Factory
{
    public function definition(): array
    {
        return [
            'class_student_id' => ClassStudent::factory(),
            'title' => fake()->sentence(3),
            'achieved_at' => now(),
            'awarded_by' => User::factory(),
            'type' => fake()->randomElement(['attendance', 'syllabus', 'custom']),
        ];
    }
}
```

**Step 6: Add relationships to ClassStudent**

In `app/Models/ClassStudent.php`, add:

```php
public function milestones(): HasMany
{
    return $this->hasMany(StudentMilestone::class);
}
```

**Step 7: Commit**

```bash
git add -A && git commit -m "feat(lms): add StudentMilestone, ClassAnnouncement, ClassAnnouncementRead models"
```

---

## Task 8: Tests — ClassResource model tests

**Files:**
- Create: `tests/Feature/Lms/ClassResourceTest.php`

**Step 1: Create test file**

```bash
php artisan make:test Lms/ClassResourceTest --pest --no-interaction
```

**Step 2: Write tests**

```php
<?php

declare(strict_types=1);

use App\Models\ClassModel;
use App\Models\ClassResource;
use App\Models\ClassResourceView;
use App\Models\ClassSession;
use App\Models\Student;
use App\Models\User;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

test('class resource belongs to a class', function () {
    $resource = ClassResource::factory()->create();

    expect($resource->class)->toBeInstanceOf(ClassModel::class);
});

test('class resource can be attached to a session', function () {
    $session = ClassSession::factory()->create();
    $resource = ClassResource::factory()->create(['session_id' => $session->id]);

    expect($resource->session)->toBeInstanceOf(ClassSession::class);
    expect($resource->session->id)->toBe($session->id);
});

test('published scope excludes unpublished and future-scheduled resources', function () {
    $class = ClassModel::factory()->create();

    $published = ClassResource::factory()->create(['class_id' => $class->id, 'is_published' => true, 'published_at' => now()->subHour()]);
    $unpublished = ClassResource::factory()->create(['class_id' => $class->id, 'is_published' => false]);
    $future = ClassResource::factory()->scheduledRelease()->create(['class_id' => $class->id]);

    $results = ClassResource::published()->pluck('id');

    expect($results)->toContain($published->id);
    expect($results)->not->toContain($unpublished->id);
    expect($results)->not->toContain($future->id);
});

test('record view creates or updates view tracking', function () {
    $resource = ClassResource::factory()->create();
    $student = Student::factory()->create();

    $view = $resource->recordView($student);

    expect($view)->toBeInstanceOf(ClassResourceView::class);
    expect($view->student_id)->toBe($student->id);
    expect($view->class_resource_id)->toBe($resource->id);

    // Second view updates last_viewed_at
    $resource->recordView($student);
    expect(ClassResourceView::where('class_resource_id', $resource->id)->where('student_id', $student->id)->count())->toBe(1);
});

test('icon attribute returns correct icon per type', function () {
    $resource = ClassResource::factory()->create(['type' => 'recording']);
    expect($resource->icon)->toBe('video-camera');

    $resource->type = 'pdf';
    expect($resource->icon)->toBe('document-text');

    $resource->type = 'note';
    expect($resource->icon)->toBe('pencil-square');
});
```

**Step 3: Run tests**

```bash
php artisan test --compact tests/Feature/Lms/ClassResourceTest.php
```

**Step 4: Commit**

```bash
git add -A && git commit -m "test(lms): add ClassResource model tests"
```

---

## Task 9: Tests — ClassAnnouncement model tests

**Files:**
- Create: `tests/Feature/Lms/ClassAnnouncementTest.php`

**Step 1: Create test file**

```bash
php artisan make:test Lms/ClassAnnouncementTest --pest --no-interaction
```

**Step 2: Write tests**

```php
<?php

declare(strict_types=1);

use App\Models\ClassAnnouncement;
use App\Models\ClassAnnouncementRead;
use App\Models\ClassModel;
use App\Models\Student;
use App\Models\User;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

test('announcement belongs to a class', function () {
    $announcement = ClassAnnouncement::factory()->create();

    expect($announcement->class)->toBeInstanceOf(ClassModel::class);
});

test('published scope excludes future announcements', function () {
    $class = ClassModel::factory()->create();

    $past = ClassAnnouncement::factory()->create(['class_id' => $class->id, 'published_at' => now()->subHour()]);
    $future = ClassAnnouncement::factory()->create(['class_id' => $class->id, 'published_at' => now()->addHour()]);

    $results = ClassAnnouncement::published()->pluck('id');

    expect($results)->toContain($past->id);
    expect($results)->not->toContain($future->id);
});

test('ordered scope puts pinned first then by date desc', function () {
    $class = ClassModel::factory()->create();

    $old = ClassAnnouncement::factory()->create(['class_id' => $class->id, 'published_at' => now()->subDays(3)]);
    $new = ClassAnnouncement::factory()->create(['class_id' => $class->id, 'published_at' => now()]);
    $pinned = ClassAnnouncement::factory()->pinned()->create(['class_id' => $class->id, 'published_at' => now()->subWeek()]);

    $ordered = ClassAnnouncement::ordered()->pluck('id')->toArray();

    expect($ordered[0])->toBe($pinned->id);
});

test('mark as read creates read record and isReadBy returns true', function () {
    $announcement = ClassAnnouncement::factory()->create();
    $student = Student::factory()->create();

    expect($announcement->isReadBy($student))->toBeFalse();

    $announcement->markAsRead($student);

    expect($announcement->isReadBy($student))->toBeTrue();
    expect($announcement->read_count)->toBe(1);
});

test('mark as read is idempotent', function () {
    $announcement = ClassAnnouncement::factory()->create();
    $student = Student::factory()->create();

    $announcement->markAsRead($student);
    $announcement->markAsRead($student);

    expect(ClassAnnouncementRead::where('announcement_id', $announcement->id)->count())->toBe(1);
});
```

**Step 3: Run tests**

```bash
php artisan test --compact tests/Feature/Lms/ClassAnnouncementTest.php
```

**Step 4: Commit**

```bash
git add -A && git commit -m "test(lms): add ClassAnnouncement model tests"
```

---

## Task 10: Teacher — Content Library tab (Bahan)

**Files:**
- Modify: `resources/views/livewire/teacher/classes-show.blade.php` (add tab + methods)
- Create: `resources/views/livewire/teacher/classes-show/tab-resources.blade.php`

**Step 1: Add resource methods to teacher classes-show component**

In the PHP section of `resources/views/livewire/teacher/classes-show.blade.php`:

- Add `'resources'` to the `$validTabs` array in `mount()`
- Add these properties and methods:

```php
// Resource management
public string $resourceTitle = '';
public string $resourceType = 'link';
public ?string $resourceUrl = null;
public ?string $resourceContent = null;
public ?int $resourceSessionId = null;
public bool $resourcePublished = true;
public bool $showResourceModal = false;
public ?int $editingResourceId = null;

public function saveResource(): void
{
    $this->validate([
        'resourceTitle' => 'required|string|max:255',
        'resourceType' => 'required|in:recording,pdf,audio,image,link,note',
        'resourceUrl' => 'nullable|url|max:500',
        'resourceContent' => 'nullable|string',
    ]);

    $data = [
        'class_id' => $this->class->id,
        'uploaded_by' => auth()->id(),
        'title' => $this->resourceTitle,
        'type' => $this->resourceType,
        'url' => $this->resourceUrl,
        'content' => $this->resourceContent,
        'session_id' => $this->resourceSessionId,
        'is_published' => $this->resourcePublished,
        'published_at' => $this->resourcePublished ? now() : null,
    ];

    if ($this->editingResourceId) {
        ClassResource::findOrFail($this->editingResourceId)->update($data);
    } else {
        ClassResource::create($data);
    }

    $this->resetResourceForm();
    $this->showResourceModal = false;
}

public function editResource(int $id): void
{
    $resource = ClassResource::findOrFail($id);
    $this->editingResourceId = $resource->id;
    $this->resourceTitle = $resource->title;
    $this->resourceType = $resource->type;
    $this->resourceUrl = $resource->url;
    $this->resourceContent = $resource->content;
    $this->resourceSessionId = $resource->session_id;
    $this->resourcePublished = $resource->is_published;
    $this->showResourceModal = true;
}

public function deleteResource(int $id): void
{
    ClassResource::where('id', $id)->where('class_id', $this->class->id)->delete();
}

public function toggleResourcePublished(int $id): void
{
    $resource = ClassResource::findOrFail($id);
    $resource->update([
        'is_published' => !$resource->is_published,
        'published_at' => !$resource->is_published ? now() : null,
    ]);
}

private function resetResourceForm(): void
{
    $this->resourceTitle = '';
    $this->resourceType = 'link';
    $this->resourceUrl = null;
    $this->resourceContent = null;
    $this->resourceSessionId = null;
    $this->resourcePublished = true;
    $this->editingResourceId = null;
}
```

**Step 2: Add the tab button to the Blade template**

Find the tab navigation section in the Blade part of `classes-show.blade.php` and add a "Bahan" tab button alongside existing tabs (overview, sessions, students, timetable).

**Step 3: Create `tab-resources.blade.php`**

Create `resources/views/livewire/teacher/classes-show/tab-resources.blade.php` with:

- List of resources grouped by session (or "General" if no session)
- Each resource row shows: icon, title, type badge, published status toggle, view count, edit/delete buttons
- "Tambah Bahan" button opens the modal
- Modal with form: title, type dropdown, URL input (for link/recording), content textarea (for notes), session selector, published checkbox

Use Flux UI components: `<flux:card>`, `<flux:button>`, `<flux:modal>`, `<flux:input>`, `<flux:select>`, `<flux:textarea>`, `<flux:switch>`, `<flux:badge>`, `<flux:icon>`.

**Step 4: File upload support**

Add file upload using Livewire's `WithFileUploads` trait for PDF/audio/image types. Store files at `class-resources/{class_id}/`. Add `$resourceFile` property and handle in `saveResource()`.

**Step 5: Commit**

```bash
git add -A && git commit -m "feat(lms): add Content Library (Bahan) tab to teacher class-show"
```

---

## Task 11: Student — Content Library tab (Bahan)

**Files:**
- Modify: `resources/views/livewire/student/class-show.blade.php` (add tab)
- Create: `resources/views/livewire/student/class-show/resources.blade.php`

**Step 1: Add `'resources'` to valid tabs in student class-show mount()**

In `resources/views/livewire/student/class-show.blade.php`, add a `getPublishedResourcesProperty()` computed:

```php
public function getPublishedResourcesProperty()
{
    return ClassResource::where('class_id', $this->class->id)
        ->published()
        ->orderBy('sort_order')
        ->orderByDesc('published_at')
        ->get();
}

public function viewResource(int $id): void
{
    $resource = ClassResource::where('id', $id)
        ->where('class_id', $this->class->id)
        ->published()
        ->firstOrFail();

    $student = auth()->user()->student;
    $resource->recordView($student);

    if ($resource->accessible_url) {
        $this->dispatch('open-url', url: $resource->accessible_url);
    }
}
```

**Step 2: Add tab button to Blade template**

Add "Bahan" tab alongside existing overview, timetable, sessions tabs.

**Step 3: Create `resources.blade.php` tab**

Create `resources/views/livewire/student/class-show/resources.blade.php`:

- List resources grouped by session or "Umum"
- Each resource card: icon, title, type badge, "Lihat" button
- Recording type: embedded YouTube/video player
- Note type: rendered markdown content inline
- Link/PDF: opens in new tab

**Step 4: Commit**

```bash
git add -A && git commit -m "feat(lms): add Content Library (Bahan) tab to student class-show"
```

---

## Task 12: Student — Progress Dashboard tab (Kemajuan)

**Files:**
- Modify: `resources/views/livewire/student/class-show.blade.php` (add progress methods)
- Create: `resources/views/livewire/student/class-show/progress.blade.php`

**Step 1: Add progress computed properties to student class-show**

```php
use App\Models\ClassAttendance;
use App\Models\ClassSyllabus;
use App\Models\StudentMilestone;

public function getProgressDataProperty(): array
{
    $student = auth()->user()->student;
    $classStudent = ClassStudent::where('class_id', $this->class->id)
        ->where('student_id', $student->id)
        ->first();

    $completedSessions = $this->class->sessions()->where('status', 'completed')->count();
    $attendedSessions = ClassAttendance::where('student_id', $student->id)
        ->whereHas('session', fn ($q) => $q->where('class_id', $this->class->id))
        ->where('status', 'present')
        ->count();

    $totalSyllabus = $this->class->syllabi()->count();
    $coveredSyllabus = $this->class->syllabi()
        ->whereNotNull('covered_at')
        ->count();

    // Calculate streak
    $streak = $this->calculateStreak($student);

    $milestones = $classStudent
        ? StudentMilestone::where('class_student_id', $classStudent->id)->orderByDesc('achieved_at')->get()
        : collect();

    $nextSession = $this->class->sessions()
        ->where('status', 'scheduled')
        ->where('session_date', '>=', now()->toDateString())
        ->orderBy('session_date')
        ->orderBy('session_time')
        ->first();

    return [
        'attendance_rate' => $completedSessions > 0 ? round(($attendedSessions / $completedSessions) * 100) : 0,
        'attended' => $attendedSessions,
        'total_completed' => $completedSessions,
        'streak' => $streak,
        'syllabus_covered' => $coveredSyllabus,
        'syllabus_total' => $totalSyllabus,
        'milestones' => $milestones,
        'next_session' => $nextSession,
    ];
}

private function calculateStreak(Student $student): int
{
    $attendances = ClassAttendance::where('student_id', $student->id)
        ->whereHas('session', fn ($q) => $q->where('class_id', $this->class->id)->where('status', 'completed'))
        ->join('class_sessions', 'class_attendances.session_id', '=', 'class_sessions.id')
        ->orderByDesc('class_sessions.session_date')
        ->pluck('class_attendances.status');

    $streak = 0;
    foreach ($attendances as $status) {
        if ($status === 'present') {
            $streak++;
        } else {
            break;
        }
    }

    return $streak;
}
```

**Step 2: Add `'progress'` to valid tabs**

**Step 3: Create `progress.blade.php` tab**

Layout:
- **Attendance ring** — circular progress (use Tailwind CSS `conic-gradient` or SVG circle)
- **Streak counter** — flame icon + "X sesi berturut-turut"
- **Sessions timeline** — vertical list of past sessions (green check = attended, red x = absent, grey = upcoming)
- **Syllabus progress bar** — horizontal bar showing covered/total
- **Milestones** — badge cards with title + achieved date
- **Next session** — countdown card

Use Flux UI: `<flux:card>`, `<flux:heading>`, `<flux:text>`, `<flux:badge>`, `<flux:icon>`.

**Step 4: Commit**

```bash
git add -A && git commit -m "feat(lms): add Student Progress (Kemajuan) tab"
```

---

## Task 13: Teacher — Progress view per student

**Files:**
- Modify: `resources/views/livewire/teacher/classes-show.blade.php` (add milestone methods)
- Modify: `resources/views/livewire/teacher/classes-show/tab-students.blade.php` (add progress column + milestone award)

**Step 1: Add milestone methods to teacher classes-show**

```php
public string $milestoneTitle = '';
public string $milestoneType = 'custom';
public ?int $milestoneStudentId = null;
public bool $showMilestoneModal = false;

public function awardMilestone(): void
{
    $this->validate([
        'milestoneTitle' => 'required|string|max:255',
        'milestoneType' => 'required|in:attendance,syllabus,custom',
        'milestoneStudentId' => 'required|exists:class_students,id',
    ]);

    StudentMilestone::create([
        'class_student_id' => $this->milestoneStudentId,
        'title' => $this->milestoneTitle,
        'type' => $this->milestoneType,
        'achieved_at' => now(),
        'awarded_by' => auth()->id(),
    ]);

    $this->milestoneTitle = '';
    $this->showMilestoneModal = false;
}
```

**Step 2: Update tab-students to show per-student progress**

In `tab-students.blade.php`, for each student row add:
- Attendance percentage column
- Streak count
- Milestone count with "Beri Pencapaian" button that opens the modal

**Step 3: Commit**

```bash
git add -A && git commit -m "feat(lms): add per-student progress view and milestone award to teacher"
```

---

## Task 14: Teacher — Announcements tab (Pengumuman)

**Files:**
- Modify: `resources/views/livewire/teacher/classes-show.blade.php` (add announcement methods)
- Create: `resources/views/livewire/teacher/classes-show/tab-announcements.blade.php`

**Step 1: Add announcement methods to teacher classes-show**

```php
public string $announcementTitle = '';
public string $announcementBody = '';
public bool $announcementPinned = false;
public bool $showAnnouncementModal = false;
public ?int $editingAnnouncementId = null;

public function saveAnnouncement(): void
{
    $this->validate([
        'announcementTitle' => 'required|string|max:255',
        'announcementBody' => 'required|string',
    ]);

    $data = [
        'class_id' => $this->class->id,
        'author_id' => auth()->id(),
        'title' => $this->announcementTitle,
        'body' => $this->announcementBody,
        'is_pinned' => $this->announcementPinned,
        'published_at' => now(),
    ];

    if ($this->editingAnnouncementId) {
        ClassAnnouncement::findOrFail($this->editingAnnouncementId)->update($data);
    } else {
        ClassAnnouncement::create($data);
    }

    $this->resetAnnouncementForm();
    $this->showAnnouncementModal = false;
}

public function editAnnouncement(int $id): void
{
    $a = ClassAnnouncement::findOrFail($id);
    $this->editingAnnouncementId = $a->id;
    $this->announcementTitle = $a->title;
    $this->announcementBody = $a->body;
    $this->announcementPinned = $a->is_pinned;
    $this->showAnnouncementModal = true;
}

public function deleteAnnouncement(int $id): void
{
    ClassAnnouncement::where('id', $id)->where('class_id', $this->class->id)->delete();
}

public function togglePin(int $id): void
{
    $a = ClassAnnouncement::findOrFail($id);
    $a->update(['is_pinned' => !$a->is_pinned]);
}

private function resetAnnouncementForm(): void
{
    $this->announcementTitle = '';
    $this->announcementBody = '';
    $this->announcementPinned = false;
    $this->editingAnnouncementId = null;
}
```

**Step 2: Add `'announcements'` to $validTabs, add tab button**

**Step 3: Create `tab-announcements.blade.php`**

- List announcements ordered (pinned first, then by date)
- Each card: pin icon, title, body preview (first 100 chars), author name, date, read count / total students
- "Tulis Pengumuman" button opens modal
- Modal: title input, body textarea (markdown), pinned checkbox
- Read receipt view: click "X/Y dibaca" to see who read it

**Step 4: Commit**

```bash
git add -A && git commit -m "feat(lms): add Announcements (Pengumuman) tab to teacher class-show"
```

---

## Task 15: Student — Announcements tab (Pengumuman)

**Files:**
- Modify: `resources/views/livewire/student/class-show.blade.php` (add announcement methods)
- Create: `resources/views/livewire/student/class-show/announcements.blade.php`

**Step 1: Add methods to student class-show**

```php
public function getAnnouncementsProperty()
{
    return ClassAnnouncement::where('class_id', $this->class->id)
        ->published()
        ->ordered()
        ->get();
}

public function getUnreadCountProperty(): int
{
    $student = auth()->user()->student;
    $total = ClassAnnouncement::where('class_id', $this->class->id)->published()->count();
    $read = ClassAnnouncementRead::whereHas('announcement', fn ($q) => $q->where('class_id', $this->class->id))
        ->where('student_id', $student->id)
        ->count();

    return max(0, $total - $read);
}

public function markAnnouncementRead(int $id): void
{
    $announcement = ClassAnnouncement::findOrFail($id);
    $student = auth()->user()->student;
    $announcement->markAsRead($student);
}
```

**Step 2: Add `'announcements'` to valid tabs, show unread badge on tab button**

```blade
<button wire:click="setActiveTab('announcements')">
    Pengumuman
    @if($this->unread_count > 0)
        <flux:badge color="red" size="sm">{{ $this->unread_count }}</flux:badge>
    @endif
</button>
```

**Step 3: Create `announcements.blade.php` tab**

- Feed of announcements (pinned at top with pin icon)
- Each card: title, body (full), author, date
- Auto-mark as read when viewed (call `markAnnouncementRead` via `wire:init` or intersection observer via Alpine)
- Unread cards have a subtle left border highlight

**Step 4: Commit**

```bash
git add -A && git commit -m "feat(lms): add Announcements (Pengumuman) tab to student class-show"
```

---

## Task 16: Student Dashboard — Unread announcements widget

**Files:**
- Modify: `resources/views/livewire/student/dashboard.blade.php`

**Step 1: Add unread announcements query to dashboard**

Query all classes the student is in, count unread announcements across all classes, show a card:

```
"3 pengumuman baru" — with links to each class
```

**Step 2: Commit**

```bash
git add -A && git commit -m "feat(lms): add unread announcements widget to student dashboard"
```

---

## Task 17: Storefront — Class-centric course page revamp

**Files:**
- Modify: `resources/views/store/course.blade.php`
- Modify: `app/Http/Controllers/StorefrontController.php` (eager-load class data)

**Step 1: Update StorefrontController to eager-load class storefront data**

In the `course()` or `showCourse()` method, add eager loading:

```php
$course->load([
    'classes' => function ($q) {
        $q->where('show_on_storefront', true)
          ->where('status', 'active')
          ->with(['teacher.user', 'timetable', 'syllabi', 'activeStudents']);
    },
]);
```

**Step 2: Enhance class cards in `course.blade.php`**

Update the `@foreach($course->classes as $class)` section to show richer cards:

- Teacher avatar + name
- Timetable schedule (formatted days + times)
- Class type badge (Individual/Group)
- Capacity bar ("12/20 pelajar" with progress indicator)
- Storefront description (if set)
- First 3 syllabus topics
- Fee (from course fee settings)
- "Daftar" CTA button

**Step 3: Add admin toggle for `show_on_storefront` per class**

In admin `class-edit.blade.php`, add a Flux switch:

```blade
<flux:switch wire:model="showOnStorefront" label="Papar di Storefront" />
<flux:textarea wire:model="storefrontDescription" label="Penerangan Storefront" />
```

**Step 4: Commit**

```bash
git add -A && git commit -m "feat(lms): revamp storefront course page with class-centric cards"
```

---

## Task 18: Feature tests — Content Library + Announcements Volt components

**Files:**
- Create: `tests/Feature/Lms/TeacherResourceTabTest.php`
- Create: `tests/Feature/Lms/StudentAnnouncementTabTest.php`

**Step 1: Write teacher resource tab test**

```php
<?php

declare(strict_types=1);

use App\Models\ClassModel;
use App\Models\ClassResource;
use App\Models\Teacher;
use App\Models\User;
use Livewire\Volt\Volt;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

test('teacher can create a resource in their class', function () {
    $teacher = Teacher::factory()->create();
    $user = $teacher->user;
    $user->assignRole('teacher');
    $class = ClassModel::factory()->create(['teacher_id' => $teacher->id, 'status' => 'active']);

    Volt::actingAs($user)
        ->test('teacher.classes-show', ['class' => $class])
        ->set('resourceTitle', 'Nota Tajwid Bab 1')
        ->set('resourceType', 'link')
        ->set('resourceUrl', 'https://example.com/nota')
        ->set('resourcePublished', true)
        ->call('saveResource')
        ->assertHasNoErrors();

    expect(ClassResource::where('class_id', $class->id)->where('title', 'Nota Tajwid Bab 1')->exists())->toBeTrue();
});

test('teacher can delete a resource', function () {
    $teacher = Teacher::factory()->create();
    $user = $teacher->user;
    $user->assignRole('teacher');
    $class = ClassModel::factory()->create(['teacher_id' => $teacher->id, 'status' => 'active']);
    $resource = ClassResource::factory()->create(['class_id' => $class->id, 'uploaded_by' => $user->id]);

    Volt::actingAs($user)
        ->test('teacher.classes-show', ['class' => $class])
        ->call('deleteResource', $resource->id);

    expect(ClassResource::find($resource->id))->toBeNull();
});
```

**Step 2: Write student announcement tab test**

```php
<?php

declare(strict_types=1);

use App\Models\ClassAnnouncement;
use App\Models\ClassModel;
use App\Models\ClassStudent;
use App\Models\Student;
use App\Models\User;
use Livewire\Volt\Volt;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

test('student can see published announcements for their class', function () {
    $student = Student::factory()->create();
    $user = $student->user;
    $user->assignRole('student');
    $class = ClassModel::factory()->create(['status' => 'active']);
    ClassStudent::create(['class_id' => $class->id, 'student_id' => $student->id, 'enrolled_at' => now(), 'status' => 'active']);

    $announcement = ClassAnnouncement::factory()->create(['class_id' => $class->id, 'title' => 'Peringatan ujian']);

    Volt::actingAs($user)
        ->test('student.class-show', ['class' => $class])
        ->set('activeTab', 'announcements')
        ->assertSee('Peringatan ujian');
});

test('student marking announcement as read reduces unread count', function () {
    $student = Student::factory()->create();
    $user = $student->user;
    $user->assignRole('student');
    $class = ClassModel::factory()->create(['status' => 'active']);
    ClassStudent::create(['class_id' => $class->id, 'student_id' => $student->id, 'enrolled_at' => now(), 'status' => 'active']);

    $announcement = ClassAnnouncement::factory()->create(['class_id' => $class->id]);

    $component = Volt::actingAs($user)
        ->test('student.class-show', ['class' => $class]);

    expect($component->get('unread_count'))->toBe(1);

    $component->call('markAnnouncementRead', $announcement->id);

    expect($component->get('unread_count'))->toBe(0);
});
```

**Step 3: Run all LMS tests**

```bash
php artisan test --compact tests/Feature/Lms/
```

**Step 4: Commit**

```bash
git add -A && git commit -m "test(lms): add feature tests for teacher resources and student announcements"
```

---

## Task 19: Run Pint + full test suite

**Step 1: Run Pint**

```bash
./vendor/bin/pint --dirty
```

**Step 2: Run full test suite**

```bash
php artisan test --compact
```

Fix any failures. Ignore pre-existing failures documented in memory (HR ~21, LiveHost 2, POS ~13, Shop 3).

**Step 3: Commit any formatting fixes**

```bash
git add -A && git commit -m "chore: apply pint formatting to LMS modules"
```

---

## Summary

| Task | What | Type |
|------|------|------|
| 1 | Add storefront fields to classes | Migration |
| 2 | Create class_resources table | Migration |
| 3 | Create class_resource_views table | Migration |
| 4 | Create student_milestones table | Migration |
| 5 | Create class_announcements + reads tables | Migration |
| 6 | ClassResource + ClassResourceView models + factory | Model |
| 7 | StudentMilestone + ClassAnnouncement + ClassAnnouncementRead models + factories | Model |
| 8 | ClassResource model tests | Test |
| 9 | ClassAnnouncement model tests | Test |
| 10 | Teacher Content Library tab (Bahan) | UI |
| 11 | Student Content Library tab (Bahan) | UI |
| 12 | Student Progress Dashboard tab (Kemajuan) | UI |
| 13 | Teacher per-student progress view + milestone award | UI |
| 14 | Teacher Announcements tab (Pengumuman) | UI |
| 15 | Student Announcements tab (Pengumuman) | UI |
| 16 | Student Dashboard unread announcements widget | UI |
| 17 | Storefront class-centric course page revamp | UI |
| 18 | Feature tests for Volt components | Test |
| 19 | Pint formatting + full test suite | QA |
