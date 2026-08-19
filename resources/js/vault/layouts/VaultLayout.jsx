import { Link, router, usePage } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import toast, { Toaster } from 'react-hot-toast';
import {
  ShieldCheck, LayoutDashboard, Key, FolderOpen, Tag, ClipboardList,
  Menu, X, ArrowLeft, Settings, Lock,
} from 'lucide-react';
import { cn } from '@/vault/lib/utils';

const NAV = [
  { label: 'Overview', href: '/admin/vault', icon: LayoutDashboard, exact: true },
  { label: 'Credentials', href: '/admin/vault/credentials', icon: Key },
  { label: 'Categories', href: '/admin/vault/categories', icon: FolderOpen },
  { label: 'Tags', href: '/admin/vault/tags', icon: Tag },
  { label: 'Audit Log', href: '/admin/vault/audit-log', icon: ClipboardList },
  { label: 'Settings', href: '/admin/vault/settings', icon: Settings },
];

function isActive(url, href, exact) {
  const path = url.split('?')[0];
  if (exact) return path === href || path === `${href}/`;
  return path === href || path.startsWith(`${href}/`);
}

function Brand() {
  return (
    <div className="flex items-center gap-3">
      <div className="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-gradient-to-br from-amber-500 to-yellow-500 text-white shadow-[0_8px_20px_-8px_rgba(245,158,11,0.7)]">
        <ShieldCheck className="h-[18px] w-[18px]" strokeWidth={2.2} />
      </div>
      <div className="min-w-0">
        <div className="text-[15px] font-semibold leading-tight tracking-[-0.02em] text-white">Password Vault</div>
        <div className="text-[11px] font-medium text-white/50">Credential Manager</div>
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
                ? 'bg-amber-500/15 text-amber-400 shadow-[inset_0_0_0_1px_rgba(245,158,11,0.15)]'
                : 'text-white/60 hover:bg-white/5 hover:text-white'
            )}
          >
            <Icon
              className={cn('h-[17px] w-[17px] transition-colors', active ? 'text-amber-400' : 'text-white/40 group-hover:text-white/70')}
              strokeWidth={2}
            />
            <span className="flex-1">{item.label}</span>
          </Link>
        );
      })}
    </nav>
  );
}

function UserFooter({ user }) {
  const lockVault = () => {
    router.post('/admin/vault/lock');
  };

  return (
    <div className="space-y-2">
      <div className="flex items-center gap-3 rounded-2xl bg-white/5 p-2.5">
        <div className="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-gradient-to-br from-amber-500 to-yellow-500 text-[12px] font-semibold text-white">
          {user?.name ? user.name.trim().split(/\s+/).slice(0, 2).map((w) => w.charAt(0).toUpperCase()).join('') : '?'}
        </div>
        <div className="min-w-0 flex-1">
          <div className="truncate text-[13px] font-semibold leading-tight text-white">{user?.name ?? 'Admin'}</div>
          <div className="mt-0.5 truncate text-[11px] text-white/45">{user?.email ?? ''}</div>
        </div>
      </div>
      <button
        type="button"
        onClick={lockVault}
        className="flex w-full items-center gap-2 rounded-xl px-3 py-2 text-[12.5px] font-medium text-amber-400/80 transition-colors hover:bg-amber-500/10 hover:text-amber-300"
      >
        <Lock className="h-[15px] w-[15px]" strokeWidth={2} />
        Lock Vault
      </button>
      <a
        href="/admin"
        className="flex items-center gap-2 rounded-xl px-3 py-2 text-[12.5px] font-medium text-white/45 transition-colors hover:bg-white/5 hover:text-white/80"
      >
        <ArrowLeft className="h-[15px] w-[15px]" strokeWidth={2} />
        Back to Admin
      </a>
    </div>
  );
}

function Sidebar({ user, url }) {
  return (
    <aside className="panel sticky top-4 m-4 hidden h-[calc(100dvh-2rem)] flex-col gap-6 rounded-[20px] px-4 py-6 lg:flex">
      <Brand />
      <NavLinks url={url} />
      <UserFooter user={user} />
    </aside>
  );
}

function MobileBar({ onOpen }) {
  return (
    <header className="panel sticky top-0 z-30 flex items-center justify-between gap-3 px-4 py-2.5 lg:hidden">
      <Brand />
      <button
        type="button"
        onClick={onOpen}
        className="grid h-9 w-9 place-items-center rounded-lg text-white/70 transition-colors hover:bg-white/10 hover:text-white"
        aria-label="Open menu"
      >
        <Menu className="h-5 w-5" strokeWidth={2} />
      </button>
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
        <NavLinks url={url} onNavigate={onClose} />
        <UserFooter user={user} />
      </div>
    </div>
  );
}

/**
 * Surface server flash messages as toasts. Tracks the last message shown so a
 * partial reload that re-sends the same flash doesn't fire a duplicate toast.
 */
function useFlashToasts(flash) {
  const seen = useRef(null);

  useEffect(() => {
    const message = flash?.success || flash?.error;
    if (!message || seen.current === message) return;

    seen.current = message;
    if (flash?.error) {
      toast.error(message);
    } else {
      toast.success(message);
    }
  }, [flash?.success, flash?.error]);
}

export default function VaultLayout({ children, title, subtitle, actions, wide = false }) {
  const { props, url } = usePage();
  const user = props.auth?.user;
  const [drawerOpen, setDrawerOpen] = useState(false);

  useFlashToasts(props.flash);

  return (
    <div className="lg:grid lg:min-h-dvh lg:grid-cols-[248px_1fr]">
      <Sidebar user={user} url={url} />
      <MobileBar onOpen={() => setDrawerOpen(true)} />
      {drawerOpen && <MobileDrawer user={user} url={url} onClose={() => setDrawerOpen(false)} />}

      <main className="min-w-0 p-4 lg:py-6 lg:pr-6 lg:pl-0">
        <div className={cn(
          'panel min-h-[calc(100dvh-2rem)] rounded-[20px]',
          wide ? 'p-4 sm:p-5' : 'p-5 sm:p-7'
        )}>
          <div className="mb-6 flex flex-wrap items-start justify-between gap-4">
            <div className="min-w-0">
              <h1 className="text-[22px] font-bold tracking-[-0.02em] text-white sm:text-[26px]">{title}</h1>
              {subtitle && <p className="mt-1 text-[13.5px] text-white/50">{subtitle}</p>}
            </div>
            {actions && <div className="flex flex-wrap items-center gap-2">{actions}</div>}
          </div>
          {children}
        </div>
      </main>

      <Toaster
        position="bottom-center"
        toastOptions={{
          style: { background: '#1E293B', color: '#F8FAFC', fontSize: '13.5px', borderRadius: '12px', border: '1px solid rgba(255,255,255,0.1)' },
          success: { iconTheme: { primary: '#F59E0B', secondary: '#1E293B' } },
          error: { iconTheme: { primary: '#F43F5E', secondary: '#1E293B' } },
        }}
      />
    </div>
  );
}
