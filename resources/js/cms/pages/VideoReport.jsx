import { useCallback, useMemo, useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { Clapperboard, ExternalLink, Loader2, MessageSquare, Send, Trash2, X } from 'lucide-react';
import { deleteVideoComment, fetchVideoReport, fetchVideoReportCell, postVideoComment } from '../lib/api';

const MONTH_LABELS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

const CATEGORY_STYLE = {
    tarik_live: 'bg-violet-50 text-violet-700 border-violet-200',
    engagement: 'bg-emerald-50 text-emerald-700 border-emerald-200',
    tunjuk_buku: 'bg-amber-50 text-amber-700 border-amber-200',
    lakonan: 'bg-sky-50 text-sky-700 border-sky-200',
    podcast: 'bg-rose-50 text-rose-700 border-rose-200',
    __uncat__: 'bg-zinc-100 text-zinc-500 border-zinc-200',
};
const chipFor = (slug) => CATEGORY_STYLE[slug] ?? 'bg-zinc-100 text-zinc-600 border-zinc-200';

const currentYear = new Date().getFullYear();
const currentMonth = new Date().getMonth() + 1;

export default function VideoReport() {
    const [filters, setFilters] = useState({
        program: '',
        year: currentYear,
        from: Math.max(1, currentMonth - 5),
        to: currentMonth,
    });
    const [cell, setCell] = useState({ open: false, loading: false, host: null, category: null, videos: [] });

    const { data, isLoading } = useQuery({
        queryKey: ['cms-video-report', filters],
        queryFn: () => fetchVideoReport({
            program: filters.program || undefined,
            year: filters.year,
            from: filters.from,
            to: filters.to,
        }),
        keepPreviousData: true,
    });

    const programs = data?.programs ?? [];
    const categories = data?.categories ?? [];
    const programOptions = data?.filters?.programOptions ?? [];
    const windowLabel = data?.window?.label ?? '';
    const totalVideos = programs.reduce((sum, p) => sum + p.grand_total, 0);

    const openCell = useCallback(async (host, category) => {
        setCell({ open: true, loading: true, host, category, videos: [] });
        try {
            const res = await fetchVideoReportCell({
                mentee: host.mentee_id,
                category: category.slug,
                year: filters.year,
                from: filters.from,
                to: filters.to,
            });
            setCell({ open: true, loading: false, host: res.host, category: res.category, videos: res.videos });
        } catch {
            setCell((c) => ({ ...c, loading: false }));
        }
    }, [filters.year, filters.from, filters.to]);

    const closeCell = () => setCell({ open: false, loading: false, host: null, category: null, videos: [] });

    const updateVideo = useCallback((updated) => {
        setCell((c) => ({ ...c, videos: c.videos.map((v) => (v.id === updated.id ? updated : v)) }));
    }, []);

    const setYear = (year) => {
        const cap = year === currentYear ? currentMonth : 12;
        setFilters((f) => ({ ...f, year, from: Math.max(1, cap - 5), to: cap }));
    };

    return (
        <div className="p-6">
            <div className="mb-6 flex flex-wrap items-start justify-between gap-4">
                <div className="flex items-start gap-3">
                    <div className="grid h-11 w-11 place-items-center rounded-xl bg-violet-50 text-violet-600">
                        <Clapperboard className="h-5 w-5" />
                    </div>
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight text-zinc-900">Video Report</h1>
                        <p className="mt-0.5 text-sm text-zinc-500">
                            Monitor videos each host logged by category{windowLabel ? ` — ${windowLabel}` : ''}. {totalVideos} videos. Click a number to review.
                        </p>
                    </div>
                </div>

                <div className="flex flex-wrap items-center gap-2">
                    <select
                        value={filters.program}
                        onChange={(e) => setFilters((f) => ({ ...f, program: e.target.value }))}
                        className="h-9 rounded-lg border border-zinc-200 bg-white px-2.5 text-sm text-zinc-800 focus:outline-none focus:ring-2 focus:ring-violet-500/20"
                    >
                        <option value="">All programs</option>
                        {programOptions.map((p) => (
                            <option key={p.id} value={p.id}>{p.title}</option>
                        ))}
                    </select>
                    <div className="flex items-center gap-1.5 rounded-lg border border-zinc-200 bg-white px-1.5 py-1">
                        <select
                            value={filters.year}
                            onChange={(e) => setYear(Number(e.target.value))}
                            className="h-7 rounded-md bg-transparent px-1 text-sm font-medium text-zinc-800 focus:outline-none"
                        >
                            {[currentYear - 2, currentYear - 1, currentYear].map((y) => <option key={y} value={y}>{y}</option>)}
                        </select>
                        <MonthSelect value={filters.from} onChange={(v) => setFilters((f) => ({ ...f, from: v }))} />
                        <span className="text-zinc-400">→</span>
                        <MonthSelect value={filters.to} onChange={(v) => setFilters((f) => ({ ...f, to: v }))} />
                    </div>
                </div>
            </div>

            {isLoading ? (
                <div className="flex items-center justify-center py-24 text-zinc-400">
                    <Loader2 className="h-6 w-6 animate-spin" />
                </div>
            ) : programs.length === 0 ? (
                <div className="rounded-2xl border border-dashed border-zinc-200 bg-white py-20 text-center text-sm text-zinc-400">
                    No active programs to report on.
                </div>
            ) : (
                <div className="flex flex-col gap-5">
                    {programs.map((program) => (
                        <ProgramMatrix key={program.id} program={program} categories={categories} onOpenCell={openCell} />
                    ))}
                </div>
            )}

            <CommentDrawer cell={cell} onClose={closeCell} onVideoUpdated={updateVideo} />
        </div>
    );
}

function MonthSelect({ value, onChange }) {
    return (
        <select
            value={value}
            onChange={(e) => onChange(Number(e.target.value))}
            className="h-7 rounded-md bg-transparent px-1 text-sm text-zinc-800 focus:outline-none"
        >
            {MONTH_LABELS.map((label, i) => <option key={label} value={i + 1}>{label}</option>)}
        </select>
    );
}

function ProgramMatrix({ program, categories, onOpenCell }) {
    return (
        <section className="overflow-hidden rounded-2xl border border-zinc-200 bg-white">
            <div className="flex items-center gap-2.5 border-b border-zinc-100 px-5 py-3.5">
                <div className="grid h-8 w-8 place-items-center rounded-lg bg-violet-50 text-violet-600">
                    <Clapperboard className="h-4 w-4" />
                </div>
                <div>
                    <h2 className="text-sm font-semibold text-zinc-900">{program.title}</h2>
                    <p className="text-xs text-zinc-500">{program.hosts.length} hosts · {program.grand_total} videos</p>
                </div>
            </div>
            <div className="overflow-x-auto">
                <table className="w-full border-collapse text-sm">
                    <thead>
                        <tr className="border-b border-zinc-100 text-[11px] uppercase tracking-wide text-zinc-400">
                            <th className="px-5 py-2.5 text-left font-semibold">Host</th>
                            {categories.map((c) => (
                                <th key={c.slug} className="px-1 py-2.5 text-center font-semibold">
                                    <span className={`inline-block rounded-full border px-2 py-0.5 text-[10px] font-semibold normal-case ${chipFor(c.slug)}`}>
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
                                <td colSpan={categories.length + 2} className="px-5 py-8 text-center text-sm text-zinc-400">
                                    No hosts in this program yet.
                                </td>
                            </tr>
                        )}
                        {program.hosts.map((host) => (
                            <tr key={host.mentee_id} className="border-b border-zinc-50 last:border-0 hover:bg-zinc-50/60">
                                <td className="px-5 py-2">
                                    <div className="flex items-center gap-2.5">
                                        <span className="grid h-7 w-7 flex-shrink-0 place-items-center rounded-full bg-zinc-100 text-[10px] font-semibold text-zinc-600">
                                            {host.initials}
                                        </span>
                                        <div className="min-w-0">
                                            <div className="flex items-center gap-1.5">
                                                <span className="truncate text-[13px] font-medium text-zinc-900">{host.name}</span>
                                                {host.status === 'graduated' && (
                                                    <span className="rounded-full bg-indigo-50 px-1.5 py-0.5 text-[10px] font-semibold text-indigo-600">GRAD</span>
                                                )}
                                            </div>
                                            {(host.commented > 0 || host.awaiting_reply > 0) && (
                                                <div className="flex items-center gap-2 text-[11px] text-zinc-400">
                                                    {host.commented > 0 && (
                                                        <span className="inline-flex items-center gap-1"><MessageSquare className="h-3 w-3" /> {host.commented}</span>
                                                    )}
                                                    {host.awaiting_reply > 0 && (
                                                        <span className="rounded-full bg-rose-50 px-1.5 py-0.5 font-semibold text-rose-600">{host.awaiting_reply} awaiting reply</span>
                                                    )}
                                                </div>
                                            )}
                                        </div>
                                    </div>
                                </td>
                                {categories.map((c) => {
                                    const value = host.counts[c.slug] ?? 0;
                                    return (
                                        <td key={c.slug} className="px-1 py-1 text-center">
                                            <button
                                                type="button"
                                                onClick={() => onOpenCell(host, c)}
                                                disabled={!value}
                                                className={`mx-auto flex h-9 w-full min-w-[44px] items-center justify-center rounded-lg text-[13px] font-semibold transition ${
                                                    value ? `${chipFor(c.slug)} border hover:brightness-95` : 'cursor-default text-zinc-300'
                                                }`}
                                            >
                                                {value || '–'}
                                            </button>
                                        </td>
                                    );
                                })}
                                <td className="px-3 py-2 text-center">
                                    <button
                                        type="button"
                                        onClick={() => onOpenCell(host, { slug: '', label: 'All categories' })}
                                        disabled={!host.total}
                                        className={`mx-auto flex h-9 min-w-[44px] items-center justify-center rounded-lg px-2 text-[13px] font-bold transition ${
                                            host.total ? 'bg-zinc-900 text-white hover:bg-zinc-700' : 'cursor-default text-zinc-300'
                                        }`}
                                    >
                                        {host.total || '–'}
                                    </button>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                    {program.hosts.length > 0 && (
                        <tfoot>
                            <tr className="border-t border-zinc-200 bg-zinc-50 text-[12px] font-semibold text-zinc-600">
                                <td className="px-5 py-2.5">Total</td>
                                {categories.map((c) => (
                                    <td key={c.slug} className="px-1 py-2.5 text-center">{program.totals[c.slug] || '–'}</td>
                                ))}
                                <td className="px-3 py-2.5 text-center text-zinc-900">{program.grand_total}</td>
                            </tr>
                        </tfoot>
                    )}
                </table>
            </div>
        </section>
    );
}

function CommentDrawer({ cell, onClose, onVideoUpdated }) {
    if (!cell.open) return null;
    return (
        <div className="fixed inset-0 z-50 flex justify-end">
            <div className="absolute inset-0 bg-black/30" onClick={onClose} />
            <div className="relative flex h-full w-full max-w-md flex-col bg-zinc-50 shadow-xl">
                <div className="flex items-center justify-between border-b border-zinc-200 bg-white px-5 py-4">
                    <div className="min-w-0">
                        <h3 className="truncate text-[15px] font-semibold text-zinc-900">{cell.host?.name}</h3>
                        <p className="text-xs text-zinc-500">{cell.category?.label}</p>
                    </div>
                    <button type="button" onClick={onClose} className="rounded-lg p-1.5 text-zinc-500 hover:bg-zinc-100">
                        <X className="h-5 w-5" />
                    </button>
                </div>
                <div className="flex-1 space-y-3 overflow-y-auto p-4">
                    {cell.loading && (
                        <div className="flex items-center justify-center py-16 text-zinc-400"><Loader2 className="h-5 w-5 animate-spin" /></div>
                    )}
                    {!cell.loading && cell.videos.length === 0 && (
                        <div className="py-16 text-center text-sm text-zinc-400">No videos in this cell.</div>
                    )}
                    {!cell.loading && cell.videos.map((v) => <VideoCard key={v.id} video={v} onUpdated={onVideoUpdated} />)}
                </div>
            </div>
        </div>
    );
}

function VideoCard({ video, onUpdated }) {
    const [body, setBody] = useState('');
    const [busy, setBusy] = useState(false);

    const submit = async () => {
        const text = body.trim();
        if (!text || busy) return;
        setBusy(true);
        try {
            const res = await postVideoComment(video.id, text);
            onUpdated(res.video);
            setBody('');
        } catch {
            /* keep the draft so the user can retry */
        } finally {
            setBusy(false);
        }
    };

    const remove = async (commentId) => {
        if (!window.confirm('Delete this feedback?')) return;
        try {
            await deleteVideoComment(commentId);
            onUpdated({ ...video, comments: video.comments.filter((c) => c.id !== commentId) });
        } catch {
            /* ignore */
        }
    };

    return (
        <div className="rounded-xl border border-zinc-200 bg-white p-4">
            <div className="flex items-start justify-between gap-3">
                <div className="min-w-0">
                    <div className="flex flex-wrap items-center gap-2">
                        <span className={`rounded-full border px-2 py-0.5 text-[10px] font-semibold ${chipFor(video.category ?? '__uncat__')}`}>
                            {video.category_label ?? 'Uncategorised'}
                        </span>
                        <span className="text-[11.5px] text-zinc-400">{video.date_label}</span>
                    </div>
                    <h4 className="mt-1.5 text-sm font-semibold text-zinc-900">{video.title || 'Untitled video'}</h4>
                </div>
                {video.link && (
                    <a
                        href={video.link}
                        target="_blank"
                        rel="noreferrer"
                        className="inline-flex flex-shrink-0 items-center gap-1 rounded-lg border border-zinc-200 px-2.5 py-1.5 text-xs font-medium text-zinc-600 hover:bg-zinc-50"
                    >
                        <ExternalLink className="h-3.5 w-3.5" /> Open
                    </a>
                )}
            </div>

            <div className="mt-3 flex flex-col gap-3 border-t border-zinc-100 pt-3">
                {video.comments.length === 0 ? (
                    <p className="text-xs text-zinc-400">No feedback yet — add the first one below.</p>
                ) : (
                    video.comments.map((c) => (
                        <div key={c.id} className={`group flex gap-2.5 ${c.is_host ? '' : 'flex-row-reverse'}`}>
                            <span className={`grid h-7 w-7 flex-shrink-0 place-items-center rounded-full text-[10px] font-semibold ${c.is_host ? 'bg-emerald-100 text-emerald-700' : 'bg-violet-100 text-violet-700'}`}>
                                {c.author.initials}
                            </span>
                            <div className={`flex max-w-[80%] flex-col ${c.is_host ? 'items-start' : 'items-end'}`}>
                                <div className={`rounded-2xl px-3 py-2 text-[13px] leading-relaxed ${c.is_host ? 'rounded-tl-sm bg-emerald-50 text-emerald-900' : 'rounded-tr-sm bg-violet-50 text-violet-900'}`}>
                                    {c.body}
                                </div>
                                <div className="mt-1 flex items-center gap-1.5 px-1 text-[10.5px] text-zinc-400">
                                    <span>{c.author.name}{c.is_host ? ' · Host' : ''} · {c.created_human}</span>
                                    {c.can_delete && (
                                        <button type="button" onClick={() => remove(c.id)} title="Delete" className="opacity-0 transition hover:text-rose-500 group-hover:opacity-100">
                                            <Trash2 className="h-3 w-3" />
                                        </button>
                                    )}
                                </div>
                            </div>
                        </div>
                    ))
                )}

                {/* Inline feedback composer — give the host feedback right here */}
                <div className="mt-1 flex items-end gap-2">
                    <textarea
                        value={body}
                        onChange={(e) => setBody(e.target.value)}
                        onKeyDown={(e) => { if (e.key === 'Enter' && (e.metaKey || e.ctrlKey)) { e.preventDefault(); submit(); } }}
                        rows={2}
                        placeholder="Write feedback for this video…"
                        className="min-h-[38px] flex-1 resize-y rounded-lg border border-zinc-200 bg-white px-3 py-2 text-[13px] text-zinc-800 placeholder:text-zinc-400 focus:border-violet-400 focus:outline-none focus:ring-2 focus:ring-violet-500/15"
                    />
                    <button
                        type="button"
                        onClick={submit}
                        disabled={busy || !body.trim()}
                        className="inline-flex h-[38px] flex-shrink-0 items-center gap-1.5 rounded-lg bg-violet-600 px-3 text-[13px] font-semibold text-white transition hover:bg-violet-700 disabled:opacity-40"
                    >
                        {busy ? <Loader2 className="h-3.5 w-3.5 animate-spin" /> : <Send className="h-3.5 w-3.5" />}
                        Send
                    </button>
                </div>
            </div>
        </div>
    );
}
