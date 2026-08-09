import { Head, router, usePage } from '@inertiajs/react';
import {
  ShoppingBag, RefreshCw, CreditCard,
  UserCircle, Lock, Globe, LogOut, ChevronRight,
} from 'lucide-react';
import StudentLayout from '@/student/layouts/StudentLayout';
import PageHeader from '@/student/components/PageHeader';
import StatusBadge from '@/student/components/StatusBadge';
import { cn, t } from '@/student/lib/utils';

/* ------------------------------------------------------------------ */
/*  Menu Item                                                          */
/* ------------------------------------------------------------------ */
function MenuItem({ href, icon: Icon, iconBg, label, description, badge, badgeVariant }) {
  return (
    <a href={href} className="group block">
      <div className="glass-card flex items-center gap-4 rounded-2xl p-4 shadow-sm transition-all hover:-translate-y-0.5 hover:shadow-md">
        <div className={cn('grid h-10 w-10 shrink-0 place-items-center rounded-xl transition-colors', iconBg)}>
          <Icon className="h-5 w-5" strokeWidth={2} />
        </div>
        <div className="min-w-0 flex-1">
          <p className="text-[13px] font-semibold text-ink">{label}</p>
          <p className="text-[12px] text-muted">{description}</p>
        </div>
        {badge > 0 && <StatusBadge variant={badgeVariant}>{badge}</StatusBadge>}
        <ChevronRight className="h-5 w-5 shrink-0 text-violet-300 transition-transform group-hover:translate-x-0.5" strokeWidth={1.8} />
      </div>
    </a>
  );
}

/* ------------------------------------------------------------------ */
/*  Language Switcher                                                  */
/* ------------------------------------------------------------------ */
function LanguageSwitcher() {
  const { locale } = usePage().props;

  const switchTo = (l) => {
    router.post('/my/locale', { locale: l }, { preserveScroll: true });
  };

  return (
    <div className="glass-card rounded-2xl p-4 shadow-sm">
      <div className="flex items-center gap-4">
        <div className="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-emerald-50 text-emerald-600">
          <Globe className="h-5 w-5" strokeWidth={2} />
        </div>
        <div className="min-w-0 flex-1">
          <p className="text-[13px] font-semibold text-ink">{t('student.account.language')}</p>
          <p className="text-[12px] text-muted">{t('student.account.select_language')}</p>
        </div>
      </div>
      <div className="mt-3 grid grid-cols-2 gap-2">
        {[{ code: 'ms', label: 'Bahasa Malaysia' }, { code: 'en', label: 'English' }].map((l) => (
          <button
            key={l.code}
            type="button"
            onClick={() => switchTo(l.code)}
            className={cn(
              'rounded-xl py-2.5 text-[13px] font-semibold transition-all',
              locale === l.code
                ? 'hero-gradient text-white shadow-md shadow-violet-300/40'
                : 'bg-violet-50 text-violet-700 hover:bg-violet-100'
            )}
          >
            {l.label}
          </button>
        ))}
      </div>
    </div>
  );
}

/* ------------------------------------------------------------------ */
/*  Logout Button                                                      */
/* ------------------------------------------------------------------ */
function LogoutButton() {
  const handleLogout = () => {
    router.post('/logout');
  };

  return (
    <button
      type="button"
      onClick={handleLogout}
      className="flex w-full items-center justify-center gap-2 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-[14px] font-semibold text-red-600 transition-colors hover:bg-red-100"
    >
      <LogOut className="h-5 w-5" strokeWidth={2} />
      {t('student.account.logout')}
    </button>
  );
}

/* ================================================================== */
/*  Page                                                               */
/* ================================================================== */
export default function Account() {
  const { user, pendingOrdersCount, activeSubscriptionsCount } = usePage().props;

  const hero = (
    <PageHeader title={user.name} subtitle={user.email} />
  );

  return (
    <StudentLayout hero={hero}>
      <Head title={t('navigation.account')} />

      <div className="space-y-5 pt-4">
        {/* Profile Card */}
        <div className="fade-up glass-card flex items-center gap-4 rounded-2xl p-5 shadow-sm">
          <div className="grid h-16 w-16 shrink-0 place-items-center rounded-full bg-gradient-to-br from-violet-200 to-rose-200 shadow-inner">
            <span className="text-[22px] font-extrabold text-violet-700">{user.initials}</span>
          </div>
          <div className="min-w-0">
            <p className="text-[17px] font-bold text-ink truncate">{user.name}</p>
            <p className="text-[13px] text-muted truncate">{user.email}</p>
            {user.phone && <p className="text-[12px] text-muted">{user.phone}</p>}
          </div>
        </div>

        {/* Pending Orders Alert */}
        {pendingOrdersCount > 0 && (
          <a href="/my/orders" className="fade-up block" style={{ animationDelay: '0.05s' }}>
            <div className="glass-card rounded-2xl border border-amber-200/60 bg-amber-50/70 p-4 text-center shadow-sm transition-all hover:shadow-md">
              <p className="text-[22px] font-extrabold text-amber-600">{pendingOrdersCount}</p>
              <p className="text-[12px] font-medium text-amber-700">{t('student.account.pending_orders')}</p>
            </div>
          </a>
        )}

        {/* Menu Items */}
        <div className="fade-up space-y-3" style={{ animationDelay: '0.1s' }}>
          <MenuItem href="/my/orders" icon={ShoppingBag} iconBg="bg-violet-50 text-violet-600" label={t('student.account.orders')} description={t('student.account.view_order_history')} badge={pendingOrdersCount} badgeVariant="warning" />
          <MenuItem href="/my/subscriptions" icon={RefreshCw} iconBg="bg-emerald-50 text-emerald-600" label={t('student.account.subscriptions')} description={t('student.account.manage_subscriptions')} badge={activeSubscriptionsCount} badgeVariant="success" />
          <MenuItem href="/my/payment-methods" icon={CreditCard} iconBg="bg-violet-50 text-violet-600" label={t('student.account.payment_methods')} description={t('student.account.manage_cards')} />
        </div>

        {/* Settings Section */}
        <div className="fade-up pt-2" style={{ animationDelay: '0.15s' }}>
          <p className="mb-3 px-1 text-[11px] font-bold uppercase tracking-wider text-muted">{t('student.account.settings')}</p>

          <div className="space-y-3">
            <MenuItem href="/settings/profile" icon={UserCircle} iconBg="bg-gray-100 text-gray-600" label={t('student.account.profile_settings')} description={t('student.account.update_info')} />
            <MenuItem href="/settings/password" icon={Lock} iconBg="bg-gray-100 text-gray-600" label={t('student.account.password')} description={t('student.account.change_password')} />
            <LanguageSwitcher />
          </div>
        </div>

        {/* Logout */}
        <div className="fade-up pt-2" style={{ animationDelay: '0.2s' }}>
          <LogoutButton />
        </div>
      </div>
    </StudentLayout>
  );
}
