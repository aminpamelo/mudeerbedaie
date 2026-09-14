import { useState } from 'react';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Plus, Workflow, Pencil, Trash2, ShoppingBag, Sparkles } from 'lucide-react';
import CekbotLayout from '@/cekbot-admin/layouts/CekbotLayout';
import { Card, Button, Badge, Field, Input, Toggle, EmptyState, Modal } from '@/cekbot-admin/components/Ui';
import { cn } from '@/cekbot-admin/lib/utils';

function CreateFlowModal({ open, sessionId, onClose }) {
  const form = useForm({ cekbot_session_id: sessionId, name: '' });

  function submit(e) {
    e.preventDefault();
    form.transform((d) => ({ ...d, cekbot_session_id: sessionId }));
    form.post(route('cekbot.flows.store'), { onSuccess: () => { form.reset(); onClose(); } });
  }

  return (
    <Modal
      open={open}
      onClose={onClose}
      title="Buat Flow baru"
      hint="Satu funnel jualan berpandu untuk nombor ini."
      footer={
        <>
          <Button variant="ghost" onClick={onClose}>Batal</Button>
          <Button variant="primary" onClick={submit} loading={form.processing}>Buat & sunting</Button>
        </>
      }
    >
      <form onSubmit={submit}>
        <Field label="Nama flow" hint="Untuk rujukan dalaman sahaja — pelanggan tak nampak." error={form.errors.name}>
          <Input
            autoFocus
            value={form.data.name}
            onChange={(e) => form.setData('name', e.target.value)}
            placeholder="Cth: Funnel Pakej Kurma"
          />
        </Field>
      </form>
    </Modal>
  );
}

function FlowCard({ flow }) {
  function toggle() {
    router.put(route('cekbot.flows.toggle', flow.id), { is_active: !flow.is_active }, { preserveScroll: true });
  }

  function remove() {
    if (!window.confirm(`Padam flow "${flow.name}"?`)) return;
    router.delete(route('cekbot.flows.destroy', flow.id), { preserveScroll: true });
  }

  return (
    <Card className="flex flex-col p-4">
      <div className="flex items-start justify-between gap-2">
        <div className="min-w-0">
          <div className="flex flex-wrap items-center gap-1.5">
            <h3 className="truncate text-[14px] font-bold text-white">{flow.name}</h3>
            <Badge color={flow.is_active ? 'emerald' : 'slate'}>{flow.is_active ? 'Aktif' : 'Draf'}</Badge>
          </div>
          <div className="mt-2 flex flex-wrap gap-1">
            {(flow.trigger_keywords || []).length ? (
              flow.trigger_keywords.map((k, i) => (
                <span key={i} className="rounded-md bg-white/8 px-1.5 py-0.5 text-[11px] text-white/60">{k}</span>
              ))
            ) : (
              <span className="text-[11.5px] text-amber-300/80">⚠️ Belum ada keyword trigger</span>
            )}
          </div>
        </div>
        <Toggle checked={flow.is_active} onChange={toggle} />
      </div>

      <div className="mt-3 flex items-center gap-1.5 text-[12px] text-white/45">
        <ShoppingBag className="h-3.5 w-3.5" />
        {flow.packages_count} pakej ditawarkan
      </div>

      <div className="mt-4 flex items-center gap-2 border-t border-white/8 pt-3">
        <Button size="sm" variant="secondary" href={route('cekbot.flows.show', flow.id)} className="flex-1">
          <Pencil className="h-3.5 w-3.5" /> Sunting
        </Button>
        <Button size="sm" variant="danger" onClick={remove} aria-label="Padam">
          <Trash2 className="h-3.5 w-3.5" />
        </Button>
      </div>
    </Card>
  );
}

export default function Index() {
  const { props } = usePage();
  const sessions = props.sessions ?? [];

  const [selectedId, setSelectedId] = useState(sessions[0]?.id ?? null);
  const selected = sessions.find((s) => s.id === selectedId) ?? sessions[0] ?? null;
  const [createOpen, setCreateOpen] = useState(false);

  if (!sessions.length) {
    return (
      <CekbotLayout title="Flows" subtitle="Funnel jualan automatik — dari mesej pertama hingga order">
        <Head title="Flows" />
        <EmptyState
          icon={Workflow}
          title="Belum ada nombor"
          hint="Tambah nombor WhatsApp dahulu sebelum bina flow jualan."
          action={<Button variant="primary" href="/admin/cekbot"><Plus className="h-4 w-4" /> Tambah nombor</Button>}
        />
      </CekbotLayout>
    );
  }

  const flows = selected?.flows ?? [];

  return (
    <CekbotLayout
      title="Flows"
      subtitle="Funnel jualan automatik — dari mesej pertama hingga order"
      actions={<Button variant="primary" onClick={() => setCreateOpen(true)}><Plus className="h-4 w-4" /> Buat Flow</Button>}
    >
      <Head title="Flows" />

      <div className="mb-5 flex items-start gap-2.5 rounded-2xl border border-violet-400/20 bg-violet-500/[0.06] p-3.5">
        <Sparkles className="mt-0.5 h-4 w-4 shrink-0 text-violet-300" />
        <p className="text-[12.5px] leading-relaxed text-white/60">
          Flow ialah perbualan jualan berpandu: bila pelanggan hantar <span className="font-semibold text-white/80">keyword trigger</span>,
          bot akan tunjuk pakej → tanya cara bayar (Transfer/COD) → kumpul butiran → <span className="font-semibold text-emerald-300">auto-cipta order</span> dalam sistem.
        </p>
      </div>

      {sessions.length > 1 && (
        <div className="mb-5 flex flex-wrap gap-2">
          {sessions.map((s) => (
            <button
              key={s.id}
              type="button"
              onClick={() => setSelectedId(s.id)}
              className={cn(
                'rounded-xl px-3.5 py-2 text-[13px] font-semibold transition-colors',
                s.id === selected?.id ? 'bg-emerald-500/15 text-emerald-300 ring-1 ring-inset ring-emerald-400/20' : 'bg-white/5 text-white/60 hover:bg-white/10'
              )}
            >
              {s.label}
            </button>
          ))}
        </div>
      )}

      {flows.length === 0 ? (
        <EmptyState
          icon={Workflow}
          title="Belum ada flow"
          hint={`Bina funnel jualan pertama untuk ${selected.label}.`}
          action={<Button variant="primary" onClick={() => setCreateOpen(true)}><Plus className="h-4 w-4" /> Buat Flow</Button>}
        />
      ) : (
        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
          {flows.map((flow) => (
            <FlowCard key={flow.id} flow={flow} />
          ))}
        </div>
      )}

      <CreateFlowModal open={createOpen} sessionId={selected?.id} onClose={() => setCreateOpen(false)} />
    </CekbotLayout>
  );
}
