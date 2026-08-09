import { Head, router, usePage } from '@inertiajs/react';
import { useState, useCallback } from 'react';
import {
  ShoppingBag, Wallet, ClipboardList, ChevronRight,
  X, SlidersHorizontal, ChevronDown, CircleAlert,
} from 'lucide-react';
import StudentLayout from '@/student/layouts/StudentLayout';
import PageHeader, { HeroStat } from '@/student/components/PageHeader';
import StatusBadge, { statusVariant } from '@/student/components/StatusBadge';
import EmptyState from '@/student/components/EmptyState';
import Pagination from '@/student/components/Pagination';
import { cn, formatMoney, t } from '@/student/lib/utils';

/* ------------------------------------------------------------------ */
/*  Filter Bar                                                         */
/* ------------------------------------------------------------------ */
function FilterBar({ filters, courses, statuses, onFilter }) {
  const [open, setOpen] = useState(false);
  const hasFilters = filters.status || filters.course;

  const update = (key, val) => onFilter({ ...filters, [key]: val });
  const clear = () => onFilter({ status: '', course: '' });

  return (
    <div className="fade-up mb-5" style={{ animationDelay: '0.1s' }}>
      {/* Mobile toggle */}
      <button
        type="button"
        onClick={() => setOpen(!open)}
        className="glass-card flex w-full items-center justify-between rounded-2xl px-4 py-3 text-[13px] font-medium shadow-sm lg:hidden"
      >
        <span className="flex items-center gap-2 text-violet-700">
          <SlidersHorizontal className="h-4 w-4" strokeWidth={2} />
          {t('student.classes.filters')}
          {hasFilters && (
            <span className="grid h-5 min-w-5 place-items-center rounded-full bg-[var(--color-accent)] px-1.5 text-[10px] font-bold text-white">
              {[filters.status, filters.course].filter(Boolean).length}
            </span>
          )}
        </span>
        <ChevronDown className={cn('h-4 w-4 text-violet-400 transition-transform', open && 'rotate-180')} strokeWidth={2} />
      </button>

      {/* Desktop always visible, mobile collapsible */}
      <div className={cn('mt-2 grid grid-cols-1 gap-3 sm:grid-cols-3', open ? '' : 'hidden lg:grid')}>
        <div className="glass-card rounded-2xl shadow-sm lg:rounded-2xl">
          <select
            value={filters.status}
            onChange={(e) => update('status', e.target.value)}
            className="w-full rounded-2xl border-0 bg-transparent px-4 py-3 text-[13px] text-ink outline-none"
          >
            <option value="">{t('student.orders.all_statuses')}</option>
            {Object.entries(statuses).map(([v, l]) => (
              <option key={v} value={v}>{l}</option>
            ))}
          </select>
        </div>

        <div className="glass-card rounded-2xl shadow-sm">
          <select
            value={filters.course}
            onChange={(e) => update('course', e.target.value)}
            className="w-full rounded-2xl border-0 bg-transparent px-4 py-3 text-[13px] text-ink outline-none"
          >
            <option value="">{t('student.courses.all_courses')}</option>
            {courses.map((c) => (
              <option key={c.id} value={c.id}>{c.name}</option>
            ))}
          </select>
        </div>

        {hasFilters && (
          <button
            type="button"
            onClick={clear}
            className="flex items-center justify-center gap-1.5 rounded-2xl border border-rose-200 bg-rose-50 px-3 py-2.5 text-[12px] font-semibold text-rose-600 transition-colors hover:bg-rose-100"
          >
            <X className="h-3.5 w-3.5" strokeWidth={2.5} />
            {t('student.courses.clear_filters')}
          </button>
        )}
      </div>
    </div>
  );
}

/* ------------------------------------------------------------------ */
/*  Order Card                                                         */
/* ------------------------------------------------------------------ */
function OrderCard({ order }) {
  return (
    <div className="glass-card overflow-hidden rounded-2xl shadow-sm transition-all hover:-translate-y-0.5 hover:shadow-md">
      <div className="p-4">
        <div className="flex items-start justify-between gap-3">
          <div className="min-w-0 flex-1">
            <div className="flex flex-wrap items-center gap-2">
              <h3 className="text-[14px] font-bold text-ink truncate">{order.course_name}</h3>
              <StatusBadge variant={statusVariant(order.status)}>{order.status_label}</StatusBadge>
            </div>
            <p className="mt-1 text-[12px] text-muted">Order #{order.order_number}</p>
          </div>
          <p className="shrink-0 text-[16px] font-extrabold text-ink">{order.formatted_amount}</p>
        </div>

        <div className="mt-3 flex flex-wrap gap-x-5 gap-y-1 text-[12px] text-muted">
          <span>{order.period_description}</span>
          <span>{order.created_at}</span>
          {order.paid_at && <span className="text-emerald-600">Paid {order.paid_at}</span>}
        </div>

        {order.is_failed && order.failure_message && (
          <div className="mt-3 flex items-start gap-2 rounded-xl bg-red-50 p-3">
            <CircleAlert className="mt-0.5 h-4 w-4 shrink-0 text-red-500" strokeWidth={2} />
            <p className="text-[12px] font-medium text-red-700">{order.failure_message}</p>
          </div>
        )}
      </div>

      <div className="flex items-center gap-2 border-t border-violet-100/60 bg-violet-50/30 px-4 py-2.5">
        <a
          href={`/my/orders/${order.id}`}
          className="flex items-center gap-1 text-[12px] font-semibold text-violet-600 transition-colors hover:text-violet-800"
        >
          {t('student.orders.view_details')}
          <ChevronRight className="h-3.5 w-3.5" strokeWidth={2} />
        </a>
        {order.is_paid && (
          <>
            <span className="text-violet-200">|</span>
            <a
              href={`/my/orders/${order.id}/receipt`}
              className="text-[12px] font-semibold text-violet-600 transition-colors hover:text-violet-800"
            >
              {t('student.orders.view_receipt')}
            </a>
          </>
        )}
        {order.is_failed && order.has_active_subscription && (
          <>
            <span className="text-violet-200">|</span>
            <a
              href="/my/payment-methods"
              className="text-[12px] font-semibold text-rose-600 transition-colors hover:text-rose-800"
            >
              {t('student.orders.update_payment')}
            </a>
          </>
        )}
      </div>
    </div>
  );
}

/* ================================================================== */
/*  Page                                                               */
/* ================================================================== */
export default function Orders() {
  const { orders, totalPaid, totalOrders, courses, orderStatuses, filters } = usePage().props;

  const applyFilters = useCallback((newFilters) => {
    router.get('/my/orders', Object.fromEntries(
      Object.entries(newFilters).filter(([, v]) => v !== '')
    ), {
      preserveState: true,
      preserveScroll: true,
      replace: true,
    });
  }, []);

  const hero = (
    <PageHeader tag={t('student.orders.my_orders')} title={t('student.orders.my_orders')} subtitle={t('student.orders.view_history')}>
      <HeroStat icon={Wallet} label={t('student.orders.total_paid')} value={formatMoney(totalPaid)} iconClassName="bg-emerald-400/20" />
      <HeroStat icon={ClipboardList} label={t('student.orders.total_orders')} value={totalOrders} />
    </PageHeader>
  );

  return (
    <StudentLayout hero={hero}>
      <Head title={t('student.orders.my_orders')} />

      <div className="pt-4">
        <FilterBar filters={filters} courses={courses} statuses={orderStatuses} onFilter={applyFilters} />

        {orders.data.length > 0 ? (
          <div className="space-y-4">
            {orders.data.map((order) => (
              <OrderCard key={order.id} order={order} />
            ))}
            <Pagination links={orders.links} />
          </div>
        ) : (
          <EmptyState
            icon={ShoppingBag}
            title={t('student.orders.no_orders')}
            description={t('student.orders.orders_appear')}
          />
        )}
      </div>
    </StudentLayout>
  );
}
