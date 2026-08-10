# Task Management System — Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Build a comprehensive 28-module Task Management System at `/workspace` (Inertia React SPA) for all BeDaie staff.

**Architecture:** New Inertia React app extending existing Task model. Department → Project → Task → Subtask → Checklist hierarchy. Controllers at `app/Http/Controllers/Workspace/`, React pages at `resources/js/workspace/`. Custom middleware `HandleWorkspaceInertiaRequests` with root view `workspace.app`.

**Tech Stack:** Laravel 12, React 19, Inertia.js, Tailwind CSS v4, Pest PHP 4

**Design doc:** `docs/plans/2026-08-10-task-management-system-design.md`

---

## Phase 1: Foundation (Migrations + Models + Inertia App Shell)

### Task 1: Migration — Add TMS columns to existing tasks table

**Files:**
- Create: `database/migrations/2026_08_10_000001_add_tms_columns_to_tasks_table.php`
- Modify: `app/Models/Task.php`

**Step 1: Create migration**

```bash
php artisan make:migration add_tms_columns_to_tasks_table --table=tasks --no-interaction
```

**Step 2: Write migration**

```php
public function up(): void
{
    Schema::table('tasks', function (Blueprint $table): void {
        $table->foreignId('project_id')->nullable()->constrained('tms_projects')->nullOnDelete()->after('category_id');
        $table->unsignedInteger('estimated_minutes')->nullable()->after('deadline');
        $table->unsignedInteger('actual_minutes')->nullable()->after('estimated_minutes');
        $table->date('start_date')->nullable()->after('actual_minutes');
        $table->unsignedInteger('sort_order')->default(0)->after('start_date');
        $table->boolean('is_recurring')->default(false)->after('sort_order');
        $table->foreignId('recurring_config_id')->nullable()->constrained('task_recurring_configs')->nullOnDelete()->after('is_recurring');
        $table->string('approval_status')->nullable()->after('recurring_config_id');
        $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete()->after('approval_status');
        $table->timestamp('approved_at')->nullable()->after('approved_by');
        $table->unsignedInteger('points')->default(0)->after('approved_at');
        $table->json('tags')->nullable()->after('points');
    });
}

public function down(): void
{
    Schema::table('tasks', function (Blueprint $table): void {
        $table->dropForeign(['project_id']);
        $table->dropForeign(['recurring_config_id']);
        $table->dropForeign(['approved_by']);
        $table->dropColumn([
            'project_id', 'estimated_minutes', 'actual_minutes', 'start_date',
            'sort_order', 'is_recurring', 'recurring_config_id', 'approval_status',
            'approved_by', 'approved_at', 'points', 'tags',
        ]);
    });
}
```

**Note:** This migration depends on `tms_projects` and `task_recurring_configs` tables existing first. Run Task 2 and Task 5 migrations before this one. Reorder the timestamp in the filename accordingly.

**Step 3: Update Task model fillable + casts**

Add to `$fillable`: `'project_id', 'estimated_minutes', 'actual_minutes', 'start_date', 'sort_order', 'is_recurring', 'recurring_config_id', 'approval_status', 'approved_by', 'approved_at', 'points', 'tags'`

Add to `casts()`: `'start_date' => 'date', 'approved_at' => 'datetime', 'tags' => 'array', 'is_recurring' => 'boolean'`

Add relationship: `public function project(): BelongsTo { return $this->belongsTo(TmsProject::class, 'project_id'); }`

**Step 4: Run & commit**

```bash
php artisan migrate
git add -A && git commit -m "feat(tms): add TMS columns to tasks table"
```

---

### Task 2: Migration — Create tms_projects + tms_project_members tables

**Files:**
- Create: `database/migrations/2026_08_10_000002_create_tms_projects_table.php`

**Step 1: Create migration**

```bash
php artisan make:migration create_tms_projects_table --no-interaction
```

**Step 2: Write migration**

```php
public function up(): void
{
    Schema::create('tms_projects', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->text('description')->nullable();
        $table->string('color', 7)->default('#6366f1');
        $table->string('icon')->default('folder');
        $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
        $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
        $table->string('status')->default('active'); // active, on_hold, completed, archived
        $table->date('start_date')->nullable();
        $table->date('target_date')->nullable();
        $table->unsignedInteger('sort_order')->default(0);
        $table->timestamps();
        $table->softDeletes();

        $table->index(['department_id', 'status']);
    });

    Schema::create('tms_project_members', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('project_id')->constrained('tms_projects')->cascadeOnDelete();
        $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
        $table->string('role')->default('member'); // owner, manager, member, viewer
        $table->timestamps();

        $table->unique(['project_id', 'user_id'], 'tms_pm_project_user_unique');
    });
}

public function down(): void
{
    Schema::dropIfExists('tms_project_members');
    Schema::dropIfExists('tms_projects');
}
```

**Step 3: Run & commit**

```bash
php artisan migrate
git add -A && git commit -m "feat(tms): create tms_projects and tms_project_members tables"
```

---

### Task 3: Migration — Create task_checklists table

**Files:**
- Create: `database/migrations/2026_08_10_000003_create_task_checklists_table.php`

**Step 1: Create & write migration**

```php
public function up(): void
{
    Schema::create('task_checklists', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
        $table->string('title');
        $table->boolean('is_completed')->default(false);
        $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
        $table->timestamp('completed_at')->nullable();
        $table->unsignedInteger('sort_order')->default(0);
        $table->timestamps();

        $table->index(['task_id', 'sort_order']);
    });
}

public function down(): void
{
    Schema::dropIfExists('task_checklists');
}
```

**Step 2: Run & commit**

```bash
php artisan migrate
git add -A && git commit -m "feat(tms): create task_checklists table"
```

---

### Task 4: Migration — Create task_time_entries table

**Files:**
- Create: `database/migrations/2026_08_10_000004_create_task_time_entries_table.php`

```php
public function up(): void
{
    Schema::create('task_time_entries', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
        $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
        $table->timestamp('started_at')->useCurrent();
        $table->timestamp('ended_at')->nullable();
        $table->unsignedInteger('duration_seconds')->default(0);
        $table->string('description')->nullable();
        $table->timestamps();

        $table->index(['task_id', 'user_id']);
        $table->index(['user_id', 'ended_at']); // find running timers
    });
}
```

**Commit:** `feat(tms): create task_time_entries table`

---

### Task 5: Migration — Create task_recurring_configs table

```php
public function up(): void
{
    Schema::create('task_recurring_configs', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
        $table->string('frequency'); // daily, weekly, monthly, yearly
        $table->unsignedTinyInteger('day_of_week')->nullable(); // 0=Sun..6=Sat
        $table->unsignedTinyInteger('day_of_month')->nullable(); // 1-31
        $table->time('time_of_day')->nullable();
        $table->timestamp('last_generated_at')->nullable();
        $table->timestamp('next_due_at')->useCurrent();
        $table->boolean('is_active')->default(true);
        $table->timestamps();
    });
}
```

**Commit:** `feat(tms): create task_recurring_configs table`

---

### Task 6: Migration — Create task_activity_logs, task_watchers, task_dependencies tables

```php
public function up(): void
{
    Schema::create('task_activity_logs', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
        $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
        $table->string('action'); // created, status_changed, assigned, commented, etc.
        $table->string('field')->nullable();
        $table->text('old_value')->nullable();
        $table->text('new_value')->nullable();
        $table->timestamps();

        $table->index(['task_id', 'created_at']);
    });

    Schema::create('task_watchers', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
        $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
        $table->timestamps();

        $table->unique(['task_id', 'user_id'], 'tw_task_user_unique');
    });

    Schema::create('task_dependencies', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
        $table->foreignId('depends_on_task_id')->constrained('tasks')->cascadeOnDelete();
        $table->string('type')->default('blocks'); // blocks, blocked_by
        $table->timestamps();

        $table->unique(['task_id', 'depends_on_task_id'], 'td_task_dep_unique');
    });
}

public function down(): void
{
    Schema::dropIfExists('task_dependencies');
    Schema::dropIfExists('task_watchers');
    Schema::dropIfExists('task_activity_logs');
}
```

**Commit:** `feat(tms): create task_activity_logs, task_watchers, task_dependencies tables`

---

### Task 7: Migration — Create gamification tables (tms_badges, tms_badge_awards, tms_user_stats)

```php
public function up(): void
{
    Schema::create('tms_badges', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->text('description')->nullable();
        $table->string('icon')->default('trophy');
        $table->string('color', 7)->default('#f59e0b');
        $table->string('criteria_type'); // tasks_completed, streak, speed, first_task
        $table->unsignedInteger('criteria_value')->default(1);
        $table->unsignedInteger('points')->default(10);
        $table->timestamps();
    });

    Schema::create('tms_badge_awards', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('badge_id')->constrained('tms_badges')->cascadeOnDelete();
        $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
        $table->timestamp('awarded_at')->useCurrent();
        $table->timestamps();

        $table->unique(['badge_id', 'user_id'], 'tms_ba_badge_user_unique');
    });

    Schema::create('tms_user_stats', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
        $table->date('date');
        $table->unsignedInteger('tasks_completed')->default(0);
        $table->unsignedInteger('tasks_created')->default(0);
        $table->unsignedInteger('tasks_overdue')->default(0);
        $table->unsignedInteger('time_tracked_seconds')->default(0);
        $table->unsignedInteger('streak_days')->default(0);
        $table->unsignedInteger('total_points')->default(0);
        $table->timestamps();

        $table->unique(['user_id', 'date'], 'tms_us_user_date_unique');
    });
}

public function down(): void
{
    Schema::dropIfExists('tms_user_stats');
    Schema::dropIfExists('tms_badge_awards');
    Schema::dropIfExists('tms_badges');
}
```

**Commit:** `feat(tms): create gamification tables`

---

### Task 8: Migration — Add role column to task_assignee pivot

```php
public function up(): void
{
    Schema::table('task_assignee', function (Blueprint $table): void {
        $table->string('role')->default('member')->after('employee_id'); // leader, member
    });
}
```

**Commit:** `feat(tms): add role column to task_assignee pivot`

---

### Task 9: Models — TmsProject + TmsProjectMember + Factories

**Files:**
- Create: `app/Models/TmsProject.php`
- Create: `app/Models/TmsProjectMember.php`
- Create: `database/factories/TmsProjectFactory.php`

**TmsProject model:** fillable (name, description, color, icon, department_id, owner_id, status, start_date, target_date, sort_order), casts (start_date→date, target_date→date), relationships (department, owner, members→BelongsToMany User via tms_project_members, tasks→HasMany Task), scopes (active, forDepartment).

**TmsProjectMember model:** fillable (project_id, user_id, role), relationships (project, user).

**Factory:** default name + description + owner, states: onHold(), completed(), archived().

**Commit:** `feat(tms): add TmsProject and TmsProjectMember models with factory`

---

### Task 10: Models — TaskChecklist, TaskTimeEntry, TaskActivityLog, TaskWatcher, TaskDependency, TaskRecurringConfig

**Create 6 models** following the same patterns as Task 9. Each with:
- Fillable fields matching migration columns
- Proper casts (timestamps, booleans)
- BelongsTo relationships (task, user)
- Factories for TaskChecklist and TaskTimeEntry

**Add relationships to Task model:**
- `checklists(): HasMany`
- `timeEntries(): HasMany`
- `activityLogs(): HasMany`
- `watchers(): BelongsToMany User via task_watchers`
- `dependencies(): HasMany TaskDependency`
- `recurringConfig(): HasOne`

**Commit:** `feat(tms): add TaskChecklist, TaskTimeEntry, TaskActivityLog and related models`

---

### Task 11: Models — TmsBadge, TmsBadgeAward, TmsUserStat

**Create 3 models + TmsBadgeFactory.**

**Commit:** `feat(tms): add gamification models (Badge, BadgeAward, UserStat)`

---

### Task 12: Inertia App Shell — Middleware + Root View + React Entry

**Files:**
- Create: `app/Http/Middleware/HandleWorkspaceInertiaRequests.php`
- Create: `resources/views/workspace/app.blade.php`
- Create: `resources/js/workspace/app.jsx`
- Create: `resources/js/workspace/styles/workspace.css`
- Modify: `vite.config.js` (add workspace entry point)

**HandleWorkspaceInertiaRequests:** extends HandleInertiaRequests, rootView = `workspace.app`, shares: auth user, departments list, TMS permissions, unread notification count.

**Root view:** Standard Inertia blade shell (copy from `resources/views/student-portal/app.blade.php` pattern), @vite workspace entry.

**app.jsx:** createInertiaApp with resolve pages from `./pages/`, register WorkspaceLayout.

**workspace.css:** Import Tailwind, define design tokens (--color-brand, glassmorphism variables matching CEO app style).

**vite.config.js:** Add `'resources/js/workspace/app.jsx'` to input array.

**Commit:** `feat(tms): create workspace Inertia app shell`

---

### Task 13: React Layout — WorkspaceLayout with sidebar + header

**Files:**
- Create: `resources/js/workspace/layouts/WorkspaceLayout.jsx`
- Create: `resources/js/workspace/lib/utils.js`
- Create: `resources/js/workspace/lib/api.js`

**WorkspaceLayout:** Sidebar with nav items (Dashboard, My Tasks, Board, Calendar, Gantt, Projects, KPI, Reports, Leaderboard, Settings), department list (collapsible), user profile at bottom. Header with page title, search bar, notification bell, create task button.

**lib/utils.js:** cn(), formatDate(), formatMoney(), priority colors, status colors.

**lib/api.js:** workspaceJson(), workspaceSend() helpers (same pattern as fighter/lib/utils.js).

**Commit:** `feat(tms): create WorkspaceLayout with sidebar and header`

---

### Task 14: Routes — Register workspace route group

**Files:**
- Modify: `routes/web.php`
- Create: `app/Http/Controllers/Workspace/DashboardController.php`

**Routes:**
```php
Route::middleware(['auth', 'role:admin,employee,ceo', HandleWorkspaceInertiaRequests::class])
    ->prefix('workspace')->name('workspace.')
    ->group(function () {
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
        // More routes added in later phases
    });
```

**DashboardController:** Simple `index()` returning `Inertia::render('Dashboard', [...])` with placeholder stats.

**Commit:** `feat(tms): register workspace routes and dashboard controller`

---

### Task 15: React Page — Dashboard placeholder

**Files:**
- Create: `resources/js/workspace/pages/Dashboard.jsx`

**Dashboard page:** Summary stat cards (Active Tasks, Completed Today, Overdue, By Department), My Tasks widget (next 5 due), placeholder for activity feed. Uses WorkspaceLayout.

**Step: Build assets**

```bash
npm run build
```

**Step: Verify**

```bash
php artisan serve
# Visit /workspace — should render the dashboard with layout
```

**Commit:** `feat(tms): create workspace Dashboard page`

---

### Task 16: Add sidebar link in admin layout

**Files:**
- Modify: `resources/views/components/layouts/app/sidebar.blade.php`

Add a workspace nav item in the admin sidebar (near Settings):
```blade
<flux:navlist.item icon="clipboard-document-list" href="/workspace">
    {{ __('Workspace') }}
</flux:navlist.item>
```

**Commit:** `feat(tms): add Workspace link to admin sidebar`

---

### Task 17: Seed default badges

**Files:**
- Create: `database/seeders/TmsBadgeSeeder.php`

Seed 8 default badges:
- First Task (criteria: first_task, value: 1, points: 5)
- 10 Tasks Completed (tasks_completed, 10, 20)
- 50 Tasks Completed (tasks_completed, 50, 50)
- 100 Tasks Completed (tasks_completed, 100, 100)
- 7 Day Streak (streak, 7, 30)
- 30 Day Streak (streak, 30, 100)
- Speed Demon — completed within estimate (speed, 1, 15)
- Top Performer — most tasks in a month (tasks_completed, 0, 200)

**Commit:** `feat(tms): seed default TMS badges`

---

## Phase 2: Core CRUD (Tasks 18-29)

Backend controllers + React components for full task management.

| Task | What |
|------|------|
| 18 | TaskController — index, store, show, update, destroy, updateStatus, reorder |
| 19 | SubtaskController — store, update, destroy under parent task |
| 20 | ChecklistController — store, update, toggle, destroy, reorder |
| 21 | AttachmentController — store (upload), destroy, download |
| 22 | Task assign endpoints — assign/unassign users, set leader/member roles |
| 23 | React TaskForm component — create/edit form with all fields |
| 24 | React TaskModal component — quick view/edit overlay |
| 25 | React TaskCard component — kanban card with priority, assignee, due, checklist progress |
| 26 | React ChecklistEditor component — inline add/toggle/reorder/delete |
| 27 | React pages: MyTasks.jsx + TaskDetail.jsx |
| 28 | Tests: TaskController CRUD tests |
| 29 | Tests: ChecklistController + AttachmentController tests |

---

## Phase 3: Views — Dashboard + Kanban + Calendar (Tasks 30-39)

| Task | What |
|------|------|
| 30 | DashboardController — real stats (active, completed today, overdue, by dept) |
| 31 | BoardController — kanban data endpoint (tasks grouped by status, reorder) |
| 32 | CalendarController — tasks by date range |
| 33 | React Dashboard.jsx — full implementation with stat cards + charts |
| 34 | React Board.jsx — Kanban with @dnd-kit drag-drop |
| 35 | React Calendar.jsx — month/week/day views |
| 36 | React FilterPanel component — reusable filter bar |
| 37 | npm install @dnd-kit/core @dnd-kit/sortable |
| 38 | Tests: BoardController reorder tests |
| 39 | Tests: CalendarController date-range tests |

---

## Phase 4: Organization — Departments + Projects (Tasks 40-47)

| Task | What |
|------|------|
| 40 | ProjectController — CRUD + members management |
| 41 | React Projects.jsx — project list with department grouping |
| 42 | React ProjectDetail.jsx — project tasks + board + timeline + members |
| 43 | Sidebar: dynamic department → project tree |
| 44 | Project member invitation flow |
| 45 | Tests: ProjectController CRUD + member tests |
| 46 | Department scoping middleware (managers see own dept only) |
| 47 | Project permission checks (owner/manager vs member/viewer) |

---

## Phase 5: Collaboration — Comments + @Mentions + Activity Log (Tasks 48-55)

| Task | What |
|------|------|
| 48 | CommentController — store, update, destroy |
| 49 | @mention parsing service — extract @usernames from text |
| 50 | ActivityLogController — read-only feed |
| 51 | Task observer — auto-log field changes to activity_logs |
| 52 | React CommentThread component |
| 53 | React MentionInput component — @autocomplete dropdown |
| 54 | React ActivityTimeline component |
| 55 | Tests: Comment + mention + activity log |

---

## Phase 6: Workflow — Recurring + Approval (Tasks 56-61)

| Task | What |
|------|------|
| 56 | RecurringController — CRUD recurring configs |
| 57 | `tms:generate-recurring` artisan command + scheduler |
| 58 | Approval workflow — status machine (draft→review→approved) |
| 59 | React RecurringConfig component |
| 60 | React ApprovalActions component |
| 61 | Tests: Recurring generation + approval flow |

---

## Phase 7: Tracking — Time + KPI + Reports (Tasks 62-71)

| Task | What |
|------|------|
| 62 | TimeEntryController — start, stop, list |
| 63 | KpiController — aggregate queries per staff/dept |
| 64 | ReportController — generate + export PDF/CSV |
| 65 | `tms:calculate-daily-stats` command + scheduler |
| 66 | React TimeTracker component (start/pause/stop in header) |
| 67 | React Kpi.jsx page — staff/dept performance |
| 68 | React Reports.jsx page — generate + download |
| 69 | Tests: TimeEntry start/stop |
| 70 | Tests: KPI aggregation |
| 71 | Tests: Report generation |

---

## Phase 8: Notifications — Reminders + Center (Tasks 72-77)

| Task | What |
|------|------|
| 72 | Laravel Notification classes (TaskAssigned, TaskDueReminder, TaskMentioned, TaskCommented) |
| 73 | `tms:send-reminders` command + scheduler |
| 74 | NotificationController — list, markRead, markAllRead |
| 75 | React NotificationBell component — dropdown feed |
| 76 | Email notification templates |
| 77 | Tests: Notification dispatch + reminder command |

---

## Phase 9: Advanced Views — Gantt + Search + Filters (Tasks 78-85)

| Task | What |
|------|------|
| 78 | GanttController — timeline data with dependencies |
| 79 | Search endpoint — full-text across tasks, comments |
| 80 | React Gantt.jsx page — timeline with dependency arrows |
| 81 | React SearchResults page/modal |
| 82 | Enhanced FilterPanel — save/load filter presets |
| 83 | URL query param persistence for filters |
| 84 | Tests: Gantt data + search |
| 85 | npm install for Gantt library (or custom SVG) |

---

## Phase 10: Access Control (Tasks 86-89)

| Task | What |
|------|------|
| 86 | TMS permission resolver service — maps user role → TMS role |
| 87 | Policy classes: TmsProjectPolicy, TaskPolicy |
| 88 | Middleware permission checks in all controllers |
| 89 | Tests: Permission matrix (admin/manager/leader/staff) |

---

## Phase 11: Gamification (Tasks 90-95)

| Task | What |
|------|------|
| 90 | Points calculation service — award points on task completion |
| 91 | Badge awarding service — check criteria after each task completion |
| 92 | LeaderboardController — monthly/weekly rankings |
| 93 | React Leaderboard.jsx — podium + table |
| 94 | React BadgeDisplay component — on user profile |
| 95 | Tests: Points + badge awarding |

---

## Phase 12: AI Assistant (Tasks 96-100)

| Task | What |
|------|------|
| 96 | AiController — decompose project into tasks, suggest priorities |
| 97 | AI daily summary command — "3 overdue, 5 completed today" |
| 98 | AI weekly report generation |
| 99 | React AiAssistant component — chat widget in sidebar |
| 100 | Tests: AI response formatting |

---

## Phase 13: Integration + PWA (Tasks 101-104)

| Task | What |
|------|------|
| 101 | Google Calendar OAuth2 integration |
| 102 | Push task deadlines to Google Calendar |
| 103 | PWA manifest + service worker for workspace |
| 104 | Tests: Calendar sync + PWA install |

---

## Execution Notes

- **Run `php artisan migrate` after ALL Phase 1 migrations** (Tasks 1-8) to avoid FK ordering issues. Create all migrations first, then run together.
- **Task 1 depends on Task 2 and Task 5** (FK to tms_projects and task_recurring_configs). Ensure migration timestamps order: Task 2 → Task 5 → Task 1.
- **Phase 2+ can be expanded** into detailed step-by-step plans when ready to execute each phase.
- **Tests reference existing patterns** in `tests/Feature/Lms/` and `tests/Feature/Hr/`.
- **React components follow the Fighter app pattern** (`resources/js/fighter/`) for API calls and styling.
