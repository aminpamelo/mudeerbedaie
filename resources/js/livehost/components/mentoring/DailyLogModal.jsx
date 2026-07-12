import { router } from '@inertiajs/react';
import { useCallback, useEffect, useState } from 'react';
import { Check, Loader2, ShieldAlert, Video, X } from 'lucide-react';
import { Button } from '@/livehost/components/ui/button';
import { Input } from '@/livehost/components/ui/input';
import { Label } from '@/livehost/components/ui/label';
import DailyComments from '@/livehost/components/mentoring/DailyComments';
import DisciplinaryModal from '@/livehost/components/mentoring/DisciplinaryModal';

function rm(n) {
  const num = Number(n);
  if (Number.isNaN(num)) return 'RM 0';
  const hasSen = Math.round(num) !== num;
  return `RM ${num.toLocaleString(undefined, { minimumFractionDigits: hasSen ? 2 : 0, maximumFractionDigits: 2 })}`;
}

function todayInput() {
  const d = new Date();
  const pad = (n) => String(n).padStart(2, '0');
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
}

/**
 * Read-only video-log status for one host on the selected day. The host logs
 * videos from their Pocket; here the PIC sees whether they did, with links.
 */
function VideoStatus({ videos, count }) {
  if (count === 0) {
    return (
      <div className="mb-2 inline-flex items-center gap-1 rounded-md bg-[#FBFAFF] px-2 py-1 text-[10.5px] font-medium text-[#A3A3A3]">
        <Video className="h-3 w-3" strokeWidth={2} /> No video posted
      </div>
    );
  }

  return (
    <div className="mb-2 flex flex-wrap items-center gap-1.5">
      <span className="inline-flex items-center gap-1 rounded-md bg-[#F3F0FE] px-1.5 py-0.5 text-[10.5px] font-semibold text-[#6D28D9]">
        <Video className="h-3 w-3" strokeWidth={2.25} /> {count} video{count === 1 ? '' : 's'}
      </span>
      {videos.map((v) => {
        const Tag = v.link ? 'a' : 'span';
        const linkProps = v.link ? { href: v.link, target: '_blank', rel: 'noopener noreferrer' } : {};
        return (
          <Tag
            key={v.id}
            {...linkProps}
            title={`${v.title}${v.category_label ? ` · ${v.category_label}` : ''}`}
            className={`inline-flex max-w-[170px] items-center gap-1 rounded-md bg-[#F7F5FE] px-1.5 py-0.5 text-[10.5px] font-medium text-[#7C3AED] ${v.link ? 'hover:underline' : ''}`}
          >
            <span className="truncate">{v.title}</span>
            {v.category_label && <span className="shrink-0 text-[9px] font-semibold text-[#A78BFA]">· {v.category_label}</span>}
          </Tag>
        );
      })}
    </div>
  );
}

/**
 * Daily-log sweep: pick a date and record the mandatory daily comment for every
 * active host in one full-screen pass (3-column grid). Sales auto-fill from
 * live-session GMV and can be overridden; a disciplinary record can be logged
 * per host for the day. Compliance banners track who still needs a comment and
 * who has not yet posted a daily video (the video is logged by the host in the
 * Pocket; here it is read-only).
 */
export default function DailyLogModal({ program, reloadOnly = ['performance'], onClose }) {
  const [date, setDate] = useState(todayInput());
  const [log, setLog] = useState(null);
  const [loading, setLoading] = useState(true);
  const [drafts, setDrafts] = useState({});
  const [savingId, setSavingId] = useState(null);
  const [dirty, setDirty] = useState(false);
  const [disciplinaryFor, setDisciplinaryFor] = useState(null);

  const fetchLog = useCallback((d) => {
    setLoading(true);
    fetch(`/livehost/mentoring/programs/${program.id}/daily-log?date=${d}`, {
      headers: { Accept: 'application/json' },
      credentials: 'same-origin',
    })
      .then((r) => r.json())
      .then((data) => {
        setLog(data);
        const seed = {};
        (data.mentees ?? []).forEach((m) => {
          seed[m.id] = {
            comment: m.my_comment ?? '',
            sales_override: m.sales_override != null ? String(m.sales_override) : '',
          };
        });
        setDrafts(seed);
      })
      .finally(() => setLoading(false));
  }, [program.id]);

  useEffect(() => { fetchLog(date); }, [date, fetchLog]);

  const setDraft = (id, field, value) => setDrafts((p) => ({ ...p, [id]: { ...(p[id] || {}), [field]: value } }));

  const saveOne = (m) => {
    const draft = drafts[m.id] || {};
    if (!draft.comment?.trim()) return;
    setSavingId(m.id);
    router.patch(
      `/livehost/mentoring/mentees/${m.id}/daily-metric`,
      {
        date,
        comment: draft.comment,
        sales_override: draft.sales_override === '' ? null : Number(draft.sales_override),
      },
      {
        preserveScroll: true,
        preserveState: true,
        only: reloadOnly,
        onSuccess: () => { setDirty(true); fetchLog(date); },
        onFinish: () => setSavingId(null),
      },
    );
  };

  const close = () => {
    if (dirty) router.reload({ only: reloadOnly, preserveScroll: true });
    onClose();
  };

  const mentees = log?.mentees ?? [];
  const missing = log?.missing ?? 0;
  const missingVideo = log?.missing_video ?? 0;
  const total = mentees.length;

  return (
    <div className="fixed inset-0 z-50 grid place-items-center bg-black/40 p-0 sm:p-4" onMouseDown={(e) => { if (e.target === e.currentTarget) close(); }}>
      <div className="flex h-[100vh] w-[100vw] max-w-[1500px] flex-col overflow-hidden bg-white sm:h-[95vh] sm:rounded-[16px] sm:shadow-[0_20px_60px_rgba(0,0,0,0.18)]">
        <div className="flex items-center justify-between gap-3 border-b border-[#F0F0F0] px-6 py-4">
          <div>
            <div className="text-[16px] font-semibold tracking-[-0.015em] text-[#0A0A0A]">Daily log</div>
            <div className="text-[12px] text-[#737373]">Record each host's mandatory daily comment. Sales auto-fill from live sessions.</div>
          </div>
          <button type="button" onClick={close} className="rounded-md p-1 text-[#737373] hover:bg-[#F5F5F5] hover:text-[#0A0A0A]" aria-label="Close">
            <X className="h-4 w-4" strokeWidth={2} />
          </button>
        </div>

        <div className="flex flex-wrap items-center justify-between gap-3 border-b border-[#F0F0F0] bg-[#FAFAFA] px-6 py-3">
          <div className="flex items-center gap-2">
            <Label className="text-[12px] font-medium text-[#525252]">Date</Label>
            <Input type="date" value={date} max={todayInput()} onChange={(e) => setDate(e.target.value)} className="h-9 w-[160px]" />
          </div>
          {!loading && total > 0 && (
            <div className="flex flex-wrap items-center gap-2">
              <span className={`inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[12px] font-medium ${missing === 0 ? 'bg-[#ECFDF5] text-[#059669]' : 'bg-[#FEF3C7] text-[#B45309]'}`}>
                {missing === 0 ? <><Check className="h-3.5 w-3.5" strokeWidth={2.5} /> All {total} commented</> : `${missing} of ${total} need a comment`}
              </span>
              <span className={`inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[12px] font-medium ${missingVideo === 0 ? 'bg-[#F3F0FE] text-[#6D28D9]' : 'bg-[#FEF3C7] text-[#B45309]'}`}>
                <Video className="h-3.5 w-3.5" strokeWidth={2.25} />
                {missingVideo === 0 ? `All ${total} posted a video` : `${missingVideo} of ${total} missing a video`}
              </span>
            </div>
          )}
        </div>

        <div className="flex-1 overflow-y-auto px-6 py-4">
          {loading ? (
            <div className="grid place-items-center py-24 text-[#A3A3A3]"><Loader2 className="h-5 w-5 animate-spin" /></div>
          ) : mentees.length === 0 ? (
            <p className="py-24 text-center text-[13px] text-[#A3A3A3]">No active mentees in this program.</p>
          ) : (
            <ul className="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-3">
              {mentees.map((m) => {
                const draft = drafts[m.id] || {};
                const done = m.has_comment;
                return (
                  <li key={m.id} className={`flex flex-col rounded-[12px] border p-3.5 ${done ? 'border-[#E7F6EE] bg-[#FCFEFD]' : m.has_disciplinary ? 'border-[#FBD5D5] bg-[#FEFCFC]' : 'border-[#EAEAEA] bg-white'}`}>
                    <div className="mb-2 flex items-start justify-between gap-2">
                      <div className="min-w-0">
                        <div className="flex items-center gap-1.5">
                          <span className="truncate text-[13px] font-semibold text-[#0A0A0A]">{m.name}</span>
                          {m.level && <span className="shrink-0 rounded-full px-1.5 py-0.5 text-[9.5px] font-semibold text-white" style={{ backgroundColor: m.level.color || '#10B981' }}>{m.level.name}</span>}
                        </div>
                        <div className="mt-0.5 flex items-center gap-2 text-[10px]">
                          {done ? <span className="inline-flex items-center gap-0.5 font-medium text-[#059669]"><Check className="h-3 w-3" strokeWidth={2.5} /> Logged</span> : <span className="font-medium text-[#B45309]">Pending</span>}
                          {m.has_disciplinary && <span className="inline-flex items-center gap-0.5 font-medium text-[#B91C1C]"><ShieldAlert className="h-3 w-3" strokeWidth={2.5} /> {m.disciplinary_count}</span>}
                        </div>
                      </div>
                      <div className="shrink-0 text-right">
                        <div className="text-[12.5px] font-bold tabular-nums text-[#0A0A0A]">{rm(m.sales)}</div>
                        <div className="text-[9px] text-[#A3A3A3]">{m.sessions > 0 ? `${m.sessions} live` : 'no live'}</div>
                      </div>
                    </div>

                    <VideoStatus videos={m.videos ?? []} count={m.video_count ?? 0} />

                    {(m.comments?.length ?? 0) > 0 && (
                      <div className="mb-2">
                        <DailyComments comments={m.comments} onChanged={() => { setDirty(true); fetchLog(date); }} reloadOnly={reloadOnly} compact />
                      </div>
                    )}

                    <textarea
                      value={draft.comment ?? ''}
                      onChange={(e) => setDraft(m.id, 'comment', e.target.value)}
                      rows={3}
                      placeholder={draft.comment ? 'Your comment' : 'Add your comment for today'}
                      className="w-full flex-1 resize-y rounded-lg border border-[#EAEAEA] bg-white px-3 py-2 text-[13px] text-[#0A0A0A] focus:outline-none focus:ring-2 focus:ring-[#10B981]/20"
                    />

                    <div className="mt-2 flex items-center gap-2">
                      <div className="w-[92px] shrink-0">
                        <input
                          type="number" min="0" step="0.01"
                          value={draft.sales_override ?? ''}
                          onChange={(e) => setDraft(m.id, 'sales_override', e.target.value)}
                          placeholder={`${m.auto}`}
                          title="Override sales for this day (optional)"
                          className="h-8 w-full rounded-lg border border-[#EAEAEA] bg-white px-2 text-right text-[12.5px] tabular-nums text-[#0A0A0A] focus:outline-none focus:ring-2 focus:ring-[#10B981]/20"
                        />
                        <div className="mt-0.5 text-center text-[8.5px] uppercase tracking-wide text-[#A3A3A3]">RM override</div>
                      </div>
                      <button
                        type="button"
                        onClick={() => setDisciplinaryFor(m)}
                        className="inline-flex h-8 items-center gap-1 rounded-lg border border-[#F5D6D6] px-2 text-[11px] font-medium text-[#B91C1C] hover:bg-[#FEF2F2]"
                        title="Log disciplinary for this day"
                      >
                        <ShieldAlert className="h-3.5 w-3.5" strokeWidth={2} /> Conduct
                      </button>
                      <Button
                        type="button" size="sm"
                        disabled={savingId === m.id || !draft.comment?.trim()}
                        onClick={() => saveOne(m)}
                        className="ml-auto h-8 bg-[#0A0A0A] px-3.5 text-white hover:bg-[#262626] disabled:opacity-40"
                      >
                        {savingId === m.id ? '…' : 'Save'}
                      </Button>
                    </div>
                  </li>
                );
              })}
            </ul>
          )}
        </div>
      </div>

      {disciplinaryFor && (
        <DisciplinaryModal
          mentee={disciplinaryFor}
          presetDate={date}
          reloadOnly={reloadOnly}
          onClose={() => { setDisciplinaryFor(null); setDirty(true); fetchLog(date); }}
        />
      )}
    </div>
  );
}
