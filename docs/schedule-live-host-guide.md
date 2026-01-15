# Schedule Live Host - User Guide

This guide explains how to use the Schedule Live Host system to manage live streaming schedules for hosts across multiple platform accounts.

---

## Table of Contents

1. [Overview](#overview)
2. [Access & Roles](#access--roles)
3. [Session Slots Configuration](#session-slots-configuration)
4. [Schedule Live Host (Main View)](#schedule-live-host-main-view)
5. [Assigning Hosts to Schedules](#assigning-hosts-to-schedules)
6. [Import & Export](#import--export)
7. [Reports & Analytics](#reports--analytics)
8. [Notifications](#notifications)
9. [Legacy View](#legacy-view)
10. [Troubleshooting](#troubleshooting)

---

## Overview

The Schedule Live Host system allows you to:

- Create and manage weekly recurring schedules for live streaming
- Assign live hosts to specific time slots across multiple platform accounts
- View schedules in a spreadsheet-like format (similar to Excel)
- Import/export schedules via CSV files
- Track schedule coverage and host workload through reports
- Automatically notify hosts when their schedules change

### Key Concepts

| Term | Description |
|------|-------------|
| **Session Slot** | A predefined time period (e.g., 6:30am - 8:30am) that can be used across all days and platforms |
| **Platform Account** | A social media account (e.g., TikTok, Instagram) where live streaming occurs |
| **Live Host** | A user with the `live_host` role who conducts live streaming sessions |
| **Schedule** | An assignment of a host to a specific platform, day, and time slot |

---

## Access & Roles

### Who Can Access

| Role | Access Level |
|------|--------------|
| **Admin** | Full access to all schedule management features |
| **Admin Livehost** | Full access to all schedule management features |
| **Live Host** | Can only view their own assigned schedules (via Live Host Portal) |

### Navigation

Access the schedule management from the sidebar:

1. **Schedule Live Host** - Main schedule view (spreadsheet style)
2. **Session Slots** - Configure available time slots
3. **Legacy Schedules** - Alternative table/calendar view

---

## Session Slots Configuration

Session Slots define the available time periods for scheduling. Configure these before creating schedules.

### Accessing Session Slots

1. Go to **Schedule Live Host** from the sidebar
2. Click the **Session Slots** button in the header

### Default Session Slots

If no slots exist, you can seed default slots by clicking **Seed Default Slots**:

| Slot | Time Range |
|------|------------|
| 1 | 6:30 AM - 8:30 AM |
| 2 | 8:30 AM - 10:30 AM |
| 3 | 10:30 AM - 12:30 PM |
| 4 | 12:30 PM - 2:30 PM |
| 5 | 2:30 PM - 4:30 PM |
| 6 | 5:00 PM - 7:00 PM |
| 7 | 8:00 PM - 10:00 PM |
| 8 | 10:00 PM - 12:00 AM |

### Managing Session Slots

#### Add a New Slot
1. Click **Add Session Slot**
2. Enter **Start Time** (e.g., 14:30)
3. Enter **End Time** (e.g., 16:30)
4. Check **Active** if the slot should be available for scheduling
5. Click **Create**

#### Edit a Slot
1. Click **Edit** next to the slot
2. Modify the times or active status
3. Click **Update**

#### Toggle Active Status
- Click the **Active/Inactive** badge to toggle the slot's availability
- Inactive slots will not appear in the schedule grid

#### Delete a Slot
1. Click **Delete** next to the slot
2. Confirm the deletion

> **Note:** Session slots are automatically sorted by start time. You don't need to manually reorder them.

---

## Schedule Live Host (Main View)

The main schedule view displays all platforms side by side in a spreadsheet format.

### Layout

Each platform account is displayed as a column with:
- **Header**: Platform account name (color-coded)
- **Columns**: Hari (Day), Nama Asatizah (Host Name), Masa (Time), Remark

### Days of Week

Days are displayed in Malay, starting from Saturday:
- SABTU (Saturday)
- AHAD (Sunday)
- ISNIN (Monday)
- SELASA (Tuesday)
- RABU (Wednesday)
- KHAMIS (Thursday)
- JUMAAT (Friday)

### Filtering by Platform

Use the dropdown at the top left to filter by a specific platform account, or select "All Platform Accounts" to view all.

### Host Color Legend

Each host is assigned a unique color for easy identification. The color legend appears at the top right of the page.

---

## Assigning Hosts to Schedules

### Assign a Host

1. Click on any cell in the schedule grid
2. A modal will open showing:
   - **Platform**: The platform account
   - **Day**: The day of the week
   - **Time**: The session slot time
3. Select a host from the **Select Host** dropdown
4. Optionally add **Remarks** (admin notes)
5. Click **Save Assignment**

### Conflict Detection

If you select a host who is already assigned to another platform at the same day and time:

- A **yellow warning box** will appear
- It shows which platform(s) the host is already assigned to
- You can still proceed with the assignment (the system allows double-booking with a warning)

### Clear an Assignment

1. Click on an assigned cell
2. In the modal, click **Clear** to remove the host assignment
3. The host will be notified of the removal

### Update Remarks Only

1. Click on an assigned cell
2. Modify the **Remarks** field
3. Click **Save Assignment**
4. The host will be notified of the update

---

## Import & Export

### Exporting Schedules

1. Click the **Export** button in the header
2. A CSV file will download with the filename `jadual-hostlive-YYYY-MM-DD.csv`

The exported file contains:
| Column | Description |
|--------|-------------|
| Platform | Platform account name |
| Day | Day of week (e.g., SABTU) |
| Time Slot | Time range (e.g., 6:30am - 8:30am) |
| Host Name | Assigned host's name |
| Host Email | Assigned host's email |
| Remarks | Admin remarks |
| Status | Assigned or Unassigned |

### Importing Schedules

1. Click the **Import** button in the header
2. Download the template by clicking **Template** (recommended for first-time use)
3. Fill in the CSV file with your schedule data
4. Click **Choose File** and select your CSV
5. Review the preview table showing matched data
6. Check for any errors in the red error box
7. Click **Import X Schedules** to process

#### CSV Format Requirements

| Column | Required | Notes |
|--------|----------|-------|
| Platform | Yes | Must match an existing platform account name |
| Day | Yes | Use SABTU, AHAD, ISNIN, SELASA, RABU, KHAMIS, JUMAAT (or English equivalents) |
| Time Slot | Yes | Must match an existing session slot (e.g., 6:30am - 8:30am) |
| Host Name | No | Will try to match by name if email not provided |
| Host Email | No | Preferred method for matching hosts |
| Remarks | No | Optional admin notes |

#### Import Tips

- The system uses fuzzy matching for platform names and time slots
- Hosts are matched by email first, then by name
- Rows with errors are skipped and listed in the error section
- Existing schedules are updated (not duplicated)

---

## Reports & Analytics

Access reports by clicking the **Reports** button in the Schedule Live Host header.

### Overall Statistics

Five key metrics displayed at the top:
- **Total Slots**: Maximum possible schedule slots (platforms × days × time slots)
- **Assigned**: Number of slots with hosts assigned
- **Unassigned**: Number of slots without hosts
- **Active Hosts**: Total number of active live hosts
- **Coverage**: Percentage of slots that are assigned

### Host Workload

Shows each host's schedule load:
- **Slots**: Number of assigned schedule slots
- **Hours/Week**: Total streaming hours per week
- **Platforms**: Which platforms they're assigned to

### Platform Coverage

Shows assignment status per platform:
- **Assigned/Total**: Ratio of assigned to total possible slots
- **Coverage %**: Visual progress bar
- Color-coded: Green (≥80%), Yellow (≥50%), Red (<50%)

### Coverage by Day

Bar chart showing which days have the most/least coverage.

### Coverage by Time Slot

Bar chart showing which time slots have the most/least coverage.

### Export Report

Click **Export Report** to download a comprehensive CSV report including all statistics.

---

## Notifications

Hosts are automatically notified when their schedules change.

### Notification Types

| Action | Notification |
|--------|--------------|
| Host assigned to new slot | "You have been assigned to a new live streaming slot" |
| Host removed from slot | "You have been removed from a live streaming slot" |
| Schedule updated (e.g., remarks) | "Your schedule assignment has been updated" |

### Notification Channels

- **Email**: Sent to the host's email address
- **Database**: Stored in the notification center (if implemented)

### Notification Content

Each notification includes:
- Platform name
- Day of week
- Time range
- Link to view their schedule

---

## Legacy View

The Legacy View provides an alternative table-based view of schedules.

### Accessing Legacy View

Click the **Legacy View** button in the Schedule Live Host header.

### Features

- Table format with filtering and sorting
- Search functionality
- Pagination for large datasets
- Quick actions for editing schedules

### When to Use Legacy View

- When you need to search for specific schedules
- When you prefer a list/table format
- For bulk viewing of all schedules across platforms

---

## Troubleshooting

### No Session Slots Appear

**Problem**: The schedule grid is empty or shows a warning.

**Solution**:
1. Go to Session Slots
2. Click "Seed Default Slots" or create slots manually
3. Ensure slots are marked as "Active"

### Platform Not Showing

**Problem**: A platform account doesn't appear in the schedule.

**Solution**:
1. Go to Platform Management
2. Ensure the platform account exists and is marked as "Active"

### Host Not in Dropdown

**Problem**: A host doesn't appear in the assignment dropdown.

**Solution**:
1. Go to Live Host Management
2. Ensure the user has the `live_host` role
3. Ensure the user's status is "Active"

### Import Errors

**Problem**: CSV import shows errors.

**Common Causes**:
- Platform name doesn't match exactly
- Day name is misspelled
- Time slot format doesn't match existing slots
- Host email/name doesn't match any live host

**Solution**:
1. Download the template and follow the exact format
2. Verify platform names match exactly
3. Use the day names in the correct format (SABTU, AHAD, etc.)
4. Check that time slots exist in Session Slots

### Notifications Not Sending

**Problem**: Hosts don't receive notifications.

**Solution**:
1. Ensure queue worker is running: `php artisan queue:listen`
2. Verify host email is correct
3. Check email configuration in `.env`

---

## Quick Reference

### Keyboard Shortcuts

Currently, no keyboard shortcuts are implemented.

### URL Routes

| Page | URL |
|------|-----|
| Schedule Live Host | `/admin/live-schedule-calendar` |
| Session Slots | `/admin/live-time-slots` |
| Reports | `/admin/live-schedule-reports` |
| Legacy View | `/admin/live-schedules` |
| Live Host Management | `/admin/live-hosts` |

### Related Documentation

- [Platform Account Management](#) - Managing TikTok, Instagram, and other platform accounts
- [Live Host Management](#) - Creating and managing live host users
- [Live Sessions](#) - Recording and tracking actual live streaming sessions

---

## Support

For technical issues or feature requests, please contact the system administrator or submit a request through the support channel.

---

*Last Updated: January 2025*
