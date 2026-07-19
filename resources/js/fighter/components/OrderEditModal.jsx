import { useEffect, useMemo, useState } from 'react';
import { X, Loader2, Plus, Minus, Trash2, Search, Package, Banknote, Landmark, Truck, Upload, FileText, Paperclip, CheckCircle2, ShoppingBag, User, CreditCard, StickyNote } from 'lucide-react';
import { cn, formatMoney, fighterJson, fighterSend, csrfToken } from '@/fighter/lib/utils';

const MALAYSIA_STATES = [
  'Johor', 'Kedah', 'Kelantan', 'Melaka', 'Negeri Sembilan', 'Pahang', 'Perak', 'Perlis',
  'Pulau Pinang', 'Sabah', 'Sarawak', 'Selangor', 'Terengganu', 'Kuala Lumpur', 'Labuan', 'Putrajaya',
];

const PAYMENT_METHODS = [
  { key: 'cash', label: 'Cash', Icon: Banknote },
  { key: 'bank_transfer', label: 'Bank', Icon: Landmark },
  { key: 'cod', label: 'COD', Icon: Truck },
];

const MAX_RECEIPT_BYTES = 5 * 1024 * 1024;

function useDebounced(value, delay = 300) {
  const [debounced, setDebounced] = useState(value);
  useEffect(() => {
    const id = setTimeout(() => setDebounced(value), delay);
    return () => clearTimeout(id);
  }, [value, delay]);
  return debounced;
}

const labelCls = 'text-[11px] font-semibold uppercase tracking-[0.03em] text-muted-2';
const inputCls = 'mt-1 w-full rounded-xl border border-line bg-white px-3 py-2 text-[13px] text-ink outline-none focus:border-[var(--color-brand)]';

function SectionHeader({ Icon, children, right }) {
  return (
    <div className="mb-2 flex items-center justify-between">
      <span className="flex items-center gap-1.5 text-[12px] font-semibold uppercase tracking-[0.03em] text-muted-2">
        <Icon className="h-3.5 w-3.5" strokeWidth={2.2} /> {children}
      </span>
      {right}
    </div>
  );
}

/* ---------- Add-item search ---------- */

function AddItem({ onAdd }) {
  const [term, setTerm] = useState('');
  const [results, setResults] = useState([]);
  const [loading, setLoading] = useState(false);
  const [variantProduct, setVariantProduct] = useState(null);
  const debounced = useDebounced(term);

  useEffect(() => {
    const q = debounced.trim();
    if (q.length < 2) {
      setResults([]);
      return;
    }
    setLoading(true);
    fighterJson(`/fighter/catalog?search=${encodeURIComponent(q)}`)
      .then((data) => setResults(data.products ?? []))
      .catch(() => setResults([]))
      .finally(() => setLoading(false));
  }, [debounced]);

  const pick = (product) => {
    if (product.variants?.length > 0) {
      setVariantProduct(product);
    } else {
      onAdd({ itemable_id: product.id, name: product.name, variantId: null, variantName: null, unitPrice: product.base_price });
      setTerm('');
      setResults([]);
    }
  };

  return (
    <div className="relative">
      <div className="relative">
        <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-2" />
        <input
          value={term}
          onChange={(e) => setTerm(e.target.value)}
          placeholder="Add a product…"
          className="w-full rounded-xl border border-line bg-white py-2.5 pl-9 pr-3 text-[13px] text-ink outline-none focus:border-[var(--color-brand)]"
        />
        {loading && <Loader2 className="absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 animate-spin text-muted-2" />}
      </div>

      {results.length > 0 && (
        <div className="mt-1 max-h-48 divide-y divide-line/70 overflow-y-auto rounded-xl border border-line bg-white shadow-sm scroll-thin">
          {results.map((p) => (
            <button key={p.id} type="button" onClick={() => pick(p)} className="flex w-full items-center justify-between gap-3 px-3 py-2 text-left transition-colors hover:bg-orange-50">
              <span className="flex min-w-0 items-center gap-2">
                <span className="grid h-8 w-8 shrink-0 place-items-center overflow-hidden rounded-lg bg-surface text-muted-2">
                  {p.image ? <img src={p.image} alt="" className="h-full w-full object-cover" /> : <Package className="h-4 w-4" strokeWidth={1.8} />}
                </span>
                <span className="min-w-0">
                  <span className="block truncate text-[13px] font-medium text-ink">{p.name}</span>
                  {p.variants?.length > 0 && <span className="block text-[11px] text-muted-2">{p.variants.length} variants</span>}
                </span>
              </span>
              <span className="shrink-0 text-[12.5px] font-semibold tabular-nums text-ink">{formatMoney(p.base_price)}</span>
            </button>
          ))}
        </div>
      )}

      {variantProduct && (
        <div className="fixed inset-0 z-[80] flex items-center justify-center p-4" role="dialog" aria-modal="true">
          <div className="absolute inset-0 bg-black/40 backdrop-blur-sm" onClick={() => setVariantProduct(null)} />
          <div className="relative z-10 w-full max-w-sm rounded-2xl bg-white p-4 shadow-xl">
            <div className="mb-2 flex items-center justify-between">
              <h4 className="text-[14px] font-semibold text-ink">Choose a variant</h4>
              <button type="button" onClick={() => setVariantProduct(null)} className="grid h-7 w-7 place-items-center rounded-lg text-muted hover:bg-slate-100"><X className="h-4 w-4" /></button>
            </div>
            <div className="flex flex-col gap-1.5">
              {variantProduct.variants.map((v) => (
                <button key={v.id} type="button" onClick={() => { onAdd({ itemable_id: variantProduct.id, name: variantProduct.name, variantId: v.id, variantName: v.name, unitPrice: v.price }); setVariantProduct(null); setTerm(''); setResults([]); }} className="flex items-center justify-between rounded-xl bg-surface px-3 py-2.5 text-left hover:bg-orange-50">
                  <span className="text-[13px] font-medium text-ink">{v.name}</span>
                  <span className="text-[13px] font-semibold text-ink">{formatMoney(v.price)}</span>
                </button>
              ))}
            </div>
          </div>
        </div>
      )}
    </div>
  );
}

/* ---------- Edit modal ---------- */

export default function OrderEditModal({ order, onClose, onSaved }) {
  const startedEmpty = order.items.length === 0;
  const [cart, setCart] = useState(() =>
    order.items.map((it) => ({
      key: `existing-${it.id}`,
      id: it.id,
      itemable_type: it.itemable_type,
      itemable_id: it.itemable_id,
      product_variant_id: it.product_variant_id,
      name: it.product_name,
      variantName: it.variant_name,
      unitPrice: Number(it.unit_price),
      quantity: it.quantity,
    }))
  );
  const [form, setForm] = useState({
    name: order.customer?.name ?? '',
    phone: order.customer?.phone ?? '',
    email: order.customer?.email ?? '',
    address: order.customer?.address ?? '',
    postcode: order.customer?.postcode ?? '',
    city: order.customer?.city ?? '',
    state: order.customer?.state ?? '',
  });
  const [paymentMethod, setPaymentMethod] = useState(
    ['cash', 'bank_transfer', 'cod'].includes(order.payment_method) ? order.payment_method : 'cash'
  );
  const [paymentStatus, setPaymentStatus] = useState(order.payment_status === 'paid' ? 'paid' : 'pending');
  const [paymentReference, setPaymentReference] = useState(order.payment_reference ?? '');
  const [shippingCost, setShippingCost] = useState(order.shipping_cost ? String(order.shipping_cost) : '');
  const [notes, setNotes] = useState(order.notes ?? '');
  const [receiptFile, setReceiptFile] = useState(null);
  const [receiptPreview, setReceiptPreview] = useState(null);
  const [removeExisting, setRemoveExisting] = useState(false);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState(null);

  const addItem = ({ itemable_id, name, variantId, variantName, unitPrice }) => {
    const key = `new-${itemable_id}-${variantId ?? ''}`;
    setCart((prev) => {
      const existing = prev.find((c) => c.key === key);
      if (existing) return prev.map((c) => (c.key === key ? { ...c, quantity: c.quantity + 1 } : c));
      return [...prev, { key, id: null, itemable_type: 'product', itemable_id, product_variant_id: variantId, name, variantName, unitPrice: Number(unitPrice), quantity: 1 }];
    });
  };
  const setQty = (key, qty) => {
    if (qty < 1) return setCart((prev) => prev.filter((c) => c.key !== key));
    setCart((prev) => prev.map((c) => (c.key === key ? { ...c, quantity: qty } : c)));
  };
  const setPrice = (key, price) => setCart((prev) => prev.map((c) => (c.key === key ? { ...c, unitPrice: parseFloat(price) || 0 } : c)));
  const removeItem = (key) => setCart((prev) => prev.filter((c) => c.key !== key));

  const handleReceiptChange = (e) => {
    const file = e.target.files?.[0];
    if (!file) return;
    if (file.size > MAX_RECEIPT_BYTES) {
      setError('Receipt must be 5MB or smaller.');
      return;
    }
    setError(null);
    setReceiptFile(file);
    setRemoveExisting(false);
    if (file.type.startsWith('image/')) {
      const reader = new FileReader();
      reader.onload = (ev) => setReceiptPreview(ev.target?.result ?? null);
      reader.readAsDataURL(file);
    } else {
      setReceiptPreview(null);
    }
  };
  const clearNewReceipt = () => { setReceiptFile(null); setReceiptPreview(null); };

  const hasCartItems = cart.length > 0;
  const subtotal = useMemo(() => cart.reduce((s, c) => s + c.unitPrice * c.quantity, 0), [cart]);
  const shipping = parseFloat(shippingCost) || 0;
  // Item-less orders keep their stored total until line items are actually added.
  const total = hasCartItems ? Math.max(0, subtotal + shipping) : Number(order.total ?? 0);

  const canSave =
    (hasCartItems || startedEmpty) &&
    !saving &&
    form.name.trim() &&
    form.phone.trim() &&
    (paymentMethod !== 'bank_transfer' || paymentReference.trim());

  const hasExistingReceipt = order.receipt_url && !removeExisting && !receiptFile;

  const save = async () => {
    if (!canSave) return;
    setSaving(true);
    setError(null);

    const payload = {
      customer_name: form.name,
      customer_phone: form.phone,
      customer_email: form.email || null,
      customer_address: form.address || null,
      customer_postcode: form.postcode || null,
      customer_city: form.city || null,
      customer_state: form.state || null,
      payment_method: paymentMethod,
      payment_status: paymentStatus,
      payment_reference: paymentMethod === 'bank_transfer' ? paymentReference : null,
      shipping_cost: shipping || null,
      notes: notes || null,
      // Empty array tells the server to leave line items + totals untouched.
      items: cart.map((c) => ({
        ...(c.id ? { id: c.id } : {}),
        itemable_type: c.itemable_type,
        itemable_id: c.itemable_id,
        product_variant_id: c.product_variant_id || null,
        quantity: c.quantity,
        unit_price: c.unitPrice,
      })),
    };
    if (removeExisting && !receiptFile) {
      payload.remove_receipt_attachment = 1;
    }

    try {
      if (receiptFile) {
        const fd = new FormData();
        Object.entries(payload).forEach(([key, value]) => {
          if (value === null || value === undefined) return;
          if (key === 'items') {
            value.forEach((item, i) => {
              Object.entries(item).forEach(([k, v]) => {
                if (v !== null && v !== undefined) fd.append(`items[${i}][${k}]`, v);
              });
            });
          } else {
            fd.append(key, value);
          }
        });
        fd.append('receipt_attachment', receiptFile);
        await fetch(`/fighter/orders/${order.id}`, {
          method: 'POST',
          headers: { 'X-CSRF-TOKEN': csrfToken(), Accept: 'application/json' },
          credentials: 'same-origin',
          body: fd,
        }).then(async (res) => {
          if (!res.ok) throw new Error((await res.json().catch(() => null))?.message || 'Could not save the order.');
        });
      } else {
        await fighterSend(`/fighter/orders/${order.id}`, { method: 'POST', body: payload });
      }
      onSaved();
    } catch (e) {
      setError(e.message);
      setSaving(false);
    }
  };

  return (
    <div className="fixed inset-0 z-[70] flex items-end justify-center p-4 sm:items-center" role="dialog" aria-modal="true">
      <div className="absolute inset-0 bg-black/40 backdrop-blur-sm" onClick={saving ? undefined : onClose} />
      <div className="relative z-10 flex max-h-[92dvh] w-full max-w-2xl flex-col overflow-hidden rounded-2xl bg-white shadow-xl">
        <div className="flex items-center justify-between border-b border-line/70 px-5 py-4">
          <div className="min-w-0">
            <h3 className="truncate text-[15px] font-semibold text-ink">Edit {order.order_number}</h3>
            <p className="text-[12px] text-muted">{order.source_label} order</p>
          </div>
          <button type="button" onClick={onClose} disabled={saving} className="grid h-8 w-8 shrink-0 place-items-center rounded-lg text-muted hover:bg-slate-100 hover:text-ink disabled:opacity-40" aria-label="Close"><X className="h-4 w-4" strokeWidth={2.2} /></button>
        </div>

        <div className="flex-1 space-y-4 overflow-y-auto px-5 py-4 scroll-thin">
          {/* Items — full width */}
          <section className="rounded-2xl bg-surface/60 p-4 ring-1 ring-line/70">
            <SectionHeader Icon={ShoppingBag} right={<span className="text-[12px] font-semibold text-ink">{formatMoney(hasCartItems ? subtotal : total)}</span>}>Items</SectionHeader>
            {hasCartItems ? (
              <div className="divide-y divide-line/70 overflow-hidden rounded-xl bg-white ring-1 ring-line/70">
                {cart.map((item) => (
                  <div key={item.key} className="flex items-center gap-2 p-2.5">
                    <div className="min-w-0 flex-1">
                      <div className="truncate text-[13px] font-medium text-ink">{item.name || 'Item'}</div>
                      {item.variantName && <div className="truncate text-[11px] text-muted">{item.variantName}</div>}
                      <div className="mt-1 flex items-center gap-1">
                        <span className="text-[11px] text-muted-2">RM</span>
                        <input type="number" min="0" step="0.01" value={item.unitPrice} onChange={(e) => setPrice(item.key, e.target.value)} className="w-20 rounded-lg border border-line bg-white px-2 py-1 text-[12px] tabular-nums text-ink outline-none focus:border-[var(--color-brand)]" />
                      </div>
                    </div>
                    <div className="flex shrink-0 items-center gap-1 rounded-lg bg-surface p-0.5 ring-1 ring-line/70">
                      <button type="button" onClick={() => setQty(item.key, item.quantity - 1)} className="grid h-6 w-6 place-items-center rounded-md text-muted hover:bg-white hover:text-ink"><Minus className="h-3.5 w-3.5" strokeWidth={2.4} /></button>
                      <span className="w-6 text-center text-[13px] font-semibold tabular-nums text-ink">{item.quantity}</span>
                      <button type="button" onClick={() => setQty(item.key, item.quantity + 1)} className="grid h-6 w-6 place-items-center rounded-md text-muted hover:bg-white hover:text-ink"><Plus className="h-3.5 w-3.5" strokeWidth={2.4} /></button>
                    </div>
                    <button type="button" onClick={() => removeItem(item.key)} className="grid h-7 w-7 shrink-0 place-items-center rounded-lg text-muted hover:bg-rose-50 hover:text-rose-600"><Trash2 className="h-3.5 w-3.5" strokeWidth={2.2} /></button>
                  </div>
                ))}
              </div>
            ) : (
              <p className="rounded-xl border border-dashed border-line bg-white px-3 py-3 text-center text-[12px] text-muted">
                This order has no line items{startedEmpty ? ' — its total stays as is' : ''}. Add a product below to itemise it.
              </p>
            )}
            <div className="mt-2"><AddItem onAdd={addItem} /></div>
          </section>

          {/* Two columns: customer | payment */}
          <div className="grid gap-4 lg:grid-cols-2">
            <section className="rounded-2xl bg-white p-4 ring-1 ring-line/70">
              <SectionHeader Icon={User}>Customer</SectionHeader>
              <div className="grid grid-cols-2 gap-2.5">
                <label className="block"><span className={labelCls}>Name</span><input value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} className={inputCls} /></label>
                <label className="block"><span className={labelCls}>Phone</span><input value={form.phone} onChange={(e) => setForm({ ...form, phone: e.target.value })} className={inputCls} /></label>
                <label className="col-span-2 block"><span className={labelCls}>Email</span><input type="email" value={form.email} onChange={(e) => setForm({ ...form, email: e.target.value })} className={inputCls} /></label>
                <label className="col-span-2 block"><span className={labelCls}>Address</span><input value={form.address} onChange={(e) => setForm({ ...form, address: e.target.value })} className={inputCls} /></label>
                <label className="block"><span className={labelCls}>Postcode</span><input value={form.postcode} onChange={(e) => setForm({ ...form, postcode: e.target.value })} className={inputCls} /></label>
                <label className="block"><span className={labelCls}>City</span><input value={form.city} onChange={(e) => setForm({ ...form, city: e.target.value })} className={inputCls} /></label>
                <label className="col-span-2 block">
                  <span className={labelCls}>State</span>
                  <select value={form.state} onChange={(e) => setForm({ ...form, state: e.target.value })} className={inputCls}>
                    <option value="">Select state…</option>
                    {MALAYSIA_STATES.map((s) => <option key={s} value={s}>{s}</option>)}
                  </select>
                </label>
              </div>
            </section>

            <section className="rounded-2xl bg-white p-4 ring-1 ring-line/70">
              <SectionHeader Icon={CreditCard}>Payment</SectionHeader>
              <div className="grid grid-cols-3 gap-1.5">
                {PAYMENT_METHODS.map(({ key, label, Icon }) => (
                  <button key={key} type="button" onClick={() => setPaymentMethod(key)} className={cn('flex flex-col items-center justify-center gap-1 rounded-lg py-2 text-[11.5px] font-semibold transition-colors', paymentMethod === key ? 'bg-[var(--color-brand)] text-white' : 'bg-surface text-ink-2 hover:bg-slate-200')}>
                    <Icon className="h-4 w-4" strokeWidth={2} /> {label}
                  </button>
                ))}
              </div>
              {paymentMethod === 'bank_transfer' && (
                <input value={paymentReference} onChange={(e) => setPaymentReference(e.target.value)} placeholder="Payment reference" className={cn(inputCls, 'mt-2')} />
              )}
              <div className="mt-2 grid grid-cols-2 gap-1.5">
                {['pending', 'paid'].map((s) => (
                  <button key={s} type="button" onClick={() => setPaymentStatus(s)} className={cn('rounded-lg py-2 text-[12px] font-semibold capitalize transition-colors', paymentStatus === s ? 'bg-ink text-white' : 'bg-surface text-ink-2 hover:bg-slate-200')}>{s}</button>
                ))}
              </div>

              {paymentMethod !== 'cod' && (
                <div className="mt-3">
                  <span className={labelCls}>Payment receipt</span>
                  {receiptFile ? (
                    <div className="mt-1 flex items-center gap-3 rounded-xl border border-line p-2.5">
                      {receiptPreview ? <img src={receiptPreview} alt="" className="h-10 w-10 shrink-0 rounded-lg object-cover" /> : <div className="grid h-10 w-10 shrink-0 place-items-center rounded-lg bg-rose-50 text-rose-500"><FileText className="h-5 w-5" strokeWidth={1.8} /></div>}
                      <div className="min-w-0 flex-1"><p className="truncate text-[12.5px] font-medium text-ink">{receiptFile.name}</p><p className="text-[11px] text-muted-2">{(receiptFile.size / 1024).toFixed(0)} KB</p></div>
                      <button type="button" onClick={clearNewReceipt} className="grid h-7 w-7 shrink-0 place-items-center rounded-lg text-muted hover:bg-rose-50 hover:text-rose-600"><X className="h-4 w-4" strokeWidth={2.2} /></button>
                    </div>
                  ) : hasExistingReceipt ? (
                    <div className="mt-1 flex items-center gap-2 rounded-xl border border-line p-2.5">
                      <a href={order.receipt_url} target="_blank" rel="noopener noreferrer" className="grid h-10 w-10 shrink-0 place-items-center rounded-lg bg-orange-50 text-[var(--color-brand)]"><Paperclip className="h-5 w-5" strokeWidth={1.8} /></a>
                      <div className="min-w-0 flex-1"><p className="truncate text-[12.5px] font-medium text-ink">Receipt attached</p><a href={order.receipt_url} target="_blank" rel="noopener noreferrer" className="text-[11px] font-medium text-[var(--color-brand-ink)] hover:underline">Open</a></div>
                      <label className="cursor-pointer rounded-lg bg-surface px-2.5 py-1.5 text-[11.5px] font-semibold text-ink-2 hover:bg-slate-200">Replace<input type="file" accept="image/jpeg,image/png,image/webp,application/pdf" onChange={handleReceiptChange} className="hidden" /></label>
                      <button type="button" onClick={() => setRemoveExisting(true)} className="grid h-7 w-7 shrink-0 place-items-center rounded-lg text-muted hover:bg-rose-50 hover:text-rose-600"><Trash2 className="h-3.5 w-3.5" strokeWidth={2.2} /></button>
                    </div>
                  ) : (
                    <label className="mt-1 flex cursor-pointer flex-col items-center justify-center rounded-xl border-2 border-dashed border-line py-3.5 text-center transition-colors hover:border-[var(--color-brand)] hover:bg-orange-50/40">
                      <Upload className="h-5 w-5 text-muted-2" strokeWidth={1.8} />
                      <span className="mt-1 text-[12px] font-medium text-ink-2">Upload receipt</span>
                      <span className="text-[11px] text-muted-2">JPG, PNG, PDF, WebP · max 5MB</span>
                      <input type="file" accept="image/jpeg,image/png,image/webp,application/pdf" onChange={handleReceiptChange} className="hidden" />
                    </label>
                  )}
                </div>
              )}

              {hasCartItems && (
                <label className="mt-3 block"><span className={labelCls}>Shipping (optional)</span><input type="number" min="0" step="0.01" value={shippingCost} onChange={(e) => setShippingCost(e.target.value)} placeholder="0.00" className={inputCls} /></label>
              )}
            </section>
          </div>

          {/* Notes — full width */}
          <section className="rounded-2xl bg-white p-4 ring-1 ring-line/70">
            <SectionHeader Icon={StickyNote}>Notes</SectionHeader>
            <textarea value={notes} onChange={(e) => setNotes(e.target.value)} rows={2} placeholder="Add any notes…" className={cn(inputCls, 'mt-0 resize-none')} />
          </section>

          {error && <p className="rounded-lg bg-rose-50 px-3 py-2 text-[12.5px] font-medium text-rose-700">{error}</p>}
        </div>

        <div className="flex items-center gap-3 border-t border-line/70 px-5 py-4">
          <div className="mr-auto text-[13px]"><span className="text-muted-2">Total </span><span className="font-bold text-ink tabular-nums">{formatMoney(total)}</span></div>
          <button type="button" onClick={onClose} disabled={saving} className="rounded-xl bg-slate-100 px-4 py-2.5 text-[13.5px] font-semibold text-ink-2 transition-colors hover:bg-slate-200 disabled:opacity-40">Cancel</button>
          <button type="button" onClick={save} disabled={!canSave} className="flex items-center justify-center gap-2 rounded-xl bg-[var(--color-brand)] px-4 py-2.5 text-[13.5px] font-semibold text-white transition-colors hover:bg-[var(--color-brand-ink)] disabled:cursor-not-allowed disabled:opacity-50">
            {saving ? <Loader2 className="h-4 w-4 animate-spin" /> : <CheckCircle2 className="h-4 w-4" strokeWidth={2.2} />}
            {saving ? 'Saving…' : 'Save changes'}
          </button>
        </div>
      </div>
    </div>
  );
}
