# Live Host Management Module

## Overview
Complete live streaming management system for TikTok, YouTube, Facebook, and other platforms. Hosts can schedule streams, track analytics, and manage sessions without API integration (manual tracking).

---

## ✅ What's Been Implemented

### 1. Database & Models (100% Complete)

#### New Roles
- ✅ `live_host` - User role for streamers
- ✅ `admin_livehost` - Admin role for managing live streaming

#### New Tables
- ✅ `live_schedules` - Weekly timetable for when hosts stream
- ✅ `live_sessions` - Individual streaming sessions
- ✅ `live_analytics` - Performance metrics per session

#### Models Created
- ✅ `LiveSchedule` - Full model with relationships and helpers
- ✅ `LiveSession` - Full model with status management (scheduled/live/ended/cancelled)
- ✅ `LiveAnalytics` - Analytics model with engagement rate calculations

#### Relationships Added
- ✅ User → platformAccounts, liveSessions
- ✅ PlatformAccount → liveSchedules, liveSessions
- ✅ LiveSchedule → platformAccount, liveSessions
- ✅ LiveSession → platformAccount, liveSchedule, analytics
- ✅ LiveAnalytics → liveSession

### 2. Admin Pages

#### Created
- ✅ `/admin/live-hosts` - List all live hosts with stats
  - Search by name, email, phone
  - Filter by status
  - Shows platform accounts count and total sessions
  - Stats cards (total hosts, active, platform accounts, today's sessions)

#### Files Created
- `resources/views/livewire/admin/live-hosts-list.blade.php`
- `resources/views/livewire/admin/live-schedules-index.blade.php` (stub)
- `resources/views/livewire/admin/live-schedules-create.blade.php` (stub)
- `resources/views/livewire/admin/live-schedules-edit.blade.php` (stub)

---

## 🚧 What Needs to Be Completed

### 1. Routes (Critical)
Add to `routes/web.php`:

```php
// Admin Livehost Routes
Route::middleware(['auth', 'role:admin,admin_livehost'])->prefix('admin')->name('admin.')->group(function () {
    // Live Hosts
    Volt::route('live-hosts', 'admin.live-hosts-list')->name('live-hosts');

    // Live Schedules
    Volt::route('live-schedules', 'admin.live-schedules-index')->name('live-schedules.index');
    Volt::route('live-schedules/create', 'admin.live-schedules-create')->name('live-schedules.create');
    Volt::route('live-schedules/{schedule}/edit', 'admin.live-schedules-edit')->name('live-schedules.edit');

    // Live Sessions
    Volt::route('live-sessions', 'admin.live-sessions-index')->name('live-sessions.index');
    Volt::route('live-sessions/{session}', 'admin.live-sessions-show')->name('live-sessions.show');
});

// Live Host Routes
Route::middleware(['auth', 'role:live_host'])->prefix('live-host')->name('live-host.')->group(function () {
    Volt::route('dashboard', 'live-host.dashboard')->name('dashboard');
    Volt::route('schedule', 'live-host.schedule')->name('schedule');
    Volt::route('sessions', 'live-host.sessions-index')->name('sessions.index');
    Volt::route('sessions/{session}', 'live-host.sessions-show')->name('sessions.show');
});

// Public/Student Routes
Volt::route('live/schedule', 'live.schedule-public')->name('live.schedule');
```

### 2. Complete Admin Pages

#### Live Schedules Index
- List all schedules in weekly calendar view
- Filter by platform, host, day
- Quick actions (edit, delete, toggle active)

#### Live Schedules Create/Edit
- Form to create weekly schedule
- Select platform account (dropdown)
- Select day of week
- Time range picker
- Recurring checkbox

#### Live Sessions Index
- List all sessions (upcoming, live, past)
- Filter by status, platform, host, date
- Quick actions (view, cancel)
- Export to CSV

#### Live Sessions Show
- Session details
- Start/End live buttons
- Analytics entry form
- Timeline visualization

### 3. Live Host Pages

#### Dashboard
```php
// Create: resources/views/livewire/live-host/dashboard.blade.php
- Upcoming sessions (next 7 days)
- Quick stats (total sessions, avg viewers, total hours)
- Recent analytics
- Platform accounts list
```

#### Schedule View
```php
// Create: resources/views/livewire/live-host/schedule.blade.php
- Weekly calendar showing their scheduled streams
- Filter by platform
- Shows time slots and platform
```

#### Sessions List
```php
// Create: resources/views/livewire/live-host/sessions-index.blade.php
- List their sessions (upcoming, past)
- Filter by status, date range
- Quick actions (prepare, start, end)
```

#### Session Detail
```php
// Create: resources/views/livewire/live-host/sessions-show.blade.php
- Session info
- Status management buttons:
  - "Prepare" (edit title/description before live)
  - "Start Live" (changes status to 'live')
  - "End Live" (changes status to 'ended')
- Analytics entry form (after ended):
  - Viewers peak/avg
  - Likes, comments, shares
  - Gifts value
  - Auto-calculate duration
```

### 4. Auto-Generate Sessions Command

```php
// Create: app/Console/Commands/GenerateLiveSessions.php
php artisan make:command GenerateLiveSessions

// Logic:
1. Get all active recurring schedules
2. For each schedule, check if session exists for upcoming week
3. If not, create session:
   - scheduled_start_at = next occurrence of day_of_week + start_time
   - title = "Live Stream - {Platform} - {Day} {Time}"
   - status = 'scheduled'
4. Schedule this command to run daily in app/Console/Kernel.php
```

### 5. Navigation Menu

Add to navigation (check existing nav structure):

```blade
// For Admin/Admin Livehost
<nav-item href="{{ route('admin.live-hosts') }}">
    <icon name="video-camera" />
    Live Hosts
</nav-item>

<nav-item href="{{ route('admin.live-schedules.index') }}">
    <icon name="calendar" />
    Live Schedules
</nav-item>

<nav-item href="{{ route('admin.live-sessions.index') }}">
    <icon name="play-circle" />
    Live Sessions
</nav-item>

// For Live Host
<nav-item href="{{ route('live-host.dashboard') }}">
    <icon name="home" />
    Dashboard
</nav-item>

<nav-item href="{{ route('live-host.schedule') }}">
    <icon name="calendar" />
    My Schedule
</nav-item>

<nav-item href="{{ route('live-host.sessions.index') }}">
    <icon name="play-circle" />
    My Sessions
</nav-item>
```

### 6. Public Schedule Page

```php
// Create: resources/views/livewire/live/schedule-public.blade.php
- Calendar view of all upcoming live sessions
- Filter by platform
- Shows host name, platform, time
- Click to get platform link (when live)
```

---

## 📊 Database Schema Reference

### live_schedules
```sql
- id
- platform_account_id (FK to platform_accounts)
- day_of_week (0-6: Sunday-Saturday)
- start_time (time)
- end_time (time)
- is_recurring (boolean)
- is_active (boolean)
- created_at, updated_at
```

### live_sessions
```sql
- id
- platform_account_id (FK to platform_accounts)
- live_schedule_id (FK to live_schedules, nullable)
- title
- description (nullable)
- status (enum: scheduled, live, ended, cancelled)
- scheduled_start_at (datetime)
- actual_start_at (datetime, nullable)
- actual_end_at (datetime, nullable)
- created_at, updated_at
```

### live_analytics
```sql
- id
- live_session_id (FK to live_sessions)
- viewers_peak (int)
- viewers_avg (int)
- total_likes (int)
- total_comments (int)
- total_shares (int)
- gifts_value (decimal)
- duration_minutes (int)
- created_at, updated_at
```

---

## 🎯 User Flows

### Admin Livehost Flow
1. Create platform (TikTok, YouTube, etc.) with `features: ['live_streaming']`
2. Create platform account → assign to live host user
3. Create weekly schedule (Monday 10 AM, Wednesday 3 PM, etc.)
4. System auto-generates sessions weekly
5. Monitor live sessions in real-time
6. View analytics after sessions end

### Live Host Flow
1. Login → See dashboard with upcoming sessions
2. View weekly schedule
3. Before stream: Edit session title/description
4. Click "Start Live" → manually go live on platform (TikTok app)
5. System tracks start time
6. Click "End Live" → system tracks end time
7. Enter analytics manually (viewers, likes, comments from TikTok stats)
8. View performance history

### Student Flow
1. Visit `/live/schedule`
2. See upcoming live sessions
3. Get notified when favorite host goes live
4. Click link → opens platform (TikTok/YouTube)

---

## 🔧 Helper Methods Available

### LiveSession Model
```php
$session->startLive();           // Start streaming
$session->endLive();             // End streaming
$session->cancel();              // Cancel session
$session->isScheduled();         // Check status
$session->isLive();              // Check if currently live
$session->isEnded();             // Check if ended
$session->duration;              // Auto-calculated duration in minutes
$session->status_color;          // Color for badge
```

### LiveSchedule Model
```php
$schedule->day_name;             // "Monday", "Tuesday", etc.
$schedule->time_range;           // "10:00 - 11:00"
LiveSchedule::active()->get();   // Get active schedules
LiveSchedule::forDay(1)->get();  // Get schedules for Monday
```

### LiveAnalytics Model
```php
$analytics->engagement_rate;     // Auto-calculated
$analytics->total_engagement;    // Likes + comments + shares
```

### User Model
```php
$user->isLiveHost();             // Check if live host
$user->isAdminLivehost();        // Check if admin livehost
$user->platformAccounts;         // Get assigned platforms
$user->liveSessions;             // Get all sessions
```

---

## 📝 Next Steps (Priority Order)

1. **Add Routes** → Critical for module to work
2. **Complete Live Schedules CRUD** → Admin needs to create timetables
3. **Create Live Host Dashboard** → Hosts need to see their schedule
4. **Create Session Detail Page** → Core functionality (start/end/analytics)
5. **Create Auto-Generate Command** → Automation
6. **Add Navigation** → User can access pages
7. **Create Public Schedule** → Students can view streams
8. **Test End-to-End** → Create test host, schedule, session

---

## 🧪 Testing Checklist

- [ ] Create a live host user
- [ ] Assign platform account to host
- [ ] Create weekly schedule
- [ ] Verify session auto-generates
- [ ] Test start/end live flow
- [ ] Enter analytics
- [ ] View in admin panel
- [ ] Check calculations (engagement rate, duration)

---

## 💡 Future Enhancements

- **API Integration**: Connect to TikTok/YouTube APIs for automatic analytics
- **Real-time Viewer Count**: WebSocket integration during live
- **Notifications**: Email/SMS when going live
- **Revenue Tracking**: Calculate host earnings from gifts/views
- **Multi-Platform**: Stream to multiple platforms simultaneously
- **Recordings**: Store and manage stream recordings
- **Chat Replay**: Save and display chat messages

---

## 📚 Code Example: Complete Session Flow

```php
// 1. Admin creates schedule
$schedule = LiveSchedule::create([
    'platform_account_id' => 1, // TikTok account
    'day_of_week' => 1, // Monday
    'start_time' => '10:00',
    'end_time' => '11:00',
    'is_recurring' => true,
    'is_active' => true,
]);

// 2. System auto-generates session (via command)
$session = LiveSession::create([
    'platform_account_id' => 1,
    'live_schedule_id' => $schedule->id,
    'title' => 'Monday Morning Live',
    'scheduled_start_at' => now()->next('Monday')->setTime(10, 0),
    'status' => 'scheduled',
]);

// 3. Host prepares before going live
$session->update([
    'title' => 'Math Tutorial - Algebra Basics',
    'description' => 'Learn algebra with interactive examples',
]);

// 4. Host starts live
$session->startLive(); // Sets status='live', actual_start_at=now()

// 5. Host ends live
$session->endLive(); // Sets status='ended', actual_end_at=now()

// 6. Host enters analytics
$analytics = LiveAnalytics::create([
    'live_session_id' => $session->id,
    'viewers_peak' => 450,
    'viewers_avg' => 320,
    'total_likes' => 1200,
    'total_comments' => 340,
    'total_shares' => 89,
    'gifts_value' => 45.50,
    'duration_minutes' => $session->duration, // Auto-calculated
]);

// 7. View engagement rate
echo $analytics->engagement_rate; // Auto-calculated: (1200+340+89)/320 * 100 = 509%
```

---

## 🛠️ File Structure

```
app/
├── Models/
│   ├── LiveSchedule.php ✅
│   ├── LiveSession.php ✅
│   └── LiveAnalytics.php ✅
├── Console/Commands/
│   └── GenerateLiveSessions.php ⚠️ TODO

database/
├── migrations/
│   ├── 2025_11_03_143034_add_live_host_roles_to_users_table.php ✅
│   ├── 2025_11_03_143118_create_live_schedules_table.php ✅
│   ├── 2025_11_03_143203_create_live_sessions_table.php ✅
│   └── 2025_11_03_143252_create_live_analytics_table.php ✅

resources/views/livewire/
├── admin/
│   ├── live-hosts-list.blade.php ✅
│   ├── live-schedules-index.blade.php ⚠️ TODO
│   ├── live-schedules-create.blade.php ⚠️ TODO
│   ├── live-schedules-edit.blade.php ⚠️ TODO
│   ├── live-sessions-index.blade.php ⚠️ TODO
│   └── live-sessions-show.blade.php ⚠️ TODO
├── live-host/
│   ├── dashboard.blade.php ⚠️ TODO
│   ├── schedule.blade.php ⚠️ TODO
│   ├── sessions-index.blade.php ⚠️ TODO
│   └── sessions-show.blade.php ⚠️ TODO
└── live/
    └── schedule-public.blade.php ⚠️ TODO
```

---

## ✅ Summary

**Completed:**
- ✅ Database schema (4 migrations)
- ✅ 3 models with full relationships and helpers
- ✅ User roles (live_host, admin_livehost)
- ✅ Admin Live Hosts list page
- ✅ Model relationships for User and PlatformAccount

**Ready to Build:**
- Routes configuration
- Remaining CRUD pages
- Live host interface
- Auto-generation command
- Navigation integration

**Module is 40% complete** - Core foundation is solid, UI pages need completion.
