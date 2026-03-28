# HR Notification System Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Build a 34-notification system for the HR module using Laravel Notifications with database, mail, and web push channels.

**Architecture:** Laravel Notification classes dispatched from controllers and scheduled commands. Each notification defines its channels via `via()` (push-only, email-only, or both). In-app notification center in the React HR layout. Web push via `laravel-notification-channels/webpush` package.

**Tech Stack:** Laravel Notifications, `laravel-notification-channels/webpush`, Laravel Mail, Laravel Scheduler, React notification UI component.

**Design doc:** `docs/plans/2026-03-28-hr-notification-system-design.md`

---

## Phase 1: Foundation

### Task 1: Install WebPush Package & Create Migrations

**Files:**
- Modify: `composer.json`
- Create: migration for `notifications` table (via artisan)
- Create: migration for `push_subscriptions` table (via package publish)

**Step 1: Install the webpush package**

Run:
```bash
composer require laravel-notification-channels/webpush
```

**Step 2: Create Laravel notifications table**

Run:
```bash
php artisan notifications:table
```

**Step 3: Publish webpush migration**

Run:
```bash
php artisan vendor:publish --provider="NotificationChannels\WebPush\WebPushServiceProvider" --tag="migrations"
```

**Step 4: Run migrations**

Run:
```bash
php artisan migrate
```
Expected: Both `notifications` and `push_subscriptions` tables created.

**Step 5: Generate VAPID keys**

Run:
```bash
php artisan webpush:vapid
```

This outputs VAPID_PUBLIC_KEY and VAPID_PRIVATE_KEY. Add them to `.env`:
```
VAPID_PUBLIC_KEY=<generated>
VAPID_PRIVATE_KEY=<generated>
```

**Step 6: Commit**

```bash
git add composer.json composer.lock database/migrations/ .env.example
git commit -m "feat(hr): install webpush package and create notification tables"
```

---

### Task 2: Add HasPushSubscriptions Trait to User Model

**Files:**
- Modify: `app/Models/User.php`

**Step 1: Add the trait to User model**

In `app/Models/User.php`, add the `HasPushSubscriptions` trait:

```php
use NotificationChannels\WebPush\HasPushSubscriptions;

class User extends Authenticatable
{
    use HasFactory, Notifiable, SoftDeletes, HasPushSubscriptions;
```

The `Notifiable` trait is already present (line 18). Just add `HasPushSubscriptions`.

**Step 2: Verify with tinker**

Run:
```bash
php artisan tinker --execute="echo method_exists(\App\Models\User::class, 'pushSubscriptions') ? 'OK' : 'FAIL';"
```
Expected: `OK`

**Step 3: Commit**

```bash
git add app/Models/User.php
git commit -m "feat(hr): add HasPushSubscriptions trait to User model"
```

---

### Task 3: Create Push Subscription API Endpoints

**Files:**
- Create: `app/Http/Controllers/Api/Hr/HrPushSubscriptionController.php`
- Modify: `routes/api.php`

**Step 1: Create the controller**

Run:
```bash
php artisan make:controller Api/Hr/HrPushSubscriptionController --no-interaction
```

**Step 2: Implement the controller**

```php
<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HrPushSubscriptionController extends Controller
{
    /**
     * Store a push subscription for the authenticated user.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'endpoint' => 'required|url',
            'keys.auth' => 'required|string',
            'keys.p256dh' => 'required|string',
        ]);

        $request->user()->updatePushSubscription(
            $validated['endpoint'],
            $validated['keys']['p256dh'],
            $validated['keys']['auth']
        );

        return response()->json(['message' => 'Push subscription saved.']);
    }

    /**
     * Remove a push subscription.
     */
    public function destroy(Request $request): JsonResponse
    {
        $request->validate([
            'endpoint' => 'required|url',
        ]);

        $request->user()->deletePushSubscription($request->endpoint);

        return response()->json(['message' => 'Push subscription removed.']);
    }
}
```

**Step 3: Add routes to `routes/api.php`**

Add inside the existing HR route group (`Route::middleware(['auth:sanctum', 'role:admin,employee'])->prefix('hr')->group(...)`):

```php
// Push Subscription
Route::post('push-subscriptions', [HrPushSubscriptionController::class, 'store']);
Route::delete('push-subscriptions', [HrPushSubscriptionController::class, 'destroy']);
```

**Step 4: Commit**

```bash
git add app/Http/Controllers/Api/Hr/HrPushSubscriptionController.php routes/api.php
git commit -m "feat(hr): add push subscription API endpoints"
```

---

### Task 4: Create Notification API Endpoints

**Files:**
- Create: `app/Http/Controllers/Api/Hr/HrNotificationController.php`
- Modify: `routes/api.php`

**Step 1: Create the controller**

Run:
```bash
php artisan make:controller Api/Hr/HrNotificationController --no-interaction
```

**Step 2: Implement the controller**

```php
<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HrNotificationController extends Controller
{
    /**
     * List notifications for the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $notifications = $request->user()
            ->notifications()
            ->where('type', 'like', 'App\\Notifications\\Hr\\%')
            ->latest()
            ->paginate(20);

        return response()->json($notifications);
    }

    /**
     * Get unread notification count.
     */
    public function unreadCount(Request $request): JsonResponse
    {
        $count = $request->user()
            ->unreadNotifications()
            ->where('type', 'like', 'App\\Notifications\\Hr\\%')
            ->count();

        return response()->json(['count' => $count]);
    }

    /**
     * Mark a notification as read.
     */
    public function markRead(Request $request, string $notificationId): JsonResponse
    {
        $notification = $request->user()
            ->notifications()
            ->findOrFail($notificationId);

        $notification->markAsRead();

        return response()->json(['message' => 'Notification marked as read.']);
    }

    /**
     * Mark all HR notifications as read.
     */
    public function markAllRead(Request $request): JsonResponse
    {
        $request->user()
            ->unreadNotifications()
            ->where('type', 'like', 'App\\Notifications\\Hr\\%')
            ->update(['read_at' => now()]);

        return response()->json(['message' => 'All notifications marked as read.']);
    }
}
```

**Step 3: Add routes**

Add inside the HR route group in `routes/api.php`:

```php
// Notifications
Route::get('notifications', [HrNotificationController::class, 'index']);
Route::get('notifications/unread-count', [HrNotificationController::class, 'unreadCount']);
Route::patch('notifications/{notification}/read', [HrNotificationController::class, 'markRead']);
Route::post('notifications/mark-all-read', [HrNotificationController::class, 'markAllRead']);
```

**Step 4: Test with tinker**

Run:
```bash
php artisan tinker --execute="\$u = \App\Models\User::first(); echo \$u->notifications()->count();"
```
Expected: `0` (no notifications yet, but query works)

**Step 5: Commit**

```bash
git add app/Http/Controllers/Api/Hr/HrNotificationController.php routes/api.php
git commit -m "feat(hr): add notification API endpoints (list, count, mark read)"
```

---

### Task 5: Create Base HR Notification Class

**Files:**
- Create: `app/Notifications/Hr/BaseHrNotification.php`

**Step 1: Create the directory and base class**

```bash
mkdir -p app/Notifications/Hr
```

**Step 2: Write the base class**

```php
<?php

namespace App\Notifications\Hr;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

abstract class BaseHrNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Define which channels this notification uses.
     * Subclasses override this to customize.
     */
    abstract protected function channels(): array;

    /**
     * The notification title for push/in-app.
     */
    abstract protected function title(): string;

    /**
     * The notification body message.
     */
    abstract protected function body(): string;

    /**
     * The URL to navigate to when notification is clicked.
     */
    abstract protected function actionUrl(): string;

    /**
     * The notification icon name (for in-app display).
     */
    protected function icon(): string
    {
        return 'bell';
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via(object $notifiable): array
    {
        $channelMap = [
            'database' => 'database',
            'mail' => 'mail',
            'push' => WebPushChannel::class,
        ];

        $result = [];
        foreach ($this->channels() as $channel) {
            if (isset($channelMap[$channel])) {
                $result[] = $channelMap[$channel];
            }
        }

        return $result;
    }

    /**
     * Get the array representation (for database channel).
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title(),
            'body' => $this->body(),
            'url' => $this->actionUrl(),
            'icon' => $this->icon(),
        ];
    }

    /**
     * Get the web push representation.
     */
    public function toWebPush(object $notifiable, $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title($this->title())
            ->body($this->body())
            ->icon('/icons/hr-192.png')
            ->badge('/icons/hr-192.png')
            ->data(['url' => $this->actionUrl()]);
    }

    /**
     * Get the mail representation.
     * Subclasses can override for custom email layouts.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->title())
            ->greeting("Hello {$notifiable->name}!")
            ->line($this->body())
            ->action('View Details', url($this->actionUrl()))
            ->line('This is an automated notification from Mudeer HR.');
    }
}
```

**Step 3: Commit**

```bash
git add app/Notifications/Hr/BaseHrNotification.php
git commit -m "feat(hr): create BaseHrNotification abstract class with database, mail, and webpush support"
```

---

### Task 6: Build React Notification Bell Component

**Files:**
- Create: `resources/js/hr/components/NotificationBell.jsx`
- Modify: `resources/js/hr/layouts/HrLayout.jsx`

**Step 1: Create the NotificationBell component**

```jsx
import { useState, useEffect, useRef } from 'react';
import { Bell, Check, CheckCheck, X } from 'lucide-react';
import api from '../lib/api';

export default function NotificationBell() {
    const [notifications, setNotifications] = useState([]);
    const [unreadCount, setUnreadCount] = useState(0);
    const [isOpen, setIsOpen] = useState(false);
    const [loading, setLoading] = useState(false);
    const dropdownRef = useRef(null);

    const fetchUnreadCount = async () => {
        try {
            const { data } = await api.get('/hr/notifications/unread-count');
            setUnreadCount(data.count);
        } catch (err) {
            console.error('Failed to fetch notification count:', err);
        }
    };

    const fetchNotifications = async () => {
        setLoading(true);
        try {
            const { data } = await api.get('/hr/notifications');
            setNotifications(data.data || []);
        } catch (err) {
            console.error('Failed to fetch notifications:', err);
        } finally {
            setLoading(false);
        }
    };

    const markAsRead = async (id) => {
        try {
            await api.patch(`/hr/notifications/${id}/read`);
            setNotifications(prev =>
                prev.map(n => n.id === id ? { ...n, read_at: new Date().toISOString() } : n)
            );
            setUnreadCount(prev => Math.max(0, prev - 1));
        } catch (err) {
            console.error('Failed to mark notification as read:', err);
        }
    };

    const markAllRead = async () => {
        try {
            await api.post('/hr/notifications/mark-all-read');
            setNotifications(prev => prev.map(n => ({ ...n, read_at: new Date().toISOString() })));
            setUnreadCount(0);
        } catch (err) {
            console.error('Failed to mark all as read:', err);
        }
    };

    const handleNotificationClick = (notification) => {
        if (!notification.read_at) {
            markAsRead(notification.id);
        }
        if (notification.data?.url) {
            window.location.href = notification.data.url;
        }
        setIsOpen(false);
    };

    // Poll for unread count every 30 seconds
    useEffect(() => {
        fetchUnreadCount();
        const interval = setInterval(fetchUnreadCount, 30000);
        return () => clearInterval(interval);
    }, []);

    // Fetch full list when dropdown opens
    useEffect(() => {
        if (isOpen) {
            fetchNotifications();
        }
    }, [isOpen]);

    // Close dropdown on outside click
    useEffect(() => {
        const handleClickOutside = (event) => {
            if (dropdownRef.current && !dropdownRef.current.contains(event.target)) {
                setIsOpen(false);
            }
        };
        document.addEventListener('mousedown', handleClickOutside);
        return () => document.removeEventListener('mousedown', handleClickOutside);
    }, []);

    const timeAgo = (dateStr) => {
        const diff = Date.now() - new Date(dateStr).getTime();
        const minutes = Math.floor(diff / 60000);
        if (minutes < 1) return 'just now';
        if (minutes < 60) return `${minutes}m ago`;
        const hours = Math.floor(minutes / 60);
        if (hours < 24) return `${hours}h ago`;
        const days = Math.floor(hours / 24);
        return `${days}d ago`;
    };

    return (
        <div className="relative" ref={dropdownRef}>
            <button
                onClick={() => setIsOpen(!isOpen)}
                className="relative rounded-lg p-2 text-zinc-400 hover:bg-zinc-100 hover:text-zinc-600 dark:hover:bg-zinc-800 dark:hover:text-zinc-300 transition-colors"
            >
                <Bell className="h-5 w-5" />
                {unreadCount > 0 && (
                    <span className="absolute -top-0.5 -right-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-red-500 px-1 text-[10px] font-bold text-white">
                        {unreadCount > 99 ? '99+' : unreadCount}
                    </span>
                )}
            </button>

            {isOpen && (
                <div className="absolute right-0 top-full z-50 mt-2 w-80 overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-lg dark:border-zinc-700 dark:bg-zinc-800 sm:w-96">
                    {/* Header */}
                    <div className="flex items-center justify-between border-b border-zinc-200 px-4 py-3 dark:border-zinc-700">
                        <h3 className="text-sm font-semibold text-zinc-900 dark:text-zinc-100">Notifications</h3>
                        <div className="flex items-center gap-2">
                            {unreadCount > 0 && (
                                <button
                                    onClick={markAllRead}
                                    className="flex items-center gap-1 text-xs text-blue-600 hover:text-blue-700 dark:text-blue-400"
                                >
                                    <CheckCheck className="h-3.5 w-3.5" />
                                    Mark all read
                                </button>
                            )}
                            <button
                                onClick={() => setIsOpen(false)}
                                className="text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-300"
                            >
                                <X className="h-4 w-4" />
                            </button>
                        </div>
                    </div>

                    {/* Notification List */}
                    <div className="max-h-96 overflow-y-auto">
                        {loading ? (
                            <div className="flex items-center justify-center py-8">
                                <div className="h-5 w-5 animate-spin rounded-full border-2 border-blue-500 border-t-transparent" />
                            </div>
                        ) : notifications.length === 0 ? (
                            <div className="py-8 text-center text-sm text-zinc-500 dark:text-zinc-400">
                                No notifications yet
                            </div>
                        ) : (
                            notifications.map((notification) => (
                                <button
                                    key={notification.id}
                                    onClick={() => handleNotificationClick(notification)}
                                    className={`flex w-full items-start gap-3 px-4 py-3 text-left transition-colors hover:bg-zinc-50 dark:hover:bg-zinc-700/50 ${
                                        !notification.read_at ? 'bg-blue-50/50 dark:bg-blue-900/10' : ''
                                    }`}
                                >
                                    {/* Unread dot */}
                                    <div className="mt-1.5 shrink-0">
                                        {!notification.read_at ? (
                                            <div className="h-2 w-2 rounded-full bg-blue-500" />
                                        ) : (
                                            <div className="h-2 w-2" />
                                        )}
                                    </div>

                                    <div className="min-w-0 flex-1">
                                        <p className={`text-sm ${!notification.read_at ? 'font-semibold text-zinc-900 dark:text-zinc-100' : 'font-medium text-zinc-700 dark:text-zinc-300'}`}>
                                            {notification.data?.title || 'Notification'}
                                        </p>
                                        <p className="mt-0.5 line-clamp-2 text-xs text-zinc-500 dark:text-zinc-400">
                                            {notification.data?.body || ''}
                                        </p>
                                        <p className="mt-1 text-xs text-zinc-400 dark:text-zinc-500">
                                            {timeAgo(notification.created_at)}
                                        </p>
                                    </div>
                                </button>
                            ))
                        )}
                    </div>
                </div>
            )}
        </div>
    );
}
```

**Step 3: Add NotificationBell to HrLayout.jsx**

In `resources/js/hr/layouts/HrLayout.jsx`, import and add the bell. Find the mobile header section and add the bell icon next to the menu button. Also add it to the desktop sidebar header area.

Import at top:
```jsx
import NotificationBell from '../components/NotificationBell';
```

Add to the mobile header (near the Menu button) and desktop sidebar top area — place the `<NotificationBell />` component next to existing header elements.

**Step 4: Commit**

```bash
git add resources/js/hr/components/NotificationBell.jsx resources/js/hr/layouts/HrLayout.jsx
git commit -m "feat(hr): add notification bell component with dropdown to HrLayout"
```

---

### Task 7: Create Push Subscription Hook in React

**Files:**
- Create: `resources/js/hr/hooks/usePushSubscription.js`
- Modify: `resources/js/hr/layouts/HrLayout.jsx` (use the hook)

**Step 1: Create the hook**

```js
import { useEffect, useState } from 'react';
import api from '../lib/api';

const VAPID_PUBLIC_KEY = document.querySelector('meta[name="vapid-public-key"]')?.content;

function urlBase64ToUint8Array(base64String) {
    const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
    const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
    const rawData = window.atob(base64);
    const outputArray = new Uint8Array(rawData.length);
    for (let i = 0; i < rawData.length; ++i) {
        outputArray[i] = rawData.charCodeAt(i);
    }
    return outputArray;
}

export default function usePushSubscription() {
    const [isSubscribed, setIsSubscribed] = useState(false);
    const [isSupported, setIsSupported] = useState(false);

    useEffect(() => {
        const supported = 'serviceWorker' in navigator && 'PushManager' in window && !!VAPID_PUBLIC_KEY;
        setIsSupported(supported);
        if (!supported) return;

        navigator.serviceWorker.ready.then(async (registration) => {
            const subscription = await registration.pushManager.getSubscription();
            setIsSubscribed(!!subscription);
        });
    }, []);

    const subscribe = async () => {
        if (!isSupported) return;

        try {
            const registration = await navigator.serviceWorker.ready;
            const subscription = await registration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: urlBase64ToUint8Array(VAPID_PUBLIC_KEY),
            });

            const key = subscription.getKey('p256dh');
            const auth = subscription.getKey('auth');

            await api.post('/hr/push-subscriptions', {
                endpoint: subscription.endpoint,
                keys: {
                    p256dh: btoa(String.fromCharCode.apply(null, new Uint8Array(key))),
                    auth: btoa(String.fromCharCode.apply(null, new Uint8Array(auth))),
                },
            });

            setIsSubscribed(true);
        } catch (err) {
            console.error('Push subscription failed:', err);
        }
    };

    const unsubscribe = async () => {
        try {
            const registration = await navigator.serviceWorker.ready;
            const subscription = await registration.pushManager.getSubscription();
            if (subscription) {
                await api.delete('/hr/push-subscriptions', {
                    data: { endpoint: subscription.endpoint },
                });
                await subscription.unsubscribe();
            }
            setIsSubscribed(false);
        } catch (err) {
            console.error('Push unsubscribe failed:', err);
        }
    };

    return { isSubscribed, isSupported, subscribe, unsubscribe };
}
```

**Step 2: Add VAPID meta tag to main Blade layout**

In the main Blade layout used by the HR React app, add inside `<head>`:
```html
<meta name="vapid-public-key" content="{{ config('webpush.vapid.public_key') }}">
```

**Step 3: Use the hook in HrLayout.jsx**

Import and call the hook in HrLayout. On first load, if not subscribed and supported, auto-subscribe (or show a prompt).

```jsx
import usePushSubscription from '../hooks/usePushSubscription';

// Inside the component:
const { isSubscribed, isSupported, subscribe } = usePushSubscription();

useEffect(() => {
    if (isSupported && !isSubscribed) {
        subscribe();
    }
}, [isSupported, isSubscribed]);
```

**Step 4: Commit**

```bash
git add resources/js/hr/hooks/usePushSubscription.js resources/js/hr/layouts/HrLayout.jsx
git commit -m "feat(hr): add push subscription hook with auto-subscribe in HrLayout"
```

---

## Phase 2: Event-Triggered Notifications (24 notifications)

### Task 8: Leave Notifications (4 classes)

**Files:**
- Create: `app/Notifications/Hr/LeaveRequestSubmitted.php`
- Create: `app/Notifications/Hr/LeaveRequestApproved.php`
- Create: `app/Notifications/Hr/LeaveRequestRejected.php`
- Create: `app/Notifications/Hr/LeaveRequestCancelled.php`
- Modify: `app/Http/Controllers/Api/Hr/HrLeaveRequestController.php`
- Modify: `app/Http/Controllers/Api/Hr/HrMyLeaveController.php` (for submit & cancel)
- Test: `tests/Feature/Hr/HrLeaveNotificationTest.php`

**Step 1: Create LeaveRequestSubmitted notification**

```php
<?php

namespace App\Notifications\Hr;

use App\Models\LeaveRequest;

class LeaveRequestSubmitted extends BaseHrNotification
{
    public function __construct(
        public LeaveRequest $leaveRequest
    ) {}

    protected function channels(): array
    {
        return ['database', 'mail', 'push']; // Both
    }

    protected function title(): string
    {
        return 'New Leave Request';
    }

    protected function body(): string
    {
        $name = $this->leaveRequest->employee->full_name;
        $type = $this->leaveRequest->leaveType->name;
        $days = $this->leaveRequest->total_days;
        $start = $this->leaveRequest->start_date->format('M j');
        $end = $this->leaveRequest->end_date->format('M j, Y');

        return "{$name} requested {$days} day(s) {$type} ({$start} - {$end})";
    }

    protected function actionUrl(): string
    {
        return '/hr/leave/requests';
    }

    protected function icon(): string
    {
        return 'calendar-plus';
    }
}
```

**Step 2: Create LeaveRequestApproved notification**

```php
<?php

namespace App\Notifications\Hr;

use App\Models\LeaveRequest;
use App\Models\User;

class LeaveRequestApproved extends BaseHrNotification
{
    public function __construct(
        public LeaveRequest $leaveRequest,
        public User $approver
    ) {}

    protected function channels(): array
    {
        return ['database', 'mail', 'push']; // Both
    }

    protected function title(): string
    {
        return 'Leave Request Approved';
    }

    protected function body(): string
    {
        $type = $this->leaveRequest->leaveType->name;
        $start = $this->leaveRequest->start_date->format('M j');
        $end = $this->leaveRequest->end_date->format('M j, Y');

        return "Your {$type} request ({$start} - {$end}) has been approved by {$this->approver->name}.";
    }

    protected function actionUrl(): string
    {
        return '/hr/my/leave';
    }

    protected function icon(): string
    {
        return 'check-circle';
    }
}
```

**Step 3: Create LeaveRequestRejected notification**

```php
<?php

namespace App\Notifications\Hr;

use App\Models\LeaveRequest;
use App\Models\User;

class LeaveRequestRejected extends BaseHrNotification
{
    public function __construct(
        public LeaveRequest $leaveRequest,
        public User $rejector
    ) {}

    protected function channels(): array
    {
        return ['database', 'mail', 'push']; // Both
    }

    protected function title(): string
    {
        return 'Leave Request Rejected';
    }

    protected function body(): string
    {
        $type = $this->leaveRequest->leaveType->name;
        $reason = $this->leaveRequest->rejection_reason ?? 'No reason provided';

        return "Your {$type} request was rejected. Reason: {$reason}";
    }

    protected function actionUrl(): string
    {
        return '/hr/my/leave';
    }

    protected function icon(): string
    {
        return 'x-circle';
    }
}
```

**Step 4: Create LeaveRequestCancelled notification**

```php
<?php

namespace App\Notifications\Hr;

use App\Models\LeaveRequest;

class LeaveRequestCancelled extends BaseHrNotification
{
    public function __construct(
        public LeaveRequest $leaveRequest
    ) {}

    protected function channels(): array
    {
        return ['database', 'push']; // Push only
    }

    protected function title(): string
    {
        return 'Leave Request Cancelled';
    }

    protected function body(): string
    {
        $name = $this->leaveRequest->employee->full_name;
        $type = $this->leaveRequest->leaveType->name;
        $start = $this->leaveRequest->start_date->format('M j');
        $end = $this->leaveRequest->end_date->format('M j, Y');

        return "{$name} cancelled their {$type} request ({$start} - {$end}).";
    }

    protected function actionUrl(): string
    {
        return '/hr/leave/requests';
    }

    protected function icon(): string
    {
        return 'calendar-x';
    }
}
```

**Step 5: Dispatch notifications in HrLeaveRequestController**

In `app/Http/Controllers/Api/Hr/HrLeaveRequestController.php`:

**In `approve()` method** — after `$leaveRequest->update(...)` (after line 81), add:

```php
// Notify the employee that their leave was approved
$leaveRequest->load('employee.user', 'leaveType');
$leaveRequest->employee->user->notify(
    new \App\Notifications\Hr\LeaveRequestApproved($leaveRequest, $request->user())
);
```

**In `reject()` method** — after the update, add:

```php
// Notify the employee that their leave was rejected
$leaveRequest->load('employee.user', 'leaveType');
$leaveRequest->employee->user->notify(
    new \App\Notifications\Hr\LeaveRequestRejected($leaveRequest, $request->user())
);
```

**Step 6: Dispatch notifications in HrMyLeaveController**

Find the store/submit method and add after creating the leave request:

```php
// Notify department approvers about the new request
$leaveRequest->load('employee.department', 'leaveType');
$approvers = \App\Models\DepartmentApprover::forDepartment(
    $leaveRequest->employee->department_id
)->forType('leave')->with('approver.user')->get();

foreach ($approvers as $deptApprover) {
    $deptApprover->approver->user->notify(
        new \App\Notifications\Hr\LeaveRequestSubmitted($leaveRequest)
    );
}
```

For the cancel/destroy method:

```php
// Notify approvers about the cancellation
$leaveRequest->load('employee.department', 'leaveType');
$approvers = \App\Models\DepartmentApprover::forDepartment(
    $leaveRequest->employee->department_id
)->forType('leave')->with('approver.user')->get();

foreach ($approvers as $deptApprover) {
    $deptApprover->approver->user->notify(
        new \App\Notifications\Hr\LeaveRequestCancelled($leaveRequest)
    );
}
```

**Step 7: Write test**

Create `tests/Feature/Hr/HrLeaveNotificationTest.php`:

```php
<?php

use App\Models\DepartmentApprover;
use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use App\Notifications\Hr\LeaveRequestApproved;
use App\Notifications\Hr\LeaveRequestRejected;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
});

it('sends notification when leave request is approved', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $employeeUser = User::factory()->create(['role' => 'employee']);
    $employee = Employee::factory()->create(['user_id' => $employeeUser->id]);
    $leaveType = LeaveType::factory()->create();

    $leaveRequest = LeaveRequest::factory()->create([
        'employee_id' => $employee->id,
        'leave_type_id' => $leaveType->id,
        'status' => 'pending',
        'total_days' => 1,
    ]);

    LeaveBalance::factory()->create([
        'employee_id' => $employee->id,
        'leave_type_id' => $leaveType->id,
        'year' => now()->year,
        'pending_days' => 1,
    ]);

    $response = $this->actingAs($admin)
        ->patchJson("/api/hr/leave/requests/{$leaveRequest->id}/approve");

    $response->assertSuccessful();

    Notification::assertSentTo($employeeUser, LeaveRequestApproved::class);
});

it('sends notification when leave request is rejected', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $employeeUser = User::factory()->create(['role' => 'employee']);
    $employee = Employee::factory()->create(['user_id' => $employeeUser->id]);
    $leaveType = LeaveType::factory()->create();

    $leaveRequest = LeaveRequest::factory()->create([
        'employee_id' => $employee->id,
        'leave_type_id' => $leaveType->id,
        'status' => 'pending',
        'total_days' => 1,
    ]);

    LeaveBalance::factory()->create([
        'employee_id' => $employee->id,
        'leave_type_id' => $leaveType->id,
        'year' => now()->year,
        'pending_days' => 1,
    ]);

    $response = $this->actingAs($admin)
        ->patchJson("/api/hr/leave/requests/{$leaveRequest->id}/reject", [
            'rejection_reason' => 'Team capacity',
        ]);

    $response->assertSuccessful();

    Notification::assertSentTo($employeeUser, LeaveRequestRejected::class);
});
```

**Step 8: Run the tests**

Run:
```bash
php artisan test --compact --filter=HrLeaveNotification
```
Expected: PASS

**Step 9: Commit**

```bash
git add app/Notifications/Hr/ tests/Feature/Hr/HrLeaveNotificationTest.php app/Http/Controllers/Api/Hr/HrLeaveRequestController.php
git commit -m "feat(hr): add leave request notifications (submitted, approved, rejected, cancelled)"
```

---

### Task 9: Attendance Notifications (4 event-triggered classes)

**Files:**
- Create: `app/Notifications/Hr/MissedClockIn.php`
- Create: `app/Notifications/Hr/OvertimeRequestSubmitted.php`
- Create: `app/Notifications/Hr/OvertimeRequestDecision.php`
- Create: `app/Notifications/Hr/ScheduleChanged.php`
- Modify: relevant controllers to dispatch
- Test: `tests/Feature/Hr/HrAttendanceNotificationTest.php`

Follow the same pattern as Task 8. Each notification extends `BaseHrNotification`:

- **MissedClockIn**: channels `['database', 'mail', 'push']` (Both), sent to employee
- **OvertimeRequestSubmitted**: channels `['database', 'mail', 'push']` (Both), sent to approver
- **OvertimeRequestDecision**: channels `['database', 'push']` (Push), sent to employee
- **ScheduleChanged**: channels `['database', 'mail', 'push']` (Both), sent to employee

Dispatch in:
- `HrOvertimeController` (approve/reject methods)
- `HrEmployeeScheduleController` (store/update methods)

---

### Task 10: Payroll Notifications (6 classes)

**Files:**
- Create: `app/Notifications/Hr/PayrollRunCreated.php`
- Create: `app/Notifications/Hr/PayrollSubmittedForReview.php`
- Create: `app/Notifications/Hr/PayrollApproved.php`
- Create: `app/Notifications/Hr/PayrollFinalized.php`
- Create: `app/Notifications/Hr/PayslipReady.php`
- Create: `app/Notifications/Hr/SalaryRevision.php`
- Modify: `app/Http/Controllers/Api/Hr/HrPayrollRunController.php`
- Modify: `app/Http/Controllers/Api/Hr/HrEmployeeSalaryController.php`
- Test: `tests/Feature/Hr/HrPayrollNotificationTest.php`

Channel mapping:
- **PayrollRunCreated**: `['database', 'push']` (Push only), sent to admin/HR users
- **PayrollSubmittedForReview**: `['database', 'mail', 'push']` (Both), sent to admin/reviewers
- **PayrollApproved**: `['database', 'push']` (Push only), sent to submitting admin
- **PayrollFinalized**: `['database', 'mail', 'push']` (Both), sent to all employees in payroll run
- **PayslipReady**: `['database', 'mail']` (Email only), sent to individual employee
- **SalaryRevision**: `['database', 'mail']` (Email only), sent to individual employee

For **PayrollFinalized**, loop through all payroll items, get each employee's user, and notify:
```php
$payrollRun->load('items.employee.user');
foreach ($payrollRun->items as $item) {
    $item->employee->user->notify(new PayslipReady($item));
}
```

---

### Task 11: Claims Notifications (4 event-triggered classes)

**Files:**
- Create: `app/Notifications/Hr/ClaimSubmitted.php`
- Create: `app/Notifications/Hr/ClaimApproved.php`
- Create: `app/Notifications/Hr/ClaimRejected.php`
- Create: `app/Notifications/Hr/ClaimMarkedPaid.php`
- Modify: `app/Http/Controllers/Api/Hr/HrClaimRequestController.php`
- Modify: `app/Http/Controllers/Api/Hr/HrMyClaimController.php`
- Test: `tests/Feature/Hr/HrClaimNotificationTest.php`

Channel mapping:
- **ClaimSubmitted**: `['database', 'mail', 'push']` (Both), sent to claim approvers
- **ClaimApproved**: `['database', 'mail', 'push']` (Both), sent to employee
- **ClaimRejected**: `['database', 'mail', 'push']` (Both), sent to employee
- **ClaimMarkedPaid**: `['database', 'push']` (Push only), sent to employee

Dispatch in `HrClaimRequestController`:
- `approve()` method → `ClaimApproved`
- `reject()` method → `ClaimRejected`
- `markPaid()` method → `ClaimMarkedPaid`

Dispatch in `HrMyClaimController`:
- `store()` or `submit()` method → `ClaimSubmitted` to approvers

---

### Task 12: Asset Notifications (2 event-triggered classes)

**Files:**
- Create: `app/Notifications/Hr/AssetAssigned.php`
- Create: `app/Notifications/Hr/AssetReturnConfirmed.php`
- Modify: `app/Http/Controllers/Api/Hr/HrAssetAssignmentController.php`
- Test: `tests/Feature/Hr/HrAssetNotificationTest.php`

Channel mapping:
- **AssetAssigned**: `['database', 'mail', 'push']` (Both), sent to employee
- **AssetReturnConfirmed**: `['database', 'push']` (Push only), sent to admin

---

### Task 13: Employee Management Notifications (2 event-triggered classes)

**Files:**
- Create: `app/Notifications/Hr/WelcomeOnboarding.php`
- Create: `app/Notifications/Hr/EmploymentStatusChanged.php`
- Modify: `app/Http/Controllers/Api/Hr/HrEmployeeController.php`
- Test: `tests/Feature/Hr/HrEmployeeNotificationTest.php`

Channel mapping:
- **WelcomeOnboarding**: `['database', 'mail']` (Email only), sent to new employee's user
- **EmploymentStatusChanged**: `['database', 'mail']` (Email only), sent to employee

Override `toMail()` on WelcomeOnboarding for a rich welcome email with portal access info.

---

## Phase 3: Scheduled Notifications (10 notifications)

### Task 14: Clock-In/Out Reminder Command

**Files:**
- Create: `app/Console/Commands/Hr/SendClockReminders.php`
- Create: `app/Notifications/Hr/ClockInReminder.php`
- Create: `app/Notifications/Hr/ClockOutReminder.php`
- Modify: `routes/console.php` (register schedule)

**Step 1: Create command**

```bash
php artisan make:command Hr/SendClockReminders --no-interaction
```

**Step 2: Implement**

The command should:
1. Get all employees with schedules for today
2. For each employee, check if their shift starts within 15 minutes
3. If no clock-in recorded, send `ClockInReminder`
4. Similarly for clock-out near shift end

Channel: `['database', 'push']` (Push only)

**Step 3: Register in scheduler**

In `routes/console.php`:
```php
Schedule::command('hr:send-clock-reminders')->everyFifteenMinutes();
```

---

### Task 15: Attendance Alert Commands

**Files:**
- Create: `app/Console/Commands/Hr/SendLateClockInAlerts.php`
- Create: `app/Notifications/Hr/LateClockInAlert.php`
- Create: `app/Notifications/Hr/AttendancePenaltyFlagged.php`

**LateClockInAlert**: Push only, sent to admin/manager when employee is 15+ mins late.
**AttendancePenaltyFlagged**: Push only, sent to employee when penalty threshold reached.

Schedule: Run every 15 minutes during work hours.

---

### Task 16: Leave Scheduled Commands

**Files:**
- Create: `app/Console/Commands/Hr/CheckLeaveBalances.php`
- Create: `app/Console/Commands/Hr/SendUpcomingLeaveReminders.php`
- Create: `app/Console/Commands/Hr/SendTeamLeaveAlerts.php`
- Create: `app/Notifications/Hr/LeaveBalanceLow.php`
- Create: `app/Notifications/Hr/UpcomingLeaveReminder.php`
- Create: `app/Notifications/Hr/TeamLeaveAlert.php`

**LeaveBalanceLow**: Push only, weekly check on Mondays. Notify if balance < 3 days.
**UpcomingLeaveReminder**: Push only, daily at 6 PM for leave starting next day.
**TeamLeaveAlert**: Push only, daily at 8 AM to managers with today's team absences.

---

### Task 17: Claims & Assets Scheduled Commands

**Files:**
- Create: `app/Console/Commands/Hr/RemindPendingClaims.php`
- Create: `app/Console/Commands/Hr/CheckExpiringClaims.php`
- Create: `app/Console/Commands/Hr/CheckAssetReturns.php`
- Create: `app/Notifications/Hr/ClaimPendingReminder.php`
- Create: `app/Notifications/Hr/ClaimExpiringSoon.php`
- Create: `app/Notifications/Hr/AssetReturnRequested.php`

**ClaimPendingReminder**: Push only, daily 9 AM to approvers with pending claims.
**ClaimExpiringSoon**: Push only, daily check for receipts expiring in 7 days.
**AssetReturnRequested**: Push only, daily check for upcoming return deadlines.

---

### Task 18: Employee Scheduled Commands

**Files:**
- Create: `app/Console/Commands/Hr/CheckProbationEndings.php`
- Create: `app/Console/Commands/Hr/CheckExpiringDocuments.php`
- Create: `app/Notifications/Hr/ProbationEnding.php`
- Create: `app/Notifications/Hr/DocumentExpiring.php`

**ProbationEnding**: Both (push + email), daily check for probations ending in 30 days. Notify admin/manager.
**DocumentExpiring**: Both (push + email), daily check for documents expiring in 30 days. Notify employee + admin.

---

### Task 19: Register All Scheduled Commands

**Files:**
- Modify: `routes/console.php`

```php
use Illuminate\Support\Facades\Schedule;

// HR Notification Schedules
Schedule::command('hr:send-clock-reminders')->everyFifteenMinutes();
Schedule::command('hr:send-late-alerts')->everyFifteenMinutes()->between('8:00', '18:00');
Schedule::command('hr:check-leave-balances')->weeklyOn(1, '8:00'); // Monday 8 AM
Schedule::command('hr:send-upcoming-leave-reminders')->dailyAt('18:00');
Schedule::command('hr:send-team-leave-alerts')->dailyAt('08:00');
Schedule::command('hr:remind-pending-claims')->dailyAt('09:00');
Schedule::command('hr:check-expiring-claims')->dailyAt('09:00');
Schedule::command('hr:check-asset-returns')->dailyAt('09:00');
Schedule::command('hr:check-probation-endings')->dailyAt('09:00');
Schedule::command('hr:check-expiring-documents')->dailyAt('09:00');
```

**Step 2: Commit**

```bash
git add routes/console.php
git commit -m "feat(hr): register all scheduled notification commands"
```

---

### Task 20: Final Integration Test

**Files:**
- Create: `tests/Feature/Hr/HrNotificationApiTest.php`

Test the notification API endpoints:

```php
<?php

use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;

it('lists notifications for authenticated user', function () {
    $user = User::factory()->create(['role' => 'employee']);

    // Create a test notification in the database
    $user->notify(new \App\Notifications\Hr\LeaveRequestApproved(
        \App\Models\LeaveRequest::factory()->create(),
        User::factory()->create()
    ));

    $response = $this->actingAs($user)->getJson('/api/hr/notifications');

    $response->assertSuccessful()
        ->assertJsonStructure(['data' => [['id', 'type', 'data', 'read_at', 'created_at']]]);
});

it('returns unread count', function () {
    $user = User::factory()->create(['role' => 'employee']);

    $response = $this->actingAs($user)->getJson('/api/hr/notifications/unread-count');

    $response->assertSuccessful()
        ->assertJson(['count' => 0]);
});

it('marks notification as read', function () {
    $user = User::factory()->create(['role' => 'employee']);

    $user->notify(new \App\Notifications\Hr\LeaveRequestApproved(
        \App\Models\LeaveRequest::factory()->create(),
        User::factory()->create()
    ));

    $notification = $user->notifications()->first();

    $response = $this->actingAs($user)
        ->patchJson("/api/hr/notifications/{$notification->id}/read");

    $response->assertSuccessful();
    expect($notification->fresh()->read_at)->not->toBeNull();
});

it('marks all notifications as read', function () {
    $user = User::factory()->create(['role' => 'employee']);

    $response = $this->actingAs($user)
        ->postJson('/api/hr/notifications/mark-all-read');

    $response->assertSuccessful();
});
```

**Run all HR notification tests:**

```bash
php artisan test --compact --filter=HrNotification
```

**Step 2: Run full test suite**

```bash
php artisan test --compact
```

**Step 3: Final commit**

```bash
git add tests/Feature/Hr/HrNotificationApiTest.php
git commit -m "test(hr): add notification API integration tests"
```

---

## Summary

| Phase | Tasks | Notifications | Description |
|-------|-------|--------------|-------------|
| 1 | 1-7 | 0 | Foundation: package, migrations, APIs, UI, push subscription |
| 2 | 8-13 | 24 | Event-triggered: leave, attendance, payroll, claims, assets, employee |
| 3 | 14-19 | 10 | Scheduled: reminders, alerts, balance checks |
| Final | 20 | 0 | Integration tests |

**Total: 20 tasks, 34 notification classes, 9 scheduled commands**
