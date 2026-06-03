import { usePage } from '@inertiajs/react';
import { LayoutDashboard, LogOut } from 'lucide-react';
import { cn } from '@/ceo/lib/utils';

function initialsFrom(name) {
  if (!name) return '?';
  return (
    name
      .split(' ')
      .filter(Boolean)
      .slice(0, 2)
      .map((part) => part[0]?.toUpperCase() ?? '')
      .join('') || '?'
  );
}

/**
 * Both targets redirect to non-Inertia pages, so a native <form> submit is used
 * instead of router.post() (which would render the HTML response in a modal).
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

function Sidebar({ auth, brand, currentUrl }) {
  const user = auth?.user;
  const brandName = brand?.name || 'CEO Overview';
  const brandLogoUrl = brand?.logoUrl || null;
  const brandInitial = initialsFrom(brandName).charAt(0) || '?';

  const navItems = [{ key: 'dashboard', label: 'Overview', href: '/ceo', icon: LayoutDashboard }];
  const isActive = (href) => currentUrl === href || currentUrl === `${href}/` || currentUrl.startsWith(`${href}?`);

  const handleLogout = () => {
    if (!window.confirm('Log out of CEO Overview?')) return;
    submitNativeForm('/logout');
  };

  return (
    <aside className="sticky top-0 flex h-screen flex-col gap-7 border-r border-border-2 px-4 py-6">
      <div className="flex items-center gap-[10px] px-2 py-1">
        {brandLogoUrl ? (
          <div className="grid h-8 w-8 place-items-center overflow-hidden rounded-[10px] bg-white shadow-[0_4px_12px_rgba(10,10,10,0.12)]">
            <img src={brandLogoUrl} alt={brandName} className="h-8 w-8 object-contain" />
          </div>
        ) : (
          <div
            className="grid h-8 w-8 place-items-center rounded-[10px] bg-ink text-[16px] font-bold text-white"
            style={{ letterSpacing: '-0.04em' }}
          >
            {brandInitial}
          </div>
        )}
        <div>
          <div className="text-[15px] font-semibold leading-tight tracking-[-0.02em] text-ink">{brandName}</div>
          <div className="text-[11px] font-medium text-muted">CEO Overview</div>
        </div>
      </div>

      <nav className="flex flex-1 flex-col gap-0.5">
        <div className="px-3 pb-1.5 text-[11px] font-medium uppercase tracking-[0.02em] text-muted-2">Executive</div>
        {navItems.map((item) => {
          const Icon = item.icon;
          const active = isActive(item.href);
          return (
            <a
              key={item.href}
              href={item.href}
              className={cn(
                'group flex items-center gap-[10px] rounded-lg px-3 py-2 text-[13.5px] font-medium transition-colors',
                active ? 'bg-ink text-white' : 'text-ink-2 hover:bg-surface-2 hover:text-ink'
              )}
            >
              <Icon className={cn('h-[15px] w-[15px]', active ? 'text-white' : 'text-muted group-hover:text-ink')} strokeWidth={2} />
              <span>{item.label}</span>
            </a>
          );
        })}
      </nav>

      <div className="flex items-center gap-[10px] rounded-[10px] border border-border bg-surface p-[10px]">
        <div className="grid h-8 w-8 shrink-0 place-items-center rounded-lg bg-gradient-to-br from-ink to-[#404040] text-[12px] font-semibold text-white">
          {initialsFrom(user?.name)}
        </div>
        <div className="min-w-0 flex-1">
          <div className="truncate text-[13px] font-medium leading-tight text-ink">{user?.name ?? 'Guest'}</div>
          <div className="mt-0.5 truncate text-[11px] text-muted">{user?.email ?? ''}</div>
        </div>
        <button
          type="button"
          onClick={handleLogout}
          className="grid h-8 w-8 shrink-0 place-items-center rounded-lg text-muted transition-colors hover:bg-surface-2 hover:text-ink"
          aria-label="Log out"
          title="Log out"
        >
          <LogOut className="h-[15px] w-[15px]" strokeWidth={2} />
        </button>
      </div>
    </aside>
  );
}

export default function CeoLayout({ children }) {
  const { props, url } = usePage();
  const auth = props.auth ?? {};
  const brand = props.brand ?? {};

  return (
    <div className="grid min-h-screen grid-cols-[240px_1fr]">
      <Sidebar auth={auth} brand={brand} currentUrl={url} />
      <main className="flex min-w-0 flex-col">{children}</main>
    </div>
  );
}
