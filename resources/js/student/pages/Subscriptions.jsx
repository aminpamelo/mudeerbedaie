import { Head, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import {
  RefreshCw, CreditCard, Play, RotateCcw, XCircle, ChevronRight,
  Clock, CircleAlert,
} from 'lucide-react';
import StudentLayout from '@/student/layouts/StudentLayout';
import PageHeader, { HeroStat } from '@/student/components/PageHeader';
import StatusBadge from '@/student/components/StatusBadge';
import EmptyState from '@/student/components/EmptyState';
import { cn, t } from '@/student/lib/utils';

/* ------------------------------------------------------------------ */
/*  Confirm Action                                                     */
/* ------------------------------------------------------------------ */
function useConfirm() {
  const [pending, setPending] = useState(null);
  return {
    pending,
    ask: (key) => setPending(key),
    cancel: () => setPending(null),
    confirm: (cb) => { cb(); setPending(null); },
  };
}

/* ------------------------------------------------------------------ */
/*  Active Subscription Card                                           */
/* ------------------------------------------------------------------ */
function SubscriptionCard({ sub }) {
  const c = useConfirm();

  const statusBadge = () => {
    if (sub.is_pending_cancellation) return <StatusBadge variant="warning">{sub.status_label}</StatusBadge>;
    if (sub.is_active && sub.is_collection_paused) return <StatusBadge variant="warning">{sub.full_status_description}</StatusBadge>;
    if (sub.is_active) return <StatusBadge variant="success">{sub.status_label}</StatusBadge>;
    if (sub.is_trialing) return <StatusBadge variant="info">{sub.status_label}</StatusBadge>;
    if (sub.is_past_due) return <StatusBadge variant="warning">{sub.status_label}</StatusBadge>;
    return <StatusBadge variant="gray">{sub.status_label}</StatusBadge>;
  };

  const cancelSub = () => router.post(`/my/subscriptions/${sub.id}/cancel`, {}, { preserveScroll: true });
  const resumeSub = () => router.post(`/my/subscriptions/${sub.id}/resume`, {}, { preserveScroll: true });
  const resumeCol = () => router.post(`/my/subscriptions/${sub.id}/resume-collection`, {}, { preserveScroll: true });

  return (
    <div className="fade-up glass-card rounded-2xl p-5 shadow-sm">
      <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div className="min-w-0 flex-1 space-y-3">
          <div className="flex flex-wrap items-center gap-2">
            <h3 className="text-[15px] font-bold text-ink">{sub.course_name}</h3>
            {statusBadge()}
          </div>

          {sub.course_description && (
            <p className="text-[12px] text-muted">{sub.course_description}</p>
          )}

          <div className="flex flex-wrap gap-x-6 gap-y-2 text-[12px]">
            {sub.fee_formatted && (
              <div>
                <span className="font-semibold text-ink">{sub.fee_formatted}</span>
                {sub.billing_cycle_label && <span className="text-muted"> / {sub.billing_cycle_label}</span>}
              </div>
            )}
            {sub.enrollment_date && (
              <div className="text-muted">Started {sub.enrollment_date}</div>
            )}
          </div>

          {sub.is_pending_cancellation && sub.cancellation_date && (
            <div className="flex items-center gap-1.5 text-[12px] font-medium text-amber-600">
              <CircleAlert className="h-4 w-4" strokeWidth={2} />
              Cancels {sub.cancellation_date}
            </div>
          )}

          {sub.is_collection_paused && sub.collection_paused_date && (
            <div className="text-[12px] text-muted">
              Paused since {sub.collection_paused_date}
            </div>
          )}

          {sub.last_order && (
            <div className="text-[12px] text-muted">
              Last payment: {sub.last_order.date} — {sub.last_order.formatted_amount}
              {sub.last_order.is_paid && <StatusBadge variant="success" className="ml-1.5">Paid</StatusBadge>}
              {sub.last_order.is_failed && <StatusBadge variant="danger" className="ml-1.5">Failed</StatusBadge>}
            </div>
          )}
        </div>

        {/* Actions */}
        <div className="flex flex-wrap gap-2 shrink-0">
          {c.pending ? (
            <div className="flex items-center gap-1.5">
              <button
                onClick={() => c.confirm(c.pending === 'cancel' ? cancelSub : c.pending === 'resume' ? resumeSub : resumeCol)}
                className="rounded-xl bg-violet-600 px-3.5 py-2 text-[12px] font-semibold text-white"
              >
                Confirm
              </button>
              <button onClick={c.cancel} className="rounded-xl bg-gray-100 px-3.5 py-2 text-[12px] font-semibold text-gray-600">
                Cancel
              </button>
            </div>
          ) : (
            <>
              {!sub.is_pending_cancellation && (
                <a
                  href="/my/payment-methods"
                  className="flex items-center gap-1 rounded-xl border border-violet-200 px-3 py-2 text-[12px] font-semibold text-violet-700 transition-colors hover:bg-violet-50"
                >
                  <CreditCard className="h-3.5 w-3.5" strokeWidth={2} />
                  {t('student.subscriptions.update_payment')}
                </a>
              )}

              {sub.is_collection_paused && sub.is_active && !sub.is_pending_cancellation && (
                <button
                  onClick={() => c.ask('resumeCol')}
                  className="flex items-center gap-1 rounded-xl bg-emerald-50 px-3 py-2 text-[12px] font-semibold text-emerald-700 transition-colors hover:bg-emerald-100"
                >
                  <Play className="h-3.5 w-3.5" strokeWidth={2} />
                  {t('student.subscriptions.resume_collection')}
                </button>
              )}

              {sub.is_pending_cancellation ? (
                <button
                  onClick={() => c.ask('resume')}
                  className="flex items-center gap-1 rounded-xl hero-gradient px-3.5 py-2 text-[12px] font-semibold text-white shadow-md shadow-violet-300/40"
                >
                  <RotateCcw className="h-3.5 w-3.5" strokeWidth={2} />
                  {t('student.subscriptions.resume_subscription')}
                </button>
              ) : (
                <button
                  onClick={() => c.ask('cancel')}
                  className="flex items-center gap-1 rounded-xl border border-red-200 px-3 py-2 text-[12px] font-semibold text-red-600 transition-colors hover:bg-red-50"
                >
                  <XCircle className="h-3.5 w-3.5" strokeWidth={2} />
                  {t('student.subscriptions.cancel')}
                </button>
              )}
            </>
          )}
        </div>
      </div>
    </div>
  );
}

/* ------------------------------------------------------------------ */
/*  Canceled Subscription Card                                         */
/* ------------------------------------------------------------------ */
function CanceledCard({ sub }) {
  return (
    <div className="glass-card rounded-2xl p-4 opacity-60 shadow-sm">
      <div className="flex items-center justify-between gap-3">
        <div className="min-w-0">
          <p className="text-[14px] font-semibold text-muted">{sub.course_name}</p>
          {sub.enrollment_date && <p className="text-[12px] text-muted-2">Was active from {sub.enrollment_date}</p>}
        </div>
        <StatusBadge variant="gray">{t('student.status.cancelled')}</StatusBadge>
      </div>
    </div>
  );
}

/* ================================================================== */
/*  Page                                                               */
/* ================================================================== */
export default function Subscriptions() {
  const { activeSubscriptions, canceledSubscriptions } = usePage().props;

  const hero = (
    <PageHeader
      tag={t('student.subscriptions.my_subscriptions')}
      title={t('student.subscriptions.my_subscriptions')}
      subtitle={t('student.subscriptions.manage_billing')}
    >
      <HeroStat icon={RefreshCw} label={t('student.subscriptions.active_subscriptions')} value={activeSubscriptions.length} />
    </PageHeader>
  );

  return (
    <StudentLayout hero={hero}>
      <Head title={t('student.subscriptions.my_subscriptions')} />

      <div className="space-y-6 pt-4">
        {/* Manage payment methods link */}
        <div className="fade-up flex justify-end">
          <a
            href="/my/payment-methods"
            className="flex items-center gap-1 text-[13px] font-semibold text-violet-600 transition-colors hover:text-violet-800"
          >
            {t('student.subscriptions.manage_payment_methods')}
            <ChevronRight className="h-4 w-4" strokeWidth={2} />
          </a>
        </div>

        {/* Active */}
        <div>
          <h2 className="mb-3 text-[15px] font-bold text-ink">{t('student.subscriptions.active_subscriptions')}</h2>
          {activeSubscriptions.length > 0 ? (
            <div className="space-y-4">
              {activeSubscriptions.map((sub) => <SubscriptionCard key={sub.id} sub={sub} />)}
            </div>
          ) : (
            <EmptyState
              icon={CreditCard}
              title={t('student.subscriptions.no_active_subscriptions')}
              description={t('student.subscriptions.contact_admin')}
            />
          )}
        </div>

        {/* Canceled */}
        {canceledSubscriptions.length > 0 && (
          <div className="fade-up" style={{ animationDelay: '0.15s' }}>
            <h2 className="mb-3 text-[15px] font-bold text-ink">{t('student.subscriptions.canceled_subscriptions')}</h2>
            <div className="space-y-3">
              {canceledSubscriptions.map((sub) => <CanceledCard key={sub.id} sub={sub} />)}
            </div>
          </div>
        )}
      </div>
    </StudentLayout>
  );
}
