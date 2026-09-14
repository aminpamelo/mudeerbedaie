import { useMemo, useState } from 'react';
import { Head, useForm, usePage } from '@inertiajs/react';
import { ArrowLeft, Plus, Trash2, X, Workflow, Banknote, Truck, MessageSquareText, Tag } from 'lucide-react';
import CekbotLayout from '@/cekbot-admin/layouts/CekbotLayout';
import { Card, Button, Field, Input, Textarea, Select, Toggle } from '@/cekbot-admin/components/Ui';
import { buildPreview } from '@/cekbot-admin/lib/flowPreview';
import { cn } from '@/cekbot-admin/lib/utils';

/** Render WhatsApp-style *bold* segments. */
function WaText({ text }) {
  const parts = String(text || '').split('*');
  return (
    <span className="whitespace-pre-wrap break-words">
      {parts.map((part, i) => (i % 2 === 1 ? <strong key={i}>{part}</strong> : <span key={i}>{part}</span>))}
    </span>
  );
}

function SectionCard({ icon: Icon, title, hint, children }) {
  return (
    <Card className="p-5">
      <div className="mb-4 flex items-center gap-2">
        <span className="grid h-8 w-8 place-items-center rounded-lg bg-emerald-500/15"><Icon className="h-4 w-4 text-emerald-400" /></span>
        <div>
          <h3 className="text-[14px] font-bold text-white">{title}</h3>
          {hint && <p className="text-[11.5px] text-white/40">{hint}</p>}
        </div>
      </div>
      {children}
    </Card>
  );
}

export default function Show() {
  const { props } = usePage();
  const flow = props.flow;
  const products = props.products ?? [];
  const salesSources = props.salesSources ?? [];

  const form = useForm({
    name: flow.name ?? '',
    is_active: flow.is_active ?? false,
    match_type: flow.match_type ?? 'contains',
    trigger_keywords: flow.trigger_keywords ?? [],
    welcome_message: flow.welcome_message ?? '',
    package_prompt: flow.package_prompt ?? '',
    confirmation_message: flow.confirmation_message ?? '',
    ask_payment: flow.ask_payment ?? true,
    payment_transfer_enabled: flow.payment_transfer_enabled ?? true,
    payment_cod_enabled: flow.payment_cod_enabled ?? true,
    bank_details: flow.bank_details ?? '',
    transfer_instructions: flow.transfer_instructions ?? '',
    ask_name: flow.ask_name ?? true,
    sales_source_id: flow.sales_source_id ?? '',
    packages: (flow.packages ?? []).map((p) => ({
      id: p.id,
      cekbot_product_id: p.cekbot_product_id ?? '',
      label: p.label ?? '',
      price: p.price ?? '',
      currency: p.currency ?? 'RM',
    })),
  });

  const { data, setData, errors } = form;

  const [keywordInput, setKeywordInput] = useState('');
  function addKeyword() {
    const k = keywordInput.trim();
    if (!k) return;
    if (!data.trigger_keywords.includes(k)) setData('trigger_keywords', [...data.trigger_keywords, k]);
    setKeywordInput('');
  }
  function removeKeyword(k) {
    setData('trigger_keywords', data.trigger_keywords.filter((x) => x !== k));
  }

  function addPackage() {
    setData('packages', [...data.packages, { cekbot_product_id: '', label: '', price: '', currency: 'RM' }]);
  }
  function updatePackage(index, patch) {
    setData('packages', data.packages.map((p, i) => (i === index ? { ...p, ...patch } : p)));
  }
  function removePackage(index) {
    setData('packages', data.packages.filter((_, i) => i !== index));
  }
  function onSelectProduct(index, productId) {
    if (!productId) {
      updatePackage(index, { cekbot_product_id: '' });
      return;
    }
    const product = products.find((p) => String(p.id) === String(productId));
    updatePackage(index, {
      cekbot_product_id: productId,
      label: data.packages[index].label || product?.name || '',
      price: data.packages[index].price === '' && product?.price != null ? product.price : data.packages[index].price,
      currency: product?.currency || data.packages[index].currency || 'RM',
    });
  }

  function save(e) {
    e?.preventDefault();
    form.put(route('cekbot.flows.update', flow.id), { preserveScroll: true });
  }

  const bothPayments = data.ask_payment && data.payment_transfer_enabled && data.payment_cod_enabled;
  const [previewPath, setPreviewPath] = useState('cod');
  const effectivePath = data.payment_cod_enabled ? previewPath : 'transfer';
  const preview = useMemo(() => buildPreview(data, effectivePath), [data, effectivePath]);

  return (
    <CekbotLayout
      title={data.name || 'Flow'}
      subtitle={`Funnel untuk ${flow.session_label ?? 'nombor ini'}`}
      actions={
        <>
          <Button variant="ghost" href={route('cekbot.flows')}><ArrowLeft className="h-4 w-4" /> Kembali</Button>
          <Button variant="primary" onClick={save} loading={form.processing}>Simpan Flow</Button>
        </>
      }
    >
      <Head title={`Flow — ${data.name || 'Baharu'}`} />

      <div className="grid gap-5 lg:grid-cols-[1fr_380px]">
        {/* Builder */}
        <form onSubmit={save} className="space-y-5">
          <SectionCard icon={Workflow} title="Asas & pencetus" hint="Bila flow ini bermula.">
            <div className="space-y-4">
              <div className="flex items-center justify-between gap-3 rounded-xl border border-white/8 bg-white/[0.03] p-3.5">
                <div>
                  <p className="text-[13px] font-semibold text-white/80">Aktifkan flow</p>
                  <p className="text-[11.5px] text-white/40">Bila off, bot ikut auto-reply/AI biasa.</p>
                </div>
                <Toggle checked={data.is_active} onChange={(v) => setData('is_active', v)} />
              </div>

              <Field label="Nama flow" error={errors.name}>
                <Input value={data.name} onChange={(e) => setData('name', e.target.value)} placeholder="Cth: Funnel Pakej Kurma" />
              </Field>

              <Field
                label="Keyword pencetus"
                hint="Bila mesej pelanggan mengandungi salah satu keyword ni, flow bermula. Tekan Enter untuk tambah."
                error={errors.trigger_keywords}
              >
                <div className="flex gap-2">
                  <Input
                    value={keywordInput}
                    onChange={(e) => setKeywordInput(e.target.value)}
                    onKeyDown={(e) => { if (e.key === 'Enter' || e.key === ',') { e.preventDefault(); addKeyword(); } }}
                    placeholder="Cth: minat, nak order, berminat"
                  />
                  <Button variant="secondary" onClick={addKeyword}><Plus className="h-4 w-4" /> Tambah</Button>
                </div>
                {data.trigger_keywords.length > 0 && (
                  <div className="mt-2 flex flex-wrap gap-1.5">
                    {data.trigger_keywords.map((k) => (
                      <span key={k} className="inline-flex items-center gap-1 rounded-lg bg-white/8 px-2 py-1 text-[12px] font-medium text-white/75 ring-1 ring-inset ring-white/10">
                        {k}
                        <button type="button" onClick={() => removeKeyword(k)} className="text-white/40 hover:text-rose-300" aria-label={`Buang ${k}`}><X className="h-3 w-3" /></button>
                      </span>
                    ))}
                  </div>
                )}
              </Field>

              <Field label="Padanan keyword" className="w-full sm:w-56">
                <Select value={data.match_type} onChange={(e) => setData('match_type', e.target.value)}>
                  <option value="contains">Mengandungi</option>
                  <option value="starts">Bermula dengan</option>
                  <option value="exact">Sama tepat</option>
                </Select>
              </Field>
            </div>
          </SectionCard>

          <SectionCard icon={MessageSquareText} title="Mesej alu-aluan" hint="Mesej pertama + arahan pilih pakej.">
            <div className="space-y-4">
              <Field label="Mesej alu-aluan (pilihan)" error={errors.welcome_message}>
                <Textarea rows={2} value={data.welcome_message} onChange={(e) => setData('welcome_message', e.target.value)}
                  placeholder="Cth: Salam! 🙌 Terima kasih berminat dengan produk kami." />
              </Field>
              <Field label="Arahan pilih pakej (pilihan)" hint="Default: 'Balas nombor pakej yang berminat 🙂'" error={errors.package_prompt}>
                <Input value={data.package_prompt} onChange={(e) => setData('package_prompt', e.target.value)}
                  placeholder="Balas nombor pakej yang berminat 🙂" />
              </Field>
            </div>
          </SectionCard>

          <SectionCard icon={Tag} title="Pakej ditawarkan" hint="Senarai pilihan yang bot tunjuk (bernombor).">
            <div className="space-y-3">
              {data.packages.length === 0 && (
                <p className="rounded-xl border border-dashed border-white/10 py-6 text-center text-[12.5px] text-white/40">
                  Belum ada pakej. Tambah sekurang-kurangnya satu untuk flow berfungsi.
                </p>
              )}
              {data.packages.map((pkg, i) => (
                <div key={pkg.id ?? `new-${i}`} className="rounded-xl border border-white/8 bg-white/[0.03] p-3.5">
                  <div className="mb-2.5 flex items-center justify-between">
                    <span className="text-[12px] font-semibold text-white/50">Pakej {i + 1}</span>
                    <button type="button" onClick={() => removePackage(i)} className="text-white/40 hover:text-rose-300" aria-label="Buang pakej"><Trash2 className="h-3.5 w-3.5" /></button>
                  </div>
                  <div className="grid gap-2.5 sm:grid-cols-2">
                    <Field label="Produk (pilihan)" className="sm:col-span-2">
                      <Select value={pkg.cekbot_product_id || ''} onChange={(e) => onSelectProduct(i, e.target.value)}>
                        <option value="">— Custom (tiada link produk) —</option>
                        {products.map((p) => (
                          <option key={p.id} value={p.id}>{p.name}{p.price != null ? ` (${p.currency}${p.price})` : ''}</option>
                        ))}
                      </Select>
                    </Field>
                    <Field label="Label dipapar" error={errors[`packages.${i}.label`]}>
                      <Input value={pkg.label} onChange={(e) => updatePackage(i, { label: e.target.value })} placeholder="Cth: Pakej Jimat" />
                    </Field>
                    <div className="flex gap-2">
                      <Field label="Harga" className="flex-1">
                        <Input type="number" step="0.01" min="0" value={pkg.price} onChange={(e) => updatePackage(i, { price: e.target.value })} placeholder="97" />
                      </Field>
                      <Field label="Mata wang" className="w-24">
                        <Input value={pkg.currency} onChange={(e) => updatePackage(i, { currency: e.target.value })} placeholder="RM" />
                      </Field>
                    </div>
                  </div>
                </div>
              ))}
              <Button variant="secondary" onClick={addPackage}><Plus className="h-4 w-4" /> Tambah pakej</Button>
            </div>
          </SectionCard>

          <SectionCard icon={Banknote} title="Pembayaran" hint="Cara pelanggan boleh bayar.">
            <div className="space-y-4">
              <label className="flex items-center justify-between gap-3">
                <span className="text-[13px] text-white/70">Tanya cara bayar</span>
                <Toggle checked={data.ask_payment} onChange={(v) => setData('ask_payment', v)} />
              </label>

              {data.ask_payment && (
                <div className="space-y-4">
                  <div className="rounded-xl border border-white/8 bg-white/[0.03] p-3.5">
                    <label className="flex items-center justify-between gap-3">
                      <span className="flex items-center gap-1.5 text-[13px] font-semibold text-white/80"><Banknote className="h-4 w-4 text-emerald-300" /> Transfer / Online Banking</span>
                      <Toggle checked={data.payment_transfer_enabled} onChange={(v) => setData('payment_transfer_enabled', v)} />
                    </label>
                    {data.payment_transfer_enabled && (
                      <div className="mt-3 space-y-3">
                        <Field label="Maklumat bank (dipapar bila pilih transfer)" error={errors.bank_details}>
                          <Textarea rows={3} value={data.bank_details} onChange={(e) => setData('bank_details', e.target.value)}
                            placeholder={'Maybank 5121xxxxxxx\nNama: Kedai ABC Sdn Bhd'} />
                        </Field>
                        <Field label="Arahan tambahan (pilihan)" error={errors.transfer_instructions}>
                          <Input value={data.transfer_instructions} onChange={(e) => setData('transfer_instructions', e.target.value)}
                            placeholder="Cth: Guna nama penuh sebagai rujukan." />
                        </Field>
                      </div>
                    )}
                  </div>

                  <div className="rounded-xl border border-white/8 bg-white/[0.03] p-3.5">
                    <label className="flex items-center justify-between gap-3">
                      <span className="flex items-center gap-1.5 text-[13px] font-semibold text-white/80"><Truck className="h-4 w-4 text-sky-300" /> COD (Bayar semasa terima)</span>
                      <Toggle checked={data.payment_cod_enabled} onChange={(v) => setData('payment_cod_enabled', v)} />
                    </label>
                    {data.payment_cod_enabled && (
                      <p className="mt-2 text-[11.5px] text-white/40">Bot akan minta alamat penuh untuk penghantaran COD.</p>
                    )}
                  </div>

                  {!data.payment_transfer_enabled && !data.payment_cod_enabled && (
                    <p className="text-[11.5px] text-amber-300/80">⚠️ Tiada cara bayar dihidupkan — order dicipta tanpa kaedah bayaran.</p>
                  )}
                </div>
              )}
            </div>
          </SectionCard>

          <SectionCard icon={MessageSquareText} title="Butiran & pengesahan" hint="Kutipan maklumat & mesej akhir.">
            <div className="space-y-4">
              <label className="flex items-center justify-between gap-3">
                <span className="text-[13px] text-white/70">Tanya nama penuh pelanggan</span>
                <Toggle checked={data.ask_name} onChange={(v) => setData('ask_name', v)} />
              </label>

              <Field
                label="Mesej pengesahan (selepas order dicipta)"
                hint="Guna {order_number} {package} {price} {name} untuk auto-isi."
                error={errors.confirmation_message}
              >
                <Textarea rows={5} value={data.confirmation_message} onChange={(e) => setData('confirmation_message', e.target.value)}
                  placeholder={'Terima kasih {name}! 🎉\nNo. Pesanan: {order_number}\nPakej: {package} — {price}'} />
              </Field>

              {salesSources.length > 0 && (
                <Field label="Sumber jualan (attribution, pilihan)">
                  <Select value={data.sales_source_id || ''} onChange={(e) => setData('sales_source_id', e.target.value)}>
                    <option value="">— Tiada —</option>
                    {salesSources.map((s) => (
                      <option key={s.id} value={s.id}>{s.name}</option>
                    ))}
                  </Select>
                </Field>
              )}
            </div>
          </SectionCard>

          <div className="flex justify-end lg:hidden">
            <Button variant="primary" onClick={save} loading={form.processing}>Simpan Flow</Button>
          </div>
        </form>

        {/* Live preview */}
        <div className="lg:sticky lg:top-6 lg:self-start">
          <Card className="overflow-hidden">
            <div className="flex items-center justify-between gap-2 border-b border-white/8 bg-white/[0.03] px-4 py-3">
              <div className="flex items-center gap-2">
                <span className="grid h-8 w-8 place-items-center rounded-full bg-gradient-to-br from-emerald-500 to-green-500 text-white text-[11px] font-bold">WA</span>
                <div>
                  <p className="text-[13px] font-bold text-white">Preview perbualan</p>
                  <p className="text-[11px] text-white/40">Contoh mesej bot</p>
                </div>
              </div>
              {bothPayments && (
                <div className="flex rounded-lg bg-white/8 p-0.5 text-[11px] font-semibold">
                  {['cod', 'transfer'].map((p) => (
                    <button key={p} type="button" onClick={() => setPreviewPath(p)}
                      className={cn('rounded-md px-2 py-1 transition-colors', effectivePath === p ? 'bg-emerald-500 text-white' : 'text-white/50 hover:text-white')}>
                      {p === 'cod' ? 'COD' : 'Transfer'}
                    </button>
                  ))}
                </div>
              )}
            </div>

            <div className="max-h-[70vh] space-y-2 overflow-y-auto bg-[#0A140F] p-3.5">
              {preview.map((m, i) => (
                <div key={i} className={cn('flex', m.from === 'cust' ? 'justify-end' : 'justify-start')}>
                  <div className={cn(
                    'max-w-[85%] rounded-2xl px-3 py-2 text-[12.5px] leading-relaxed shadow-sm',
                    m.from === 'cust' ? 'rounded-br-sm bg-emerald-600 text-white' : 'rounded-bl-sm bg-white/10 text-white/90'
                  )}>
                    <WaText text={m.text} />
                  </div>
                </div>
              ))}
              {!data.packages.length && (
                <p className="py-6 text-center text-[12px] text-white/40">Tambah pakej untuk lihat preview penuh.</p>
              )}
            </div>
          </Card>
        </div>
      </div>
    </CekbotLayout>
  );
}
