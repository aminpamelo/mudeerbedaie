import { useState } from 'react';
import toast from 'react-hot-toast';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Plus, BookOpenCheck, Pencil, Trash2, Sparkles, Upload, ShieldCheck, Workflow } from 'lucide-react';
import CekbotLayout from '@/cekbot-admin/layouts/CekbotLayout';
import { Card, Button, Badge, Field, Input, Textarea, EmptyState, Modal, Toggle } from '@/cekbot-admin/components/Ui';
import { cn } from '@/cekbot-admin/lib/utils';

const TRANSCRIPT_MAX = 30000;

/** Tick the flows a reference applies to; none ticked = every flow. */
function FlowPicker({ flows, value, onChange }) {
  function toggle(id) {
    onChange(value.includes(id) ? value.filter((v) => v !== id) : [...value, id]);
  }

  return (
    <div className="flex flex-wrap gap-1.5">
      <button
        type="button"
        onClick={() => onChange([])}
        className={cn('rounded-lg px-2.5 py-1 text-[12px] font-semibold ring-1 ring-inset transition', value.length === 0 ? 'bg-emerald-500/20 text-emerald-200 ring-emerald-400/40' : 'text-white/55 ring-white/10 hover:text-white/80')}
      >
        Semua flow
      </button>
      {flows.map((flow) => (
        <button
          key={flow.id}
          type="button"
          onClick={() => toggle(flow.id)}
          className={cn('rounded-lg px-2.5 py-1 text-[12px] font-semibold ring-1 ring-inset transition', value.includes(flow.id) ? 'bg-emerald-500/20 text-emerald-200 ring-emerald-400/40' : 'text-white/55 ring-white/10 hover:text-white/80')}
        >
          {flow.name}
        </button>
      ))}
    </div>
  );
}

function ReferenceModal({ reference, flows, onClose }) {
  const editing = Boolean(reference?.id);
  const form = useForm({
    title: reference?.title ?? '',
    transcript: reference?.transcript ?? '',
    notes: reference?.notes ?? '',
    flow_ids: reference?.flow_ids ?? [],
    is_active: reference?.is_active ?? true,
  });

  function loadFile(e) {
    const file = e.target.files?.[0];
    e.target.value = '';
    if (!file) return;
    if (file.size > 2 * 1024 * 1024) { toast.error('Fail terlalu besar (maks 2MB).'); return; }
    const reader = new FileReader();
    reader.onload = () => {
      const text = String(reader.result || '');
      form.setData((d) => ({ ...d, transcript: text.slice(0, TRANSCRIPT_MAX), title: d.title || file.name.replace(/\.txt$/i, '') }));
      if (text.length > TRANSCRIPT_MAX) toast('Fail dipotong ke 30,000 aksara pertama.', { icon: '✂️' });
    };
    reader.onerror = () => toast.error('Gagal baca fail.');
    reader.readAsText(file);
  }

  function submit(e) {
    e.preventDefault();
    const options = { preserveScroll: true, onSuccess: onClose };
    if (editing) {
      form.put(route('cekbot.references.update', reference.id), options);
    } else {
      form.post(route('cekbot.references.store'), options);
    }
  }

  return (
    <Modal
      open
      onClose={onClose}
      size="lg"
      title={editing ? 'Sunting rujukan closing' : 'Tambah rujukan closing'}
      hint="Tampal chat closing yang berjaya. AI akan belajar gaya (nada, cara jawab bantahan, cara ajak beli) — bukan salin harga atau fakta."
      footer={
        <>
          <Button variant="ghost" onClick={onClose}>Batal</Button>
          <Button variant="primary" onClick={submit} loading={form.processing}>Simpan</Button>
        </>
      }
    >
      <div className="flex flex-col gap-3.5">
        <Field label="Tajuk" error={form.errors.title}>
          <Input value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} placeholder="Cth: Closing Qadha Solat — customer ragu harga" />
        </Field>

        <Field label="Guna untuk flow" hint="Pilih flow tertentu, atau biar 'Semua flow'. Rujukan flow tu diutamakan." error={form.errors.flow_ids || form.errors['flow_ids.0']}>
          <FlowPicker flows={flows} value={form.data.flow_ids} onChange={(ids) => form.setData('flow_ids', ids)} />
        </Field>

        <Field
          label="Perbualan closing"
          hint={`${form.data.transcript.length.toLocaleString()} / ${TRANSCRIPT_MAX.toLocaleString()} aksara. Boleh tampal terus atau muat naik fail Export Chat WhatsApp (.txt).`}
          error={form.errors.transcript}
        >
          <Textarea
            rows={12}
            value={form.data.transcript}
            onChange={(e) => form.setData('transcript', e.target.value.slice(0, TRANSCRIPT_MAX))}
            placeholder={'Customer: Assalamualaikum, nak tanya pasal kelas qadha solat\nSales: Waalaikumsalam kak 😊 ...'}
            className="font-mono text-[12px]"
          />
          <label className="mt-2 inline-flex cursor-pointer items-center gap-1.5 rounded-lg bg-white/[0.06] px-2.5 py-1.5 text-[12px] font-semibold text-white/70 ring-1 ring-inset ring-white/10 hover:bg-white/10">
            <Upload className="h-3.5 w-3.5" /> Muat naik .txt
            <input type="file" accept=".txt,text/plain" className="hidden" onChange={loadFile} />
          </label>
        </Field>

        <Field label="Kenapa closing ni berjaya (pilihan)" hint="Bantu AI faham teknik yang patut ditiru." error={form.errors.notes}>
          <Textarea rows={2} value={form.data.notes} onChange={(e) => form.setData('notes', e.target.value)} placeholder="Cth: Sales tak terus bagi harga, tanya masalah customer dulu, lepas tu kongsi testimoni." />
        </Field>

        <div className="flex items-center justify-between rounded-xl bg-white/[0.04] px-3 py-2.5">
          <div>
            <p className="text-[13px] font-semibold text-white/85">Aktif</p>
            <p className="text-[11.5px] text-white/45">Bila off, AI tak guna rujukan ni.</p>
          </div>
          <Toggle checked={form.data.is_active} onChange={(v) => form.setData('is_active', v)} />
        </div>
      </div>
    </Modal>
  );
}

function ReferenceCard({ reference, onEdit }) {
  function toggle() {
    router.put(route('cekbot.references.toggle', reference.id), {}, { preserveScroll: true });
  }

  function remove() {
    if (!window.confirm(`Padam rujukan "${reference.title}"?`)) return;
    router.delete(route('cekbot.references.destroy', reference.id), { preserveScroll: true });
  }

  return (
    <Card className={cn('flex flex-col gap-2 p-4', !reference.is_active && 'opacity-60')}>
      <div className="flex items-start gap-2">
        <p className="min-w-0 flex-1 text-[14px] font-semibold text-white/90">{reference.title}</p>
        <Toggle checked={reference.is_active} onChange={toggle} />
      </div>
      <div className="flex flex-wrap gap-1">
        {reference.flow_names.length === 0
          ? <Badge color="emerald">Semua flow</Badge>
          : reference.flow_names.map((name) => <Badge key={name} color="slate"><Workflow className="h-2.5 w-2.5" /> {name}</Badge>)}
      </div>
      <pre className="line-clamp-5 whitespace-pre-wrap rounded-lg bg-black/25 p-2.5 font-mono text-[11.5px] leading-relaxed text-white/55">{reference.transcript}</pre>
      {reference.notes && <p className="line-clamp-2 text-[12px] text-white/55"><span className="font-semibold text-white/70">Kenapa berjaya:</span> {reference.notes}</p>}
      <div className="mt-auto flex items-center gap-2 pt-1">
        <span className="text-[11px] text-white/35">{reference.chars.toLocaleString()} aksara{reference.creator ? ` · ${reference.creator}` : ''}</span>
        <div className="ml-auto flex gap-2">
          <Button size="sm" variant="secondary" onClick={() => onEdit(reference)} aria-label="Sunting"><Pencil className="h-3.5 w-3.5" /></Button>
          <Button size="sm" variant="danger" onClick={remove} aria-label="Padam"><Trash2 className="h-3.5 w-3.5" /></Button>
        </div>
      </div>
    </Card>
  );
}

export default function Index() {
  const { props } = usePage();
  const references = props.references ?? [];
  const flows = props.flows ?? [];
  const limits = props.limits ?? { perReply: 3, charsEach: 3000 };
  const [modal, setModal] = useState(null);

  return (
    <CekbotLayout
      title="Rujukan Closing"
      subtitle="Chat closing sebenar team sales untuk AI belajar gaya jualan"
      actions={<Button variant="primary" onClick={() => setModal({})}><Plus className="h-4 w-4" /> Tambah rujukan</Button>}
    >
      <Head title="Rujukan Closing" />

      <div className="mb-5 grid gap-3 lg:grid-cols-2">
        <div className="flex items-start gap-2.5 rounded-2xl border border-violet-400/20 bg-violet-500/[0.06] p-3.5">
          <Sparkles className="mt-0.5 h-4 w-4 shrink-0 text-violet-300" />
          <p className="text-[12.5px] leading-relaxed text-white/60">
            Setiap kali AI balas customer, ia baca sehingga <span className="font-semibold text-white/80">{limits.perReply} rujukan</span> — yang di-tag pada flow tu didahulukan, kemudian rujukan "Semua flow".
            AI tiru <span className="font-semibold text-white/80">gaya</span> sahaja; harga & pakej tetap ikut flow.
          </p>
        </div>
        <div className="flex items-start gap-2.5 rounded-2xl border border-emerald-400/20 bg-emerald-500/[0.06] p-3.5">
          <ShieldCheck className="mt-0.5 h-4 w-4 shrink-0 text-emerald-300" />
          <p className="text-[12.5px] leading-relaxed text-white/60">
            Nombor telefon & emel dalam chat disorok secara automatik sebelum dihantar ke AI. Elakkan tampal alamat penuh customer.
          </p>
        </div>
      </div>

      {references.length === 0 ? (
        <EmptyState
          icon={BookOpenCheck}
          title="Belum ada rujukan closing"
          hint="Tambah chat closing terbaik team sales supaya AI Cekbot pandai closing macam mereka."
          action={<Button variant="primary" onClick={() => setModal({})}><Plus className="h-4 w-4" /> Tambah rujukan</Button>}
        />
      ) : (
        <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
          {references.map((ref) => <ReferenceCard key={ref.id} reference={ref} onEdit={setModal} />)}
        </div>
      )}

      {modal && <ReferenceModal key={modal.id ?? 'new'} reference={modal} flows={flows} onClose={() => setModal(null)} />}
    </CekbotLayout>
  );
}
