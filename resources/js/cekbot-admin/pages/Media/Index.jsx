import { useEffect, useRef, useState } from 'react';
import axios from 'axios';
import toast from 'react-hot-toast';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Plus, Images, Pencil, Trash2, Copy, Sparkles, Video, Image as ImageIcon, Search, AlertTriangle } from 'lucide-react';
import CekbotLayout from '@/cekbot-admin/layouts/CekbotLayout';
import { Card, Button, Badge, Field, Input, Textarea, Select, EmptyState, Modal } from '@/cekbot-admin/components/Ui';
import { cn } from '@/cekbot-admin/lib/utils';

/** "Testimoni Puan Aminah!" → "testimoni-puan-aminah" (matches the server key rule). */
function slugify(text) {
  return String(text || '')
    .toLowerCase()
    .normalize('NFKD')
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '')
    .slice(0, 60);
}

function formatSize(bytes) {
  if (!bytes) return '';
  return bytes >= 1024 * 1024 ? `${(bytes / 1024 / 1024).toFixed(1)} MB` : `${Math.round(bytes / 1024)} KB`;
}

function LibraryPicker({ selectedId, onSelect }) {
  const [query, setQuery] = useState('');
  const [type, setType] = useState('');
  const [items, setItems] = useState(null);
  const timer = useRef(null);

  function load(q, t) {
    clearTimeout(timer.current);
    timer.current = setTimeout(() => {
      axios.get(route('cekbot.media.library'), { params: { q: q || undefined, type: t || undefined } })
        .then(({ data }) => setItems(data.items ?? []))
        .catch(() => { setItems([]); toast.error('Gagal ambil Media Library.'); });
    }, 300);
  }

  useEffect(() => { load('', ''); }, []);

  return (
    <div>
      <div className="flex gap-2">
        <div className="relative flex-1">
          <Search className="pointer-events-none absolute left-3 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-white/35" />
          <Input value={query} onChange={(e) => { setQuery(e.target.value); load(e.target.value, type); }} placeholder="Cari tajuk / tag…" className="pl-8" />
        </div>
        <Select value={type} onChange={(e) => { setType(e.target.value); load(query, e.target.value); }} className="w-32">
          <option value="">Semua</option>
          <option value="image">Gambar</option>
          <option value="video">Video</option>
        </Select>
      </div>
      <p className="mt-1.5 text-[11px] text-white/40">Hanya tunjuk media yang WhatsApp boleh hantar (JPG/PNG ≤5MB, MP4 ≤16MB).</p>
      <div className="mt-2 grid max-h-72 grid-cols-3 gap-2 overflow-y-auto sm:grid-cols-4">
        {items === null && <p className="col-span-full py-6 text-center text-[12px] text-white/40">Memuatkan…</p>}
        {items?.length === 0 && <p className="col-span-full py-6 text-center text-[12px] text-white/40">Tiada media sesuai. Muat naik baru di tab sebelah.</p>}
        {items?.map((item) => (
          <button
            key={item.id}
            type="button"
            onClick={() => onSelect(item)}
            className={cn(
              'group relative overflow-hidden rounded-lg bg-black/30 text-left ring-2 transition',
              selectedId === item.id ? 'ring-emerald-400' : 'ring-transparent hover:ring-white/25',
            )}
          >
            {item.type === 'video'
              ? <video src={item.url} preload="metadata" muted className="aspect-square w-full object-cover" />
              : <img src={item.url} alt="" loading="lazy" className="aspect-square w-full object-cover" />}
            {item.type === 'video' && <Video className="absolute right-1 top-1 h-3.5 w-3.5 text-white drop-shadow" />}
            {item.key && <span className="absolute left-1 top-1 rounded bg-black/70 px-1 font-mono text-[9.5px] text-emerald-200">{item.key}</span>}
            <p className="truncate px-1.5 py-1 text-[10.5px] text-white/70">{item.title}</p>
          </button>
        ))}
      </div>
    </div>
  );
}

function AddModal({ open, onClose, limits }) {
  const form = useForm({ media_id: null, file: null, key: '', title: '', description: '' });
  const [tab, setTab] = useState('library');
  const [picked, setPicked] = useState(null);
  const [keyTouched, setKeyTouched] = useState(false);

  function close() {
    form.reset();
    form.clearErrors();
    setPicked(null);
    setKeyTouched(false);
    setTab('library');
    onClose();
  }

  function suggest(title) {
    return (d) => ({ ...d, title, key: keyTouched ? d.key : slugify(title) });
  }

  function pick(item) {
    setPicked(item);
    form.setData((d) => ({ ...suggest(d.title || item.title)(d), media_id: item.id, file: null }));
  }

  function submit(e) {
    e.preventDefault();
    form.transform((d) => (tab === 'library' ? { ...d, file: null } : { ...d, media_id: null }));
    form.post(route('cekbot.media.store'), { forceFormData: true, preserveScroll: true, onSuccess: close });
  }

  return (
    <Modal
      open={open}
      onClose={close}
      size="lg"
      title="Tambah media untuk AI"
      hint="Pilih dari Media Library sedia ada, atau muat naik baru (fail akan masuk ke Media Library juga)."
      footer={
        <>
          <Button variant="ghost" onClick={close}>Batal</Button>
          <Button variant="primary" onClick={submit} loading={form.processing}>Simpan</Button>
        </>
      }
    >
      <div className="flex flex-col gap-3.5">
        <div className="flex gap-1 rounded-xl bg-white/[0.04] p-1">
          {[['library', 'Pilih dari Media Library'], ['upload', 'Muat naik baru']].map(([value, label]) => (
            <button
              key={value}
              type="button"
              onClick={() => setTab(value)}
              className={cn('flex-1 rounded-lg px-3 py-1.5 text-[12.5px] font-semibold transition', tab === value ? 'bg-white/10 text-white' : 'text-white/50 hover:text-white/80')}
            >
              {label}
            </button>
          ))}
        </div>

        {tab === 'library' ? (
          <Field error={form.errors.media_id || form.errors.file}>
            <LibraryPicker selectedId={picked?.id} onSelect={pick} />
          </Field>
        ) : (
          <Field label="Fail" hint={`Gambar JPG/PNG (maks ${limits.imageMb}MB) atau video MP4 (maks ${limits.videoMb}MB).`} error={form.errors.file}>
            <input
              type="file"
              accept="image/jpeg,image/png,video/mp4,video/3gpp"
              onChange={(e) => form.setData('file', e.target.files?.[0] ?? null)}
              className="block w-full text-[12.5px] text-white/70 file:mr-3 file:rounded-lg file:border-0 file:bg-white/10 file:px-3 file:py-2 file:text-[12.5px] file:font-semibold file:text-white/80 hover:file:bg-white/15"
            />
            {form.progress && <p className="mt-1.5 text-[11.5px] text-white/50">Memuat naik… {form.progress.percentage}%</p>}
          </Field>
        )}

        <Field label="Tajuk (pilihan)" error={form.errors.title}>
          <Input value={form.data.title} onChange={(e) => form.setData(suggest(e.target.value))} placeholder="Cth: Testimoni Puan Aminah" />
        </Field>
        <Field label="Key" hint="Nama pendek untuk disebut dalam arahan AI, cth: hantar testimoni-1" error={form.errors.key}>
          <Input
            value={form.data.key}
            onChange={(e) => { setKeyTouched(true); form.setData('key', slugify(e.target.value)); }}
            placeholder="testimoni-1"
            className="font-mono"
          />
        </Field>
        <Field label="Bila nak guna (pilihan)" hint="Membantu AI pilih media yang sesuai." error={form.errors.description}>
          <Textarea rows={2} value={form.data.description} onChange={(e) => form.setData('description', e.target.value)} placeholder="Cth: Hantar bila pelanggan ragu-ragu buku ni berkesan." />
        </Field>
      </div>
    </Modal>
  );
}

function EditModal({ media, onClose }) {
  const form = useForm({ key: media?.key ?? '', title: media?.title ?? '', description: media?.description ?? '' });

  function submit(e) {
    e.preventDefault();
    form.put(route('cekbot.media.update', media.id), { preserveScroll: true, onSuccess: onClose });
  }

  return (
    <Modal
      open={Boolean(media)}
      onClose={onClose}
      title="Sunting media"
      hint="Kalau tukar key, kemas kini juga arahan AI yang sebut key lama."
      footer={
        <>
          <Button variant="ghost" onClick={onClose}>Batal</Button>
          <Button variant="primary" onClick={submit} loading={form.processing}>Simpan</Button>
        </>
      }
    >
      <div className="flex flex-col gap-3.5">
        <Field label="Tajuk (pilihan)" error={form.errors.title}>
          <Input value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} />
        </Field>
        <Field label="Key" error={form.errors.key}>
          <Input value={form.data.key} onChange={(e) => form.setData('key', slugify(e.target.value))} className="font-mono" />
        </Field>
        <Field label="Bila nak guna (pilihan)" error={form.errors.description}>
          <Textarea rows={2} value={form.data.description} onChange={(e) => form.setData('description', e.target.value)} />
        </Field>
      </div>
    </Modal>
  );
}

function MediaCard({ media, onEdit }) {
  function copyKey() {
    navigator.clipboard?.writeText(`hantar ${media.key}`).then(
      () => toast.success(`Disalin: "hantar ${media.key}"`),
      () => toast.error('Gagal salin.'),
    );
  }

  function remove() {
    if (!window.confirm(`Buang "${media.key}" dari Cekbot? Fail kekal dalam Media Library, tapi arahan AI yang sebut key ni takkan dapat hantar media lagi.`)) return;
    router.delete(route('cekbot.media.destroy', media.id), { preserveScroll: true });
  }

  const video = media.type === 'video';

  return (
    <Card className="flex flex-col overflow-hidden">
      <div className="aspect-video bg-black/30">
        {video
          ? <video src={media.url} controls preload="metadata" className="h-full w-full object-contain" />
          : <img src={media.url} alt={media.title || media.key} loading="lazy" className="h-full w-full object-contain" />}
      </div>
      <div className="flex flex-1 flex-col gap-1.5 p-3.5">
        <div className="flex items-center gap-1.5">
          <code className="truncate rounded-md bg-emerald-500/15 px-1.5 py-0.5 font-mono text-[12px] font-semibold text-emerald-200">{media.key}</code>
          <Badge color="slate">
            {video ? <><Video className="h-2.5 w-2.5" /> Video</> : <><ImageIcon className="h-2.5 w-2.5" /> Gambar</>}
          </Badge>
          <span className="ml-auto text-[11px] text-white/35">{formatSize(media.size)}</span>
        </div>
        {media.title && <p className="truncate text-[13px] font-semibold text-white/85">{media.title}</p>}
        {!media.sendable && (
          <p className="flex items-center gap-1 text-[11px] text-amber-300/90"><AlertTriangle className="h-3 w-3" /> Fail ni tak boleh dihantar di WhatsApp (format/saiz). AI akan abaikan.</p>
        )}
        {media.description && <p className="line-clamp-2 text-[12px] text-white/50">{media.description}</p>}
        <div className="mt-auto flex items-center gap-2 pt-2">
          <Button size="sm" variant="secondary" onClick={copyKey} className="flex-1" title="Salin arahan untuk ditampal dalam prompt AI">
            <Copy className="h-3.5 w-3.5" /> Salin arahan
          </Button>
          <Button size="sm" variant="secondary" onClick={() => onEdit(media)} aria-label="Sunting"><Pencil className="h-3.5 w-3.5" /></Button>
          <Button size="sm" variant="danger" onClick={remove} aria-label="Padam"><Trash2 className="h-3.5 w-3.5" /></Button>
        </div>
      </div>
    </Card>
  );
}

export default function Index() {
  const { props } = usePage();
  const media = props.media ?? [];
  const limits = props.limits ?? { imageMb: 5, videoMb: 16 };
  const [uploadOpen, setUploadOpen] = useState(false);
  const [editing, setEditing] = useState(null);

  return (
    <CekbotLayout
      title="Media"
      subtitle="Gambar & video (cth testimoni) yang AI boleh hantar dalam perbualan"
      actions={<Button variant="primary" onClick={() => setUploadOpen(true)}><Plus className="h-4 w-4" /> Tambah media</Button>}
    >
      <Head title="Media" />

      <div className="mb-5 flex items-start gap-2.5 rounded-2xl border border-violet-400/20 bg-violet-500/[0.06] p-3.5">
        <Sparkles className="mt-0.5 h-4 w-4 shrink-0 text-violet-300" />
        <p className="text-[12.5px] leading-relaxed text-white/60">
          Pilih media dari <span className="font-semibold text-white/80">Media Library</span> dan beri <span className="font-semibold text-white/80">key</span> pendek, kemudian sebut key tu dalam
          {' '}<span className="font-semibold text-white/80">Arahan tambahan untuk AI</span> di Flow. Contoh:
          {' '}<span className="font-mono text-emerald-200">"Lepas terangkan pakej, hantar testimoni-1"</span>. AI akan hantar media tu pada masa yang sesuai.
        </p>
      </div>

      {media.length === 0 ? (
        <EmptyState
          icon={Images}
          title="Belum ada media"
          hint="Pilih gambar atau video testimoni dari Media Library untuk digunakan oleh AI."
          action={<Button variant="primary" onClick={() => setUploadOpen(true)}><Plus className="h-4 w-4" /> Tambah media</Button>}
        />
      ) : (
        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
          {media.map((m) => <MediaCard key={m.id} media={m} onEdit={setEditing} />)}
        </div>
      )}

      <AddModal open={uploadOpen} onClose={() => setUploadOpen(false)} limits={limits} />
      {editing && <EditModal key={editing.id} media={editing} onClose={() => setEditing(null)} />}
    </CekbotLayout>
  );
}
