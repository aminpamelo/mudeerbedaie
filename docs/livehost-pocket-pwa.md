# Live Host Pocket — PWA & Push Notifications

The Live Host Pocket (`/live-host/*`) is an installable Progressive Web App with
Web Push notifications. Hosts can add it to their phone home screen and receive
native push alerts for sessions, schedule changes, and replacements.

This guide has three parts: **for hosts**, **for PICs/admins**, and **for
developers**.

---

## 1. For hosts — install & enable notifications

### Install the app

Open **`/live-host`** in your phone browser, sign in, then:

- **Android (Chrome):** tap the **"Pasang"** banner at the top of the app, or
  use the browser menu → *Install app* / *Add to Home screen*.
- **iPhone (Safari):** tap the **Share** button → **Add to Home Screen**. iOS
  does not show an automatic install banner — this manual step is required.

The app opens full-screen from your home screen with the violet **Hos** icon.

### Turn on notifications

Tap **"Hidupkan"** on the notification banner inside the app and **Allow** when
the browser asks. You'll then receive push alerts even when the app is closed.

> **iPhone note:** Web Push on iOS only works **after** the app has been added to
> the Home Screen (iOS 16.4+). Enable notifications from the installed app, not
> from Safari.

You can dismiss either banner — it won't nag you again on that device.

### What you'll be notified about

| Notification | When |
|---|---|
| **Sesi akan bermula** | ~15 minutes before a scheduled session starts |
| **Jadual dikemas kini / Slot baharu** | When a PIC assigns you a slot or changes its time/account/status |
| **Ganti slot** | When you're assigned as a replacement, or your replacement request is approved/rejected/expired |
| **Rekap belum dihantar** | When a finished session still has no uploaded proof |

Tapping a notification opens the relevant page in the app.

---

## 2. For PICs / admins — what triggers a push

Pushes are fired automatically from existing workflows — there is no separate
"send notification" UI:

- **Assigning / editing a session slot** (Live Host Desk → *Session Slots*)
  notifies the assigned host when the slot is dated (not a template) and today
  or later. Reassigning via the **replacement workflow** notifies through the
  replacement notifications instead (no double alert).
- **Replacement assignment / rejection / expiry** notifies the affected host.
- **Time-based reminders** (session-starting-soon, recap-overdue) are sent by
  scheduled commands — see below.

A host only receives a push if they've installed the app and enabled
notifications on at least one device. Each device is a separate subscription.

---

## 3. For developers

### Architecture

The Pocket PWA follows the same hand-rolled pattern as the CEO and HR PWAs (no
`vite-plugin-pwa`): **manifest route → blade head tags → service worker
registered on load**. Push reuses `laravel-notification-channels/webpush`, which
was already installed (the `push_subscriptions` table, `config/webpush.php`, and
the `HasPushSubscriptions` trait on `User` all pre-existed).

### File map

**PWA core**

| File | Role |
|---|---|
| `app/Http/Controllers/LiveHostPocket/PocketPwaController.php` | Serves the `/live-host/manifest.json` manifest |
| `public/pocket-sw.js` | Service worker: offline shell + `push` + `notificationclick` handlers (cache `mudeer-pocket-v1`) |
| `public/icons/pocket-192.svg`, `pocket-512.svg` | Maskable violet Bedaie icons |
| `resources/views/livehost-pocket/app.blade.php` | `<head>` manifest link, apple-touch-icon, `vapid-public-key` meta |
| `resources/js/livehost-pocket/app.jsx` | Registers `/pocket-sw.js` (scope `/live-host`) on `load` |
| `resources/js/livehost-pocket/components/InstallButton.jsx` | "Pasang aplikasi" banner (`beforeinstallprompt`) |
| `resources/js/livehost-pocket/components/NotificationOptIn.jsx` | "Hidupkan notifikasi" banner (permission + subscribe) |
| `resources/js/livehost-pocket/lib/push.js` | Subscribe/unsubscribe helpers, VAPID key decode |
| `resources/js/livehost-pocket/layouts/PocketLayout.jsx` | Mounts both banners above the page body |

**Push backend**

| File | Role |
|---|---|
| `app/Http/Controllers/LiveHostPocket/PushSubscriptionController.php` | `POST`/`DELETE /live-host/push-subscriptions` |
| `app/Notifications/LiveHost/BasePocketNotification.php` | Base class (via/toWebPush/toArray/toMail), pocket icon |
| `app/Notifications/LiveHost/SessionStartingSoonNotification.php` | 15-min reminder |
| `app/Notifications/LiveHost/RecapOverdueNotification.php` | Recap-overdue reminder |
| `app/Notifications/LiveHost/ScheduleSlotChangedNotification.php` | Slot assigned/updated (push-only) |
| `app/Notifications/Replacement*Notification.php`, `ScheduleAssignmentNotification.php` | Extended with the `WebPushChannel` |

**Triggers**

| File | Role |
|---|---|
| `app/Console/Commands/SendSessionRemindersCommand.php` | `livehost:send-session-reminders` (every 5 min) |
| `app/Console/Commands/SendRecapRemindersCommand.php` | `livehost:send-recap-reminders` (hourly) |
| `app/Http/Controllers/LiveHost/SessionSlotController.php` | Fires `ScheduleSlotChangedNotification` on store/update |
| `routes/console.php` | Schedules the two reminder commands |
| `database/migrations/..._add_push_reminder_flags_to_live_sessions_table.php` | `reminder_15m_sent_at`, `recap_reminder_sent_at` dedupe columns |

### VAPID keys

Web Push requires a VAPID keypair. This project already has one in `.env`
(`VAPID_SUBJECT`, `VAPID_PUBLIC_KEY`, `VAPID_PRIVATE_KEY`), shared with the HR
PWA and read via `config/webpush.php`. The public key is exposed to the browser
through the `<meta name="vapid-public-key">` tag.

To generate a fresh keypair (only if you ever need to rotate — this invalidates
all existing subscriptions):

```bash
php artisan webpush:vapid
# then copy the printed keys into .env and run: php artisan config:clear
```

### Make the scheduler run

The reminder commands only fire if Laravel's scheduler runs. In production:

```cron
* * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1
```

Locally you can run them on demand:

```bash
php artisan livehost:send-session-reminders   # --minutes=15 by default
php artisan livehost:send-recap-reminders      # --hours=2 by default
```

### Adding a new Pocket push notification

1. Create a class extending `BasePocketNotification`:

   ```php
   class MyPocketNotification extends BasePocketNotification
   {
       public function __construct(public LiveSession $session) {}

       protected function channels(): array { return ['database', 'push']; }
       protected function title(): string { return 'Tajuk'; }
       protected function body(): string { return 'Mesej'; }
       protected function actionUrl(): string { return route('live-host.sessions.show', $this->session); }
   }
   ```

   - `channels()`: any of `database`, `mail`, `push`.
   - Use **push-only** (`['push']`) for high-frequency triggers to avoid filling
     the `notifications` table (this is why `ScheduleSlotChangedNotification`
     does).

2. Send it: `$host->notify(new MyPocketNotification($session));` (the host is a
   `User`; `routeNotificationForWebPush` comes from `HasPushSubscriptions`).

3. The service worker (`public/pocket-sw.js`) already renders any
   `WebPushMessage`, so no client change is needed.

### Testing

```bash
php artisan test tests/Feature/LiveHostPocket/PocketPwaTest.php \
                 tests/Feature/LiveHostPocket/PocketNotificationsTest.php
```

`PocketPwaTest` covers the manifest, service worker, icons, head meta tags, and
the subscribe/unsubscribe endpoint (auth + role). `PocketNotificationsTest`
covers the reminder commands (including dedupe), the web-push channel on the
replacement notifications, and the schedule-slot trigger (assigned/updated, plus
"no push for templates").

### Troubleshooting

- **No push received:** confirm the host installed the app *and* allowed
  notifications, that a row exists in `push_subscriptions` for them, that the
  scheduler is running, and that `VAPID_*` keys are set (`php artisan config:clear`
  after editing `.env`).
- **iOS not prompting:** the app must be added to the Home Screen first
  (iOS 16.4+); Safari tabs can't receive Web Push.
- **Service worker changes not taking effect:** bump the `CACHE` version in
  `public/pocket-sw.js` (e.g. `mudeer-pocket-v2`) so old caches are evicted, and
  hard-reload.
- **Manifest 404 in the browser:** it's a public route — verify
  `route('live-host.manifest')` resolves and `npm run build` has run.
