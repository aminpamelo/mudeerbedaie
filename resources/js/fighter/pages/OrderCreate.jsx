import { router } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { Search, Plus, Minus, Trash2, ShoppingBag, ArrowLeft, Loader2, User, UserPlus, CheckCircle2 } from 'lucide-react';
import FighterLayout from '@/fighter/layouts/FighterLayout';
import { cn, csrfToken, formatMoney } from '@/fighter/lib/utils';

/** Small fetch wrapper for the (session-authed) POS read/write endpoints. */
async function posGet(path) {
  const res = await fetch(`/api/pos${path}`, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
  if (!res.ok) throw new Error('request failed');
  return res.json();
}

function useDebounced(value, delay = 300) {
  const [debounced, setDebounced] = useState(value);
  useEffect(() => {
    const id = setTimeout(() => setDebounced(value), delay);
    return () => clearTimeout(id);
  }, [value, delay]);
  return debounced;
}

/* ---------- Product search + cart ---------- */

function ProductPicker({ onAdd }) {
  const [term, setTerm] = useState('');
  const [results, setResults] = useState([]);
  const [loading, setLoading] = useState(false);
  const debounced = useDebounced(term);
  const reqId = useRef(0);

  useEffect(() => {
    if (debounced.trim().length < 2) {
      setResults([]);
      return;
    }
    const id = ++reqId.current;
    setLoading(true);
    posGet(`/products?search=${encodeURIComponent(debounced.trim())}`)
      .then((data) => {
        if (id === reqId.current) setResults(data.data ?? []);
      })
      .catch(() => id === reqId.current && setResults([]))
      .finally(() => id === reqId.current && setLoading(false));
  }, [debounced]);

  return (
    <div className="rounded-2xl bg-white p-4 ring-1 ring-line/70">
      <div className="relative">
        <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-2" />
        <input
          value={term}
          onChange={(e) => setTerm(e.target.value)}
          placeholder="Search products to add…"
          className="w-full rounded-xl border border-line bg-surface py-2.5 pl-9 pr-3 text-[13.5px] text-ink outline-none focus:border-[var(--color-brand)]"
        />
        {loading && <Loader2 className="absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 animate-spin text-muted-2" />}
      </div>

      {term.trim().length >= 2 && (
        <div className="mt-3 max-h-[320px] space-y-1 overflow-y-auto scroll-thin">
          {results.length === 0 && !loading && <p className="px-1 py-3 text-center text-[12.5px] text-muted">No products found.</p>}
          {results.map((product) => (
            <ProductRow key={product.id} product={product} onAdd={onAdd} />
          ))}
        </div>
      )}
    </div>
  );
}

function ProductRow({ product, onAdd }) {
  const [open, setOpen] = useState(false);
  const variants = product.variants ?? [];
  const basePrice = parseFloat(product.base_price ?? 0) || 0;

  if (variants.length === 0) {
    return (
      <button
        type="button"
        onClick={() => onAdd({ productId: product.id, name: product.name, variantId: null, variantName: null, unitPrice: basePrice })}
        className="flex w-full items-center justify-between gap-3 rounded-xl px-3 py-2.5 text-left transition-colors hover:bg-surface"
      >
        <span className="min-w-0 truncate text-[13px] font-medium text-ink">{product.name}</span>
        <span className="flex shrink-0 items-center gap-2">
          <span className="text-[12.5px] font-semibold text-ink-2">{formatMoney(basePrice)}</span>
          <Plus className="h-4 w-4 text-[var(--color-brand)]" strokeWidth={2.4} />
        </span>
      </button>
    );
  }

  return (
    <div className="rounded-xl">
      <button
        type="button"
        onClick={() => setOpen((v) => !v)}
        className="flex w-full items-center justify-between gap-3 rounded-xl px-3 py-2.5 text-left transition-colors hover:bg-surface"
      >
        <span className="min-w-0 truncate text-[13px] font-medium text-ink">{product.name}</span>
        <span className="shrink-0 text-[11.5px] font-medium text-muted-2">{variants.length} variants</span>
      </button>
      {open && (
        <div className="mb-1 ml-3 flex flex-wrap gap-1.5 border-l border-line pl-3">
          {variants.map((v) => {
            const price = parseFloat(v.price ?? basePrice) || basePrice;
            return (
              <button
                key={v.id}
                type="button"
                onClick={() => onAdd({ productId: product.id, name: product.name, variantId: v.id, variantName: v.name, unitPrice: price })}
                className="flex items-center gap-1.5 rounded-lg bg-orange-50 px-2.5 py-1.5 text-[12px] font-semibold text-[var(--color-brand-ink)] transition-colors hover:bg-orange-100"
              >
                {v.name} · {formatMoney(price)}
                <Plus className="h-3 w-3" strokeWidth={2.6} />
              </button>
            );
          })}
        </div>
      )}
    </div>
  );
}

function Cart({ items, onQty, onRemove }) {
  if (items.length === 0) {
    return (
      <div className="flex flex-col items-center justify-center rounded-2xl border border-dashed border-line bg-surface px-4 py-10 text-center">
        <ShoppingBag className="h-6 w-6 text-muted-2" strokeWidth={1.8} />
        <p className="mt-2 text-[13px] font-medium text-ink">Cart is empty</p>
        <p className="text-[12px] text-muted">Search and add products above.</p>
      </div>
    );
  }
  return (
    <div className="divide-y divide-line/70 rounded-2xl bg-white ring-1 ring-line/70">
      {items.map((item) => (
        <div key={item.key} className="flex items-center gap-3 p-3">
          <div className="min-w-0 flex-1">
            <div className="truncate text-[13px] font-semibold text-ink">{item.name}</div>
            <div className="text-[11.5px] text-muted">
              {item.variantName ? `${item.variantName} · ` : ''}{formatMoney(item.unitPrice)} each
            </div>
          </div>
          <div className="flex shrink-0 items-center gap-1 rounded-lg bg-surface p-0.5">
            <button type="button" onClick={() => onQty(item.key, item.quantity - 1)} className="grid h-6 w-6 place-items-center rounded-md text-muted hover:bg-white hover:text-ink">
              <Minus className="h-3.5 w-3.5" strokeWidth={2.4} />
            </button>
            <span className="w-6 text-center text-[13px] font-semibold tabular-nums text-ink">{item.quantity}</span>
            <button type="button" onClick={() => onQty(item.key, item.quantity + 1)} className="grid h-6 w-6 place-items-center rounded-md text-muted hover:bg-white hover:text-ink">
              <Plus className="h-3.5 w-3.5" strokeWidth={2.4} />
            </button>
          </div>
          <div className="w-20 shrink-0 text-right text-[13px] font-bold tabular-nums text-ink">{formatMoney(item.unitPrice * item.quantity)}</div>
          <button type="button" onClick={() => onRemove(item.key)} className="grid h-7 w-7 shrink-0 place-items-center rounded-lg text-muted hover:bg-rose-50 hover:text-rose-600">
            <Trash2 className="h-3.5 w-3.5" strokeWidth={2.2} />
          </button>
        </div>
      ))}
    </div>
  );
}

/* ---------- Customer ---------- */

function CustomerSection({ mode, setMode, selected, setSelected, form, setForm }) {
  const [term, setTerm] = useState('');
  const [results, setResults] = useState([]);
  const debounced = useDebounced(term);

  useEffect(() => {
    if (mode !== 'existing' || debounced.trim().length < 2) {
      setResults([]);
      return;
    }
    posGet(`/customers?search=${encodeURIComponent(debounced.trim())}`)
      .then((data) => setResults(data.data ?? []))
      .catch(() => setResults([]));
  }, [debounced, mode]);

  const field = (key, label, type = 'text') => (
    <label className="block">
      <span className="text-[11.5px] font-semibold uppercase tracking-[0.03em] text-muted-2">{label}</span>
      <input
        type={type}
        value={form[key]}
        onChange={(e) => setForm({ ...form, [key]: e.target.value })}
        className="mt-1 w-full rounded-xl border border-line bg-white px-3 py-2 text-[13px] text-ink outline-none focus:border-[var(--color-brand)]"
      />
    </label>
  );

  return (
    <div className="rounded-2xl bg-white p-4 ring-1 ring-line/70">
      <div className="mb-3 flex items-center gap-1 rounded-xl bg-surface p-1">
        {[['new', 'New customer', UserPlus], ['existing', 'Existing', User]].map(([key, label, Icon]) => (
          <button
            key={key}
            type="button"
            onClick={() => setMode(key)}
            className={cn('flex flex-1 items-center justify-center gap-1.5 rounded-lg py-2 text-[12.5px] font-semibold transition-colors', mode === key ? 'bg-white text-ink shadow-sm' : 'text-muted hover:text-ink')}
          >
            <Icon className="h-3.5 w-3.5" strokeWidth={2.2} /> {label}
          </button>
        ))}
      </div>

      {mode === 'existing' ? (
        <div>
          {selected ? (
            <div className="flex items-center justify-between rounded-xl bg-orange-50/60 px-3 py-2.5 ring-1 ring-orange-600/15">
              <div className="min-w-0">
                <div className="truncate text-[13px] font-semibold text-ink">{selected.name}</div>
                <div className="truncate text-[11.5px] text-muted">{selected.phone || selected.email || ''}</div>
              </div>
              <button type="button" onClick={() => setSelected(null)} className="text-[12px] font-semibold text-[var(--color-brand-ink)] hover:underline">Change</button>
            </div>
          ) : (
            <>
              <div className="relative">
                <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-2" />
                <input value={term} onChange={(e) => setTerm(e.target.value)} placeholder="Search name, phone or email…" className="w-full rounded-xl border border-line bg-surface py-2.5 pl-9 pr-3 text-[13px] text-ink outline-none focus:border-[var(--color-brand)]" />
              </div>
              <div className="mt-2 max-h-40 space-y-1 overflow-y-auto scroll-thin">
                {results.map((c) => (
                  <button key={c.id} type="button" onClick={() => setSelected(c)} className="flex w-full items-center justify-between rounded-lg px-3 py-2 text-left transition-colors hover:bg-surface">
                    <span className="min-w-0 truncate text-[13px] font-medium text-ink">{c.name}</span>
                    <span className="shrink-0 text-[11.5px] text-muted">{c.phone || c.email}</span>
                  </button>
                ))}
              </div>
            </>
          )}
        </div>
      ) : (
        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
          {field('name', 'Name')}
          {field('phone', 'Phone')}
          {field('email', 'Email', 'email')}
          {field('address', 'Address')}
        </div>
      )}
    </div>
  );
}

/* ---------- Page ---------- */

export default function OrderCreate({ segment }) {
  const [cart, setCart] = useState([]);
  const [customerMode, setCustomerMode] = useState('new');
  const [customer, setCustomer] = useState(null);
  const [customerForm, setCustomerForm] = useState({ name: '', phone: '', email: '', address: '' });
  const [paymentMethod, setPaymentMethod] = useState('cash');
  const [paymentStatus, setPaymentStatus] = useState('pending');
  const [paymentReference, setPaymentReference] = useState('');
  const [shippingCost, setShippingCost] = useState('');
  const [notes, setNotes] = useState('');
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState(null);

  const addToCart = ({ productId, name, variantId, variantName, unitPrice }) => {
    const key = `${productId}-${variantId ?? ''}`;
    setCart((prev) => {
      const existing = prev.find((c) => c.key === key);
      if (existing) return prev.map((c) => (c.key === key ? { ...c, quantity: c.quantity + 1 } : c));
      return [...prev, { key, productId, name, variantId, variantName, unitPrice, quantity: 1 }];
    });
  };
  const setQty = (key, qty) => {
    if (qty < 1) return setCart((prev) => prev.filter((c) => c.key !== key));
    setCart((prev) => prev.map((c) => (c.key === key ? { ...c, quantity: qty } : c)));
  };
  const removeItem = (key) => setCart((prev) => prev.filter((c) => c.key !== key));

  const subtotal = useMemo(() => cart.reduce((sum, c) => sum + c.unitPrice * c.quantity, 0), [cart]);
  const shipping = parseFloat(shippingCost) || 0;
  const total = Math.max(0, subtotal + shipping);

  const canSubmit =
    cart.length > 0 &&
    !submitting &&
    (customerMode === 'existing' ? !!customer : customerForm.name.trim() && customerForm.phone.trim()) &&
    (paymentMethod !== 'bank_transfer' || paymentReference.trim());

  const submit = async () => {
    if (!canSubmit) return;
    setSubmitting(true);
    setError(null);

    const payload = {
      payment_method: paymentMethod,
      payment_status: paymentStatus,
      payment_reference: paymentMethod === 'bank_transfer' ? paymentReference : null,
      shipping_cost: shipping || null,
      notes: notes || null,
      items: cart.map((c) => ({
        itemable_type: 'product',
        itemable_id: c.productId,
        product_variant_id: c.variantId || null,
        quantity: c.quantity,
        unit_price: c.unitPrice,
      })),
      ...(customerMode === 'existing'
        ? { customer_id: customer.id }
        : { customer_name: customerForm.name, customer_phone: customerForm.phone, customer_email: customerForm.email || null, customer_address: customerForm.address || null }),
    };

    try {
      const res = await fetch('/api/pos/sales', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken(), Accept: 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify(payload),
      });
      if (!res.ok) {
        const data = await res.json().catch(() => null);
        throw new Error(data?.message || 'Could not create the order.');
      }
      router.visit('/fighter/orders');
    } catch (e) {
      setError(e.message);
      setSubmitting(false);
    }
  };

  const actions = (
    <button type="button" onClick={() => router.visit('/fighter/orders')} className="flex items-center gap-2 rounded-xl bg-slate-100 px-3.5 py-2.5 text-[13px] font-semibold text-ink-2 transition-colors hover:bg-slate-200">
      <ArrowLeft className="h-4 w-4" strokeWidth={2.2} /> Back to orders
    </button>
  );

  return (
    <FighterLayout title="New order" subtitle={`Recorded under your segment: ${segment?.name ?? 'Fighter'}`} actions={actions}>
      <div className="grid grid-cols-1 gap-5 lg:grid-cols-[1fr_360px]">
        {/* Left: products + cart */}
        <div className="flex flex-col gap-4">
          <ProductPicker onAdd={addToCart} />
          <Cart items={cart} onQty={setQty} onRemove={removeItem} />
        </div>

        {/* Right: customer, payment, summary */}
        <div className="flex flex-col gap-4">
          <CustomerSection mode={customerMode} setMode={setCustomerMode} selected={customer} setSelected={setCustomer} form={customerForm} setForm={setCustomerForm} />

          <div className="rounded-2xl bg-white p-4 ring-1 ring-line/70">
            <h3 className="text-[13px] font-semibold text-ink">Payment</h3>
            <div className="mt-3 grid grid-cols-3 gap-1.5">
              {['cash', 'bank_transfer', 'cod'].map((m) => (
                <button key={m} type="button" onClick={() => setPaymentMethod(m)} className={cn('rounded-lg py-2 text-[11.5px] font-semibold capitalize transition-colors', paymentMethod === m ? 'bg-[var(--color-brand)] text-white' : 'bg-surface text-ink-2 hover:bg-slate-200')}>
                  {m === 'bank_transfer' ? 'Bank' : m.toUpperCase()}
                </button>
              ))}
            </div>
            {paymentMethod === 'bank_transfer' && (
              <input value={paymentReference} onChange={(e) => setPaymentReference(e.target.value)} placeholder="Payment reference" className="mt-2 w-full rounded-xl border border-line bg-white px-3 py-2 text-[13px] text-ink outline-none focus:border-[var(--color-brand)]" />
            )}
            <div className="mt-2 grid grid-cols-2 gap-1.5">
              {['pending', 'paid'].map((s) => (
                <button key={s} type="button" onClick={() => setPaymentStatus(s)} className={cn('rounded-lg py-2 text-[12px] font-semibold capitalize transition-colors', paymentStatus === s ? 'bg-ink text-white' : 'bg-surface text-ink-2 hover:bg-slate-200')}>
                  {s}
                </button>
              ))}
            </div>
            <label className="mt-3 block">
              <span className="text-[11.5px] font-semibold uppercase tracking-[0.03em] text-muted-2">Shipping (optional)</span>
              <input type="number" min="0" step="0.01" value={shippingCost} onChange={(e) => setShippingCost(e.target.value)} placeholder="0.00" className="mt-1 w-full rounded-xl border border-line bg-white px-3 py-2 text-[13px] text-ink outline-none focus:border-[var(--color-brand)]" />
            </label>
          </div>

          <div className="rounded-2xl bg-white p-4 ring-1 ring-line/70">
            <div className="flex items-center justify-between text-[13px] text-ink-2">
              <span>Subtotal</span><span className="tabular-nums">{formatMoney(subtotal)}</span>
            </div>
            {shipping > 0 && (
              <div className="mt-1 flex items-center justify-between text-[13px] text-ink-2">
                <span>Shipping</span><span className="tabular-nums">{formatMoney(shipping)}</span>
              </div>
            )}
            <div className="mt-2 flex items-center justify-between border-t border-line/70 pt-2 text-[15px] font-bold text-ink">
              <span>Total</span><span className="tabular-nums">{formatMoney(total)}</span>
            </div>

            {error && <p className="mt-3 rounded-lg bg-rose-50 px-3 py-2 text-[12.5px] font-medium text-rose-700">{error}</p>}

            <button
              type="button"
              onClick={submit}
              disabled={!canSubmit}
              className="mt-3 flex w-full items-center justify-center gap-2 rounded-xl bg-[var(--color-brand)] py-3 text-[14px] font-semibold text-white transition-colors hover:bg-[var(--color-brand-ink)] disabled:cursor-not-allowed disabled:opacity-50"
            >
              {submitting ? <Loader2 className="h-4 w-4 animate-spin" /> : <CheckCircle2 className="h-4 w-4" strokeWidth={2.2} />}
              {submitting ? 'Creating…' : 'Create order'}
            </button>
          </div>
        </div>
      </div>
    </FighterLayout>
  );
}
