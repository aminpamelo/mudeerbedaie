import { Head, usePage } from '@inertiajs/react';
import { ArrowLeft, CheckCircle, XCircle } from 'lucide-react';
import StudentLayout from '@/student/layouts/StudentLayout';
import PageHeader from '@/student/components/PageHeader';
import StatusBadge from '@/student/components/StatusBadge';
import TimelineSteps from '@/student/components/TimelineSteps';
import { cn, t } from '@/student/lib/utils';

function InfoRow({ label, children }) {
  return (
    <div>
      <p className="text-[11px] font-semibold uppercase tracking-wider text-muted">{label}</p>
      <div className="mt-0.5 text-[14px] font-medium text-ink">{children}</div>
    </div>
  );
}

export default function RefundRequestShow() {
  const { refund } = usePage().props;

  const hero = (
    <PageHeader title={refund.refund_number} subtitle={`Submitted ${refund.created_at}`}>
      <StatusBadge variant={refund.status_color === 'green' ? 'success' : refund.status_color === 'red' ? 'danger' : refund.status_color === 'yellow' ? 'warning' : 'gray'} size="lg">
        {refund.status_label}
      </StatusBadge>
    </PageHeader>
  );

  return (
    <StudentLayout hero={hero}>
      <Head title={refund.refund_number} />

      <div className="pt-4">
        <div className="fade-up mb-5">
          <a href="/my/refund-requests" className="flex items-center gap-1.5 rounded-xl bg-violet-50 px-4 py-2 text-[13px] font-semibold text-violet-700 transition-colors hover:bg-violet-100 w-fit">
            <ArrowLeft className="h-4 w-4" strokeWidth={2} />
            Back to Requests
          </a>
        </div>

        <div className="grid grid-cols-1 gap-5 lg:grid-cols-3">
          {/* Main */}
          <div className="space-y-5 lg:col-span-2">
            {/* Status Timeline */}
            <div className="fade-up glass-card rounded-2xl p-5 shadow-sm">
              <h3 className="mb-4 text-[15px] font-bold text-ink">Request Status</h3>
              <TimelineSteps
                currentStatus={refund.status}
                isRejected={refund.is_rejected}
                isCancelled={refund.is_cancelled}
              />
            </div>

            {/* Reason */}
            <div className="fade-up glass-card rounded-2xl p-5 shadow-sm" style={{ animationDelay: '0.05s' }}>
              <h3 className="mb-3 text-[15px] font-bold text-ink">Refund Reason</h3>
              <div className="whitespace-pre-line text-[13px] leading-relaxed text-ink">{refund.reason}</div>
              {refund.notes && (
                <div className="mt-4 border-t border-violet-100/60 pt-4">
                  <p className="text-[12px] font-semibold text-muted">Additional Notes</p>
                  <p className="mt-1 text-[13px] text-ink">{refund.notes}</p>
                </div>
              )}
            </div>

            {/* Admin Response */}
            {refund.action_reason && (
              <div className="fade-up glass-card rounded-2xl p-5 shadow-sm" style={{ animationDelay: '0.1s' }}>
                <h3 className="mb-3 text-[15px] font-bold text-ink">Admin Response</h3>
                <div className={cn('flex items-start gap-3 rounded-xl p-4', refund.action === 'approved' ? 'bg-emerald-50' : 'bg-red-50')}>
                  {refund.action === 'approved'
                    ? <CheckCircle className="mt-0.5 h-6 w-6 shrink-0 text-emerald-600" strokeWidth={1.8} />
                    : <XCircle className="mt-0.5 h-6 w-6 shrink-0 text-red-600" strokeWidth={1.8} />
                  }
                  <div>
                    <p className={cn('text-[13px] font-semibold', refund.action === 'approved' ? 'text-emerald-800' : 'text-red-800')}>
                      {refund.action === 'approved' ? 'Approved' : 'Rejected'}
                      {refund.processed_by_name && ` by ${refund.processed_by_name}`}
                    </p>
                    <p className={cn('mt-1 text-[12px]', refund.action === 'approved' ? 'text-emerald-700' : 'text-red-700')}>
                      {refund.action_reason}
                    </p>
                    {refund.action_date && <p className="mt-1 text-[11px] text-muted">{refund.action_date}</p>}
                  </div>
                </div>
              </div>
            )}

            {/* Order Details */}
            {refund.order && (
              <div className="fade-up glass-card rounded-2xl p-5 shadow-sm" style={{ animationDelay: '0.15s' }}>
                <h3 className="mb-4 text-[15px] font-bold text-ink">Order Details</h3>
                <div className="mb-4 grid grid-cols-2 gap-4 text-[13px] sm:grid-cols-4">
                  <div><span className="text-[11px] font-semibold text-muted">Order Number</span><p className="font-medium">{refund.order.order_number}</p></div>
                  <div><span className="text-[11px] font-semibold text-muted">Order Date</span><p>{refund.order.created_at}</p></div>
                  <div><span className="text-[11px] font-semibold text-muted">Status</span><p><StatusBadge variant="gray">{refund.order.status}</StatusBadge></p></div>
                  <div><span className="text-[11px] font-semibold text-muted">Total</span><p className="font-semibold">RM {Number(refund.order.total_amount).toFixed(2)}</p></div>
                </div>
                {refund.order.items.length > 0 && (
                  <div className="border-t border-violet-100/60 pt-3">
                    <p className="mb-2 text-[12px] font-semibold text-muted">Items</p>
                    <div className="divide-y divide-violet-50">
                      {refund.order.items.map((item, i) => (
                        <div key={i} className="flex items-center justify-between py-2 text-[13px]">
                          <div>
                            <p className="text-ink">{item.name}</p>
                            <p className="text-[11px] text-muted">Qty: {item.quantity}</p>
                          </div>
                          <p className="font-medium">RM {Number(item.total_price).toFixed(2)}</p>
                        </div>
                      ))}
                    </div>
                  </div>
                )}
              </div>
            )}
          </div>

          {/* Sidebar */}
          <div className="space-y-5">
            <div className="fade-up glass-card rounded-2xl p-5 shadow-sm">
              <h3 className="mb-3 text-[15px] font-bold text-ink">Refund Summary</h3>
              <div className="space-y-2 text-[13px]">
                <div className="flex justify-between"><span className="text-muted">Refund Amount</span><span className="text-[16px] font-extrabold text-emerald-600">RM {Number(refund.refund_amount).toFixed(2)}</span></div>
                <div className="flex justify-between"><span className="text-muted">Return Date</span><span>{refund.return_date}</span></div>
                <div className="flex justify-between items-center"><span className="text-muted">Action</span><StatusBadge variant={refund.action_color === 'green' ? 'success' : refund.action_color === 'red' ? 'danger' : 'warning'}>{refund.action_label}</StatusBadge></div>
              </div>
            </div>

            {(refund.bank_name || refund.account_number) && (
              <div className="fade-up glass-card rounded-2xl p-5 shadow-sm" style={{ animationDelay: '0.05s' }}>
                <h3 className="mb-3 text-[15px] font-bold text-ink">Bank Details</h3>
                <div className="space-y-3">
                  {refund.bank_name && <InfoRow label="Bank Name">{refund.bank_name}</InfoRow>}
                  {refund.account_number && <InfoRow label="Account Number"><span className="font-mono">{refund.account_number}</span></InfoRow>}
                  {refund.account_holder_name && <InfoRow label="Account Holder">{refund.account_holder_name}</InfoRow>}
                </div>
              </div>
            )}

            {refund.tracking_number && (
              <div className="fade-up glass-card rounded-2xl p-5 shadow-sm" style={{ animationDelay: '0.1s' }}>
                <h3 className="mb-3 text-[15px] font-bold text-ink">Return Tracking</h3>
                <InfoRow label="Tracking Number"><span className="font-mono">{refund.tracking_number}</span></InfoRow>
              </div>
            )}

            <div className="fade-up glass-card rounded-2xl p-5 shadow-sm" style={{ animationDelay: '0.15s' }}>
              <h3 className="mb-3 text-[15px] font-bold text-ink">Timeline</h3>
              <div className="space-y-2 text-[13px]">
                <div className="flex justify-between"><span className="text-muted">Submitted</span><span>{refund.created_at}</span></div>
                {refund.action_date && <div className="flex justify-between"><span className="text-muted">Reviewed</span><span>{refund.action_date}</span></div>}
                <div className="flex justify-between"><span className="text-muted">Last Updated</span><span>{refund.updated_at}</span></div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </StudentLayout>
  );
}
