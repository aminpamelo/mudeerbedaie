import { Head, usePage } from '@inertiajs/react';
import { ArrowLeft, Printer } from 'lucide-react';
import { t } from '@/student/lib/utils';

export default function OrderReceipt() {
  const { appName, order, generatedAt } = usePage().props;

  return (
    <>
      <Head title={`Receipt - ${order.order_number}`} />

      <div className="min-h-dvh bg-white">
        <div className="mx-auto max-w-4xl p-6 sm:p-8">
          {/* Header (hidden in print) */}
          <div className="mb-8 flex items-center justify-between print:hidden">
            <div>
              <h1 className="text-[22px] font-extrabold text-gray-900">{t('student.orders.receipt')}</h1>
              <p className="text-[14px] text-gray-500">Order {order.order_number}</p>
            </div>
            <div className="flex gap-3">
              <a
                href={`/my/orders/${order.id}`}
                className="flex items-center gap-1.5 rounded-xl border border-gray-200 px-4 py-2 text-[13px] font-semibold text-gray-700 transition-colors hover:bg-gray-50"
              >
                <ArrowLeft className="h-4 w-4" strokeWidth={2} />
                {t('student.orders.back_to_order')}
              </a>
              <button
                type="button"
                onClick={() => window.print()}
                className="flex items-center gap-1.5 rounded-xl bg-violet-600 px-4 py-2 text-[13px] font-semibold text-white transition-colors hover:bg-violet-700"
              >
                <Printer className="h-4 w-4" strokeWidth={2} />
                {t('student.orders.print_receipt')}
              </button>
            </div>
          </div>

          {/* Receipt Content */}
          <div className="rounded-xl border border-gray-200 p-6 sm:p-8 print:border-0 print:p-0 print:shadow-none">
            {/* Company Header */}
            <div className="mb-8 text-center">
              <h2 className="text-[24px] font-extrabold text-gray-800">{appName}</h2>
              <p className="mt-1 text-[14px] text-gray-500">{t('student.orders.payment_receipt')}</p>
            </div>

            {/* Receipt + Student Details */}
            <div className="mb-8 grid grid-cols-1 gap-8 sm:grid-cols-2">
              <div>
                <h3 className="mb-3 text-[15px] font-bold text-gray-800">{t('student.orders.receipt_details')}</h3>
                <dl className="space-y-2 text-[13px]">
                  <div className="flex justify-between"><dt className="text-gray-500">{t('student.orders.receipt_number')}:</dt><dd className="font-semibold text-gray-800">{order.order_number}</dd></div>
                  <div className="flex justify-between"><dt className="text-gray-500">{t('student.orders.date_issued')}:</dt><dd className="font-semibold text-gray-800">{order.paid_at}</dd></div>
                  <div className="flex justify-between"><dt className="text-gray-500">{t('student.orders.payment_status')}:</dt><dd><span className="rounded-full bg-emerald-100 px-2.5 py-0.5 text-[11px] font-semibold text-emerald-700">{order.status_label}</span></dd></div>
                  <div className="flex justify-between"><dt className="text-gray-500">{t('student.orders.billing_period')}:</dt><dd className="font-semibold text-gray-800">{order.period_description}</dd></div>
                </dl>
              </div>
              <div>
                <h3 className="mb-3 text-[15px] font-bold text-gray-800">{t('student.orders.student_info')}</h3>
                <dl className="space-y-2 text-[13px]">
                  <div className="flex justify-between"><dt className="text-gray-500">{t('student.orders.name')}:</dt><dd className="font-semibold text-gray-800">{order.student_name}</dd></div>
                  <div className="flex justify-between"><dt className="text-gray-500">{t('student.orders.email')}:</dt><dd className="font-semibold text-gray-800">{order.student_email}</dd></div>
                  <div className="flex justify-between"><dt className="text-gray-500">{t('student.orders.student_id')}:</dt><dd className="font-semibold text-gray-800">{order.student_id}</dd></div>
                </dl>
              </div>
            </div>

            {/* Course Info */}
            {order.course_name && (
              <div className="mb-8">
                <h3 className="mb-3 text-[15px] font-bold text-gray-800">{t('student.orders.course_info')}</h3>
                <div className="rounded-lg bg-gray-50 p-4">
                  <p className="text-[15px] font-semibold text-gray-800">{order.course_name}</p>
                  {order.course_description && <p className="mt-1 text-[13px] text-gray-600">{order.course_description}</p>}
                </div>
              </div>
            )}

            {/* Items Table */}
            <div className="mb-8">
              <h3 className="mb-3 text-[15px] font-bold text-gray-800">{t('student.orders.payment_details')}</h3>
              <div className="overflow-x-auto">
                <table className="w-full text-[13px]">
                  <thead>
                    <tr className="border-b-2 border-gray-300">
                      <th className="py-2.5 text-left font-bold text-gray-800">{t('student.orders.description')}</th>
                      <th className="py-2.5 text-center font-bold text-gray-800">{t('student.orders.qty')}</th>
                      <th className="py-2.5 text-right font-bold text-gray-800">{t('student.orders.unit_price')}</th>
                      <th className="py-2.5 text-right font-bold text-gray-800">{t('student.orders.total')}</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-gray-200">
                    {order.items.length > 0 ? order.items.map((item, i) => (
                      <tr key={i}>
                        <td className="py-3 font-medium">{item.description}</td>
                        <td className="py-3 text-center">{item.quantity}</td>
                        <td className="py-3 text-right">{item.formatted_unit_price}</td>
                        <td className="py-3 text-right font-semibold">{item.formatted_total_price}</td>
                      </tr>
                    )) : (
                      <tr><td colSpan="4" className="py-4 text-center text-gray-500">{t('student.orders.no_items')}</td></tr>
                    )}
                  </tbody>
                  <tfoot>
                    <tr className="border-t-2 border-gray-300">
                      <td colSpan="3" className="py-3 text-right font-bold text-gray-800">{t('student.orders.total_amount')}:</td>
                      <td className="py-3 text-right text-[16px] font-extrabold text-gray-800">{order.formatted_amount}</td>
                    </tr>
                  </tfoot>
                </table>
              </div>
            </div>

            {/* Payment Info */}
            <div className="mb-8">
              <h3 className="mb-3 text-[15px] font-bold text-gray-800">{t('student.orders.payment_info')}</h3>
              <div className="rounded-lg border border-emerald-200 bg-emerald-50 p-4">
                <div className="grid grid-cols-1 gap-4 text-[13px] sm:grid-cols-2">
                  {order.stripe_charge_id && (
                    <div>
                      <p className="text-gray-500">{t('student.orders.transaction_id')}:</p>
                      <p className="font-mono text-[12px] font-medium text-gray-800">{order.stripe_charge_id}</p>
                    </div>
                  )}
                  <div><p className="text-gray-500">{t('student.orders.payment_method')}:</p><p className="font-semibold text-gray-800">{t('student.orders.credit_debit_card')}</p></div>
                  <div><p className="text-gray-500">{t('student.orders.currency')}:</p><p className="font-semibold text-gray-800">{order.currency}</p></div>
                </div>
              </div>
            </div>

            {/* Footer */}
            <div className="border-t border-gray-200 pt-6 text-center">
              <p className="text-[13px] text-gray-600">{t('student.orders.thank_you')}</p>
              <p className="mt-1 text-[12px] text-gray-500">{t('student.orders.receipt_help')}</p>
              <p className="mt-3 text-[11px] text-gray-400">{t('student.orders.generated_on')} {generatedAt}</p>
            </div>
          </div>
        </div>
      </div>

      <style>{`
        @media print {
          .print\\:hidden { display: none !important; }
          body { margin: 0; padding: 20px; background: white; }
        }
      `}</style>
    </>
  );
}
