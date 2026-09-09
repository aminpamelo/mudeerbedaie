import { useEffect, useState } from 'react';
import { router } from '@inertiajs/react';
import { Plus, Pencil, Trash2, Check } from 'lucide-react';
import { Modal, Input, Button } from '@/cekbot-admin/components/Ui';
import { leadColor } from '@/cekbot-admin/lib/leadColors';
import { cn } from '@/cekbot-admin/lib/utils';

function ColorSwatches({ value, onChange, options }) {
  return (
    <div className="flex flex-wrap gap-1.5">
      {options.map((token) => {
        const c = leadColor(token);
        const selected = value === token;
        return (
          <button
            key={token}
            type="button"
            onClick={() => onChange(token)}
            className={cn('grid h-7 w-7 place-items-center rounded-full ring-2 transition', c.dot,
              selected ? 'ring-white/80' : 'ring-transparent hover:ring-white/30')}
            aria-label={token}
            aria-pressed={selected}
          >
            {selected && <Check className="h-3.5 w-3.5 text-black/70" strokeWidth={3} />}
          </button>
        );
      })}
    </div>
  );
}

/**
 * Generic manager for a colour-coded taxonomy (labels or lead categories):
 * lists items with inline edit + delete, plus an "add new" form. Every write
 * goes through Inertia and refreshes `items` from the server on success.
 */
export default function TaxonomyManagerModal({
  open, onClose, title, hint, items = [], colorOptions = [],
  routeNames, namePlaceholder = 'Nama…', deleteConfirm, emptyText = 'Belum ada.',
}) {
  const fallbackColor = colorOptions[0] ?? 'blue';
  const [newName, setNewName] = useState('');
  const [newColor, setNewColor] = useState(fallbackColor);
  const [adding, setAdding] = useState(false);
  const [addError, setAddError] = useState(null);

  const [editId, setEditId] = useState(null);
  const [editName, setEditName] = useState('');
  const [editColor, setEditColor] = useState(fallbackColor);
  const [savingEdit, setSavingEdit] = useState(false);
  const [editError, setEditError] = useState(null);

  useEffect(() => {
    if (!open) return;
    setNewName(''); setNewColor(fallbackColor); setAddError(null);
    setEditId(null); setEditError(null);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [open]);

  function submitNew(e) {
    e.preventDefault();
    if (!newName.trim()) return;
    setAdding(true); setAddError(null);
    router.post(route(routeNames.store), { name: newName.trim(), color: newColor }, {
      preserveScroll: true, preserveState: true,
      onSuccess: () => { setNewName(''); setNewColor(fallbackColor); },
      onError: (errors) => setAddError(errors.name || errors.color || 'Gagal menyimpan.'),
      onFinish: () => setAdding(false),
    });
  }

  function startEdit(item) {
    setEditId(item.id); setEditName(item.name); setEditColor(item.color); setEditError(null);
  }

  function saveEdit(id) {
    if (!editName.trim()) return;
    setSavingEdit(true); setEditError(null);
    router.put(route(routeNames.update, id), { name: editName.trim(), color: editColor }, {
      preserveScroll: true, preserveState: true,
      onSuccess: () => setEditId(null),
      onError: (errors) => setEditError(errors.name || errors.color || 'Gagal mengemas kini.'),
      onFinish: () => setSavingEdit(false),
    });
  }

  function remove(item) {
    if (!window.confirm(deleteConfirm ? deleteConfirm(item) : `Padam "${item.name}"?`)) return;
    router.delete(route(routeNames.destroy, item.id), { preserveScroll: true, preserveState: true });
  }

  return (
    <Modal
      open={open} onClose={onClose} size="sm" title={title} hint={hint}
      footer={<Button variant="ghost" onClick={onClose}>Tutup</Button>}
    >
      <div className="space-y-4">
        <div className="space-y-1.5">
          {items.length === 0 && <p className="py-2 text-center text-[12.5px] text-white/40">{emptyText}</p>}
          {items.map((item) => {
            const c = leadColor(item.color);
            const isEditing = editId === item.id;
            return (
              <div key={item.id} className="rounded-xl border border-white/8 bg-white/[0.03] p-2.5">
                {isEditing ? (
                  <div className="space-y-2.5">
                    <Input
                      value={editName}
                      onChange={(e) => setEditName(e.target.value)}
                      onKeyDown={(e) => { if (e.key === 'Enter') { e.preventDefault(); saveEdit(item.id); } }}
                      placeholder={namePlaceholder}
                      autoFocus
                    />
                    <ColorSwatches value={editColor} onChange={setEditColor} options={colorOptions} />
                    {editError && <p className="text-[11.5px] font-semibold text-rose-400" role="alert">{editError}</p>}
                    <div className="flex justify-end gap-2">
                      <Button size="sm" variant="ghost" onClick={() => setEditId(null)}>Batal</Button>
                      <Button size="sm" variant="primary" loading={savingEdit} onClick={() => saveEdit(item.id)}>Simpan</Button>
                    </div>
                  </div>
                ) : (
                  <div className="flex items-center gap-2.5">
                    <span className={cn('h-3 w-3 shrink-0 rounded-full', c.dot)} />
                    <span className="flex-1 truncate text-[13px] font-semibold text-white">{item.name}</span>
                    {typeof item.count === 'number' && (
                      <span className="rounded-md bg-white/8 px-1.5 py-0.5 text-[11px] font-semibold tabular-nums text-white/50">{item.count}</span>
                    )}
                    <button type="button" onClick={() => startEdit(item)} className="grid h-7 w-7 place-items-center rounded-lg text-white/40 hover:bg-white/8 hover:text-white" aria-label={`Edit ${item.name}`}>
                      <Pencil className="h-3.5 w-3.5" />
                    </button>
                    <button type="button" onClick={() => remove(item)} className="grid h-7 w-7 place-items-center rounded-lg text-rose-300/70 hover:bg-rose-500/10 hover:text-rose-300" aria-label={`Padam ${item.name}`}>
                      <Trash2 className="h-3.5 w-3.5" />
                    </button>
                  </div>
                )}
              </div>
            );
          })}
        </div>

        <form onSubmit={submitNew} className="space-y-2.5 rounded-xl border border-dashed border-white/12 p-3">
          <p className="text-[12px] font-semibold text-white/60">Tambah baru</p>
          <Input value={newName} onChange={(e) => setNewName(e.target.value)} placeholder={namePlaceholder} />
          <ColorSwatches value={newColor} onChange={setNewColor} options={colorOptions} />
          {addError && <p className="text-[11.5px] font-semibold text-rose-400" role="alert">{addError}</p>}
          <div className="flex justify-end">
            <Button type="submit" size="sm" variant="primary" loading={adding} disabled={!newName.trim()}>
              <Plus className="h-3.5 w-3.5" /> Tambah
            </Button>
          </div>
        </form>
      </div>
    </Modal>
  );
}
