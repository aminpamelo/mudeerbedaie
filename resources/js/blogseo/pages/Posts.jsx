import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import {
  PenLine, Search, Star, Eye, Clock, MoreHorizontal, ExternalLink,
  RefreshCw, Rocket, EyeOff, Trash2, FileText, MessageSquare, Image as ImageIcon,
} from 'lucide-react';
import BlogSeoLayout from '@/blogseo/layouts/BlogSeoLayout';
import { Card, Badge, Button, Input, Select, EmptyState, Pagination, ScoreBar } from '@/blogseo/components/Ui';
import { cn, formatNumber, timeAgo, postStatusMeta } from '@/blogseo/lib/utils';

const STATUS_TABS = [
  { value: '', label: 'All', key: 'all' },
  { value: 'published', label: 'Published', key: 'published' },
  { value: 'draft', label: 'Drafts', key: 'draft' },
  { value: 'scheduled', label: 'Scheduled', key: 'scheduled' },
];

function RowMenu({ post, onClose }) {
  const act = (url, method = 'post') => {
    router[method](url, {}, { preserveScroll: true, onFinish: onClose });
  };

  return (
    <>
      <div className="fixed inset-0 z-10" onClick={onClose} aria-hidden="true" />
      <div className="absolute right-0 top-9 z-20 w-52 overflow-hidden rounded-xl border border-line bg-white py-1 shadow-xl">
        <Link
          href={`/blog-seo/posts/${post.id}/edit`}
          className="flex items-center gap-2.5 px-3 py-2 text-[13px] text-ink-2 hover:bg-surface"
        >
          <PenLine className="h-3.5 w-3.5 text-muted" /> Edit
        </Link>

        {post.publicUrl && (
          <a
            href={post.publicUrl}
            target="_blank"
            rel="noopener noreferrer"
            className="flex items-center gap-2.5 px-3 py-2 text-[13px] text-ink-2 hover:bg-surface"
          >
            <ExternalLink className="h-3.5 w-3.5 text-muted" /> View live
          </a>
        )}

        <button type="button" onClick={() => act(`/blog-seo/posts/${post.id}/reanalyse`)} className="flex w-full items-center gap-2.5 px-3 py-2 text-left text-[13px] text-ink-2 hover:bg-surface">
          <RefreshCw className="h-3.5 w-3.5 text-muted" /> Re-run SEO audit
        </button>

        <button type="button" onClick={() => act(`/blog-seo/posts/${post.id}/featured`)} className="flex w-full items-center gap-2.5 px-3 py-2 text-left text-[13px] text-ink-2 hover:bg-surface">
          <Star className="h-3.5 w-3.5 text-muted" /> {post.isFeatured ? 'Unfeature' : 'Feature'}
        </button>

        {post.status === 'published' ? (
          <button type="button" onClick={() => act(`/blog-seo/posts/${post.id}/unpublish`)} className="flex w-full items-center gap-2.5 px-3 py-2 text-left text-[13px] text-ink-2 hover:bg-surface">
            <EyeOff className="h-3.5 w-3.5 text-muted" /> Unpublish
          </button>
        ) : (
          <button type="button" onClick={() => act(`/blog-seo/posts/${post.id}/publish`)} className="flex w-full items-center gap-2.5 px-3 py-2 text-left text-[13px] text-ink-2 hover:bg-surface">
            <Rocket className="h-3.5 w-3.5 text-muted" /> Publish now
          </button>
        )}

        <div className="my-1 border-t border-line" />

        <button
          type="button"
          onClick={() => {
            if (window.confirm(`Move "${post.title}" to trash?`)) act(`/blog-seo/posts/${post.id}`, 'delete');
          }}
          className="flex w-full items-center gap-2.5 px-3 py-2 text-left text-[13px] text-rose-600 hover:bg-rose-50"
        >
          <Trash2 className="h-3.5 w-3.5" /> Delete
        </button>
      </div>
    </>
  );
}

function PostRow({ post }) {
  const [menuOpen, setMenuOpen] = useState(false);
  const st = postStatusMeta(post.status);

  return (
    <Card className="flex flex-col gap-3 p-3 transition-shadow hover:shadow-md sm:flex-row sm:items-center">
      {/* Thumb */}
      <Link href={`/blog-seo/posts/${post.id}/edit`} className="relative block h-24 w-full shrink-0 overflow-hidden rounded-xl bg-surface sm:h-16 sm:w-24">
        {post.image ? (
          <img src={post.image} alt="" className="h-full w-full object-cover" loading="lazy" />
        ) : (
          <span className="grid h-full w-full place-items-center bg-gradient-to-br from-emerald-50 to-teal-50">
            <ImageIcon className="h-5 w-5 text-emerald-200" />
          </span>
        )}
        {post.isFeatured && (
          <span className="absolute left-1.5 top-1.5 grid h-5 w-5 place-items-center rounded-md bg-amber-400 text-white shadow" title="Featured">
            <Star className="h-3 w-3" fill="currentColor" strokeWidth={0} />
          </span>
        )}
      </Link>

      {/* Body */}
      <div className="min-w-0 flex-1">
        <div className="flex flex-wrap items-center gap-2">
          <Link href={`/blog-seo/posts/${post.id}/edit`} className="truncate text-[14px] font-bold text-ink hover:text-emerald-700">
            {post.title}
          </Link>
          <Badge className={st.className}>{st.label}</Badge>
          {post.pendingComments > 0 && (
            <Link href="/blog-seo/comments">
              <Badge className="bg-amber-50 text-amber-700 ring-amber-600/20">
                <MessageSquare className="h-3 w-3" /> {post.pendingComments}
              </Badge>
            </Link>
          )}
        </div>

        {post.excerpt && <p className="mt-1 line-clamp-1 text-[12.5px] text-muted">{post.excerpt}</p>}

        <div className="mt-1.5 flex flex-wrap items-center gap-x-3 gap-y-1 text-[11.5px] text-muted">
          {post.category && (
            <span className="inline-flex items-center gap-1">
              <span className="h-2 w-2 rounded-full" style={{ backgroundColor: post.categoryColor || '#94A3B8' }} />
              {post.category}
            </span>
          )}
          <span className="uppercase">{post.locale}</span>
          <span>{post.author}</span>
          <span className="inline-flex items-center gap-1"><Eye className="h-3 w-3" />{formatNumber(post.views)}</span>
          <span className="inline-flex items-center gap-1"><Clock className="h-3 w-3" />{post.readingTime}m</span>
          <span>{post.publishedAt ? timeAgo(post.publishedAt) : `edited ${timeAgo(post.updatedAt)}`}</span>
        </div>
      </div>

      {/* Score + actions */}
      <div className="flex shrink-0 items-center justify-between gap-3 sm:justify-end">
        <ScoreBar score={post.score} />
        <div className="relative">
          <button
            type="button"
            onClick={() => setMenuOpen((v) => !v)}
            className="grid h-8 w-8 place-items-center rounded-lg text-muted transition-colors hover:bg-surface hover:text-ink"
            aria-label={`Actions for ${post.title}`}
            aria-expanded={menuOpen}
          >
            <MoreHorizontal className="h-4 w-4" />
          </button>
          {menuOpen && <RowMenu post={post} onClose={() => setMenuOpen(false)} />}
        </div>
      </div>
    </Card>
  );
}

export default function Posts({ posts, filters, categories, counts }) {
  const [search, setSearch] = useState(filters.search || '');

  const apply = (patch) => {
    router.get('/blog-seo/posts', { ...filters, ...patch }, { preserveState: true, replace: true, preserveScroll: true });
  };

  const onSearch = (e) => {
    e.preventDefault();
    apply({ search });
  };

  return (
    <BlogSeoLayout
      title="Posts"
      subtitle="Write, publish and score every article on the blog"
      actions={<Button variant="primary" href="/blog-seo/posts/create"><PenLine className="h-4 w-4" /> Write a post</Button>}
    >
      <Head title="Posts" />

      {/* Filters */}
      <div className="mb-4 flex flex-wrap items-center gap-2">
        {STATUS_TABS.map((tab) => (
          <button
            key={tab.key}
            type="button"
            onClick={() => apply({ status: tab.value })}
            className={cn(
              'inline-flex items-center gap-1.5 rounded-xl px-3.5 py-2 text-[13px] font-semibold transition-colors',
              filters.status === tab.value
                ? 'bg-slate-900 text-white'
                : 'bg-surface text-ink-2 ring-1 ring-inset ring-line hover:bg-slate-100'
            )}
          >
            {tab.label}
            <span className="tabular-nums opacity-60">{counts[tab.key]}</span>
          </button>
        ))}

        <form onSubmit={onSearch} className="relative ml-auto w-full sm:w-64">
          <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-2" />
          <Input
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            placeholder="Search posts…"
            className="pl-9"
            aria-label="Search posts"
          />
        </form>

        <Select value={filters.category} onChange={(e) => apply({ category: e.target.value })} className="w-full sm:w-44" aria-label="Filter by category">
          <option value="">All categories</option>
          {categories.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
        </Select>

        <Select value={filters.sort} onChange={(e) => apply({ sort: e.target.value })} className="w-full sm:w-40" aria-label="Sort posts">
          <option value="latest">Newest first</option>
          <option value="views">Most viewed</option>
          <option value="seo_low">Lowest SEO</option>
          <option value="seo_high">Highest SEO</option>
        </Select>
      </div>

      {/* List */}
      {posts.data.length === 0 ? (
        <EmptyState
          icon={FileText}
          title="No posts found"
          hint={filters.search || filters.status || filters.category ? 'Try clearing the filters.' : 'Write your first article to get started.'}
          action={<Button variant="primary" href="/blog-seo/posts/create"><PenLine className="h-4 w-4" /> Write a post</Button>}
        />
      ) : (
        <div className="space-y-2.5">
          {posts.data.map((post) => <PostRow key={post.id} post={post} />)}
        </div>
      )}

      <Pagination links={posts.links} meta={{ from: posts.from, to: posts.to, total: posts.total }} />
    </BlogSeoLayout>
  );
}
