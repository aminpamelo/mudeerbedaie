import { Link } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { createPortal } from 'react-dom';
import { ChevronRight, ExternalLink, Loader2, MessageSquare, X } from 'lucide-react';

/**
 * Pocket design tokens, mirrored from the `.pocket-shell` scope in pocket.css.
 * The sheet is portalled to <body> (escaping any transformed ancestor that
 * would trap its fixed positioning), which also escapes the token scope — so we
 * re-declare them inline. The Pocket ships light-only, so these are stable.
 */
const POCKET_TOKENS = {
  '--app-bg': '#F8F7FA',
  '--app-bg-2': '#FFFFFF',
  '--app-bg-3': '#F1EEF7',
  '--fg': '#14101F',
  '--fg-2': 'rgba(20, 16, 31, 0.60)',
  '--fg-3': 'rgba(20, 16, 31, 0.40)',
  '--hair': 'rgba(20, 16, 31, 0.08)',
  '--hair-2': 'rgba(20, 16, 31, 0.14)',
  '--accent': '#7C3AED',
  '--accent-soft': 'rgba(124, 58, 237, 0.10)',
};

const CATEGORY_LABELS = { lateness: 'Lateness', absence: 'Absence', rule_violation: 'Rule violation', misconduct: 'Misconduct', other: 'Other' };

/** Ringgit value with 2 decimals, e.g. "RM 2,909.00". */
function rm(n) {
  return `RM ${Number(n).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

/** A video link stripped of its protocol/trailing slash for a compact display. */
function prettyLink(url) {
  return String(url).replace(/^https?:\/\//, '').replace(/\/+$/, '');
}

/**
 * The tap-a-date detail sheet — a bottom sheet portalled to <body> (so no
 * blurred/transformed ancestor can trap its fixed positioning). Fetches the
 * day's full breakdown on open: sales, sessions, the video(s) logged (with any
 * link the host attached + their feedback threads), PIC comments, and any
 * conduct. Shared by the My Path calendar and the Today "Video content" card.
 */
export default function DayDetailSheet({ date, onClose }) {
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(false);

  useEffect(() => {
    let alive = true;
    setLoading(true);
    setError(false);
    fetch(`/live-host/my-path/day?date=${date}`, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
      .then((r) => { if (!r.ok) { throw new Error('failed'); } return r.json(); })
      .then((d) => { if (alive) { setData(d); setLoading(false); } })
      .catch(() => { if (alive) { setError(true); setLoading(false); } });
    return () => { alive = false; };
  }, [date]);

  useEffect(() => {
    const onKey = (e) => { if (e.key === 'Escape') { onClose(); } };
    document.addEventListener('keydown', onKey);
    const prevOverflow = document.body.style.overflow;
    document.body.style.overflow = 'hidden';
    return () => {
      document.removeEventListener('keydown', onKey);
      document.body.style.overflow = prevOverflow;
    };
  }, [onClose]);

  return createPortal(
    <div className="fixed inset-0 z-[60] flex items-end justify-center" style={POCKET_TOKENS} role="dialog" aria-modal="true">
      <button type="button" aria-label="Close" onClick={onClose} className="absolute inset-0 bg-black/40" />
      <div className="relative z-10 max-h-[85vh] w-full max-w-[480px] overflow-y-auto rounded-t-[22px] border border-[var(--hair)] bg-[var(--app-bg)] pb-[calc(env(safe-area-inset-bottom)+16px)] shadow-[0_-8px_40px_rgba(0,0,0,0.18)]">
        <div className="sticky top-0 z-10 flex items-center justify-between border-b border-[var(--hair)] bg-[var(--app-bg)] px-4 pt-3 pb-3">
          <div className="min-w-0">
            <div className="font-mono text-[9px] font-bold uppercase tracking-[0.14em] text-[var(--fg-3)]">Day detail</div>
            <div className="truncate font-display text-[16px] font-medium tracking-[-0.02em] text-[var(--fg)]">{data?.date_human ?? '…'}</div>
          </div>
          <button type="button" onClick={onClose} aria-label="Close" className="grid h-8 w-8 shrink-0 place-items-center rounded-full border border-[var(--hair-2)] text-[var(--fg-2)] transition active:scale-95">
            <X className="h-4 w-4" strokeWidth={2.2} />
          </button>
        </div>

        <div className="px-4 pt-3">
          {loading && <div className="flex justify-center py-12"><Loader2 className="h-5 w-5 animate-spin text-[var(--fg-3)]" /></div>}
          {error && <div className="py-12 text-center text-[13px] text-[var(--fg-2)]">Couldn’t load this day. Tap outside to close.</div>}
          {data && !loading && !error && <DayDetailBody data={data} />}
        </div>
      </div>
    </div>,
    document.body,
  );
}

function DayDetailBody({ data }) {
  return (
    <div className="space-y-4 pb-2">
      <div className="grid grid-cols-2 gap-2">
        <div className="rounded-[14px] border border-[var(--hair)] bg-[var(--app-bg-2)] px-3 py-2.5">
          <div className="font-mono text-[9px] font-bold uppercase tracking-[0.14em] text-[var(--fg-3)]">Sales</div>
          <div className="mt-1 font-display text-[18px] font-medium tabular-nums text-[var(--fg)]">{rm(data.sales)}</div>
        </div>
        <div className="rounded-[14px] border border-[var(--hair)] bg-[var(--app-bg-2)] px-3 py-2.5">
          <div className="font-mono text-[9px] font-bold uppercase tracking-[0.14em] text-[var(--fg-3)]">Sessions</div>
          <div className="mt-1 font-display text-[18px] font-medium tabular-nums text-[var(--fg)]">{data.sessions}</div>
        </div>
      </div>

      <div>
        <div className="mb-2 flex items-baseline justify-between">
          <div className="font-mono text-[10px] font-bold uppercase tracking-[0.14em] text-[var(--fg-3)]">Videos · {data.video_count}</div>
          {data.video_target ? <span className="font-mono text-[9.5px] text-[var(--fg-3)]">Month KPI {data.video_target}</span> : null}
        </div>
        {data.videos.length === 0 ? (
          <div className="rounded-[14px] border border-dashed border-[var(--hair-2)] bg-[var(--app-bg-2)] px-3 py-4 text-center text-[12px] text-[var(--fg-3)]">
            No video logged on this day.
          </div>
        ) : (
          <div className="space-y-2">
            {data.videos.map((v) => <DayVideoRow key={v.id} video={v} />)}
          </div>
        )}
      </div>

      {data.comments.length > 0 && (
        <div>
          <div className="mb-2 font-mono text-[10px] font-bold uppercase tracking-[0.14em] text-[var(--fg-3)]">Coach comments</div>
          <div className="space-y-2">
            {data.comments.map((c, i) => (
              <div key={i} className="rounded-[14px] border border-[var(--hair)] bg-[var(--app-bg-2)] px-3 py-2.5">
                {c.by && <div className="mb-0.5 text-[10px] font-semibold text-[var(--fg-3)]">{c.by}</div>}
                <p className="whitespace-pre-wrap text-[13px] leading-snug text-[var(--fg)]">{c.comment}</p>
              </div>
            ))}
          </div>
        </div>
      )}

      {data.conduct.length > 0 && (
        <div>
          <div className="mb-2 font-mono text-[10px] font-bold uppercase tracking-[0.14em] text-[var(--fg-3)]">Conduct</div>
          <div className="overflow-hidden rounded-[14px] border border-[#F0C8C8] bg-[#FEF7F7]">
            <ul className="divide-y divide-[#F5DADA]">
              {data.conduct.map((r, i) => (
                <li key={i} className="px-3 py-2.5">
                  <div className="flex flex-wrap items-center gap-2">
                    <span className={`inline-flex items-center rounded-md px-1.5 py-0.5 text-[9.5px] font-bold uppercase tracking-wide ${r.severity === 'major' ? 'bg-[#FEE2E2] text-[#B91C1C]' : 'bg-[#FEF3C7] text-[#B45309]'}`}>{r.severity}</span>
                    <span className="text-[12.5px] font-semibold text-[var(--fg)]">{CATEGORY_LABELS[r.category] ?? r.category}</span>
                  </div>
                  <p className="mt-1 whitespace-pre-wrap text-[12.5px] leading-snug text-[var(--fg-2)]">{r.description}</p>
                </li>
              ))}
            </ul>
          </div>
        </div>
      )}
    </div>
  );
}

/**
 * One logged video inside the day sheet. The title row deep-links into the
 * Daily Video Log (to view feedback / reply); if the host attached a link, it
 * shows as a separate tappable row that opens the actual video in a new tab.
 */
function DayVideoRow({ video }) {
  const lastComment = video.comments.length > 0 ? video.comments[video.comments.length - 1] : null;
  const awaitingReply = Boolean(lastComment && !lastComment.is_host);

  return (
    <div className="rounded-[14px] border border-[var(--hair)] bg-[var(--app-bg-2)] px-3 py-2.5">
      <Link href={`/live-host/videos?video=${video.id}`} className="block transition active:scale-[0.99]">
        <div className="flex items-start justify-between gap-2">
          <div className="min-w-0">
            <div className="truncate text-[13px] font-semibold text-[var(--fg)]">{video.title}</div>
            <div className="mt-1 flex flex-wrap items-center gap-1.5">
              {video.category_label && (
                <span className="rounded-full bg-[var(--accent-soft)] px-2 py-0.5 text-[10px] font-semibold text-[var(--accent)]">{video.category_label}</span>
              )}
              {video.comments.length > 0 && (
                <span className="inline-flex items-center gap-1 text-[10px] text-[var(--fg-3)]">
                  <MessageSquare className="h-3 w-3" strokeWidth={2} />{video.comments.length}
                </span>
              )}
              {awaitingReply && (
                <span className="rounded-full bg-[#FEECEF] px-2 py-0.5 text-[9.5px] font-bold uppercase tracking-wide text-[#B91C1C]">Reply</span>
              )}
            </div>
          </div>
          <ChevronRight className="mt-0.5 h-4 w-4 shrink-0 text-[var(--fg-3)]" strokeWidth={2} />
        </div>
        {lastComment && (
          <div className="mt-2 rounded-[10px] bg-[var(--app-bg)] px-2.5 py-2">
            <div className="mb-0.5 text-[10px] font-semibold text-[var(--fg-3)]">{lastComment.is_host ? 'You' : (lastComment.author?.name ?? 'Coach')}</div>
            <p className="line-clamp-2 whitespace-pre-wrap text-[12px] leading-snug text-[var(--fg-2)]">{lastComment.body}</p>
          </div>
        )}
      </Link>
      {video.link && (
        <a
          href={video.link}
          target="_blank"
          rel="noopener noreferrer"
          className="mt-2 flex items-center gap-1.5 rounded-[10px] border border-[var(--hair)] bg-[var(--app-bg)] px-2.5 py-1.5 text-[11px] font-medium text-[var(--accent)] transition active:scale-[0.99]"
        >
          <ExternalLink className="h-3 w-3 shrink-0" strokeWidth={2} />
          <span className="truncate">{prettyLink(video.link)}</span>
        </a>
      )}
    </div>
  );
}
