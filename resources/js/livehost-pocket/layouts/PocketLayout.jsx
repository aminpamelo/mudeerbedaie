import { Link, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import {
  Home,
  CalendarDays,
  Video,
  ListChecks,
  TrendingUp,
  CheckCircle2,
  AlertCircle,
  Eye,
  LogOut,
} from 'lucide-react';
import { cn, initialsFrom } from '@/livehost-pocket/lib/utils';
import InstallButton from '@/livehost-pocket/components/InstallButton';
import NotificationOptIn from '@/livehost-pocket/components/NotificationOptIn';
import NotificationBell from '@/livehost-pocket/components/NotificationBell';

/**
 * Pocket shell — iOS-style phone layout with a fake status bar, scrollable
 * body, and a bottom tab bar with an elevated "Go Live" FAB at the center.
 *
 * Today / Schedule / Sessions / Performance point at real Inertia routes.
 * The FAB routes to the Go Live flow. The "You" profile tab was replaced by
 * "Performance"; the profile is now reached via the avatar in the top-right of
 * the status bar.
 */
const TABS = [
  { key: 'today', label: 'Today', href: '/live-host', icon: Home },
  { key: 'schedule', label: 'Schedule', href: '/live-host/schedule', icon: CalendarDays },
  { key: 'sessions', label: 'Sessions', href: '/live-host/sessions', icon: ListChecks },
  { key: 'performance', label: 'Performance', href: '/live-host/my-path', icon: TrendingUp },
];

function StatusBar({ user }) {
  const [time, setTime] = useState(() => formatClock(new Date()));

  useEffect(() => {
    const id = setInterval(() => setTime(formatClock(new Date())), 30_000);
    return () => clearInterval(id);
  }, []);

  return (
    <div className="pocket-safe-top relative flex h-11 items-center justify-between px-5 text-[13px] font-semibold text-[var(--color-pocket-ink)]">
      <span>{time}</span>
      <div className="flex items-center gap-1.5">
        <NotificationBell />
        <ProfileAvatar user={user} />
      </div>
    </div>
  );
}

/**
 * Top-right avatar that opens the profile ("You") page — the entry point that
 * moved here when the "You" tab was swapped for "Performance".
 */
function ProfileAvatar({ user }) {
  const avatarUrl = user?.avatarUrl;

  return (
    <Link
      href="/live-host/me"
      aria-label="Your profile"
      className="relative h-[30px] w-[30px] shrink-0 overflow-hidden rounded-full bg-gradient-to-br from-[var(--color-pocket-accent)] to-[var(--hot,#EC4899)] shadow-sm ring-1 ring-black/5 transition active:scale-95"
    >
      {avatarUrl ? (
        <img src={avatarUrl} alt={user?.name ?? 'Profile'} className="h-full w-full object-cover" />
      ) : (
        <span className="grid h-full w-full place-items-center text-[11px] font-bold tracking-[-0.02em] text-white">
          {initialsFrom(user?.name)}
        </span>
      )}
    </Link>
  );
}

function formatClock(date) {
  return date.toLocaleTimeString('en-GB', {
    hour: '2-digit',
    minute: '2-digit',
    hour12: false,
  });
}

function TabBar({ currentPath }) {
  const leftTabs = TABS.slice(0, 2);
  const rightTabs = TABS.slice(2);

  return (
    <nav
      className="pocket-safe-bottom fixed inset-x-0 bottom-0 z-30"
      aria-label="Pocket navigation"
    >
      <div className="mx-auto max-w-[480px] px-3 pb-3">
        <div className="relative rounded-[28px] border border-[var(--color-pocket-border)] bg-white/95 px-3 py-2 shadow-[var(--shadow-pocket-card)] backdrop-blur">
          <div className="grid grid-cols-5 items-end">
            {leftTabs.map((tab) => (
              <TabItem key={tab.key} tab={tab} currentPath={currentPath} />
            ))}

            <div className="relative flex justify-center">
              <Link
                href="/live-host/go-live"
                className="pointer-events-auto -mt-8 flex h-14 w-14 items-center justify-center rounded-full bg-[var(--color-pocket-accent)] text-white shadow-[var(--shadow-pocket-fab)] transition active:scale-95"
                aria-label="Go live"
              >
                <Video className="h-6 w-6" strokeWidth={2} />
              </Link>
            </div>

            {rightTabs.map((tab) => (
              <TabItem key={tab.key} tab={tab} currentPath={currentPath} />
            ))}
          </div>
        </div>
      </div>
    </nav>
  );
}

function TabItem({ tab, currentPath }) {
  const Icon = tab.icon;
  const isActive =
    tab.href === '/live-host'
      ? currentPath === '/live-host' || currentPath === '/live-host/'
      : currentPath.startsWith(tab.href);

  return (
    <Link
      href={tab.href}
      className={cn(
        'flex flex-col items-center gap-1 py-1.5 text-[11px] font-medium transition',
        isActive
          ? 'text-[var(--color-pocket-accent)]'
          : 'text-[var(--color-pocket-muted)] hover:text-[var(--color-pocket-ink)]'
      )}
    >
      <Icon className="h-5 w-5" strokeWidth={1.8} />
      <span>{tab.label}</span>
    </Link>
  );
}

export default function PocketLayout({ children }) {
  const { url, props } = usePage();
  const flashSuccess = props?.flash?.success ?? null;
  const flashError = props?.flash?.error ?? null;

  return (
    <div className="pocket-shell">
      <StatusBar user={props?.pocketUser} />
      <ImpersonationBanner
        active={Boolean(props?.auth?.isImpersonating)}
        hostName={props?.pocketUser?.name}
      />
      <FlashToast message={flashSuccess} tone="success" />
      <FlashToast message={flashError} tone="error" />
      <NotificationOptIn />
      <InstallButton />
      <main className="px-5 pb-32">{children}</main>
      <TabBar currentPath={url.split('?')[0] ?? url} />
    </div>
  );
}

/**
 * Native <form> POST to the stop-impersonation endpoint. Inertia's router.post
 * can't be used here: the endpoint redirects to a non-Inertia (Volt) admin page,
 * which Inertia would surface inside an error modal instead of following. This
 * mirrors the Live Host Desk sidebar's stop-impersonation handler.
 */
function submitStopImpersonation() {
  const csrfToken = document
    .querySelector('meta[name="csrf-token"]')
    ?.getAttribute('content');

  const form = document.createElement('form');
  form.method = 'POST';
  form.action = '/stop-impersonation';
  form.style.display = 'none';

  if (csrfToken) {
    const tokenField = document.createElement('input');
    tokenField.type = 'hidden';
    tokenField.name = '_token';
    tokenField.value = csrfToken;
    form.appendChild(tokenField);
  }

  document.body.appendChild(form);
  form.submit();
}

/**
 * Amber warning bar shown while an admin is impersonating this host, with a
 * one-tap way back to the admin account. Sticks below the status bar so it
 * stays reachable while the host feed scrolls.
 */
function ImpersonationBanner({ active, hostName }) {
  if (!active) {
    return null;
  }

  return (
    <div className="sticky top-0 z-40 flex items-center justify-between gap-2 border-b border-amber-600/30 bg-amber-500 px-4 py-2 text-white shadow-sm">
      <div className="flex min-w-0 items-center gap-2">
        <Eye className="h-4 w-4 shrink-0" strokeWidth={2} />
        <span className="min-w-0 truncate text-[12.5px] font-medium">
          Menyamar sebagai <span className="font-semibold">{hostName ?? 'host'}</span>
        </span>
      </div>
      <button
        type="button"
        onClick={submitStopImpersonation}
        className="inline-flex shrink-0 items-center gap-1 rounded-full bg-white px-2.5 py-1 text-[12px] font-semibold text-amber-700 transition active:scale-95"
      >
        <LogOut className="h-3.5 w-3.5" strokeWidth={2.2} />
        Keluar
      </button>
    </div>
  );
}

/**
 * Floating toast that surfaces the Laravel flash bag (success/error) after
 * an Inertia redirect. Auto-dismisses after 3s but stays dismissible via
 * the X button. Re-keyed on the message string so repeat successes with the
 * same copy re-trigger the animation.
 */
function FlashToast({ message, tone }) {
  const [visible, setVisible] = useState(Boolean(message));

  useEffect(() => {
    if (!message) {
      setVisible(false);
      return undefined;
    }
    setVisible(true);
    const id = setTimeout(() => setVisible(false), 3000);
    return () => clearTimeout(id);
  }, [message]);

  if (!message || !visible) {
    return null;
  }

  const isSuccess = tone === 'success';
  const Icon = isSuccess ? CheckCircle2 : AlertCircle;

  return (
    <div
      role="status"
      aria-live="polite"
      className="pointer-events-none fixed inset-x-0 top-12 z-40 flex justify-center px-4"
    >
      <div
        className={cn(
          'pointer-events-auto flex items-center gap-[10px] rounded-full border px-[14px] py-[10px] text-[13px] font-medium shadow-lg backdrop-blur',
          isSuccess
            ? 'border-[var(--accent)] bg-[var(--accent)] text-[var(--accent-ink)]'
            : 'border-[var(--hot)] bg-[var(--hot)] text-white'
        )}
      >
        <Icon className="h-[18px] w-[18px] flex-shrink-0" strokeWidth={2} />
        <span>{message}</span>
      </div>
    </div>
  );
}
