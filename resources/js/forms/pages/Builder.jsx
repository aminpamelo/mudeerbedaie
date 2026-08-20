import { useState } from 'react';
import { Link, router } from '@inertiajs/react';
import {
  Save,
  Trash2,
  ChevronUp,
  ChevronDown,
  Plus,
  X,
  Eye,
  GripVertical,
  ImagePlus,
  Loader2,
} from 'lucide-react';
import FormsLayout from '../layouts/FormsLayout';
import FieldRenderer from '../components/FieldRenderer';
import { FIELD_CATALOG, FIELD_GROUPS, blankField, fieldHasOptions, fieldMeta, isLayoutField } from '../lib/fields';
import { uploadFormImage } from '../lib/upload';

const inputClass =
  'w-full rounded-lg border border-line bg-white px-3.5 py-2.5 text-sm text-ink outline-none transition focus:border-brand focus:ring-2 focus:ring-brand/20';

export default function Builder({ form, categories = [] }) {
  const isNew = !form;

  const [title, setTitle] = useState(form?.title || '');
  const [description, setDescription] = useState(form?.description || '');
  const [categoryId, setCategoryId] = useState(form?.form_category_id || '');
  const [status, setStatus] = useState(form?.status || 'draft');
  const [confirmation, setConfirmation] = useState(
    form?.settings?.confirmation_message || 'Terima kasih atas maklum balas anda.',
  );
  const [allowMultiple, setAllowMultiple] = useState(form?.settings?.allow_multiple !== false);
  const [logoPath, setLogoPath] = useState(form?.settings?.logo_path || null);
  const [logoUrl, setLogoUrl] = useState(form?.logo_url || null);
  const [logoUploading, setLogoUploading] = useState(false);
  const [fields, setFields] = useState(
    form?.fields?.length ? form.fields : [blankField('short_text')],
  );
  const [processing, setProcessing] = useState(false);
  const [errors, setErrors] = useState({});

  const uploadLogo = async (file) => {
    if (!file) return;
    setLogoUploading(true);
    try {
      const { path, url } = await uploadFormImage(file);
      setLogoPath(path);
      setLogoUrl(url);
    } catch {
      alert('Gagal memuat naik logo. Cuba gambar lain (maks 4MB).');
    } finally {
      setLogoUploading(false);
    }
  };

  const addField = (type) => setFields((f) => [...f, blankField(type)]);

  const updateField = (id, patch) =>
    setFields((f) => f.map((fld) => (fld.id === id ? { ...fld, ...patch } : fld)));

  const removeField = (id) => setFields((f) => f.filter((fld) => fld.id !== id));

  const move = (index, dir) => {
    const target = index + dir;
    if (target < 0 || target >= fields.length) return;
    setFields((f) => {
      const next = [...f];
      [next[index], next[target]] = [next[target], next[index]];
      return next;
    });
  };

  const save = () => {
    setProcessing(true);
    setErrors({});
    const payload = {
      title,
      description,
      form_category_id: categoryId || null,
      status,
      fields,
      settings: {
        confirmation_message: confirmation,
        allow_multiple: allowMultiple,
        logo_path: logoPath,
      },
    };

    const opts = {
      preserveScroll: true,
      onError: (e) => setErrors(e),
      onFinish: () => setProcessing(false),
    };

    if (isNew) {
      router.post('/forms', payload, opts);
    } else {
      router.put(`/forms/${form.id}`, payload, opts);
    }
  };

  const actions = (
    <div className="flex items-center gap-2">
      {!isNew && (
        <a
          href={form.public_url}
          target="_blank"
          rel="noreferrer"
          className="inline-flex items-center gap-1.5 rounded-lg bg-slate-100 px-3 py-2.5 text-sm font-medium text-slate-700 transition hover:bg-slate-200"
        >
          <Eye className="h-4 w-4" /> Pratonton
        </a>
      )}
      <button
        onClick={save}
        disabled={processing}
        className="inline-flex items-center gap-2 rounded-lg bg-brand px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-ink disabled:opacity-60"
      >
        <Save className="h-4 w-4" />
        {processing ? 'Menyimpan…' : 'Simpan'}
      </button>
    </div>
  );

  return (
    <FormsLayout
      title={isNew ? 'Cipta Borang' : 'Edit Borang'}
      subtitle={
        <Link href="/forms" className="text-brand hover:underline">
          ← Kembali ke Borang Saya
        </Link>
      }
      actions={actions}
    >
      {errors.title && (
        <div className="mb-4 rounded-lg bg-rose-50 px-4 py-3 text-sm text-rose-700">{errors.title}</div>
      )}

      <div className="grid gap-6 lg:grid-cols-[1fr_260px]">
        {/* Builder column */}
        <div className="space-y-5">
          {/* Form meta */}
          <div className="rounded-2xl border-t-4 border-brand border-x border-b border-line bg-white p-6 shadow-sm">
            <input
              className="w-full border-0 border-b border-transparent bg-transparent pb-2 text-2xl font-bold text-ink outline-none focus:border-brand"
              placeholder="Tajuk borang"
              value={title}
              onChange={(e) => setTitle(e.target.value)}
            />
            <textarea
              className="mt-3 w-full resize-none border-0 bg-transparent text-sm text-muted outline-none"
              placeholder="Penerangan borang (pilihan)"
              rows={2}
              value={description}
              onChange={(e) => setDescription(e.target.value)}
            />
          </div>

          {/* Fields */}
          {fields.map((field, index) => (
            <FieldCard
              key={field.id}
              field={field}
              index={index}
              total={fields.length}
              onUpdate={updateField}
              onRemove={removeField}
              onMove={move}
            />
          ))}

          {fields.length === 0 && (
            <div className="rounded-2xl border border-dashed border-line bg-white py-12 text-center text-sm text-muted">
              Tiada soalan lagi. Tambah dari panel di sebelah kanan.
            </div>
          )}
        </div>

        {/* Right panel: palette + settings */}
        <div className="space-y-5">
          <div className="sticky top-6 space-y-5">
            <div className="rounded-2xl border border-line bg-white p-4 shadow-sm">
              <h4 className="mb-3 text-sm font-semibold text-ink">Tambah Soalan</h4>
              {FIELD_GROUPS.map((group) => (
                <div key={group} className="mb-3 last:mb-0">
                  <p className="mb-1.5 text-[11px] font-semibold uppercase tracking-wider text-slate-400">{group}</p>
                  <div className="grid grid-cols-2 gap-1.5">
                    {FIELD_CATALOG.filter((f) => f.group === group).map((f) => {
                      const Icon = f.icon;
                      return (
                        <button
                          key={f.type}
                          onClick={() => addField(f.type)}
                          className="flex items-center gap-1.5 rounded-lg border border-line px-2.5 py-2 text-left text-xs font-medium text-slate-700 transition hover:border-brand hover:bg-brand-soft"
                        >
                          <Icon className="h-3.5 w-3.5 shrink-0 text-brand" />
                          <span className="truncate">{f.label}</span>
                        </button>
                      );
                    })}
                  </div>
                </div>
              ))}
            </div>

            <div className="rounded-2xl border border-line bg-white p-4 shadow-sm">
              <h4 className="mb-3 text-sm font-semibold text-ink">Tetapan</h4>

              <label className="mb-1 block text-xs font-medium text-slate-600">Kategori</label>
              <select className={inputClass + ' mb-3'} value={categoryId} onChange={(e) => setCategoryId(e.target.value)}>
                <option value="">— Tiada —</option>
                {categories.map((c) => (
                  <option key={c.id} value={c.id}>
                    {c.name}
                  </option>
                ))}
              </select>

              <label className="mb-1 block text-xs font-medium text-slate-600">Status</label>
              <select className={inputClass + ' mb-3'} value={status} onChange={(e) => setStatus(e.target.value)}>
                <option value="draft">Draf</option>
                <option value="published">Diterbitkan</option>
                <option value="closed">Ditutup</option>
              </select>

              <label className="mb-1 block text-xs font-medium text-slate-600">Mesej pengesahan</label>
              <textarea
                className={inputClass + ' mb-3'}
                rows={2}
                value={confirmation}
                onChange={(e) => setConfirmation(e.target.value)}
              />

              <label className="flex items-center gap-2 text-sm text-slate-700">
                <input
                  type="checkbox"
                  className="h-4 w-4 rounded accent-brand"
                  checked={allowMultiple}
                  onChange={(e) => setAllowMultiple(e.target.checked)}
                />
                Benarkan jawapan berganda
              </label>
            </div>

            {/* Logo / banner */}
            <div className="rounded-2xl border border-line bg-white p-4 card-soft">
              <h4 className="mb-1 text-sm font-semibold text-ink">Logo Borang</h4>
              <p className="mb-3 text-[11.5px] text-muted">Dipaparkan di atas borang awam.</p>

              {logoUrl ? (
                <div className="space-y-2">
                  <div className="flex items-center justify-center rounded-xl border border-line bg-surface p-3">
                    <img src={logoUrl} alt="Logo" className="max-h-16 w-auto" />
                  </div>
                  <div className="flex items-center gap-2">
                    <label className="flex-1 cursor-pointer rounded-lg border border-line px-3 py-1.5 text-center text-xs font-medium text-slate-700 transition hover:bg-slate-50">
                      Tukar
                      <input type="file" accept="image/*" className="hidden" onChange={(e) => uploadLogo(e.target.files?.[0])} />
                    </label>
                    <button
                      onClick={() => { setLogoPath(null); setLogoUrl(null); }}
                      className="rounded-lg px-3 py-1.5 text-xs font-medium text-rose-500 transition hover:bg-rose-50"
                    >
                      Buang
                    </button>
                  </div>
                </div>
              ) : (
                <label className="flex cursor-pointer flex-col items-center gap-1.5 rounded-xl border border-dashed border-line px-3 py-5 text-center transition hover:border-brand hover:bg-brand-soft">
                  {logoUploading ? (
                    <Loader2 className="h-5 w-5 animate-spin text-brand" />
                  ) : (
                    <ImagePlus className="h-5 w-5 text-brand" />
                  )}
                  <span className="text-xs font-medium text-slate-600">
                    {logoUploading ? 'Memuat naik…' : 'Muat naik logo'}
                  </span>
                  <input type="file" accept="image/*" className="hidden" disabled={logoUploading} onChange={(e) => uploadLogo(e.target.files?.[0])} />
                </label>
              )}
            </div>
          </div>
        </div>
      </div>
    </FormsLayout>
  );
}

function FieldCard({ field, index, total, onUpdate, onRemove, onMove }) {
  const meta = fieldMeta(field.type);
  const Icon = meta.icon;
  const layout = isLayoutField(field.type);
  const isImage = field.type === 'image';
  const [imgUploading, setImgUploading] = useState(false);

  const uploadBlockImage = async (file) => {
    if (!file) return;
    setImgUploading(true);
    try {
      const { path, url } = await uploadFormImage(file);
      onUpdate(field.id, { settings: { ...field.settings, path, url } });
    } catch {
      alert('Gagal memuat naik gambar. Cuba gambar lain (maks 4MB).');
    } finally {
      setImgUploading(false);
    }
  };

  const setOption = (i, val) => {
    const options = [...(field.options || [])];
    options[i] = val;
    onUpdate(field.id, { options });
  };
  const addOption = () => onUpdate(field.id, { options: [...(field.options || []), `Pilihan ${(field.options?.length || 0) + 1}`] });
  const removeOption = (i) => onUpdate(field.id, { options: field.options.filter((_, idx) => idx !== i) });

  return (
    <div className="group rounded-2xl border border-line bg-white p-5 shadow-sm transition hover:border-slate-300">
      <div className="mb-3 flex items-center gap-2">
        <GripVertical className="h-4 w-4 text-slate-300" />
        <span className="inline-flex items-center gap-1.5 rounded-md bg-brand-soft px-2 py-1 text-xs font-medium text-brand-ink">
          <Icon className="h-3.5 w-3.5" />
          {meta.label}
        </span>
        <div className="ml-auto flex items-center gap-0.5">
          <button
            onClick={() => onMove(index, -1)}
            disabled={index === 0}
            className="rounded p-1.5 text-slate-400 transition hover:bg-slate-100 disabled:opacity-30"
          >
            <ChevronUp className="h-4 w-4" />
          </button>
          <button
            onClick={() => onMove(index, 1)}
            disabled={index === total - 1}
            className="rounded p-1.5 text-slate-400 transition hover:bg-slate-100 disabled:opacity-30"
          >
            <ChevronDown className="h-4 w-4" />
          </button>
          <button onClick={() => onRemove(field.id)} className="rounded p-1.5 text-rose-400 transition hover:bg-rose-50">
            <Trash2 className="h-4 w-4" />
          </button>
        </div>
      </div>

      {isImage && (
        <div className="mb-3">
          {field.settings?.url ? (
            <div className="space-y-2">
              <div className="flex items-center justify-center rounded-xl border border-line bg-surface p-3">
                <img src={field.settings.url} alt="" className="max-h-40 w-auto rounded-lg" />
              </div>
              <div className="flex flex-wrap items-center gap-2">
                <label className="cursor-pointer rounded-lg border border-line px-3 py-1.5 text-xs font-medium text-slate-700 transition hover:bg-slate-50">
                  Tukar gambar
                  <input type="file" accept="image/*" className="hidden" onChange={(e) => uploadBlockImage(e.target.files?.[0])} />
                </label>
                <select
                  className="rounded-lg border border-line px-2 py-1.5 text-xs"
                  value={field.settings?.width || 'medium'}
                  onChange={(e) => onUpdate(field.id, { settings: { ...field.settings, width: e.target.value } })}
                >
                  <option value="small">Kecil</option>
                  <option value="medium">Sederhana</option>
                  <option value="large">Besar</option>
                  <option value="full">Penuh</option>
                </select>
                <select
                  className="rounded-lg border border-line px-2 py-1.5 text-xs"
                  value={field.settings?.align || 'center'}
                  onChange={(e) => onUpdate(field.id, { settings: { ...field.settings, align: e.target.value } })}
                >
                  <option value="left">Kiri</option>
                  <option value="center">Tengah</option>
                  <option value="right">Kanan</option>
                </select>
              </div>
            </div>
          ) : (
            <label className="flex cursor-pointer flex-col items-center gap-1.5 rounded-xl border border-dashed border-line px-3 py-8 text-center transition hover:border-brand hover:bg-brand-soft">
              {imgUploading ? <Loader2 className="h-6 w-6 animate-spin text-brand" /> : <ImagePlus className="h-6 w-6 text-brand" />}
              <span className="text-sm font-medium text-slate-600">{imgUploading ? 'Memuat naik…' : 'Muat naik gambar'}</span>
              <span className="text-[11px] text-muted">JPG, PNG, GIF, WEBP · maks 4MB</span>
              <input type="file" accept="image/*" className="hidden" disabled={imgUploading} onChange={(e) => uploadBlockImage(e.target.files?.[0])} />
            </label>
          )}
        </div>
      )}

      <input
        className={inputClass}
        placeholder={isImage ? 'Kapsyen (pilihan)' : layout ? 'Teks' : 'Soalan'}
        value={field.label}
        onChange={(e) => onUpdate(field.id, { label: e.target.value })}
      />

      {!layout && (
        <input
          className={inputClass + ' mt-2 text-xs'}
          placeholder="Teks bantuan (pilihan)"
          value={field.help || ''}
          onChange={(e) => onUpdate(field.id, { help: e.target.value })}
        />
      )}

      {fieldHasOptions(field.type) && (
        <div className="mt-3 space-y-2">
          {(field.options || []).map((opt, i) => (
            <div key={i} className="flex items-center gap-2">
              <span className="text-xs text-slate-400">{i + 1}.</span>
              <input className={inputClass + ' py-1.5'} value={opt} onChange={(e) => setOption(i, e.target.value)} />
              <button onClick={() => removeOption(i)} className="text-slate-400 hover:text-rose-500">
                <X className="h-4 w-4" />
              </button>
            </div>
          ))}
          <button onClick={addOption} className="inline-flex items-center gap-1.5 text-xs font-medium text-brand hover:underline">
            <Plus className="h-3.5 w-3.5" /> Tambah pilihan
          </button>
        </div>
      )}

      {field.type === 'rating' && (
        <div className="mt-3 flex items-center gap-2 text-sm">
          <span className="text-slate-600">Bilangan bintang:</span>
          <select
            className="rounded-lg border border-line px-2 py-1 text-sm"
            value={field.settings?.max || 5}
            onChange={(e) => onUpdate(field.id, { settings: { ...field.settings, max: Number(e.target.value) } })}
          >
            {[3, 4, 5, 6, 7, 8, 9, 10].map((n) => (
              <option key={n} value={n}>
                {n}
              </option>
            ))}
          </select>
        </div>
      )}

      {!layout && (
        <label className="mt-4 flex items-center gap-2 border-t border-line pt-3 text-sm text-slate-600">
          <input
            type="checkbox"
            className="h-4 w-4 rounded accent-brand"
            checked={field.required}
            onChange={(e) => onUpdate(field.id, { required: e.target.checked })}
          />
          Wajib diisi
        </label>
      )}
    </div>
  );
}
