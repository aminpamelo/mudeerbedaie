import { router, useForm } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { Package, Plus, Pencil, Trash2, Search, X, ImagePlus, ChevronLeft, ChevronRight, Lock } from 'lucide-react';
import FighterLayout from '@/fighter/layouts/FighterLayout';
import { cn, formatMoney } from '@/fighter/lib/utils';

function StatusPill({ status }) {
  const active = status === 'active';
  return (
    <span className={cn('rounded-full px-2 py-0.5 text-[10.5px] font-semibold ring-1', active ? 'bg-emerald-50 text-emerald-700 ring-emerald-600/20' : 'bg-slate-100 text-slate-500 ring-slate-500/20')}>
      {active ? 'Active' : 'Inactive'}
    </span>
  );
}

function ProductCard({ product, onEdit, onDelete }) {
  return (
    <div className="group relative overflow-hidden rounded-2xl bg-white ring-1 ring-line/70">
      <div className="aspect-[4/3] w-full bg-surface">
        {product.image ? (
          <img src={product.image} alt={product.name} className="h-full w-full object-cover" loading="lazy" />
        ) : (
          <div className="grid h-full w-full place-items-center text-muted-2"><Package className="h-7 w-7" strokeWidth={1.6} /></div>
        )}
      </div>
      <div className="p-3">
        <div className="flex items-start justify-between gap-2">
          <div className="line-clamp-2 min-h-[34px] text-[12.5px] font-semibold leading-snug text-ink">{product.name}</div>
          {product.editable ? <StatusPill status={product.status} /> : <Lock className="mt-0.5 h-3.5 w-3.5 shrink-0 text-muted-2" title="HQ product (view only)" />}
        </div>
        <div className="mt-1 flex items-center justify-between">
          <span className="text-[13px] font-bold text-ink">{formatMoney(product.base_price)}</span>
          {product.editable && (
            <div className="flex items-center gap-1">
              <button type="button" onClick={() => onEdit(product)} className="grid h-7 w-7 place-items-center rounded-lg bg-slate-100 text-ink-2 transition-colors hover:bg-slate-200" title="Edit"><Pencil className="h-3.5 w-3.5" strokeWidth={2.2} /></button>
              <button type="button" onClick={() => onDelete(product)} className="grid h-7 w-7 place-items-center rounded-lg bg-slate-100 text-muted transition-colors hover:bg-rose-50 hover:text-rose-600" title="Delete"><Trash2 className="h-3.5 w-3.5" strokeWidth={2.2} /></button>
            </div>
          )}
        </div>
      </div>
    </div>
  );
}

function ProductModal({ product, onClose }) {
  const editing = !!product;
  const form = useForm({
    name: product?.name ?? '',
    base_price: product?.base_price ?? '',
    description: product?.description ?? '',
    status: product?.status ?? 'active',
    image: null,
  });
  const [preview, setPreview] = useState(product?.image ?? null);

  useEffect(() => {
    const onKey = (e) => e.key === 'Escape' && onClose();
    document.addEventListener('keydown', onKey);
    return () => document.removeEventListener('keydown', onKey);
  }, [onClose]);

  const onImage = (e) => {
    const file = e.target.files?.[0];
    if (!file) return;
    form.setData('image', file);
    setPreview(URL.createObjectURL(file));
  };

  const submit = (e) => {
    e.preventDefault();
    const opts = { forceFormData: true, preserveScroll: true, onSuccess: onClose };
    if (editing) {
      form.transform((d) => ({ ...d, _method: 'put' })).post(`/fighter/products/${product.id}`, opts);
    } else {
      form.post('/fighter/products', opts);
    }
  };

  return (
    <div className="fixed inset-0 z-[70] flex items-end justify-center p-4 sm:items-center" role="dialog" aria-modal="true">
      <div className="absolute inset-0 bg-black/40 backdrop-blur-sm" onClick={onClose} />
      <form onSubmit={submit} className="relative z-10 flex max-h-[90dvh] w-full max-w-lg flex-col overflow-hidden rounded-2xl bg-white shadow-xl">
        <div className="flex items-center justify-between border-b border-line px-5 py-3.5">
          <h3 className="text-[15px] font-semibold text-ink">{editing ? 'Edit product' : 'New product'}</h3>
          <button type="button" onClick={onClose} className="grid h-8 w-8 place-items-center rounded-lg text-muted hover:bg-slate-100 hover:text-ink"><X className="h-4 w-4" strokeWidth={2.2} /></button>
        </div>

        <div className="flex-1 space-y-4 overflow-y-auto p-5">
          <div>
            <label className="flex aspect-[16/9] w-full cursor-pointer items-center justify-center overflow-hidden rounded-xl border border-dashed border-line bg-surface">
              {preview ? (
                <img src={preview} alt="" className="h-full w-full object-cover" />
              ) : (
                <span className="flex flex-col items-center gap-1 text-muted-2">
                  <ImagePlus className="h-6 w-6" strokeWidth={1.8} />
                  <span className="text-[12px] font-medium">Add product image</span>
                </span>
              )}
              <input type="file" accept="image/*" onChange={onImage} className="hidden" />
            </label>
            {form.errors.image && <p className="mt-1 text-[12px] text-rose-600">{form.errors.image}</p>}
          </div>

          <label className="block">
            <span className="text-[11.5px] font-semibold uppercase tracking-[0.03em] text-muted-2">Name</span>
            <input value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} className="mt-1 w-full rounded-xl border border-line bg-white px-3 py-2 text-[13.5px] text-ink outline-none focus:border-[var(--color-brand)]" />
            {form.errors.name && <p className="mt-1 text-[12px] text-rose-600">{form.errors.name}</p>}
          </label>

          <div className="grid grid-cols-2 gap-3">
            <label className="block">
              <span className="text-[11.5px] font-semibold uppercase tracking-[0.03em] text-muted-2">Price (RM)</span>
              <input type="number" min="0" step="0.01" value={form.data.base_price} onChange={(e) => form.setData('base_price', e.target.value)} className="mt-1 w-full rounded-xl border border-line bg-white px-3 py-2 text-[13.5px] text-ink outline-none focus:border-[var(--color-brand)]" />
              {form.errors.base_price && <p className="mt-1 text-[12px] text-rose-600">{form.errors.base_price}</p>}
            </label>
            <div>
              <span className="text-[11.5px] font-semibold uppercase tracking-[0.03em] text-muted-2">Status</span>
              <div className="mt-1 grid grid-cols-2 gap-1 rounded-xl bg-surface p-1">
                {['active', 'inactive'].map((s) => (
                  <button key={s} type="button" onClick={() => form.setData('status', s)} className={cn('rounded-lg py-1.5 text-[12px] font-semibold capitalize transition-colors', form.data.status === s ? 'bg-white text-ink shadow-sm' : 'text-muted hover:text-ink')}>{s}</button>
                ))}
              </div>
            </div>
          </div>

          <label className="block">
            <span className="text-[11.5px] font-semibold uppercase tracking-[0.03em] text-muted-2">Description (optional)</span>
            <textarea rows={3} value={form.data.description} onChange={(e) => form.setData('description', e.target.value)} className="mt-1 w-full resize-none rounded-xl border border-line bg-white px-3 py-2 text-[13.5px] text-ink outline-none focus:border-[var(--color-brand)]" />
          </label>
        </div>

        <div className="flex items-center justify-end gap-2 border-t border-line px-5 py-3.5">
          <button type="button" onClick={onClose} className="rounded-xl bg-slate-100 px-4 py-2.5 text-[13px] font-semibold text-ink-2 hover:bg-slate-200">Cancel</button>
          <button type="submit" disabled={form.processing} className="rounded-xl bg-[var(--color-brand)] px-4 py-2.5 text-[13px] font-semibold text-white hover:bg-[var(--color-brand-ink)] disabled:opacity-60">
            {form.processing ? 'Saving…' : editing ? 'Save changes' : 'Create product'}
          </button>
        </div>
      </form>
    </div>
  );
}

export default function Products({ myProducts = [], hq, search }) {
  const [modal, setModal] = useState(null); // {product} for edit, {} for create, null closed
  const [term, setTerm] = useState(search ?? '');
  const hqRows = hq?.data ?? [];
  const hqMeta = hq?.meta ?? { current_page: 1, last_page: 1, total: 0 };

  const onSearch = (e) => {
    e.preventDefault();
    router.get('/fighter/products', { search: term }, { preserveScroll: true, preserveState: true, replace: true });
  };

  const onDelete = (product) => {
    if (!window.confirm(`Delete "${product.name}"?`)) return;
    router.delete(`/fighter/products/${product.id}`, { preserveScroll: true });
  };

  const goHqPage = (page) => router.get('/fighter/products', { search: term, page }, { preserveScroll: true, preserveState: true });

  const actions = (
    <button type="button" onClick={() => setModal({})} className="flex items-center gap-2 rounded-xl bg-[var(--color-brand)] px-3.5 py-2.5 text-[13px] font-semibold text-white transition-colors hover:bg-[var(--color-brand-ink)]">
      <Plus className="h-4 w-4" strokeWidth={2.4} /> New product
    </button>
  );

  return (
    <FighterLayout title="Products" subtitle="Your own products (editable) and official HQ products (view-only)." actions={actions}>
      {/* My products */}
      <section>
        <h2 className="mb-3 text-[13px] font-semibold uppercase tracking-[0.04em] text-muted-2">My products</h2>
        {myProducts.length === 0 ? (
          <div className="flex flex-col items-center justify-center rounded-2xl border border-dashed border-line bg-surface px-6 py-12 text-center">
            <div className="grid h-12 w-12 place-items-center rounded-2xl bg-orange-50 text-[var(--color-brand)]"><Package className="h-6 w-6" strokeWidth={1.8} /></div>
            <p className="mt-3 text-[14px] font-semibold text-ink">No products yet</p>
            <p className="mt-1 text-[13px] text-muted">Add your own products to sell through your funnels and orders.</p>
            <button type="button" onClick={() => setModal({})} className="mt-4 flex items-center gap-2 rounded-xl bg-[var(--color-brand)] px-4 py-2.5 text-[13px] font-semibold text-white hover:bg-[var(--color-brand-ink)]"><Plus className="h-4 w-4" strokeWidth={2.4} /> Add product</button>
          </div>
        ) : (
          <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-4">
            {myProducts.map((p) => <ProductCard key={p.id} product={p} onEdit={(prod) => setModal({ product: prod })} onDelete={onDelete} />)}
          </div>
        )}
      </section>

      {/* HQ products */}
      <section className="mt-8">
        <div className="mb-3 flex flex-wrap items-center justify-between gap-3">
          <h2 className="flex items-center gap-1.5 text-[13px] font-semibold uppercase tracking-[0.04em] text-muted-2">
            <Lock className="h-3.5 w-3.5" /> HQ products · view only
          </h2>
          <form onSubmit={onSearch} className="relative w-full max-w-xs">
            <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-2" />
            <input value={term} onChange={(e) => setTerm(e.target.value)} placeholder="Search HQ products…" className="w-full rounded-xl border border-line bg-white py-2 pl-9 pr-3 text-[13px] text-ink outline-none focus:border-[var(--color-brand)]" />
          </form>
        </div>

        {hqRows.length === 0 ? (
          <p className="py-10 text-center text-[13px] text-muted">No HQ products found.</p>
        ) : (
          <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-4">
            {hqRows.map((p) => <ProductCard key={p.id} product={p} />)}
          </div>
        )}

        {hqMeta.last_page > 1 && (
          <div className="mt-4 flex items-center justify-between">
            <div className="text-[12.5px] text-muted">Page {hqMeta.current_page} of {hqMeta.last_page} · {hqMeta.total} products</div>
            <div className="flex items-center gap-2">
              <button type="button" disabled={hqMeta.current_page <= 1} onClick={() => goHqPage(hqMeta.current_page - 1)} className="flex items-center gap-1 rounded-lg bg-slate-100 px-3 py-2 text-[12.5px] font-semibold text-ink-2 hover:bg-slate-200 disabled:cursor-not-allowed disabled:opacity-40"><ChevronLeft className="h-4 w-4" strokeWidth={2.2} /> Prev</button>
              <button type="button" disabled={hqMeta.current_page >= hqMeta.last_page} onClick={() => goHqPage(hqMeta.current_page + 1)} className="flex items-center gap-1 rounded-lg bg-slate-100 px-3 py-2 text-[12.5px] font-semibold text-ink-2 hover:bg-slate-200 disabled:cursor-not-allowed disabled:opacity-40">Next <ChevronRight className="h-4 w-4" strokeWidth={2.2} /></button>
            </div>
          </div>
        )}
      </section>

      {modal && <ProductModal product={modal.product ?? null} onClose={() => setModal(null)} />}
    </FighterLayout>
  );
}
