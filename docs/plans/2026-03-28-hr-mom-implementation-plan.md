# MOM (Minutes of Meeting) Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Build a Minutes of Meeting module inside the HR system with attendance tracking, decision logging, shared polymorphic task system, meeting series, AI transcription/analysis, and notifications.

**Architecture:** React 19 SPA frontend + Laravel API backend. Shared polymorphic `tasks` table for cross-module reuse. Google Cloud Speech-to-Text for transcription, Google Gemini for AI analysis. Follows existing HR module patterns (controllers, form requests, models, React Query).

**Tech Stack:** Laravel 12, React 19, TanStack Query, Shadcn/ui, Tailwind CSS v4, Zustand, Google Cloud Speech-to-Text, Google Gemini API, Laravel Notifications (database + mail + webpush)

**Design Doc:** `docs/plans/2026-03-28-hr-mom-module-design.md`

---

## Phase 1: Database & Models (Foundation)

### Task 1: Create Meeting Series Migration & Model

**Files:**
- Create: `database/migrations/2026_03_28_200001_create_meeting_series_table.php`
- Create: `app/Models/MeetingSeries.php`

**Step 1: Create migration**

```bash
php artisan make:migration create_meeting_series_table --no-interaction
```

Edit the migration:

```php
return new class extends Migration {
    public function up(): void
    {
        Schema::create('meeting_series', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meeting_series');
    }
};
```

**Step 2: Create model**

```bash
php artisan make:model MeetingSeries --no-interaction
```

Edit `app/Models/MeetingSeries.php`:

```php
<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MeetingSeries extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'description', 'created_by'];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function meetings(): HasMany
    {
        return $this->hasMany(Meeting::class);
    }
}
```

**Step 3: Run migration**

```bash
php artisan migrate
```

**Step 4: Commit**

```bash
git add database/migrations/*meeting_series* app/Models/MeetingSeries.php
git commit -m "feat(hr): add meeting_series table and model"
```

---

### Task 2: Create Meetings Migration & Model

**Files:**
- Create: `database/migrations/2026_03_28_200002_create_meetings_table.php`
- Create: `app/Models/Meeting.php`

**Step 1: Create migration**

```bash
php artisan make:migration create_meetings_table --no-interaction
```

Edit the migration:

```php
return new class extends Migration {
    public function up(): void
    {
        Schema::create('meetings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meeting_series_id')->nullable()->constrained('meeting_series')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('location')->nullable();
            $table->date('meeting_date');
            $table->time('start_time');
            $table->time('end_time')->nullable();
            $table->enum('status', ['draft', 'scheduled', 'in_progress', 'completed', 'cancelled'])->default('draft');
            $table->foreignId('organizer_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('note_taker_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meetings');
    }
};
```

**Step 2: Create model**

Edit `app/Models/Meeting.php`:

```php
<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Meeting extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'meeting_series_id', 'title', 'description', 'location',
        'meeting_date', 'start_time', 'end_time', 'status',
        'organizer_id', 'note_taker_id', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'meeting_date' => 'date',
        ];
    }

    public function series(): BelongsTo
    {
        return $this->belongsTo(MeetingSeries::class, 'meeting_series_id');
    }

    public function organizer(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'organizer_id');
    }

    public function noteTaker(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'note_taker_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function attendees(): HasMany
    {
        return $this->hasMany(MeetingAttendee::class);
    }

    public function agendaItems(): HasMany
    {
        return $this->hasMany(MeetingAgendaItem::class)->orderBy('sort_order');
    }

    public function decisions(): HasMany
    {
        return $this->hasMany(MeetingDecision::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(MeetingAttachment::class);
    }

    public function recordings(): HasMany
    {
        return $this->hasMany(MeetingRecording::class);
    }

    public function transcripts(): HasMany
    {
        return $this->hasMany(MeetingTranscript::class);
    }

    public function aiSummaries(): HasMany
    {
        return $this->hasMany(MeetingAiSummary::class);
    }

    public function tasks(): MorphMany
    {
        return $this->morphMany(Task::class, 'taskable');
    }
}
```

**Step 3: Run migration**

```bash
php artisan migrate
```

**Step 4: Commit**

```bash
git add database/migrations/*meetings_table* app/Models/Meeting.php
git commit -m "feat(hr): add meetings table and model with relationships"
```

---

### Task 3: Create Meeting Attendees Migration & Model

**Files:**
- Create: `database/migrations/2026_03_28_200003_create_meeting_attendees_table.php`
- Create: `app/Models/MeetingAttendee.php`

**Step 1: Create migration**

```php
Schema::create('meeting_attendees', function (Blueprint $table) {
    $table->id();
    $table->foreignId('meeting_id')->constrained('meetings')->cascadeOnDelete();
    $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
    $table->enum('role', ['organizer', 'note_taker', 'attendee'])->default('attendee');
    $table->enum('attendance_status', ['invited', 'attended', 'absent', 'excused'])->default('invited');
    $table->timestamps();

    $table->unique(['meeting_id', 'employee_id']);
});
```

**Step 2: Create model**

```php
<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeetingAttendee extends Model
{
    protected $fillable = ['meeting_id', 'employee_id', 'role', 'attendance_status'];

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
```

**Step 3: Run migration and commit**

```bash
php artisan migrate
git add database/migrations/*meeting_attendees* app/Models/MeetingAttendee.php
git commit -m "feat(hr): add meeting_attendees table and model"
```

---

### Task 4: Create Agenda Items, Decisions, Attachments Migrations & Models

**Files:**
- Create: `database/migrations/2026_03_28_200004_create_meeting_agenda_items_table.php`
- Create: `database/migrations/2026_03_28_200005_create_meeting_decisions_table.php`
- Create: `database/migrations/2026_03_28_200006_create_meeting_attachments_table.php`
- Create: `app/Models/MeetingAgendaItem.php`
- Create: `app/Models/MeetingDecision.php`
- Create: `app/Models/MeetingAttachment.php`

**Step 1: Agenda items migration**

```php
Schema::create('meeting_agenda_items', function (Blueprint $table) {
    $table->id();
    $table->foreignId('meeting_id')->constrained('meetings')->cascadeOnDelete();
    $table->string('title');
    $table->text('description')->nullable();
    $table->unsignedInteger('sort_order')->default(0);
    $table->timestamps();
});
```

**Step 2: Decisions migration**

```php
Schema::create('meeting_decisions', function (Blueprint $table) {
    $table->id();
    $table->foreignId('meeting_id')->constrained('meetings')->cascadeOnDelete();
    $table->foreignId('agenda_item_id')->nullable()->constrained('meeting_agenda_items')->nullOnDelete();
    $table->string('title');
    $table->text('description');
    $table->foreignId('decided_by')->constrained('employees')->cascadeOnDelete();
    $table->datetime('decided_at');
    $table->timestamps();
});
```

**Step 3: Attachments migration**

```php
Schema::create('meeting_attachments', function (Blueprint $table) {
    $table->id();
    $table->foreignId('meeting_id')->constrained('meetings')->cascadeOnDelete();
    $table->string('file_name');
    $table->string('file_path');
    $table->unsignedInteger('file_size');
    $table->string('file_type');
    $table->foreignId('uploaded_by')->constrained('employees')->cascadeOnDelete();
    $table->timestamps();
});
```

**Step 4: Create all three models**

`app/Models/MeetingAgendaItem.php`:
```php
<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MeetingAgendaItem extends Model
{
    protected $fillable = ['meeting_id', 'title', 'description', 'sort_order'];

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    public function decisions(): HasMany
    {
        return $this->hasMany(MeetingDecision::class, 'agenda_item_id');
    }
}
```

`app/Models/MeetingDecision.php`:
```php
<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeetingDecision extends Model
{
    protected $fillable = [
        'meeting_id', 'agenda_item_id', 'title', 'description',
        'decided_by', 'decided_at',
    ];

    protected function casts(): array
    {
        return ['decided_at' => 'datetime'];
    }

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    public function agendaItem(): BelongsTo
    {
        return $this->belongsTo(MeetingAgendaItem::class, 'agenda_item_id');
    }

    public function decidedByEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'decided_by');
    }
}
```

`app/Models/MeetingAttachment.php`:
```php
<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeetingAttachment extends Model
{
    protected $fillable = [
        'meeting_id', 'file_name', 'file_path', 'file_size',
        'file_type', 'uploaded_by',
    ];

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'uploaded_by');
    }
}
```

**Step 5: Run migrations and commit**

```bash
php artisan migrate
git add database/migrations/*meeting_agenda* database/migrations/*meeting_decisions* database/migrations/*meeting_attachments* app/Models/MeetingAgendaItem.php app/Models/MeetingDecision.php app/Models/MeetingAttachment.php
git commit -m "feat(hr): add agenda items, decisions, and attachments tables and models"
```

---

### Task 5: Create Shared Task System Migrations & Models

**Files:**
- Create: `database/migrations/2026_03_28_200007_create_tasks_table.php`
- Create: `database/migrations/2026_03_28_200008_create_task_comments_table.php`
- Create: `database/migrations/2026_03_28_200009_create_task_attachments_table.php`
- Create: `app/Models/Task.php`
- Create: `app/Models/TaskComment.php`
- Create: `app/Models/TaskAttachment.php`

**Step 1: Tasks migration**

```php
Schema::create('tasks', function (Blueprint $table) {
    $table->id();
    $table->morphs('taskable');
    $table->foreignId('parent_id')->nullable()->constrained('tasks')->cascadeOnDelete();
    $table->string('title');
    $table->text('description')->nullable();
    $table->foreignId('assigned_to')->constrained('employees')->cascadeOnDelete();
    $table->foreignId('assigned_by')->constrained('employees')->cascadeOnDelete();
    $table->enum('priority', ['low', 'medium', 'high', 'urgent'])->default('medium');
    $table->enum('status', ['pending', 'in_progress', 'completed', 'cancelled'])->default('pending');
    $table->date('deadline');
    $table->datetime('completed_at')->nullable();
    $table->timestamps();
    $table->softDeletes();
});
```

**Step 2: Task comments migration**

```php
Schema::create('task_comments', function (Blueprint $table) {
    $table->id();
    $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
    $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
    $table->text('content');
    $table->timestamps();
});
```

**Step 3: Task attachments migration**

```php
Schema::create('task_attachments', function (Blueprint $table) {
    $table->id();
    $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
    $table->string('file_name');
    $table->string('file_path');
    $table->unsignedInteger('file_size');
    $table->string('file_type');
    $table->foreignId('uploaded_by')->constrained('employees')->cascadeOnDelete();
    $table->timestamps();
});
```

**Step 4: Create models**

`app/Models/Task.php`:
```php
<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Task extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'taskable_type', 'taskable_id', 'parent_id', 'title', 'description',
        'assigned_to', 'assigned_by', 'priority', 'status', 'deadline', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'deadline' => 'date',
            'completed_at' => 'datetime',
        ];
    }

    public function taskable(): MorphTo
    {
        return $this->morphTo();
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'parent_id');
    }

    public function subtasks(): HasMany
    {
        return $this->hasMany(Task::class, 'parent_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'assigned_to');
    }

    public function assigner(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'assigned_by');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(TaskComment::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(TaskAttachment::class);
    }
}
```

`app/Models/TaskComment.php`:
```php
<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskComment extends Model
{
    protected $fillable = ['task_id', 'employee_id', 'content'];

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
```

`app/Models/TaskAttachment.php`:
```php
<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskAttachment extends Model
{
    protected $fillable = [
        'task_id', 'file_name', 'file_path', 'file_size',
        'file_type', 'uploaded_by',
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'uploaded_by');
    }
}
```

**Step 5: Run migrations and commit**

```bash
php artisan migrate
git add database/migrations/*tasks* database/migrations/*task_comments* database/migrations/*task_attachments* app/Models/Task.php app/Models/TaskComment.php app/Models/TaskAttachment.php
git commit -m "feat(hr): add shared polymorphic task system (tasks, comments, attachments)"
```

---

### Task 6: Create Recording & AI Tables Migrations & Models

**Files:**
- Create: `database/migrations/2026_03_28_200010_create_meeting_recordings_table.php`
- Create: `database/migrations/2026_03_28_200011_create_meeting_transcripts_table.php`
- Create: `database/migrations/2026_03_28_200012_create_meeting_ai_summaries_table.php`
- Create: `app/Models/MeetingRecording.php`
- Create: `app/Models/MeetingTranscript.php`
- Create: `app/Models/MeetingAiSummary.php`

**Step 1: Recordings migration**

```php
Schema::create('meeting_recordings', function (Blueprint $table) {
    $table->id();
    $table->foreignId('meeting_id')->constrained('meetings')->cascadeOnDelete();
    $table->string('file_name');
    $table->string('file_path');
    $table->unsignedBigInteger('file_size');
    $table->string('file_type');
    $table->unsignedInteger('duration_seconds')->nullable();
    $table->enum('source', ['browser_recording', 'uploaded'])->default('uploaded');
    $table->foreignId('uploaded_by')->constrained('employees')->cascadeOnDelete();
    $table->timestamps();
});
```

**Step 2: Transcripts migration**

```php
Schema::create('meeting_transcripts', function (Blueprint $table) {
    $table->id();
    $table->foreignId('meeting_id')->constrained('meetings')->cascadeOnDelete();
    $table->foreignId('recording_id')->constrained('meeting_recordings')->cascadeOnDelete();
    $table->longText('content');
    $table->string('language')->default('en');
    $table->enum('status', ['processing', 'completed', 'failed'])->default('processing');
    $table->datetime('processed_at')->nullable();
    $table->timestamps();
});
```

**Step 3: AI summaries migration**

```php
Schema::create('meeting_ai_summaries', function (Blueprint $table) {
    $table->id();
    $table->foreignId('meeting_id')->constrained('meetings')->cascadeOnDelete();
    $table->foreignId('transcript_id')->constrained('meeting_transcripts')->cascadeOnDelete();
    $table->text('summary');
    $table->json('key_points')->nullable();
    $table->json('suggested_tasks')->nullable();
    $table->enum('status', ['processing', 'completed', 'reviewed', 'failed'])->default('processing');
    $table->foreignId('reviewed_by')->nullable()->constrained('employees')->nullOnDelete();
    $table->datetime('reviewed_at')->nullable();
    $table->timestamps();
});
```

**Step 4: Create models**

`app/Models/MeetingRecording.php`:
```php
<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class MeetingRecording extends Model
{
    protected $fillable = [
        'meeting_id', 'file_name', 'file_path', 'file_size',
        'file_type', 'duration_seconds', 'source', 'uploaded_by',
    ];

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'uploaded_by');
    }

    public function transcript(): HasOne
    {
        return $this->hasOne(MeetingTranscript::class, 'recording_id');
    }
}
```

`app/Models/MeetingTranscript.php`:
```php
<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class MeetingTranscript extends Model
{
    protected $fillable = [
        'meeting_id', 'recording_id', 'content', 'language',
        'status', 'processed_at',
    ];

    protected function casts(): array
    {
        return ['processed_at' => 'datetime'];
    }

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    public function recording(): BelongsTo
    {
        return $this->belongsTo(MeetingRecording::class, 'recording_id');
    }

    public function aiSummary(): HasOne
    {
        return $this->hasOne(MeetingAiSummary::class, 'transcript_id');
    }
}
```

`app/Models/MeetingAiSummary.php`:
```php
<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeetingAiSummary extends Model
{
    protected $fillable = [
        'meeting_id', 'transcript_id', 'summary', 'key_points',
        'suggested_tasks', 'status', 'reviewed_by', 'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'key_points' => 'array',
            'suggested_tasks' => 'array',
            'reviewed_at' => 'datetime',
        ];
    }

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    public function transcript(): BelongsTo
    {
        return $this->belongsTo(MeetingTranscript::class, 'transcript_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'reviewed_by');
    }
}
```

**Step 5: Run migrations and commit**

```bash
php artisan migrate
git add database/migrations/*meeting_recordings* database/migrations/*meeting_transcripts* database/migrations/*meeting_ai_summaries* app/Models/MeetingRecording.php app/Models/MeetingTranscript.php app/Models/MeetingAiSummary.php
git commit -m "feat(hr): add recording, transcript, and AI summary tables and models"
```

---

## Phase 2: Backend API (Controllers, Form Requests, Routes)

### Task 7: Create Meeting Form Requests

**Files:**
- Create: `app/Http/Requests/Hr/StoreMeetingRequest.php`
- Create: `app/Http/Requests/Hr/UpdateMeetingRequest.php`
- Create: `app/Http/Requests/Hr/StoreMeetingSeriesRequest.php`

**Step 1: StoreMeetingRequest**

```bash
php artisan make:request Hr/StoreMeetingRequest --no-interaction
```

```php
<?php
namespace App\Http\Requests\Hr;

use Illuminate\Foundation\Http\FormRequest;

class StoreMeetingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'location' => ['nullable', 'string', 'max:255'],
            'meeting_date' => ['required', 'date', 'after_or_equal:today'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i', 'after:start_time'],
            'status' => ['nullable', 'in:draft,scheduled'],
            'meeting_series_id' => ['nullable', 'exists:meeting_series,id'],
            'note_taker_id' => ['nullable', 'exists:employees,id'],
            'attendee_ids' => ['nullable', 'array'],
            'attendee_ids.*' => ['exists:employees,id'],
            'agenda_items' => ['nullable', 'array'],
            'agenda_items.*.title' => ['required_with:agenda_items', 'string', 'max:255'],
            'agenda_items.*.description' => ['nullable', 'string'],
        ];
    }
}
```

**Step 2: UpdateMeetingRequest**

```bash
php artisan make:request Hr/UpdateMeetingRequest --no-interaction
```

```php
<?php
namespace App\Http\Requests\Hr;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMeetingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'location' => ['nullable', 'string', 'max:255'],
            'meeting_date' => ['sometimes', 'required', 'date'],
            'start_time' => ['sometimes', 'required', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i', 'after:start_time'],
            'status' => ['nullable', 'in:draft,scheduled,in_progress,completed,cancelled'],
            'meeting_series_id' => ['nullable', 'exists:meeting_series,id'],
            'note_taker_id' => ['nullable', 'exists:employees,id'],
        ];
    }
}
```

**Step 3: StoreMeetingSeriesRequest**

```bash
php artisan make:request Hr/StoreMeetingSeriesRequest --no-interaction
```

```php
<?php
namespace App\Http\Requests\Hr;

use Illuminate\Foundation\Http\FormRequest;

class StoreMeetingSeriesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ];
    }
}
```

**Step 4: Commit**

```bash
git add app/Http/Requests/Hr/StoreMeetingRequest.php app/Http/Requests/Hr/UpdateMeetingRequest.php app/Http/Requests/Hr/StoreMeetingSeriesRequest.php
git commit -m "feat(hr): add meeting and series form requests"
```

---

### Task 8: Create Task & Decision Form Requests

**Files:**
- Create: `app/Http/Requests/Hr/StoreTaskRequest.php`
- Create: `app/Http/Requests/Hr/UpdateTaskRequest.php`
- Create: `app/Http/Requests/Hr/StoreAgendaItemRequest.php`
- Create: `app/Http/Requests/Hr/StoreDecisionRequest.php`

**Step 1: StoreTaskRequest**

```php
<?php
namespace App\Http\Requests\Hr;

use Illuminate\Foundation\Http\FormRequest;

class StoreTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'assigned_to' => ['required', 'exists:employees,id'],
            'priority' => ['required', 'in:low,medium,high,urgent'],
            'deadline' => ['required', 'date', 'after_or_equal:today'],
        ];
    }
}
```

**Step 2: UpdateTaskRequest**

```php
<?php
namespace App\Http\Requests\Hr;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'assigned_to' => ['sometimes', 'required', 'exists:employees,id'],
            'priority' => ['sometimes', 'required', 'in:low,medium,high,urgent'],
            'status' => ['sometimes', 'required', 'in:pending,in_progress,completed,cancelled'],
            'deadline' => ['sometimes', 'required', 'date'],
        ];
    }
}
```

**Step 3: StoreAgendaItemRequest**

```php
<?php
namespace App\Http\Requests\Hr;

use Illuminate\Foundation\Http\FormRequest;

class StoreAgendaItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ];
    }
}
```

**Step 4: StoreDecisionRequest**

```php
<?php
namespace App\Http\Requests\Hr;

use Illuminate\Foundation\Http\FormRequest;

class StoreDecisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'decided_by' => ['required', 'exists:employees,id'],
            'agenda_item_id' => ['nullable', 'exists:meeting_agenda_items,id'],
        ];
    }
}
```

**Step 5: Commit**

```bash
git add app/Http/Requests/Hr/StoreTaskRequest.php app/Http/Requests/Hr/UpdateTaskRequest.php app/Http/Requests/Hr/StoreAgendaItemRequest.php app/Http/Requests/Hr/StoreDecisionRequest.php
git commit -m "feat(hr): add task, agenda item, and decision form requests"
```

---

### Task 9: Create HrMeetingController

**Files:**
- Create: `app/Http/Controllers/Api/Hr/HrMeetingController.php`

**Step 1: Create controller**

```php
<?php
namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\StoreMeetingRequest;
use App\Http\Requests\Hr\UpdateMeetingRequest;
use App\Models\Meeting;
use App\Models\MeetingAttendee;
use App\Notifications\Hr\MeetingInvitationNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HrMeetingController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Meeting::query()
            ->with(['organizer:id,full_name', 'noteTaker:id,full_name', 'series:id,name'])
            ->withCount(['attendees', 'tasks', 'decisions']);

        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        if ($seriesId = $request->get('meeting_series_id')) {
            $query->where('meeting_series_id', $seriesId);
        }

        if ($request->get('tab') === 'upcoming') {
            $query->where('meeting_date', '>=', now()->toDateString())
                ->whereIn('status', ['draft', 'scheduled']);
        } elseif ($request->get('tab') === 'past') {
            $query->where(function ($q) {
                $q->where('meeting_date', '<', now()->toDateString())
                    ->orWhere('status', 'completed');
            });
        }

        $sortBy = $request->get('sort_by', 'meeting_date');
        $sortDir = $request->get('sort_dir', 'desc');
        $query->orderBy($sortBy, $sortDir);

        $meetings = $query->paginate($request->get('per_page', 15));

        return response()->json($meetings);
    }

    public function store(StoreMeetingRequest $request): JsonResponse
    {
        $employee = $request->user()->employee;

        $meeting = Meeting::create([
            ...$request->safe()->except(['attendee_ids', 'agenda_items']),
            'organizer_id' => $employee->id,
            'created_by' => $request->user()->id,
            'status' => $request->get('status', 'draft'),
        ]);

        // Add organizer as attendee
        $meeting->attendees()->create([
            'employee_id' => $employee->id,
            'role' => 'organizer',
            'attendance_status' => 'invited',
        ]);

        // Add note taker as attendee
        if ($request->note_taker_id && $request->note_taker_id !== $employee->id) {
            $meeting->attendees()->create([
                'employee_id' => $request->note_taker_id,
                'role' => 'note_taker',
                'attendance_status' => 'invited',
            ]);
        }

        // Add attendees
        if ($request->attendee_ids) {
            foreach ($request->attendee_ids as $attendeeId) {
                if ($attendeeId == $employee->id || $attendeeId == $request->note_taker_id) {
                    continue;
                }
                $meeting->attendees()->create([
                    'employee_id' => $attendeeId,
                    'role' => 'attendee',
                    'attendance_status' => 'invited',
                ]);
            }
        }

        // Add agenda items
        if ($request->agenda_items) {
            foreach ($request->agenda_items as $index => $item) {
                $meeting->agendaItems()->create([
                    'title' => $item['title'],
                    'description' => $item['description'] ?? null,
                    'sort_order' => $index,
                ]);
            }
        }

        // Notify attendees
        $meeting->load('attendees.employee.user');
        foreach ($meeting->attendees as $attendee) {
            if ($attendee->employee->user) {
                $attendee->employee->user->notify(new MeetingInvitationNotification($meeting));
            }
        }

        $meeting->load(['organizer', 'noteTaker', 'attendees.employee', 'agendaItems', 'series']);

        return response()->json(['data' => $meeting, 'message' => 'Meeting created successfully.'], 201);
    }

    public function show(Meeting $meeting): JsonResponse
    {
        $meeting->load([
            'organizer:id,full_name,profile_photo',
            'noteTaker:id,full_name,profile_photo',
            'series:id,name',
            'attendees.employee:id,full_name,profile_photo,department_id',
            'attendees.employee.department:id,name',
            'agendaItems',
            'decisions.decidedByEmployee:id,full_name',
            'decisions.agendaItem:id,title',
            'tasks.assignee:id,full_name',
            'tasks.subtasks',
            'attachments.uploader:id,full_name',
            'recordings.uploader:id,full_name',
            'recordings.transcript',
            'aiSummaries',
        ]);

        return response()->json(['data' => $meeting]);
    }

    public function update(UpdateMeetingRequest $request, Meeting $meeting): JsonResponse
    {
        $meeting->update($request->validated());

        $meeting->load(['organizer', 'noteTaker', 'attendees.employee', 'agendaItems', 'series']);

        return response()->json(['data' => $meeting, 'message' => 'Meeting updated successfully.']);
    }

    public function destroy(Meeting $meeting): JsonResponse
    {
        $meeting->delete();

        return response()->json(['message' => 'Meeting deleted successfully.']);
    }

    public function updateStatus(Request $request, Meeting $meeting): JsonResponse
    {
        $request->validate([
            'status' => ['required', 'in:draft,scheduled,in_progress,completed,cancelled'],
        ]);

        $meeting->update(['status' => $request->status]);

        return response()->json(['data' => $meeting, 'message' => 'Meeting status updated.']);
    }
}
```

**Step 2: Commit**

```bash
git add app/Http/Controllers/Api/Hr/HrMeetingController.php
git commit -m "feat(hr): add HrMeetingController with CRUD and status update"
```

---

### Task 10: Create HrMeetingSeriesController

**Files:**
- Create: `app/Http/Controllers/Api/Hr/HrMeetingSeriesController.php`

```php
<?php
namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\StoreMeetingSeriesRequest;
use App\Models\MeetingSeries;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HrMeetingSeriesController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $series = MeetingSeries::query()
            ->withCount('meetings')
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $series]);
    }

    public function store(StoreMeetingSeriesRequest $request): JsonResponse
    {
        $series = MeetingSeries::create([
            ...$request->validated(),
            'created_by' => $request->user()->id,
        ]);

        return response()->json(['data' => $series, 'message' => 'Series created successfully.'], 201);
    }

    public function show(MeetingSeries $series): JsonResponse
    {
        $series->load(['meetings' => function ($q) {
            $q->with(['organizer:id,full_name'])
                ->withCount('attendees')
                ->orderByDesc('meeting_date');
        }]);

        return response()->json(['data' => $series]);
    }
}
```

**Commit:**

```bash
git add app/Http/Controllers/Api/Hr/HrMeetingSeriesController.php
git commit -m "feat(hr): add HrMeetingSeriesController"
```

---

### Task 11: Create HrMeetingAttendeeController, HrMeetingAgendaController, HrMeetingDecisionController, HrMeetingAttachmentController

**Files:**
- Create: `app/Http/Controllers/Api/Hr/HrMeetingAttendeeController.php`
- Create: `app/Http/Controllers/Api/Hr/HrMeetingAgendaController.php`
- Create: `app/Http/Controllers/Api/Hr/HrMeetingDecisionController.php`
- Create: `app/Http/Controllers/Api/Hr/HrMeetingAttachmentController.php`

These follow the same patterns as the meeting controller. Each handles CRUD for its sub-resource under a meeting.

**Key patterns:**
- Route model binding with `Meeting $meeting`
- Validate ownership (attendee belongs to meeting, etc.)
- Return JSON responses

**Commit after each controller or batch them:**

```bash
git add app/Http/Controllers/Api/Hr/HrMeetingAttendeeController.php app/Http/Controllers/Api/Hr/HrMeetingAgendaController.php app/Http/Controllers/Api/Hr/HrMeetingDecisionController.php app/Http/Controllers/Api/Hr/HrMeetingAttachmentController.php
git commit -m "feat(hr): add meeting sub-resource controllers (attendees, agenda, decisions, attachments)"
```

---

### Task 12: Create HrTaskController

**Files:**
- Create: `app/Http/Controllers/Api/Hr/HrTaskController.php`

This controller handles the shared task system endpoints:
- `index()` — list all tasks (filterable by status, priority, assignee, meeting)
- `show()` — task detail with subtasks, comments, attachments
- `storeForMeeting()` — create task scoped to a meeting
- `update()` — update task fields
- `updateStatus()` — update task status (auto-set `completed_at` when completed)
- `destroy()` — soft delete
- `storeSubtask()` — create subtask under a parent task
- `storeComment()` — add comment to task
- `storeAttachment()` — upload file to task

**Commit:**

```bash
git add app/Http/Controllers/Api/Hr/HrTaskController.php
git commit -m "feat(hr): add HrTaskController for shared task system"
```

---

### Task 13: Create HrMeetingRecordingController and HrMeetingAiController

**Files:**
- Create: `app/Http/Controllers/Api/Hr/HrMeetingRecordingController.php`
- Create: `app/Http/Controllers/Api/Hr/HrMeetingAiController.php`

**HrMeetingRecordingController:**
- `store()` — upload recording file (multipart/form-data), save to `storage/app/meetings/recordings/`
- `destroy()` — delete recording and file from storage

**HrMeetingAiController:**
- `transcribe()` — dispatch `TranscribeMeetingRecording` job
- `getTranscript()` — return transcript for a meeting
- `analyze()` — dispatch `AnalyzeMeetingTranscript` job
- `getSummary()` — return AI summary
- `approveTasks()` — create tasks from approved suggested items

**Commit:**

```bash
git add app/Http/Controllers/Api/Hr/HrMeetingRecordingController.php app/Http/Controllers/Api/Hr/HrMeetingAiController.php
git commit -m "feat(hr): add recording and AI controllers"
```

---

### Task 14: Create Employee Self-Service Endpoints (MyMeetings, MyTasks)

**Files:**
- Modify: `app/Http/Controllers/Api/Hr/HrMyProfileController.php` (or create separate controllers)

Add methods:
- `myMeetings()` — meetings where current user's employee is an attendee
- `myTasks()` — tasks assigned to current user's employee

**Commit:**

```bash
git commit -m "feat(hr): add my meetings and my tasks self-service endpoints"
```

---

### Task 15: Register All Routes

**Files:**
- Modify: `routes/api.php`

Add inside the existing HR route group:

```php
// Meeting Series (before meetings resource to avoid route conflicts)
Route::get('meetings/series', [HrMeetingSeriesController::class, 'index'])->name('api.hr.meetings.series.index');
Route::post('meetings/series', [HrMeetingSeriesController::class, 'store'])->name('api.hr.meetings.series.store');
Route::get('meetings/series/{series}', [HrMeetingSeriesController::class, 'show'])->name('api.hr.meetings.series.show');

// Meetings
Route::apiResource('meetings', HrMeetingController::class)->names('api.hr.meetings');
Route::patch('meetings/{meeting}/status', [HrMeetingController::class, 'updateStatus'])->name('api.hr.meetings.update-status');

// Meeting sub-resources
Route::post('meetings/{meeting}/attendees', [HrMeetingAttendeeController::class, 'store'])->name('api.hr.meetings.attendees.store');
Route::delete('meetings/{meeting}/attendees/{employee}', [HrMeetingAttendeeController::class, 'destroy'])->name('api.hr.meetings.attendees.destroy');
Route::patch('meetings/{meeting}/attendees/{employee}', [HrMeetingAttendeeController::class, 'update'])->name('api.hr.meetings.attendees.update');

Route::post('meetings/{meeting}/agenda-items', [HrMeetingAgendaController::class, 'store'])->name('api.hr.meetings.agenda.store');
Route::put('meetings/{meeting}/agenda-items/{agendaItem}', [HrMeetingAgendaController::class, 'update'])->name('api.hr.meetings.agenda.update');
Route::delete('meetings/{meeting}/agenda-items/{agendaItem}', [HrMeetingAgendaController::class, 'destroy'])->name('api.hr.meetings.agenda.destroy');
Route::patch('meetings/{meeting}/agenda-items/reorder', [HrMeetingAgendaController::class, 'reorder'])->name('api.hr.meetings.agenda.reorder');

Route::post('meetings/{meeting}/decisions', [HrMeetingDecisionController::class, 'store'])->name('api.hr.meetings.decisions.store');
Route::put('meetings/{meeting}/decisions/{decision}', [HrMeetingDecisionController::class, 'update'])->name('api.hr.meetings.decisions.update');
Route::delete('meetings/{meeting}/decisions/{decision}', [HrMeetingDecisionController::class, 'destroy'])->name('api.hr.meetings.decisions.destroy');

Route::post('meetings/{meeting}/attachments', [HrMeetingAttachmentController::class, 'store'])->name('api.hr.meetings.attachments.store');
Route::delete('meetings/{meeting}/attachments/{attachment}', [HrMeetingAttachmentController::class, 'destroy'])->name('api.hr.meetings.attachments.destroy');

// Recording & AI
Route::post('meetings/{meeting}/recordings', [HrMeetingRecordingController::class, 'store'])->name('api.hr.meetings.recordings.store');
Route::delete('meetings/{meeting}/recordings/{recording}', [HrMeetingRecordingController::class, 'destroy'])->name('api.hr.meetings.recordings.destroy');
Route::post('meetings/{meeting}/recordings/{recording}/transcribe', [HrMeetingAiController::class, 'transcribe'])->name('api.hr.meetings.recordings.transcribe');
Route::get('meetings/{meeting}/transcript', [HrMeetingAiController::class, 'getTranscript'])->name('api.hr.meetings.transcript');
Route::post('meetings/{meeting}/ai-analyze', [HrMeetingAiController::class, 'analyze'])->name('api.hr.meetings.ai-analyze');
Route::get('meetings/{meeting}/ai-summary', [HrMeetingAiController::class, 'getSummary'])->name('api.hr.meetings.ai-summary');
Route::post('meetings/{meeting}/ai-summary/approve-tasks', [HrMeetingAiController::class, 'approveTasks'])->name('api.hr.meetings.ai-approve-tasks');

// Shared Tasks
Route::get('tasks', [HrTaskController::class, 'index'])->name('api.hr.tasks.index');
Route::get('tasks/{task}', [HrTaskController::class, 'show'])->name('api.hr.tasks.show');
Route::post('meetings/{meeting}/tasks', [HrTaskController::class, 'storeForMeeting'])->name('api.hr.meetings.tasks.store');
Route::put('tasks/{task}', [HrTaskController::class, 'update'])->name('api.hr.tasks.update');
Route::patch('tasks/{task}/status', [HrTaskController::class, 'updateStatus'])->name('api.hr.tasks.update-status');
Route::delete('tasks/{task}', [HrTaskController::class, 'destroy'])->name('api.hr.tasks.destroy');
Route::post('tasks/{task}/subtasks', [HrTaskController::class, 'storeSubtask'])->name('api.hr.tasks.subtasks.store');
Route::post('tasks/{task}/comments', [HrTaskController::class, 'storeComment'])->name('api.hr.tasks.comments.store');
Route::post('tasks/{task}/attachments', [HrTaskController::class, 'storeAttachment'])->name('api.hr.tasks.attachments.store');

// Employee self-service
Route::get('my/meetings', [HrMyProfileController::class, 'myMeetings'])->name('api.hr.my.meetings');
Route::get('my/tasks', [HrMyProfileController::class, 'myTasks'])->name('api.hr.my.tasks');
```

**Commit:**

```bash
git add routes/api.php
git commit -m "feat(hr): register all MOM module routes (meetings, tasks, AI)"
```

---

## Phase 3: AI Services & Jobs

### Task 16: Create MeetingTranscriptionService

**Files:**
- Create: `app/Services/Hr/MeetingTranscriptionService.php`

Handles Google Cloud Speech-to-Text API integration:
- Accept audio file path
- Send to Google Speech-to-Text API
- Return transcript text
- Requires `GOOGLE_CLOUD_PROJECT_ID` and `GOOGLE_CLOUD_KEY_FILE` env vars

**Dependencies:**
```bash
composer require google/cloud-speech
```

**Commit:**

```bash
git add app/Services/Hr/MeetingTranscriptionService.php composer.json composer.lock
git commit -m "feat(hr): add MeetingTranscriptionService for Google Cloud Speech-to-Text"
```

---

### Task 17: Create MeetingAiAnalysisService

**Files:**
- Create: `app/Services/Hr/MeetingAiAnalysisService.php`

Handles Google Gemini API integration:
- Accept transcript text
- Send to Gemini with structured prompt requesting: summary, key_points, action_items
- Parse JSON response
- Return structured data
- Requires `GOOGLE_GEMINI_API_KEY` env var

**Dependencies:**
```bash
composer require gemini-api-php/laravel
```

**Commit:**

```bash
git add app/Services/Hr/MeetingAiAnalysisService.php composer.json composer.lock
git commit -m "feat(hr): add MeetingAiAnalysisService for Google Gemini analysis"
```

---

### Task 18: Create Queued Jobs

**Files:**
- Create: `app/Jobs/Hr/TranscribeMeetingRecording.php`
- Create: `app/Jobs/Hr/AnalyzeMeetingTranscript.php`

**TranscribeMeetingRecording:**
- Accepts `MeetingRecording $recording`
- Calls `MeetingTranscriptionService`
- Creates/updates `MeetingTranscript` record
- Sets status to completed/failed
- Notifies organizer on completion

**AnalyzeMeetingTranscript:**
- Accepts `MeetingTranscript $transcript`
- Calls `MeetingAiAnalysisService`
- Creates `MeetingAiSummary` record
- Sets status to completed/failed
- Sends `AiAnalysisCompletedNotification`

**Commit:**

```bash
git add app/Jobs/Hr/TranscribeMeetingRecording.php app/Jobs/Hr/AnalyzeMeetingTranscript.php
git commit -m "feat(hr): add queued jobs for transcription and AI analysis"
```

---

## Phase 4: Notifications

### Task 19: Create MOM Notifications

**Files:**
- Create: `app/Notifications/Hr/MeetingInvitationNotification.php`
- Create: `app/Notifications/Hr/MeetingUpdatedNotification.php`
- Create: `app/Notifications/Hr/MeetingCancelledNotification.php`
- Create: `app/Notifications/Hr/TaskAssignedNotification.php`
- Create: `app/Notifications/Hr/TaskDeadlineApproachingNotification.php`
- Create: `app/Notifications/Hr/AiAnalysisCompletedNotification.php`

All extend `BaseHrNotification` and implement `channels()`, `title()`, `body()`, `actionUrl()`, `icon()`, and `toMail()`.

**Pattern** (follow existing `WelcomeOnboarding` notification):

```php
class MeetingInvitationNotification extends BaseHrNotification
{
    public function __construct(public Meeting $meeting) {}

    protected function channels(): array
    {
        return ['database', 'mail', 'webpush'];
    }

    protected function title(): string
    {
        return 'Meeting Invitation';
    }

    protected function body(): string
    {
        return "You've been invited to \"{$this->meeting->title}\" on {$this->meeting->meeting_date->format('d M Y')} at {$this->meeting->start_time}.";
    }

    protected function actionUrl(): string
    {
        return "/hr/meetings/{$this->meeting->id}";
    }

    protected function icon(): string
    {
        return 'calendar';
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Meeting Invitation: {$this->meeting->title}")
            ->greeting('Hello!')
            ->line("You've been invited to a meeting.")
            ->line("**{$this->meeting->title}**")
            ->line("Date: {$this->meeting->meeting_date->format('d M Y')}")
            ->line("Time: {$this->meeting->start_time}")
            ->line("Location: " . ($this->meeting->location ?? 'TBD'))
            ->action('View Meeting', url("/hr/meetings/{$this->meeting->id}"));
    }
}
```

**Commit:**

```bash
git add app/Notifications/Hr/MeetingInvitationNotification.php app/Notifications/Hr/MeetingUpdatedNotification.php app/Notifications/Hr/MeetingCancelledNotification.php app/Notifications/Hr/TaskAssignedNotification.php app/Notifications/Hr/TaskDeadlineApproachingNotification.php app/Notifications/Hr/AiAnalysisCompletedNotification.php
git commit -m "feat(hr): add MOM notifications (invitation, update, cancel, task, AI)"
```

---

## Phase 5: Frontend — API Layer & Routes

### Task 20: Add Meeting & Task API Functions

**Files:**
- Modify: `resources/js/hr/lib/api.js`

Add sections:

```javascript
// ========== Meetings ==========
export const fetchMeetings = (params) => api.get('/meetings', { params }).then(r => r.data);
export const fetchMeeting = (id) => api.get(`/meetings/${id}`).then(r => r.data);
export const createMeeting = (data) => api.post('/meetings', data).then(r => r.data);
export const updateMeeting = (id, data) => api.put(`/meetings/${id}`, data).then(r => r.data);
export const deleteMeeting = (id) => api.delete(`/meetings/${id}`).then(r => r.data);
export const updateMeetingStatus = (id, data) => api.patch(`/meetings/${id}/status`, data).then(r => r.data);

// ========== Meeting Series ==========
export const fetchMeetingSeries = () => api.get('/meetings/series').then(r => r.data);
export const createMeetingSeries = (data) => api.post('/meetings/series', data).then(r => r.data);
export const fetchMeetingSeriesDetail = (id) => api.get(`/meetings/series/${id}`).then(r => r.data);

// ========== Meeting Attendees ==========
export const addMeetingAttendees = (meetingId, data) => api.post(`/meetings/${meetingId}/attendees`, data).then(r => r.data);
export const removeMeetingAttendee = (meetingId, employeeId) => api.delete(`/meetings/${meetingId}/attendees/${employeeId}`).then(r => r.data);
export const updateAttendanceStatus = (meetingId, employeeId, data) => api.patch(`/meetings/${meetingId}/attendees/${employeeId}`, data).then(r => r.data);

// ========== Meeting Agenda ==========
export const addAgendaItem = (meetingId, data) => api.post(`/meetings/${meetingId}/agenda-items`, data).then(r => r.data);
export const updateAgendaItem = (meetingId, itemId, data) => api.put(`/meetings/${meetingId}/agenda-items/${itemId}`, data).then(r => r.data);
export const deleteAgendaItem = (meetingId, itemId) => api.delete(`/meetings/${meetingId}/agenda-items/${itemId}`).then(r => r.data);
export const reorderAgendaItems = (meetingId, data) => api.patch(`/meetings/${meetingId}/agenda-items/reorder`, data).then(r => r.data);

// ========== Meeting Decisions ==========
export const addDecision = (meetingId, data) => api.post(`/meetings/${meetingId}/decisions`, data).then(r => r.data);
export const updateDecision = (meetingId, decId, data) => api.put(`/meetings/${meetingId}/decisions/${decId}`, data).then(r => r.data);
export const deleteDecision = (meetingId, decId) => api.delete(`/meetings/${meetingId}/decisions/${decId}`).then(r => r.data);

// ========== Meeting Attachments ==========
export const uploadMeetingAttachment = (meetingId, formData) => api.post(`/meetings/${meetingId}/attachments`, formData, {
    headers: { 'Content-Type': 'multipart/form-data' },
}).then(r => r.data);
export const deleteMeetingAttachment = (meetingId, attId) => api.delete(`/meetings/${meetingId}/attachments/${attId}`).then(r => r.data);

// ========== Meeting Recording & AI ==========
export const uploadRecording = (meetingId, formData) => api.post(`/meetings/${meetingId}/recordings`, formData, {
    headers: { 'Content-Type': 'multipart/form-data' },
}).then(r => r.data);
export const deleteRecording = (meetingId, recId) => api.delete(`/meetings/${meetingId}/recordings/${recId}`).then(r => r.data);
export const triggerTranscription = (meetingId, recId) => api.post(`/meetings/${meetingId}/recordings/${recId}/transcribe`).then(r => r.data);
export const fetchTranscript = (meetingId) => api.get(`/meetings/${meetingId}/transcript`).then(r => r.data);
export const triggerAiAnalysis = (meetingId) => api.post(`/meetings/${meetingId}/ai-analyze`).then(r => r.data);
export const fetchAiSummary = (meetingId) => api.get(`/meetings/${meetingId}/ai-summary`).then(r => r.data);
export const approveAiTasks = (meetingId, data) => api.post(`/meetings/${meetingId}/ai-summary/approve-tasks`, data).then(r => r.data);

// ========== Tasks ==========
export const fetchTasks = (params) => api.get('/tasks', { params }).then(r => r.data);
export const fetchTask = (id) => api.get(`/tasks/${id}`).then(r => r.data);
export const createMeetingTask = (meetingId, data) => api.post(`/meetings/${meetingId}/tasks`, data).then(r => r.data);
export const updateTask = (id, data) => api.put(`/tasks/${id}`, data).then(r => r.data);
export const updateTaskStatus = (id, data) => api.patch(`/tasks/${id}/status`, data).then(r => r.data);
export const deleteTask = (id) => api.delete(`/tasks/${id}`).then(r => r.data);
export const createSubtask = (taskId, data) => api.post(`/tasks/${taskId}/subtasks`, data).then(r => r.data);
export const addTaskComment = (taskId, data) => api.post(`/tasks/${taskId}/comments`, data).then(r => r.data);
export const uploadTaskAttachment = (taskId, formData) => api.post(`/tasks/${taskId}/attachments`, formData, {
    headers: { 'Content-Type': 'multipart/form-data' },
}).then(r => r.data);

// ========== My Meetings & Tasks ==========
export const fetchMyMeetings = (params) => api.get('/my/meetings', { params }).then(r => r.data);
export const fetchMyTasks = (params) => api.get('/my/tasks', { params }).then(r => r.data);
```

**Commit:**

```bash
git add resources/js/hr/lib/api.js
git commit -m "feat(hr): add meeting and task API functions to frontend"
```

---

### Task 21: Add Routes to App.jsx

**Files:**
- Modify: `resources/js/hr/App.jsx`

Add meeting and task routes to both `AdminRoutes()` and `EmployeeRoutes()`:

```jsx
// In AdminRoutes:
<Route path="meetings" element={<MeetingList />} />
<Route path="meetings/create" element={<MeetingCreate />} />
<Route path="meetings/:id" element={<MeetingDetail />} />
<Route path="meetings/:id/edit" element={<MeetingEdit />} />
<Route path="meetings/:id/record" element={<MeetingRecord />} />
<Route path="meetings/series" element={<MeetingSeriesList />} />
<Route path="meetings/tasks" element={<TaskDashboard />} />

// In EmployeeRoutes:
<Route path="my/meetings" element={<MyMeetings />} />
<Route path="my/tasks" element={<MyTasks />} />
```

**Commit:**

```bash
git add resources/js/hr/App.jsx
git commit -m "feat(hr): add meeting and task routes to App.jsx"
```

---

## Phase 6: Frontend — Meeting Pages

### Task 22: Create MeetingList Page

**Files:**
- Create: `resources/js/hr/pages/meetings/MeetingList.jsx`

Features:
- Tabs: Upcoming, Past, Draft, All
- Search by title
- Filter by status, series
- Paginated table with columns: Title, Date, Time, Status, Organizer, Attendees count, Actions
- "Create Meeting" button
- Link to Meeting Series

Follow the pattern from `EmployeeList.jsx`.

**Commit:**

```bash
git add resources/js/hr/pages/meetings/MeetingList.jsx
git commit -m "feat(hr): add MeetingList page"
```

---

### Task 23: Create MeetingCreate Page

**Files:**
- Create: `resources/js/hr/pages/meetings/MeetingCreate.jsx`

Form fields:
- Title, Description, Location
- Date, Start Time, End Time
- Series selection (dropdown with option to create new)
- Note Taker selection (employee search/select)
- Attendees (multi-select employee picker)
- Agenda Items (dynamic list with add/remove, drag-to-reorder)
- Save as Draft / Schedule buttons

**Commit:**

```bash
git add resources/js/hr/pages/meetings/MeetingCreate.jsx
git commit -m "feat(hr): add MeetingCreate page with form"
```

---

### Task 24: Create MeetingDetail Page (MOM View)

**Files:**
- Create: `resources/js/hr/pages/meetings/MeetingDetail.jsx`

This is the main MOM view. Sections:
- **Header:** Title, date, time, location, status badge, series link
- **Actions bar:** Edit, Start/Complete/Cancel meeting, Record, Upload attachment
- **Attendees card:** List with attendance status checkboxes (note taker can mark)
- **Agenda card:** Numbered list of agenda items
- **Decisions card:** List of decisions with "Add Decision" button
- **Tasks card:** List of tasks with status badges, assignee, deadline. "Add Task" button
- **Recording & AI card:** Recording player, transcript viewer, AI summary panel
- **Attachments card:** List of uploaded files with download links

**Commit:**

```bash
git add resources/js/hr/pages/meetings/MeetingDetail.jsx
git commit -m "feat(hr): add MeetingDetail page (full MOM view)"
```

---

### Task 25: Create MeetingEdit Page

**Files:**
- Create: `resources/js/hr/pages/meetings/MeetingEdit.jsx`

Same form as MeetingCreate but pre-populated with existing data. Uses `updateMeeting` API.

**Commit:**

```bash
git add resources/js/hr/pages/meetings/MeetingEdit.jsx
git commit -m "feat(hr): add MeetingEdit page"
```

---

### Task 26: Create MeetingRecord Page

**Files:**
- Create: `resources/js/hr/pages/meetings/MeetingRecord.jsx`

Browser-based audio recorder using MediaRecorder API:
- Start/Stop/Pause buttons
- Recording timer display
- Audio waveform visualization (optional, can use simple timer)
- Auto-upload on stop
- Option to upload existing file instead
- After upload: button to trigger transcription

**Commit:**

```bash
git add resources/js/hr/pages/meetings/MeetingRecord.jsx
git commit -m "feat(hr): add MeetingRecord page with browser audio recorder"
```

---

### Task 27: Create MeetingSeriesList Page

**Files:**
- Create: `resources/js/hr/pages/meetings/MeetingSeriesList.jsx`

Simple list of meeting series with:
- Name, description, meeting count
- "Create Series" button with modal/dialog
- Click to view series detail (list of meetings in the series)

**Commit:**

```bash
git add resources/js/hr/pages/meetings/MeetingSeriesList.jsx
git commit -m "feat(hr): add MeetingSeriesList page"
```

---

### Task 28: Create TaskDashboard Page

**Files:**
- Create: `resources/js/hr/pages/meetings/TaskDashboard.jsx`

Cross-meeting task view:
- Filter by status, priority, assignee, deadline range
- Table view with columns: Task, Meeting, Assignee, Priority, Deadline, Status
- Click to expand task detail (subtasks, comments, attachments)
- Inline status update
- Stats cards at top: Total, Pending, In Progress, Completed, Overdue

**Commit:**

```bash
git add resources/js/hr/pages/meetings/TaskDashboard.jsx
git commit -m "feat(hr): add TaskDashboard page"
```

---

## Phase 7: Frontend — Employee Self-Service & Shared Components

### Task 29: Create MyMeetings and MyTasks Pages

**Files:**
- Create: `resources/js/hr/pages/my/MyMeetings.jsx`
- Create: `resources/js/hr/pages/my/MyTasks.jsx`

**MyMeetings:** Simplified meeting list showing only meetings the employee is part of. Link to meeting detail.

**MyTasks:** Task list filtered to current employee. Status updates, comment thread.

**Commit:**

```bash
git add resources/js/hr/pages/my/MyMeetings.jsx resources/js/hr/pages/my/MyTasks.jsx
git commit -m "feat(hr): add MyMeetings and MyTasks employee self-service pages"
```

---

### Task 30: Create Reusable Meeting Components

**Files:**
- Create: `resources/js/hr/components/meetings/AttendeeList.jsx`
- Create: `resources/js/hr/components/meetings/AgendaEditor.jsx`
- Create: `resources/js/hr/components/meetings/DecisionLog.jsx`
- Create: `resources/js/hr/components/meetings/TaskList.jsx`
- Create: `resources/js/hr/components/meetings/RecordingPlayer.jsx`
- Create: `resources/js/hr/components/meetings/TranscriptViewer.jsx`
- Create: `resources/js/hr/components/meetings/AiSummaryPanel.jsx`

These are reusable components used by MeetingDetail and other pages.

**Commit:**

```bash
git add resources/js/hr/components/meetings/
git commit -m "feat(hr): add reusable meeting components (attendees, agenda, decisions, tasks, recording, AI)"
```

---

## Phase 8: Navigation & Integration

### Task 31: Update Navigation Menus

**Files:**
- Modify: `resources/js/hr/layouts/HrLayout.jsx` — add "Meetings" nav item with sub-items (All Meetings, Series, Tasks)
- Modify: `resources/js/hr/layouts/EmployeeAppLayout.jsx` — add "My Meetings" and "My Tasks" nav items

**Commit:**

```bash
git add resources/js/hr/layouts/HrLayout.jsx resources/js/hr/layouts/EmployeeAppLayout.jsx
git commit -m "feat(hr): add MOM navigation items to HR layouts"
```

---

### Task 32: Build Frontend Assets & Test

**Step 1: Build**

```bash
npm run build
```

**Step 2: Verify routes load**

Visit `/hr/meetings` and `/hr/meetings/create` to verify pages render.

**Step 3: Test API endpoints with tinker**

```bash
php artisan tinker
# Verify models and relationships work
```

**Step 4: Commit any fixes**

```bash
git commit -m "fix(hr): resolve build issues for MOM module"
```

---

## Phase 9: Testing

### Task 33: Write Feature Tests for Meeting CRUD

**Files:**
- Create: `tests/Feature/Hr/HrMeetingTest.php`

Test cases:
- Can list meetings
- Can create a meeting with attendees and agenda
- Can view meeting detail
- Can update a meeting
- Can delete a meeting (soft delete)
- Can update meeting status
- Unauthorized user cannot create meeting

Run: `php artisan test --compact --filter=HrMeeting`

---

### Task 34: Write Feature Tests for Tasks

**Files:**
- Create: `tests/Feature/Hr/HrTaskTest.php`

Test cases:
- Can create task for a meeting
- Can update task
- Can change task status
- Can add subtask
- Can add comment
- Completing task sets completed_at
- Can list tasks filtered by status/assignee

Run: `php artisan test --compact --filter=HrTask`

---

### Task 35: Write Feature Tests for Meeting Series, Attendees, Decisions

**Files:**
- Create: `tests/Feature/Hr/HrMeetingSeriesTest.php`
- Create: `tests/Feature/Hr/HrMeetingAttendeeTest.php`

Run: `php artisan test --compact`

---

## Summary

| Phase | Tasks | Description |
|-------|-------|-------------|
| 1 | 1-6 | Database migrations & models (12 tables, 12 models) |
| 2 | 7-15 | Backend API (9 controllers, 6 form requests, all routes) |
| 3 | 16-18 | AI services & queued jobs |
| 4 | 19 | Notifications (6 notification classes) |
| 5 | 20-21 | Frontend API layer & router config |
| 6 | 22-28 | Frontend meeting pages (7 pages) |
| 7 | 29-30 | Employee self-service & shared components |
| 8 | 31-32 | Navigation & integration |
| 9 | 33-35 | Feature tests |

**Total: 35 tasks across 9 phases**
