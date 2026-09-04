import { useEffect, useState } from 'react';
import { useForm } from '@inertiajs/react';
import axios from 'axios';
import { Link2, X, Search } from 'lucide-react';
import { Modal, Field, Input, Textarea, Button } from '@/cekbot-admin/components/Ui';

export default function ProductModal({ open, editing, onClose }) {
  const isEdit = Boolean(editing);
  const form = useForm({
    name: '', price: '', currency: 'RM', url: '', description: '', product_id: null, is_active: true, images: [], removed_images: [],
  });

  const [existing, setExisting] = useState([]); // existing own image paths
  const [linkedName, setLinkedName] = useState(null);
  const [search, setSearch] = useState('');
  const [results, setResults] = useState([]);
  const [searching, setSearching] = useState(false);

  useEffect(() => {
    if (!open) return;
    form.setData({
      name: editing?.name ?? '', price: editing?.price ?? '', currency: editing?.currency ?? 'RM',
      url: editing?.url ?? '', description: editing?.description ?? '', product_id: editing?.product_id ?? null,
      is_active: editing?.is_active ?? true, images: [], removed_images: [],
    });
    form.clearErrors();
    setExisting(editing?.images ?? []);
    setLinkedName(editing?.linked_product ?? null);
    setSearch(''); setResults([]);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [open, editing?.id]);

  async function runSearch() {
    setSearching(true);
    try {
      const { data } = await axios.get(route('cekbot.products.search'), { params: { q: search } });
      setResults(data.data || []);
    } catch { setResults([]); } finally { setSearching(false); }
  }

  function pickCatalog(p) {
    form.setData('product_id', p.id);
    if (!form.data.name) form.setData('name', p.name);
    if (!form.data.price && p.price) form.setData('price', p.price);
    setLinkedName(p.name);
    setResults([]); setSearch('');
  }

  function removeExisting(path) {
    setExisting((prev) => prev.filter((p) => p !== path));
    form.setData('removed_images', [...form.data.removed_images, path]);
  }

  function addFiles(e) {
    form.setData('images', [...form.data.images, ...Array.from(e.target.files || [])]);
    e.target.value = '';
  }

  function removeNewFile(idx) {
    form.setData('images', form.data.images.filter((_, i) => i !== idx));
  }

  function submit(e) {
    e.preventDefault();
    const opts = { forceFormData: true, preserveScroll: true, onSuccess: onClose };
    if (isEdit) {
      form.post(route('cekbot.products.update', editing.id), opts);
    } else {
      form.post(route('cekbot.products.store'), opts);
    }
  }

  return (
    <Modal
      open={open} onClose={onClose} size="lg"
      title={isEdit ? 'Edit produk' : 'Tambah produk'}
      hint="Maklumat ini digunakan oleh bot AI untuk jawab soalan jualan."
      footer={
        <>
          <Button variant="ghost" onClick={onClose}>Batal</Button>
          <Button type="submit" form="cekbot-product-form" variant="primary" loading={form.processing}>Simpan</Button>
        </>
      }
    >
      <form id="cekbot-product-form" onSubmit={submit} className="space-y-4">
        {/* Catalog link */}
        <div className="rounded-xl border border-white/8 bg-white/[0.03] p-3">
          <p className="mb-1.5 flex items-center gap-1.5 text-[12.5px] font-semibold text-white/70"><Link2 className="h-3.5 w-3.5" /> Link ke produk katalog (pilihan)</p>
          {form.data.product_id ? (
            <div className="flex items-center justify-between gap-2 rounded-lg bg-emerald-500/10 px-3 py-2 text-[13px] text-emerald-200">
              <span>Dipautkan: {linkedName || `#${form.data.product_id}`}</span>
              <button type="button" onClick={() => { form.setData('product_id', null); setLinkedName(null); }} className="text-white/50 hover:text-white"><X className="h-4 w-4" /></button>
            </div>
          ) : (
            <>
              <div className="flex gap-2">
                <Input value={search} onChange={(e) => setSearch(e.target.value)} onKeyDown={(e) => { if (e.key === 'Enter') { e.preventDefault(); runSearch(); } }} placeholder="Cari nama / SKU produk…" />
                <Button variant="secondary" loading={searching} onClick={runSearch}><Search className="h-4 w-4" /></Button>
              </div>
              {results.length > 0 && (
                <div className="mt-2 max-h-40 space-y-1 overflow-y-auto">
                  {results.map((p) => (
                    <button key={p.id} type="button" onClick={() => pickCatalog(p)} className="flex w-full items-center gap-2 rounded-lg px-2 py-1.5 text-left text-[12.5px] text-white/80 hover:bg-white/8">
                      {p.image ? <img src={p.image} alt="" className="h-7 w-7 rounded object-cover" /> : <span className="h-7 w-7 rounded bg-white/10" />}
                      <span className="flex-1 truncate">{p.name}</span>
                      {p.price && <span className="text-white/50">RM{Number(p.price).toFixed(2)}</span>}
                    </button>
                  ))}
                </div>
              )}
            </>
          )}
        </div>

        <Field label="Nama produk" error={form.errors.name}>
          <Input value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} placeholder="Cth: Kurma Ajwa Premium 500g" autoFocus />
        </Field>

        <div className="grid grid-cols-2 gap-3">
          <Field label="Harga" error={form.errors.price}>
            <Input type="number" step="0.01" min="0" value={form.data.price} onChange={(e) => form.setData('price', e.target.value)} placeholder="99.00" />
          </Field>
          <Field label="Mata wang">
            <Input value={form.data.currency} onChange={(e) => form.setData('currency', e.target.value)} placeholder="RM" />
          </Field>
        </div>

        <Field label="Link produk (pilihan)" error={form.errors.url}>
          <Input value={form.data.url} onChange={(e) => form.setData('url', e.target.value)} placeholder="https://…" />
        </Field>

        <Field label="Penerangan / selling points" hint="AI guna ini untuk jawab soalan pelanggan." error={form.errors.description}>
          <Textarea rows={4} value={form.data.description} onChange={(e) => form.setData('description', e.target.value)} placeholder="Ciri-ciri, kelebihan, cara guna, bahan, dsb." />
        </Field>

        {/* Images */}
        <Field label="Gambar produk" error={form.errors['images.0']}>
          <div className="flex flex-wrap gap-2">
            {existing.map((path) => (
              <div key={path} className="relative h-16 w-16">
                <img src={`/storage/${path}`} alt="" className="h-16 w-16 rounded-lg object-cover" />
                <button type="button" onClick={() => removeExisting(path)} className="absolute -right-1.5 -top-1.5 grid h-5 w-5 place-items-center rounded-full bg-rose-500 text-white"><X className="h-3 w-3" /></button>
              </div>
            ))}
            {form.data.images.map((file, i) => (
              <div key={i} className="relative h-16 w-16">
                <img src={URL.createObjectURL(file)} alt="" className="h-16 w-16 rounded-lg object-cover" />
                <button type="button" onClick={() => removeNewFile(i)} className="absolute -right-1.5 -top-1.5 grid h-5 w-5 place-items-center rounded-full bg-rose-500 text-white"><X className="h-3 w-3" /></button>
              </div>
            ))}
            <label className="grid h-16 w-16 cursor-pointer place-items-center rounded-lg border border-dashed border-white/15 text-white/40 hover:border-white/30 hover:text-white/70">
              <span className="text-2xl leading-none">+</span>
              <input type="file" accept="image/*" multiple onChange={addFiles} className="hidden" />
            </label>
          </div>
        </Field>

        <label className="flex items-center gap-2 text-[13px] text-white/70">
          <input type="checkbox" checked={form.data.is_active} onChange={(e) => form.setData('is_active', e.target.checked)} className="h-4 w-4 rounded border-white/20 bg-white/10 text-emerald-500" />
          Aktif (bot guna produk ini)
        </label>
      </form>
    </Modal>
  );
}
