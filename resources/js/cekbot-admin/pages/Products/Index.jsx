import { useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import { Plus, Pencil, Trash2, Package, Link2 } from 'lucide-react';
import CekbotLayout from '@/cekbot-admin/layouts/CekbotLayout';
import { Card, Button, Badge, EmptyState } from '@/cekbot-admin/components/Ui';
import ProductModal from '@/cekbot-admin/components/products/ProductModal';

export default function Index() {
  const { props } = usePage();
  const products = props.products ?? [];
  const [modal, setModal] = useState({ open: false, editing: null });

  function del(product) {
    if (!window.confirm(`Padam produk "${product.name}"?`)) return;
    router.delete(route('cekbot.products.destroy', product.id), { preserveScroll: true });
  }

  return (
    <CekbotLayout
      title="Produk"
      subtitle="Product knowledge untuk bot jualan AI"
      actions={<Button variant="primary" onClick={() => setModal({ open: true, editing: null })}><Plus className="h-4 w-4" strokeWidth={2.4} /> Tambah produk</Button>}
    >
      <Head title="Produk" />

      {products.length === 0 ? (
        <EmptyState
          icon={Package}
          title="Belum ada produk"
          hint="Tambah produk (nama, harga, link, gambar) supaya bot AI boleh jawab soalan jualan dengan tepat."
          action={<Button variant="primary" onClick={() => setModal({ open: true, editing: null })}><Plus className="h-4 w-4" strokeWidth={2.4} /> Tambah produk</Button>}
        />
      ) : (
        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
          {products.map((p) => (
            <Card key={p.id} className="flex flex-col overflow-hidden">
              <div className="aspect-video w-full bg-white/5">
                {p.image_urls?.[0]
                  ? <img src={p.image_urls[0]} alt="" className="h-full w-full object-cover" />
                  : <div className="grid h-full place-items-center text-white/20"><Package className="h-8 w-8" /></div>}
              </div>
              <div className="flex flex-1 flex-col p-4">
                <div className="flex items-start justify-between gap-2">
                  <h3 className="text-[14px] font-bold text-white">{p.name}</h3>
                  <Badge color={p.is_active ? 'emerald' : 'slate'}>{p.is_active ? 'Aktif' : 'Off'}</Badge>
                </div>
                {p.price != null && <p className="mt-0.5 text-[14px] font-semibold text-emerald-300">{p.currency}{Number(p.price).toFixed(2)}</p>}
                {p.description && <p className="mt-1.5 line-clamp-2 text-[12.5px] text-white/50">{p.description}</p>}
                <div className="mt-2 flex flex-wrap items-center gap-2 text-[11.5px]">
                  {p.url && <a href={p.url} target="_blank" rel="noopener" className="truncate text-sky-300 hover:underline">Link produk ↗</a>}
                  {p.linked_product && <span className="inline-flex items-center gap-1 rounded-md bg-white/5 px-1.5 py-0.5 text-white/40"><Link2 className="h-3 w-3" /> {p.linked_product}</span>}
                </div>
                <div className="mt-auto flex items-center justify-end gap-1.5 pt-3">
                  <Button size="sm" variant="ghost" onClick={() => setModal({ open: true, editing: p })} aria-label="Edit"><Pencil className="h-3.5 w-3.5" /></Button>
                  <Button size="sm" variant="danger" onClick={() => del(p)} aria-label="Padam"><Trash2 className="h-3.5 w-3.5" /></Button>
                </div>
              </div>
            </Card>
          ))}
        </div>
      )}

      <ProductModal open={modal.open} editing={modal.editing} onClose={() => setModal({ open: false, editing: null })} />
    </CekbotLayout>
  );
}
