# HR Module: Minutes of Meeting (MOM)

**Date:** 2026-03-28
**Status:** Approved
**Module Location:** Inside HR system at `/hr/meetings`

## Overview

A Minutes of Meeting (MOM) module for recording, managing, and tracking internal staff meetings. Includes attendance tracking, decision logging, task management with deadlines, meeting series grouping, and AI-powered meeting analysis via recording transcription.

## Key Decisions

- **Architecture:** Approach B — Shared Task System with polymorphic `tasks` table reusable across future modules
- **Access:** Any employee can create meetings; admin/HR has full oversight of all meetings
- **Meeting Roles:** Organizer, Note Taker, Attendees
- **Attendance:** Note Taker marks who attended/absent during or after the meeting
- **Decisions:** Simple log (title, description, decided by, date)
- **Tasks:** Full management — title, assignee, deadline, priority, status, subtasks, attachments, comments
- **Series:** Meetings can be linked to a series for grouping (manually created, no auto-recurrence)
- **AI:** Google Cloud Speech-to-Text for transcription + Google Gemini for summary/action item extraction
- **Recording:** Browser-based live recording (MediaRecorder API) + file upload option
- **Notifications:** Full — meeting created/updated, task assigned, deadline approaching, meeting reminder, AI analysis complete

---

## Database Schema

### Core Meeting Tables

```
meetings
├── id
├── meeting_series_id (nullable FK → meeting_series)
├── title (string)
├── description (text, nullable)
├── location (string, nullable — room name, online link, etc.)
├── meeting_date (date)
├── start_time (time)
├── end_time (time, nullable)
├── status (enum: draft, scheduled, in_progress, completed, cancelled)
├── organizer_id (FK → employees)
├── note_taker_id (FK → employees, nullable)
├── created_by (FK → users)
├── timestamps + soft_deletes

meeting_series
├── id
├── name (string — e.g., "Weekly Standup", "Monthly Review")
├── description (text, nullable)
├── created_by (FK → users)
├── timestamps

meeting_attendees
├── id
├── meeting_id (FK → meetings)
├── employee_id (FK → employees)
├── role (enum: organizer, note_taker, attendee)
├── attendance_status (enum: invited, attended, absent, excused)
├── timestamps

meeting_agenda_items
├── id
├── meeting_id (FK → meetings)
├── title (string)
├── description (text, nullable)
├── sort_order (integer)
├── timestamps

meeting_decisions
├── id
├── meeting_id (FK → meetings)
├── agenda_item_id (FK → meeting_agenda_items, nullable)
├── title (string)
├── description (text)
├── decided_by (FK → employees)
├── decided_at (datetime)
├── timestamps

meeting_attachments
├── id
├── meeting_id (FK → meetings)
├── file_name (string)
├── file_path (string)
├── file_size (integer, bytes)
├── file_type (string, mime type)
├── uploaded_by (FK → employees)
├── timestamps
```

### Recording & AI Tables

```
meeting_recordings
├── id
├── meeting_id (FK → meetings)
├── file_name (string)
├── file_path (string)
├── file_size (integer)
├── file_type (string — audio/video mime)
├── duration_seconds (integer, nullable)
├── source (enum: browser_recording, uploaded)
├── uploaded_by (FK → employees)
├── timestamps

meeting_transcripts
├── id
├── meeting_id (FK → meetings)
├── recording_id (FK → meeting_recordings)
├── content (longText — full transcript)
├── language (string, default: 'en')
├── status (enum: processing, completed, failed)
├── processed_at (datetime, nullable)
├── timestamps

meeting_ai_summaries
├── id
├── meeting_id (FK → meetings)
├── transcript_id (FK → meeting_transcripts)
├── summary (text — executive summary)
├── key_points (json — array of key discussion points)
├── suggested_tasks (json — AI-extracted action items, pending review)
├── status (enum: processing, completed, reviewed, failed)
├── reviewed_by (FK → employees, nullable)
├── reviewed_at (datetime, nullable)
├── timestamps
```

### Shared Task System (Polymorphic)

```
tasks
├── id
├── taskable_type (string — "App\Models\Meeting", or future modules)
├── taskable_id (integer)
├── parent_id (nullable FK → tasks, for subtasks)
├── title (string)
├── description (text, nullable)
├── assigned_to (FK → employees)
├── assigned_by (FK → employees)
├── priority (enum: low, medium, high, urgent)
├── status (enum: pending, in_progress, completed, cancelled)
├── deadline (date)
├── completed_at (datetime, nullable)
├── timestamps + soft_deletes

task_comments
├── id
├── task_id (FK → tasks)
├── employee_id (FK → employees)
├── content (text)
├── timestamps

task_attachments
├── id
├── task_id (FK → tasks)
├── file_name (string)
├── file_path (string)
├── file_size (integer, bytes)
├── file_type (string, mime type)
├── uploaded_by (FK → employees)
├── timestamps
```

---

## Frontend Pages

All pages live under the existing HR React SPA (`/resources/js/hr/`).

### Admin/HR Pages (HrLayout)

| Route | Page | Description |
|-------|------|-------------|
| `/hr/meetings` | MeetingList | All meetings with filters (date, status, series). Tabs: Upcoming, Past, Draft, All |
| `/hr/meetings/create` | MeetingCreate | Form: title, description, date/time, location, series, attendees, note taker, agenda |
| `/hr/meetings/:id` | MeetingDetail | Full MOM view: attendees, agenda, decisions, tasks, recording, transcript, AI summary |
| `/hr/meetings/:id/edit` | MeetingEdit | Edit meeting details |
| `/hr/meetings/:id/record` | MeetingRecord | Browser-based audio recorder with start/stop/pause |
| `/hr/meetings/series` | MeetingSeriesList | List and manage meeting series |
| `/hr/meetings/tasks` | TaskDashboard | All tasks across meetings. Filter by status, assignee, priority, deadline |

### Employee Self-Service Pages (EmployeeAppLayout)

| Route | Page | Description |
|-------|------|-------------|
| `/hr/my/meetings` | MyMeetings | Meetings the employee is part of |
| `/hr/my/tasks` | MyTasks | Tasks assigned to the employee |

---

## API Endpoints

All endpoints under `/api/hr/` with `auth:sanctum` + `role:admin,employee` middleware.

### Meetings

```
GET    /api/hr/meetings                     → List meetings (filterable, paginated)
POST   /api/hr/meetings                     → Create meeting
GET    /api/hr/meetings/{id}                → Get meeting detail (full MOM)
PUT    /api/hr/meetings/{id}                → Update meeting
DELETE /api/hr/meetings/{id}                → Delete meeting (soft)
PATCH  /api/hr/meetings/{id}/status         → Update status (start/complete/cancel)
```

### Meeting Series

```
GET    /api/hr/meetings/series              → List all series
POST   /api/hr/meetings/series              → Create series
GET    /api/hr/meetings/series/{id}         → Get series with its meetings
```

### Attendees

```
POST   /api/hr/meetings/{id}/attendees             → Add attendees
DELETE /api/hr/meetings/{id}/attendees/{employeeId} → Remove attendee
PATCH  /api/hr/meetings/{id}/attendees/{employeeId} → Update attendance status
```

### Agenda Items

```
POST   /api/hr/meetings/{id}/agenda-items           → Add agenda item
PUT    /api/hr/meetings/{id}/agenda-items/{itemId}   → Update
DELETE /api/hr/meetings/{id}/agenda-items/{itemId}   → Delete
PATCH  /api/hr/meetings/{id}/agenda-items/reorder    → Reorder items
```

### Decisions

```
POST   /api/hr/meetings/{id}/decisions               → Add decision
PUT    /api/hr/meetings/{id}/decisions/{decId}        → Update
DELETE /api/hr/meetings/{id}/decisions/{decId}        → Delete
```

### Attachments

```
POST   /api/hr/meetings/{id}/attachments             → Upload attachment
DELETE /api/hr/meetings/{id}/attachments/{attId}      → Delete
```

### Recording & AI

```
POST   /api/hr/meetings/{id}/recordings                        → Upload recording
DELETE /api/hr/meetings/{id}/recordings/{recId}                 → Delete recording
POST   /api/hr/meetings/{id}/recordings/{recId}/transcribe     → Trigger transcription
GET    /api/hr/meetings/{id}/transcript                         → Get transcript
POST   /api/hr/meetings/{id}/ai-analyze                        → Trigger AI analysis
GET    /api/hr/meetings/{id}/ai-summary                        → Get AI summary
POST   /api/hr/meetings/{id}/ai-summary/approve-tasks          → Approve suggested tasks
```

### Shared Tasks

```
GET    /api/hr/tasks                        → List all tasks (cross-meeting, filterable)
GET    /api/hr/tasks/{id}                   → Get task detail
POST   /api/hr/meetings/{id}/tasks          → Create task for a meeting
PUT    /api/hr/tasks/{id}                   → Update task
PATCH  /api/hr/tasks/{id}/status            → Update task status
DELETE /api/hr/tasks/{id}                   → Delete task
POST   /api/hr/tasks/{id}/subtasks          → Add subtask
POST   /api/hr/tasks/{id}/comments          → Add comment
POST   /api/hr/tasks/{id}/attachments       → Upload attachment
```

### Employee Self-Service

```
GET    /api/hr/my/meetings                  → My meetings
GET    /api/hr/my/tasks                     → My tasks
```

---

## AI Processing Flow

```
1. User records audio in browser (MediaRecorder API) or uploads a file
2. Recording saved to storage, entry created in meeting_recordings
3. User triggers transcription → queued job
4. Job sends audio to Google Cloud Speech-to-Text API
5. Transcript saved to meeting_transcripts (status: completed)
6. User triggers AI analysis → queued job
7. Job sends transcript to Google Gemini API with prompt:
   - Generate executive summary
   - Extract key discussion points
   - Identify action items (task title, suggested assignee if mentioned, deadline if mentioned)
8. AI summary saved to meeting_ai_summaries (status: completed)
9. Note taker reviews suggested tasks → approves/edits/rejects each
10. Approved tasks created in shared tasks table
11. Notification sent to organizer & note taker when AI analysis completes
```

---

## Notifications

| Event | Channels | Recipients |
|-------|----------|------------|
| Meeting invitation | in-app, email, push | All attendees |
| Meeting updated | in-app, push | All attendees |
| Meeting cancelled | in-app, email, push | All attendees |
| Meeting reminder (30 min before) | push | All attendees |
| Task assigned | in-app, email, push | Assignee |
| Task deadline approaching (1 day before) | in-app, push | Assignee |
| AI analysis completed | in-app, push | Organizer + Note Taker |

---

## Backend Structure

### Models (app/Models/)
- `Meeting`, `MeetingSeries`, `MeetingAttendee`, `MeetingAgendaItem`
- `MeetingDecision`, `MeetingAttachment`, `MeetingRecording`
- `MeetingTranscript`, `MeetingAiSummary`
- `Task`, `TaskComment`, `TaskAttachment` (shared/polymorphic)

### Controllers (app/Http/Controllers/Api/Hr/)
- `HrMeetingController`
- `HrMeetingSeriesController`
- `HrMeetingAttendeeController`
- `HrMeetingAgendaController`
- `HrMeetingDecisionController`
- `HrMeetingAttachmentController`
- `HrMeetingRecordingController`
- `HrMeetingAiController`
- `HrTaskController`

### Services (app/Services/Hr/)
- `MeetingTranscriptionService` — handles Google Cloud Speech-to-Text
- `MeetingAiAnalysisService` — handles Google Gemini analysis

### Jobs (app/Jobs/Hr/)
- `TranscribeMeetingRecording` — queued job for transcription
- `AnalyzeMeetingTranscript` — queued job for AI analysis

### Form Requests (app/Http/Requests/Hr/)
- `StoreMeetingRequest`, `UpdateMeetingRequest`
- `StoreMeetingSeriesRequest`
- `StoreAgendaItemRequest`
- `StoreDecisionRequest`
- `StoreTaskRequest`, `UpdateTaskRequest`

### Notifications (app/Notifications/Hr/)
- `MeetingInvitationNotification`
- `MeetingUpdatedNotification`
- `MeetingCancelledNotification`
- `MeetingReminderNotification`
- `TaskAssignedNotification`
- `TaskDeadlineApproachingNotification`
- `AiAnalysisCompletedNotification`

### Frontend (resources/js/hr/)
- `pages/meetings/` — MeetingList, MeetingCreate, MeetingDetail, MeetingEdit, MeetingRecord
- `pages/meetings/MeetingSeriesList.jsx`
- `pages/meetings/TaskDashboard.jsx`
- `pages/my/MyMeetings.jsx`
- `pages/my/MyTasks.jsx`
- `components/meetings/` — reusable components (AttendeeList, AgendaEditor, DecisionLog, RecordingPlayer, TranscriptViewer, AiSummaryPanel, TaskList)
- `lib/api.js` — add meeting & task API functions
