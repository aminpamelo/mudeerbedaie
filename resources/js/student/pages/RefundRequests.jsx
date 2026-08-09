import { Head, router, usePage } from '@inertiajs/react';
import { useState, useCallback } from 'react';
import { RefreshCw, Plus, ChevronRight, X, ChevronDown } from 'lucide-react';
import StudentLayout from '@/student/layouts/StudentLayout';
import PageHeader, { HeroStat } from '@/student/components/PageHeader';
import StatusBadge from '@/student/components/StatusBadge';
import EmptyState from '@/student/components/EmptyState';
import Pagination from '@/student/components/Pagination';
import { cn, formatMoney, t } from '@/student/lib/utils';

const statusOptions = [
  { value: 'pending_review', label: 'Pending Review' },
  { value: 'approved_pending_return', label: 'Approved - Pending Return' },
  { value: 'item_received', label: 'Item Received' },
  { value: 'refund_processing', label: 'Refund Processing' },
  { value: 'refund_completed', label: 'Completed' },
  { value: 'rejected', label: 'Rejected' },
  { value: 'cancelled', label: 'Cancelled' },
];

function RefundCard({ refund }) {
  return (
    <div className="fade-up glass-card overflow-hidden rounded-2xl shadow-sm transition-all hover:-translate-y-0.5 hover:shadow-md">
      <div className="p-4">
        <div className="flex flex-wrap items-center gap-2 mb-2">
          <h3 className="text-[14px] font-bold text-ink">{refund.refund_number}</h3>
          <StatusBadge variant={refund.status_color === 'green' ? 'success' : refund.status_color === 'red' ? 'danger' : refund.status_color === 'yellow' ? 'warning' : refund.status_color === 'blue' ? 'info' : refund.status_color === 'violet' ? 'purple' : 'gray'}>
            {refund.status_label}
          </StatusBadge>
          <StatusBadge variant={refund.action_color === 'green' ? 'success' : refund.action_color === 'red' ? 'danger' : refund.action_color === 'yellow' ? 'warning' : 'gray'}>
            {refund.action_label}
          </StatusBadge>
        </div>

        <div className="grid grid-cols-2 gap-3 text-[12px] sm:grid-cols-4">
          <div><span className="text-muted">Order</span><p className="font-medium text-ink">{refund.order_number}</p></div>
          <div><span className="text-muted">Refund Amount</span><p className="font-semibold text-emerald-600">RM {Number(refund.refund_amount).toFixed(2)}</p></div>
          <div><span className="text-muted">Submitted</span><p className="text-ink">{refund.created_at}</p></div>
          <div><span className="text-muted">Return Date</span><p className="text-ink">{refund.return_date}</p></div>
        </div>

        {refund.reason && (
          <div className="mt-3 rounded-xl bg-violet-50/50 p-3">
            <p className="text-[11px] font-semibold text-muted">{t('student.refunds.reason')}</p>
            <p className="text-[12px] text-ink">{refund.reason}</p>
          </div>
        )}

        {refund.action_reason && (
          <div className={cn('mt-3 rounded-xl p-3', refund.action_is_approved ? 'bg-emerald-50' : 'bg-red-50')}>
            <p className={cn('text-[11px] font-semibold', refund.action_is_approved ? 'text-emerald-600' : 'text-red-600')}>{t('student.refunds.admin_response')}</p>
            <p className="text-[12px] text-ink">{refund.action_reason}</p>
          </div>
        )}
      </div>

      <a
        href={`/my/refund-requests/${refund.id}`}
        className="flex items-center justify-center gap-1 border-t border-violet-100/60 bg-violet-50/30 py-2.5 text-[12px] font-semibold text-violet-600 transition-colors hover:bg-violet-50/60"
      >
        {t('student.refunds.view_details')}
        <ChevronRight className="h-3.5 w-3.5" strokeWidth={2} />
      </a>
    </div>
  );
}

export default function RefundRequests() {
  const { refunds, stats, filters } = usePage().props;
  const [open, setOpen] = useState(false);

  const applyFilters = useCallback((newFilters) => {
    router.get('/my/refund-requests', Object.fromEntries(
      Object.entries(newFilters).filter(([, v]) => v !== '')
    ), { preserveState: true, preserveScroll: true, replace: true });
  }, []);

  const hero = (
    <PageHeader tag={t('student.refunds.title')} title={t('student.refunds.title')} subtitle={t('student.refunds.subtitle')}>
      <HeroStat icon={RefreshCw} label={t('student.refunds.total')} value={stats.total} />
    </PageHeader>
  );

  return (
    <StudentLayout hero={hero}>
      <Head title={t('student.refunds.title')} />

      <div className="space-y-5 pt-4">
        {/* Stats */}
        <div className="fade-up grid grid-cols-4 gap-3">
          {[
            { label: t('student.refunds.total'), value: stats.total, color: 'text-gray-700' },
            { label: t('student.refunds.pending'), value: stats.pending, color: 'text-amber-600' },
            { label: t('student.refunds.approved'), value: stats.approved, color: 'text-emerald-600' },
            { label: t('student.refunds.completed_count'), value: stats.completed, color: 'text-violet-600' },
          ].map((s, i) => (
            <div key={i} className="glass-card rounded-2xl p-3 text-center shadow-sm">
              <p className={cn('text-[20px] font-extrabold', s.color)}>{s.value}</p>
              <p className="text-[11px] font-medium text-muted">{s.label}</p>
            </div>
          ))}
        </div>

        {/* New Request + Filter */}
        <div className="fade-up flex items-center justify-between gap-3" style={{ animationDelay: '0.05s' }}>
          <a
            href="/my/refund-requests/create"
            className="flex items-center gap-1.5 rounded-xl hero-gradient px-4 py-2.5 text-[13px] font-semibold text-white shadow-md shadow-violet-300/40"
          >
            <Plus className="h-4 w-4" strokeWidth={2.5} />
            {t('student.refunds.new_request')}
          </a>

          <div className="flex items-center gap-2">
            <div className="glass-card rounded-2xl shadow-sm">
              <select
                value={filters.status}
                onChange={(e) => applyFilters({ status: e.target.value })}
                className="rounded-2xl border-0 bg-transparent px-4 py-2.5 text-[13px] text-ink outline-none"
              >
                <option value="">{t('student.refunds.all_status')}</option>
                {statusOptions.map((o) => <option key={o.value} value={o.value}>{o.label}</option>)}
              </select>
            </div>
            {filters.status && (
              <button onClick={() => applyFilters({ status: '' })} className="text-[12px] font-semibold text-rose-600 hover:text-rose-800">
                <X className="h-4 w-4" strokeWidth={2.5} />
              </button>
            )}
          </div>
        </div>

        {/* List */}
        {refunds.data.length > 0 ? (
          <div className="space-y-4">
            {refunds.data.map((r) => <RefundCard key={r.id} refund={r} />)}
            <Pagination links={refunds.links} />
          </div>
        ) : (
          <EmptyState
            icon={RefreshCw}
            title={t('student.refunds.no_requests')}
            description={t('student.refunds.no_requests_desc')}
            action={
              <a href="/my/refund-requests/create" className="inline-flex items-center gap-2 rounded-xl hero-gradient px-5 py-2.5 text-[13px] font-semibold text-white shadow-md shadow-violet-300/40">
                <Plus className="h-4 w-4" strokeWidth={2.5} />
                {t('student.refunds.request_refund')}
              </a>
            }
          />
        )}
      </div>
    </StudentLayout>
  );
}
