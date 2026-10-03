import { useMemo, useRef, useState } from 'react';
import axios from 'axios';
import toast from 'react-hot-toast';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { ArrowLeft, Plus, Trash2, X, Workflow, Banknote, Truck, MessageSquareText, Tag, Sparkles, Image as ImageIcon, Smartphone, ShieldCheck, Server, ChevronUp, ChevronDown, Type, Megaphone, Search } from 'lucide-react';
import CekbotLayout from '@/cekbot-admin/layouts/CekbotLayout';
import { Card, Button, Field, Input, Textarea, Select, Toggle } from '@/cekbot-admin/components/Ui';
import { buildPreview } from '@/cekbot-admin/lib/flowPreview';
import { cn, formatPhone } from '@/cekbot-admin/lib/utils';

/** Must match the `ai_instructions` max rule in FlowController. */
const AI_INSTRUCTIONS_MAX = 15000;

/**
 * Pick the Click-to-WhatsApp ads that start this flow. Searches the connected
 * Facebook ad accounts; an ad id can also be pasted for ads outside them.
 */
function AdTriggerPicker({ value, onChange, error }) {
  const [open, setOpen] = useState(false);
  const [query, setQuery] = useState('');
  const [results, setResults] = useState([]);
  const [connected, setConnected] = useState(true);
  const [loading, setLoading] = useState(false);
  const [manualId, setManualId] = useState('');
  const timer = useRef(null);

  function search(q) {
    setQuery(q);
    clearTimeout(timer.current);
    timer.current = setTimeout(() => {
      setLoading(true);
      axios.get(route('cekbot.flows.ads-search'), { params: { q } })
        .then(({ data }) => { setResults(data.ads ?? []); setConnected(data.connected); })
        .catch(() => toast.error('Gagal ambil senarai iklan.'))
        .finally(() => setLoading(false));
    }, 350);
  }

  function openPicker() {
    setOpen(true);
    if (results.length === 0) search('');
  }

  function add(ad) {
    if (value.some((a) => a.id === ad.id)) return;
    onChange([...value, { id: ad.id, name: ad.name ?? null }]);
  }

  function addManual() {
    const id = manualId.trim();
    if (!/^\d{5,30}$/.test(id)) { toast.error('ID iklan mesti nombor sahaja.'); return; }
    add({ id, name: null });
    setManualId('');
  }

  return (
    <Field
      label="Iklan pencetus (Click-to-WhatsApp)"
      hint="Pelanggan yang klik iklan ni terus masuk flow ni, walaupun ayat greeting dia lain. Iklan diutamakan berbanding keyword."
      error={error}
    >
      {value.length > 0 && (
        <div className="mb-2 flex flex-col gap-1.5">
          {value.map((ad) => (
            <div key={ad.id} className="flex items-center gap-2 rounded-lg bg-white/[0.06] px-2.5 py-1.5 ring-1 ring-inset ring-white/10">
              <Megaphone className="h-3.5 w-3.5 shrink-0 text-emerald-300" />
              <div className="min-w-0 flex-1">
                <p className="truncate text-[12.5px] font-medium text-white/85">{ad.name || 'Iklan (nama tak diketahui)'}</p>
                <p className="font-mono text-[10.5px] text-white/40">ID {ad.id}</p>
              </div>
              <button type="button" onClick={() => onChange(value.filter((a) => a.id !== ad.id))} className="text-white/40 hover:text-rose-300" aria-label="Buang iklan"><X className="h-3.5 w-3.5" /></button>
            </div>
          ))}
        </div>
      )}

      {!open ? (
        <Button variant="secondary" onClick={openPicker}><Megaphone className="h-4 w-4" /> Pilih iklan</Button>
      ) : (
        <div className="rounded-xl bg-white/[0.03] p-2.5 ring-1 ring-inset ring-white/10">
          <div className="relative">
            <Search className="pointer-events-none absolute left-3 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-white/35" />
            <Input autoFocus value={query} onChange={(e) => search(e.target.value)} placeholder="Cari nama iklan…" className="pl-8" />
          </div>
          <div className="mt-2 max-h-64 overflow-y-auto">
            {loading && <p className="px-1 py-2 text-[12px] text-white/40">Mencari…</p>}
            {!loading && !connected && <p className="px-1 py-2 text-[12px] text-amber-300/80">Belum ada akaun iklan Facebook disambung. Tampal ID iklan di bawah.</p>}
            {!loading && connected && results.length === 0 && <p className="px-1 py-2 text-[12px] text-white/40">Tiada iklan dijumpai.</p>}
            {!loading && results.map((ad) => {
              const picked = value.some((a) => a.id === ad.id);
              return (
                <button
                  key={ad.id}
                  type="button"
                  disabled={picked}
                  onClick={() => add(ad)}
                  className="flex w-full items-center gap-2.5 rounded-lg px-1.5 py-1.5 text-left hover:bg-white/[0.06] disabled:opacity-40"
                >
                  {ad.thumbnail
                    ? <img src={ad.thumbnail} alt="" className="h-9 w-9 shrink-0 rounded-md object-cover" />
                    : <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-md bg-white/8"><Megaphone className="h-4 w-4 text-white/40" /></div>}
                  <div className="min-w-0 flex-1">
                    <p className="truncate text-[12.5px] font-medium text-white/85">{ad.name}</p>
                    <p className="truncate text-[10.5px] text-white/40">{ad.account} · {ad.status === 'ACTIVE' ? 'Aktif' : 'Tidak aktif'}</p>
                  </div>
                  {picked ? <span className="text-[11px] text-emerald-300">Dipilih</span> : <Plus className="h-3.5 w-3.5 text-white/40" />}
                </button>
              );
            })}
          </div>
          <div className="mt-2 flex gap-2 border-t border-white/8 pt-2">
            <Input value={manualId} onChange={(e) => setManualId(e.target.value)} onKeyDown={(e) => { if (e.key === 'Enter') { e.preventDefault(); addManual(); } }} placeholder="Atau tampal ID iklan" />
            <Button variant="secondary" onClick={addManual}>Tambah</Button>
            <Button variant="secondary" onClick={() => setOpen(false)}>Tutup</Button>
          </div>
        </div>
      )}
    </Field>
  );
}

/** Render WhatsApp-style *bold* segments. */
function WaText({ text }) {
  const parts = String(text || '').split('*');
  return (
    <span className="whitespace-pre-wrap break-words">
      {parts.map((part, i) => (i % 2 === 1 ? <strong key={i}>{part}</strong> : <span key={i}>{part}</span>))}
    </span>
  );
}

/** Upload / preview / remove the transfer QR or bank poster (own endpoint). */
function BankImage({ flowId, imageUrl }) {
  const fileRef = useRef(null);
  const [uploading, setUploading] = useState(false);

  function onFile(e) {
    const file = e.target.files?.[0];
    if (!file) return;
    setUploading(true);
    router.post(route('cekbot.flows.bank-image.store', flowId), { bank_image: file }, {
      forceFormData: true,
      preserveScroll: true,
      onFinish: () => { setUploading(false); if (fileRef.current) fileRef.current.value = ''; },
    });
  }

  function remove() {
    if (!window.confirm('Buang gambar ni?')) return;
    router.delete(route('cekbot.flows.bank-image.destroy', flowId), { preserveScroll: true });
  }

  return (
    <Field label="Gambar QR / poster bank (pilihan)" hint="Dihantar bersama maklumat bank bila pelanggan pilih transfer.">
      {imageUrl ? (
        <div className="flex items-center gap-3">
          <img src={imageUrl} alt="QR bank" className="h-24 w-24 rounded-lg object-cover ring-1 ring-inset ring-white/10" />
          <div className="flex flex-col gap-2">
            <Button variant="secondary" size="sm" onClick={() => fileRef.current?.click()} loading={uploading}>Tukar</Button>
            <Button variant="danger" size="sm" onClick={remove}>Buang</Button>
          </div>
        </div>
      ) : (
        <Button variant="secondary" onClick={() => fileRef.current?.click()} loading={uploading}>
          <ImageIcon className="h-4 w-4" /> Muat naik QR / gambar
        </Button>
      )}
      <input ref={fileRef} type="file" accept="image/*" className="hidden" onChange={onFile} />
    </Field>
  );
}

/**
 * Scripted opening messages — text and image bubbles sent verbatim, in order,
 * as soon as the flow triggers (before the AI / menu takes over).
 */
function OpeningMessages({ flowId, messages, onChange, errors }) {
  const fileRef = useRef(null);
  const [uploadingIndex, setUploadingIndex] = useState(null);
  const targetIndex = useRef(null);

  function update(index, patch) {
    onChange(messages.map((m, i) => (i === index ? { ...m, ...patch } : m)));
  }
  function move(index, delta) {
    const next = [...messages];
    const [item] = next.splice(index, 1);
    next.splice(index + delta, 0, item);
    onChange(next);
  }
  function remove(index) {
    onChange(messages.filter((_, i) => i !== index));
  }
  function addText() {
    onChange([...messages, { type: 'text', text: '', path: '', caption: '', url: '' }]);
  }
  function pickImage(index) {
    targetIndex.current = index;
    fileRef.current?.click();
  }

  async function onFile(e) {
    const file = e.target.files?.[0];
    if (fileRef.current) fileRef.current.value = '';
    if (!file) return;

    const index = targetIndex.current;
    setUploadingIndex(index ?? messages.length);
    try {
      const body = new FormData();
      body.append('image', file);
      const { data: uploaded } = await axios.post(route('cekbot.flows.opening-image.store', flowId), body);
      if (index === null) {
        onChange([...messages, { type: 'image', text: '', path: uploaded.path, caption: '', url: uploaded.url }]);
      } else {
        update(index, { path: uploaded.path, url: uploaded.url });
      }
    } catch (err) {
      toast.error(err.response?.data?.errors?.image?.[0] ?? 'Gagal muat naik gambar.');
    } finally {
      setUploadingIndex(null);
    }
  }

  return (
    <Field
      label="Mesej pembuka (dihantar tepat, ikut susunan)"
      hint="Dihantar sebaik pelanggan trigger flow — sebelum AI / menu ambil alih. Dalam mod AI, AI mula balas pada mesej pelanggan yang seterusnya."
    >
      <div className="space-y-2.5">
        {messages.map((m, i) => (
          <div key={i} className="rounded-xl bg-white/[0.04] p-3 ring-1 ring-inset ring-white/8">
            <div className="mb-2 flex items-center justify-between">
              <span className="flex items-center gap-1.5 text-[11.5px] font-semibold text-white/50">
                {m.type === 'image' ? <ImageIcon className="h-3.5 w-3.5" /> : <Type className="h-3.5 w-3.5" />}
                Mesej {i + 1} · {m.type === 'image' ? 'Gambar' : 'Teks'}
              </span>
              <div className="flex items-center gap-0.5">
                <button type="button" disabled={i === 0} onClick={() => move(i, -1)} className="grid h-7 w-7 place-items-center rounded-lg text-white/40 hover:bg-white/8 hover:text-white disabled:opacity-25" aria-label="Naik"><ChevronUp className="h-4 w-4" /></button>
                <button type="button" disabled={i === messages.length - 1} onClick={() => move(i, 1)} className="grid h-7 w-7 place-items-center rounded-lg text-white/40 hover:bg-white/8 hover:text-white disabled:opacity-25" aria-label="Turun"><ChevronDown className="h-4 w-4" /></button>
                <button type="button" onClick={() => remove(i)} className="grid h-7 w-7 place-items-center rounded-lg text-white/40 hover:bg-rose-500/15 hover:text-rose-300" aria-label="Buang mesej"><Trash2 className="h-3.5 w-3.5" /></button>
              </div>
            </div>

            {m.type === 'image' ? (
              <div className="flex gap-3">
                <button type="button" onClick={() => pickImage(i)} className="relative shrink-0" title="Tukar gambar">
                  <img src={m.url} alt="" className="h-20 w-20 rounded-lg object-cover ring-1 ring-inset ring-white/10" />
                  {uploadingIndex === i && <span className="absolute inset-0 grid place-items-center rounded-lg bg-black/60 text-[11px] text-white">…</span>}
                </button>
                <div className="flex-1">
                  <Input value={m.caption ?? ''} onChange={(e) => update(i, { caption: e.target.value })} placeholder="Caption (pilihan)" />
                  <p className="mt-1 text-[11px] text-white/35">Klik gambar untuk tukar.</p>
                </div>
              </div>
            ) : (
              <Textarea rows={3} value={m.text ?? ''} onChange={(e) => update(i, { text: e.target.value })} placeholder="Cth: Assalamualaikum! 🌙 Terima kasih berminat dengan Buku Panduan Qadha Solat…" />
            )}
            {(errors[`opening_messages.${i}.text`] || errors[`opening_messages.${i}.path`]) && (
              <p className="mt-1 text-[11.5px] font-semibold text-rose-400">{errors[`opening_messages.${i}.text`] || errors[`opening_messages.${i}.path`]}</p>
            )}
          </div>
        ))}

        {messages.length < 10 && (
          <div className="flex flex-wrap gap-2">
            <Button variant="secondary" size="sm" onClick={addText}><Type className="h-3.5 w-3.5" /> Tambah teks</Button>
            <Button variant="secondary" size="sm" onClick={() => pickImage(null)} loading={uploadingIndex === messages.length}><ImageIcon className="h-3.5 w-3.5" /> Tambah gambar</Button>
          </div>
        )}
      </div>
      <input ref={fileRef} type="file" accept="image/*" className="hidden" onChange={onFile} />
    </Field>
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
  const catalogProducts = props.catalogProducts ?? [];
  const catalogPackages = props.catalogPackages ?? [];
  const cekbotProducts = props.cekbotProducts ?? [];
  const salesSources = props.salesSources ?? [];
  const aiAvailable = props.aiAvailable;

  const form = useForm({
    name: flow.name ?? '',
    is_active: flow.is_active ?? false,
    use_ai: flow.use_ai ?? true,
    ai_instructions: flow.ai_instructions ?? '',
    match_type: flow.match_type ?? 'contains',
    trigger_keywords: flow.trigger_keywords ?? [],
    trigger_ads: flow.trigger_ads ?? [],
    welcome_message: flow.welcome_message ?? '',
    opening_messages: (flow.opening_messages ?? []).map((m) => ({
      type: m.type, text: m.text ?? '', path: m.path ?? '', caption: m.caption ?? '', url: m.url ?? '',
    })),
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
      product_id: p.product_id ?? '',
      shop_package_id: p.shop_package_id ?? '',
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
    setData('packages', [...data.packages, { cekbot_product_id: '', product_id: '', shop_package_id: '', label: '', price: '', currency: 'RM' }]);
  }
  function updatePackage(index, patch) {
    setData('packages', data.packages.map((p, i) => (i === index ? { ...p, ...patch } : p)));
  }
  function removePackage(index) {
    setData('packages', data.packages.filter((_, i) => i !== index));
  }
  // The product select encodes which source is linked: "catalog:ID" (shop
  // product), "package:ID" (shop package) or "cekbot:ID" (Cekbot knowledge product).
  function packageSelectValue(pkg) {
    if (pkg.product_id) return `catalog:${pkg.product_id}`;
    if (pkg.shop_package_id) return `package:${pkg.shop_package_id}`;
    if (pkg.cekbot_product_id) return `cekbot:${pkg.cekbot_product_id}`;
    return '';
  }
  function onSelectProduct(index, value) {
    const row = data.packages[index];
    if (!value) {
      updatePackage(index, { product_id: '', shop_package_id: '', cekbot_product_id: '' });
      return;
    }
    const [type, id] = value.split(':');
    if (type === 'catalog' || type === 'package') {
      const source = type === 'catalog' ? catalogProducts : catalogPackages;
      const product = source.find((p) => String(p.id) === String(id));
      updatePackage(index, {
        product_id: type === 'catalog' ? id : '',
        shop_package_id: type === 'package' ? id : '',
        cekbot_product_id: '',
        label: row.label || product?.name || '',
        price: row.price === '' && product?.price != null ? product.price : row.price,
      });
    } else {
      const product = cekbotProducts.find((p) => String(p.id) === String(id));
      updatePackage(index, {
        cekbot_product_id: id,
        product_id: '',
        shop_package_id: '',
        label: row.label || product?.name || '',
        price: row.price === '' && product?.price != null ? product.price : row.price,
        currency: product?.currency || row.currency || 'RM',
      });
    }
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
      subtitle={`Funnel untuk ${flow.session_label ?? 'nombor ini'}${flow.session_phone ? ` · ${formatPhone(flow.session_phone)}` : ''}`}
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
          {/* Which WhatsApp number this funnel runs on. */}
          <div className="flex flex-wrap items-center gap-3 rounded-xl border border-white/8 bg-white/[0.03] p-3.5">
            <span className="grid h-10 w-10 shrink-0 place-items-center rounded-lg bg-emerald-500/12 text-emerald-300">
              <Smartphone className="h-5 w-5" strokeWidth={2} />
            </span>
            <div className="min-w-0 flex-1">
              <div className="flex flex-wrap items-center gap-2">
                <p className="text-[13px] font-semibold text-white">{flow.session_label ?? 'Nombor tidak diketahui'}</p>
                {flow.session_provider === 'cloud_api' ? (
                  <span className="inline-flex items-center gap-0.5 rounded-md bg-emerald-500/15 px-1.5 py-0.5 text-[10px] font-semibold text-emerald-300">
                    <ShieldCheck className="h-2.5 w-2.5" strokeWidth={2.6} /> WhatsApp Rasmi
                  </span>
                ) : (
                  <span className="inline-flex items-center gap-0.5 rounded-md bg-white/8 px-1.5 py-0.5 text-[10px] font-semibold text-white/50">
                    <Server className="h-2.5 w-2.5" strokeWidth={2.6} /> WAHA
                  </span>
                )}
                {flow.session_is_working ? (
                  <span className="inline-block rounded-md bg-emerald-500/15 px-1.5 py-0.5 text-[10px] font-semibold text-emerald-300">Bersambung</span>
                ) : (
                  <span className="inline-block rounded-md bg-amber-500/15 px-1.5 py-0.5 text-[10px] font-semibold text-amber-300">Belum bersambung</span>
                )}
              </div>
              <p className="mt-0.5 text-[12px] text-white/45">
                {flow.session_phone ? formatPhone(flow.session_phone) : 'Nombor belum dipautkan'} — funnel ini berjalan pada nombor WhatsApp ini.
              </p>
            </div>
          </div>

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

              <AdTriggerPicker
                value={data.trigger_ads}
                onChange={(ads) => setData('trigger_ads', ads)}
                error={errors.trigger_ads || Object.entries(errors).find(([k]) => k.startsWith('trigger_ads.'))?.[1]}
              />

              <Field label="Padanan keyword" className="w-full sm:w-56">
                <Select value={data.match_type} onChange={(e) => setData('match_type', e.target.value)}>
                  <option value="contains">Mengandungi</option>
                  <option value="starts">Bermula dengan</option>
                  <option value="exact">Sama tepat</option>
                </Select>
              </Field>
            </div>
          </SectionCard>

          <SectionCard icon={Sparkles} title="Mod jawapan" hint="Cara bot berbual dengan pelanggan.">
            <div className="space-y-4">
              <div className="flex items-start justify-between gap-3 rounded-xl border border-violet-400/25 bg-violet-500/[0.06] p-3.5">
                <div>
                  <p className="flex items-center gap-1.5 text-[13px] font-semibold text-white/85">
                    <Sparkles className="h-4 w-4 text-violet-300" /> Guna AI (disyorkan)
                  </p>
                  <p className="mt-1 text-[11.5px] leading-relaxed text-white/50">
                    AI faham ayat biasa (tak paksa pilih nombor), jawab soalan, kesan bila pelanggan berminat, kumpul & sahkan borang, baru cipta order. Bila off, bot guna menu bernombor tetap.
                  </p>
                </div>
                <Toggle checked={form.data.use_ai} onChange={(v) => setData('use_ai', v)} disabled={!aiAvailable} />
              </div>

              {!aiAvailable && (
                <p className="text-[11.5px] text-amber-300/80">⚠️ OpenAI belum dikonfigur — flow akan guna menu bernombor sehingga OPENAI_API_KEY ditetapkan.</p>
              )}

              {form.data.use_ai && aiAvailable && (
                <Field
                  label="Arahan tambahan untuk AI (pilihan)"
                  hint={`Cth: tekankan promosi, gaya bahasa, jangan janji diskaun, dsb. · ${(form.data.ai_instructions || '').length.toLocaleString()} / ${AI_INSTRUCTIONS_MAX.toLocaleString()} aksara`}
                  error={errors.ai_instructions}
                >
                  <Textarea rows={8} maxLength={AI_INSTRUCTIONS_MAX} value={form.data.ai_instructions} onChange={(e) => setData('ai_instructions', e.target.value)}
                    placeholder="Cth: Guna bahasa santai & mesra. Galakkan COD. Jangan janji apa-apa yang tiada dalam maklumat pakej." />
                </Field>
              )}
            </div>
          </SectionCard>

          <SectionCard icon={MessageSquareText} title="Mesej alu-aluan" hint={form.data.use_ai && aiAvailable ? 'Mesej pembuka tetap + panduan gaya untuk AI.' : 'Mesej pertama + arahan pilih pakej.'}>
            <div className="space-y-4">
              <OpeningMessages flowId={flow.id} messages={data.opening_messages} onChange={(v) => setData('opening_messages', v)} errors={errors} />
              <Field label={form.data.use_ai && aiAvailable ? 'Panduan alu-aluan untuk AI (pilihan)' : 'Mesej alu-aluan (pilihan)'} error={errors.welcome_message}>
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
                    <Field label="Link ke produk / package (pilihan)" className="sm:col-span-2"
                      hint={catalogProducts.length === 0 && catalogPackages.length === 0 && cekbotProducts.length === 0 ? 'Belum ada produk — tambah di Produk (kedai) dahulu, atau isi label + harga secara manual.' : 'Link ke produk / package kedai supaya order rujuk item sebenar & harga auto-isi.'}>
                      <Select value={packageSelectValue(pkg)} onChange={(e) => onSelectProduct(i, e.target.value)}>
                        <option value="">— Custom (tiada link produk) —</option>
                        {catalogProducts.length > 0 && (
                          <optgroup label="Produk kedai">
                            {catalogProducts.map((p) => (
                              <option key={`c${p.id}`} value={`catalog:${p.id}`}>{p.name}{p.price != null ? ` (RM${p.price})` : ''}</option>
                            ))}
                          </optgroup>
                        )}
                        {catalogPackages.length > 0 && (
                          <optgroup label="Package kedai">
                            {catalogPackages.map((p) => (
                              <option key={`p${p.id}`} value={`package:${p.id}`}>{p.name}{p.price != null ? ` (RM${p.price})` : ''}</option>
                            ))}
                          </optgroup>
                        )}
                        {cekbotProducts.length > 0 && (
                          <optgroup label="Cekbot Produk (info AI)">
                            {cekbotProducts.map((p) => (
                              <option key={`k${p.id}`} value={`cekbot:${p.id}`}>{p.name}{p.price != null ? ` (${p.currency}${p.price})` : ''}</option>
                            ))}
                          </optgroup>
                        )}
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
                        <BankImage flowId={flow.id} imageUrl={flow.bank_image_url} />
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
              {data.use_ai && aiAvailable && (
                <div className="mb-1 flex items-start gap-1.5 rounded-lg bg-violet-500/10 px-2.5 py-2 text-[11px] leading-relaxed text-violet-200/80">
                  <Sparkles className="mt-0.5 h-3 w-3 shrink-0" />
                  <span>Mod AI aktif — perbualan sebenar lebih dinamik & ikut pelanggan. Ini cuma contoh aliran.</span>
                </div>
              )}
              {preview.map((m, i) => (
                <div key={i} className={cn('flex', m.from === 'cust' ? 'justify-end' : 'justify-start')}>
                  <div className={cn(
                    'max-w-[85%] rounded-2xl px-3 py-2 text-[12.5px] leading-relaxed shadow-sm',
                    m.from === 'cust' ? 'rounded-br-sm bg-emerald-600 text-white' : 'rounded-bl-sm bg-white/10 text-white/90'
                  )}>
                    {m.image && <img src={m.image} alt="" className={cn('w-48 max-w-full rounded-xl object-cover', m.text && 'mb-1.5')} />}
                    {m.text && <WaText text={m.text} />}
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
