import { useEffect, useState } from 'react';
import { useForm } from '@inertiajs/react';
import axios from 'axios';
import { Users, Clock } from 'lucide-react';
import { Modal, Field, Input, Textarea, Select, Toggle, Button } from '@/cekbot-admin/components/Ui';

export default function BroadcastModal({ open, sessions, availableLabels, onClose }) {
  const form = useForm({
    cekbot_session_id: sessions[0]?.id ?? '',
    name: '',
    message: '',
    audience_type: 'all',
    audience_value: '',
    include_groups: false,
    schedule: false,
    scheduled_at: '',
  });

  const [count, setCount] = useState(null);

  useEffect(() => {
    if (open) {
      form.setData({
        cekbot_session_id: sessions[0]?.id ?? '', name: '', message: '',
        audience_type: 'all', audience_value: '', include_groups: false, schedule: false, scheduled_at: '',
      });
      form.clearErrors();
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [open]);

  // Live recipient count.
  useEffect(() => {
    if (!open || !form.data.cekbot_session_id) { setCount(null); return; }
    let cancelled = false;
    axios.get(route('cekbot.broadcast.count'), {
      params: {
        cekbot_session_id: form.data.cekbot_session_id,
        audience_type: form.data.audience_type,
        audience_value: form.data.audience_value,
        include_groups: form.data.include_groups ? 1 : 0,
      },
    }).then(({ data }) => { if (!cancelled) setCount(data.count); }).catch(() => { if (!cancelled) setCount(null); });
    return () => { cancelled = true; };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [open, form.data.cekbot_session_id, form.data.audience_type, form.data.audience_value, form.data.include_groups]);

  function submit(e) {
    e.preventDefault();
    form.transform((data) => {
      const { schedule, ...rest } = data;
      return { ...rest, scheduled_at: schedule ? rest.scheduled_at : null };
    });
    form.post(route('cekbot.broadcast.store'), { preserveScroll: true, onSuccess: onClose });
  }

  return (
    <Modal
      open={open} onClose={onClose} size="md"
      title="Broadcast baru"
      hint="Hantar mesej kepada pelanggan yang pernah mesej nombor ini."
      footer={
        <>
          <Button variant="ghost" onClick={onClose}>Batal</Button>
          <Button type="submit" form="cekbot-broadcast-form" variant="primary" loading={form.processing}>
            {form.data.schedule ? 'Jadualkan' : 'Hantar sekarang'}
          </Button>
        </>
      }
    >
      <form id="cekbot-broadcast-form" onSubmit={submit} className="space-y-4">
        <Field label="Nombor penghantar" error={form.errors.cekbot_session_id}>
          <Select value={form.data.cekbot_session_id} onChange={(e) => form.setData('cekbot_session_id', e.target.value)}>
            {sessions.map((s) => <option key={s.id} value={s.id}>{s.label}{s.phone_number ? ` (${s.phone_number})` : ''}</option>)}
          </Select>
        </Field>

        <Field label="Nama kempen" error={form.errors.name}>
          <Input value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} placeholder="Cth: Promosi Kurma Ramadan" />
        </Field>

        <Field label="Mesej" hint="Guna {name} untuk nama penerima. WhatsApp: *tebal*, _italik_." error={form.errors.message}>
          <Textarea rows={4} value={form.data.message} onChange={(e) => form.setData('message', e.target.value)} placeholder="Salam {name}! Kami ada promosi istimewa…" />
        </Field>

        <div className="grid grid-cols-2 gap-3">
          <Field label="Penerima" error={form.errors.audience_value}>
            <Select value={form.data.audience_type} onChange={(e) => form.setData('audience_type', e.target.value)}>
              <option value="all">Semua perbualan</option>
              <option value="label">Mengikut label</option>
            </Select>
          </Field>
          {form.data.audience_type === 'label' && (
            <Field label="Label">
              <Select value={form.data.audience_value} onChange={(e) => form.setData('audience_value', e.target.value)}>
                <option value="">Pilih label…</option>
                {availableLabels.map((l) => <option key={l.key} value={l.key}>{l.name}</option>)}
              </Select>
            </Field>
          )}
        </div>

        <label className="flex items-center justify-between gap-3">
          <span className="text-[13px] text-white/70">Termasuk group</span>
          <Toggle checked={form.data.include_groups} onChange={(v) => form.setData('include_groups', v)} />
        </label>

        <div className="flex items-center gap-2 rounded-lg bg-white/5 px-3 py-2 text-[12.5px] text-white/60">
          <Users className="h-4 w-4 text-emerald-400" strokeWidth={2.2} />
          {count === null ? 'Mengira penerima…' : <span><span className="font-semibold text-white">{count}</span> penerima akan menerima mesej ini</span>}
        </div>

        <div className="rounded-xl border border-white/8 bg-white/[0.03] p-3">
          <label className="flex items-center justify-between gap-3">
            <span className="flex items-center gap-1.5 text-[13px] font-semibold text-white/80"><Clock className="h-4 w-4 text-sky-300" /> Jadualkan (follow-up)</span>
            <Toggle checked={form.data.schedule} onChange={(v) => form.setData('schedule', v)} />
          </label>
          {form.data.schedule && (
            <Field className="mt-3" label="Hantar pada" error={form.errors.scheduled_at}>
              <Input type="datetime-local" value={form.data.scheduled_at} onChange={(e) => form.setData('scheduled_at', e.target.value)} />
            </Field>
          )}
        </div>
      </form>
    </Modal>
  );
}
