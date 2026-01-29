# Task Management Module - Implementation Plan

## Overview

Module terasing untuk Task Management dengan Kanban board design, 4 departments, dan role PIC Department.

---

## Scope Fasa 1

### Features
- **Basic + Comments**: Create, assign, status, due date, priority, comments/discussion
- **Kanban Board**: Drag-and-drop task cards
- **4 Departments**: Affiliate, Editor, Content Creator, Designer
- **2 Task Types**: KPI, Adhoc
- **Notifications**: Email + In-App

### Access Control
| Role | View | Create | Edit | Delete | Manage PIC |
|------|------|--------|------|--------|------------|
| Admin | All Depts (READ-ONLY) | No | No | No | No |
| Department PIC | Own Dept | Yes | Yes | Yes | Yes |
| Member | Own Dept | No | No | No | No |

---

## Database Schema

### Tables (5)

```
departments
├── id, name, slug, description, color, icon, status, sort_order
└── timestamps

department_users (pivot)
├── department_id, user_id
├── role (enum: department_pic, member)
└── assigned_by, timestamps

tasks
├── id, task_number (unique, e.g. TASK-20260127-ABCD)
├── department_id, title, description
├── task_type (enum: kpi, adhoc)
├── status (enum: todo, in_progress, review, completed, cancelled)
├── priority (enum: low, medium, high, urgent)
├── assigned_to, created_by, due_date, due_time
├── started_at, completed_at, cancelled_at
├── estimated_hours, actual_hours, sort_order, metadata (json)
└── timestamps

task_comments
├── id, task_id, user_id, content
├── parent_id (for replies), is_internal
└── timestamps

task_activity_logs
├── id, task_id, user_id, action
├── old_value (json), new_value (json), description
└── created_at
```

### Enums (4)
- `TaskStatus`: todo, in_progress, review, completed, cancelled
- `TaskType`: kpi, adhoc
- `TaskPriority`: low, medium, high, urgent
- `DepartmentRole`: department_pic, member

---

## Models

### New Models (4)
1. `app/Models/Department.php`
2. `app/Models/Task.php`
3. `app/Models/TaskComment.php`
4. `app/Models/TaskActivityLog.php`

### User Model Updates
Add ke `app/Models/User.php`:
- `departments()` - BelongsToMany
- `picDepartments()` - BelongsToMany (filtered)
- `assignedTasks()` - HasMany
- `isDepartmentPic()` - bool
- `isPicOf(Department)` - bool
- `canManageTasks(Department)` - bool
- `canViewTasks(Department)` - bool

---

## Livewire Components

Lokasi: `resources/views/livewire/tasks/`

| Component | Purpose |
|-----------|---------|
| `dashboard.blade.php` | Overview semua departments |
| `my-tasks.blade.php` | Personal task view |
| `kanban-board.blade.php` | Main Kanban board |
| `task-list.blade.php` | Alternative list view |
| `task-create.blade.php` | Create task modal |
| `task-show.blade.php` | Task detail + comments |
| `task-edit.blade.php` | Edit task form |
| `department-settings.blade.php` | Manage PICs |

---

## Routes

```php
Route::middleware(['auth'])->prefix('tasks')->name('tasks.')->group(function () {
    Volt::route('/', 'tasks.dashboard')->name('dashboard');
    Volt::route('my-tasks', 'tasks.my-tasks')->name('my-tasks');
    Volt::route('department/{department:slug}', 'tasks.kanban-board')->name('department.board');
    Volt::route('department/{department:slug}/list', 'tasks.task-list')->name('department.list');
    Volt::route('department/{department:slug}/settings', 'tasks.department-settings')->name('department.settings');
    Volt::route('create', 'tasks.task-create')->name('create');
    Volt::route('{task}', 'tasks.task-show')->name('show');
    Volt::route('{task}/edit', 'tasks.task-edit')->name('edit');
});
```

---

## Navigation

Tambah section "Task Management" dalam sidebar:
- Dashboard (overview)
- My Tasks (personal)
- Department links (4):
  - Affiliate
  - Editor
  - Content Creator
  - Designer

---

## Notifications

| Event | Recipients | Channels |
|-------|------------|----------|
| Task Created | Assignee | Email, Database |
| Task Assigned | Assignee | Email, Database |
| Task Status Changed | Creator, Assignee | Database |
| Task Due Soon (24h) | Assignee | Email, Database |
| Task Overdue | Assignee, PICs | Email, Database |
| Comment Added | Creator, Assignee | Database |

---

## Files to Create

### Enums (4 files)
- `app/Enums/TaskStatus.php`
- `app/Enums/TaskType.php`
- `app/Enums/TaskPriority.php`
- `app/Enums/DepartmentRole.php`

### Models (4 files)
- `app/Models/Department.php`
- `app/Models/Task.php`
- `app/Models/TaskComment.php`
- `app/Models/TaskActivityLog.php`

### Migrations (5 files)
- `create_departments_table`
- `create_department_users_table`
- `create_tasks_table`
- `create_task_comments_table`
- `create_task_activity_logs_table`

### Notifications (3 files)
- `app/Notifications/TaskAssignedNotification.php`
- `app/Notifications/TaskCommentNotification.php`
- `app/Notifications/TaskDueReminderNotification.php`

### Observer
- `app/Observers/TaskObserver.php`

### Livewire Components (8 files)
- `resources/views/livewire/tasks/dashboard.blade.php`
- `resources/views/livewire/tasks/my-tasks.blade.php`
- `resources/views/livewire/tasks/kanban-board.blade.php`
- `resources/views/livewire/tasks/task-list.blade.php`
- `resources/views/livewire/tasks/task-create.blade.php`
- `resources/views/livewire/tasks/task-show.blade.php`
- `resources/views/livewire/tasks/task-edit.blade.php`
- `resources/views/livewire/tasks/department-settings.blade.php`

### Seeder
- `database/seeders/DepartmentSeeder.php`

### Factories (3 files)
- `database/factories/DepartmentFactory.php`
- `database/factories/TaskFactory.php`
- `database/factories/TaskCommentFactory.php`

---

## Files to Modify

1. **`app/Models/User.php`**
   - Add department relationships
   - Add permission methods

2. **`routes/web.php`**
   - Add task management routes

3. **`resources/views/components/layouts/app/sidebar.blade.php`**
   - Add Task Management navigation section

4. **`app/Providers/AppServiceProvider.php`**
   - Register TaskObserver

---

## Implementation Order

### Step 1: Database Foundation
1. Create 4 Enum classes
2. Create 5 migrations
3. Run migrations
4. Create DepartmentSeeder
5. Run seeder

### Step 2: Models
1. Create Department model
2. Create Task model
3. Create TaskComment model
4. Create TaskActivityLog model
5. Update User model dengan relationships baru

### Step 3: Routes & Navigation
1. Add routes ke web.php
2. Update sidebar navigation

### Step 4: Core Components
1. Create kanban-board.blade.php
2. Create task-create.blade.php
3. Create task-show.blade.php (dengan comments)
4. Create task-edit.blade.php

### Step 5: Supporting Components
1. Create dashboard.blade.php
2. Create my-tasks.blade.php
3. Create task-list.blade.php
4. Create department-settings.blade.php

### Step 6: Notifications
1. Create TaskObserver
2. Register observer in AppServiceProvider
3. Create notification classes
4. Test notification flow

### Step 7: Testing & Polish
1. Create factories
2. Write feature tests
3. UI polish

---

## Verification Plan

1. **Database**: Run `php artisan migrate` - should create 5 tables
2. **Seeder**: Run `php artisan db:seed --class=DepartmentSeeder` - should create 4 departments
3. **Routes**: Visit `/tasks` - should show dashboard
4. **Kanban**: Visit `/tasks/department/affiliate` - should show Kanban board
5. **Create Task**: Click "+ Add Task" - should open create form
6. **Comments**: Add comment on task - should appear immediately
7. **Drag & Drop**: Drag task card to new column - should update status
8. **Notifications**: Assign task - assignee should receive notification
9. **Access Control**: Login as admin - should see all departments but READ-ONLY

---

## UI Reference

Based on TaskFlow screenshots:
- Clean sidebar navigation with department links
- Kanban columns: TODO, In Progress, Review, Completed (4 columns)
- Task cards with: title, priority badge, due date, assignee avatar, comment count
- Purple/violet color scheme for accents
- Quick search and filter options
- "+ Add Task" button in each column (for PICs only)

---

## Kanban Board Layout

```
+-------------------------------------------------------------------+
| Department: [Dropdown Selector]  | Filter: [Type] [Assignee]      |
| [Search...]                      | View: [Kanban] [List]          |
+-------------------------------------------------------------------+
|                                                                    |
| +---------------+ +---------------+ +---------------+ +----------+ |
| | TODO (5)      | | IN PROGRESS   | | REVIEW (2)    | | DONE     | |
| +---------------+ +---------------+ +---------------+ +----------+ |
| | +-----------+ | | +-----------+ | | +-----------+ | |          | |
| | | Task Card | | | | Task Card | | | | Task Card | | |          | |
| | | - Title   | | | | - Title   | | | | - Title   | | |          | |
| | | - Priority| | | | - Priority| | | | - Priority| | |          | |
| | | - Due     | | | | - Due     | | | | - Due     | | |          | |
| | | - Assignee| | | | - Assignee| | | | - Assignee| | |          | |
| | +-----------+ | | +-----------+ | | +-----------+ | |          | |
| |               | |               | |               | |          | |
| | [+ Add Task]  | |               | |               | |          | |
| +---------------+ +---------------+ +---------------+ +----------+ |
+-------------------------------------------------------------------+
```

---

## Task Card Design

```blade
<div class="bg-white rounded-lg shadow-sm border p-4 cursor-move hover:shadow-md">
    <!-- Header: Priority + Type badges -->
    <div class="flex justify-between mb-2">
        <flux:badge color="red">Urgent</flux:badge>
        <flux:badge variant="outline">KPI</flux:badge>
    </div>

    <!-- Title -->
    <h4 class="font-medium mb-2 line-clamp-2">Task title here</h4>

    <!-- Due date -->
    <div class="flex items-center text-sm text-zinc-500 mb-3">
        <flux:icon name="calendar" class="w-4 h-4 mr-1" />
        27 Jan 2026
    </div>

    <!-- Footer: Assignee + Comments -->
    <div class="flex justify-between border-t pt-2">
        <div class="flex items-center">
            <flux:avatar size="xs" name="John Doe" />
            <span class="ml-2 text-xs">John Doe</span>
        </div>
        <div class="flex items-center text-zinc-400">
            <flux:icon name="chat-bubble-left" class="w-4 h-4 mr-1" />
            <span class="text-xs">5</span>
        </div>
    </div>
</div>
```

---

## Notes

- Cancelled tasks tidak ada column sendiri, hanya status dalam database
- Drag & drop menggunakan Alpine.js dengan SortableJS
- Real-time updates menggunakan Livewire events
- Admin boleh view semua tapi READ-ONLY (tidak boleh create/edit/delete)
