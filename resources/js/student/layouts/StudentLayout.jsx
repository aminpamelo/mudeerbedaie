import { usePage } from '@inertiajs/react';
import { BookOpen, GraduationCap, Calendar, Home, User, Sparkles, BrainCircuit } from 'lucide-react';
import { cn, initialsFrom, t } from '@/student/lib/utils';
import NotificationBell from '@/student/components/NotificationBell';
import UserMenu from '@/student/components/UserMenu';

function navItems(trans) {
  return [
    { label: t('navigation.home', {}, trans), href: '/my', icon: Home, match: (p) => p === '/my' || p === '/my/' },
    { label: t('navigation.classes', {}, trans), href: '/my/classes', icon: GraduationCap, match: (p) => p.startsWith('/my/classes') },
    { label: t('navigation.schedule', {}, trans), href: '/my/timetable', icon: Calendar, match: (p) => p.startsWith('/my/timetable') },
    { label: t('navigation.courses', {}, trans), href: '/my/courses', icon: BookOpen, match: (p) => p.startsWith('/my/courses') },
    { label: 'Tanya Ilmu', href: '/my/mindpal', icon: BrainCircuit, match: (p) => p.startsWith('/my/mindpal') },
    { label: t('navigation.account', {}, trans), href: '/my/account', icon: User, match: (p) => p.startsWith('/my/account') || p.startsWith('/my/orders') || p.startsWith('/my/payment') },
  ];
}

function TopBar({ user, url, translations }) {
  const path = url.split('?')[0];
  const items = navItems(translations);

  return (
    <header className="hero-gradient sticky top-0 z-30 shadow-lg shadow-purple-900/20">
      <div className="dot-pattern">
        <div className="mx-auto flex max-w-7xl items-center justify-between px-4 py-3 sm:px-6 lg:px-8">
          {/* Brand */}
          <div className="flex items-center gap-3">
            <div className="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-white/20 backdrop-blur-sm ring-1 ring-white/30">
              <Sparkles className="h-[18px] w-[18px] text-white" strokeWidth={2.2} />
            </div>
            <div className="min-w-0">
              <div className="text-[15px] font-bold leading-tight tracking-[-0.01em] text-white">Student Portal</div>
              <div className="hidden text-[11px] font-medium text-white/60 sm:block">Mudeer Bedaie</div>
            </div>
          </div>

          {/* Desktop nav links */}
          <nav className="hidden items-center gap-1 lg:flex">
            {items.map((item) => {
              const Icon = item.icon;
              const active = item.match(path);
              return (
                <a
                  key={item.href}
                  href={item.href}
                  className={cn(
                    'flex items-center gap-2 rounded-xl px-3.5 py-2 text-[13px] font-medium transition-all',
                    active
                      ? 'bg-white/20 text-white shadow-inner shadow-white/10 ring-1 ring-white/20'
                      : 'text-white/70 hover:bg-white/10 hover:text-white'
                  )}
                >
                  <Icon className="h-4 w-4" strokeWidth={active ? 2.2 : 1.8} />
                  <span>{item.label}</span>
                </a>
              );
            })}
          </nav>

          {/* User area — notification bell + user dropdown */}
          <div className="flex items-center gap-2">
            <NotificationBell />
            <UserMenu user={user} />
          </div>
        </div>
      </div>
    </header>
  );
}

function BottomNav({ url, translations }) {
  const path = url.split('?')[0];
  const items = navItems(translations);
  const bottomItems = [
    { ...items[0] },
    { ...items[1] },
    { ...items[2], featured: true },
    { ...items[3] },
    { ...items[5] },
  ];

  return (
    <nav className="fixed bottom-0 left-0 right-0 z-50 bg-white/90 backdrop-blur-lg shadow-[0_-4px_20px_-4px_rgba(124,58,237,0.12)] lg:hidden">
      <div className="pb-safe">
        <div className="flex items-end justify-around px-2 py-1.5">
          {bottomItems.map((tab) => {
            const Icon = tab.icon;
            const active = tab.match(path);

            if (tab.featured) {
              return (
                <a
                  key={tab.href}
                  href={tab.href}
                  className="relative -mt-3 flex flex-col items-center px-3 py-1"
                >
                  <div className={cn(
                    'rounded-2xl p-2.5 shadow-lg transition-all',
                    active
                      ? 'hero-gradient shadow-purple-500/40'
                      : 'bg-gradient-to-br from-violet-500 to-rose-500 shadow-violet-500/30 hover:shadow-violet-500/50'
                  )}>
                    <Icon className="h-5 w-5 text-white" strokeWidth={2.2} />
                  </div>
                  <span className={cn(
                    'mt-1 text-[10px] font-bold',
                    active ? 'text-[var(--color-brand)]' : 'text-violet-500'
                  )}>
                    {tab.label}
                  </span>
                </a>
              );
            }

            return (
              <a
                key={tab.href}
                href={tab.href}
                className={cn(
                  'flex flex-col items-center px-3 py-2 transition-colors',
                  active ? 'text-[var(--color-brand)]' : 'text-muted-2 hover:text-ink'
                )}
              >
                <div className={cn(
                  'mb-1 rounded-xl p-1.5 transition-colors',
                  active && 'bg-violet-100'
                )}>
                  <Icon className="h-[18px] w-[18px]" strokeWidth={active ? 2.2 : 1.6} />
                </div>
                <span className={cn('text-[10px] font-semibold leading-none', active && 'text-[var(--color-brand)]')}>
                  {tab.label}
                </span>
              </a>
            );
          })}
        </div>
      </div>
    </nav>
  );
}

export default function StudentLayout({ children, hero }) {
  const { props, url } = usePage();
  const user = props.auth?.user;
  const translations = props.translations ?? {};

  // Store translations globally so t() can access them without prop drilling
  if (typeof window !== 'undefined') {
    window.__studentTranslations = translations;
  }

  return (
    <div className="min-h-dvh">
      <TopBar user={user} url={url} translations={translations} />

      {/* Optional hero section (pages can pass a custom hero) */}
      {hero}

      <main className="mx-auto max-w-7xl px-4 pb-28 sm:px-6 lg:px-8 lg:pb-8">
        {children}
      </main>

      <BottomNav url={url} translations={translations} />
    </div>
  );
}
