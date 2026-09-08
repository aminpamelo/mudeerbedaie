import { useEffect } from 'react';
import { useForm } from '@inertiajs/react';
import { X, ImagePlus } from 'lucide-react';
import { Modal, Field, Input, Textarea, Button } from '@/cekbot-admin/components/Ui';

export default function TestimonialModal({ open, productId, onClose }) {
  const form = useForm({ author: '', text: '', image: null });

  useEffect(() => {
    if (!open) return;
    form.setData({ author: '', text: '', image: null });
    form.clearErrors();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [open]);

  function pickImage(e) {
    form.setData('image', e.target.files?.[0] ?? null);
    e.target.value = '';
  }

  function submit(e) {
    e.preventDefault();
    form.post(route('cekbot.products.testimonials.store', productId), {
      forceFormData: true,
      preserveScroll: true,
      onSuccess: onClose,
    });
  }

  return (
    <Modal
      open={open} onClose={onClose} size="md"
      title="Tambah testimoni"
      hint="Kongsi maklum balas pelanggan sebagai social proof. Gambar & teks kedua-duanya pilihan."
      footer={
        <>
          <Button variant="ghost" onClick={onClose}>Batal</Button>
          <Button type="submit" form="cekbot-testimonial-form" variant="primary" loading={form.processing}>Simpan</Button>
        </>
      }
    >
      <form id="cekbot-testimonial-form" onSubmit={submit} className="space-y-4">
        <Field label="Gambar (screenshot / gambar pelanggan)" error={form.errors.image}>
          {form.data.image ? (
            <div className="relative inline-block">
              <img src={URL.createObjectURL(form.data.image)} alt="" className="max-h-48 rounded-xl object-cover ring-1 ring-inset ring-white/10" />
              <button type="button" onClick={() => form.setData('image', null)} className="absolute -right-2 -top-2 grid h-6 w-6 place-items-center rounded-full bg-rose-500 text-white"><X className="h-3.5 w-3.5" /></button>
            </div>
          ) : (
            <label className="flex cursor-pointer flex-col items-center justify-center gap-1.5 rounded-xl border border-dashed border-white/15 py-8 text-white/40 hover:border-white/30 hover:text-white/70">
              <ImagePlus className="h-6 w-6" />
              <span className="text-[12.5px] font-medium">Muat naik gambar</span>
              <input type="file" accept="image/*" onChange={pickImage} className="hidden" />
            </label>
          )}
        </Field>

        <Field label="Teks testimoni" error={form.errors.text}>
          <Textarea rows={3} value={form.data.text} onChange={(e) => form.setData('text', e.target.value)} placeholder="Cth: Kurma sangat sedap & fresh! Penghantaran pun laju. Sudah repeat 3 kali." />
        </Field>

        <Field label="Nama pelanggan (pilihan)" error={form.errors.author}>
          <Input value={form.data.author} onChange={(e) => form.setData('author', e.target.value)} placeholder="Cth: Puan Aisyah, Shah Alam" />
        </Field>
      </form>
    </Modal>
  );
}
