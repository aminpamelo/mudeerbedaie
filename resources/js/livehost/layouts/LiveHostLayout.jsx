import { Link, usePage } from '@inertiajs/react';
import {
  LayoutDashboard,
  Users,
  Clock,
  LayoutGrid,
  Play,
  Search,
  Store,
  ChevronsUpDown,
  DollarSign,
  Banknote,
} from 'lucide-react';
import { cn } from '@/livehost/lib/utils';

const NAV_GROUPS = [
  {
    label: 'Operations',
    items: [
      { label: 'Dashboard', href: '/livehost', icon: LayoutDashboard },
      { label: 'Live Hosts', href: '/livehost/hosts', icon: Users, countKey: 'hosts' },
    ],
  },
  {
    label: 'Allocation',
    items: [
      { label: 'Time Slots', href: '/livehost/time-slots', icon: Clock },
      { label: 'Session Slots', href: '/livehost/session-slots', icon: LayoutGrid },
      { label: 'Platform Accounts', href: '/livehost/platform-accounts', icon: Store, countKey: 'platformAccounts' },
    ],
  },
  {
    label: 'Records',
    items: [
      { label: 'Live Sessions', href: '/livehost/sessions', icon: Play, countKey: 'sessions' },
      { label: 'Commission', href: '/livehost/commission', icon: DollarSign },
      { label: 'Payroll', href: '/livehost/payroll', icon: Banknote },
    ],
  },
];

function initialsFrom(name) {
  if (!name) {
    return '?';
  }

  return name
    .split(' ')
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0]?.toUpperCase() ?? '')
    .join('') || '?';
}

function formatCount(value) {
  if (value === undefined || value === null) {
    return null;
  }

  return new Intl.NumberFormat('en-US').format(value);
}

function Sidebar({ auth, navCounts, currentUrl }) {
  const user = auth?.user;
  const roleLabel = user?.role === 'admin'
    ? 'Admin'
    : user?.role === 'admin_livehost'
      ? 'PIC · Admin'
      : user?.role ?? '';

  const isActive = (href) => {
    if (href === '/livehost') {
      return currentUrl === '/livehost' || currentUrl === '/livehost/';
    }

    return currentUrl === href || currentUrl.startsWith(`${href}/`);
  };

  return (
    <aside className="sticky top-0 flex h-screen flex-col gap-7 border-r border-border-2 px-4 py-6">
      {/* Brand */}
      <div className="flex items-center gap-[10px] px-2 py-1">
        <div
          className="grid h-8 w-8 place-items-center rounded-[10px] bg-gradient-to-br from-emerald to-sky text-[16px] font-bold text-white shadow-[0_4px_12px_rgba(16,185,129,0.25)]"
          style={{ letterSpacing: '-0.04em' }}
        >
          P
        </div>
        <div>
          <div className="text-[15px] font-semibold leading-tight tracking-[-0.02em] text-ink">
            Pulse
          </div>
          <div className="text-[11px] font-medium text-muted">Live Host Desk</div>
        </div>
      </div>

      {/* Search */}
      <button
        type="button"
        className="mx-1 flex cursor-text items-center gap-2 rounded-[10px] border border-border bg-surface px-3 py-2 text-[13px] text-muted"
      >
        <Search className="h-[14px] w-[14px] shrink-0" strokeWidth={2} />
        <span>Search...</span>
        <kbd className="ml-auto rounded bg-surface-2 px-[6px] py-[2px] font-mono text-[10px] text-muted-2">
          ⌘K
        </kbd>
      </button>

      {/* Nav groups */}
      <nav className="flex flex-1 flex-col gap-5 overflow-y-auto">
        {NAV_GROUPS.map((group) => (
          <div key={group.label} className="flex flex-col gap-0.5">
            <div className="px-3 pb-1.5 text-[11px] font-medium uppercase tracking-[0.02em] text-muted-2">
              {group.label}
            </div>
            {group.items.map((item) => {
              const Icon = item.icon;
              const active = isActive(item.href);
              const count = item.countKey ? formatCount(navCounts?.[item.countKey]) : null;
              const showLiveDot = item.live && (navCounts?.[item.countKey] ?? 0) > 0;

              return (
                <Link
                  key={item.href}
                  href={item.href}
                  className={cn(
                    'group relative flex items-center gap-[10px] rounded-lg px-3 py-2 text-[13.5px] font-medium transition-colors',
                    active
                      ? 'bg-ink text-white shadow-[0_1px_2px_rgba(0,0,0,0.04)]'
                      : 'text-ink-2 hover:bg-surface-2 hover:text-ink'
                  )}
                >
                  {showLiveDot && (
                    <span
                      className="pulse-dot -mr-0.5 h-[6px] w-[6px]"
                      aria-hidden="true"
                    />
                  )}
                  <Icon
                    className={cn(
                      'h-[15px] w-[15px] transition-colors',
                      active
                        ? 'text-white'
                        : 'text-muted group-hover:text-ink'
                    )}
                    strokeWidth={2}
                  />
                  <span>{item.label}</span>
                  {count !== null && count !== undefined && (
                    <span
                      className={cn(
                        'ml-auto rounded-full px-[7px] py-px text-[11px] font-semibold tabular-nums',
                        active
                          ? 'bg-white/15 text-white'
                          : 'bg-surface-2 text-muted'
                      )}
                    >
                      {count}
                    </span>
                  )}
                </Link>
              );
            })}
          </div>
        ))}
      </nav>

      {/* User footer */}
      <button
        type="button"
        className="flex cursor-pointer items-center gap-[10px] rounded-[10px] border border-border bg-surface p-[10px] text-left transition-colors hover:bg-surface-2"
      >
        <div className="grid h-8 w-8 shrink-0 place-items-center rounded-lg bg-gradient-to-br from-violet to-[#EC4899] text-[12px] font-semibold text-white">
          {initialsFrom(user?.name)}
        </div>
        <div className="min-w-0 flex-1">
          <div className="truncate text-[13px] font-medium leading-tight text-ink">
            {user?.name ?? 'Guest'}
          </div>
          <div className="mt-0.5 text-[11px] text-muted">{roleLabel}</div>
        </div>
        <ChevronsUpDown className="h-[14px] w-[14px] shrink-0 text-muted" strokeWidth={2} />
      </button>
    </aside>
  );
}

export function TopBar({ breadcrumb = [], actions = null }) {
  return (
    <header
      className="sticky top-0 z-50 flex items-center justify-between border-b border-border-2 px-8 py-4"
      style={{
        background: 'rgba(250,250,250,0.75)',
        backdropFilter: 'saturate(180%) blur(12px)',
        WebkitBackdropFilter: 'saturate(180%) blur(12px)',
      }}
    >
      <nav className="flex items-center gap-2 text-[13px] font-medium text-muted" aria-label="Breadcrumb">
        {breadcrumb.map((crumb, index) => {
          const isLast = index === breadcrumb.length - 1;

          return (
            <span key={`${crumb}-${index}`} className="flex items-center gap-2">
              {index > 0 && <span className="text-muted-2">/</span>}
              {isLast ? (
                <strong className="font-semibold text-ink">{crumb}</strong>
              ) : (
                <span>{crumb}</span>
              )}
            </span>
          );
        })}
      </nav>
      {actions && <div className="flex items-center gap-2">{actions}</div>}
    </header>
  );
}

export default function LiveHostLayout({ children }) {
  const { props, url } = usePage();
  const auth = props.auth ?? {};
  const navCounts = props.navCounts ?? {};

  return (
    <div className="grid min-h-screen grid-cols-[240px_1fr]">
      <Sidebar auth={auth} navCounts={navCounts} currentUrl={url} />
      <main className="flex min-w-0 flex-col">{children}</main>
    </div>
  );
}
