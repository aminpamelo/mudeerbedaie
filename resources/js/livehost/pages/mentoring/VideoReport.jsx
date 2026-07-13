import { Head, router, usePage } from '@inertiajs/react';
import { useCallback, useEffect, useMemo, useState } from 'react';
import { Clapperboard, ExternalLink, MessageSquare, Send, Trash2, X, Loader2 } from 'lucide-react';
import LiveHostLayout, { TopBar } from '@/livehost/layouts/LiveHostLayout';

const MONTH_LABELS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

// Subtle per-category accent so the 5 columns read as distinct content types.
const CATEGORY_STYLE = {
  tarik_live: { chip: 'bg-violet-50 text-violet-700 border-violet-200', dot: 'bg-violet-500' },
  engagement: { chip: 'bg-emerald-50 text-emerald-700 border-emerald-200', dot: 'bg-emerald-500' },
  tunjuk_buku: { chip: 'bg-amber-50 text-amber-700 border-amber-200', dot: 'bg-amber-500' },
  lakonan: { chip: 'bg-sky-50 text-sky-700 border-sky-200', dot: 'bg-sky-500' },
  podcast: { chip: 'bg-rose-50 text-rose-700 border-rose-200', dot: 'bg-rose-500' },
};

const styleFor = (slug) => CATEGORY_STYLE[slug] ?? { chip: 'bg-zinc-100 text-zinc-600 border-zinc-200', dot: 'bg-zinc-400' };

function csrf() {
  return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

async function apiGet(url) {
  const res = await fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
  if (!res.ok) throw new Error(`HTTP ${res.status}`);
  return res.json();
}

async function apiSend(url, method, body) {
  const res = await fetch(url, {
    method,
    credentials: 'same-origin',
    headers: {
      Accept: 'application/json',
      'Content-Type': 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
      'X-CSRF-TOKEN': csrf(),
    },
    body: body ? JSON.stringify(body) : undefined,
  });
  if (!res.ok) throw new Error(`HTTP ${res.status}`);
  return res.status === 204 ? null : res.json();
}

/* ---------------- Filters ---------------- */
function Filters({ window: win, filters }) {
  const { year, from, to, years } = win;
  const currentYear = new Date().getFullYear();
  const currentMonth = new Date().getMonth() + 1;

  const apply = (params) => {
    router.get(
      window.location.pathname,
      { program: filters.program ?? undefined, year, from, to, ...params },
      { only: ['programs', 'window', 'filters'], preserveState: true, preserveScroll: true, replace: true },
    );
  };

  const monthField = (value, key) => (
    <select
      value={value}
      onChange={(e) => apply({ [key]: Number(e.target.value) })}
      className="h-9 rounded-lg border border-[#EAEAEA] bg-white px-2 text-[13px] text-[#0A0A0A] focus:outline-none focus:ring-2 focus:ring-[#10B981]/20"
    >
      {MONTH_LABELS.map((label, i) => (
        <option key={label} value={i + 1}>{label}</option>
      ))}
    </select>
  );

  return (
    <div className="flex flex-wrap items-center gap-2">
      <select
        value={filters.program ?? ''}
        onChange={(e) => apply({ program: e.target.value || undefined })}
        className="h-9 rounded-lg border border-[#EAEAEA] bg-white px-2.5 text-[13px] text-[#0A0A0A] focus:outline-none focus:ring-2 focus:ring-[#10B981]/20"
      >
        <option value="">All programs</option>
        {filters.programOptions.map((p) => (
          <option key={p.id} value={p.id}>{p.title}</option>
        ))}
      </select>

      <div className="flex items-center gap-1.5 rounded-lg border border-[#EAEAEA] bg-white px-1.5 py-1">
        <select
          value={year}
          onChange={(e) => {
            const y = Number(e.target.value);
            const cap = y === currentYear ? currentMonth : 12;
            apply({ year: y, from: Math.max(1, cap - 5), to: cap });
          }}
          className="h-7 rounded-md bg-transparent px-1 text-[13px] font-medium text-[#0A0A0A] focus:outline-none"
        >
          {years.map((y) => <option key={y} value={y}>{y}</option>)}
        </select>
        {monthField(from, 'from')}
        <span className="text-[#A3A3A3]">→</span>
        {monthField(to, 'to')}
      </div>
    </div>
  );
}

/* ---------------- Matrix ---------------- */
function StatusPill({ status }) {
  if (status === 'graduated') {
    return <span className="rounded-full bg-indigo-50 px-1.5 py-0.5 text-[10px] font-semibold text-indigo-600">GRAD</span>;
  }
  return null;
}

function CountCell({ value, slug, onClick }) {
  const empty = !value;
  return (
    <td className="px-1 py-1 text-center">
      <button
        type="button"
        onClick={onClick}
        disabled={empty}
        className={[
          'mx-auto flex h-9 w-full min-w-[44px] items-center justify-center rounded-lg text-[13px] font-semibold transition-colors',
          empty
            ? 'cursor-default text-[#D4D4D4]'
            : `${styleFor(slug).chip} border hover:brightness-95`,
        ].join(' ')}
      >
        {empty ? '–' : value}
      </button>
    </td>
  );
}

function ProgramMatrix({ program, categories, onOpenCell }) {
  return (
    <section className="overflow-hidden rounded-2xl border border-[#EAEAEA] bg-white shadow-[0_1px_2px_rgba(0,0,0,0.04)]">
      <div className="flex items-center justify-between border-b border-[#F0F0F0] px-5 py-3.5">
        <div className="flex items-center gap-2.5">
          <div className="grid h-8 w-8 place-items-center rounded-lg bg-[#F5F3FF] text-violet-600">
            <Clapperboard className="h-4 w-4" strokeWidth={2} />
          </div>
          <div>
            <h2 className="text-[14px] font-semibold tracking-[-0.01em] text-[#0A0A0A]">{program.title}</h2>
            <p className="text-[11.5px] text-[#737373]">{program.hosts.length} hosts · {program.grand_total} videos</p>
          </div>
        </div>
      </div>

      <div className="overflow-x-auto">
        <table className="w-full border-collapse text-sm">
          <thead>
            <tr className="border-b border-[#F0F0F0] text-[11px] uppercase tracking-wide text-[#8A8A8A]">
              <th className="sticky left-0 z-10 bg-white px-5 py-2.5 text-left font-semibold">Host</th>
              {categories.map((c) => (
                <th key={c.slug} className="px-1 py-2.5 text-center font-semibold">
                  <span className={`inline-block rounded-full border px-2 py-0.5 text-[10px] font-semibold normal-case ${styleFor(c.slug).chip}`}>
                    {c.label}
                  </span>
                </th>
              ))}
              <th className="px-3 py-2.5 text-center font-semibold">Total</th>
            </tr>
          </thead>
          <tbody>
            {program.hosts.length === 0 && (
              <tr>
                <td colSpan={categories.length + 2} className="px-5 py-8 text-center text-[13px] text-[#A3A3A3]">
                  No hosts in this program yet.
                </td>
              </tr>
            )}
            {program.hosts.map((host) => (
              <tr key={host.mentee_id} className="border-b border-[#F5F5F5] last:border-0 hover:bg-[#FAFAFA]">
                <td className="sticky left-0 z-10 bg-white px-5 py-2 hover:bg-[#FAFAFA]">
                  <div className="flex items-center gap-2.5">
                    <span className="grid h-7 w-7 flex-shrink-0 place-items-center rounded-full bg-[#F0F0F0] text-[10px] font-semibold text-[#525252]">
                      {host.initials}
                    </span>
                    <div className="min-w-0">
                      <div className="flex items-center gap-1.5">
                        <span className="truncate text-[13px] font-medium text-[#0A0A0A]">{host.name}</span>
                        <StatusPill status={host.status} />
                      </div>
                      <div className="flex items-center gap-2 text-[11px] text-[#A3A3A3]">
                        {host.commented > 0 && (
                          <span className="inline-flex items-center gap-1">
                            <MessageSquare className="h-3 w-3" /> {host.commented}
                          </span>
                        )}
                        {host.awaiting_reply > 0 && (
                          <span className="inline-flex items-center gap-1 rounded-full bg-rose-50 px-1.5 py-0.5 font-semibold text-rose-600">
                            {host.awaiting_reply} awaiting reply
                          </span>
                        )}
                      </div>
                    </div>
                  </div>
                </td>
                {categories.map((c) => (
                  <CountCell
                    key={c.slug}
                    value={host.counts[c.slug]}
                    slug={c.slug}
                    onClick={() => onOpenCell(host, c)}
                  />
                ))}
                <td className="px-3 py-2 text-center">
                  <button
                    type="button"
                    onClick={() => onOpenCell(host, { slug: '', label: 'All categories' })}
                    disabled={!host.total}
                    className={[
                      'mx-auto flex h-9 min-w-[44px] items-center justify-center rounded-lg px-2 text-[13px] font-bold transition-colors',
                      host.total ? 'bg-[#0A0A0A] text-white hover:bg-[#262626]' : 'cursor-default text-[#D4D4D4]',
                    ].join(' ')}
                  >
                    {host.total || '–'}
                  </button>
                </td>
              </tr>
            ))}
          </tbody>
          {program.hosts.length > 0 && (
            <tfoot>
              <tr className="border-t border-[#EAEAEA] bg-[#FAFAFA] text-[12px] font-semibold text-[#525252]">
                <td className="sticky left-0 z-10 bg-[#FAFAFA] px-5 py-2.5">Total</td>
                {categories.map((c) => (
                  <td key={c.slug} className="px-1 py-2.5 text-center">{program.totals[c.slug] || '–'}</td>
                ))}
                <td className="px-3 py-2.5 text-center text-[#0A0A0A]">{program.grand_total}</td>
              </tr>
            </tfoot>
          )}
        </table>
      </div>
    </section>
  );
}

/* ---------------- Comment thread ---------------- */
function CommentBubble({ comment, onDelete }) {
  const host = comment.is_host;
  return (
    <div className={`flex gap-2.5 ${host ? '' : 'flex-row-reverse'}`}>
      <span className={`grid h-7 w-7 flex-shrink-0 place-items-center rounded-full text-[10px] font-semibold ${host ? 'bg-emerald-100 text-emerald-700' : 'bg-violet-100 text-violet-700'}`}>
        {comment.author.initials}
      </span>
      <div className={`group max-w-[80%] ${host ? 'items-start' : 'items-end'} flex flex-col`}>
        <div className={`rounded-2xl px-3 py-2 text-[13px] leading-relaxed ${host ? 'rounded-tl-sm bg-emerald-50 text-emerald-900' : 'rounded-tr-sm bg-violet-50 text-violet-900'}`}>
          {comment.body}
        </div>
        <div className="mt-1 flex items-center gap-2 px-1 text-[10.5px] text-[#A3A3A3]">
          <span className="font-medium text-[#737373]">{comment.author.name}{host ? ' · Host' : ''}</span>
          <span>{comment.created_human}</span>
          {comment.can_delete && (
            <button
              type="button"
              onClick={() => onDelete(comment)}
              className="opacity-0 transition-opacity hover:text-rose-500 group-hover:opacity-100"
            >
              <Trash2 className="h-3 w-3" />
            </button>
          )}
        </div>
      </div>
    </div>
  );
}

function VideoCard({ video, onPost, onDelete }) {
  const [body, setBody] = useState('');
  const [sending, setSending] = useState(false);
  const style = styleFor(video.category);

  const submit = async () => {
    const text = body.trim();
    if (!text || sending) return;
    setSending(true);
    try {
      await onPost(video.id, text);
      setBody('');
    } finally {
      setSending(false);
    }
  };

  return (
    <div className="rounded-xl border border-[#EAEAEA] bg-white p-4">
      <div className="flex items-start justify-between gap-3">
        <div className="min-w-0">
          <div className="flex flex-wrap items-center gap-2">
            <span className={`rounded-full border px-2 py-0.5 text-[10px] font-semibold ${style.chip}`}>
              {video.category_label ?? 'Uncategorised'}
            </span>
            <span className="text-[11.5px] text-[#A3A3A3]">{video.date_label}</span>
          </div>
          <h4 className="mt-1.5 text-[14px] font-semibold text-[#0A0A0A]">{video.title || 'Untitled video'}</h4>
        </div>
        {video.link && (
          <a
            href={video.link}
            target="_blank"
            rel="noreferrer"
            className="inline-flex flex-shrink-0 items-center gap-1 rounded-lg border border-[#EAEAEA] px-2.5 py-1.5 text-[12px] font-medium text-[#525252] hover:bg-[#FAFAFA]"
          >
            <ExternalLink className="h-3.5 w-3.5" /> Open
          </a>
        )}
      </div>

      <div className="mt-3 flex flex-col gap-3">
        {video.comments.length === 0 && (
          <p className="text-[12px] text-[#A3A3A3]">No feedback yet. Be the first to comment.</p>
        )}
        {video.comments.map((c) => (
          <CommentBubble key={c.id} comment={c} onDelete={onDelete} />
        ))}
      </div>

      <div className="mt-3 flex items-end gap-2 border-t border-[#F5F5F5] pt-3">
        <textarea
          value={body}
          onChange={(e) => setBody(e.target.value)}
          onKeyDown={(e) => { if (e.key === 'Enter' && (e.metaKey || e.ctrlKey)) submit(); }}
          rows={1}
          placeholder="Write feedback for this video…"
          className="min-h-[38px] flex-1 resize-none rounded-lg border border-[#EAEAEA] bg-white px-3 py-2 text-[13px] text-[#0A0A0A] placeholder:text-[#A3A3A3] focus:outline-none focus:ring-2 focus:ring-[#10B981]/20"
        />
        <button
          type="button"
          onClick={submit}
          disabled={!body.trim() || sending}
          className="inline-flex h-[38px] items-center gap-1.5 rounded-lg bg-[#10B981] px-3 text-[13px] font-semibold text-white transition-colors hover:bg-[#059669] disabled:opacity-40"
        >
          {sending ? <Loader2 className="h-4 w-4 animate-spin" /> : <Send className="h-4 w-4" />}
          Send
        </button>
      </div>
    </div>
  );
}

function CommentDrawer({ cell, onClose, onPost, onDelete }) {
  if (!cell.open) return null;
  return (
    <div className="fixed inset-0 z-50 flex justify-end">
      <div className="absolute inset-0 bg-black/30" onClick={onClose} />
      <div className="relative flex h-full w-full max-w-md flex-col bg-[#FBFBFB] shadow-xl">
        <div className="flex items-center justify-between border-b border-[#EAEAEA] bg-white px-5 py-4">
          <div className="min-w-0">
            <h3 className="truncate text-[15px] font-semibold text-[#0A0A0A]">{cell.host?.name}</h3>
            <p className="text-[12px] text-[#737373]">{cell.category?.label}</p>
          </div>
          <button type="button" onClick={onClose} className="rounded-lg p-1.5 text-[#737373] hover:bg-[#F5F5F5]">
            <X className="h-5 w-5" />
          </button>
        </div>

        <div className="flex-1 space-y-3 overflow-y-auto p-4">
          {cell.loading && (
            <div className="flex items-center justify-center py-16 text-[#A3A3A3]">
              <Loader2 className="h-5 w-5 animate-spin" />
            </div>
          )}
          {!cell.loading && cell.videos.length === 0 && (
            <div className="py-16 text-center text-[13px] text-[#A3A3A3]">No videos in this cell.</div>
          )}
          {!cell.loading && cell.videos.map((v) => (
            <VideoCard key={v.id} video={v} onPost={onPost} onDelete={onDelete} />
          ))}
        </div>
      </div>
    </div>
  );
}

/* ---------------- Page ---------------- */
export default function VideoReport() {
  const { programs, categories, filters, window: win } = usePage().props;
  const [cell, setCell] = useState({ open: false, loading: false, host: null, category: null, videos: [] });

  const winQuery = useMemo(
    () => `year=${win.year}&from=${win.from}&to=${win.to}`,
    [win.year, win.from, win.to],
  );

  const openCell = useCallback(async (host, category) => {
    setCell({ open: true, loading: true, host, category, videos: [] });
    try {
      const data = await apiGet(
        `/livehost/mentoring/video-report/cell?mentee=${host.mentee_id}&category=${category.slug}&${winQuery}`,
      );
      setCell({ open: true, loading: false, host: data.host, category: data.category, videos: data.videos });
    } catch {
      setCell((c) => ({ ...c, loading: false }));
    }
  }, [winQuery]);

  const closeCell = () => setCell({ open: false, loading: false, host: null, category: null, videos: [] });

  const replaceVideo = (updated) =>
    setCell((c) => ({ ...c, videos: c.videos.map((v) => (v.id === updated.id ? updated : v)) }));

  const postComment = useCallback(async (videoId, body) => {
    const data = await apiSend(`/livehost/mentoring/videos/${videoId}/comments`, 'POST', { body });
    if (data?.video) replaceVideo(data.video);
    router.reload({ only: ['programs'], preserveScroll: true, preserveState: true });
  }, []);

  const deleteComment = useCallback(async (comment) => {
    await apiSend(`/livehost/mentoring/video-comments/${comment.id}`, 'DELETE');
    setCell((c) => ({
      ...c,
      videos: c.videos.map((v) => ({ ...v, comments: v.comments.filter((x) => x.id !== comment.id) })),
    }));
    router.reload({ only: ['programs'], preserveScroll: true, preserveState: true });
  }, []);

  const totalVideos = programs.reduce((sum, p) => sum + p.grand_total, 0);

  return (
    <>
      <Head title="Video Report" />
      <TopBar
        breadcrumb={['Live Host Desk', 'Mentoring', 'Video Report']}
        actions={<Filters window={win} filters={filters} />}
      />

      <div className="mx-auto flex max-w-6xl flex-col gap-5 px-4 py-6 sm:px-6">
        <div className="flex items-start gap-3">
          <div className="grid h-11 w-11 place-items-center rounded-xl bg-[#F5F3FF] text-violet-600">
            <Clapperboard className="h-5 w-5" strokeWidth={2} />
          </div>
          <div>
            <h1 className="text-[24px] font-bold tracking-[-0.02em] text-[#0A0A0A]">Video Report</h1>
            <p className="mt-0.5 text-[13.5px] text-[#737373]">
              Videos each host logged by category — {win.label}. {totalVideos} videos. Click a number to review and comment.
            </p>
          </div>
        </div>

        {programs.length === 0 && (
          <div className="rounded-2xl border border-dashed border-[#EAEAEA] bg-white py-16 text-center text-[14px] text-[#A3A3A3]">
            No active programs to report on.
          </div>
        )}

        {programs.map((program) => (
          <ProgramMatrix
            key={program.id}
            program={program}
            categories={categories}
            onOpenCell={openCell}
          />
        ))}
      </div>

      <CommentDrawer cell={cell} onClose={closeCell} onPost={postComment} onDelete={deleteComment} />
    </>
  );
}

VideoReport.layout = (page) => <LiveHostLayout>{page}</LiveHostLayout>;
