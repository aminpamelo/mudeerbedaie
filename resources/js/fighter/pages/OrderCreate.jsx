import { router } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import { Search, Plus, Minus, Trash2, ShoppingBag, ArrowLeft, Loader2, User, UserPlus, CheckCircle2, Star, X, Package, Banknote, Landmark, Truck, Upload, FileText, Paperclip, CreditCard } from 'lucide-react';
import FighterLayout from '@/fighter/layouts/FighterLayout';
import { cn, csrfToken, formatMoney } from '@/fighter/lib/utils';

/** Fetch helpers (session-authed). POS reads reuse /api/pos; catalog is /fighter. */
async function posGet(path) {
  const res = await fetch(`/api/pos${path}`, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
  if (!res.ok) throw new Error('request failed');
  return res.json();
}
async function fighterGet(path) {
  const res = await fetch(`/fighter${path}`, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
  if (!res.ok) throw new Error('request failed');
  return res.json();
}
async function fighterPost(path, body) {
  const res = await fetch(`/fighter${path}`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken(), Accept: 'application/json' },
    credentials: 'same-origin',
    body: JSON.stringify(body),
  });
  if (!res.ok) throw new Error('request failed');
  return res.json();
}

const MALAYSIA_STATES = [
  'Johor', 'Kedah', 'Kelantan', 'Melaka', 'Negeri Sembilan', 'Pahang', 'Perak', 'Perlis',
  'Pulau Pinang', 'Sabah', 'Sarawak', 'Selangor', 'Terengganu', 'Kuala Lumpur', 'Labuan', 'Putrajaya',
];

function useDebounced(value, delay = 300) {
  const [debounced, setDebounced] = useState(value);
  useEffect(() => {
    const id = setTimeout(() => setDebounced(value), delay);
    return () => clearTimeout(id);
  }, [value, delay]);
  return debounced;
}

/* ---------- Product browsing ---------- */

function ProductCard({ product, onClick, onToggleFav }) {
  return (
    <div className="group relative overflow-hidden rounded-2xl bg-white ring-1 ring-line/70 transition-shadow hover:shadow-[0_10px_30px_-16px_rgba(0,0,0,0.35)]">
      <button
        type="button"
        onClick={(e) => { e.stopPropagation(); onToggleFav(product); }}
        className={cn(
          'absolute right-2 top-2 z-10 grid h-7 w-7 place-items-center rounded-full backdrop-blur transition-colors',
          product.is_favourite ? 'bg-amber-400/90 text-white' : 'bg-white/80 text-muted-2 hover:text-amber-500'
        )}
        title={product.is_favourite ? 'Remove favourite' : 'Add to favourites'}
        aria-label="Toggle favourite"
      >
        <Star className="h-3.5 w-3.5" strokeWidth={2.2} fill={product.is_favourite ? 'currentColor' : 'none'} />
      </button>

      <button type="button" onClick={() => onClick(product)} className="block w-full text-left">
        <div className="aspect-[4/3] w-full bg-surface">
          {product.image ? (
            <img src={product.image} alt={product.name} className="h-full w-full object-cover" loading="lazy" />
          ) : (
            <div className="grid h-full w-full place-items-center text-muted-2"><Package className="h-7 w-7" strokeWidth={1.6} /></div>
          )}
        </div>
        <div className="p-3">
          <div className="line-clamp-2 min-h-[34px] text-[12.5px] font-semibold leading-snug text-ink">{product.name}</div>
          <div className="mt-1 flex items-center justify-between">
            <span className="text-[13px] font-bold text-ink">{formatMoney(product.base_price)}</span>
            {product.variants?.length > 0 ? (
              <span className="text-[10.5px] font-medium text-muted-2">{product.variants.length} variants</span>
            ) : (
              <span className="grid h-6 w-6 place-items-center rounded-lg bg-orange-50 text-[var(--color-brand)]"><Plus className="h-3.5 w-3.5" strokeWidth={2.6} /></span>
            )}
          </div>
        </div>
      </button>
    </div>
  );
}

function VariantPicker({ product, onPick, onClose }) {
  return (
    <div className="fixed inset-0 z-[70] flex items-end justify-center p-4 sm:items-center" role="dialog" aria-modal="true">
      <div className="absolute inset-0 bg-black/40 backdrop-blur-sm" onClick={onClose} />
      <div className="relative z-10 w-full max-w-md rounded-2xl bg-white p-5 shadow-xl">
        <div className="mb-3 flex items-center justify-between">
          <h3 className="text-[15px] font-semibold text-ink">Choose a variant</h3>
          <button type="button" onClick={onClose} className="grid h-8 w-8 place-items-center rounded-lg text-muted hover:bg-slate-100 hover:text-ink"><X className="h-4 w-4" strokeWidth={2.2} /></button>
        </div>
        <p className="mb-3 truncate text-[12.5px] text-muted">{product.name}</p>
        <div className="flex flex-col gap-1.5">
          {product.variants.map((v) => (
            <button
              key={v.id}
              type="button"
              onClick={() => onPick(v)}
              className="flex items-center justify-between rounded-xl bg-surface px-3.5 py-3 text-left transition-colors hover:bg-orange-50"
            >
              <span className="min-w-0">
                <span className="block truncate text-[13px] font-semibold text-ink">{v.name}</span>
                {v.sku && <span className="block truncate text-[11px] text-muted">SKU: {v.sku}</span>}
              </span>
              <span className="ml-3 shrink-0 text-[13px] font-bold text-ink">{formatMoney(v.price)}</span>
            </button>
          ))}
        </div>
      </div>
    </div>
  );
}

function ProductBrowser({ onAdd }) {
  const [term, setTerm] = useState('');
  const [favourites, setFavourites] = useState([]);
  const [products, setProducts] = useState([]);
  const [loading, setLoading] = useState(true);
  const [variantProduct, setVariantProduct] = useState(null);
  const debounced = useDebounced(term);

  const load = (search) => {
    setLoading(true);
    fighterGet(`/catalog${search ? `?search=${encodeURIComponent(search)}` : ''}`)
      .then((data) => {
        setFavourites(data.favourites ?? []);
        setProducts(data.products ?? []);
      })
      .catch(() => {})
      .finally(() => setLoading(false));
  };

  useEffect(() => { load(debounced.trim()); }, [debounced]);

  const clickProduct = (product) => {
    if (product.variants?.length > 0) {
      setVariantProduct(product);
    } else {
      onAdd({ productId: product.id, name: product.name, variantId: null, variantName: null, unitPrice: product.base_price });
    }
  };

  const toggleFav = async (product) => {
    const nowFav = !product.is_favourite;
    setProducts((prev) => prev.map((p) => (p.id === product.id ? { ...p, is_favourite: nowFav } : p)));
    setFavourites((prev) => {
      if (nowFav) {
        if (prev.some((p) => p.id === product.id)) return prev;
        return [...prev, { ...product, is_favourite: true }].sort((a, b) => a.name.localeCompare(b.name));
      }
      return prev.filter((p) => p.id !== product.id);
    });
    try {
      await fighterPost('/catalog/favourites', { product_id: product.id });
    } catch {
      load(debounced.trim());
    }
  };

  const gridProps = { onClick: clickProduct, onToggleFav: toggleFav };

  return (
    <div className="flex flex-col gap-4">
      <div className="relative">
        <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-2" />
        <input
          value={term}
          onChange={(e) => setTerm(e.target.value)}
          placeholder="Search products…"
          className="w-full rounded-xl border border-line bg-white py-2.5 pl-9 pr-3 text-[13.5px] text-ink outline-none focus:border-[var(--color-brand)]"
        />
      </div>

      {loading ? (
        <div className="grid place-items-center py-16 text-muted-2"><Loader2 className="h-6 w-6 animate-spin" /></div>
      ) : (
        <>
          {favourites.length > 0 && (
            <div>
              <div className="mb-2 flex items-center gap-1.5 text-[12px] font-semibold uppercase tracking-[0.04em] text-muted-2">
                <Star className="h-3.5 w-3.5 text-amber-400" fill="currentColor" /> Favourites
              </div>
              <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-4">
                {favourites.map((p) => <ProductCard key={`fav-${p.id}`} product={p} {...gridProps} />)}
              </div>
            </div>
          )}

          <div>
            {favourites.length > 0 && <div className="mb-2 text-[12px] font-semibold uppercase tracking-[0.04em] text-muted-2">All products</div>}
            {products.length === 0 ? (
              <p className="py-10 text-center text-[13px] text-muted">No products found.</p>
            ) : (
              <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-4">
                {products.map((p) => <ProductCard key={p.id} product={p} {...gridProps} />)}
              </div>
            )}
          </div>
        </>
      )}

      {variantProduct && (
        <VariantPicker
          product={variantProduct}
          onClose={() => setVariantProduct(null)}
          onPick={(v) => {
            onAdd({ productId: variantProduct.id, name: variantProduct.name, variantId: v.id, variantName: v.name, unitPrice: v.price });
            setVariantProduct(null);
          }}
        />
      )}
    </div>
  );
}

/* ---------- Cart ---------- */

function Cart({ items, onQty, onRemove }) {
  if (items.length === 0) {
    return (
      <div className="flex flex-col items-center justify-center rounded-2xl border border-dashed border-line bg-surface px-4 py-8 text-center">
        <ShoppingBag className="h-6 w-6 text-muted-2" strokeWidth={1.8} />
        <p className="mt-2 text-[13px] font-medium text-ink">Cart is empty</p>
        <p className="text-[12px] text-muted">Tap a product to add it.</p>
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
            <button type="button" onClick={() => onQty(item.key, item.quantity - 1)} className="grid h-6 w-6 place-items-center rounded-md text-muted hover:bg-white hover:text-ink"><Minus className="h-3.5 w-3.5" strokeWidth={2.4} /></button>
            <span className="w-6 text-center text-[13px] font-semibold tabular-nums text-ink">{item.quantity}</span>
            <button type="button" onClick={() => onQty(item.key, item.quantity + 1)} className="grid h-6 w-6 place-items-center rounded-md text-muted hover:bg-white hover:text-ink"><Plus className="h-3.5 w-3.5" strokeWidth={2.4} /></button>
          </div>
          <div className="w-20 shrink-0 text-right text-[13px] font-bold tabular-nums text-ink">{formatMoney(item.unitPrice * item.quantity)}</div>
          <button type="button" onClick={() => onRemove(item.key)} className="grid h-7 w-7 shrink-0 place-items-center rounded-lg text-muted hover:bg-rose-50 hover:text-rose-600"><Trash2 className="h-3.5 w-3.5" strokeWidth={2.2} /></button>
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
      <input type={type} value={form[key]} onChange={(e) => setForm({ ...form, [key]: e.target.value })} className="mt-1 w-full rounded-xl border border-line bg-white px-3 py-2 text-[13px] text-ink outline-none focus:border-[var(--color-brand)]" />
    </label>
  );

  return (
    <div className="rounded-2xl bg-white p-4 ring-1 ring-line/70">
      <div className="mb-3 flex items-center gap-1 rounded-xl bg-surface p-1">
        {[['new', 'New customer', UserPlus], ['existing', 'Existing', User]].map(([key, label, Icon]) => (
          <button key={key} type="button" onClick={() => setMode(key)} className={cn('flex flex-1 items-center justify-center gap-1.5 rounded-lg py-2 text-[12.5px] font-semibold transition-colors', mode === key ? 'bg-white text-ink shadow-sm' : 'text-muted hover:text-ink')}>
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
        <div className="grid grid-cols-2 gap-3">
          {field('name', 'Name')}
          {field('phone', 'Phone')}
          <div className="col-span-2">{field('email', 'Email', 'email')}</div>
          <div className="col-span-2">{field('address', 'Address (street, unit)')}</div>
          {field('postcode', 'Postcode')}
          {field('city', 'City')}
          <label className="col-span-2 block">
            <span className="text-[11.5px] font-semibold uppercase tracking-[0.03em] text-muted-2">State</span>
            <select
              value={form.state}
              onChange={(e) => setForm({ ...form, state: e.target.value })}
              className="mt-1 w-full rounded-xl border border-line bg-white px-3 py-2 text-[13px] text-ink outline-none focus:border-[var(--color-brand)]"
            >
              <option value="">Select state…</option>
              {MALAYSIA_STATES.map((s) => <option key={s} value={s}>{s}</option>)}
            </select>
          </label>
        </div>
      )}
    </div>
  );
}

/* ---------- Payment ---------- */

const PAYMENT_METHODS = [
  { key: 'cash', label: 'Cash', Icon: Banknote },
  { key: 'bank_transfer', label: 'Bank', Icon: Landmark },
  { key: 'cod', label: 'COD', Icon: Truck },
];

const MAX_RECEIPT_BYTES = 5 * 1024 * 1024;

/** Upload / preview a payment receipt. Only shown for Cash & Bank. */
function ReceiptUploader({ file, preview, onChange, onRemove }) {
  return (
    <div className="mt-3">
      <span className="text-[11.5px] font-semibold uppercase tracking-[0.03em] text-muted-2">Payment receipt (optional)</span>
      {!file ? (
        <label className="mt-1 flex cursor-pointer flex-col items-center justify-center rounded-xl border-2 border-dashed border-line py-4 text-center transition-colors hover:border-[var(--color-brand)] hover:bg-orange-50/40">
          <Upload className="h-5 w-5 text-muted-2" strokeWidth={1.8} />
          <span className="mt-1 text-[12px] font-medium text-ink-2">Upload receipt</span>
          <span className="text-[11px] text-muted-2">JPG, PNG, PDF, WebP · max 5MB</span>
          <input type="file" accept="image/jpeg,image/png,image/webp,application/pdf" onChange={onChange} className="hidden" />
        </label>
      ) : (
        <div className="mt-1 flex items-center gap-3 rounded-xl border border-line p-2.5">
          {preview ? (
            <img src={preview} alt="Receipt preview" className="h-10 w-10 shrink-0 rounded-lg object-cover" />
          ) : (
            <div className="grid h-10 w-10 shrink-0 place-items-center rounded-lg bg-rose-50 text-rose-500"><FileText className="h-5 w-5" strokeWidth={1.8} /></div>
          )}
          <div className="min-w-0 flex-1">
            <p className="truncate text-[12.5px] font-medium text-ink">{file.name}</p>
            <p className="text-[11px] text-muted-2">{(file.size / 1024).toFixed(0)} KB</p>
          </div>
          <button type="button" onClick={onRemove} className="grid h-7 w-7 shrink-0 place-items-center rounded-lg text-muted hover:bg-rose-50 hover:text-rose-600" aria-label="Remove receipt"><X className="h-4 w-4" strokeWidth={2.2} /></button>
        </div>
      )}
    </div>
  );
}

/* ---------- Review / confirm ---------- */

function ReviewRow({ label, value, sub }) {
  return (
    <div className="rounded-xl bg-surface px-3 py-2.5">
      <div className="text-[11px] font-semibold uppercase tracking-[0.03em] text-muted-2">{label}</div>
      <div className="mt-0.5 truncate text-[13px] font-medium text-ink">{value || '—'}</div>
      {sub && <div className="truncate text-[11.5px] text-muted">{sub}</div>}
    </div>
  );
}

function ReviewModal({ cart, subtotal, shipping, total, customerLine, paymentLine, paymentSub, receiptFile, notes, submitting, error, onConfirm, onClose }) {
  return (
    <div className="fixed inset-0 z-[70] flex items-end justify-center p-4 sm:items-center" role="dialog" aria-modal="true">
      <div className="absolute inset-0 bg-black/40 backdrop-blur-sm" onClick={submitting ? undefined : onClose} />
      <div className="relative z-10 flex max-h-[92dvh] w-full max-w-md flex-col overflow-hidden rounded-2xl bg-white shadow-xl">
        <div className="flex items-center justify-between border-b border-line/70 px-5 py-4">
          <h3 className="text-[15px] font-semibold text-ink">Confirm order</h3>
          <button type="button" onClick={onClose} disabled={submitting} className="grid h-8 w-8 place-items-center rounded-lg text-muted hover:bg-slate-100 hover:text-ink disabled:opacity-40" aria-label="Close"><X className="h-4 w-4" strokeWidth={2.2} /></button>
        </div>

        <div className="flex-1 overflow-y-auto px-5 py-4 scroll-thin">
          <div className="mb-1 text-[11.5px] font-semibold uppercase tracking-[0.03em] text-muted-2">Items</div>
          <div className="divide-y divide-line/70 overflow-hidden rounded-xl bg-surface">
            {cart.map((item) => (
              <div key={item.key} className="flex items-center justify-between gap-3 px-3 py-2">
                <div className="min-w-0">
                  <div className="truncate text-[13px] font-medium text-ink">{item.name}</div>
                  <div className="text-[11.5px] text-muted">{item.variantName ? `${item.variantName} · ` : ''}{item.quantity} × {formatMoney(item.unitPrice)}</div>
                </div>
                <div className="shrink-0 text-[13px] font-semibold tabular-nums text-ink">{formatMoney(item.unitPrice * item.quantity)}</div>
              </div>
            ))}
          </div>

          <div className="mt-4 grid grid-cols-2 gap-3">
            <ReviewRow label="Customer" value={customerLine.name} sub={customerLine.contact} />
            <ReviewRow label="Payment" value={paymentLine} sub={paymentSub} />
          </div>

          {receiptFile && (
            <div className="mt-3 flex items-center gap-2 rounded-xl bg-surface px-3 py-2.5">
              <Paperclip className="h-4 w-4 shrink-0 text-muted-2" />
              <span className="truncate text-[12.5px] text-ink-2">{receiptFile.name}</span>
            </div>
          )}
          {notes && <p className="mt-3 whitespace-pre-wrap rounded-xl bg-surface px-3 py-2.5 text-[12.5px] text-ink-2">{notes}</p>}

          <div className="mt-4 border-t border-line/70 pt-3">
            <div className="flex items-center justify-between text-[13px] text-ink-2"><span>Subtotal</span><span className="tabular-nums">{formatMoney(subtotal)}</span></div>
            {shipping > 0 && <div className="mt-1 flex items-center justify-between text-[13px] text-ink-2"><span>Shipping</span><span className="tabular-nums">{formatMoney(shipping)}</span></div>}
            <div className="mt-2 flex items-center justify-between text-[16px] font-bold text-ink"><span>Total</span><span className="tabular-nums">{formatMoney(total)}</span></div>
          </div>

          {error && <p className="mt-3 rounded-lg bg-rose-50 px-3 py-2 text-[12.5px] font-medium text-rose-700">{error}</p>}
        </div>

        <div className="flex gap-2 border-t border-line/70 px-5 py-4">
          <button type="button" onClick={onClose} disabled={submitting} className="flex-1 rounded-xl bg-slate-100 py-3 text-[13.5px] font-semibold text-ink-2 transition-colors hover:bg-slate-200 disabled:opacity-40">Back to edit</button>
          <button type="button" onClick={onConfirm} disabled={submitting} className="flex flex-[1.4] items-center justify-center gap-2 rounded-xl bg-[var(--color-brand)] py-3 text-[13.5px] font-semibold text-white transition-colors hover:bg-[var(--color-brand-ink)] disabled:opacity-50">
            {submitting ? <Loader2 className="h-4 w-4 animate-spin" /> : <CheckCircle2 className="h-4 w-4" strokeWidth={2.2} />}
            {submitting ? 'Creating…' : 'Confirm & create'}
          </button>
        </div>
      </div>
    </div>
  );
}

/* ---------- Page ---------- */

export default function OrderCreate({ segment }) {
  const [cart, setCart] = useState([]);
  const [customerMode, setCustomerMode] = useState('new');
  const [customer, setCustomer] = useState(null);
  const [customerForm, setCustomerForm] = useState({ name: '', phone: '', email: '', address: '', postcode: '', city: '', state: '' });
  const [paymentMethod, setPaymentMethod] = useState('cash');
  const [paymentStatus, setPaymentStatus] = useState('pending');
  const [paymentReference, setPaymentReference] = useState('');
  const [shippingCost, setShippingCost] = useState('');
  const [notes, setNotes] = useState('');
  const [receiptFile, setReceiptFile] = useState(null);
  const [receiptPreview, setReceiptPreview] = useState(null);
  const [reviewing, setReviewing] = useState(false);
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

  const removeReceipt = () => { setReceiptFile(null); setReceiptPreview(null); };

  const handleReceiptChange = (e) => {
    const file = e.target.files?.[0];
    if (!file) return;
    if (file.size > MAX_RECEIPT_BYTES) {
      setError('Receipt must be 5MB or smaller.');
      return;
    }
    setError(null);
    setReceiptFile(file);
    if (file.type.startsWith('image/')) {
      const reader = new FileReader();
      reader.onload = (ev) => setReceiptPreview(ev.target?.result ?? null);
      reader.readAsDataURL(file);
    } else {
      setReceiptPreview(null);
    }
  };

  // COD has no payment receipt — drop any attached file when switching to it.
  const changePaymentMethod = (m) => {
    setPaymentMethod(m);
    if (m === 'cod') removeReceipt();
  };

  const subtotal = useMemo(() => cart.reduce((sum, c) => sum + c.unitPrice * c.quantity, 0), [cart]);
  const cartCount = cart.reduce((sum, c) => sum + c.quantity, 0);
  const shipping = parseFloat(shippingCost) || 0;
  const total = Math.max(0, subtotal + shipping);

  const canSubmit =
    cart.length > 0 &&
    !submitting &&
    (customerMode === 'existing' ? !!customer : customerForm.name.trim() && customerForm.phone.trim()) &&
    (paymentMethod !== 'bank_transfer' || paymentReference.trim());

  const buildPayload = () => ({
    payment_method: paymentMethod,
    payment_status: paymentStatus,
    payment_reference: paymentMethod === 'bank_transfer' ? paymentReference : null,
    shipping_cost: shipping || null,
    notes: notes || null,
    items: cart.map((c) => ({ itemable_type: 'product', itemable_id: c.productId, product_variant_id: c.variantId || null, quantity: c.quantity, unit_price: c.unitPrice })),
    ...(customerMode === 'existing'
      ? { customer_id: customer.id }
      : {
          customer_name: customerForm.name,
          customer_phone: customerForm.phone,
          customer_email: customerForm.email || null,
          customer_address: customerForm.address || null,
          customer_postcode: customerForm.postcode || null,
          customer_city: customerForm.city || null,
          customer_state: customerForm.state || null,
        }),
  });

  const submit = async () => {
    if (!canSubmit) return;
    setSubmitting(true);
    setError(null);

    const payload = buildPayload();
    const authHeaders = { 'X-CSRF-TOKEN': csrfToken(), Accept: 'application/json' };

    try {
      let res;
      // A receipt file forces a multipart request; otherwise send plain JSON.
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
        res = await fetch('/api/pos/sales', { method: 'POST', headers: authHeaders, credentials: 'same-origin', body: fd });
      } else {
        res = await fetch('/api/pos/sales', {
          method: 'POST',
          headers: { ...authHeaders, 'Content-Type': 'application/json' },
          credentials: 'same-origin',
          body: JSON.stringify(payload),
        });
      }
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

  const customerLine = customerMode === 'existing'
    ? { name: customer?.name, contact: customer?.phone || customer?.email }
    : { name: customerForm.name, contact: customerForm.phone };
  const paymentLine = `${paymentMethod === 'bank_transfer' ? 'Bank transfer' : paymentMethod.toUpperCase()} · ${paymentStatus}`;
  const paymentSub = paymentMethod === 'bank_transfer' && paymentReference ? `Ref: ${paymentReference}` : null;

  const actions = (
    <button type="button" onClick={() => router.visit('/fighter/orders')} className="flex items-center gap-2 rounded-xl bg-slate-100 px-3.5 py-2.5 text-[13px] font-semibold text-ink-2 transition-colors hover:bg-slate-200">
      <ArrowLeft className="h-4 w-4" strokeWidth={2.2} /> Back to orders
    </button>
  );

  return (
    <FighterLayout title="New order" subtitle={`Recorded under your segment: ${segment?.name ?? 'Fighter'}`} actions={actions}>
      <div className="grid grid-cols-1 gap-5 lg:grid-cols-[1fr_380px] lg:items-start">
        {/* Left: product grid */}
        <ProductBrowser onAdd={addToCart} />

        {/* Right: cart + customer + payment + summary — pinned on desktop so the
            order is always visible while browsing products. */}
        <div className="flex flex-col gap-4 lg:sticky lg:top-4 lg:max-h-[calc(100dvh-2rem)] lg:overflow-y-auto lg:pr-1 scroll-thin">
          <div>
            <div className="mb-2 flex items-center justify-between px-1">
              <h3 className="flex items-center gap-1.5 text-[13px] font-semibold text-ink"><ShoppingBag className="h-3.5 w-3.5 text-muted-2" strokeWidth={2.2} /> Order items</h3>
              <span className="rounded-full bg-orange-50 px-2 py-0.5 text-[11.5px] font-semibold text-[var(--color-brand-ink)]">{cartCount} {cartCount === 1 ? 'item' : 'items'}</span>
            </div>
            <Cart items={cart} onQty={setQty} onRemove={removeItem} />
          </div>

          <CustomerSection mode={customerMode} setMode={setCustomerMode} selected={customer} setSelected={setCustomer} form={customerForm} setForm={setCustomerForm} />

          <div className="rounded-2xl bg-white p-4 ring-1 ring-line/70">
            <h3 className="flex items-center gap-1.5 text-[13px] font-semibold text-ink"><CreditCard className="h-3.5 w-3.5 text-muted-2" strokeWidth={2.2} /> Payment</h3>
            <div className="mt-3 grid grid-cols-3 gap-1.5">
              {PAYMENT_METHODS.map(({ key, label, Icon }) => (
                <button key={key} type="button" onClick={() => changePaymentMethod(key)} className={cn('flex flex-col items-center justify-center gap-1 rounded-lg py-2 text-[11.5px] font-semibold transition-colors', paymentMethod === key ? 'bg-[var(--color-brand)] text-white' : 'bg-surface text-ink-2 hover:bg-slate-200')}>
                  <Icon className="h-4 w-4" strokeWidth={2} /> {label}
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

            {/* Receipt upload — for Cash & Bank only (COD is paid on delivery). */}
            {paymentMethod !== 'cod' && (
              <ReceiptUploader file={receiptFile} preview={receiptPreview} onChange={handleReceiptChange} onRemove={removeReceipt} />
            )}

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

            {error && !reviewing && <p className="mt-3 rounded-lg bg-rose-50 px-3 py-2 text-[12.5px] font-medium text-rose-700">{error}</p>}

            <button type="button" onClick={() => { setError(null); setReviewing(true); }} disabled={!canSubmit} className="mt-3 flex w-full items-center justify-center gap-2 rounded-xl bg-[var(--color-brand)] py-3 text-[14px] font-semibold text-white transition-colors hover:bg-[var(--color-brand-ink)] disabled:cursor-not-allowed disabled:opacity-50">
              <CheckCircle2 className="h-4 w-4" strokeWidth={2.2} />
              Review &amp; create
            </button>
          </div>
        </div>
      </div>

      {reviewing && (
        <ReviewModal
          cart={cart}
          subtotal={subtotal}
          shipping={shipping}
          total={total}
          customerLine={customerLine}
          paymentLine={paymentLine}
          paymentSub={paymentSub}
          receiptFile={receiptFile}
          notes={notes}
          submitting={submitting}
          error={error}
          onConfirm={submit}
          onClose={() => { if (!submitting) { setReviewing(false); } }}
        />
      )}
    </FighterLayout>
  );
}
