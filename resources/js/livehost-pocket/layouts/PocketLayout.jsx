import { Link, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import {
  Home,
  CalendarDays,
  Video,
  ListChecks,
  User as UserIcon,
} from 'lucide-react';
import { cn } from '@/livehost-pocket/lib/utils';

/**
 * Pocket shell — iOS-style phone layout with a fake status bar, scrollable
 * body, and a bottom tab bar with an elevated "Go Live" FAB at the center.
 *
 * Today / Schedule / Sessions / You all point at real Inertia routes
 * (Batches 2-4). The FAB simply routes back to Today — a live-card there
 * carries the real "Manage session" CTAs; a later batch can swap in a
 * dedicated go-live action once the backend contract is ready.
 */
const TABS = [
  { key: 'today', label: 'Today', href: '/live-host', icon: Home },
  { key: 'schedule', label: 'Schedule', href: '/live-host/schedule', icon: CalendarDays },
  { key: 'sessions', label: 'Sessions', href: '/live-host/sessions', icon: ListChecks },
  { key: 'you', label: 'You', href: '/live-host/me', icon: UserIcon },
];

function StatusBar() {
  const [time, setTime] = useState(() => formatClock(new Date()));

  useEffect(() => {
    const id = setInterval(() => setTime(formatClock(new Date())), 30_000);
    return () => clearInterval(id);
  }, []);

  return (
    <div className="pocket-safe-top relative flex h-11 items-center justify-between px-6 text-[13px] font-semibold text-[var(--color-pocket-ink)]">
      <span>{time}</span>
      <div className="flex items-center gap-1.5" aria-hidden="true">
        <span className="h-2 w-2 rounded-full bg-[var(--color-pocket-ink)]" />
        <span className="h-2 w-3 rounded-sm bg-[var(--color-pocket-ink)]" />
        <span className="h-2 w-5 rounded-sm border border-[var(--color-pocket-ink)]" />
      </div>
    </div>
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
                href="/live-host"
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
  const { url } = usePage();

  return (
    <div className="pocket-shell">
      <StatusBar />
      <main className="px-5 pb-32">{children}</main>
      <TabBar currentPath={url.split('?')[0] ?? url} />
    </div>
  );
}
