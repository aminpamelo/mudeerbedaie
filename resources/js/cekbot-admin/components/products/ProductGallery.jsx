import { useEffect, useState } from 'react';
import { router } from '@inertiajs/react';
import { Package, X, ImagePlus, Link2, ChevronLeft, ChevronRight, Maximize2, Loader2 } from 'lucide-react';
import { Card } from '@/cekbot-admin/components/Ui';

export default function ProductGallery({ product }) {
  const images = [
    ...(product.own_images ?? []).map((img) => ({ url: img.url, path: img.path, linked: false })),
    ...(product.linked_image_urls ?? []).map((url) => ({ url, path: null, linked: true })),
  ];

  const [active, setActive] = useState(0);
  const [lightbox, setLightbox] = useState(false);
  const [uploading, setUploading] = useState(false);

  const index = images.length ? Math.min(active, images.length - 1) : 0;
  const current = images[index];

  useEffect(() => {
    if (!lightbox) return undefined;
    const onKey = (e) => {
      if (e.key === 'Escape') setLightbox(false);
      if (e.key === 'ArrowRight') setActive((i) => (i + 1) % images.length);
      if (e.key === 'ArrowLeft') setActive((i) => (i - 1 + images.length) % images.length);
    };
    document.addEventListener('keydown', onKey);
    return () => document.removeEventListener('keydown', onKey);
  }, [lightbox, images.length]);

  function upload(e) {
    const files = Array.from(e.target.files || []);
    e.target.value = '';
    if (!files.length) return;
    router.post(route('cekbot.products.images', product.id), { images: files }, {
      forceFormData: true,
      preserveScroll: true,
      onStart: () => setUploading(true),
      onFinish: () => setUploading(false),
    });
  }

  function remove(img) {
    if (!img.path) return;
    if (!window.confirm('Buang gambar ini?')) return;
    router.post(route('cekbot.products.images', product.id), { removed_images: [img.path] }, { preserveScroll: true });
  }

  return (
    <Card className="overflow-hidden">
      {/* Main viewer */}
      <div className="group relative aspect-square w-full bg-white/5">
        {current ? (
          <>
            <img src={current.url} alt="" className="h-full w-full object-contain" />
            <button
              type="button"
              onClick={() => setLightbox(true)}
              className="absolute right-2 top-2 grid h-8 w-8 place-items-center rounded-lg bg-black/40 text-white/70 opacity-0 backdrop-blur transition group-hover:opacity-100 hover:text-white"
              aria-label="Besarkan"
            >
              <Maximize2 className="h-4 w-4" />
            </button>
            {current.linked && (
              <span className="absolute left-2 top-2 inline-flex items-center gap-1 rounded-md bg-black/50 px-1.5 py-0.5 text-[10.5px] font-medium text-white/70 backdrop-blur">
                <Link2 className="h-3 w-3" /> Katalog
              </span>
            )}
            {images.length > 1 && (
              <>
                <button type="button" onClick={() => setActive((i) => (i - 1 + images.length) % images.length)} className="absolute left-2 top-1/2 grid h-8 w-8 -translate-y-1/2 place-items-center rounded-full bg-black/40 text-white/80 opacity-0 backdrop-blur transition group-hover:opacity-100 hover:bg-black/60" aria-label="Sebelum"><ChevronLeft className="h-4 w-4" /></button>
                <button type="button" onClick={() => setActive((i) => (i + 1) % images.length)} className="absolute right-2 top-1/2 grid h-8 w-8 -translate-y-1/2 place-items-center rounded-full bg-black/40 text-white/80 opacity-0 backdrop-blur transition group-hover:opacity-100 hover:bg-black/60" aria-label="Seterusnya"><ChevronRight className="h-4 w-4" /></button>
              </>
            )}
          </>
        ) : (
          <div className="grid h-full place-items-center text-white/20"><Package className="h-12 w-12" /></div>
        )}
        {uploading && (
          <div className="absolute inset-0 grid place-items-center bg-black/50 backdrop-blur-sm">
            <Loader2 className="h-6 w-6 animate-spin text-emerald-400" />
          </div>
        )}
      </div>

      {/* Thumbnails + upload */}
      <div className="flex flex-wrap gap-2 p-3">
        {images.map((img, i) => (
          <div key={`${img.url}-${i}`} className="group/thumb relative">
            <button
              type="button"
              onClick={() => setActive(i)}
              className={`block h-14 w-14 overflow-hidden rounded-lg ring-2 transition ${i === index ? 'ring-emerald-500' : 'ring-inset ring-white/10 hover:ring-white/25'}`}
            >
              <img src={img.url} alt="" className="h-full w-full object-cover" />
            </button>
            {img.linked
              ? <span className="absolute -right-1 -top-1 grid h-4 w-4 place-items-center rounded-full bg-slate-700 text-white/70" title="Dari katalog"><Link2 className="h-2.5 w-2.5" /></span>
              : <button type="button" onClick={() => remove(img)} className="absolute -right-1.5 -top-1.5 grid h-5 w-5 place-items-center rounded-full bg-rose-500 text-white opacity-0 transition group-hover/thumb:opacity-100" aria-label="Buang gambar"><X className="h-3 w-3" /></button>}
          </div>
        ))}
        <label className="grid h-14 w-14 cursor-pointer place-items-center rounded-lg border border-dashed border-white/15 text-white/40 transition hover:border-emerald-500/40 hover:text-emerald-300" title="Tambah gambar">
          <ImagePlus className="h-5 w-5" />
          <input type="file" accept="image/*" multiple onChange={upload} className="hidden" />
        </label>
      </div>
      <p className="px-3 pb-3 text-[11px] text-white/35">Gambar bertanda <Link2 className="inline h-3 w-3" /> datang dari produk katalog (urus di katalog).</p>

      {/* Lightbox */}
      {lightbox && current && (
        <div className="fixed inset-0 z-[80] flex items-center justify-center p-4" role="dialog" aria-modal="true" onClick={() => setLightbox(false)}>
          <div className="absolute inset-0 bg-black/85 backdrop-blur-sm" aria-hidden="true" />
          <button type="button" onClick={() => setLightbox(false)} className="absolute right-4 top-4 z-10 grid h-10 w-10 place-items-center rounded-full bg-white/10 text-white/80 hover:bg-white/20" aria-label="Tutup"><X className="h-5 w-5" /></button>
          {images.length > 1 && (
            <>
              <button type="button" onClick={(e) => { e.stopPropagation(); setActive((i) => (i - 1 + images.length) % images.length); }} className="absolute left-4 z-10 grid h-11 w-11 place-items-center rounded-full bg-white/10 text-white/80 hover:bg-white/20" aria-label="Sebelum"><ChevronLeft className="h-6 w-6" /></button>
              <button type="button" onClick={(e) => { e.stopPropagation(); setActive((i) => (i + 1) % images.length); }} className="absolute right-4 z-10 grid h-11 w-11 place-items-center rounded-full bg-white/10 text-white/80 hover:bg-white/20" aria-label="Seterusnya"><ChevronRight className="h-6 w-6" /></button>
            </>
          )}
          <img src={current.url} alt="" className="relative z-[1] max-h-[88vh] max-w-[92vw] object-contain" onClick={(e) => e.stopPropagation()} />
        </div>
      )}
    </Card>
  );
}
