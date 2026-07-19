import { Link, usePage } from '@inertiajs/react';
import { createContext, useContext, useEffect, useState } from 'react';
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
  FileSpreadsheet,
  UserCircle2,
  Megaphone,
  Replace,
  Activity,
  Gauge,
  BarChart3,
  CalendarRange,
  ShoppingBag,
  Database,
  LogOut,
  UserMinus,
  GraduationCap,
  Clapperboard,
  Layers,
  Trophy,
  Menu,
  X,
} from 'lucide-react';
import { cn } from '@/livehost/lib/utils';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/livehost/components/ui/dropdown-menu';

const NAV_GROUPS = [
  {
    label: 'Operations',
    items: [
      { key: 'dashboard', label: 'Dashboard', href: '/livehost', icon: LayoutDashboard },
      { key: 'hosts', label: 'Live Hosts', href: '/livehost/hosts', icon: Users, countKey: 'hosts' },
      { key: 'replacements', label: 'Permohonan Ganti', href: '/livehost/replacements', icon: Replace, countKey: 'replacements' },
      { key: 'recruitment', label: 'Recruitment', href: '/livehost/recruitment/campaigns', icon: Megaphone },
      { key: 'mentoring', label: 'Mentoring', href: '/livehost/mentoring/programs', icon: GraduationCap, countKey: 'activeMentees' },
      { key: 'mentoring-overview', label: 'Mentoring Overview', href: '/livehost/mentoring/overview', icon: Gauge },
      { key: 'leaderboard', label: 'Leaderboard', href: '/livehost/mentoring/leaderboard', icon: Trophy },
      { key: 'video-report', label: 'Video Report', href: '/livehost/mentoring/video-report', icon: Clapperboard },
    ],
  },
  {
    label: 'Allocation',
    items: [
      { key: 'time-slots', label: 'Time Slots', href: '/livehost/time-slots', icon: Clock },
      { key: 'session-slots', label: 'Session Slots', href: '/livehost/session-slots', icon: LayoutGrid },
      { key: 'platform-accounts', label: 'Platform Accounts', href: '/livehost/platform-accounts', icon: Store, countKey: 'platformAccounts' },
      { key: 'creators', label: 'Creators', href: '/livehost/creators', icon: UserCircle2, countKey: 'creators' },
      { key: 'live-accounts', label: 'Live Accounts', href: '/livehost/live-accounts', icon: UserCircle2 },
    ],
  },
  {
    label: 'Records',
    items: [
      { key: 'sessions', label: 'Live Sessions', href: '/livehost/sessions', icon: Play, countKey: 'sessions' },
      { key: 'session-data', label: 'Session Data', href: '/livehost/session-data', icon: Database },
      { key: 'orders', label: 'Orders', href: '/livehost/orders', icon: ShoppingBag, countKey: 'unmatchedOrders' },
      { key: 'commission', label: 'Commission', href: '/livehost/commission', icon: DollarSign },
      { key: 'commission-templates', label: 'Commission Templates', href: '/livehost/commission-templates', icon: Layers },
      { key: 'payroll', label: 'Payroll', href: '/livehost/payroll', icon: Banknote },
      { key: 'tiktok-imports', label: 'TikTok Imports', href: '/livehost/tiktok-imports', icon: FileSpreadsheet },
    ],
  },
  {
    label: 'Reports',
    items: [
      { key: 'reports.host-scorecard', label: 'Host Scorecard', href: '/livehost/reports/host-scorecard', icon: Activity },
      { key: 'reports.gmv', label: 'GMV Performance', href: '/livehost/reports/gmv', icon: BarChart3 },
      { key: 'reports.coverage', label: 'Schedule Coverage', href: '/livehost/reports/coverage', icon: CalendarRange },
      { key: 'reports.replacements', label: 'Replacement Activity', href: '/livehost/reports/replacements', icon: Replace },
    ],
  },
];

const NAV_ITEM_PERMISSION = {
  dashboard: null,
  hosts: null,
  replacements: null,
  recruitment: 'canSeeRecruitment',
  mentoring: 'canSeeMentoring',
  'mentoring-overview': 'canSeeMentoring',
  leaderboard: 'canSeeMentoring',
  'video-report': 'canSeeMentoring',
  'time-slots': null,
  'session-slots': null,
  'platform-accounts': null,
  creators: null,
  'live-accounts': null,
  sessions: 'canSeeSessions',
  'session-data': 'canSeeSessions',
  orders: 'canSeeFinancials',
  commission: 'canSeeFinancials',
  payroll: 'canSeePayroll',
  'tiktok-imports': 'canSeeTiktokImports',
  'reports.host-scorecard': 'canSeeReports',
  'reports.gmv': 'canSeeReports',
  'reports.coverage': 'canSeeReports',
  'reports.replacements': 'canSeeReports',
};

// Shared state so the TopBar hamburger (rendered per-page) and the off-canvas
// drawer (rendered by the layout) can talk to each other on mobile.
const MobileNavContext = createContext({ open: false, setOpen: () => {} });

function canSeeNavItem(itemKey, permissions) {
  const flag = NAV_ITEM_PERMISSION[itemKey];
  if (flag === null || flag === undefined) return true;
  return Boolean(permissions?.[flag]);
}

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

// The full sidebar body (brand, search, nav, user footer). Rendered inside both
// the desktop <aside> and the mobile off-canvas drawer, so it takes no layout
// chrome of its own. `onNavigate` lets the drawer close itself on link taps.
function SidebarContent({ auth, brand, navCounts, currentUrl, onNavigate = () => {} }) {
  const user = auth?.user;
  const permissions = auth?.permissions ?? {};
  const isImpersonating = Boolean(auth?.isImpersonating);
  const impersonator = auth?.impersonator ?? null;
  const roleLabel = user?.role === 'admin'
    ? 'Admin'
    : user?.role === 'admin_livehost'
      ? 'PIC · Admin'
      : user?.role ?? '';

  // Both endpoints redirect to non-Inertia (Livewire/Volt) pages, so a
  // native <form> submit is used instead of router.post() — Inertia's POST
  // helper renders non-Inertia HTML responses inside an error modal, which
  // breaks the redirect we actually want here.
  const submitNativeForm = (action) => {
    const csrfToken = document
      .querySelector('meta[name="csrf-token"]')
      ?.getAttribute('content');

    const form = document.createElement('form');
    form.method = 'POST';
    form.action = action;
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
  };

  const handleStopImpersonation = () => {
    submitNativeForm('/stop-impersonation');
  };

  const handleLogout = () => {
    if (!window.confirm('Log out of Live Host Desk?')) {
      return;
    }
    submitNativeForm('/logout');
  };

  const brandName = brand?.name || 'Live Host Desk';
  const brandLogoUrl = brand?.logoUrl || null;
  const brandInitial = initialsFrom(brandName).charAt(0) || '?';

  const isActive = (href) => {
    // Compare on the path only — query strings (e.g. the overview's ?perf_year=…
    // month filter) must not drop a nav item's active highlight.
    const path = (currentUrl || '').split('?')[0];
    if (href === '/livehost') {
      return path === '/livehost' || path === '/livehost/';
    }

    return path === href || path.startsWith(`${href}/`);
  };

  const visibleGroups = NAV_GROUPS
    .map((group) => ({
      ...group,
      items: group.items.filter((item) => canSeeNavItem(item.key, permissions)),
    }))
    .filter((group) => group.items.length > 0);

  return (
    <>
      {/* Brand */}
      <div className="flex items-center gap-[10px] px-2 py-1">
        {brandLogoUrl ? (
          <div className="grid h-8 w-8 place-items-center overflow-hidden rounded-[10px] bg-white shadow-[0_4px_12px_rgba(16,185,129,0.15)]">
            <img
              src={brandLogoUrl}
              alt={brandName}
              className="h-8 w-8 object-contain"
            />
          </div>
        ) : (
          <div
            className="grid h-8 w-8 place-items-center rounded-[10px] bg-gradient-to-br from-emerald to-sky text-[16px] font-bold text-white shadow-[0_4px_12px_rgba(16,185,129,0.25)]"
            style={{ letterSpacing: '-0.04em' }}
          >
            {brandInitial}
          </div>
        )}
        <div>
          <div className="text-[15px] font-semibold leading-tight tracking-[-0.02em] text-ink">
            {brandName}
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
        {visibleGroups.map((group) => (
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
                  onClick={onNavigate}
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
      <DropdownMenu>
        <DropdownMenuTrigger asChild>
          <button
            type="button"
            className="flex cursor-pointer items-center gap-[10px] rounded-[10px] border border-border bg-surface p-[10px] text-left transition-colors hover:bg-surface-2 focus:outline-none focus-visible:ring-2 focus-visible:ring-ink/20"
          >
            <div className="relative shrink-0">
              <div className="grid h-8 w-8 place-items-center rounded-lg bg-gradient-to-br from-violet to-[#EC4899] text-[12px] font-semibold text-white">
                {initialsFrom(user?.name)}
              </div>
              {isImpersonating && (
                <span
                  className="absolute -right-1 -top-1 grid h-3.5 w-3.5 place-items-center rounded-full bg-amber-500 ring-2 ring-surface"
                  aria-hidden="true"
                />
              )}
            </div>
            <div className="min-w-0 flex-1">
              <div className="truncate text-[13px] font-medium leading-tight text-ink">
                {user?.name ?? 'Guest'}
              </div>
              <div className="mt-0.5 truncate text-[11px] text-muted">
                {isImpersonating && impersonator
                  ? `Impersonating · ${impersonator.name}`
                  : roleLabel}
              </div>
            </div>
            <ChevronsUpDown className="h-[14px] w-[14px] shrink-0 text-muted" strokeWidth={2} />
          </button>
        </DropdownMenuTrigger>
        <DropdownMenuContent side="top" align="start" className="w-[212px]">
          <DropdownMenuLabel className="flex flex-col gap-0.5">
            <span className="truncate text-[13px] font-semibold text-ink">
              {user?.name ?? 'Guest'}
            </span>
            {user?.email && (
              <span className="truncate text-[11px] font-normal text-muted">
                {user.email}
              </span>
            )}
          </DropdownMenuLabel>
          <DropdownMenuSeparator />
          {isImpersonating && (
            <>
              <DropdownMenuItem onSelect={handleStopImpersonation}>
                <UserMinus className="h-4 w-4" />
                <span>Stop impersonation</span>
              </DropdownMenuItem>
              <DropdownMenuSeparator />
            </>
          )}
          <DropdownMenuItem variant="destructive" onSelect={handleLogout}>
            <LogOut className="h-4 w-4" />
            <span>Log out</span>
          </DropdownMenuItem>
        </DropdownMenuContent>
      </DropdownMenu>
    </>
  );
}

// Desktop sidebar — visible from lg upward, occupies the first grid column.
function DesktopSidebar(props) {
  return (
    <aside className="sticky top-0 hidden h-screen flex-col gap-7 border-r border-border-2 px-4 py-6 lg:flex">
      <SidebarContent {...props} />
    </aside>
  );
}

// Mobile off-canvas drawer — a fixed overlay that slides the sidebar in from the
// left over a dimmed backdrop. Kept mounted so open/close can animate.
function MobileDrawer(props) {
  const { open, setOpen } = useContext(MobileNavContext);

  return (
    <div
      className={cn(
        'fixed inset-0 z-[60] lg:hidden',
        open ? 'pointer-events-auto' : 'pointer-events-none'
      )}
      aria-hidden={!open}
      inert={!open}
    >
      {/* Backdrop */}
      <div
        onClick={() => setOpen(false)}
        className={cn(
          'absolute inset-0 bg-ink/40 backdrop-blur-sm transition-opacity duration-300',
          open ? 'opacity-100' : 'opacity-0'
        )}
      />

      {/* Panel */}
      <aside
        className={cn(
          'absolute inset-y-0 left-0 flex w-[280px] max-w-[85vw] flex-col gap-7 border-r border-border-2 bg-canvas px-4 py-6 shadow-[0_20px_60px_-20px_rgba(0,0,0,0.4)] transition-transform duration-300 ease-out',
          open ? 'translate-x-0' : '-translate-x-full'
        )}
        role="dialog"
        aria-modal="true"
        aria-label="Navigation"
      >
        <button
          type="button"
          onClick={() => setOpen(false)}
          className="absolute right-3 top-3 grid h-8 w-8 place-items-center rounded-lg text-muted hover:bg-surface-2 hover:text-ink"
          aria-label="Close navigation"
        >
          <X className="h-[16px] w-[16px]" strokeWidth={2} />
        </button>
        <SidebarContent {...props} onNavigate={() => setOpen(false)} />
      </aside>
    </div>
  );
}

export function TopBar({ breadcrumb = [], actions = null }) {
  const { setOpen } = useContext(MobileNavContext);

  return (
    <header
      className="sticky top-0 z-50 flex items-center justify-between gap-2 border-b border-border-2 px-4 py-3 sm:px-8 sm:py-4"
      style={{
        background: 'rgba(250,250,250,0.75)',
        backdropFilter: 'saturate(180%) blur(12px)',
        WebkitBackdropFilter: 'saturate(180%) blur(12px)',
      }}
    >
      <div className="flex min-w-0 items-center gap-2">
        <button
          type="button"
          onClick={() => setOpen(true)}
          className="-ml-1 grid h-9 w-9 shrink-0 place-items-center rounded-lg text-ink-2 hover:bg-surface-2 hover:text-ink lg:hidden"
          aria-label="Open navigation"
        >
          <Menu className="h-5 w-5" strokeWidth={2} />
        </button>
        <nav className="flex min-w-0 items-center gap-2 text-[13px] font-medium text-muted" aria-label="Breadcrumb">
          {breadcrumb.map((crumb, index) => {
            const isLast = index === breadcrumb.length - 1;

            return (
              <span
                key={`${crumb}-${index}`}
                className={cn('items-center gap-2', isLast ? 'flex min-w-0' : 'hidden sm:flex')}
              >
                {index > 0 && <span className="text-muted-2">/</span>}
                {isLast ? (
                  <strong className="truncate font-semibold text-ink">{crumb}</strong>
                ) : (
                  <span>{crumb}</span>
                )}
              </span>
            );
          })}
        </nav>
      </div>
      {actions && <div className="flex shrink-0 items-center gap-2">{actions}</div>}
    </header>
  );
}

export default function LiveHostLayout({ children }) {
  const { props, url } = usePage();
  const auth = props.auth ?? {};
  const navCounts = props.navCounts ?? {};
  const brand = props.brand ?? {};

  const [mobileNavOpen, setMobileNavOpen] = useState(false);

  // Close the drawer whenever the route changes (link taps, back/forward).
  useEffect(() => {
    setMobileNavOpen(false);
  }, [url]);

  // Lock body scroll while the drawer is open so the page behind it stays put.
  useEffect(() => {
    if (typeof document === 'undefined') {
      return undefined;
    }
    const previous = document.body.style.overflow;
    if (mobileNavOpen) {
      document.body.style.overflow = 'hidden';
    }
    return () => {
      document.body.style.overflow = previous;
    };
  }, [mobileNavOpen]);

  // Close on Escape for keyboard users.
  useEffect(() => {
    if (!mobileNavOpen) {
      return undefined;
    }
    const onKey = (event) => {
      if (event.key === 'Escape') {
        setMobileNavOpen(false);
      }
    };
    window.addEventListener('keydown', onKey);
    return () => window.removeEventListener('keydown', onKey);
  }, [mobileNavOpen]);

  const sidebarProps = { auth, brand, navCounts, currentUrl: url };

  return (
    <MobileNavContext.Provider value={{ open: mobileNavOpen, setOpen: setMobileNavOpen }}>
      <div className="min-h-screen lg:grid lg:grid-cols-[240px_1fr]">
        <DesktopSidebar {...sidebarProps} />
        <main className="flex min-w-0 flex-col">{children}</main>
        <MobileDrawer {...sidebarProps} />
      </div>
    </MobileNavContext.Provider>
  );
}
