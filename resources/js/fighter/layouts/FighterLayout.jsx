import { Link, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { LayoutDashboard, TrendingUp, ShoppingBag, Bell, LogOut, Menu, X, Swords } from 'lucide-react';
import { cn, initialsFrom } from '@/fighter/lib/utils';
import NotificationBell from '@/fighter/components/NotificationBell';
import CreateFunnelButton from '@/fighter/components/CreateFunnelButton';

const NAV = [
  { label: 'Dashboard', href: '/fighter', icon: LayoutDashboard, exact: true },
  { label: 'Performance', href: '/fighter/performance', icon: TrendingUp },
  { label: 'Orders', href: '/fighter/orders', icon: ShoppingBag },
  { label: 'Notifications', href: '/fighter/notifications', icon: Bell },
];

function isActive(url, href, exact) {
  const path = url.split('?')[0];
  if (exact) return path === href || path === `${href}/`;
  return path === href || path.startsWith(`${href}/`);
}

/** Log out via a native POST form (the target is a non-Inertia redirect). */
function submitLogout() {
  if (!window.confirm('Log out of Bedaie Fighter?')) return;
  const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
  const form = document.createElement('form');
  form.method = 'POST';
  form.action = '/logout';
  form.style.display = 'none';
  if (token) {
    const field = document.createElement('input');
    field.type = 'hidden';
    field.name = '_token';
    field.value = token;
    form.appendChild(field);
  }
  document.body.appendChild(form);
  form.submit();
}

function Brand() {
  return (
    <div className="flex items-center gap-3">
      <div className="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-gradient-to-br from-[var(--color-brand)] to-[var(--color-rose)] text-white shadow-[0_8px_20px_-8px_rgba(249,115,22,0.7)]">
        <Swords className="h-[18px] w-[18px]" strokeWidth={2.2} />
      </div>
      <div className="min-w-0">
        <div className="text-[15px] font-semibold leading-tight tracking-[-0.02em] text-white">Bedaie Fighter</div>
        <div className="text-[11px] font-medium text-white/50">Funnel workspace</div>
      </div>
    </div>
  );
}

function NavLinks({ url, onNavigate }) {
  return (
    <nav className="flex flex-1 flex-col gap-1">
      {NAV.map((item) => {
        const Icon = item.icon;
        const active = isActive(url, item.href, item.exact);
        return (
          <Link
            key={item.href}
            href={item.href}
            onClick={onNavigate}
            className={cn(
              'group flex items-center gap-3 rounded-xl px-3 py-2.5 text-[13.5px] font-semibold transition-all',
              active
                ? 'bg-white/10 text-white shadow-[inset_0_0_0_1px_rgba(255,255,255,0.08)]'
                : 'text-white/60 hover:bg-white/5 hover:text-white'
            )}
          >
            <Icon
              className={cn('h-[17px] w-[17px] transition-colors', active ? 'text-[var(--color-brand)]' : 'text-white/40 group-hover:text-white/70')}
              strokeWidth={2}
            />
            <span>{item.label}</span>
          </Link>
        );
      })}
    </nav>
  );
}

function UserFooter({ user }) {
  return (
    <div className="flex items-center gap-3 rounded-2xl bg-white/5 p-2.5">
      <div className="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-gradient-to-br from-[var(--color-brand)] to-[var(--color-amber)] text-[12px] font-semibold text-white">
        {initialsFrom(user?.name)}
      </div>
      <div className="min-w-0 flex-1">
        <div className="truncate text-[13px] font-semibold leading-tight text-white">{user?.name ?? 'Fighter'}</div>
        <div className="mt-0.5 truncate text-[11px] text-white/45">{user?.email ?? ''}</div>
      </div>
      <button
        type="button"
        onClick={submitLogout}
        className="grid h-8 w-8 shrink-0 place-items-center rounded-lg text-white/50 transition-colors hover:bg-white/10 hover:text-white"
        aria-label="Log out"
        title="Log out"
      >
        <LogOut className="h-[15px] w-[15px]" strokeWidth={2} />
      </button>
    </div>
  );
}

function Sidebar({ user, url }) {
  return (
    <aside className="panel sticky top-4 m-4 hidden h-[calc(100dvh-2rem)] flex-col gap-6 rounded-[20px] px-4 py-6 lg:flex">
      <Brand />
      <CreateFunnelButton />
      <NavLinks url={url} />
      <UserFooter user={user} />
    </aside>
  );
}

function MobileBar({ url, onOpen }) {
  return (
    <header className="panel sticky top-0 z-30 flex items-center justify-between gap-3 px-4 py-2.5 lg:hidden">
      <Brand />
      <div className="flex items-center gap-1">
        <NotificationBell />
        <button
          type="button"
          onClick={onOpen}
          className="grid h-9 w-9 place-items-center rounded-lg text-white/70 transition-colors hover:bg-white/10 hover:text-white"
          aria-label="Open menu"
        >
          <Menu className="h-5 w-5" strokeWidth={2} />
        </button>
      </div>
    </header>
  );
}

function MobileDrawer({ user, url, onClose }) {
  useEffect(() => {
    const onKey = (e) => e.key === 'Escape' && onClose();
    document.addEventListener('keydown', onKey);
    const prev = document.body.style.overflow;
    document.body.style.overflow = 'hidden';
    return () => {
      document.removeEventListener('keydown', onKey);
      document.body.style.overflow = prev;
    };
  }, [onClose]);

  return (
    <div className="fixed inset-0 z-[60] flex lg:hidden" role="dialog" aria-modal="true">
      <div className="absolute inset-0 bg-black/50 backdrop-blur-sm" onClick={onClose} aria-hidden="true" />
      <div className="panel relative z-10 flex h-full w-[82%] max-w-[320px] flex-col gap-6 rounded-r-[20px] px-4 py-6">
        <div className="flex items-center justify-between">
          <Brand />
          <button
            type="button"
            onClick={onClose}
            className="grid h-8 w-8 place-items-center rounded-lg text-white/60 hover:bg-white/10 hover:text-white"
            aria-label="Close menu"
          >
            <X className="h-4 w-4" strokeWidth={2.2} />
          </button>
        </div>
        <CreateFunnelButton />
        <NavLinks url={url} onNavigate={onClose} />
        <UserFooter user={user} />
      </div>
    </div>
  );
}

export default function FighterLayout({ children, title, subtitle, actions }) {
  const { props, url } = usePage();
  const user = props.auth?.user;
  const [drawerOpen, setDrawerOpen] = useState(false);

  return (
    <div className="lg:grid lg:min-h-dvh lg:grid-cols-[248px_1fr]">
      <Sidebar user={user} url={url} />
      <MobileBar url={url} onOpen={() => setDrawerOpen(true)} />
      {drawerOpen && <MobileDrawer user={user} url={url} onClose={() => setDrawerOpen(false)} />}

      <main className="min-w-0 p-4 lg:py-6 lg:pr-6 lg:pl-0">
        <div className="min-h-[calc(100dvh-2rem)] rounded-[20px] bg-white p-5 shadow-[0_20px_60px_-30px_rgba(0,0,0,0.5)] sm:p-7">
          <div className="mb-6 flex flex-wrap items-start justify-between gap-4">
            <div className="min-w-0">
              <h1 className="text-[22px] font-bold tracking-[-0.02em] text-ink sm:text-[26px]">{title}</h1>
              {subtitle && <p className="mt-1 text-[13.5px] text-muted">{subtitle}</p>}
            </div>
            <div className="flex items-center gap-2">
              {/* Notification bell on desktop lives beside the page actions. */}
              <div className="hidden lg:block">
                <NotificationBell dark={false} />
              </div>
              {actions}
            </div>
          </div>
          {children}
        </div>
      </main>
    </div>
  );
}
