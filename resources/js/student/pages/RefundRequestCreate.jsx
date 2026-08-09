import { Head, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { ArrowLeft, Send, ShoppingBag } from 'lucide-react';
import StudentLayout from '@/student/layouts/StudentLayout';
import PageHeader from '@/student/components/PageHeader';
import EmptyState from '@/student/components/EmptyState';
import StatusBadge from '@/student/components/StatusBadge';
import { cn, t } from '@/student/lib/utils';

export default function RefundRequestCreate() {
  const { eligibleOrders, preselectedOrderId } = usePage().props;

  const [form, setForm] = useState({
    order_id: preselectedOrderId || '',
    reason: '',
    refund_amount: '',
    bank_name: '',
    account_number: '',
    account_holder_name: '',
    notes: '',
  });
  const [errors, setErrors] = useState({});
  const [submitting, setSubmitting] = useState(false);

  const selectedOrder = eligibleOrders.find((o) => o.id === Number(form.order_id));

  const selectOrder = (id) => {
    const order = eligibleOrders.find((o) => o.id === id);
    setForm((f) => ({ ...f, order_id: id, refund_amount: order ? order.total_amount.toFixed(2) : '' }));
  };

  const update = (key, val) => setForm((f) => ({ ...f, [key]: val }));

  const submit = (e) => {
    e.preventDefault();
    setSubmitting(true);
    router.post('/my/refund-requests', form, {
      onError: (errs) => { setErrors(errs); setSubmitting(false); },
      onFinish: () => setSubmitting(false),
    });
  };

  const hero = <PageHeader title="Request a Refund" subtitle="Submit a refund request for your order" />;

  if (eligibleOrders.length === 0) {
    return (
      <StudentLayout hero={hero}>
        <Head title="Request a Refund" />
        <div className="pt-4">
          <EmptyState
            icon={ShoppingBag}
            title="No Eligible Orders"
            description="You don't have any orders that are eligible for refund requests. Orders must be delivered, shipped, or completed."
            action={<a href="/my/orders" className="inline-flex items-center gap-2 rounded-xl border border-violet-200 bg-violet-50 px-5 py-2.5 text-[13px] font-semibold text-violet-700">View My Orders</a>}
          />
        </div>
      </StudentLayout>
    );
  }

  return (
    <StudentLayout hero={hero}>
      <Head title="Request a Refund" />

      <div className="pt-4">
        <div className="fade-up mb-5">
          <a href="/my/refund-requests" className="flex items-center gap-1.5 rounded-xl bg-violet-50 px-4 py-2 text-[13px] font-semibold text-violet-700 transition-colors hover:bg-violet-100 w-fit">
            <ArrowLeft className="h-4 w-4" strokeWidth={2} />
            Back to Requests
          </a>
        </div>

        <form onSubmit={submit}>
          <div className="grid grid-cols-1 gap-5 lg:grid-cols-3">
            {/* Main */}
            <div className="space-y-5 lg:col-span-2">
              {/* Select Order */}
              <div className="fade-up glass-card rounded-2xl p-5 shadow-sm">
                <h3 className="mb-4 text-[15px] font-bold text-ink">Select Order</h3>
                <div className="space-y-3">
                  {eligibleOrders.map((order) => (
                    <label
                      key={order.id}
                      className={cn(
                        'flex cursor-pointer items-start gap-4 rounded-xl border p-4 transition-colors hover:bg-violet-50/30',
                        form.order_id === order.id ? 'border-violet-400 bg-violet-50/50' : 'border-violet-100/60'
                      )}
                    >
                      <input type="radio" checked={form.order_id === order.id} onChange={() => selectOrder(order.id)} className="mt-1" />
                      <div className="min-w-0 flex-1">
                        <div className="flex items-center justify-between">
                          <p className="text-[13px] font-semibold text-ink">{order.order_number}</p>
                          <p className="text-[14px] font-bold text-emerald-600">RM {Number(order.total_amount).toFixed(2)}</p>
                        </div>
                        <div className="mt-1 flex items-center gap-3 text-[12px] text-muted">
                          <span>{order.created_at}</span>
                          <StatusBadge variant="gray">{order.status}</StatusBadge>
                          <span>{order.item_count} item(s)</span>
                        </div>
                      </div>
                    </label>
                  ))}
                </div>
                {errors.order_id && <p className="mt-2 text-[12px] text-red-600">{errors.order_id}</p>}
              </div>

              {/* Reason */}
              <div className="fade-up glass-card rounded-2xl p-5 shadow-sm" style={{ animationDelay: '0.05s' }}>
                <h3 className="mb-4 text-[15px] font-bold text-ink">Reason for Refund</h3>
                <div className="space-y-4">
                  <div>
                    <label className="mb-1.5 block text-[12px] font-semibold text-muted">Why are you requesting a refund?</label>
                    <textarea value={form.reason} onChange={(e) => update('reason', e.target.value)} rows="4" placeholder="Please describe the reason..." className="w-full rounded-xl border border-violet-200/60 bg-violet-50/30 px-4 py-3 text-[13px] outline-none focus:border-violet-400 focus:ring-2 focus:ring-violet-200" />
                    {errors.reason && <p className="mt-1 text-[12px] text-red-600">{errors.reason}</p>}
                  </div>
                  <div>
                    <label className="mb-1.5 block text-[12px] font-semibold text-muted">Additional Notes (Optional)</label>
                    <textarea value={form.notes} onChange={(e) => update('notes', e.target.value)} rows="2" placeholder="Any additional information..." className="w-full rounded-xl border border-violet-200/60 bg-violet-50/30 px-4 py-3 text-[13px] outline-none focus:border-violet-400 focus:ring-2 focus:ring-violet-200" />
                  </div>
                </div>
              </div>

              {/* Bank Details */}
              <div className="fade-up glass-card rounded-2xl p-5 shadow-sm" style={{ animationDelay: '0.1s' }}>
                <h3 className="mb-1 text-[15px] font-bold text-ink">Bank Details for Refund</h3>
                <p className="mb-4 text-[12px] text-muted">Provide your bank account details for the refund.</p>
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                  <div>
                    <label className="mb-1.5 block text-[12px] font-semibold text-muted">Bank Name</label>
                    <input value={form.bank_name} onChange={(e) => update('bank_name', e.target.value)} placeholder="e.g., Maybank" className="w-full rounded-xl border border-violet-200/60 bg-violet-50/30 px-4 py-2.5 text-[13px] outline-none focus:border-violet-400 focus:ring-2 focus:ring-violet-200" />
                    {errors.bank_name && <p className="mt-1 text-[12px] text-red-600">{errors.bank_name}</p>}
                  </div>
                  <div>
                    <label className="mb-1.5 block text-[12px] font-semibold text-muted">Account Number</label>
                    <input value={form.account_number} onChange={(e) => update('account_number', e.target.value)} placeholder="Your account number" className="w-full rounded-xl border border-violet-200/60 bg-violet-50/30 px-4 py-2.5 text-[13px] outline-none focus:border-violet-400 focus:ring-2 focus:ring-violet-200" />
                    {errors.account_number && <p className="mt-1 text-[12px] text-red-600">{errors.account_number}</p>}
                  </div>
                  <div className="sm:col-span-2">
                    <label className="mb-1.5 block text-[12px] font-semibold text-muted">Account Holder Name</label>
                    <input value={form.account_holder_name} onChange={(e) => update('account_holder_name', e.target.value)} placeholder="Name as shown on bank account" className="w-full rounded-xl border border-violet-200/60 bg-violet-50/30 px-4 py-2.5 text-[13px] outline-none focus:border-violet-400 focus:ring-2 focus:ring-violet-200" />
                    {errors.account_holder_name && <p className="mt-1 text-[12px] text-red-600">{errors.account_holder_name}</p>}
                  </div>
                </div>
              </div>
            </div>

            {/* Sidebar */}
            <div className="space-y-5">
              {selectedOrder && (
                <div className="fade-up glass-card rounded-2xl p-5 shadow-sm">
                  <h3 className="mb-3 text-[15px] font-bold text-ink">Order Summary</h3>
                  <div className="space-y-2 text-[13px]">
                    <div className="flex justify-between"><span className="text-muted">Order Number</span><span className="font-medium">{selectedOrder.order_number}</span></div>
                    <div className="flex justify-between"><span className="text-muted">Order Date</span><span>{selectedOrder.created_at}</span></div>
                    <div className="flex justify-between"><span className="text-muted">Order Total</span><span className="font-semibold">RM {Number(selectedOrder.total_amount).toFixed(2)}</span></div>
                    {selectedOrder.items.length > 0 && (
                      <div className="border-t border-violet-100/60 pt-2 mt-2">
                        <p className="text-muted mb-1">Items</p>
                        {selectedOrder.items.map((item, i) => (
                          <div key={i} className="flex justify-between text-[12px]">
                            <span className="truncate flex-1">{item.name}</span>
                            <span className="text-muted ml-2">x{item.quantity}</span>
                          </div>
                        ))}
                        {selectedOrder.has_more_items && <p className="text-[11px] text-muted-2">+{selectedOrder.remaining_items} more</p>}
                      </div>
                    )}
                  </div>
                </div>
              )}

              <div className="fade-up glass-card rounded-2xl p-5 shadow-sm" style={{ animationDelay: '0.05s' }}>
                <h3 className="mb-3 text-[15px] font-bold text-ink">Refund Amount</h3>
                <label className="mb-1.5 block text-[12px] font-semibold text-muted">Amount to Refund (RM)</label>
                <input type="number" step="0.01" min="0.01" value={form.refund_amount} onChange={(e) => update('refund_amount', e.target.value)} placeholder="0.00" className="w-full rounded-xl border border-violet-200/60 bg-violet-50/30 px-4 py-2.5 text-[13px] outline-none focus:border-violet-400 focus:ring-2 focus:ring-violet-200" />
                {errors.refund_amount && <p className="mt-1 text-[12px] text-red-600">{errors.refund_amount}</p>}
              </div>

              <div className="fade-up glass-card rounded-2xl p-5 shadow-sm" style={{ animationDelay: '0.1s' }}>
                <button
                  type="submit"
                  disabled={submitting}
                  className="flex w-full items-center justify-center gap-2 rounded-xl hero-gradient py-3 text-[14px] font-semibold text-white shadow-md shadow-violet-300/40 transition-all hover:shadow-lg disabled:opacity-60"
                >
                  <Send className="h-4 w-4" strokeWidth={2} />
                  {submitting ? 'Submitting...' : 'Submit Request'}
                </button>
                <p className="mt-2 text-center text-[11px] text-muted">Your request will be reviewed within 1-3 business days.</p>
              </div>
            </div>
          </div>
        </form>
      </div>
    </StudentLayout>
  );
}
