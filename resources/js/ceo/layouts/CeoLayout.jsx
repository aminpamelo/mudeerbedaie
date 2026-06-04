import { usePage } from '@inertiajs/react';
import { LayoutDashboard, LogOut, Radio, GraduationCap, ShoppingBag, Users } from 'lucide-react';
import { cn, initialsFrom } from '@/ceo/lib/utils';
import { useT } from '@/ceo/lib/i18n';
import LanguageSwitcher from '@/ceo/components/LanguageSwitcher';
import InstallButton from '@/ceo/components/InstallButton';

const DEPARTMENTS = [
  { key: 'livehost', href: '/ceo/livehost', icon: Radio, accent: 'emerald' },
  { key: 'education', href: '/ceo/education', icon: GraduationCap, accent: 'sky' },
  { key: 'ecommerce', href: '/ceo/ecommerce', icon: ShoppingBag, accent: 'violet' },
  { key: 'hr', href: '/ceo/hr', icon: Users, accent: 'amber' },
];

const ACCENT_HEX = {
  emerald: '#10B981',
  sky: '#0EA5E9',
  violet: '#8B5CF6',
  amber: '#F59E0B',
};

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

function Sidebar({ auth, brand, currentUrl }) {
  const t = useT();
  const user = auth?.user;
  const brandName = brand?.name || 'CEO Overview';
  const brandLogoUrl = brand?.logoUrl || null;
  const brandInitial = initialsFrom(brandName).charAt(0) || '?';

  const overviewActive = currentUrl === '/ceo' || currentUrl === '/ceo/' || currentUrl.startsWith('/ceo?');
  const isDeptActive = (href) => currentUrl === href || currentUrl === `${href}/` || currentUrl.startsWith(`${href}?`);

  const handleLogout = () => {
    if (!window.confirm('Log out of CEO Overview?')) return;
    submitNativeForm('/logout');
  };

  return (
    <aside className="glass sticky top-4 m-4 flex h-[calc(100dvh-2rem)] flex-col gap-7 rounded-[20px] px-4 py-6">
      <div className="flex items-center gap-[10px] px-2 py-1">
        {brandLogoUrl ? (
          <div className="grid h-9 w-9 place-items-center overflow-hidden rounded-xl bg-white shadow-[0_6px_16px_rgba(99,102,241,0.25)]">
            <img src={brandLogoUrl} alt={brandName} className="h-9 w-9 object-contain" />
          </div>
        ) : (
          <div className="grid h-9 w-9 place-items-center rounded-xl bg-gradient-to-br from-[var(--color-brand)] to-[var(--color-violet)] text-[16px] font-bold text-white shadow-[0_6px_16px_rgba(99,102,241,0.35)]" style={{ letterSpacing: '-0.04em' }}>
            {brandInitial}
          </div>
        )}
        <div>
          <div className="text-[15px] font-semibold leading-tight tracking-[-0.02em] text-ink">{brandName}</div>
          <div className="text-[11px] font-medium text-muted">{t('ceo_overview')}</div>
        </div>
      </div>

      <nav className="flex flex-1 flex-col gap-5 overflow-y-auto">
        <div className="flex flex-col gap-0.5">
          <div className="px-3 pb-1.5 text-[11px] font-medium uppercase tracking-[0.04em] text-muted-2">{t('executive')}</div>
          <a
            href="/ceo"
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

        <div className="flex flex-col gap-0.5">
          <div className="px-3 pb-1.5 text-[11px] font-medium uppercase tracking-[0.04em] text-muted-2">{t('departments')}</div>
          {DEPARTMENTS.map((item) => {
            const Icon = item.icon;
            const active = isDeptActive(item.href);
            const hex = ACCENT_HEX[item.accent];
            return (
              <a
                key={item.href}
                href={item.href}
                data-accent={item.accent}
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
                <span>{t(`dept_${item.key}`)}</span>
              </a>
            );
          })}
        </div>
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
    </aside>
  );
}

export default function CeoLayout({ children }) {
  const { props, url } = usePage();
  const auth = props.auth ?? {};
  const brand = props.brand ?? {};

  return (
    <div className="relative z-10 grid min-h-dvh grid-cols-[248px_1fr]">
      <Sidebar auth={auth} brand={brand} currentUrl={url} />
      <main className="flex min-w-0 flex-col">{children}</main>
    </div>
  );
}
