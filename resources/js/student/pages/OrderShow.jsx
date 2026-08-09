import { Head, usePage } from '@inertiajs/react';
import { ArrowLeft, Receipt, CircleAlert } from 'lucide-react';
import StudentLayout from '@/student/layouts/StudentLayout';
import PageHeader from '@/student/components/PageHeader';
import StatusBadge, { statusVariant } from '@/student/components/StatusBadge';
import { t } from '@/student/lib/utils';

function InfoRow({ label, children }) {
  return (
    <div>
      <p className="text-[11px] font-semibold uppercase tracking-wider text-muted">{label}</p>
      <div className="mt-0.5 text-[14px] font-medium text-ink">{children}</div>
    </div>
  );
}

export default function OrderShow() {
  const { order } = usePage().props;

  const hero = (
    <PageHeader title={`Order ${order.order_number}`} subtitle={t('student.orders.order_details')}>
      <StatusBadge variant={statusVariant(order.status)} size="lg">{order.status_label}</StatusBadge>
    </PageHeader>
  );

  return (
    <StudentLayout hero={hero}>
      <Head title={`Order ${order.order_number}`} />

      <div className="pt-4">
        {/* Back + Receipt buttons */}
        <div className="fade-up mb-5 flex flex-wrap items-center gap-3">
          <a href="/my/orders" className="flex items-center gap-1.5 rounded-xl bg-violet-50 px-4 py-2 text-[13px] font-semibold text-violet-700 transition-colors hover:bg-violet-100">
            <ArrowLeft className="h-4 w-4" strokeWidth={2} />
            {t('student.orders.back_to_orders')}
          </a>
          {order.is_paid && (
            <a href={`/my/orders/${order.id}/receipt`} className="flex items-center gap-1.5 rounded-xl bg-violet-50 px-4 py-2 text-[13px] font-semibold text-violet-700 transition-colors hover:bg-violet-100">
              <Receipt className="h-4 w-4" strokeWidth={2} />
              {t('student.orders.view_receipt')}
            </a>
          )}
        </div>

        <div className="grid grid-cols-1 gap-5 lg:grid-cols-3">
          {/* Main Content */}
          <div className="space-y-5 lg:col-span-2">
            {/* Order Status */}
            <div className="fade-up glass-card rounded-2xl p-5 shadow-sm" style={{ animationDelay: '0.05s' }}>
              <div className="mb-4 flex items-center justify-between">
                <h3 className="text-[15px] font-bold text-ink">{t('student.orders.order_status')}</h3>
                <StatusBadge variant={statusVariant(order.status)} size="lg">{order.status_label}</StatusBadge>
              </div>
              <div className="grid grid-cols-2 gap-5">
                <InfoRow label={t('student.orders.order_date')}>{order.created_at}</InfoRow>
                {order.paid_at && <InfoRow label={t('student.orders.paid_date')}>{order.paid_at}</InfoRow>}
                {order.failed_at && <InfoRow label={t('student.orders.failed_date')}>{order.failed_at}</InfoRow>}
                <InfoRow label={t('student.orders.billing_period')}>{order.period_description}</InfoRow>
                <InfoRow label={t('student.orders.billing_reason')}>{order.billing_reason_label}</InfoRow>
              </div>

              {order.failure_reason && (
                <div className="mt-4 flex items-start gap-2.5 rounded-xl bg-red-50 p-4">
                  <CircleAlert className="mt-0.5 h-5 w-5 shrink-0 text-red-500" strokeWidth={2} />
                  <div>
                    <p className="text-[13px] font-semibold text-red-800">{t('student.orders.failure_reason')}</p>
                    <p className="text-[12px] text-red-700">{order.failure_reason.failure_message ?? 'Payment failed'}</p>
                    {order.failure_reason.failure_code && (
                      <p className="mt-0.5 text-[11px] text-red-600">Code: {order.failure_reason.failure_code}</p>
                    )}
                  </div>
                </div>
              )}
            </div>

            {/* Order Items */}
            <div className="fade-up glass-card overflow-hidden rounded-2xl shadow-sm" style={{ animationDelay: '0.1s' }}>
              <div className="border-b border-violet-100/60 px-5 py-3">
                <h3 className="text-[15px] font-bold text-ink">{t('student.orders.order_items')}</h3>
              </div>
              <div className="overflow-x-auto">
                <table className="w-full text-[13px]">
                  <thead>
                    <tr className="border-b border-violet-100/60 text-muted">
                      <th className="px-5 py-2.5 text-left font-semibold">{t('student.orders.description')}</th>
                      <th className="px-3 py-2.5 text-center font-semibold">{t('student.orders.qty')}</th>
                      <th className="px-3 py-2.5 text-right font-semibold">{t('student.orders.unit_price')}</th>
                      <th className="px-5 py-2.5 text-right font-semibold">{t('student.orders.total')}</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-violet-50">
                    {order.items.length > 0 ? order.items.map((item, i) => (
                      <tr key={i}>
                        <td className="px-5 py-3 font-medium text-ink">{item.description}</td>
                        <td className="px-3 py-3 text-center">{item.quantity}</td>
                        <td className="px-3 py-3 text-right">{item.formatted_unit_price}</td>
                        <td className="px-5 py-3 text-right font-semibold">{item.formatted_total_price}</td>
                      </tr>
                    )) : (
                      <tr>
                        <td colSpan="4" className="px-5 py-6 text-center text-muted">{t('student.orders.no_items')}</td>
                      </tr>
                    )}
                  </tbody>
                  <tfoot>
                    <tr className="border-t-2 border-violet-200/60">
                      <td colSpan="3" className="px-5 py-3 text-right font-bold text-ink">{t('student.orders.order_total')}:</td>
                      <td className="px-5 py-3 text-right text-[16px] font-extrabold text-ink">{order.formatted_amount}</td>
                    </tr>
                  </tfoot>
                </table>
              </div>
            </div>

            {/* Payment Details */}
            {order.is_paid && (
              <div className="fade-up glass-card rounded-2xl p-5 shadow-sm" style={{ animationDelay: '0.15s' }}>
                <h3 className="mb-4 text-[15px] font-bold text-ink">{t('student.orders.payment_details')}</h3>
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                  {order.stripe_charge_id && <InfoRow label={t('student.orders.transaction_id')}><span className="font-mono text-[12px]">{order.stripe_charge_id}</span></InfoRow>}
                  <InfoRow label={t('student.orders.currency')}>{order.currency}</InfoRow>
                  <InfoRow label={t('student.orders.payment_method')}>{t('student.orders.credit_debit_card')}</InfoRow>
                </div>
              </div>
            )}
          </div>

          {/* Sidebar */}
          <div className="space-y-5">
            {/* Course */}
            {order.course && (
              <div className="fade-up glass-card rounded-2xl p-5 shadow-sm" style={{ animationDelay: '0.1s' }}>
                <h3 className="mb-3 text-[15px] font-bold text-ink">{t('student.orders.course')}</h3>
                <InfoRow label={t('student.orders.course_name')}>{order.course.name}</InfoRow>
                {order.course.description && (
                  <div className="mt-3">
                    <InfoRow label={t('student.orders.description')}>{order.course.description}</InfoRow>
                  </div>
                )}
              </div>
            )}

            {/* Enrollment */}
            {order.enrollment && (
              <div className="fade-up glass-card rounded-2xl p-5 shadow-sm" style={{ animationDelay: '0.15s' }}>
                <h3 className="mb-3 text-[15px] font-bold text-ink">{t('student.orders.enrollment')}</h3>
                <div className="space-y-3">
                  <InfoRow label={t('student.classes.status')}>{order.enrollment.status}</InfoRow>
                  {order.enrollment.subscription_status_label && (
                    <InfoRow label={t('student.orders.subscription')}>{order.enrollment.subscription_status_label}</InfoRow>
                  )}
                  {order.enrollment.enrollment_date && (
                    <InfoRow label={t('student.orders.enrolled_date')}>{order.enrollment.enrollment_date}</InfoRow>
                  )}
                </div>
              </div>
            )}

            {/* Actions */}
            {order.is_failed && order.enrollment?.has_active_subscription && (
              <div className="fade-up glass-card rounded-2xl p-5 shadow-sm" style={{ animationDelay: '0.2s' }}>
                <h3 className="mb-3 text-[15px] font-bold text-ink">{t('student.orders.actions')}</h3>
                <a
                  href="/my/payment-methods"
                  className="block w-full rounded-xl hero-gradient py-2.5 text-center text-[13px] font-semibold text-white shadow-md shadow-violet-300/40 transition-all hover:shadow-lg"
                >
                  {t('student.orders.update_payment_method')}
                </a>
              </div>
            )}

            {order.is_paid && (
              <div className="fade-up glass-card rounded-2xl p-5 shadow-sm" style={{ animationDelay: '0.2s' }}>
                <h3 className="mb-3 text-[15px] font-bold text-ink">{t('student.orders.receipt')}</h3>
                <a
                  href={`/my/orders/${order.id}/receipt`}
                  className="block w-full rounded-xl hero-gradient py-2.5 text-center text-[13px] font-semibold text-white shadow-md shadow-violet-300/40 transition-all hover:shadow-lg"
                >
                  {t('student.orders.view_receipt')}
                </a>
                <p className="mt-2 text-center text-[11px] text-muted">{t('student.orders.download_print_receipt')}</p>
              </div>
            )}
          </div>
        </div>
      </div>
    </StudentLayout>
  );
}
