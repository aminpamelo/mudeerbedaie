import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { Check, Search, MoreHorizontal, ExternalLink, Undo2, Ban, Trash2, ShieldCheck, CheckCheck } from 'lucide-react';
import BlogSeoLayout from '@/blogseo/layouts/BlogSeoLayout';
import { Card, Badge, Button, Input, EmptyState, Pagination } from '@/blogseo/components/Ui';
import { cn, timeAgo } from '@/blogseo/lib/utils';

const TABS = [
  { value: 'pending', label: 'Pending', key: 'pending', active: 'bg-amber-500 text-white' },
  { value: 'approved', label: 'Approved', key: 'approved', active: 'bg-emerald-500 text-white' },
  { value: 'spam', label: 'Spam', key: 'spam', active: 'bg-rose-500 text-white' },
  { value: '', label: 'All', key: null, active: 'bg-slate-900 text-white' },
];

function RowMenu({ comment, onClose }) {
  const act = (url, method = 'post') => router[method](url, {}, { preserveScroll: true, onFinish: onClose });

  return (
    <>
      <div className="fixed inset-0 z-10" onClick={onClose} aria-hidden="true" />
      <div className="absolute right-0 top-9 z-20 w-48 overflow-hidden rounded-xl border border-line bg-white py-1 shadow-xl">
        {comment.postUrl && (
          <a href={comment.postUrl} target="_blank" rel="noopener noreferrer" className="flex items-center gap-2.5 px-3 py-2 text-[13px] text-ink-2 hover:bg-surface">
            <ExternalLink className="h-3.5 w-3.5 text-muted" /> View on site
          </a>
        )}
        {comment.status === 'approved' && (
          <button type="button" onClick={() => act(`/blog-seo/comments/${comment.id}/unapprove`)} className="flex w-full items-center gap-2.5 px-3 py-2 text-left text-[13px] text-ink-2 hover:bg-surface">
            <Undo2 className="h-3.5 w-3.5 text-muted" /> Move to pending
          </button>
        )}
        {comment.status !== 'spam' && (
          <button type="button" onClick={() => act(`/blog-seo/comments/${comment.id}/spam`)} className="flex w-full items-center gap-2.5 px-3 py-2 text-left text-[13px] text-ink-2 hover:bg-surface">
            <Ban className="h-3.5 w-3.5 text-muted" /> Mark as spam
          </button>
        )}
        <div className="my-1 border-t border-line" />
        <button
          type="button"
          onClick={() => { if (window.confirm('Delete this comment?')) act(`/blog-seo/comments/${comment.id}`, 'delete'); }}
          className="flex w-full items-center gap-2.5 px-3 py-2 text-left text-[13px] text-rose-600 hover:bg-rose-50"
        >
          <Trash2 className="h-3.5 w-3.5" /> Delete
        </button>
      </div>
    </>
  );
}

function CommentRow({ comment }) {
  const [menuOpen, setMenuOpen] = useState(false);

  return (
    <Card className="p-4">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div className="flex min-w-0 flex-1 gap-3">
          <span className="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-gradient-to-br from-emerald-500 to-teal-500 text-[12px] font-bold text-white">
            {comment.initials}
          </span>

          <div className="min-w-0 flex-1">
            <div className="flex flex-wrap items-center gap-x-2 gap-y-0.5">
              <span className="text-[13.5px] font-bold text-ink">{comment.author}</span>
              <span className="text-[11.5px] text-muted-2">{timeAgo(comment.createdAt)}</span>
              {comment.isReply && <Badge className="bg-slate-100 text-slate-600 ring-slate-500/20">Reply</Badge>}
            </div>

            <p className="mt-0.5 truncate text-[11.5px] text-muted">
              on <Link href={`/blog-seo/posts/${comment.postId}/edit`} className="font-medium text-emerald-700 hover:underline">{comment.postTitle ?? 'deleted post'}</Link>
            </p>

            <p className="mt-2 whitespace-pre-line text-[13px] leading-relaxed text-ink-2">{comment.body}</p>
          </div>
        </div>

        <div className="flex shrink-0 items-center gap-1.5">
          {comment.status !== 'approved' ? (
            <Button size="sm" variant="primary" onClick={() => router.post(`/blog-seo/comments/${comment.id}/approve`, {}, { preserveScroll: true })}>
              <Check className="h-3.5 w-3.5" /> Approve
            </Button>
          ) : (
            <Badge className="bg-emerald-50 text-emerald-700 ring-emerald-600/20">Approved</Badge>
          )}

          <div className="relative">
            <button
              type="button"
              onClick={() => setMenuOpen((v) => !v)}
              className="grid h-8 w-8 place-items-center rounded-lg text-muted transition-colors hover:bg-surface hover:text-ink"
              aria-label="Comment actions"
              aria-expanded={menuOpen}
            >
              <MoreHorizontal className="h-4 w-4" />
            </button>
            {menuOpen && <RowMenu comment={comment} onClose={() => setMenuOpen(false)} />}
          </div>
        </div>
      </div>
    </Card>
  );
}

export default function Comments({ comments, filters, counts }) {
  const [search, setSearch] = useState(filters.search || '');

  const apply = (patch) => {
    router.get('/blog-seo/comments', { ...filters, ...patch }, { preserveState: true, replace: true, preserveScroll: true });
  };

  return (
    <BlogSeoLayout
      title="Comments"
      subtitle="Moderate reader comments before they appear on the blog"
      actions={
        counts.pending > 0 && filters.status === 'pending' ? (
          <Button
            variant="primary"
            onClick={() => {
              if (window.confirm(`Approve all ${counts.pending} pending comment(s)?`)) {
                router.post('/blog-seo/comments/approve-all', {}, { preserveScroll: true });
              }
            }}
          >
            <CheckCheck className="h-4 w-4" /> Approve all ({counts.pending})
          </Button>
        ) : null
      }
    >
      <Head title="Comments" />

      <div className="mb-4 flex flex-wrap items-center gap-2">
        {TABS.map((tab) => (
          <button
            key={tab.label}
            type="button"
            onClick={() => apply({ status: tab.value })}
            className={cn(
              'inline-flex items-center gap-1.5 rounded-xl px-3.5 py-2 text-[13px] font-semibold transition-colors',
              filters.status === tab.value ? tab.active : 'bg-surface text-ink-2 ring-1 ring-inset ring-line hover:bg-slate-100'
            )}
          >
            {tab.label}
            {tab.key && <span className="tabular-nums opacity-70">{counts[tab.key]}</span>}
          </button>
        ))}

        <form
          onSubmit={(e) => { e.preventDefault(); apply({ search }); }}
          className="relative ml-auto w-full sm:w-64"
        >
          <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-2" />
          <Input value={search} onChange={(e) => setSearch(e.target.value)} placeholder="Search comments…" className="pl-9" aria-label="Search comments" />
        </form>
      </div>

      {comments.data.length === 0 ? (
        <EmptyState
          icon={ShieldCheck}
          title="Nothing to moderate"
          hint={filters.status === 'pending' ? 'All comments have been reviewed.' : 'No comments match this filter.'}
        />
      ) : (
        <div className="space-y-2.5">
          {comments.data.map((c) => <CommentRow key={c.id} comment={c} />)}
        </div>
      )}

      <Pagination links={comments.links} meta={{ from: comments.from, to: comments.to, total: comments.total }} />
    </BlogSeoLayout>
  );
}
