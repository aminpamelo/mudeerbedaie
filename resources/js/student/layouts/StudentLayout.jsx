import { usePage } from '@inertiajs/react';
import { BookOpen, GraduationCap, Home, User, MessageCircleMore } from 'lucide-react';
import { cn, t } from '@/student/lib/utils';
import NotificationBell from '@/student/components/NotificationBell';
import UserMenu from '@/student/components/UserMenu';
import { BrandMark, MosqueSilhouette, IslamicDivider } from '@/student/components/BrandArt';

function navItems(trans) {
  return [
    {
      label: t('navigation.home', {}, trans),
      short: t('navigation.home', {}, trans),
      href: '/my',
      icon: Home,
      match: (p) => p === '/my' || p === '/my/',
    },
    {
      label: 'Kelas Saya',
      short: t('navigation.classes', {}, trans),
      href: '/my/classes',
      icon: GraduationCap,
      match: (p) => p.startsWith('/my/classes') || p.startsWith('/my/timetable'),
    },
    {
      label: 'Kursus / Kedai / Blog Bacaan',
      short: t('navigation.courses', {}, trans),
      href: '/my/courses',
      icon: BookOpen,
      match: (p) => p.startsWith('/my/courses'),
    },
    {
      label: 'Tanya Ilmu',
      short: 'Tanya',
      href: '/my/mindpal',
      icon: MessageCircleMore,
      match: (p) => p.startsWith('/my/mindpal'),
    },
    {
      label: t('navigation.account', {}, trans),
      short: t('navigation.account', {}, trans),
      href: '/my/account',
      icon: User,
      match: (p) =>
        p.startsWith('/my/account') || p.startsWith('/my/orders') ||
        p.startsWith('/my/payment') || p.startsWith('/my/subscriptions') ||
        p.startsWith('/my/refund'),
    },
  ];
}

/* ------------------------------------------------------------------ */
/*  Desktop sidebar                                                    */
/* ------------------------------------------------------------------ */
function Sidebar({ url, translations }) {
  const path = url.split('?')[0];
  const items = navItems(translations);

  return (
    <aside className="sidebar-shell fixed inset-y-0 left-0 z-40 hidden w-[264px] flex-col lg:flex">
      <div className="px-5 pb-5 pt-6">
        <a href="/my" className="inline-flex">
          <BrandMark />
        </a>
      </div>

      <nav className="flex-1 space-y-1 px-3">
        {items.map((item) => {
          const Icon = item.icon;
          const active = item.match(path);
          return (
            <a
              key={item.href}
              href={item.href}
              className={cn(
                'group relative flex items-center gap-3 rounded-xl px-3 py-2.5 text-[14px] transition-all',
                active
                  ? 'sidebar-link-active font-semibold'
                  : 'font-medium text-ink-2 hover:bg-brand-soft/70 hover:text-brand-ink'
              )}
            >
              {active && (
                <span className="absolute left-0 top-1/2 h-6 w-1 -translate-y-1/2 rounded-r-full bg-brand" />
              )}
              <Icon
                className={cn('h-5 w-5 shrink-0', active ? 'text-brand' : 'text-muted group-hover:text-brand')}
                strokeWidth={active ? 2.2 : 1.8}
              />
              <span className="leading-tight">{item.label}</span>
            </a>
          );
        })}
      </nav>

      <div className="px-5 pb-7 pt-4">
        <MosqueSilhouette className="mx-auto h-20 w-auto text-brand/15" />
        <p className="mt-3 text-center text-[12px] italic leading-relaxed text-muted">
          Ilmu, hari ini,
          <br />
          amal esok,
          <br />
          syurga nanti.
        </p>
        <IslamicDivider className="mt-3" />
      </div>
    </aside>
  );
}

/* ------------------------------------------------------------------ */
/*  Light top bar (bell + user)                                        */
/* ------------------------------------------------------------------ */
function TopBar({ user }) {
  return (
    <header className="sticky top-0 z-30 border-b border-line/70 bg-white/80 backdrop-blur-md">
      <div className="flex h-16 items-center justify-between gap-3 px-4 sm:px-6 lg:px-8">
        <div className="lg:hidden">
          <a href="/my" className="inline-flex">
            <BrandMark compact />
          </a>
        </div>
        <div className="hidden lg:block" />
        <div className="flex items-center gap-1.5">
          <NotificationBell />
          <UserMenu user={user} />
        </div>
      </div>
    </header>
  );
}

/* ------------------------------------------------------------------ */
/*  Mobile bottom nav                                                  */
/* ------------------------------------------------------------------ */
function BottomNav({ url, translations }) {
  const path = url.split('?')[0];
  const items = navItems(translations);

  return (
    <nav className="fixed inset-x-0 bottom-0 z-50 border-t border-line bg-white/95 backdrop-blur-lg lg:hidden">
      <div style={{ paddingBottom: 'env(safe-area-inset-bottom)' }}>
        <div className="grid grid-cols-5">
          {items.map((tab) => {
            const Icon = tab.icon;
            const active = tab.match(path);
            return (
              <a
                key={tab.href}
                href={tab.href}
                className={cn(
                  'flex flex-col items-center gap-1 py-2 transition-colors',
                  active ? 'text-brand' : 'text-muted-2 hover:text-ink'
                )}
              >
                <div className={cn('grid h-8 w-8 place-items-center rounded-xl transition-colors', active && 'bg-brand-soft')}>
                  <Icon className="h-[19px] w-[19px]" strokeWidth={active ? 2.2 : 1.7} />
                </div>
                <span className={cn('whitespace-nowrap text-[10px] font-semibold leading-none', active && 'text-brand-ink')}>
                  {tab.short}
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

  if (typeof window !== 'undefined') {
    window.__studentTranslations = translations;
  }

  return (
    <div className="min-h-dvh">
      <Sidebar url={url} translations={translations} />

      <div className="lg:pl-[264px]">
        <TopBar user={user} />

        <main className="mx-auto max-w-6xl px-4 pb-28 pt-4 sm:px-6 lg:px-8 lg:pb-12">
          {hero}
          {children}
        </main>
      </div>

      <BottomNav url={url} translations={translations} />
    </div>
  );
}
