import { router, usePage } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { ChevronDown, Globe, LogOut, Settings, User } from 'lucide-react';
import { cn, initialsFrom, t } from '@/student/lib/utils';

function submitLogout() {
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

export default function UserMenu({ user }) {
  const { locale } = usePage().props;
  const [open, setOpen] = useState(false);
  const ref = useRef(null);

  // Close on outside click
  useEffect(() => {
    if (!open) return undefined;
    const onClick = (e) => ref.current && !ref.current.contains(e.target) && setOpen(false);
    document.addEventListener('mousedown', onClick);
    return () => document.removeEventListener('mousedown', onClick);
  }, [open]);

  const switchLocale = (newLocale) => {
    if (newLocale === locale) return;
    router.post('/my/locale', { locale: newLocale }, { preserveScroll: true });
    setOpen(false);
  };

  const handleLogout = () => {
    setOpen(false);
    submitLogout();
  };

  return (
    <div className="relative" ref={ref}>
      {/* Trigger — avatar pill */}
      <button
        type="button"
        onClick={() => setOpen(!open)}
        className={cn(
          'flex items-center gap-2 rounded-xl py-1.5 pl-1.5 pr-2.5 transition-all',
          'bg-white/10 ring-1 ring-white/15 hover:bg-white/20',
          open && 'bg-white/20'
        )}
      >
        <div className="grid h-7 w-7 shrink-0 place-items-center rounded-lg bg-gradient-to-br from-violet-400 to-rose-400 text-[11px] font-bold text-white">
          {initialsFrom(user?.name)}
        </div>
        <span className="hidden max-w-[120px] truncate text-[13px] font-medium text-white/90 sm:block">
          {user?.name}
        </span>
        <ChevronDown className={cn(
          'h-3.5 w-3.5 text-white/50 transition-transform',
          open && 'rotate-180'
        )} strokeWidth={2} />
      </button>

      {/* Dropdown */}
      {open && (
        <div className="absolute right-0 z-50 mt-2 w-[240px] overflow-hidden rounded-2xl bg-white shadow-[0_20px_50px_-12px_rgba(124,58,237,0.35)] ring-1 ring-violet-100">
          {/* User info header */}
          <div className="border-b border-violet-50 bg-gradient-to-r from-violet-50 to-rose-50 px-4 py-3.5">
            <div className="flex items-center gap-3">
              <div className="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-gradient-to-br from-violet-500 to-rose-500 text-[13px] font-bold text-white">
                {initialsFrom(user?.name)}
              </div>
              <div className="min-w-0 flex-1">
                <p className="truncate text-[14px] font-semibold text-ink">{user?.name}</p>
                <p className="truncate text-[12px] text-muted">{user?.email}</p>
              </div>
            </div>
          </div>

          {/* Menu items */}
          <div className="p-1.5">
            {/* Profile / Settings link */}
            <a
              href="/my/account"
              onClick={() => setOpen(false)}
              className="flex items-center gap-3 rounded-xl px-3 py-2.5 text-[13px] font-medium text-ink transition-colors hover:bg-violet-50"
            >
              <Settings className="h-4 w-4 text-muted" strokeWidth={1.8} />
              {t('student.account.settings')}
            </a>

            {/* Language switcher */}
            <div className="px-3 py-2.5">
              <div className="mb-2 flex items-center gap-2">
                <Globe className="h-4 w-4 text-muted" strokeWidth={1.8} />
                <span className="text-[13px] font-medium text-ink">{t('student.account.language')}</span>
              </div>
              <div className="flex gap-1.5 rounded-xl bg-violet-50/80 p-1">
                <button
                  type="button"
                  onClick={() => switchLocale('ms')}
                  className={cn(
                    'flex flex-1 items-center justify-center gap-1.5 rounded-lg px-3 py-2 text-[12px] font-semibold transition-all',
                    locale === 'ms'
                      ? 'bg-white text-violet-700 shadow-sm ring-1 ring-violet-200'
                      : 'text-muted hover:text-ink'
                  )}
                >
                  <span className="text-[14px]">🇲🇾</span>
                  BM
                </button>
                <button
                  type="button"
                  onClick={() => switchLocale('en')}
                  className={cn(
                    'flex flex-1 items-center justify-center gap-1.5 rounded-lg px-3 py-2 text-[12px] font-semibold transition-all',
                    locale === 'en'
                      ? 'bg-white text-violet-700 shadow-sm ring-1 ring-violet-200'
                      : 'text-muted hover:text-ink'
                  )}
                >
                  <span className="text-[14px]">🇬🇧</span>
                  EN
                </button>
              </div>
            </div>
          </div>

          {/* Logout */}
          <div className="border-t border-violet-50 p-1.5">
            <button
              type="button"
              onClick={handleLogout}
              className="flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-[13px] font-medium text-rose-600 transition-colors hover:bg-rose-50"
            >
              <LogOut className="h-4 w-4" strokeWidth={1.8} />
              {t('student.account.logout')}
            </button>
          </div>
        </div>
      )}
    </div>
  );
}
