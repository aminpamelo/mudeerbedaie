import { usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { LayoutDashboard, LogOut, Radio, GraduationCap, ShoppingBag, Users, ListChecks, CalendarRange, Wallet, Menu, X, BarChart3 } from 'lucide-react';
import { cn, initialsFrom } from '@/ceo/lib/utils';
import { useT } from '@/ceo/lib/i18n';
import LanguageSwitcher from '@/ceo/components/LanguageSwitcher';
import InstallButton from '@/ceo/components/InstallButton';
import { ToastProvider } from '@/ceo/components/Toast';

const DEPARTMENTS = [
  { labelKey: 'dept_livehost', href: '/ceo/livehost', icon: Radio, accent: 'emerald' },
  { labelKey: 'dept_education', href: '/ceo/education', icon: GraduationCap, accent: 'sky' },
  { labelKey: 'dept_ecommerce', href: '/ceo/ecommerce', icon: ShoppingBag, accent: 'violet' },
  { labelKey: 'dept_hr', href: '/ceo/hr', icon: Users, accent: 'amber' },
  { labelKey: 'dept_sales', href: '/ceo/sales', icon: Wallet, accent: 'brand' },
];

const MONITORING = [
  { labelKey: 'tasks_nav', href: '/ceo/tasks', icon: ListChecks, accent: 'rose' },
  { labelKey: 'kpi_nav', href: '/ceo/kpi', icon: BarChart3, accent: 'cyan' },
];

const REPORTS = [
  { labelKey: 'monthly_nav_ecommerce', dept: 'ecommerce', href: '/ceo/reports/monthly?department=ecommerce', icon: CalendarRange, accent: 'violet' },
  { labelKey: 'monthly_nav_livehost', dept: 'livehost', href: '/ceo/reports/monthly?department=livehost', icon: CalendarRange, accent: 'emerald' },
];

// The four most-used destinations surfaced as bottom-bar shortcuts on mobile;
// everything else stays reachable through the "More" sheet.
const BOTTOM_TABS = [
  { labelKey: 'overview', href: '/ceo', icon: LayoutDashboard, overview: true },
  { labelKey: 'dept_livehost', href: '/ceo/livehost', icon: Radio },
  { labelKey: 'dept_ecommerce', href: '/ceo/ecommerce', icon: ShoppingBag },
  { labelKey: 'tasks_nav', href: '/ceo/tasks', icon: ListChecks },
];

const ACCENT_HEX = {
  emerald: '#10B981',
  sky: '#0EA5E9',
  violet: '#8B5CF6',
  amber: '#F59E0B',
  rose: '#F43F5E',
  brand: '#6366F1',
  cyan: '#06B6D4',
};

function isOverviewActive(url) {
  return url === '/ceo' || url === '/ceo/' || url.startsWith('/ceo?');
}

function isHrefActive(url, href) {
  return url === href || url === `${href}/` || url.startsWith(`${href}?`);
}

/**
 * Both targets redirect to non-Inertia pages, so a native <form> submit is used
 * instead of router.post().
 */
function submitNativeForm(action) {
  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
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
}

function BrandMark({ brand, size = 'lg' }) {
  const t = useT();
  const brandName = brand?.name || 'CEO Overview';
  const brandLogoUrl = brand?.logoUrl || null;
  const brandInitial = initialsFrom(brandName).charAt(0) || '?';
  const box = size === 'lg' ? 'h-9 w-9 rounded-xl text-[16px]' : 'h-8 w-8 rounded-lg text-[14px]';
  const title = size === 'lg' ? 'text-[15px]' : 'text-[14px]';
  const sub = size === 'lg' ? 'text-[11px]' : 'text-[10px]';

  return (
    <div className="flex min-w-0 items-center gap-[10px]">
      {brandLogoUrl ? (
        <div className={cn('grid shrink-0 place-items-center overflow-hidden bg-white shadow-[0_6px_16px_rgba(99,102,241,0.25)]', box)}>
          <img src={brandLogoUrl} alt={brandName} className="h-full w-full object-contain" />
        </div>
      ) : (
        <div className={cn('grid shrink-0 place-items-center bg-gradient-to-br from-[var(--color-brand)] to-[var(--color-violet)] font-bold text-white shadow-[0_6px_16px_rgba(99,102,241,0.35)]', box)} style={{ letterSpacing: '-0.04em' }}>
          {brandInitial}
        </div>
      )}
      <div className="min-w-0">
        <div className={cn('truncate font-semibold leading-tight tracking-[-0.02em] text-ink', title)}>{brandName}</div>
        <div className={cn('font-medium text-muted', sub)}>{t('ceo_overview')}</div>
      </div>
    </div>
  );
}

/**
 * Full navigation body shared by the desktop sidebar and the mobile "More"
 * sheet. `onNavigate` lets the sheet close itself when a link is tapped.
 */
function NavBody({ auth, brand, currentUrl, onNavigate }) {
  const t = useT();
  const user = auth?.user;

  const overviewActive = isOverviewActive(currentUrl);
  const onMonthly = currentUrl.startsWith('/ceo/reports/monthly');
  const monthlyDept = new URLSearchParams(currentUrl.split('?')[1] || '').get('department') || 'ecommerce';
  const isReportActive = (item) => onMonthly && monthlyDept === item.dept;

  const handleLogout = () => {
    onNavigate?.();
    if (!window.confirm('Log out of CEO Overview?')) return;
    submitNativeForm('/logout');
  };

  const renderGroup = (label, items, activeFn) => (
    <div className="flex flex-col gap-0.5">
      <div className="px-3 pb-1.5 text-[11px] font-medium uppercase tracking-[0.04em] text-muted-2">{label}</div>
      {items.map((item) => {
        const Icon = item.icon;
        const active = activeFn(item);
        const hex = ACCENT_HEX[item.accent];
        return (
          <a
            key={item.href}
            href={item.href}
            data-accent={item.accent}
            onClick={onNavigate}
            className={cn(
              'group flex items-center gap-[10px] rounded-xl px-3 py-2.5 text-[13.5px] font-semibold transition-all',
              active ? 'bg-white/70 text-ink shadow-[0_6px_16px_-10px_rgba(15,23,42,0.4)]' : 'text-ink-2 hover:bg-white/50 hover:text-ink'
            )}
          >
            <span
              className="grid h-7 w-7 shrink-0 place-items-center rounded-lg transition-colors"
              style={{ background: active ? hex : `color-mix(in oklab, ${hex} 14%, white)` }}
            >
              <Icon className="h-[15px] w-[15px]" style={{ color: active ? '#fff' : hex }} strokeWidth={2} />
            </span>
            <span>{t(item.labelKey)}</span>
          </a>
        );
      })}
    </div>
  );

  return (
    <>
      <BrandMark brand={brand} size="lg" />

      <nav className="flex flex-1 flex-col gap-5 overflow-y-auto">
        <div className="flex flex-col gap-0.5">
          <div className="px-3 pb-1.5 text-[11px] font-medium uppercase tracking-[0.04em] text-muted-2">{t('executive')}</div>
          <a
            href="/ceo"
            onClick={onNavigate}
            className={cn(
              'group flex items-center gap-[10px] rounded-xl px-3 py-2.5 text-[13.5px] font-semibold transition-all',
              overviewActive
                ? 'bg-gradient-to-r from-[var(--color-brand)] to-[var(--color-violet)] text-white shadow-[0_8px_20px_-8px_rgba(99,102,241,0.6)]'
                : 'text-ink-2 hover:bg-white/50 hover:text-ink'
            )}
          >
            <LayoutDashboard className={cn('h-[16px] w-[16px]', overviewActive ? 'text-white' : 'text-muted group-hover:text-ink')} strokeWidth={2} />
            <span>{t('overview')}</span>
          </a>
        </div>

        {renderGroup(t('departments'), DEPARTMENTS, (item) => isHrefActive(currentUrl, item.href))}
        {renderGroup(t('monitoring'), MONITORING, (item) => isHrefActive(currentUrl, item.href))}
        {renderGroup(t('reports'), REPORTS, isReportActive)}
      </nav>

      <InstallButton />
      <LanguageSwitcher />

      <div className="flex items-center gap-[10px] rounded-2xl bg-white/50 p-[10px]">
        <div className="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-gradient-to-br from-[var(--color-violet)] to-[var(--color-rose)] text-[12px] font-semibold text-white">
          {initialsFrom(user?.name)}
        </div>
        <div className="min-w-0 flex-1">
          <div className="truncate text-[13px] font-semibold leading-tight text-ink">{user?.name ?? 'Guest'}</div>
          <div className="mt-0.5 truncate text-[11px] text-muted">{user?.email ?? ''}</div>
        </div>
        <button
          type="button"
          onClick={handleLogout}
          className="grid h-8 w-8 shrink-0 place-items-center rounded-lg text-muted transition-colors hover:bg-white/70 hover:text-ink"
          aria-label={t('log_out')}
          title={t('log_out')}
        >
          <LogOut className="h-[15px] w-[15px]" strokeWidth={2} />
        </button>
      </div>
    </>
  );
}

/** Desktop sidebar — hidden below the lg breakpoint. */
function Sidebar({ auth, brand, currentUrl }) {
  return (
    <aside className="glass sticky top-4 m-4 hidden h-[calc(100dvh-2rem)] flex-col gap-7 rounded-[20px] px-4 py-6 lg:flex">
      <NavBody auth={auth} brand={brand} currentUrl={currentUrl} />
    </aside>
  );
}

/** Mobile sticky header — brand identity + language toggle. */
function MobileTopBar({ brand }) {
  return (
    <header className="glass sticky top-0 z-30 flex items-center justify-between gap-3 px-4 py-2.5 lg:hidden">
      <BrandMark brand={brand} size="sm" />
      <LanguageSwitcher />
    </header>
  );
}

/** Mobile bottom tab bar — primary shortcuts + a "More" trigger. */
function MobileTabBar({ currentUrl, onMore, moreActive }) {
  const t = useT();

  const tabClass = (active) =>
    cn('flex min-w-0 flex-1 flex-col items-center gap-1 rounded-xl py-1 text-[10px] font-semibold transition-colors', active ? 'text-[var(--color-brand-ink)]' : 'text-muted');
  const iconWrap = (active) =>
    cn('grid h-8 w-full max-w-[56px] place-items-center rounded-[12px] transition-all', active && 'bg-gradient-to-r from-[var(--color-brand)] to-[var(--color-violet)] shadow-[0_6px_16px_-6px_rgba(99,102,241,0.7)]');

  return (
    <nav
      aria-label={t('menu')}
      className="glass fixed inset-x-0 bottom-0 z-40 flex items-stretch gap-1 px-2 pt-1.5 pb-[calc(0.375rem+env(safe-area-inset-bottom))] lg:hidden"
    >
      {BOTTOM_TABS.map((tab) => {
        const Icon = tab.icon;
        const active = tab.overview ? isOverviewActive(currentUrl) : isHrefActive(currentUrl, tab.href);
        return (
          <a key={tab.href} href={tab.href} aria-current={active ? 'page' : undefined} className={tabClass(active)}>
            <span className={iconWrap(active)}>
              <Icon className={cn('h-[18px] w-[18px]', active && 'text-white')} strokeWidth={2} />
            </span>
            <span className="max-w-full truncate">{t(tab.labelKey)}</span>
          </a>
        );
      })}
      <button type="button" onClick={onMore} aria-haspopup="dialog" aria-expanded={moreActive} className={tabClass(false)}>
        <span className={cn('grid h-8 w-full max-w-[56px] place-items-center rounded-[12px] transition-colors', moreActive && 'bg-white/70 text-ink')}>
          <Menu className="h-[18px] w-[18px]" strokeWidth={2} />
        </span>
        <span>{t('more')}</span>
      </button>
    </nav>
  );
}

/** Full-navigation bottom sheet opened from the "More" tab. */
function MoreSheet({ auth, brand, currentUrl, onClose }) {
  const t = useT();

  useEffect(() => {
    const onKey = (e) => {
      if (e.key === 'Escape') onClose();
    };
    document.addEventListener('keydown', onKey);
    const prevOverflow = document.body.style.overflow;
    document.body.style.overflow = 'hidden';
    return () => {
      document.removeEventListener('keydown', onKey);
      document.body.style.overflow = prevOverflow;
    };
  }, [onClose]);

  return (
    <div className="fixed inset-0 z-[60] flex flex-col justify-end lg:hidden" role="dialog" aria-modal="true" aria-label={t('menu')}>
      <div className="scrim-in absolute inset-0 bg-[rgba(11,18,32,0.45)] backdrop-blur-sm" onClick={onClose} aria-hidden="true" />
      <div className="glass sheet-up relative z-10 flex max-h-[85dvh] flex-col gap-4 overflow-y-auto rounded-t-[24px] px-4 pb-[calc(1.25rem+env(safe-area-inset-bottom))] pt-3">
        <div className="flex items-center justify-between gap-2">
          <span className="mx-auto h-1.5 w-10 rounded-full bg-[rgba(15,23,42,0.18)]" aria-hidden="true" />
          <button
            type="button"
            onClick={onClose}
            aria-label={t('close')}
            className="absolute right-3 top-3 grid h-8 w-8 place-items-center rounded-lg text-muted transition-colors hover:bg-white/60 hover:text-ink"
          >
            <X className="h-4 w-4" strokeWidth={2.2} />
          </button>
        </div>
        <NavBody auth={auth} brand={brand} currentUrl={currentUrl} onNavigate={onClose} />
      </div>
    </div>
  );
}

export default function CeoLayout({ children }) {
  const { props, url } = usePage();
  const auth = props.auth ?? {};
  const brand = props.brand ?? {};
  const [moreOpen, setMoreOpen] = useState(false);

  return (
    <ToastProvider>
      <div className="relative z-10 lg:grid lg:min-h-dvh lg:grid-cols-[248px_1fr]">
        <Sidebar auth={auth} brand={brand} currentUrl={url} />
        <MobileTopBar brand={brand} />
        <main className="flex min-w-0 flex-col pb-[calc(6rem+env(safe-area-inset-bottom))] lg:pb-0">{children}</main>
        <MobileTabBar currentUrl={url} onMore={() => setMoreOpen(true)} moreActive={moreOpen} />
        {moreOpen && <MoreSheet auth={auth} brand={brand} currentUrl={url} onClose={() => setMoreOpen(false)} />}
      </div>
    </ToastProvider>
  );
}
