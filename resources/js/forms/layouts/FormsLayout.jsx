import { useState, useEffect } from 'react';
import { Link, usePage } from '@inertiajs/react';
import {
  FileText,
  Plus,
  LayoutGrid,
  Tag,
  Inbox,
  BarChart3,
  Menu,
  X,
  CheckCircle2,
  AlertCircle,
  ArrowLeft,
} from 'lucide-react';

function isActive(url, href, exact = false) {
  const path = url.split('?')[0];
  return exact ? path === href : path === href || path.startsWith(href + '/');
}

function NavItem({ href, icon: Icon, label, url, exact }) {
  const active = isActive(url, href, exact);
  return (
    <Link
      href={href}
      className={`flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition ${
        active ? 'bg-brand text-white shadow-sm' : 'text-slate-600 hover:bg-slate-100'
      }`}
    >
      <Icon className="h-[18px] w-[18px]" />
      {label}
    </Link>
  );
}

function Sidebar({ url, isAdmin, brand }) {
  return (
    <nav className="flex h-full flex-col gap-1 p-4">
      <div className="mb-4 flex items-center gap-2.5 px-2">
        <div className="flex h-9 w-9 items-center justify-center rounded-lg bg-brand text-white">
          <FileText className="h-5 w-5" />
        </div>
        <span className="text-[15px] font-bold text-ink">{brand?.name || 'Borang'}</span>
      </div>

      <NavItem href="/forms" icon={LayoutGrid} label="Borang Saya" url={url} exact />
      <NavItem href="/forms/create" icon={Plus} label="Cipta Borang" url={url} />

      {isAdmin && (
        <>
          <div className="mt-5 mb-1 px-3 text-[11px] font-semibold uppercase tracking-wider text-slate-400">
            Admin
          </div>
          <NavItem href="/forms/admin" icon={FileText} label="Semua Borang" url={url} />
          <NavItem href="/forms/reports" icon={BarChart3} label="Laporan" url={url} />
          <NavItem href="/forms/submissions" icon={Inbox} label="Semua Submission" url={url} />
          <NavItem href="/forms/categories" icon={Tag} label="Kategori" url={url} />
        </>
      )}

      {isAdmin && (
        <a
          href="/dashboard"
          className="mt-auto flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-slate-500 transition hover:bg-slate-100 hover:text-slate-800"
        >
          <ArrowLeft className="h-[18px] w-[18px]" />
          Kembali ke Admin
        </a>
      )}
    </nav>
  );
}

function Flash() {
  const { flash } = usePage().props;
  const [visible, setVisible] = useState(true);

  useEffect(() => {
    setVisible(true);
    if (flash?.success || flash?.error) {
      const t = setTimeout(() => setVisible(false), 4000);
      return () => clearTimeout(t);
    }
  }, [flash?.success, flash?.error]);

  if (!visible || (!flash?.success && !flash?.error)) return null;

  const isError = !!flash?.error;
  return (
    <div
      className={`fixed right-4 top-4 z-50 flex items-center gap-2.5 rounded-xl px-4 py-3 text-sm font-medium shadow-lg fade-up ${
        isError ? 'bg-rose-600 text-white' : 'bg-emerald-600 text-white'
      }`}
    >
      {isError ? <AlertCircle className="h-4 w-4" /> : <CheckCircle2 className="h-4 w-4" />}
      {flash.error || flash.success}
    </div>
  );
}

export default function FormsLayout({ title, subtitle, actions, children }) {
  const { props, url } = usePage();
  const isAdmin = props.isFormsAdmin;
  const brand = props.brand;
  const user = props.auth?.user;
  const [drawer, setDrawer] = useState(false);

  return (
    <div className="lg:grid lg:min-h-dvh lg:grid-cols-[248px_1fr]">
      <Flash />

      {/* Desktop sidebar */}
      <aside className="hidden border-r border-line bg-white lg:block">
        <Sidebar url={url} isAdmin={isAdmin} brand={brand} />
      </aside>

      {/* Mobile drawer */}
      {drawer && (
        <div className="fixed inset-0 z-40 lg:hidden">
          <div className="absolute inset-0 bg-black/40" onClick={() => setDrawer(false)} />
          <aside className="absolute left-0 top-0 h-full w-64 bg-white shadow-xl">
            <button className="absolute right-3 top-4 text-slate-400" onClick={() => setDrawer(false)}>
              <X className="h-5 w-5" />
            </button>
            <Sidebar url={url} isAdmin={isAdmin} brand={brand} />
          </aside>
        </div>
      )}

      <main className="min-w-0">
        {/* Mobile top bar */}
        <div className="flex items-center gap-3 border-b border-line bg-white px-4 py-3 lg:hidden">
          <button onClick={() => setDrawer(true)} className="text-slate-600">
            <Menu className="h-5 w-5" />
          </button>
          <span className="font-semibold text-ink">Borang</span>
        </div>

        <div className="mx-auto max-w-6xl p-5 sm:p-8">
          <div className="mb-6 flex flex-wrap items-start justify-between gap-4">
            <div className="min-w-0">
              <h1 className="text-[22px] font-bold tracking-[-0.02em] text-ink sm:text-[26px]">{title}</h1>
              {subtitle && <p className="mt-1 text-[13.5px] text-muted">{subtitle}</p>}
            </div>
            <div className="flex items-center gap-2">
              {actions}
              {user && (
                <div className="hidden items-center gap-2 rounded-lg bg-white px-3 py-1.5 text-sm text-slate-600 shadow-sm sm:flex">
                  <div className="flex h-7 w-7 items-center justify-center rounded-full bg-brand-soft text-xs font-bold text-brand-ink">
                    {user.name?.charAt(0)?.toUpperCase()}
                  </div>
                  <span className="max-w-[120px] truncate">{user.name}</span>
                </div>
              )}
            </div>
          </div>

          {children}
        </div>
      </main>
    </div>
  );
}
