import { Link, usePage } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import toast, { Toaster } from 'react-hot-toast';
import { Bot, Smartphone, MessageCircle, Zap, Package, Megaphone, BarChart3, Settings, Menu, X, ArrowLeft } from 'lucide-react';
import { cn, initialsFrom } from '@/cekbot-admin/lib/utils';

const NAV = [
  { label: 'Nombor WhatsApp', href: '/admin/cekbot', icon: Smartphone, exact: true },
  { label: 'Mesej', href: '/admin/cekbot/inbox', icon: MessageCircle },
  { label: 'Auto-Reply', href: '/admin/cekbot/auto-reply', icon: Zap },
  { label: 'Produk', href: '/admin/cekbot/products', icon: Package },
  { label: 'Broadcast', href: '/admin/cekbot/broadcast', icon: Megaphone },
  { label: 'Analitik', href: '/admin/cekbot/analytics', icon: BarChart3 },
  { label: 'Tetapan', href: '/admin/cekbot/settings', icon: Settings },
];

function isActive(url, href, exact) {
  const path = url.split('?')[0];
  if (exact) return path === href || path === `${href}/`;
  return path === href || path.startsWith(`${href}/`);
}

function Brand() {
  return (
    <div className="flex items-center gap-3">
      <div className="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-gradient-to-br from-emerald-500 to-green-500 text-white shadow-[0_8px_20px_-8px_rgba(16,185,129,0.7)]">
        <Bot className="h-[18px] w-[18px]" strokeWidth={2.2} />
      </div>
      <div className="min-w-0">
        <div className="text-[15px] font-semibold leading-tight tracking-[-0.02em] text-white">Cekbot</div>
        <div className="text-[11px] font-medium text-white/50">WhatsApp Numbers</div>
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
                ? 'bg-emerald-500/15 text-emerald-400 shadow-[inset_0_0_0_1px_rgba(16,185,129,0.15)]'
                : 'text-white/60 hover:bg-white/5 hover:text-white'
            )}
          >
            <Icon
              className={cn('h-[17px] w-[17px] transition-colors', active ? 'text-emerald-400' : 'text-white/40 group-hover:text-white/70')}
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
  return (
    <div className="space-y-2">
      <div className="flex items-center gap-3 rounded-2xl bg-white/5 p-2.5">
        <div className="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-gradient-to-br from-emerald-500 to-green-500 text-[12px] font-semibold text-white">
          {initialsFrom(user?.name)}
        </div>
        <div className="min-w-0 flex-1">
          <div className="truncate text-[13px] font-semibold leading-tight text-white">{user?.name ?? 'Admin'}</div>
          <div className="mt-0.5 truncate text-[11px] text-white/45">{user?.email ?? ''}</div>
        </div>
      </div>
      <a
        href="/admin"
        className="flex items-center gap-2 rounded-xl px-3 py-2 text-[12.5px] font-medium text-white/45 transition-colors hover:bg-white/5 hover:text-white/80"
      >
        <ArrowLeft className="h-[15px] w-[15px]" strokeWidth={2} />
        Kembali ke Admin
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
        aria-label="Buka menu"
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
            aria-label="Tutup menu"
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

export default function CekbotLayout({ children, title, subtitle, actions }) {
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
        <div className="panel min-h-[calc(100dvh-2rem)] rounded-[20px] p-5 sm:p-7">
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
          style: { background: '#0B1A14', color: '#F0FDF4', fontSize: '13.5px', borderRadius: '12px', border: '1px solid rgba(255,255,255,0.1)' },
          success: { iconTheme: { primary: '#10B981', secondary: '#0B1A14' } },
          error: { iconTheme: { primary: '#F43F5E', secondary: '#0B1A14' } },
        }}
      />
    </div>
  );
}
