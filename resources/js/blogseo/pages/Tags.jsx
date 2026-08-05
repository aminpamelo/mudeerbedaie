import { Head, router, useForm } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import { Plus, Pencil, Trash2, ExternalLink, Hash, Sparkles, Search } from 'lucide-react';
import BlogSeoLayout from '@/blogseo/layouts/BlogSeoLayout';
import { Card, Button, Field, Input, Modal, EmptyState } from '@/blogseo/components/Ui';
import { cn } from '@/blogseo/lib/utils';

export default function Tags({ tags, unusedCount }) {
  const [query, setQuery] = useState('');
  const [open, setOpen] = useState(false);
  const [editing, setEditing] = useState(null);

  const form = useForm({ name: '', slug: '' });
  const { data, setData, errors, processing, reset, clearErrors } = form;

  const filtered = useMemo(() => {
    const q = query.trim().toLowerCase();
    if (!q) return tags;
    return tags.filter((t) => t.name.toLowerCase().includes(q));
  }, [tags, query]);

  const openModal = (tag = null) => {
    clearErrors();
    setEditing(tag);
    setData(tag ? { name: tag.name, slug: tag.slug } : { name: '', slug: '' });
    setOpen(true);
  };

  const closeModal = () => { setOpen(false); setEditing(null); reset(); clearErrors(); };

  const submit = (e) => {
    e?.preventDefault();
    const options = { preserveScroll: true, onSuccess: closeModal };

    if (editing) {
      form.put(`/blog-seo/tags/${editing.id}`, options);
    } else {
      form.post('/blog-seo/tags', options);
    }
  };

  const destroy = (tag) => {
    if (!window.confirm(`Delete the tag "${tag.name}"?`)) return;
    router.delete(`/blog-seo/tags/${tag.id}`, { preserveScroll: true });
  };

  const prune = () => {
    if (!window.confirm(`Delete all ${unusedCount} tag(s) with no posts?`)) return;
    router.post('/blog-seo/tags/prune', {}, { preserveScroll: true });
  };

  return (
    <BlogSeoLayout
      title="Tags"
      subtitle="Fine-grained topics. Tags with no published posts are excluded from the sitemap."
      actions={
        <>
          {unusedCount > 0 && (
            <Button variant="ghost" onClick={prune}>
              <Sparkles className="h-4 w-4" /> Prune unused ({unusedCount})
            </Button>
          )}
          <Button variant="primary" onClick={() => openModal()}><Plus className="h-4 w-4" /> New tag</Button>
        </>
      }
    >
      <Head title="Tags" />

      <div className="mb-4 max-w-sm">
        <div className="relative">
          <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-2" />
          <Input value={query} onChange={(e) => setQuery(e.target.value)} placeholder="Search tags…" className="pl-9" aria-label="Search tags" />
        </div>
      </div>

      <Card className="p-5">
        {filtered.length === 0 ? (
          <EmptyState
            icon={Hash}
            title={tags.length === 0 ? 'No tags yet' : 'No tags match that search'}
            hint={tags.length === 0 ? 'Tags are created automatically when you type them in the post editor.' : 'Try a different search term.'}
          />
        ) : (
          <div className="flex flex-wrap gap-2">
            {filtered.map((tag) => (
              <div
                key={tag.id}
                className="group inline-flex items-center gap-1.5 rounded-full py-1.5 pl-3 pr-1.5 text-[13px] ring-1 ring-inset ring-line transition-colors hover:bg-emerald-50 hover:ring-emerald-300"
              >
                <Hash className="h-3.5 w-3.5 text-muted-2" />
                <span className="font-semibold text-ink-2">{tag.name}</span>
                <span className={cn(
                  'rounded-full px-1.5 text-[11px] font-bold tabular-nums',
                  tag.published > 0 ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-muted-2'
                )}>
                  {tag.published}
                </span>

                <span className="flex items-center gap-0.5">
                  {tag.published > 0 && (
                    <a href={tag.url} target="_blank" rel="noopener noreferrer" className="grid h-6 w-6 place-items-center rounded-full text-muted-2 hover:bg-white hover:text-ink" aria-label={`View ${tag.name}`}>
                      <ExternalLink className="h-3 w-3" />
                    </a>
                  )}
                  <button type="button" onClick={() => openModal(tag)} className="grid h-6 w-6 place-items-center rounded-full text-muted-2 hover:bg-white hover:text-ink" aria-label={`Rename ${tag.name}`}>
                    <Pencil className="h-3 w-3" />
                  </button>
                  <button type="button" onClick={() => destroy(tag)} className="grid h-6 w-6 place-items-center rounded-full text-muted-2 hover:bg-white hover:text-rose-600" aria-label={`Delete ${tag.name}`}>
                    <Trash2 className="h-3 w-3" />
                  </button>
                </span>
              </div>
            ))}
          </div>
        )}
      </Card>

      <Modal
        open={open}
        onClose={closeModal}
        title={editing ? 'Rename tag' : 'New tag'}
        size="sm"
        footer={
          <>
            <Button variant="ghost" onClick={closeModal}>Cancel</Button>
            <Button variant="primary" onClick={submit} disabled={processing}>{processing ? 'Saving…' : 'Save'}</Button>
          </>
        }
      >
        <form onSubmit={submit} className="space-y-4">
          <Field label="Name" error={errors.name}>
            <Input value={data.name} onChange={(e) => setData('name', e.target.value)} placeholder="penjagaan kulit" autoFocus />
          </Field>
          <Field label="URL slug" error={errors.slug} hint="Leave blank to generate from the name.">
            <Input value={data.slug} onChange={(e) => setData('slug', e.target.value)} placeholder="penjagaan-kulit" />
          </Field>
        </form>
      </Modal>
    </BlogSeoLayout>
  );
}
