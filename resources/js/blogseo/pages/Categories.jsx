import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { Plus, Pencil, Trash2, ExternalLink, FolderOpen, EyeOff } from 'lucide-react';
import BlogSeoLayout from '@/blogseo/layouts/BlogSeoLayout';
import { Card, Badge, Button, Field, Input, Textarea, Switch, Modal, EmptyState } from '@/blogseo/components/Ui';
import { cn } from '@/blogseo/lib/utils';

/** Palette drawn from the storefront's violet → fuchsia → rose identity plus
 *  the workspace accent, so category chips sit naturally on the public blog. */
const PALETTE = ['#7c3aed', '#c026d3', '#f43f5e', '#0ea5e9', '#10b981', '#f59e0b', '#6366f1', '#ec4899'];

const BLANK = {
  name: '', slug: '', description: '', color: '#7c3aed',
  sort_order: 0, is_active: true, meta_title: '', meta_description: '',
};

export default function Categories({ categories }) {
  const [open, setOpen] = useState(false);
  const [editing, setEditing] = useState(null);

  const form = useForm(BLANK);
  const { data, setData, errors, processing, reset, clearErrors } = form;

  const openModal = (category = null) => {
    clearErrors();
    setEditing(category);

    setData(category ? {
      name: category.name,
      slug: category.slug,
      description: category.description ?? '',
      color: category.color,
      sort_order: category.sort_order,
      is_active: category.is_active,
      meta_title: category.meta_title ?? '',
      meta_description: category.meta_description ?? '',
    } : { ...BLANK });

    setOpen(true);
  };

  const closeModal = () => {
    setOpen(false);
    setEditing(null);
    reset();
    clearErrors();
  };

  const submit = (e) => {
    e?.preventDefault();
    const options = { preserveScroll: true, onSuccess: closeModal };

    if (editing) {
      form.put(`/blog-seo/categories/${editing.id}`, options);
    } else {
      form.post('/blog-seo/categories', options);
    }
  };

  const destroy = (category) => {
    if (!window.confirm(`Delete "${category.name}"? Its ${category.posts} post(s) become uncategorised.`)) return;
    router.delete(`/blog-seo/categories/${category.id}`, { preserveScroll: true });
  };

  return (
    <BlogSeoLayout
      title="Categories"
      subtitle="Topics that organise the blog and give each article a cleaner URL path"
      actions={<Button variant="primary" onClick={() => openModal()}><Plus className="h-4 w-4" /> New category</Button>}
    >
      <Head title="Categories" />

      {categories.length === 0 ? (
        <EmptyState
          icon={FolderOpen}
          title="No categories yet"
          hint="Create one to start grouping your articles by topic."
          action={<Button variant="primary" onClick={() => openModal()}><Plus className="h-4 w-4" /> New category</Button>}
        />
      ) : (
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          {categories.map((c) => (
            <Card key={c.id} className="p-5 transition-shadow hover:shadow-md">
              <div className="flex items-start justify-between gap-3">
                <div className="flex min-w-0 items-center gap-2.5">
                  <span className="h-9 w-9 shrink-0 rounded-lg" style={{ backgroundColor: c.color }} />
                  <div className="min-w-0">
                    <p className="truncate text-[14px] font-bold text-ink">{c.name}</p>
                    <p className="truncate text-[11.5px] text-muted-2">/blog/category/{c.slug}</p>
                  </div>
                </div>

                <div className="flex shrink-0 items-center gap-0.5">
                  <a href={c.url} target="_blank" rel="noopener noreferrer" className="grid h-8 w-8 place-items-center rounded-lg text-muted hover:bg-surface hover:text-ink" aria-label={`View ${c.name}`}>
                    <ExternalLink className="h-3.5 w-3.5" />
                  </a>
                  <button type="button" onClick={() => openModal(c)} className="grid h-8 w-8 place-items-center rounded-lg text-muted hover:bg-surface hover:text-ink" aria-label={`Edit ${c.name}`}>
                    <Pencil className="h-3.5 w-3.5" />
                  </button>
                  <button type="button" onClick={() => destroy(c)} className="grid h-8 w-8 place-items-center rounded-lg text-muted hover:bg-rose-50 hover:text-rose-600" aria-label={`Delete ${c.name}`}>
                    <Trash2 className="h-3.5 w-3.5" />
                  </button>
                </div>
              </div>

              {c.description && <p className="mt-3 line-clamp-2 text-[12.5px] text-muted">{c.description}</p>}

              <div className="mt-4 flex flex-wrap items-center gap-1.5">
                <Badge className="bg-slate-100 text-slate-600 ring-slate-500/20">{c.published} published</Badge>
                {c.posts > c.published && <Badge className="bg-amber-50 text-amber-700 ring-amber-600/20">{c.posts - c.published} draft</Badge>}
                {!c.is_active && <Badge className="bg-rose-50 text-rose-700 ring-rose-600/20"><EyeOff className="h-3 w-3" /> Hidden</Badge>}
              </div>
            </Card>
          ))}
        </div>
      )}

      <Modal
        open={open}
        onClose={closeModal}
        title={editing ? 'Edit category' : 'New category'}
        hint="Categories appear as filter pills on the blog index."
        footer={
          <>
            <Button variant="ghost" onClick={closeModal}>Cancel</Button>
            <Button variant="primary" onClick={submit} disabled={processing}>{processing ? 'Saving…' : 'Save'}</Button>
          </>
        }
      >
        <form onSubmit={submit} className="space-y-4">
          <Field label="Name" error={errors.name}>
            <Input value={data.name} onChange={(e) => setData('name', e.target.value)} placeholder="Panduan Produk" autoFocus />
          </Field>

          <Field label="URL slug" error={errors.slug} hint="Leave blank to generate from the name.">
            <Input value={data.slug} onChange={(e) => setData('slug', e.target.value)} placeholder="panduan-produk" />
          </Field>

          <Field label="Description" error={errors.description} hint="Shown under the heading on the category page.">
            <Textarea rows={2} value={data.description} onChange={(e) => setData('description', e.target.value)} />
          </Field>

          <Field label="Accent colour" error={errors.color}>
            <div className="flex flex-wrap items-center gap-2">
              {PALETTE.map((swatch) => (
                <button
                  key={swatch}
                  type="button"
                  onClick={() => setData('color', swatch)}
                  style={{ backgroundColor: swatch }}
                  aria-label={`Use colour ${swatch}`}
                  className={cn(
                    'h-8 w-8 rounded-lg transition-transform hover:scale-110',
                    data.color === swatch && 'ring-2 ring-slate-900 ring-offset-2'
                  )}
                />
              ))}
              <Input value={data.color} onChange={(e) => setData('color', e.target.value)} className="!w-28" />
            </div>
          </Field>

          <div className="grid gap-4 sm:grid-cols-2">
            <Field label="Sort order" error={errors.sort_order}>
              <Input type="number" min="0" value={data.sort_order} onChange={(e) => setData('sort_order', Number(e.target.value))} />
            </Field>
            <div className="flex items-end pb-2.5">
              <Switch id="cat-active" checked={data.is_active} onChange={(v) => setData('is_active', v)} label="Visible on the blog" />
            </div>
          </div>

          <div className="border-t border-line pt-4">
            <Field label="SEO title" error={errors.meta_title} hint="Defaults to the category name.">
              <Input value={data.meta_title} onChange={(e) => setData('meta_title', e.target.value)} />
            </Field>
            <Field label="Meta description" error={errors.meta_description} className="mt-3">
              <Textarea rows={2} value={data.meta_description} onChange={(e) => setData('meta_description', e.target.value)} placeholder="120–158 characters for the category page." />
            </Field>
          </div>
        </form>
      </Modal>
    </BlogSeoLayout>
  );
}
