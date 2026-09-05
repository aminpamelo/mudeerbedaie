import { useEffect } from 'react';
import { useForm } from '@inertiajs/react';
import { Modal, Field, Input, Textarea, Button } from '@/cekbot-admin/components/Ui';

export default function AddNumberModal({ open, editing, onClose }) {
  const isEdit = Boolean(editing);
  const form = useForm({ label: '', notes: '' });

  useEffect(() => {
    if (open) {
      form.setDefaults({ label: editing?.label ?? '', notes: editing?.notes ?? '' });
      form.setData({ label: editing?.label ?? '', notes: editing?.notes ?? '' });
      form.clearErrors();
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [open, editing?.id]);

  function submit(e) {
    e.preventDefault();
    const opts = { preserveScroll: true, onSuccess: onClose };
    if (isEdit) {
      form.put(route('cekbot.sessions.update', editing.id), opts);
    } else {
      form.post(route('cekbot.sessions.store'), opts);
    }
  }

  return (
    <Modal
      open={open}
      onClose={onClose}
      size="md"
      title={isEdit ? 'Edit nombor' : 'Tambah nombor WhatsApp'}
      hint={isEdit ? 'Kemas kini label & nota. Nombor sedia ada tidak terjejas.' : 'Beri label. Selepas ditambah, scan QR untuk pautkan WhatsApp.'}
      footer={
        <>
          <Button variant="ghost" onClick={onClose}>Batal</Button>
          <Button type="submit" form="cekbot-session-form" variant="primary" loading={form.processing}>
            {isEdit ? 'Simpan' : 'Tambah nombor'}
          </Button>
        </>
      }
    >
      <form id="cekbot-session-form" onSubmit={submit} className="space-y-4">
        <Field label="Label" hint="Nama untuk kenal nombor ini, cth: CS Utama, Team Sales." error={form.errors.label}>
          <Input
            value={form.data.label}
            onChange={(e) => form.setData('label', e.target.value)}
            placeholder="Cth: CS Utama"
            autoFocus
          />
        </Field>

        <Field label="Nota (pilihan)" error={form.errors.notes}>
          <Textarea
            rows={3}
            value={form.data.notes}
            onChange={(e) => form.setData('notes', e.target.value)}
            placeholder="Nota ringkas untuk rujukan pasukan (pilihan)"
          />
        </Field>
      </form>
    </Modal>
  );
}
