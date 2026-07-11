import { router } from '@inertiajs/react';
import { useCallback, useEffect, useState } from 'react';
import { CalendarDays, Check, Loader2, Pencil, Radio, ShieldAlert, Video, X } from 'lucide-react';
import { Button } from '@/livehost/components/ui/button';
import DailyComments from '@/livehost/components/mentoring/DailyComments';
import DisciplinaryModal, { categoryLabel, severityTone } from '@/livehost/components/mentoring/DisciplinaryModal';

/* ---- small local helpers (kept independent of the grid file) ---- */

function scoreTone(score) {
  if (score === null || score === undefined || score === '') return { bg: 'bg-[#F5F5F5]', text: 'text-[#A3A3A3]' };
  const n = Number(score);
  if (n >= 80) return { bg: 'bg-[#ECFDF5]', text: 'text-[#047857]' };
  if (n >= 60) return { bg: 'bg-[#FEF3C7]', text: 'text-[#B45309]' };
  return { bg: 'bg-[#FEE2E2]', text: 'text-[#B91C1C]' };
}

function salesPct(sales, target) {
  if (sales === '' || sales == null || !target || target <= 0) return null;
  const n = Number(sales);
  if (Number.isNaN(n)) return null;
  return Math.min(100, Math.round((n / target) * 100));
}

function countPct(actual, target) {
  if (!target || target <= 0) return null;
  const n = Number(actual ?? 0);
  if (Number.isNaN(n)) return null;
  return Math.min(100, Math.round((n / target) * 100));
}

/** Overall = average of every KPI that applies: Attitude, Sales%, Video%, Live%
 * (each counted only when it has a value/target). Mirrors the grid's helper. */
function overallKpi({ attitude, sales, salesTarget, videoActual, videoTarget, liveActual, liveTarget }) {
  const a = attitude === '' || attitude == null ? null : Math.max(0, Math.min(100, Number(attitude)));
  const parts = [a, salesPct(sales, salesTarget), countPct(videoActual, videoTarget), countPct(liveActual, liveTarget)]
    .filter((v) => v !== null && !Number.isNaN(v));
  if (parts.length === 0) return null;
  return Math.round(parts.reduce((x, y) => x + y, 0) / parts.length);
}

function rm(n) {
  const num = Number(n);
  if (Number.isNaN(num)) return '–';
  const hasSen = Math.round(num) !== num;
  return `RM ${num.toLocaleString(undefined, { minimumFractionDigits: hasSen ? 2 : 0, maximumFractionDigits: 2 })}`;
}

function sessionStatusDot(status) {
  return { ended: 'bg-[#10B981]', live: 'bg-[#F59E0B]', scheduled: 'bg-[#A3A3A3]', missed: 'bg-[#EF4444]', cancelled: 'bg-[#EF4444]' }[status] ?? 'bg-[#A3A3A3]';
}

/** Split an ordered list of days into three chronological columns (first third, second third, final third). */
function chunkIntoThree(items) {
  const per = Math.ceil(items.length / 3) || 1;
  return [items.slice(0, per), items.slice(per, per * 2), items.slice(per * 2)];
}

function Stat({ label, value, sub, tone }) {
  return (
    <div className="rounded-[10px] bg-[#F9F9F9] px-3 py-2">
      <div className="text-[9.5px] font-semibold uppercase tracking-wide text-[#A3A3A3]">{label}</div>
      <div className={`mt-0.5 text-[15px] font-bold tabular-nums ${tone ?? 'text-[#0A0A0A]'}`}>{value}</div>
      {sub && <div className="text-[9.5px] text-[#A3A3A3]">{sub}</div>}
    </div>
  );
}

/**
 * Full month overview for one host: the monthly summary + attitude editor, and
 * the whole month day-by-day (live sessions, daily comment, disciplinary). Days
 * are editable inline; disciplinary can be logged per day. Everything that
 * happened that month, in one place.
 */
export default function MonthDetailModal({ mentee, month, target, reloadOnly = ['performance'], onSaved, onClose }) {
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [attitude, setAttitude] = useState('');
  const [note, setNote] = useState('');
  const [videoTarget, setVideoTarget] = useState('');
  const [liveTarget, setLiveTarget] = useState('');
  const [savingAttitude, setSavingAttitude] = useState(false);
  const [attitudeStart, setAttitudeStart] = useState('');
  const [noteStart, setNoteStart] = useState('');
  const [videoTargetStart, setVideoTargetStart] = useState('');
  const [liveTargetStart, setLiveTargetStart] = useState('');
  const [dayEdit, setDayEdit] = useState(null); // day object being edited
  const [disciplinaryFor, setDisciplinaryFor] = useState(null); // { date }
  const [onlyActive, setOnlyActive] = useState(false);

  const fetchOverview = useCallback(() => {
    setLoading(true);
    fetch(`/livehost/mentoring/mentees/${mentee.id}/month-overview?year=${month.year}&month=${month.month}`, {
      headers: { Accept: 'application/json' },
      credentials: 'same-origin',
    })
      .then((r) => r.json())
      .then((d) => {
        setData(d);
        setAttitude(d.summary.attitude ?? '');
        setNote(d.summary.note ?? '');
        setAttitudeStart(d.summary.attitude ?? '');
        setNoteStart(d.summary.note ?? '');
        const vt = d.summary.video_target ?? '';
        const lt = d.summary.live_target ?? '';
        setVideoTarget(vt);
        setVideoTargetStart(vt);
        setLiveTarget(lt);
        setLiveTargetStart(lt);
      })
      .finally(() => setLoading(false));
  }, [mentee.id, month.year, month.month]);

  useEffect(() => { fetchOverview(); }, [fetchOverview]);

  const refresh = () => { fetchOverview(); onSaved?.(); };

  const saveAttitude = () => {
    setSavingAttitude(true);
    router.patch(
      `/livehost/mentoring/mentees/${mentee.id}/monthly-score`,
      {
        year: month.year,
        month: month.month,
        attitude_score: attitude === '' ? null : Number(attitude),
        video_target: videoTarget === '' ? null : Number(videoTarget),
        live_target: liveTarget === '' ? null : Number(liveTarget),
        notes: note || null,
      },
      {
        preserveScroll: true,
        preserveState: true,
        only: reloadOnly,
        onSuccess: () => {
          setAttitudeStart(attitude);
          setNoteStart(note);
          setVideoTargetStart(videoTarget);
          setLiveTargetStart(liveTarget);
          onSaved?.();
        },
        onFinish: () => setSavingAttitude(false),
      },
    );
  };

  const summary = data?.summary;
  const salesTotal = summary?.sales_total ?? 0;
  const overall = overallKpi({
    attitude,
    sales: salesTotal,
    salesTarget: target,
    videoActual: summary?.video_total ?? 0,
    videoTarget: videoTarget === '' ? null : Number(videoTarget),
    liveActual: summary?.live_total ?? 0,
    liveTarget: liveTarget === '' ? null : Number(liveTarget),
  });
  const overallTone = scoreTone(overall);
  const attitudeDirty = String(attitude) !== String(attitudeStart)
    || String(note) !== String(noteStart)
    || String(videoTarget) !== String(videoTargetStart)
    || String(liveTarget) !== String(liveTargetStart);
  const today = new Date();
  const todayKey = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}-${String(today.getDate()).padStart(2, '0')}`;

  const days = (data?.days ?? []).filter((d) => (onlyActive ? d.has_activity : true));

  return (
    <div className="fixed inset-0 z-50 grid place-items-center bg-black/40 p-0 sm:p-4" onMouseDown={(e) => { if (e.target === e.currentTarget) onClose(); }}>
      <div className="flex h-[100vh] w-[100vw] max-w-[1500px] flex-col overflow-hidden bg-white sm:h-[95vh] sm:rounded-[16px] sm:shadow-[0_20px_60px_rgba(0,0,0,0.18)]">
        {/* Header */}
        <div className="flex items-start justify-between gap-3 border-b border-[#F0F0F0] px-6 py-4">
          <div className="flex items-center gap-2">
            <span className="text-[16px] font-semibold text-[#0A0A0A]">{mentee.name}</span>
            {mentee.level && <span className="rounded-full px-1.5 py-0.5 text-[9.5px] font-semibold text-white" style={{ backgroundColor: mentee.level.color || '#10B981' }}>{mentee.level.name}</span>}
            <span className="text-[13px] text-[#A3A3A3]">· {month.label}</span>
          </div>
          <button type="button" onClick={onClose} className="rounded-md p-1 text-[#737373] hover:bg-[#F5F5F5] hover:text-[#0A0A0A]"><X className="h-4 w-4" strokeWidth={2} /></button>
        </div>

        {/* Summary (actuals) */}
        <div className="border-b border-[#F0F0F0] bg-[#FCFCFC] px-6 py-3">
          <div className="grid grid-cols-2 gap-2 sm:grid-cols-4 lg:grid-cols-7">
            <Stat label="Sales (summed)" value={rm(salesTotal)} sub={target ? `target ${rm(target)}` : 'no target'} />
            <div className="rounded-[10px] bg-[#F9F9F9] px-3 py-2">
              <div className="text-[9.5px] font-semibold uppercase tracking-wide text-[#A3A3A3]">Overall</div>
              <div className={`mt-0.5 inline-flex h-7 min-w-[44px] items-center justify-center rounded-md px-2 text-[13px] font-bold tabular-nums ${overallTone.bg} ${overallTone.text}`}>{overall != null ? `${overall}%` : '–'}</div>
            </div>
            <Stat
              label="Live sessions"
              value={summary?.live_total ?? 0}
              sub={liveTarget !== '' && liveTarget != null ? `target ${liveTarget}` : (summary?.live_days ?? 0) > 0 ? `${summary.live_days} day${summary.live_days === 1 ? '' : 's'}` : null}
              tone={liveTarget !== '' && liveTarget != null && (summary?.live_total ?? 0) >= Number(liveTarget) ? 'text-[#047857]' : 'text-[#0A0A0A]'}
            />
            <Stat
              label="Videos"
              value={summary?.video_total ?? 0}
              sub={videoTarget !== '' && videoTarget != null ? `target ${videoTarget}` : (summary?.video_days ?? 0) > 0 ? `${summary.video_days} day${summary.video_days === 1 ? '' : 's'}` : null}
              tone={videoTarget !== '' && videoTarget != null && (summary?.video_total ?? 0) >= Number(videoTarget) ? 'text-[#047857]' : (summary?.video_total ?? 0) > 0 ? 'text-[#6D28D9]' : 'text-[#0A0A0A]'}
            />
            <Stat label="Live days" value={summary?.live_days ?? 0} />
            <Stat label="Comments" value={summary?.comment_days ?? 0} />
            <Stat label="Disciplinary" value={summary?.disciplinary_total ?? 0} tone={(summary?.disciplinary_total ?? 0) > 0 ? 'text-[#B91C1C]' : 'text-[#0A0A0A]'} />
          </div>

          {/* Editable monthly targets + attitude */}
          <div className="mt-2 flex flex-wrap items-end gap-2">
            <label className="flex flex-col gap-1">
              <span className="text-[9.5px] font-semibold uppercase tracking-wide text-[#A3A3A3]">Attitude / 100</span>
              <input type="number" min="0" max="100" value={attitude} onChange={(e) => setAttitude(e.target.value)} placeholder="–" className="h-9 w-[84px] rounded-lg border border-[#EAEAEA] bg-white px-2 text-center text-[14px] font-semibold tabular-nums text-[#0A0A0A] focus:outline-none focus:ring-2 focus:ring-[#10B981]/20" />
            </label>
            <label className="flex flex-col gap-1">
              <span className="inline-flex items-center gap-1 text-[9.5px] font-semibold uppercase tracking-wide text-[#A3A3A3]"><Radio className="h-3 w-3" strokeWidth={2.5} /> Live target</span>
              <input type="number" min="0" value={liveTarget} onChange={(e) => setLiveTarget(e.target.value)} placeholder="–" className="h-9 w-[84px] rounded-lg border border-[#EAEAEA] bg-white px-2 text-center text-[14px] font-semibold tabular-nums text-[#0A0A0A] focus:outline-none focus:ring-2 focus:ring-[#10B981]/20" />
            </label>
            <label className="flex flex-col gap-1">
              <span className="inline-flex items-center gap-1 text-[9.5px] font-semibold uppercase tracking-wide text-[#A3A3A3]"><Video className="h-3 w-3" strokeWidth={2.5} /> Video target</span>
              <input type="number" min="0" value={videoTarget} onChange={(e) => setVideoTarget(e.target.value)} placeholder="–" className="h-9 w-[84px] rounded-lg border border-[#EAEAEA] bg-white px-2 text-center text-[14px] font-semibold tabular-nums text-[#0A0A0A] focus:outline-none focus:ring-2 focus:ring-[#10B981]/20" />
            </label>
            <input value={note} onChange={(e) => setNote(e.target.value)} placeholder="Monthly note (optional)…" className="h-9 min-w-[180px] flex-1 self-end rounded-lg border border-[#EAEAEA] bg-white px-3 text-[12.5px] text-[#0A0A0A] focus:outline-none focus:ring-2 focus:ring-[#10B981]/20" />
            <Button type="button" size="sm" disabled={savingAttitude || !attitudeDirty} onClick={saveAttitude} className="h-9 self-end bg-[#0A0A0A] px-3.5 text-white hover:bg-[#262626] disabled:opacity-40">{savingAttitude ? 'Saving…' : 'Save'}</Button>
          </div>
        </div>

        {/* Day list controls */}
        <div className="flex items-center justify-between border-b border-[#F0F0F0] px-6 py-2">
          <span className="inline-flex items-center gap-1.5 text-[12px] font-semibold text-[#525252]"><CalendarDays className="h-3.5 w-3.5 text-[#A3A3A3]" strokeWidth={2} /> Day by day</span>
          <label className="inline-flex cursor-pointer items-center gap-1.5 text-[11.5px] text-[#525252]">
            <input type="checkbox" checked={onlyActive} onChange={(e) => setOnlyActive(e.target.checked)} className="h-3.5 w-3.5 accent-[#10B981]" />
            Only days with activity
          </label>
        </div>

        {/* Day list — three chronological columns (first third / second third / final third) */}
        <div className="flex-1 overflow-y-auto px-6 py-4">
          {loading ? (
            <div className="grid place-items-center py-24 text-[#A3A3A3]"><Loader2 className="h-5 w-5 animate-spin" /></div>
          ) : days.length === 0 ? (
            <p className="py-16 text-center text-[13px] text-[#A3A3A3]">No days to show.</p>
          ) : (
            <div className="grid grid-cols-1 gap-x-4 gap-y-2 md:grid-cols-2 xl:grid-cols-3">
              {chunkIntoThree(days).map((col, ci) => (
                <ul key={ci} className="space-y-2">
                  {col.map((d) => (
                    <DayItem
                      key={d.date}
                      d={d}
                      isToday={d.date === todayKey}
                      reloadOnly={reloadOnly}
                      onEdit={() => setDayEdit(d)}
                      onDiscipline={() => setDisciplinaryFor({ date: d.date })}
                      onChanged={refresh}
                    />
                  ))}
                </ul>
              ))}
            </div>
          )}
        </div>
      </div>

      {dayEdit && (
        <DayEditForm
          menteeId={mentee.id}
          day={dayEdit}
          reloadOnly={reloadOnly}
          onSaved={refresh}
          onClose={() => setDayEdit(null)}
        />
      )}
      {disciplinaryFor && (
        <DisciplinaryModal
          mentee={mentee}
          presetDate={disciplinaryFor.date}
          reloadOnly={reloadOnly}
          onClose={() => { setDisciplinaryFor(null); refresh(); }}
        />
      )}
    </div>
  );
}

/* ---- one day card (compact when quiet, rich when there was activity) ---- */

function DayItem({ d, isToday, reloadOnly = ['performance'], onEdit, onDiscipline, onChanged }) {
  if (!d.has_activity) {
    return (
      <li className={`flex items-center justify-between rounded-lg px-3 py-1.5 text-[12px] ${isToday ? 'bg-[#F0FDF4]' : ''}`}>
        <span className="text-[#C4C4C4]"><span className="font-semibold text-[#A3A3A3]">{d.day}</span> {d.weekday}{isToday ? ' · today' : ''}</span>
        <button type="button" onClick={onEdit} className="text-[11px] font-medium text-[#A3A3A3] hover:text-[#10B981]">+ log</button>
      </li>
    );
  }

  return (
    <li className={`rounded-[12px] border p-3 ${isToday ? 'border-[#A7F3D0] bg-[#F0FDF4]' : 'border-[#EAEAEA] bg-white'}`}>
      <div className="mb-2 flex items-start justify-between gap-3">
        <div className="flex items-baseline gap-2">
          <span className="text-[15px] font-bold tabular-nums text-[#0A0A0A]">{d.day}</span>
          <span className="text-[11px] font-medium uppercase tracking-wide text-[#A3A3A3]">{d.weekday}{isToday ? ' · today' : ''}</span>
        </div>
        <div className="text-right">
          <div className="text-[14px] font-bold tabular-nums text-[#0A0A0A]">{rm(d.effective)}</div>
          <div className="text-[9.5px] text-[#A3A3A3]">{d.override != null ? `override · auto ${rm(d.auto)}` : 'auto GMV'}</div>
        </div>
      </div>

      {d.sessions.length > 0 && (
        <div className="mb-2 space-y-1">
          {d.sessions.map((s) => (
            <div key={s.id} className="flex items-center justify-between gap-2 rounded-lg bg-[#FAFAFA] px-2.5 py-1.5">
              <div className="flex min-w-0 items-center gap-1.5">
                <span className={`h-1.5 w-1.5 shrink-0 rounded-full ${sessionStatusDot(s.status)}`} />
                <span className="truncate text-[12px] font-medium text-[#0A0A0A]">{s.title || s.account || 'Live session'}</span>
                <span className="shrink-0 text-[10.5px] text-[#A3A3A3]">{[s.start, s.status, s.duration_minutes ? `${s.duration_minutes}m` : null].filter(Boolean).join(' · ')}</span>
              </div>
              <span className="shrink-0 text-[12px] font-bold tabular-nums text-[#0A0A0A]">{s.gmv != null ? rm(s.gmv) : '—'}</span>
            </div>
          ))}
        </div>
      )}

      {d.comments?.length > 0 && (
        <div className="mb-2">
          <DailyComments comments={d.comments} onChanged={onChanged} reloadOnly={reloadOnly} compact />
        </div>
      )}

      {d.videos?.length > 0 && (
        <div className="mb-2 space-y-1">
          {d.videos.map((v) => (
            <div key={v.id} className="flex items-center justify-between gap-2 rounded-lg border border-[#E9E3FB] bg-[#F7F5FE] px-2.5 py-1.5">
              <div className="flex min-w-0 items-center gap-1.5">
                <Video className="h-3.5 w-3.5 shrink-0 text-[#7C3AED]" strokeWidth={2} />
                <span className="truncate text-[12px] font-medium text-[#0A0A0A]">{v.title}</span>
              </div>
              {v.link && <a href={v.link} target="_blank" rel="noopener noreferrer" className="shrink-0 text-[11px] font-medium text-[#7C3AED] hover:underline">Open</a>}
            </div>
          ))}
        </div>
      )}

      {d.disciplinary.map((r) => (
        <div key={r.id} className="mb-2 rounded-lg border border-[#FBD5D5] bg-[#FEF2F2] px-2.5 py-1.5">
          <div className="flex flex-wrap items-center gap-1.5">
            <ShieldAlert className="h-3 w-3 text-[#B91C1C]" strokeWidth={2.25} />
            <span className={`rounded px-1 py-0.5 text-[9px] font-bold uppercase tracking-wide ${severityTone(r.severity)}`}>{r.severity}</span>
            <span className="text-[12px] font-semibold text-[#0A0A0A]">{categoryLabel(r.category)}</span>
          </div>
          <div className="mt-0.5 whitespace-pre-wrap text-[11.5px] text-[#525252]">{r.description}</div>
          {r.recorded_by && <div className="text-[10px] text-[#A3A3A3]">by {r.recorded_by}</div>}
        </div>
      ))}

      <div className="flex items-center gap-3 pt-1">
        <button type="button" onClick={onEdit} className="inline-flex items-center gap-1 text-[11.5px] font-medium text-[#525252] hover:text-[#0A0A0A]"><Pencil className="h-3 w-3" strokeWidth={2} /> Edit day</button>
        <button type="button" onClick={onDiscipline} className="inline-flex items-center gap-1 text-[11.5px] font-medium text-[#B91C1C] hover:underline"><ShieldAlert className="h-3 w-3" strokeWidth={2} /> Log disciplinary</button>
      </div>
    </li>
  );
}

/* ---- inline day editor (comment + sales override) ---- */

function DayEditForm({ menteeId, day, reloadOnly = ['performance'], onSaved, onClose }) {
  const [comment, setComment] = useState(day.comments?.find((c) => c.is_mine)?.text ?? '');
  const [override, setOverride] = useState(day.override != null ? String(day.override) : '');
  const [busy, setBusy] = useState(false);

  const otherComments = (day.comments ?? []).filter((c) => !c.is_mine);

  const save = () => {
    if (!comment.trim() && override === '') return;
    setBusy(true);
    router.patch(
      `/livehost/mentoring/mentees/${menteeId}/daily-metric`,
      { date: day.date, comment, sales_override: override === '' ? null : Number(override) },
      { preserveScroll: true, preserveState: true, only: reloadOnly, onSuccess: () => { onSaved(); onClose(); }, onFinish: () => setBusy(false) },
    );
  };

  const dateLabel = new Date(day.date).toLocaleDateString(undefined, { weekday: 'long', day: 'numeric', month: 'long' });

  return (
    <div className="fixed inset-0 z-[60] grid place-items-center bg-black/40 p-4" onMouseDown={(e) => { if (e.target === e.currentTarget) onClose(); }}>
      <div className="w-full max-w-md rounded-[16px] bg-white p-5 shadow-[0_20px_60px_rgba(0,0,0,0.2)]">
        <div className="mb-3 flex items-center justify-between">
          <span className="text-[14px] font-semibold text-[#0A0A0A]">{dateLabel}</span>
          <button type="button" onClick={onClose} className="rounded-md p-1 text-[#737373] hover:bg-[#F5F5F5]"><X className="h-4 w-4" strokeWidth={2} /></button>
        </div>
        <div className="mb-2 flex items-center justify-between rounded-[10px] bg-[#F9F9F9] px-3 py-2 text-[12px]">
          <span className="text-[#737373]">{day.sessions?.length > 0 ? `${day.sessions.length} live session${day.sessions.length === 1 ? '' : 's'}` : 'No live session'}</span>
          <span className="font-medium text-[#525252]">Auto GMV <span className="font-bold text-[#0A0A0A]">{rm(day.auto)}</span></span>
        </div>
        {otherComments.length > 0 && (
          <div className="mb-3">
            <div className="mb-1 text-[11px] font-semibold uppercase tracking-wide text-[#A3A3A3]">Other comments ({otherComments.length})</div>
            <DailyComments comments={otherComments} canDelete={false} compact />
          </div>
        )}
        <label className="mb-1 block text-[12.5px] font-medium text-[#525252]">Your comment <span className="font-normal text-[#A3A3A3]">· saved under your name</span></label>
        <textarea value={comment} onChange={(e) => setComment(e.target.value)} rows={3} autoFocus placeholder="How did they do today?" className="mb-3 w-full resize-y rounded-lg border border-[#EAEAEA] bg-white px-3 py-2 text-[13px] text-[#0A0A0A] focus:outline-none focus:ring-2 focus:ring-[#10B981]/20" />
        <label className="mb-1 block text-[12.5px] font-medium text-[#525252]">Sales override <span className="font-normal text-[#A3A3A3]">(optional)</span></label>
        <div className="mb-4 flex items-center gap-2">
          <input type="number" min="0" step="0.01" value={override} onChange={(e) => setOverride(e.target.value)} placeholder={String(day.auto)} className="h-9 w-40 rounded-lg border border-[#EAEAEA] bg-white px-3 text-right text-[14px] tabular-nums text-[#0A0A0A] focus:outline-none focus:ring-2 focus:ring-[#10B981]/20" />
          <span className="text-[11px] text-[#A3A3A3]">RM · leave blank to use auto</span>
        </div>
        <div className="flex justify-end gap-2">
          <Button type="button" variant="ghost" onClick={onClose}>Cancel</Button>
          <Button type="button" disabled={busy || (!comment.trim() && override === '')} onClick={save} className="bg-[#0A0A0A] text-white hover:bg-[#262626] disabled:opacity-40">{busy ? 'Saving…' : 'Save day'}</Button>
        </div>
      </div>
    </div>
  );
}
