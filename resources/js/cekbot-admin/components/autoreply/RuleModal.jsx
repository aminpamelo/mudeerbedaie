import { useEffect } from 'react';
import { useForm } from '@inertiajs/react';
import { Modal, Field, Input, Textarea, Select, Button } from '@/cekbot-admin/components/Ui';

const MATCH_LABELS = {
  contains: 'Mengandungi',
  exact: 'Sama tepat',
  starts: 'Bermula dengan',
  regex: 'Regex (lanjutan)',
};

export default function RuleModal({ open, sessionId, editing, onClose }) {
  const isEdit = Boolean(editing);
  const form = useForm({
    name: '',
    match_type: 'contains',
    keywords: '',
    reply_body: '',
    is_active: true,
    priority: 100,
  });

  useEffect(() => {
    if (open) {
      form.setData({
        name: editing?.name ?? '',
        match_type: editing?.match_type ?? 'contains',
        keywords: (editing?.keywords ?? []).join(', '),
        reply_body: editing?.reply_body ?? '',
        is_active: editing?.is_active ?? true,
        priority: editing?.priority ?? 100,
      });
      form.clearErrors();
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [open, editing?.id]);

  function submit(e) {
    e.preventDefault();
    const keywords = form.data.keywords.split(',').map((k) => k.trim()).filter(Boolean);
    const opts = { preserveScroll: true, onSuccess: onClose };
    form.transform((data) => ({ ...data, keywords }));
    if (isEdit) {
      form.put(route('cekbot.auto-reply.rules.update', editing.id), opts);
    } else {
      form.post(route('cekbot.auto-reply.rules.store', sessionId), opts);
    }
  }

  return (
    <Modal
      open={open}
      onClose={onClose}
      size="md"
      title={isEdit ? 'Edit peraturan' : 'Peraturan auto-reply baru'}
      hint="Bila mesej masuk sepadan, bot balas secara automatik."
      footer={
        <>
          <Button variant="ghost" onClick={onClose}>Batal</Button>
          <Button type="submit" form="cekbot-rule-form" variant="primary" loading={form.processing}>Simpan</Button>
        </>
      }
    >
      <form id="cekbot-rule-form" onSubmit={submit} className="space-y-4">
        <Field label="Nama peraturan" error={form.errors.name}>
          <Input value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} placeholder="Cth: Soalan harga" autoFocus />
        </Field>

        <div className="grid grid-cols-2 gap-3">
          <Field label="Jenis padanan" error={form.errors.match_type}>
            <Select value={form.data.match_type} onChange={(e) => form.setData('match_type', e.target.value)}>
              {Object.entries(MATCH_LABELS).map(([v, l]) => <option key={v} value={v}>{l}</option>)}
            </Select>
          </Field>
          <Field label="Keutamaan" hint="Kecil = dahulu" error={form.errors.priority}>
            <Input type="number" min="0" value={form.data.priority} onChange={(e) => form.setData('priority', e.target.value)} />
          </Field>
        </div>

        <Field label="Kata kunci (pisah dengan koma)" hint="Cth: harga, price, berapa" error={form.errors.keywords}>
          <Input value={form.data.keywords} onChange={(e) => form.setData('keywords', e.target.value)} placeholder="harga, price, berapa" />
        </Field>

        <Field label="Balasan automatik" error={form.errors.reply_body}>
          <Textarea rows={4} value={form.data.reply_body} onChange={(e) => form.setData('reply_body', e.target.value)} placeholder="Mesej yang bot akan hantar…" />
        </Field>

        <label className="flex items-center gap-2 text-[13px] text-white/70">
          <input type="checkbox" checked={form.data.is_active} onChange={(e) => form.setData('is_active', e.target.checked)} className="h-4 w-4 rounded border-white/20 bg-white/10 text-emerald-500" />
          Aktif
        </label>
      </form>
    </Modal>
  );
}
