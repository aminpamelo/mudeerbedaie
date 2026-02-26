# CRM & Automation Enhancement Plan

## Executive Summary

Transform the existing CRM module into an industry-standard Customer Relationship Management and Marketing Automation platform with visual workflow builder, multi-channel communication, and comprehensive student lifecycle management.

---

## Current State vs Target State

| Area | Current | Target |
|------|---------|--------|
| Contact Management | Basic list with filters | Full profile with timeline, tags, notes, tasks |
| Segmentation | Static audiences | Dynamic segments + tags + lead scoring |
| Email | One-time broadcasts | Drip sequences + automation workflows |
| Channels | Email only | Email + SMS + WhatsApp + In-app |
| Automation | Manual triggers | Visual workflow builder with triggers/actions |
| Analytics | Basic sent/failed | Opens, clicks, conversions, revenue attribution |
| Team | Single admin view | Role-based access for 15+ users |

---

## Phase 1: Foundation Enhancement (Week 1-2)
**Priority: HIGH | Effort: Medium**

### 1.1 Contact Tags System

**Database Schema:**
```sql
-- contact_tags table
CREATE TABLE contact_tags (
    id BIGINT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) UNIQUE NOT NULL,
    color VARCHAR(20) DEFAULT 'gray', -- blue, green, red, yellow, purple, etc.
    description TEXT NULL,
    created_by BIGINT REFERENCES users(id),
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);

-- contact_tag_student pivot
CREATE TABLE contact_tag_student (
    id BIGINT PRIMARY KEY,
    tag_id BIGINT REFERENCES contact_tags(id) ON DELETE CASCADE,
    student_id BIGINT REFERENCES students(id) ON DELETE CASCADE,
    tagged_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    tagged_by BIGINT REFERENCES users(id),
    UNIQUE(tag_id, student_id)
);
```

**Features:**
- Create/edit/delete tags with colors
- Bulk tag/untag contacts
- Filter contacts by tags
- Tag-based audience segmentation
- Auto-tag based on actions (purchased, attended, etc.)

**UI Components:**
- Tag manager page
- Tag selector component (reusable)
- Tag badges in contact list

---

### 1.2 Contact Activity Timeline

**Database Schema:**
```sql
-- contact_activities table
CREATE TABLE contact_activities (
    id BIGINT PRIMARY KEY,
    student_id BIGINT REFERENCES students(id) ON DELETE CASCADE,
    activity_type VARCHAR(50) NOT NULL, -- email_sent, email_opened, email_clicked, order_placed, class_attended, note_added, tag_added, etc.
    subject_type VARCHAR(100) NULL, -- App\Models\Broadcast, App\Models\ProductOrder, etc.
    subject_id BIGINT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NULL,
    metadata JSON NULL, -- Additional context data
    performed_by BIGINT REFERENCES users(id) NULL, -- NULL for system actions
    created_at TIMESTAMP,

    INDEX(student_id, created_at DESC),
    INDEX(activity_type)
);
```

**Activity Types:**
- `email_sent` - Email broadcast sent
- `email_opened` - Email opened (tracked)
- `email_clicked` - Link in email clicked
- `order_placed` - New order created
- `order_paid` - Order payment completed
- `class_enrolled` - Enrolled in class
- `class_attended` - Attended class session
- `class_absent` - Missed class session
- `note_added` - Staff added note
- `task_created` - Follow-up task created
- `tag_added` / `tag_removed`
- `audience_joined` / `audience_left`
- `sequence_started` / `sequence_completed`

---

### 1.3 Contact Notes & Tasks

**Database Schema:**
```sql
-- contact_notes table
CREATE TABLE contact_notes (
    id BIGINT PRIMARY KEY,
    student_id BIGINT REFERENCES students(id) ON DELETE CASCADE,
    user_id BIGINT REFERENCES users(id) ON DELETE SET NULL,
    content TEXT NOT NULL,
    is_pinned BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,

    INDEX(student_id, is_pinned, created_at DESC)
);

-- contact_tasks table
CREATE TABLE contact_tasks (
    id BIGINT PRIMARY KEY,
    student_id BIGINT REFERENCES students(id) ON DELETE CASCADE,
    assigned_to BIGINT REFERENCES users(id) ON DELETE SET NULL,
    created_by BIGINT REFERENCES users(id) ON DELETE SET NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NULL,
    due_date DATE NULL,
    due_time TIME NULL,
    priority ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
    status ENUM('pending', 'in_progress', 'completed', 'cancelled') DEFAULT 'pending',
    completed_at TIMESTAMP NULL,
    reminder_at TIMESTAMP NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,

    INDEX(assigned_to, status, due_date),
    INDEX(student_id, status)
);
```

**Features:**
- Add notes to any contact
- Pin important notes
- Create follow-up tasks
- Assign tasks to team members
- Task reminders (email notification)
- Task dashboard for staff

---

### 1.4 Enhanced Email Tracking

**Database Schema:**
```sql
-- Update broadcast_logs table
ALTER TABLE broadcast_logs ADD COLUMN opened_at TIMESTAMP NULL;
ALTER TABLE broadcast_logs ADD COLUMN opened_count INT DEFAULT 0;
ALTER TABLE broadcast_logs ADD COLUMN clicked_at TIMESTAMP NULL;
ALTER TABLE broadcast_logs ADD COLUMN clicked_count INT DEFAULT 0;
ALTER TABLE broadcast_logs ADD COLUMN unsubscribed_at TIMESTAMP NULL;
ALTER TABLE broadcast_logs ADD COLUMN bounced_at TIMESTAMP NULL;
ALTER TABLE broadcast_logs ADD COLUMN bounce_type VARCHAR(50) NULL; -- hard, soft

-- email_link_clicks table (detailed click tracking)
CREATE TABLE email_link_clicks (
    id BIGINT PRIMARY KEY,
    broadcast_log_id BIGINT REFERENCES broadcast_logs(id) ON DELETE CASCADE,
    student_id BIGINT REFERENCES students(id) ON DELETE CASCADE,
    url TEXT NOT NULL,
    clicked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    user_agent TEXT NULL,
    ip_address VARCHAR(45) NULL,

    INDEX(broadcast_log_id),
    INDEX(student_id, clicked_at DESC)
);

-- email_unsubscribes table
CREATE TABLE email_unsubscribes (
    id BIGINT PRIMARY KEY,
    student_id BIGINT REFERENCES students(id) ON DELETE CASCADE,
    email VARCHAR(255) NOT NULL,
    reason VARCHAR(100) NULL,
    feedback TEXT NULL,
    unsubscribed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    UNIQUE(email),
    INDEX(student_id)
);
```

**Features:**
- Tracking pixel for opens
- Link rewriting for click tracking
- Unsubscribe link in all emails
- Bounce handling via webhook
- Per-email analytics

---

## Phase 2: Automation Workflow Builder (Week 3-5)
**Priority: HIGH | Effort: High**

### 2.1 Core Automation System

**Database Schema:**
```sql
-- automation_workflows table
CREATE TABLE automation_workflows (
    id BIGINT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT NULL,
    status ENUM('draft', 'active', 'paused', 'archived') DEFAULT 'draft',
    trigger_type VARCHAR(100) NOT NULL, -- See trigger types below
    trigger_conditions JSON NULL, -- Conditions for the trigger
    entry_settings JSON NULL, -- Re-entry rules, etc.
    created_by BIGINT REFERENCES users(id),
    activated_at TIMESTAMP NULL,
    total_enrolled INT DEFAULT 0,
    total_completed INT DEFAULT 0,
    total_converted INT DEFAULT 0,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,

    INDEX(status),
    INDEX(trigger_type)
);

-- automation_steps table
CREATE TABLE automation_steps (
    id BIGINT PRIMARY KEY,
    workflow_id BIGINT REFERENCES automation_workflows(id) ON DELETE CASCADE,
    parent_step_id BIGINT REFERENCES automation_steps(id) ON DELETE CASCADE NULL,
    branch_type ENUM('main', 'yes', 'no') DEFAULT 'main', -- For conditional branches
    step_order INT NOT NULL,
    step_type VARCHAR(50) NOT NULL, -- See step types below
    step_config JSON NOT NULL, -- Configuration for the step
    created_at TIMESTAMP,
    updated_at TIMESTAMP,

    INDEX(workflow_id, step_order)
);

-- automation_enrollments table (contacts in workflow)
CREATE TABLE automation_enrollments (
    id BIGINT PRIMARY KEY,
    workflow_id BIGINT REFERENCES automation_workflows(id) ON DELETE CASCADE,
    student_id BIGINT REFERENCES students(id) ON DELETE CASCADE,
    current_step_id BIGINT REFERENCES automation_steps(id) ON DELETE SET NULL,
    status ENUM('active', 'completed', 'paused', 'exited', 'failed') DEFAULT 'active',
    enrolled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    next_action_at TIMESTAMP NULL, -- When to process next step
    completed_at TIMESTAMP NULL,
    exit_reason VARCHAR(255) NULL,
    metadata JSON NULL, -- Track conversion, etc.

    UNIQUE(workflow_id, student_id),
    INDEX(status, next_action_at),
    INDEX(student_id)
);

-- automation_step_logs table
CREATE TABLE automation_step_logs (
    id BIGINT PRIMARY KEY,
    enrollment_id BIGINT REFERENCES automation_enrollments(id) ON DELETE CASCADE,
    step_id BIGINT REFERENCES automation_steps(id) ON DELETE CASCADE,
    status ENUM('pending', 'processing', 'completed', 'failed', 'skipped') DEFAULT 'pending',
    executed_at TIMESTAMP NULL,
    result JSON NULL, -- Step execution result
    error_message TEXT NULL,
    created_at TIMESTAMP,

    INDEX(enrollment_id, created_at DESC)
);
```

### 2.2 Trigger Types

| Trigger | Description | Configuration |
|---------|-------------|---------------|
| `student_created` | New student signs up | filters: country, source |
| `order_placed` | Student places order | filters: product, min_amount |
| `order_paid` | Payment completed | filters: product, amount |
| `order_abandoned` | Cart abandoned (X hours) | delay: hours |
| `class_enrolled` | Enrolled in class | filters: class, course |
| `class_attended` | Attended session | filters: class |
| `class_missed` | Missed session | filters: class, consecutive |
| `tag_added` | Tag applied to contact | filters: tag_id |
| `tag_removed` | Tag removed | filters: tag_id |
| `audience_joined` | Added to audience | filters: audience_id |
| `email_opened` | Opened specific email | filters: broadcast_id |
| `email_clicked` | Clicked link in email | filters: broadcast_id, url |
| `date_based` | Specific date/time | date, time, repeat |
| `lead_score_reached` | Score threshold met | min_score |
| `days_inactive` | No activity for X days | days: number |
| `birthday` | Contact's birthday | days_before: 0 |
| `enrollment_anniversary` | Anniversary date | years: number |
| `manual` | Manually enrolled | - |

### 2.3 Action Types (Steps)

| Action | Description | Configuration |
|--------|-------------|---------------|
| **Communication** |||
| `send_email` | Send email | template_id, subject, content |
| `send_sms` | Send SMS | message, sender_id |
| `send_whatsapp` | Send WhatsApp | template_id, message |
| `send_notification` | In-app notification | title, message, url |
| **Timing** |||
| `wait_duration` | Wait X time | duration, unit (minutes/hours/days) |
| `wait_until` | Wait until date/time | date, time |
| `wait_for_event` | Wait for action | event_type, timeout |
| **Segmentation** |||
| `add_tag` | Add tag to contact | tag_id |
| `remove_tag` | Remove tag | tag_id |
| `add_to_audience` | Add to audience | audience_id |
| `remove_from_audience` | Remove from audience | audience_id |
| `update_field` | Update contact field | field, value |
| **Lead Management** |||
| `update_lead_score` | Add/subtract points | points, operation |
| `assign_owner` | Assign to team member | user_id or round_robin |
| `create_task` | Create follow-up task | title, due_date, assignee |
| **Flow Control** |||
| `condition` | If/Then branch | conditions (AND/OR), yes_steps, no_steps |
| `ab_split` | A/B test split | variants[], percentages[] |
| `go_to_step` | Jump to step | step_id |
| `exit_workflow` | End workflow | reason |
| **Integration** |||
| `webhook` | Call external URL | url, method, headers, body |
| `add_to_workflow` | Enroll in another workflow | workflow_id |
| `remove_from_workflow` | Remove from workflow | workflow_id |

### 2.4 Condition Builder

```json
{
  "match": "all", // or "any"
  "conditions": [
    {
      "field": "lead_score",
      "operator": "greater_than",
      "value": 50
    },
    {
      "field": "tags",
      "operator": "contains",
      "value": "interested"
    },
    {
      "field": "orders_count",
      "operator": "equals",
      "value": 0
    },
    {
      "field": "last_email_opened",
      "operator": "is_set",
      "value": true
    }
  ]
}
```

**Available Operators:**
- `equals`, `not_equals`
- `greater_than`, `less_than`, `between`
- `contains`, `not_contains`
- `starts_with`, `ends_with`
- `is_set`, `is_not_set`
- `in_list`, `not_in_list`
- `before`, `after` (dates)

### 2.5 Visual Workflow Builder UI

**Technology:**
- React Flow or Vue Flow for drag-and-drop canvas
- Real-time preview
- Step configuration modals

**UI Components:**
1. **Workflow List** - All workflows with status, stats
2. **Workflow Editor** - Visual canvas builder
3. **Step Palette** - Draggable step types
4. **Step Config Modal** - Configure each step
5. **Condition Builder** - Visual condition editor
6. **Enrollment Stats** - Live enrollment data
7. **Activity Log** - Per-contact journey view

---

## Phase 3: Lead Scoring System (Week 6)
**Priority: MEDIUM | Effort: Medium**

### 3.1 Lead Scoring Schema

```sql
-- lead_score_rules table
CREATE TABLE lead_score_rules (
    id BIGINT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT NULL,
    trigger_type VARCHAR(100) NOT NULL, -- Same as automation triggers
    trigger_conditions JSON NULL,
    points INT NOT NULL, -- Can be negative
    is_active BOOLEAN DEFAULT TRUE,
    max_occurrences INT NULL, -- NULL = unlimited
    decay_days INT NULL, -- Points expire after X days
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);

-- lead_scores table (current score per student)
CREATE TABLE lead_scores (
    id BIGINT PRIMARY KEY,
    student_id BIGINT UNIQUE REFERENCES students(id) ON DELETE CASCADE,
    score INT DEFAULT 0,
    grade ENUM('cold', 'warm', 'hot', 'qualified') DEFAULT 'cold',
    last_activity_at TIMESTAMP NULL,
    last_calculated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX(score DESC),
    INDEX(grade)
);

-- lead_score_history table
CREATE TABLE lead_score_history (
    id BIGINT PRIMARY KEY,
    student_id BIGINT REFERENCES students(id) ON DELETE CASCADE,
    rule_id BIGINT REFERENCES lead_score_rules(id) ON DELETE SET NULL,
    points INT NOT NULL,
    reason VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NULL,

    INDEX(student_id, created_at DESC)
);
```

### 3.2 Default Scoring Rules

| Action | Points | Category |
|--------|--------|----------|
| Opens email | +5 | Engagement |
| Clicks email link | +10 | Engagement |
| Visits pricing page | +15 | Intent |
| Downloads resource | +10 | Interest |
| Submits inquiry form | +20 | Intent |
| Attends free class | +25 | Engagement |
| Purchases course | +50 | Conversion |
| Refers a friend | +30 | Advocacy |
| Completes course | +40 | Success |
| Inactive 14 days | -5 | Decay |
| Inactive 30 days | -15 | Decay |
| Unsubscribes | -50 | Negative |

### 3.3 Grade Thresholds

| Grade | Score Range | Description |
|-------|-------------|-------------|
| Cold | 0-25 | New or inactive lead |
| Warm | 26-50 | Showing interest |
| Hot | 51-75 | High intent, ready to buy |
| Qualified | 76+ | Sales-ready, priority contact |

---

## Phase 4: Multi-Channel Communication (Week 7-8)
**Priority: HIGH | Effort: High**

### 4.1 SMS Integration

**Provider Options:**
- Twilio (recommended)
- MessageBird
- Vonage

**Database Schema:**
```sql
-- sms_messages table
CREATE TABLE sms_messages (
    id BIGINT PRIMARY KEY,
    student_id BIGINT REFERENCES students(id) ON DELETE CASCADE,
    phone_number VARCHAR(20) NOT NULL,
    message TEXT NOT NULL,
    direction ENUM('outbound', 'inbound') DEFAULT 'outbound',
    status ENUM('pending', 'sent', 'delivered', 'failed') DEFAULT 'pending',
    provider VARCHAR(50) NOT NULL,
    provider_message_id VARCHAR(255) NULL,
    cost DECIMAL(10,4) NULL,
    sent_at TIMESTAMP NULL,
    delivered_at TIMESTAMP NULL,
    error_message TEXT NULL,
    metadata JSON NULL,
    created_at TIMESTAMP,

    INDEX(student_id, created_at DESC),
    INDEX(status)
);

-- sms_templates table
CREATE TABLE sms_templates (
    id BIGINT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    content TEXT NOT NULL, -- Max 160 chars recommended
    merge_tags JSON NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

### 4.2 WhatsApp Integration

**Provider:** WhatsApp Business API via:
- Twilio
- MessageBird
- 360dialog
- Meta Cloud API (direct)

**Database Schema:**
```sql
-- whatsapp_messages table
CREATE TABLE whatsapp_messages (
    id BIGINT PRIMARY KEY,
    student_id BIGINT REFERENCES students(id) ON DELETE CASCADE,
    phone_number VARCHAR(20) NOT NULL,
    message_type ENUM('text', 'template', 'media') NOT NULL,
    template_name VARCHAR(255) NULL,
    content TEXT NULL,
    media_url TEXT NULL,
    direction ENUM('outbound', 'inbound') DEFAULT 'outbound',
    status ENUM('pending', 'sent', 'delivered', 'read', 'failed') DEFAULT 'pending',
    provider_message_id VARCHAR(255) NULL,
    sent_at TIMESTAMP NULL,
    delivered_at TIMESTAMP NULL,
    read_at TIMESTAMP NULL,
    error_message TEXT NULL,
    metadata JSON NULL,
    created_at TIMESTAMP,

    INDEX(student_id, created_at DESC),
    INDEX(status)
);

-- whatsapp_templates table (pre-approved templates)
CREATE TABLE whatsapp_templates (
    id BIGINT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    language VARCHAR(10) DEFAULT 'en',
    category ENUM('marketing', 'utility', 'authentication') NOT NULL,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    header_type ENUM('none', 'text', 'image', 'video', 'document') DEFAULT 'none',
    header_content TEXT NULL,
    body_content TEXT NOT NULL,
    footer_content TEXT NULL,
    buttons JSON NULL,
    provider_template_id VARCHAR(255) NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

### 4.3 In-App Notifications

**Database Schema:**
```sql
-- notifications table (use Laravel's built-in)
-- Extended with custom fields

CREATE TABLE user_notifications (
    id BIGINT PRIMARY KEY,
    user_id BIGINT REFERENCES users(id) ON DELETE CASCADE,
    type VARCHAR(255) NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    action_url TEXT NULL,
    icon VARCHAR(50) NULL,
    read_at TIMESTAMP NULL,
    created_at TIMESTAMP,

    INDEX(user_id, read_at, created_at DESC)
);
```

### 4.4 Communication Preferences

```sql
-- communication_preferences table
CREATE TABLE communication_preferences (
    id BIGINT PRIMARY KEY,
    student_id BIGINT UNIQUE REFERENCES students(id) ON DELETE CASCADE,
    email_enabled BOOLEAN DEFAULT TRUE,
    sms_enabled BOOLEAN DEFAULT TRUE,
    whatsapp_enabled BOOLEAN DEFAULT TRUE,
    push_enabled BOOLEAN DEFAULT TRUE,
    marketing_emails BOOLEAN DEFAULT TRUE,
    transactional_emails BOOLEAN DEFAULT TRUE,
    class_reminders BOOLEAN DEFAULT TRUE,
    payment_reminders BOOLEAN DEFAULT TRUE,
    preferred_channel ENUM('email', 'sms', 'whatsapp') DEFAULT 'email',
    quiet_hours_start TIME NULL,
    quiet_hours_end TIME NULL,
    updated_at TIMESTAMP
);
```

---

## Phase 5: Advanced Analytics Dashboard (Week 9-10)
**Priority: MEDIUM | Effort: Medium**

### 5.1 CRM Dashboard Metrics

**Overview Cards:**
- Total Contacts (with growth %)
- Active Contacts (30 days)
- Total Revenue (attributed)
- Conversion Rate

**Charts:**
- Contact growth over time
- Revenue by source/campaign
- Engagement trends
- Top performing workflows

### 5.2 Campaign Analytics

**Email Metrics:**
- Delivery rate
- Open rate
- Click-through rate (CTR)
- Unsubscribe rate
- Bounce rate
- Revenue attributed

**Workflow Metrics:**
- Total enrolled
- Currently active
- Completion rate
- Conversion rate
- Exit points analysis

### 5.3 Contact Analytics

**Individual Contact View:**
- Engagement score trend
- Email interaction history
- Website activity (if tracked)
- Purchase history
- Predicted lifetime value

---

## Phase 6: Team & Access Management (Week 11)
**Priority: MEDIUM | Effort: Medium**

### 6.1 CRM Roles & Permissions

**Roles:**
- `crm_admin` - Full access to all CRM features
- `crm_manager` - Manage workflows, campaigns, view all contacts
- `crm_agent` - View assigned contacts, add notes/tasks
- `crm_viewer` - Read-only access to reports

**Permissions:**
```php
// CRM Permissions
'crm.contacts.view'
'crm.contacts.edit'
'crm.contacts.delete'
'crm.contacts.export'

'crm.tags.manage'
'crm.audiences.manage'

'crm.broadcasts.view'
'crm.broadcasts.create'
'crm.broadcasts.send'

'crm.workflows.view'
'crm.workflows.create'
'crm.workflows.activate'

'crm.analytics.view'
'crm.settings.manage'
```

### 6.2 Contact Ownership

```sql
-- Add to students table
ALTER TABLE students ADD COLUMN owner_id BIGINT REFERENCES users(id) ON DELETE SET NULL;
ALTER TABLE students ADD COLUMN team_id BIGINT REFERENCES teams(id) ON DELETE SET NULL;
```

**Features:**
- Assign contacts to team members
- Round-robin assignment
- Territory management
- Owner-based filtering

---

## Phase 7: Additional Features (Week 12+)
**Priority: LOW | Effort: Varies**

### 7.1 Email Template Builder
- Drag-and-drop email editor
- Pre-built templates
- Mobile preview
- Dynamic content blocks

### 7.2 Landing Page Builder
- Simple page builder
- Form integration
- A/B testing
- Analytics

### 7.3 Forms & Surveys
- Custom form builder
- Survey creation
- Response tracking
- Automation triggers

### 7.4 Reporting & Export
- Custom report builder
- Scheduled reports
- Export to PDF/Excel
- Dashboard sharing

---

## Technical Architecture

### Background Jobs

```
app/Jobs/
├── Automation/
│   ├── ProcessWorkflowEnrollments.php  (runs every minute)
│   ├── ExecuteWorkflowStep.php
│   ├── CheckTriggerConditions.php
│   └── CalculateLeadScores.php
├── Communication/
│   ├── SendEmail.php
│   ├── SendSms.php
│   ├── SendWhatsApp.php
│   └── SendNotification.php
└── Analytics/
    ├── TrackEmailOpen.php
    ├── TrackLinkClick.php
    └── SyncAnalytics.php
```

### Events & Listeners

```
app/Events/
├── ContactCreated.php
├── ContactUpdated.php
├── OrderPlaced.php
├── OrderPaid.php
├── ClassEnrolled.php
├── ClassAttended.php
├── EmailOpened.php
├── EmailClicked.php
└── LeadScoreChanged.php

app/Listeners/
├── TriggerAutomations.php
├── UpdateLeadScore.php
├── LogContactActivity.php
└── SendNotifications.php
```

### API Endpoints (for tracking)

```
/api/track/email/open/{tracking_id}     # Pixel tracking
/api/track/email/click/{tracking_id}    # Link redirect
/api/track/unsubscribe/{token}          # Unsubscribe
/api/webhooks/email/{provider}          # Provider webhooks
/api/webhooks/sms/{provider}            # SMS status updates
/api/webhooks/whatsapp/{provider}       # WhatsApp webhooks
```

---

## Implementation Timeline

| Week | Phase | Deliverables |
|------|-------|--------------|
| 1-2 | Foundation | Tags, Timeline, Notes, Tasks, Email Tracking |
| 3-5 | Automation Builder | Workflow schema, Triggers, Actions, Visual UI |
| 6 | Lead Scoring | Scoring rules, Grades, History |
| 7-8 | Multi-Channel | SMS, WhatsApp, Notifications, Preferences |
| 9-10 | Analytics | Dashboard, Reports, Metrics |
| 11 | Team Management | Roles, Permissions, Ownership |
| 12+ | Advanced | Templates, Landing Pages, Forms |

---

## Success Metrics

| Metric | Target |
|--------|--------|
| Email Open Rate | > 25% |
| Click-Through Rate | > 5% |
| Automation Completion Rate | > 70% |
| Lead-to-Customer Conversion | > 15% |
| Customer Retention Rate | > 85% |
| Response Time (Tasks) | < 24 hours |

---

## Next Steps

1. **Review this plan** and provide feedback
2. **Prioritize phases** based on immediate needs
3. **Start with Phase 1** - Foundation (tags, timeline, notes)
4. **Parallel work** on Phase 2 - Automation Builder design

---

*Document Version: 1.0*
*Created: January 2026*
*Last Updated: January 2026*
