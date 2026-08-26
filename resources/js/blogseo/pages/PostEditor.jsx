import { Head, Link, router, useForm } from '@inertiajs/react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import toast from 'react-hot-toast';
import {
  ArrowLeft, Rocket, Save, ExternalLink, Bold, Italic, List, ListOrdered,
  Link2, Image as ImageIcon, Quote, Code, Eye, Columns2, PenLine,
  CheckCircle2, AlertTriangle, XCircle, Search, X, ShoppingBag, Trash2, Loader2,
  ChevronDown, Check, UserRound, Plus,
  AlignLeft, AlignCenter, AlignRight, AlignJustify,
} from 'lucide-react';
import BlogSeoLayout from '@/blogseo/layouts/BlogSeoLayout';
import { Card, SectionTitle, Badge, Button, Field, Input, Textarea, Select, Switch, Modal, ScoreGauge } from '@/blogseo/components/Ui';
import { cn, csrfToken, checkTone, formatNumber, initialsFrom } from '@/blogseo/lib/utils';

const GROUP_LABELS = {
  basics: 'Basics',
  keyword: 'Focus keyword',
  content: 'Content quality',
  technical: 'Technical',
};

const CHECK_ICON = { pass: CheckCircle2, warn: AlertTriangle, fail: XCircle };

/** Markdown toolbar actions. `before`/`after` wrap the current selection. */
const TOOLS = [
  { key: 'bold', title: 'Bold', text: 'B', bold: true, before: '**', after: '**' },
  { key: 'italic', title: 'Italic', text: 'I', italic: true, before: '*', after: '*' },
  { key: 'h2', title: 'Heading 2', text: 'H2', before: '\n## ', after: '' },
  { key: 'h3', title: 'Heading 3', text: 'H3', before: '\n### ', after: '' },
  { key: 'ul', title: 'Bullet list', icon: List, before: '\n- ', after: '' },
  { key: 'ol', title: 'Numbered list', icon: ListOrdered, before: '\n1. ', after: '' },
  { key: 'link', title: 'Link', icon: Link2, before: '[', after: '](https://)' },
  { key: 'image', title: 'Insert image', icon: ImageIcon, picker: true },
  { key: 'quote', title: 'Quote', icon: Quote, before: '\n> ', after: '' },
  { key: 'code', title: 'Code', icon: Code, before: '`', after: '`' },
];

/**
 * Paragraph alignment. Markdown has no alignment syntax, so each option wraps
 * the selection in a `<div>` with an inline `text-align` — CommonMark passes the
 * raw HTML through (html_input is allowed) and the blank lines let the text
 * inside still be parsed as Markdown (bold, links, etc.).
 */
const ALIGNMENTS = [
  { key: 'left', title: 'Align left', icon: AlignLeft, value: 'left' },
  { key: 'center', title: 'Align center', icon: AlignCenter, value: 'center' },
  { key: 'right', title: 'Align right', icon: AlignRight, value: 'right' },
  { key: 'justify', title: 'Justify', icon: AlignJustify, value: 'justify' },
];

function MediaPickerModal({ open, onClose, media, onPick }) {
  const [query, setQuery] = useState('');

  const filtered = useMemo(() => {
    const q = query.trim().toLowerCase();
    if (!q) return media;
    return media.filter((m) => (m.title || '').toLowerCase().includes(q));
  }, [media, query]);

  return (
    <Modal open={open} onClose={onClose} title="Choose an image" hint="Pulled from your media library" size="lg">
      <div className="relative mb-4">
        <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-2" />
        <Input value={query} onChange={(e) => setQuery(e.target.value)} placeholder="Search media…" className="pl-9" autoFocus />
      </div>

      {filtered.length === 0 ? (
        <p className="py-10 text-center text-[13px] text-muted">No images match that search.</p>
      ) : (
        <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
          {filtered.map((m) => (
            <button
              key={m.id}
              type="button"
              onClick={() => { onPick(m); onClose(); }}
              className="group overflow-hidden rounded-xl ring-1 ring-line transition-all hover:ring-2 hover:ring-[var(--color-brand)]"
            >
              <span className="block aspect-[4/3] bg-surface">
                <img
                  src={m.url}
                  alt={m.alt || m.title || ''}
                  loading="lazy"
                  className="h-full w-full object-cover"
                  onError={(e) => { e.currentTarget.style.visibility = 'hidden'; }}
                />
              </span>
              <span className="block truncate px-2 py-1.5 text-left text-[11px] text-muted">{m.title}</span>
            </button>
          ))}
        </div>
      )}
    </Modal>
  );
}

/** Toolbar dropdown that wraps the current selection in an aligned block. */
function AlignMenu({ onPick }) {
  const [open, setOpen] = useState(false);
  const boxRef = useRef(null);

  useEffect(() => {
    if (!open) return undefined;
    const onDown = (e) => { if (boxRef.current && !boxRef.current.contains(e.target)) setOpen(false); };
    const onKey = (e) => e.key === 'Escape' && setOpen(false);
    document.addEventListener('mousedown', onDown);
    document.addEventListener('keydown', onKey);
    return () => {
      document.removeEventListener('mousedown', onDown);
      document.removeEventListener('keydown', onKey);
    };
  }, [open]);

  return (
    <div ref={boxRef} className="relative">
      <button
        type="button"
        onClick={() => setOpen((v) => !v)}
        title="Text alignment"
        aria-label="Text alignment"
        aria-haspopup="menu"
        aria-expanded={open}
        className="flex h-9 items-center gap-0.5 rounded-lg px-1.5 text-muted transition-colors hover:bg-white hover:text-emerald-600"
      >
        <AlignJustify className="h-4 w-4" strokeWidth={2} />
        <ChevronDown className={cn('h-3 w-3 transition-transform', open && 'rotate-180')} />
      </button>

      {open && (
        <div className="absolute left-0 top-full z-20 mt-1 w-40 rounded-xl border border-line bg-white p-1 shadow-lg">
          {ALIGNMENTS.map((a) => (
            <button
              key={a.key}
              type="button"
              onClick={() => { onPick(a.value); setOpen(false); }}
              className="flex w-full items-center gap-2.5 rounded-lg px-2.5 py-1.5 text-left text-[13px] text-ink-2 transition-colors hover:bg-surface hover:text-emerald-700"
            >
              <a.icon className="h-4 w-4 shrink-0" strokeWidth={2} /> {a.title}
            </button>
          ))}
        </div>
      )}
    </div>
  );
}

function ProductPicker({ open, onClose, products, selected, onToggle }) {
  const [query, setQuery] = useState('');

  const filtered = useMemo(() => {
    const q = query.trim().toLowerCase();
    if (!q) return products;
    return products.filter((p) => p.name.toLowerCase().includes(q));
  }, [products, query]);

  return (
    <Modal
      open={open}
      onClose={onClose}
      title="Products in this article"
      hint="Shown as a shopping strip under the article — turns the post into a storefront entry point."
      size="lg"
      footer={<Button variant="primary" onClick={onClose}>Done ({selected.length})</Button>}
    >
      <div className="relative mb-3">
        <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-2" />
        <Input value={query} onChange={(e) => setQuery(e.target.value)} placeholder="Search products…" className="pl-9" autoFocus />
      </div>

      <div className="space-y-1">
        {filtered.map((p) => {
          const checked = selected.includes(p.id);
          return (
            <label key={p.id} className="flex cursor-pointer items-center gap-3 rounded-xl px-2.5 py-2 hover:bg-surface">
              <input
                type="checkbox"
                checked={checked}
                onChange={() => onToggle(p.id)}
                className="h-4 w-4 rounded border-line text-[var(--color-brand)] focus:ring-[var(--color-brand)]"
              />
              <span className="min-w-0 flex-1 truncate text-[13px] text-ink-2">{p.name}</span>
              <span className="shrink-0 text-[12px] tabular-nums text-muted">RM {p.price.toFixed(2)}</span>
            </label>
          );
        })}
      </div>
    </Modal>
  );
}

/** Small round avatar with an initials fallback. */
function AuthorAvatar({ url, name, className }) {
  return url ? (
    <img src={url} alt="" className={cn('rounded-full object-cover', className)} />
  ) : (
    <span className={cn('grid place-items-center rounded-full bg-gradient-to-br from-[var(--color-brand)] to-[var(--color-sky)] text-[10px] font-semibold text-white', className)}>
      {name ? initialsFrom(name) : <UserRound className="h-3.5 w-3.5" />}
    </span>
  );
}

/**
 * Searchable author byline picker. A native <select> can't show avatars or
 * filter, so this is a lightweight combobox: a trigger showing the current
 * pick and a searchable, avatar-rich dropdown panel.
 */
function AuthorPicker({ authors, value, onChange, error }) {
  const [open, setOpen] = useState(false);
  const [query, setQuery] = useState('');
  const boxRef = useRef(null);
  const searchRef = useRef(null);

  const selected = useMemo(
    () => authors.find((a) => String(a.id) === String(value)) ?? null,
    [authors, value],
  );

  const filtered = useMemo(() => {
    const q = query.trim().toLowerCase();
    if (!q) return authors;
    return authors.filter((a) => a.name.toLowerCase().includes(q));
  }, [authors, query]);

  useEffect(() => {
    if (!open) return undefined;
    const onDown = (e) => { if (boxRef.current && !boxRef.current.contains(e.target)) setOpen(false); };
    const onKey = (e) => e.key === 'Escape' && setOpen(false);
    document.addEventListener('mousedown', onDown);
    document.addEventListener('keydown', onKey);
    const t = setTimeout(() => searchRef.current?.focus(), 20);
    return () => {
      document.removeEventListener('mousedown', onDown);
      document.removeEventListener('keydown', onKey);
      clearTimeout(t);
    };
  }, [open]);

  const pick = (id) => {
    onChange(id === null ? '' : String(id));
    setOpen(false);
    setQuery('');
  };

  return (
    <div ref={boxRef} className="relative">
      <button
        type="button"
        onClick={() => setOpen((v) => !v)}
        aria-haspopup="listbox"
        aria-expanded={open}
        className={cn(
          'flex w-full items-center gap-2.5 rounded-xl bg-white px-3 py-2 text-left text-[13.5px] text-ink ring-1 ring-inset transition-shadow',
          open ? 'ring-2 ring-[var(--color-brand)]' : 'ring-line hover:ring-slate-300',
        )}
      >
        {selected
          ? <AuthorAvatar url={selected.avatar_url} name={selected.name} className="h-7 w-7 shrink-0" />
          : <span className="grid h-7 w-7 shrink-0 place-items-center rounded-full bg-surface text-muted-2"><UserRound className="h-4 w-4" /></span>}
        <span className={cn('min-w-0 flex-1 truncate', !selected && 'text-muted-2')}>
          {selected ? selected.name : 'Default byline'}
        </span>
        <ChevronDown className={cn('h-4 w-4 shrink-0 text-muted-2 transition-transform', open && 'rotate-180')} />
      </button>

      {open && (
        <div className="absolute z-30 mt-1.5 w-full overflow-hidden rounded-xl bg-white shadow-[0_16px_40px_-12px_rgba(0,0,0,0.28)] ring-1 ring-line">
          <div className="relative border-b border-line p-2">
            <Search className="pointer-events-none absolute left-4 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-muted-2" />
            <input
              ref={searchRef}
              value={query}
              onChange={(e) => setQuery(e.target.value)}
              placeholder="Search authors…"
              className="w-full rounded-lg border-0 bg-surface py-2 pl-8 pr-2.5 text-[13px] text-ink placeholder:text-muted-2 focus:outline-none focus:ring-2 focus:ring-[var(--color-brand)]"
            />
          </div>

          <div className="scroll-thin max-h-60 overflow-y-auto p-1">
            <button
              type="button"
              onClick={() => pick(null)}
              className="flex w-full items-center gap-2.5 rounded-lg px-2 py-2 text-left text-[13px] hover:bg-surface"
            >
              <span className="grid h-7 w-7 shrink-0 place-items-center rounded-full bg-surface text-muted-2"><UserRound className="h-4 w-4" /></span>
              <span className="min-w-0 flex-1 truncate text-muted">Default byline</span>
              {!selected && <Check className="h-4 w-4 shrink-0 text-[var(--color-brand)]" />}
            </button>

            {filtered.length === 0 ? (
              <p className="px-2 py-6 text-center text-[12.5px] text-muted-2">No authors match “{query}”.</p>
            ) : filtered.map((a) => {
              const active = String(a.id) === String(value);
              return (
                <button
                  key={a.id}
                  type="button"
                  onClick={() => pick(a.id)}
                  className={cn('flex w-full items-center gap-2.5 rounded-lg px-2 py-2 text-left text-[13px] hover:bg-surface', active && 'bg-emerald-50/60')}
                >
                  <AuthorAvatar url={a.avatar_url} name={a.name} className="h-7 w-7 shrink-0" />
                  <span className="min-w-0 flex-1 truncate font-medium text-ink-2">{a.name}</span>
                  {active && <Check className="h-4 w-4 shrink-0 text-[var(--color-brand)]" />}
                </button>
              );
            })}
          </div>

          <a
            href="/blog-seo/authors"
            className="flex items-center gap-2 border-t border-line px-3 py-2.5 text-[12.5px] font-medium text-muted transition-colors hover:bg-surface hover:text-ink"
          >
            <Plus className="h-3.5 w-3.5" /> Manage authors
          </a>
        </div>
      )}
      {error && <p className="mt-1 text-[11.5px] font-semibold text-rose-600" role="alert">{error}</p>}
    </div>
  );
}

export default function PostEditor({ post, categories, authors, allTags, products, media, siteUrl }) {
  const isNew = !post;

  const form = useForm({
    title: post?.title ?? '',
    slug: post?.slug ?? '',
    excerpt: post?.excerpt ?? '',
    content: post?.content ?? '',
    category_id: post?.category_id ?? '',
    blog_author_id: post?.blog_author_id ?? '',
    locale: post?.locale ?? 'ms',
    status: post?.status ?? 'draft',
    published_at: post?.published_at ?? '',
    is_featured: post?.is_featured ?? false,
    allow_comments: post?.allow_comments ?? true,
    featured_image_id: post?.featured_image_id ?? '',
    og_image_id: post?.og_image_id ?? '',
    meta_title: post?.meta_title ?? '',
    meta_description: post?.meta_description ?? '',
    focus_keyword: post?.focus_keyword ?? '',
    canonical_url: post?.canonical_url ?? '',
    noindex: post?.noindex ?? false,
    tags: post?.tags ?? [],
    product_ids: post?.product_ids ?? [],
  });

  const { data, setData, errors, processing } = form;

  const [view, setView] = useState('split');
  const [preview, setPreview] = useState({ html: '', report: null, readingTime: 0 });
  const [analysing, setAnalysing] = useState(false);
  const [imageUrl, setImageUrl] = useState(post?.featured_image_url ?? null);
  const [mediaOpen, setMediaOpen] = useState(false);
  const [insertImageOpen, setInsertImageOpen] = useState(false);
  const [productsOpen, setProductsOpen] = useState(false);
  const [tagInput, setTagInput] = useState('');
  const [slugTouched, setSlugTouched] = useState(!isNew);

  const editorRef = useRef(null);

  /* ------------------------------------------------------------------ live analyse
     Rendering and scoring both happen server-side so the editor always agrees
     with what a saved post will score — no duplicate rules in JavaScript. */
  const analyseNow = useCallback(async (payload) => {
    setAnalysing(true);
    try {
      const res = await fetch('/blog-seo/posts/analyze', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken(), Accept: 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify(payload),
      });
      if (!res.ok) throw new Error('Analyse failed');
      setPreview(await res.json());
    } catch {
      // Non-fatal: the editor stays usable, only the live panel goes stale.
    } finally {
      setAnalysing(false);
    }
  }, []);

  const analyseKey = JSON.stringify({
    title: data.title,
    slug: data.slug,
    excerpt: data.excerpt,
    content: data.content,
    meta_title: data.meta_title,
    meta_description: data.meta_description,
    focus_keyword: data.focus_keyword,
    category_id: data.category_id,
    featured_image_id: data.featured_image_id,
    og_image_id: data.og_image_id,
    noindex: data.noindex,
  });

  useEffect(() => {
    const timer = setTimeout(() => analyseNow(JSON.parse(analyseKey)), 450);
    return () => clearTimeout(timer);
  }, [analyseKey, analyseNow]);

  /* ------------------------------------------------------------------ slug */
  useEffect(() => {
    if (slugTouched || !data.title) return undefined;

    const timer = setTimeout(async () => {
      const params = new URLSearchParams({ title: data.title });
      if (post?.id) params.set('id', String(post.id));
      const res = await fetch(`/blog-seo/posts/slugify?${params}`, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
      if (res.ok) setData('slug', (await res.json()).slug);
    }, 400);

    return () => clearTimeout(timer);
  }, [data.title, slugTouched, post?.id, setData]);

  /* ------------------------------------------------------------------ toolbar */
  const replaceSelection = (before, after = '') => {
    const el = editorRef.current;
    if (!el) return;

    const [start, end] = [el.selectionStart, el.selectionEnd];
    const selected = el.value.slice(start, end);
    const next = `${el.value.slice(0, start)}${before}${selected}${after}${el.value.slice(end)}`;

    setData('content', next);

    requestAnimationFrame(() => {
      el.focus();
      const caret = start + before.length + selected.length;
      el.setSelectionRange(caret, caret);
    });
  };

  const applyTool = (tool) => {
    if (tool.picker) {
      setInsertImageOpen(true);
      return;
    }
    replaceSelection(tool.before, tool.after);
  };

  const insertImage = (m) => {
    const alt = (m.alt || m.title || 'image').replace(/[[\]]/g, '');
    replaceSelection(`![${alt}](${m.url})`);
  };

  const applyAlign = (value) => replaceSelection(`\n<div style="text-align: ${value};">\n\n`, `\n\n</div>\n`);

  /* ------------------------------------------------------------------ tags */
  const addTag = (raw) => {
    const name = raw.trim().replace(/,$/, '');
    if (!name || data.tags.includes(name)) return;
    setData('tags', [...data.tags, name]);
  };

  const onTagKeyDown = (e) => {
    if (e.key === 'Enter' || e.key === ',') {
      e.preventDefault();
      addTag(tagInput);
      setTagInput('');
    } else if (e.key === 'Backspace' && !tagInput && data.tags.length) {
      setData('tags', data.tags.slice(0, -1));
    }
  };

  /* ------------------------------------------------------------------ save */
  const submit = (publish = false) => {
    const payload = publish ? { ...data, status: 'published' } : data;
    const options = {
      preserveScroll: true,
      onError: () => toast.error('Please fix the highlighted fields.'),
    };

    if (isNew) {
      router.post('/blog-seo/posts', payload, options);
    } else {
      router.put(`/blog-seo/posts/${post.id}`, payload, options);
    }
  };

  const report = preview.report;
  const titleLen = (data.meta_title || data.title || '').length;
  const descLen = (data.meta_description || '').length;

  return (
    <BlogSeoLayout
      wide
      title={isNew ? 'New post' : 'Edit post'}
      subtitle="Write in Markdown — the preview and SEO score update as you type"
      actions={
        <>
          <Button variant="ghost" href="/blog-seo/posts"><ArrowLeft className="h-4 w-4" /> Posts</Button>
          {post?.publicUrl && (
            <Button variant="secondary" href={post.publicUrl} target="_blank" rel="noopener noreferrer">
              <ExternalLink className="h-4 w-4" /> View live
            </Button>
          )}
          <Button variant="secondary" onClick={() => submit(false)} disabled={processing}>
            <Save className="h-4 w-4" /> {processing ? 'Saving…' : 'Save'}
          </Button>
          <Button variant="primary" onClick={() => submit(true)} disabled={processing}>
            <Rocket className="h-4 w-4" /> Publish
          </Button>
        </>
      }
    >
      <Head title={isNew ? 'New post' : data.title || 'Edit post'} />

      <div className="grid gap-4 xl:grid-cols-12">
        {/* ============================= MAIN ============================= */}
        <div className="space-y-4 xl:col-span-8">
          <Card className="p-5">
            <Field label="Title" error={errors.title}>
              <Input
                value={data.title}
                onChange={(e) => setData('title', e.target.value)}
                placeholder="How to choose the right skincare products"
                className="!text-[16px] !font-semibold"
              />
            </Field>

            <Field label="URL slug" error={errors.slug} className="mt-4"
              hint={`${siteUrl}/blog/${data.slug || 'your-slug'}`}>
              <Input
                value={data.slug}
                onChange={(e) => { setSlugTouched(true); setData('slug', e.target.value); }}
                placeholder="how-to-choose-skincare"
              />
            </Field>

            <Field label="Excerpt" error={errors.excerpt} className="mt-4"
              hint="One or two sentences shown on the blog index card.">
              <Textarea rows={2} value={data.excerpt} onChange={(e) => setData('excerpt', e.target.value)} />
            </Field>
          </Card>

          {/* ---------------- Markdown editor ---------------- */}
          <Card className="overflow-hidden">
            <div className="flex flex-wrap items-center gap-1 border-b border-line bg-surface px-3 py-2">
              {TOOLS.map((tool) => {
                const Icon = tool.icon;
                return (
                  <button
                    key={tool.key}
                    type="button"
                    onClick={() => applyTool(tool)}
                    title={tool.title}
                    aria-label={tool.title}
                    className="grid h-9 w-9 place-items-center rounded-lg text-muted transition-colors hover:bg-white hover:text-emerald-600"
                  >
                    {Icon ? <Icon className="h-4 w-4" strokeWidth={2} /> : (
                      <span className={cn('text-[12px]', tool.bold && 'font-black text-[13px]', tool.italic && 'font-serif italic', !tool.bold && !tool.italic && 'font-bold')}>
                        {tool.text}
                      </span>
                    )}
                  </button>
                );
              })}

              <span className="mx-1 h-5 w-px bg-line" aria-hidden="true" />
              <AlignMenu onPick={applyAlign} />

              <div className="ml-auto flex items-center gap-1 rounded-lg bg-slate-200/70 p-0.5">
                {[
                  { key: 'write', label: 'Write', icon: PenLine },
                  { key: 'split', label: 'Split', icon: Columns2 },
                  { key: 'preview', label: 'Preview', icon: Eye },
                ].map((m) => (
                  <button
                    key={m.key}
                    type="button"
                    onClick={() => setView(m.key)}
                    className={cn(
                      'inline-flex items-center gap-1.5 rounded-md px-2.5 py-1 text-[12px] font-semibold transition-colors',
                      view === m.key ? 'bg-white text-emerald-700 shadow-sm' : 'text-muted hover:text-ink'
                    )}
                  >
                    <m.icon className="h-3.5 w-3.5" /> {m.label}
                  </button>
                ))}
              </div>
            </div>

            <div className={cn('grid', view === 'split' && 'lg:grid-cols-2 lg:divide-x lg:divide-line')}>
              {view !== 'preview' && (
                <div>
                  <label htmlFor="md" className="sr-only">Article content (Markdown)</label>
                  <textarea
                    id="md"
                    ref={editorRef}
                    value={data.content}
                    onChange={(e) => setData('content', e.target.value)}
                    rows={26}
                    spellCheck
                    placeholder={'Write your article in Markdown…\n\n## A section heading\n\nYour paragraph text, with **bold** and [links](https://example.com).'}
                    className="w-full resize-y border-0 bg-transparent px-5 py-4 font-mono text-[13px] leading-relaxed text-ink placeholder:text-muted-2 focus:outline-none focus:ring-0"
                  />
                </div>
              )}

              {view !== 'write' && (
                <div className="scroll-thin max-h-[42rem] overflow-y-auto px-5 py-4">
                  {data.content.trim() === '' ? (
                    <p className="py-16 text-center text-[13px] text-muted-2">Preview appears here as you write.</p>
                  ) : (
                    <div className="md-preview" dangerouslySetInnerHTML={{ __html: preview.html }} />
                  )}
                </div>
              )}
            </div>

            <div className="flex items-center justify-between border-t border-line px-5 py-2 text-[11.5px] text-muted">
              <span>Markdown supported — headings, lists, links, tables, images, code.</span>
              <span className="flex items-center gap-2 tabular-nums">
                {analysing && <Loader2 className="h-3 w-3 animate-spin" />}
                {formatNumber(report?.word_count ?? 0)} words · {preview.readingTime || 0}m read
              </span>
            </div>
          </Card>

          {/* ---------------- Products ---------------- */}
          <Card className="p-5">
            <SectionTitle
              title="Products in this article"
              hint="Shown as a shopping strip under the article"
              action={<Button size="sm" onClick={() => setProductsOpen(true)}><ShoppingBag className="h-3.5 w-3.5" /> Choose</Button>}
            />
            {data.product_ids.length === 0 ? (
              <p className="text-[13px] text-muted">No products attached yet.</p>
            ) : (
              <div className="flex flex-wrap gap-2">
                {data.product_ids.map((id) => {
                  const p = products.find((x) => x.id === id);
                  if (!p) return null;
                  return (
                    <span key={id} className="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 py-1 pl-3 pr-1.5 text-[12px] font-medium text-emerald-800 ring-1 ring-inset ring-emerald-600/20">
                      {p.name}
                      <button
                        type="button"
                        onClick={() => setData('product_ids', data.product_ids.filter((x) => x !== id))}
                        className="grid h-5 w-5 place-items-center rounded-full hover:bg-emerald-100"
                        aria-label={`Remove ${p.name}`}
                      >
                        <X className="h-3 w-3" />
                      </button>
                    </span>
                  );
                })}
              </div>
            )}
          </Card>
        </div>

        {/* ============================= SIDEBAR ============================= */}
        <div className="space-y-4 xl:col-span-4">
          {/* ---------------- SEO score ---------------- */}
          <Card className="overflow-hidden">
            <div className="border-b border-line p-5">
              <ScoreGauge
                score={report?.score ?? 0}
                size={84}
                sublabel={report ? `${report.passed} passed · ${report.warnings} warnings · ${report.failed} failed` : 'Analysing…'}
              />
            </div>

            <div className="scroll-thin max-h-96 overflow-y-auto p-3">
              {!report && <p className="py-8 text-center text-[13px] text-muted">Analysing…</p>}
              {report && Object.entries(report.groups).map(([group, checks]) => (
                <div key={group}>
                  <p className="px-2 pb-1 pt-2 text-[10.5px] font-bold uppercase tracking-wide text-muted-2">
                    {GROUP_LABELS[group] ?? group}
                  </p>
                  <ul className="space-y-0.5">
                    {checks.map((check) => {
                      const Icon = CHECK_ICON[check.status] ?? XCircle;
                      return (
                        <li key={check.id} className="flex gap-2 rounded-lg px-2 py-1.5 hover:bg-surface">
                          <Icon className={cn('mt-0.5 h-4 w-4 shrink-0', checkTone(check.status))} strokeWidth={2.2} />
                          <div className="min-w-0">
                            <p className="text-[12px] font-semibold text-ink">{check.label}</p>
                            <p className="text-[11.5px] leading-snug text-muted">{check.message}</p>
                          </div>
                        </li>
                      );
                    })}
                  </ul>
                </div>
              ))}
            </div>
          </Card>

          {/* ---------------- Publishing ---------------- */}
          <Card className="space-y-3 p-5">
            <SectionTitle title="Publishing" />

            <Field label="Status" error={errors.status}>
              <Select value={data.status} onChange={(e) => setData('status', e.target.value)}>
                <option value="draft">Draft</option>
                <option value="scheduled">Scheduled</option>
                <option value="published">Published</option>
                <option value="archived">Archived</option>
              </Select>
            </Field>

            <Field label="Publish date" error={errors.published_at}>
              <Input type="datetime-local" value={data.published_at || ''} onChange={(e) => setData('published_at', e.target.value)} />
            </Field>

            <Field label="Language" error={errors.locale}>
              <Select value={data.locale} onChange={(e) => setData('locale', e.target.value)}>
                <option value="ms">Bahasa Malaysia</option>
                <option value="en">English</option>
              </Select>
            </Field>

            <Field label="Category" error={errors.category_id}>
              <Select value={data.category_id || ''} onChange={(e) => setData('category_id', e.target.value)}>
                <option value="">— No category —</option>
                {categories.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
              </Select>
            </Field>

            <Field label="Author" hint="Who this article is attributed to. Search or manage the list under Authors.">
              <AuthorPicker
                authors={authors}
                value={data.blog_author_id}
                onChange={(v) => setData('blog_author_id', v)}
                error={errors.blog_author_id}
              />
            </Field>

            <Field label="Tags" hint="Press Enter or comma to add. New tags are created automatically.">
              <div className="flex flex-wrap gap-1.5 rounded-xl bg-white p-2 ring-1 ring-inset ring-line focus-within:ring-2 focus-within:ring-[var(--color-brand)]">
                {data.tags.map((tag) => (
                  <span key={tag} className="inline-flex items-center gap-1 rounded-full bg-slate-100 py-0.5 pl-2.5 pr-1 text-[12px] font-medium text-ink-2">
                    {tag}
                    <button
                      type="button"
                      onClick={() => setData('tags', data.tags.filter((t) => t !== tag))}
                      className="grid h-4 w-4 place-items-center rounded-full hover:bg-slate-200"
                      aria-label={`Remove tag ${tag}`}
                    >
                      <X className="h-2.5 w-2.5" />
                    </button>
                  </span>
                ))}
                <input
                  value={tagInput}
                  onChange={(e) => setTagInput(e.target.value)}
                  onKeyDown={onTagKeyDown}
                  onBlur={() => { addTag(tagInput); setTagInput(''); }}
                  list="all-tags"
                  placeholder={data.tags.length ? '' : 'skincare, guide'}
                  className="min-w-24 flex-1 border-0 bg-transparent px-1 py-0.5 text-[13px] focus:outline-none focus:ring-0"
                  aria-label="Add a tag"
                />
                <datalist id="all-tags">
                  {allTags.map((t) => <option key={t} value={t} />)}
                </datalist>
              </div>
            </Field>

            <div className="space-y-2 pt-1">
              <Switch id="featured" checked={data.is_featured} onChange={(v) => setData('is_featured', v)} label="Feature on the blog index" />
              <Switch id="comments" checked={data.allow_comments} onChange={(v) => setData('allow_comments', v)} label="Allow comments" />
            </div>
          </Card>

          {/* ---------------- Featured image ---------------- */}
          <Card className="p-5">
            <SectionTitle title="Featured image" hint="Index card and social share preview" />
            {imageUrl ? (
              <div className="relative overflow-hidden rounded-xl ring-1 ring-line">
                <img src={imageUrl} alt="" className="aspect-[16/10] w-full object-cover" />
                <button
                  type="button"
                  onClick={() => { setImageUrl(null); setData('featured_image_id', ''); }}
                  className="absolute right-2 top-2 grid h-8 w-8 place-items-center rounded-lg bg-slate-900/70 text-white transition-colors hover:bg-rose-600"
                  aria-label="Remove featured image"
                >
                  <Trash2 className="h-3.5 w-3.5" />
                </button>
              </div>
            ) : (
              <div className="grid aspect-[16/10] place-items-center rounded-xl border border-dashed border-line bg-surface">
                <ImageIcon className="h-7 w-7 text-muted-2" />
              </div>
            )}
            <Button className="mt-2 w-full" onClick={() => setMediaOpen(true)}>
              <ImageIcon className="h-4 w-4" /> {imageUrl ? 'Change image' : 'Choose image'}
            </Button>
          </Card>

          {/* ---------------- Search listing ---------------- */}
          <Card className="p-5">
            <SectionTitle title="Search engine listing" />

            <div className="rounded-xl bg-surface p-3">
              <p className="truncate text-[11.5px] text-emerald-700">{siteUrl}/blog/{data.slug || 'your-slug'}</p>
              <p className="mt-0.5 line-clamp-1 text-[13.5px] font-medium text-blue-700">{data.meta_title || data.title || 'Your post title'}</p>
              <p className="mt-0.5 line-clamp-2 text-[11.5px] leading-snug text-muted">
                {data.meta_description || data.excerpt || 'Your meta description will appear here.'}
              </p>
            </div>

            <div className="mt-4 space-y-3">
              <Field label="Focus keyword" error={errors.focus_keyword} hint="The phrase you want this article to rank for.">
                <Input value={data.focus_keyword} onChange={(e) => setData('focus_keyword', e.target.value)} placeholder="produk penjagaan kulit" />
              </Field>

              <Field
                label="SEO title"
                error={errors.meta_title}
                hint={`${titleLen} / 60 characters`}
                className={titleLen > 60 ? '[&_p]:text-amber-600' : ''}
              >
                <Input value={data.meta_title} onChange={(e) => setData('meta_title', e.target.value)} placeholder="Defaults to the post title" />
              </Field>

              <Field
                label="Meta description"
                error={errors.meta_description}
                hint={`${descLen} / 158 characters`}
                className={descLen > 158 ? '[&_p]:text-amber-600' : ''}
              >
                <Textarea rows={3} value={data.meta_description} onChange={(e) => setData('meta_description', e.target.value)} placeholder="120–158 characters describing the article." />
              </Field>

              <Field label="Canonical URL" error={errors.canonical_url} hint="Leave blank unless this republishes another page.">
                <Input value={data.canonical_url} onChange={(e) => setData('canonical_url', e.target.value)} placeholder="https://…" />
              </Field>

              <Switch id="noindex" checked={data.noindex} onChange={(v) => setData('noindex', v)} label="Hide from search engines (noindex)" />
            </div>
          </Card>
        </div>
      </div>

      <MediaPickerModal
        open={mediaOpen}
        onClose={() => setMediaOpen(false)}
        media={media}
        onPick={(m) => { setImageUrl(m.url); setData('featured_image_id', m.id); }}
      />

      <MediaPickerModal
        open={insertImageOpen}
        onClose={() => setInsertImageOpen(false)}
        media={media}
        onPick={insertImage}
      />

      <ProductPicker
        open={productsOpen}
        onClose={() => setProductsOpen(false)}
        products={products}
        selected={data.product_ids}
        onToggle={(id) => setData('product_ids', data.product_ids.includes(id)
          ? data.product_ids.filter((x) => x !== id)
          : [...data.product_ids, id])}
      />
    </BlogSeoLayout>
  );
}
