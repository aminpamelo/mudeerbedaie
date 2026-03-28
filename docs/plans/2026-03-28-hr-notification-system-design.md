# HR Notification System Design

**Date:** 2026-03-28
**Status:** Approved
**Approach:** Laravel Notifications (database + mail + webpush channels)

## Overview

A role-based notification system for the Mudeer HR module delivering **34 notifications** across 6 modules via two channels:

- **PWA Push Notifications** — real-time alerts via service worker (already set up)
- **Email Notifications** — immediate delivery via Laravel Mail

Each notification class defines its channels via the `via()` method (push-only, email-only, or both) based on event type.

## Decisions

| Decision | Choice | Reasoning |
|----------|--------|-----------|
| Recipients | Role-based only | Admin/HR gets management notifications, employees get personal ones. No per-user preference UI needed. |
| Email timing | Immediate | Events are time-sensitive (approvals, payroll). No daily digest. |
| Channel strategy | Per event type | Some events push-only (reminders), some email-only (payslips), some both (approvals). |
| Architecture | Laravel Notifications | Native pattern, single class per notification, `via()` controls channels, testable. |

## Architecture

### Channels

1. **Database** — All notifications stored in Laravel's `notifications` table for in-app notification center
2. **Mail** — Laravel Mail with queued delivery (`ShouldQueue`)
3. **WebPush** — `laravel-notification-channels/webpush` package for PWA push via existing service worker

### Notification Flow

```
Event occurs (e.g., leave approved)
  → Dispatch Laravel Notification
    → via() determines channels [database, mail, webpush]
      → Database: stored for in-app bell icon
      → Mail: queued email sent immediately
      → WebPush: push sent to subscribed devices
```

### Key Components

- **Notification classes** — One class per event in `app/Notifications/Hr/`
- **Notifiable trait** — Already on User/Employee models
- **Push subscription** — Employee subscribes via PWA, stored in `push_subscriptions` table (webpush package)
- **Service worker** — Existing `public/sw.js` already handles push events

## Notification Map (34 total)

### Channel Legend

- **Push** = PWA push notification only
- **Email** = Email only
- **Both** = Push + Email
- All notifications are also stored in the database channel for in-app notification center

---

### Module 1: Leave Management (7 notifications)

| # | Notification Class | Event | Recipient | Channel |
|---|-------------------|-------|-----------|---------|
| 1 | `LeaveRequestSubmitted` | Employee submits leave | Dept Approver | Both |
| 2 | `LeaveRequestApproved` | Leave approved | Employee | Both |
| 3 | `LeaveRequestRejected` | Leave rejected | Employee | Both |
| 4 | `LeaveRequestCancelled` | Employee cancels leave | Approver | Push |
| 5 | `LeaveBalanceLow` | Balance below threshold | Employee | Push |
| 6 | `UpcomingLeaveReminder` | Leave starts tomorrow | Employee | Push |
| 7 | `TeamLeaveAlert` | Daily team leave summary | Manager/Admin | Push |

### Module 2: Attendance & Time Tracking (8 notifications)

| # | Notification Class | Event | Recipient | Channel |
|---|-------------------|-------|-----------|---------|
| 8 | `ClockInReminder` | Shift start approaching | Employee | Push |
| 9 | `ClockOutReminder` | Shift end approaching | Employee | Push |
| 10 | `LateClockInAlert` | Employee clocked in late | Admin/Manager | Push |
| 11 | `MissedClockIn` | No clock-in recorded | Employee | Both |
| 12 | `OvertimeRequestSubmitted` | OT request submitted | Approver | Both |
| 13 | `OvertimeRequestDecision` | OT approved/rejected | Employee | Push |
| 14 | `ScheduleChanged` | Work schedule updated | Employee | Both |
| 15 | `AttendancePenaltyFlagged` | Repeated late attendance | Employee | Push |

### Module 3: Payroll (6 notifications)

| # | Notification Class | Event | Recipient | Channel |
|---|-------------------|-------|-----------|---------|
| 16 | `PayrollRunCreated` | New payroll run | Admin/HR | Push |
| 17 | `PayrollSubmittedForReview` | Payroll needs review | Reviewer/Admin | Both |
| 18 | `PayrollApproved` | Payroll approved | Submitting Admin | Push |
| 19 | `PayrollFinalized` | Payroll finalized | All Employees | Both |
| 20 | `PayslipReady` | Payslip available | Employee | Email |
| 21 | `SalaryRevision` | Salary updated | Employee | Email |

### Module 4: Claims (6 notifications)

| # | Notification Class | Event | Recipient | Channel |
|---|-------------------|-------|-----------|---------|
| 22 | `ClaimSubmitted` | Claim submitted | Approver | Both |
| 23 | `ClaimApproved` | Claim approved | Employee | Both |
| 24 | `ClaimRejected` | Claim rejected | Employee | Both |
| 25 | `ClaimMarkedPaid` | Claim payment processed | Employee | Push |
| 26 | `ClaimPendingReminder` | Pending claims reminder | Approver | Push |
| 27 | `ClaimExpiringSoon` | Receipt expiring | Employee | Push |

### Module 5: Assets (3 notifications)

| # | Notification Class | Event | Recipient | Channel |
|---|-------------------|-------|-----------|---------|
| 28 | `AssetAssigned` | Asset assigned to employee | Employee | Both |
| 29 | `AssetReturnRequested` | Return deadline approaching | Employee | Push |
| 30 | `AssetReturnConfirmed` | Asset returned | Admin | Push |

### Module 6: Employee Management (4 notifications)

| # | Notification Class | Event | Recipient | Channel |
|---|-------------------|-------|-----------|---------|
| 31 | `WelcomeOnboarding` | New employee created | Employee | Email |
| 32 | `ProbationEnding` | Probation period ending | Admin/Manager | Both |
| 33 | `DocumentExpiring` | Document expires soon | Employee + Admin | Both |
| 34 | `EmploymentStatusChanged` | Status change (confirmed, etc.) | Employee | Email |

## Channel Distribution Summary

| Module | Count | Push Only | Email Only | Both |
|--------|-------|-----------|------------|------|
| Leave | 7 | 4 | 0 | 3 |
| Attendance | 8 | 4 | 0 | 4 |
| Payroll | 6 | 2 | 2 | 2 |
| Claims | 6 | 2 | 0 | 4 |
| Assets | 3 | 2 | 0 | 1 |
| Employee | 4 | 0 | 2 | 2 |
| **Total** | **34** | **14** | **4** | **16** |

## Technical Implementation Notes

### Package Required

```bash
composer require laravel-notification-channels/webpush
```

### Database Tables

- `notifications` — Laravel's built-in (already exists via framework)
- `push_subscriptions` — From webpush package (new migration)

### Notification Class Structure

```php
namespace App\Notifications\Hr;

use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class LeaveRequestSubmitted extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public LeaveRequest $leaveRequest
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail', WebPushChannel::class]; // "Both" channel
    }

    public function toMail(object $notifiable): MailMessage { ... }
    public function toWebPush(object $notifiable): WebPushMessage { ... }
    public function toArray(object $notifiable): array { ... }
}
```

### Push-Only Example

```php
public function via(object $notifiable): array
{
    return ['database', WebPushChannel::class]; // Push only, no mail
}
```

### Email-Only Example

```php
public function via(object $notifiable): array
{
    return ['database', 'mail']; // Email only, no push
}
```

### Service Worker Integration

The existing `public/sw.js` already handles push events. The webpush package sends standard Web Push Protocol messages that the service worker receives via:

```javascript
self.addEventListener('push', function(event) {
    const data = event.data.json();
    self.registration.showNotification(data.title, {
        body: data.body,
        icon: data.icon,
        data: data.data // URL to navigate on click
    });
});
```

### React In-App Notification Center

A notification bell icon in `HrLayout.jsx` that:
- Fetches unread notifications from `GET /api/hr/notifications`
- Shows notification count badge
- Dropdown with notification list
- Mark as read on click
- "Mark all as read" action

### Scheduled Notifications (Cron)

Some notifications are triggered by scheduled commands, not user actions:

| Notification | Schedule | Command |
|-------------|----------|---------|
| ClockInReminder | Daily, before shift start | `hr:send-clock-reminders` |
| ClockOutReminder | Daily, before shift end | `hr:send-clock-reminders` |
| TeamLeaveAlert | Daily 8:00 AM | `hr:send-team-leave-alerts` |
| LeaveBalanceLow | Weekly Monday | `hr:check-leave-balances` |
| ClaimPendingReminder | Daily 9:00 AM | `hr:remind-pending-claims` |
| DocumentExpiring | Daily | `hr:check-expiring-documents` |
| ProbationEnding | Daily | `hr:check-probation-endings` |
| AssetReturnRequested | Daily | `hr:check-asset-returns` |
| ClaimExpiringSoon | Daily | `hr:check-expiring-claims` |

### VAPID Keys

Web Push requires VAPID (Voluntary Application Server Identification) keys. Generated once and stored in `.env`:

```
VAPID_PUBLIC_KEY=...
VAPID_PRIVATE_KEY=...
```

## Implementation Phases

### Phase 1: Foundation
- Install webpush package, run migrations
- Generate VAPID keys
- Add push subscription API endpoints
- Add notification API endpoints (list, mark read)
- Build notification bell UI component in HrLayout

### Phase 2: Event-Triggered Notifications (24 notifications)
- Leave: #1-4 (request submitted, approved, rejected, cancelled)
- Attendance: #11-14 (missed clock-in, overtime submitted/decision, schedule changed)
- Payroll: #16-21 (all payroll notifications)
- Claims: #22-25 (submitted, approved, rejected, paid)
- Assets: #28, #30 (assigned, return confirmed)
- Employee: #31, #34 (welcome, status changed)

### Phase 3: Scheduled Notifications (10 notifications)
- Leave: #5-7 (balance low, upcoming reminder, team alert)
- Attendance: #8-10, #15 (clock reminders, late alert, penalty)
- Claims: #26-27 (pending reminder, expiring)
- Assets: #29 (return requested)
- Employee: #32-33 (probation ending, document expiring)
