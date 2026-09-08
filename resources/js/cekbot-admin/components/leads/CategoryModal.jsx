import { useEffect } from 'react';
import { useForm } from '@inertiajs/react';
import { Check } from 'lucide-react';
import { Modal, Field, Input, Button } from '@/cekbot-admin/components/Ui';
import { leadColor } from '@/cekbot-admin/lib/leadColors';

export default function CategoryModal({ open, editing, colorOptions, onClose }) {
  const isEdit = Boolean(editing);
  const form = useForm({ name: '', color: 'blue' });

  useEffect(() => {
    if (!open) return;
    form.setData({ name: editing?.name ?? '', color: editing?.color ?? 'blue' });
    form.clearErrors();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [open, editing?.id]);

  function submit(e) {
    e.preventDefault();
    const opts = { preserveScroll: true, onSuccess: onClose };
    if (isEdit) {
      form.put(route('cekbot.leads.categories.update', editing.id), opts);
    } else {
      form.post(route('cekbot.leads.categories.store'), opts);
    }
  }

  return (
    <Modal
      open={open} onClose={onClose} size="sm"
      title={isEdit ? 'Edit kategori' : 'Kategori baru'}
      hint="Kategori jadi lajur dalam papan Kanban leads."
      footer={
        <>
          <Button variant="ghost" onClick={onClose}>Batal</Button>
          <Button type="submit" form="cekbot-category-form" variant="primary" loading={form.processing}>Simpan</Button>
        </>
      }
    >
      <form id="cekbot-category-form" onSubmit={submit} className="space-y-4">
        <Field label="Nama kategori" error={form.errors.name}>
          <Input value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} placeholder="Cth: Berminat, Follow-up, Deal" autoFocus />
        </Field>

        <Field label="Warna" error={form.errors.color}>
          <div className="flex flex-wrap gap-2">
            {colorOptions.map((token) => {
              const c = leadColor(token);
              const selected = form.data.color === token;
              return (
                <button
                  key={token}
                  type="button"
                  onClick={() => form.setData('color', token)}
                  className={`grid h-8 w-8 place-items-center rounded-full ${c.dot} ring-2 transition ${selected ? 'ring-white/80' : 'ring-transparent hover:ring-white/30'}`}
                  aria-label={token}
                  aria-pressed={selected}
                >
                  {selected && <Check className="h-4 w-4 text-black/70" strokeWidth={3} />}
                </button>
              );
            })}
          </div>
        </Field>
      </form>
    </Modal>
  );
}
