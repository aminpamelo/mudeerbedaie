import { useState } from 'react';
import toast from 'react-hot-toast';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Plus, Images, Pencil, Trash2, Copy, Sparkles, Video, Image as ImageIcon } from 'lucide-react';
import CekbotLayout from '@/cekbot-admin/layouts/CekbotLayout';
import { Card, Button, Badge, Field, Input, Textarea, EmptyState, Modal } from '@/cekbot-admin/components/Ui';

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

function UploadModal({ open, onClose, limits }) {
  const form = useForm({ file: null, key: '', title: '', description: '' });
  const [keyTouched, setKeyTouched] = useState(false);

  function close() {
    form.reset();
    form.clearErrors();
    setKeyTouched(false);
    onClose();
  }

  function submit(e) {
    e.preventDefault();
    form.post(route('cekbot.media.store'), { forceFormData: true, preserveScroll: true, onSuccess: close });
  }

  return (
    <Modal
      open={open}
      onClose={close}
      title="Muat naik media"
      hint={`Gambar (maks ${limits.imageMb}MB) atau video MP4 (maks ${limits.videoMb}MB).`}
      footer={
        <>
          <Button variant="ghost" onClick={close}>Batal</Button>
          <Button variant="primary" onClick={submit} loading={form.processing}>Muat naik</Button>
        </>
      }
    >
      <div className="flex flex-col gap-3.5">
        <Field label="Fail" error={form.errors.file}>
          <input
            type="file"
            accept="image/jpeg,image/png,image/webp,video/mp4,video/3gpp"
            onChange={(e) => form.setData('file', e.target.files?.[0] ?? null)}
            className="block w-full text-[12.5px] text-white/70 file:mr-3 file:rounded-lg file:border-0 file:bg-white/10 file:px-3 file:py-2 file:text-[12.5px] file:font-semibold file:text-white/80 hover:file:bg-white/15"
          />
          {form.progress && <p className="mt-1.5 text-[11.5px] text-white/50">Memuat naik… {form.progress.percentage}%</p>}
        </Field>
        <Field label="Tajuk (pilihan)" error={form.errors.title}>
          <Input
            value={form.data.title}
            onChange={(e) => {
              form.setData((d) => ({ ...d, title: e.target.value, key: keyTouched ? d.key : slugify(e.target.value) }));
            }}
            placeholder="Cth: Testimoni Puan Aminah"
          />
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
    if (!window.confirm(`Padam media "${media.key}"? Arahan AI yang sebut key ni takkan dapat hantar media lagi.`)) return;
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
      actions={<Button variant="primary" onClick={() => setUploadOpen(true)}><Plus className="h-4 w-4" /> Muat naik</Button>}
    >
      <Head title="Media" />

      <div className="mb-5 flex items-start gap-2.5 rounded-2xl border border-violet-400/20 bg-violet-500/[0.06] p-3.5">
        <Sparkles className="mt-0.5 h-4 w-4 shrink-0 text-violet-300" />
        <p className="text-[12.5px] leading-relaxed text-white/60">
          Muat naik media dengan <span className="font-semibold text-white/80">key</span> pendek, kemudian sebut key tu dalam
          {' '}<span className="font-semibold text-white/80">Arahan tambahan untuk AI</span> di Flow. Contoh:
          {' '}<span className="font-mono text-emerald-200">"Lepas terangkan pakej, hantar testimoni-1"</span>. AI akan hantar media tu pada masa yang sesuai.
        </p>
      </div>

      {media.length === 0 ? (
        <EmptyState
          icon={Images}
          title="Belum ada media"
          hint="Muat naik gambar atau video testimoni untuk digunakan oleh AI."
          action={<Button variant="primary" onClick={() => setUploadOpen(true)}><Plus className="h-4 w-4" /> Muat naik</Button>}
        />
      ) : (
        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
          {media.map((m) => <MediaCard key={m.id} media={m} onEdit={setEditing} />)}
        </div>
      )}

      <UploadModal open={uploadOpen} onClose={() => setUploadOpen(false)} limits={limits} />
      {editing && <EditModal key={editing.id} media={editing} onClose={() => setEditing(null)} />}
    </CekbotLayout>
  );
}
