import { useState } from 'react';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { ArrowLeft, Pencil, Trash2, Link2, Plus, X, BookOpen, HelpCircle, Sparkles, Quote, MessageSquareQuote } from 'lucide-react';
import CekbotLayout from '@/cekbot-admin/layouts/CekbotLayout';
import { Card, Button, Badge, Field, Input, Textarea } from '@/cekbot-admin/components/Ui';
import ProductModal from '@/cekbot-admin/components/products/ProductModal';
import TestimonialModal from '@/cekbot-admin/components/products/TestimonialModal';
import ProductGallery from '@/cekbot-admin/components/products/ProductGallery';

export default function Show() {
  const { props } = usePage();
  const product = props.product;
  const [editing, setEditing] = useState(false);
  const [addTestimonial, setAddTestimonial] = useState(false);
  const testimonials = product.testimonials ?? [];

  const form = useForm({
    knowledge: product.knowledge ?? '',
    faqs: product.faqs?.length ? product.faqs : [{ question: '', answer: '' }],
  });

  function setFaq(i, key, value) {
    const faqs = form.data.faqs.map((f, idx) => (idx === i ? { ...f, [key]: value } : f));
    form.setData('faqs', faqs);
  }

  function addFaq() {
    form.setData('faqs', [...form.data.faqs, { question: '', answer: '' }]);
  }

  function removeFaq(i) {
    form.setData('faqs', form.data.faqs.filter((_, idx) => idx !== i));
  }

  function save(e) {
    e.preventDefault();
    form.post(route('cekbot.products.knowledge', product.id), { preserveScroll: true });
  }

  function del() {
    if (!window.confirm(`Padam produk "${product.name}"?`)) return;
    router.delete(route('cekbot.products.destroy', product.id));
  }

  function delTestimonial(t) {
    if (!window.confirm('Padam testimoni ini?')) return;
    router.delete(route('cekbot.products.testimonials.destroy', [product.id, t.id]), { preserveScroll: true });
  }

  return (
    <CekbotLayout
      title={product.name}
      subtitle="Product knowledge untuk bot jualan AI"
      actions={
        <>
          <Button variant="secondary" onClick={() => setEditing(true)}><Pencil className="h-4 w-4" /> Edit</Button>
          <Button variant="danger" onClick={del}><Trash2 className="h-4 w-4" /> Padam</Button>
        </>
      }
    >
      <Head title={product.name} />

      <Link href={route('cekbot.products')} className="mb-5 inline-flex items-center gap-1.5 text-[13px] font-medium text-white/50 transition-colors hover:text-white">
        <ArrowLeft className="h-4 w-4" /> Kembali ke Produk
      </Link>

      <div className="grid gap-5 lg:grid-cols-3">
        {/* Overview */}
        <div className="space-y-5">
          <ProductGallery product={product} />

          <Card className="space-y-3 p-4">
            <div className="flex items-center justify-between gap-2">
              <Badge color={product.is_active ? 'emerald' : 'slate'}>{product.is_active ? 'Aktif' : 'Off'}</Badge>
              {product.price != null && <span className="text-[16px] font-bold text-emerald-300">{product.currency}{Number(product.price).toFixed(2)}</span>}
            </div>
            {product.description && (
              <div>
                <p className="text-[11px] font-semibold uppercase tracking-wide text-white/35">Penerangan ringkas</p>
                <p className="mt-1 whitespace-pre-line text-[13px] leading-relaxed text-white/70">{product.description}</p>
              </div>
            )}
            {product.url && (
              <a href={product.url} target="_blank" rel="noopener" className="flex items-center gap-1.5 text-[12.5px] text-sky-300 hover:underline">
                <Link2 className="h-3.5 w-3.5" /> {product.url}
              </a>
            )}
            {product.linked_product && (
              <div className="flex items-center gap-1.5 text-[12px] text-white/45">
                <Link2 className="h-3.5 w-3.5" /> Dipautkan: {product.linked_product}
              </div>
            )}
            <p className="text-[11.5px] text-white/35">Klik <b className="text-white/55">Edit</b> untuk ubah nama, harga, gambar & link.</p>
          </Card>
        </div>

        {/* Knowledge editor */}
        <form onSubmit={save} className="space-y-5 lg:col-span-2">
          <Card className="p-5">
            <div className="mb-3 flex items-center gap-2">
              <div className="grid h-8 w-8 place-items-center rounded-lg bg-emerald-500/15 text-emerald-300"><BookOpen className="h-4 w-4" /></div>
              <div>
                <h3 className="text-[14.5px] font-bold text-white">Product knowledge</h3>
                <p className="text-[12px] text-white/45">Maklumat terperinci — spesifikasi, bahan, cara guna, penghantaran, jaminan, dsb. Bot AI guna ini untuk jawab soalan pelanggan.</p>
              </div>
            </div>
            <Field error={form.errors.knowledge}>
              <Textarea
                rows={12}
                value={form.data.knowledge}
                onChange={(e) => form.setData('knowledge', e.target.value)}
                placeholder={'Cth:\n• Bahan: 100% kurma Ajwa gred A dari Madinah\n• Berat: 500g setiap kotak\n• Penghantaran: 1–3 hari, free postage semenanjung\n• Simpanan: tempat kering & sejuk, elak cahaya matahari\n• Jaminan: 100% asli, refund jika rosak semasa penghantaran'}
              />
            </Field>
          </Card>

          <Card className="p-5">
            <div className="mb-3 flex items-center justify-between gap-2">
              <div className="flex items-center gap-2">
                <div className="grid h-8 w-8 place-items-center rounded-lg bg-sky-500/15 text-sky-300"><HelpCircle className="h-4 w-4" /></div>
                <div>
                  <h3 className="text-[14.5px] font-bold text-white">Soalan lazim (FAQ)</h3>
                  <p className="text-[12px] text-white/45">Pasangan soalan & jawapan yang biasa ditanya pelanggan.</p>
                </div>
              </div>
              <Button size="sm" variant="secondary" onClick={addFaq}><Plus className="h-3.5 w-3.5" /> Soalan</Button>
            </div>

            <div className="space-y-3">
              {form.data.faqs.map((faq, i) => (
                <div key={i} className="rounded-xl border border-white/8 bg-white/[0.03] p-3">
                  <div className="flex items-start gap-2">
                    <div className="flex-1 space-y-2">
                      <Input value={faq.question} onChange={(e) => setFaq(i, 'question', e.target.value)} placeholder="Soalan — cth: Berapa lama penghantaran?" />
                      <Textarea rows={2} value={faq.answer} onChange={(e) => setFaq(i, 'answer', e.target.value)} placeholder="Jawapan — cth: 1–3 hari bekerja untuk semenanjung." />
                    </div>
                    <button type="button" onClick={() => removeFaq(i)} className="mt-1 grid h-7 w-7 shrink-0 place-items-center rounded-lg text-white/40 hover:bg-rose-500/15 hover:text-rose-400" aria-label="Buang soalan">
                      <X className="h-4 w-4" />
                    </button>
                  </div>
                </div>
              ))}
              {form.data.faqs.length === 0 && (
                <button type="button" onClick={addFaq} className="flex w-full items-center justify-center gap-1.5 rounded-xl border border-dashed border-white/15 py-4 text-[13px] text-white/40 hover:border-white/30 hover:text-white/70">
                  <Plus className="h-4 w-4" /> Tambah soalan lazim
                </button>
              )}
            </div>
          </Card>

          <div className="flex items-center justify-between gap-3">
            <p className="flex items-center gap-1.5 text-[12px] text-white/40"><Sparkles className="h-3.5 w-3.5 text-emerald-400" /> Bot AI rujuk knowledge ini bila pelanggan tanya tentang produk.</p>
            <Button type="submit" variant="primary" loading={form.processing}>Simpan knowledge</Button>
          </div>
        </form>
      </div>

      {/* Testimonials */}
      <Card className="mt-5 p-5">
        <div className="mb-4 flex items-center justify-between gap-2">
          <div className="flex items-center gap-2">
            <div className="grid h-8 w-8 place-items-center rounded-lg bg-amber-500/15 text-amber-300"><MessageSquareQuote className="h-4 w-4" /></div>
            <div>
              <h3 className="text-[14.5px] font-bold text-white">Testimoni pelanggan</h3>
              <p className="text-[12px] text-white/45">Gambar & maklum balas pelanggan sebagai social proof. Bot AI boleh kongsi bila pelanggan teragak-agak.</p>
            </div>
          </div>
          <Button size="sm" variant="secondary" onClick={() => setAddTestimonial(true)}><Plus className="h-3.5 w-3.5" /> Tambah</Button>
        </div>

        {testimonials.length === 0 ? (
          <button type="button" onClick={() => setAddTestimonial(true)} className="flex w-full flex-col items-center justify-center gap-1.5 rounded-xl border border-dashed border-white/15 py-8 text-white/40 hover:border-white/30 hover:text-white/70">
            <Quote className="h-6 w-6" />
            <span className="text-[13px]">Belum ada testimoni — tambah gambar & teks pelanggan</span>
          </button>
        ) : (
          <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            {testimonials.map((t) => (
              <div key={t.id} className="group relative flex flex-col overflow-hidden rounded-xl border border-white/8 bg-white/[0.03]">
                {t.image_url && (
                  <a href={t.image_url} target="_blank" rel="noopener" className="block bg-white/5">
                    <img src={t.image_url} alt="" className="max-h-64 w-full object-contain" />
                  </a>
                )}
                <div className="flex flex-1 flex-col p-3.5">
                  {t.text && (
                    <p className="text-[13px] leading-relaxed text-white/75">
                      <Quote className="mr-1 inline h-3.5 w-3.5 -translate-y-0.5 text-white/25" />{t.text}
                    </p>
                  )}
                  {t.author && <p className="mt-2 text-[12px] font-semibold text-emerald-300">— {t.author}</p>}
                </div>
                <button type="button" onClick={() => delTestimonial(t)} className="absolute right-2 top-2 grid h-7 w-7 place-items-center rounded-lg bg-black/40 text-white/60 opacity-0 backdrop-blur transition group-hover:opacity-100 hover:bg-rose-500/25 hover:text-rose-300" aria-label="Padam testimoni">
                  <Trash2 className="h-3.5 w-3.5" />
                </button>
              </div>
            ))}
          </div>
        )}
      </Card>

      <ProductModal open={editing} editing={product} onClose={() => setEditing(false)} />
      <TestimonialModal open={addTestimonial} productId={product.id} onClose={() => setAddTestimonial(false)} />
    </CekbotLayout>
  );
}
