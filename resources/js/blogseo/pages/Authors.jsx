import { Head, router, useForm } from '@inertiajs/react';
import { useRef, useState } from 'react';
import { Plus, Pencil, Trash2, Users, ImagePlus, X } from 'lucide-react';
import BlogSeoLayout from '@/blogseo/layouts/BlogSeoLayout';
import { Card, Badge, Button, Field, Input, Modal, EmptyState } from '@/blogseo/components/Ui';
import { cn, initialsFrom } from '@/blogseo/lib/utils';

const BLANK = { name: '', slug: '', avatar: null, remove_avatar: false };

/** Circular avatar — falls back to the author's initials when no image is set. */
function Avatar({ url, name, className }) {
  return url ? (
    <img src={url} alt="" className={cn('rounded-full object-cover', className)} />
  ) : (
    <span className={cn('grid place-items-center rounded-full bg-gradient-to-br from-[var(--color-brand)] to-[var(--color-sky)] text-[13px] font-semibold text-white', className)}>
      {initialsFrom(name)}
    </span>
  );
}

export default function Authors({ authors }) {
  const [open, setOpen] = useState(false);
  const [editing, setEditing] = useState(null);
  const [preview, setPreview] = useState(null);
  const fileRef = useRef(null);

  const form = useForm(BLANK);
  const { data, setData, errors, processing, reset, clearErrors } = form;

  const openModal = (author = null) => {
    clearErrors();
    setEditing(author);
    setPreview(author?.avatar_url ?? null);
    setData(author ? { name: author.name, slug: author.slug, avatar: null, remove_avatar: false } : { ...BLANK });
    if (fileRef.current) fileRef.current.value = '';
    setOpen(true);
  };

  const closeModal = () => {
    setOpen(false);
    setEditing(null);
    setPreview(null);
    reset();
    clearErrors();
  };

  const pickFile = (e) => {
    const file = e.target.files?.[0];
    if (!file) return;
    setData((prev) => ({ ...prev, avatar: file, remove_avatar: false }));
    setPreview(URL.createObjectURL(file));
  };

  const clearAvatar = () => {
    setData((prev) => ({ ...prev, avatar: null, remove_avatar: true }));
    setPreview(null);
    if (fileRef.current) fileRef.current.value = '';
  };

  const submit = (e) => {
    e?.preventDefault();
    const options = { preserveScroll: true, forceFormData: true, onSuccess: closeModal };

    if (editing) {
      form.transform((d) => ({ ...d, _method: 'put' }));
      form.post(`/blog-seo/authors/${editing.id}`, options);
    } else {
      form.transform((d) => d);
      form.post('/blog-seo/authors', options);
    }
  };

  const destroy = (author) => {
    if (!window.confirm(`Delete "${author.name}"? Their ${author.posts} post(s) fall back to the default byline.`)) return;
    router.delete(`/blog-seo/authors/${author.id}`, { preserveScroll: true });
  };

  return (
    <BlogSeoLayout
      title="Authors"
      subtitle="Bylines your articles are attributed to — assign one to any post from its editor"
      actions={<Button variant="primary" onClick={() => openModal()}><Plus className="h-4 w-4" /> New author</Button>}
    >
      <Head title="Authors" />

      {authors.length === 0 ? (
        <EmptyState
          icon={Users}
          title="No authors yet"
          hint="Create one, then pick it as the byline in any post's Publishing panel."
          action={<Button variant="primary" onClick={() => openModal()}><Plus className="h-4 w-4" /> New author</Button>}
        />
      ) : (
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          {authors.map((a) => (
            <Card key={a.id} className="p-5 transition-shadow hover:shadow-md">
              <div className="flex items-start justify-between gap-3">
                <div className="flex min-w-0 items-center gap-3">
                  <Avatar url={a.avatar_url} name={a.name} className="h-11 w-11 shrink-0" />
                  <div className="min-w-0">
                    <p className="truncate text-[14px] font-bold text-ink">{a.name}</p>
                    <p className="truncate text-[11.5px] text-muted-2">{a.slug}</p>
                  </div>
                </div>

                <div className="flex shrink-0 items-center gap-0.5">
                  <button type="button" onClick={() => openModal(a)} className="grid h-8 w-8 place-items-center rounded-lg text-muted hover:bg-surface hover:text-ink" aria-label={`Edit ${a.name}`}>
                    <Pencil className="h-3.5 w-3.5" />
                  </button>
                  <button type="button" onClick={() => destroy(a)} className="grid h-8 w-8 place-items-center rounded-lg text-muted hover:bg-rose-50 hover:text-rose-600" aria-label={`Delete ${a.name}`}>
                    <Trash2 className="h-3.5 w-3.5" />
                  </button>
                </div>
              </div>

              <div className="mt-4 flex flex-wrap items-center gap-1.5">
                <Badge className="bg-slate-100 text-slate-600 ring-slate-500/20">{a.published} published</Badge>
                {a.posts > a.published && <Badge className="bg-amber-50 text-amber-700 ring-amber-600/20">{a.posts - a.published} draft</Badge>}
              </div>
            </Card>
          ))}
        </div>
      )}

      <Modal
        open={open}
        onClose={closeModal}
        title={editing ? 'Edit author' : 'New author'}
        hint="Authors show as the byline on the public blog."
        footer={
          <>
            <Button variant="ghost" onClick={closeModal}>Cancel</Button>
            <Button variant="primary" onClick={submit} disabled={processing}>{processing ? 'Saving…' : 'Save'}</Button>
          </>
        }
      >
        <form onSubmit={submit} className="space-y-4">
          <Field label="Photo" error={errors.avatar} hint="Square image works best. Optional.">
            <div className="flex items-center gap-4">
              <Avatar url={preview} name={data.name} className="h-16 w-16 shrink-0" />
              <div className="flex flex-wrap items-center gap-2">
                <Button size="sm" onClick={() => fileRef.current?.click()}>
                  <ImagePlus className="h-3.5 w-3.5" /> {preview ? 'Change' : 'Upload'}
                </Button>
                {preview && (
                  <Button size="sm" variant="ghost" onClick={clearAvatar}>
                    <X className="h-3.5 w-3.5" /> Remove
                  </Button>
                )}
              </div>
              <input ref={fileRef} type="file" accept="image/*" onChange={pickFile} className="hidden" />
            </div>
          </Field>

          <Field label="Name" error={errors.name}>
            <Input value={data.name} onChange={(e) => setData('name', e.target.value)} placeholder="Nurul Aina" autoFocus />
          </Field>

          <Field label="URL slug" error={errors.slug} hint="Leave blank to generate from the name.">
            <Input value={data.slug} onChange={(e) => setData('slug', e.target.value)} placeholder="nurul-aina" />
          </Field>
        </form>
      </Modal>
    </BlogSeoLayout>
  );
}
