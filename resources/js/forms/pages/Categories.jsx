import { useState } from 'react';
import { router } from '@inertiajs/react';
import { Plus, Pencil, Trash2, X, Tag } from 'lucide-react';
import FormsLayout from '../layouts/FormsLayout';

const PRESET_COLORS = ['#4F46E5', '#0EA5E9', '#10B981', '#F59E0B', '#EF4444', '#8B5CF6', '#EC4899', '#64748B'];

function CategoryModal({ category, onClose }) {
  const editing = !!category;
  const [name, setName] = useState(category?.name || '');
  const [color, setColor] = useState(category?.color || PRESET_COLORS[0]);
  const [description, setDescription] = useState(category?.description || '');
  const [isActive, setIsActive] = useState(category ? category.is_active : true);
  const [processing, setProcessing] = useState(false);

  const submit = () => {
    setProcessing(true);
    const payload = { name, color, description, is_active: isActive };
    const opts = { preserveScroll: true, onFinish: () => setProcessing(false), onSuccess: onClose };
    if (editing) {
      router.put(`/forms/categories/${category.id}`, payload, opts);
    } else {
      router.post('/forms/categories', payload, opts);
    }
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
      <div className="absolute inset-0 bg-black/50" onClick={onClose} />
      <div className="relative z-10 w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl">
        <div className="mb-4 flex items-center justify-between">
          <h3 className="text-lg font-semibold text-ink">{editing ? 'Edit Kategori' : 'Kategori Baru'}</h3>
          <button onClick={onClose} className="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100">
            <X className="h-5 w-5" />
          </button>
        </div>

        <label className="mb-1 block text-xs font-medium text-slate-600">Nama</label>
        <input
          className="mb-3 w-full rounded-lg border border-line px-3.5 py-2.5 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20"
          value={name}
          onChange={(e) => setName(e.target.value)}
          placeholder="cth: Pendaftaran, Maklum balas"
        />

        <label className="mb-1 block text-xs font-medium text-slate-600">Warna</label>
        <div className="mb-3 flex flex-wrap gap-2">
          {PRESET_COLORS.map((c) => (
            <button
              key={c}
              onClick={() => setColor(c)}
              className={`h-7 w-7 rounded-full transition ${color === c ? 'ring-2 ring-offset-2 ring-slate-400' : ''}`}
              style={{ backgroundColor: c }}
            />
          ))}
        </div>

        <label className="mb-1 block text-xs font-medium text-slate-600">Penerangan (pilihan)</label>
        <input
          className="mb-3 w-full rounded-lg border border-line px-3.5 py-2.5 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20"
          value={description}
          onChange={(e) => setDescription(e.target.value)}
        />

        <label className="mb-4 flex items-center gap-2 text-sm text-slate-700">
          <input type="checkbox" className="h-4 w-4 rounded accent-brand" checked={isActive} onChange={(e) => setIsActive(e.target.checked)} />
          Aktif
        </label>

        <div className="flex justify-end gap-2">
          <button onClick={onClose} className="rounded-lg px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100">
            Batal
          </button>
          <button
            onClick={submit}
            disabled={processing || !name.trim()}
            className="rounded-lg bg-brand px-4 py-2 text-sm font-semibold text-white transition hover:bg-brand-ink disabled:opacity-60"
          >
            {processing ? 'Menyimpan…' : 'Simpan'}
          </button>
        </div>
      </div>
    </div>
  );
}

export default function Categories({ categories = [] }) {
  const [modal, setModal] = useState(null); // {category} or {} for new, null closed

  const remove = (cat) => {
    if (confirm(`Buang kategori "${cat.name}"? Borang berkaitan tidak akan dibuang.`)) {
      router.delete(`/forms/categories/${cat.id}`, { preserveScroll: true });
    }
  };

  const actions = (
    <button
      onClick={() => setModal({})}
      className="inline-flex items-center gap-2 rounded-lg bg-brand px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-ink"
    >
      <Plus className="h-4 w-4" /> Kategori Baru
    </button>
  );

  return (
    <FormsLayout title="Kategori Borang" subtitle="Klasifikasikan borang untuk mengenal pasti kegunaan setiap satu." actions={actions}>
      {categories.length === 0 ? (
        <div className="flex flex-col items-center justify-center rounded-2xl border border-dashed border-line bg-white py-16 text-center">
          <Tag className="mb-3 h-10 w-10 text-slate-300" />
          <p className="text-sm font-medium text-ink">Belum ada kategori</p>
          <p className="mt-1 text-sm text-muted">Tambah kategori untuk mengelompokkan borang.</p>
        </div>
      ) : (
        <div className="overflow-hidden rounded-2xl border border-line bg-white shadow-sm">
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b border-line bg-slate-50 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                <th className="px-4 py-3">Kategori</th>
                <th className="px-4 py-3">Penerangan</th>
                <th className="px-4 py-3 text-center">Borang</th>
                <th className="px-4 py-3 text-center">Status</th>
                <th className="px-4 py-3 text-right">Tindakan</th>
              </tr>
            </thead>
            <tbody>
              {categories.map((cat) => (
                <tr key={cat.id} className="border-b border-line last:border-0 hover:bg-slate-50">
                  <td className="px-4 py-3">
                    <span className="inline-flex items-center gap-2 font-medium text-ink">
                      <span className="h-3 w-3 rounded-full" style={{ backgroundColor: cat.color || '#64748B' }} />
                      {cat.name}
                    </span>
                  </td>
                  <td className="px-4 py-3 text-slate-500">{cat.description || '—'}</td>
                  <td className="px-4 py-3 text-center text-slate-600">{cat.forms_count}</td>
                  <td className="px-4 py-3 text-center">
                    <span className={`inline-flex rounded-full px-2 py-0.5 text-xs font-medium ${cat.is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500'}`}>
                      {cat.is_active ? 'Aktif' : 'Tidak aktif'}
                    </span>
                  </td>
                  <td className="px-4 py-3">
                    <div className="flex items-center justify-end gap-1">
                      <button onClick={() => setModal({ category: cat })} className="rounded-lg p-1.5 text-slate-500 transition hover:bg-slate-100">
                        <Pencil className="h-4 w-4" />
                      </button>
                      <button onClick={() => remove(cat)} className="rounded-lg p-1.5 text-rose-400 transition hover:bg-rose-50">
                        <Trash2 className="h-4 w-4" />
                      </button>
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      {modal && <CategoryModal category={modal.category} onClose={() => setModal(null)} />}
    </FormsLayout>
  );
}
