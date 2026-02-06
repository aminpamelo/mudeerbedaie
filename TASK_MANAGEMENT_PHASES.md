# Task Management Module - Development Phases

## Overview
Module Task Management untuk Mudeer Bedaie platform dengan Kanban board, parent-child department hierarchy, dan role-based access control.

### Department Structure
```
Affiliate (top-level, parent)
├── Recruit Affiliate (sub-department)
├── KPI Content Creator (sub-department)
└── Content Staff (sub-department)
Designer (top-level)
```

### Admin Role
- Admin does NOT have a dedicated department
- Admin uses "My Tasks" page for personal task management
- Admin can only VIEW (read-only) all department tasks (Affiliate, sub-departments, Designer)
- Admin CANNOT create, edit, or delete tasks in any department
- Admin creates personal tasks (no department) via "New Task" button - displayed in My Tasks only
- Admin manages department members via manage-members page

---

## Phase 1 - Foundation (COMPLETED)

### Features Implemented
- **Database Schema**: departments (with parent_id), department_users, tasks, task_comments, task_activity_logs tables
- **Enums**: TaskStatus (todo, in_progress, review, completed, cancelled), TaskType (kpi, adhoc), TaskPriority (low, medium, high, urgent), DepartmentRole (department_pic, member)
- **Models**: Department (with parent-child hierarchy), Task, TaskComment, TaskActivityLog
- **User Roles**: `pic_department` and `member_department` added to users.role enum
- **Kanban Board**: Drag-and-drop task cards across 4 columns (TODO, In Progress, Review, Completed)
- **Task CRUD**: Create, view, edit, delete tasks with full form fields
- **Comments**: Threaded comments with reply support
- **Activity Logs**: Full audit trail for all task changes
- **Task List View**: Table view with sorting, search, and filters
- **My Tasks**: Personal task view with filters, Add Task button for PICs
- **Dashboard**: ClickUp-style dashboard with stats, workspaces (parent-child hierarchy), urgent/overdue tasks
- **Department Settings**: Add/remove members, change roles
- **Manage Members**: Admin-level member management across departments
- **Notifications**: TaskAssignedNotification (email + database), TaskCommentNotification (database)
- **Observer**: TaskObserver for auto-notifications on assignment
- **Parent-Child Departments**: `parent_id` on departments table, sidebar and dashboard show hierarchy
- **Parent PIC Inheritance**: PIC of parent department automatically manages all sub-departments
- **Manage Members**: Only top-level departments shown; sub-departments inherit members from parent

### Access Control
| Role | View Depts | Create in Dept | Edit in Dept | Delete in Dept | Personal Tasks | Manage Members |
|------|-----------|----------------|-------------|---------------|----------------|----------------|
| Admin | All Depts (VIEW ONLY) | No | No | No | Yes (My Tasks) | Yes (manage-members) |
| PIC Department | Own Dept + Sub-depts | Yes | Yes | Yes | No | Yes |
| Member Department | Own Dept + Sub-depts | No | Yes | No | No | No |

### Files Created
- `app/Models/Department.php`, `Task.php`, `TaskComment.php`, `TaskActivityLog.php`
- `app/Enums/TaskStatus.php`, `TaskType.php`, `TaskPriority.php`, `DepartmentRole.php`
- `app/Notifications/TaskAssignedNotification.php`, `TaskCommentNotification.php`
- `app/Observers/TaskObserver.php`
- `database/migrations/` - 5 migration files (departments, department_users, tasks, task_comments, task_activity_logs) + `add_parent_id_to_departments_table`
- `database/seeders/DepartmentSeeder.php`
- `resources/views/livewire/tasks/` - dashboard, my-tasks, kanban-board, task-list, task-create, task-show, task-edit, department-settings, manage-members

---

## Phase 2 - Enhanced Productivity (COMPLETED)

### Features Implemented
1. **Task Attachments** - Upload files (images, PDFs, documents) to tasks
2. **Due Date Reminders** - Auto email/notification 24h before due date + overdue alerts
3. **My Tasks Improvements** - Today's focus section, grouped views, better UX
4. **Task Templates** - Reusable templates for recurring KPI/Adhoc tasks

### Task Attachments
- New `task_attachments` table: id, task_id, user_id, filename, original_name, mime_type, size, disk, path
- Upload via task-show page with `WithFileUploads` trait
- Support images, PDFs, documents (max 10MB per file)
- File type icons (image/PDF/generic), size display, uploader info
- Download link for all files, PIC can delete attachments
- Auto-deletes file from storage when model is deleted

### Due Date Reminders
- `TaskDueReminderNotification` - Email + database notification 24h before due
- `TaskOverdueNotification` - Email + database notification when task becomes overdue
- `tasks:send-reminders` artisan command scheduled daily at 08:00
- Tracks `reminder_sent_at` and `overdue_notified_at` to avoid duplicates

### My Tasks Improvements
- **Focus/List toggle** - Two view modes switchable via header buttons
- **Focus View**: Quick stats cards (Today's Focus, In Progress, To Do, Done 7d), Today's Focus section (overdue + due today tasks), Upcoming This Week, All Active Tasks grouped by status (3 columns: In Progress, To Do, Review)
- **List View**: Original table with search, filters (status, priority, type), sorting, pagination
- Better empty states with icons

### Task Templates
- New `task_templates` table: id, department_id, created_by, name, description, task_type, priority, estimated_hours, template_data (JSON)
- **Save as Template**: Button on task-show page opens modal to save current task config as template
- **Load from Template**: Dropdown on task-create page pre-fills form fields (title, description, type, priority, estimated hours)
- **Template Management**: Section in department-settings to create/delete templates with full form (name, description, default values)

### Files Created
- `database/migrations/2026_01_29_000001_create_task_attachments_table.php`
- `database/migrations/2026_01_29_000002_create_task_templates_table.php`
- `database/migrations/2026_01_29_000003_add_reminder_sent_at_to_tasks_table.php`
- `app/Models/TaskAttachment.php`
- `app/Models/TaskTemplate.php`
- `app/Notifications/TaskDueReminderNotification.php`
- `app/Notifications/TaskOverdueNotification.php`
- `app/Console/Commands/SendTaskReminders.php`

### Files Modified
- `app/Models/Task.php` - Added `reminder_sent_at`, `overdue_notified_at`, `attachments()` relationship, `isDueSoon()`, `isDueToday()`
- `resources/views/livewire/tasks/task-show.blade.php` - Attachments section, Save as Template modal
- `resources/views/livewire/tasks/task-create.blade.php` - Load from Template dropdown
- `resources/views/livewire/tasks/my-tasks.blade.php` - Focus/List dual view with stats and grouped tasks
- `resources/views/livewire/tasks/department-settings.blade.php` - Task Templates management section
- `routes/console.php` - Scheduled `tasks:send-reminders` command

---

## Phase 3 - Advanced Features (PLANNED)

### Features
1. **Subtasks/Checklist** - Break tasks into smaller items with progress tracking
2. **Task Dependencies** - Mark tasks as blocked by other tasks
3. **Time Tracking** - Log hours spent on tasks with timer UI
4. **Recurring Tasks** - Auto-create tasks on schedule (daily, weekly, monthly)
5. **Department Analytics** - Charts for completion rate, productivity, trends

### Subtasks/Checklist
- New `task_checklists` table: id, task_id, title, is_completed, sort_order, completed_by, completed_at
- Progress bar on task cards (e.g., "3/5 completed")
- Quick toggle checkbox UI
- Activity log for checklist changes

### Task Dependencies
- New `task_dependencies` table: id, task_id, depends_on_task_id, dependency_type (blocks, blocked_by)
- Visual indicators on blocked tasks
- Warning when completing a task that blocks others
- Dependency chain view

### Time Tracking
- New `task_time_entries` table: id, task_id, user_id, started_at, ended_at, duration_minutes, description
- Start/stop timer on task detail page
- Manual time entry
- Time reports per member/task/department

### Recurring Tasks
- New fields on tasks or separate `task_recurring_schedules` table
- Frequency: daily, weekly, monthly, custom
- Auto-create new task when previous is completed or on schedule
- Template-based recurring tasks

### Department Analytics
- Task completion rate (daily, weekly, monthly)
- Average task duration
- Member productivity metrics
- Overdue trends chart
- KPI vs Adhoc distribution
- Export reports as PDF/CSV

---

## Phase 4 - Collaboration & Polish (PLANNED)

### Features
1. **@Mentions in Comments** - Tag team members with notifications
2. **Task Labels/Tags** - Custom color-coded labels
3. **Calendar View** - Tasks on calendar by due date
4. **Bulk Actions** - Multi-select tasks for batch operations
5. **Activity Feed** - Department-wide activity stream
6. **Task Cloning** - Duplicate task with same settings
7. **Saved Filters** - Save and reuse filter presets
8. **Export Tasks** - CSV/Excel export for task lists

### @Mentions
- Parse `@username` in comments
- Notify mentioned users
- Highlight mentions in comment display
- Autocomplete dropdown when typing `@`

### Task Labels/Tags
- New `task_labels` table: id, department_id, name, color
- New `task_label_task` pivot table
- Multiple labels per task
- Filter by labels in kanban/list views

### Calendar View
- New Volt component: `tasks/calendar.blade.php`
- Monthly/weekly view
- Color-coded by priority
- Click to view task detail
- Drag to reschedule (PIC only)

### Bulk Actions
- Checkbox selection in list view
- Bulk status change
- Bulk reassign
- Bulk delete (PIC only)
- Select all / deselect all

---

## Phase 5 - Enterprise Features (FUTURE)

### Features
1. **Task SLA/Escalation** - Auto-escalate overdue tasks
2. **Custom Fields** - User-defined task properties
3. **Workflow Automation** - Auto-assign, auto-status rules
4. **API Integration** - REST API for external tools
5. **Notification Preferences** - Per-user notification settings
6. **Task Import** - Import tasks from CSV/Excel
7. **Mobile Optimization** - PWA support, mobile-specific views
8. **Task Archive** - Archive completed tasks for cleaner views

---

## Technical Notes

### Department Hierarchy
- Departments support parent-child relationships via `parent_id` column
- `Department::topLevel()` scope returns root departments (parent_id = null)
- `Department::children()` relationship returns sub-departments ordered by sort_order
- Sidebar and dashboard render hierarchy: parents show children indented below
- Admin sees all top-level departments with children; non-admin sees only assigned departments

### Department Structure (Current)
| Department | Type | Parent | Color | Slug |
|-----------|------|--------|-------|------|
| Affiliate | Top-level (parent) | — | Blue | `affiliate` |
| Recruit Affiliate | Sub-department | Affiliate | Cyan | `recruit-affiliate` |
| KPI Content Creator | Sub-department | Affiliate | Violet | `kpi-content-creator` |
| Content Staff | Sub-department | Affiliate | Pink | `content-staff` |
| Designer | Top-level | — | Amber | `designer` |

### User Role System
Two role systems work together:
- `users.role` (account type): `admin`, `pic_department`, `member_department`
- `department_users.role` (department role): `department_pic`, `member`

### Role Mapping
| Department Role (pivot) | User Account Role |
|------------------------|-------------------|
| `department_pic` | `pic_department` |
| `member` | `member_department` |

### Admin Access
- Admin does NOT have a dedicated department
- Admin can only VIEW (read-only) tasks in all departments
- Admin CANNOT create, edit, or delete tasks in any department
- Admin creates personal tasks (department_id = null) via "New Task" button
- Admin personal tasks are displayed in "My Tasks" page only
- Admin can manage members across all departments via manage-members page

### Database
- SQLite (development) - requires drop/recreate for enum changes
- MySQL/MariaDB (production) - supports ALTER for enum changes

### Key Routes
```
/tasks                              → Dashboard
/tasks/my-tasks                     → Personal tasks
/tasks/manage-members               → Admin member management
/tasks/department/{slug}            → Kanban board
/tasks/department/{slug}/list       → List view
/tasks/department/{slug}/settings   → Department settings
/tasks/create                       → Create task
/tasks/{task}                       → Task detail
/tasks/{task}/edit                  → Edit task
```

### Bug Fixes Applied
- Fixed `TaskStatus::InProgress` → `TaskStatus::IN_PROGRESS` (enum cases are uppercase)
- Fixed admin dashboard hero text (shows overview message instead of personal task count)
- Added "Add Task" button on My Tasks page for users who are PIC of any department
- Added `metadata` property to task-create for workflow data from templates
