import { Link, router } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import { ArrowRight, Check, Loader2, Phone, X, XCircle } from 'lucide-react';

function WhatsAppIcon({ className }) {
  return (
    <svg className={className} viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
      <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51l-.57-.01c-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.872.118.571-.085 1.758-.719 2.006-1.413.247-.694.247-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z" />
    </svg>
  );
}

function toWhatsAppNumber(phone) {
  if (!phone) return '';
  let digits = String(phone).replace(/\D/g, '');
  if (digits.startsWith('00')) digits = digits.slice(2);
  return digits;
}

function toLocalDateTimeInput(iso) {
  if (!iso) return '';
  const d = new Date(iso);
  if (isNaN(d.getTime())) return '';
  const pad = (n) => String(n).padStart(2, '0');
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

function fromLocalDateTimeInput(value) {
  if (!value) return null;
  const d = new Date(value);
  if (isNaN(d.getTime())) return null;
  return d.toISOString();
}

export default function MentorAssignmentModal({ mentee, stages, assignableMentors = [], programLeader = null, onClose }) {
  const initial = mentee.assignment ?? {};
  const [mentorId, setMentorId] = useState(mentee.mentor_user_id ? String(mentee.mentor_user_id) : '');
  const [dueAt, setDueAt] = useState(toLocalDateTimeInput(initial.due_at));
  const [stageNotes, setStageNotes] = useState(initial.stage_notes ?? '');
  const [saveState, setSaveState] = useState('idle');

  const orderedStages = useMemo(
    () => (stages ?? []).slice().sort((a, b) => Number(a.position) - Number(b.position)),
    [stages],
  );
  const currentIndex = orderedStages.findIndex((s) => s.id === mentee.current_stage_id);
  const nextStage = currentIndex >= 0 ? orderedStages[currentIndex + 1] : null;

  useEffect(() => {
    const onKey = (e) => {
      if (e.key === 'Escape') onClose();
    };
    document.addEventListener('keydown', onKey);
    return () => document.removeEventListener('keydown', onKey);
  }, [onClose]);

  const save = ({ thenClose = false } = {}) => {
    setSaveState('saving');
    router.patch(
      `/livehost/mentoring/mentees/${mentee.id}/current-stage`,
      {
        mentor_user_id: mentorId ? Number(mentorId) : null,
        due_at: fromLocalDateTimeInput(dueAt),
        stage_notes: stageNotes || null,
      },
      {
        preserveScroll: true,
        preserveState: true,
        only: ['mentees'],
        onSuccess: () => {
          setSaveState('saved');
          setTimeout(() => setSaveState('idle'), 1500);
          if (thenClose) onClose();
        },
        onError: () => setSaveState('error'),
      },
    );
  };

  const moveTo = (stageId) => {
    router.patch(
      `/livehost/mentoring/mentees/${mentee.id}/stage`,
      { to_stage_id: stageId },
      { preserveScroll: true, onSuccess: () => onClose() },
    );
  };

  const drop = () => {
    if (!window.confirm(`Drop ${mentee.full_name} from this program?`)) {
      return;
    }
    router.patch(
      `/livehost/mentoring/mentees/${mentee.id}/drop`,
      { notes: null },
      { preserveScroll: true, onSuccess: () => onClose() },
    );
  };

  const overdue = mentee.assignment?.is_overdue;
  const leaderHint = programLeader ? `Defaults to leader ${programLeader.name}` : 'No program leader set';

  return (
    <div
      className="fixed inset-0 z-50 grid place-items-center bg-black/40 p-4"
      onMouseDown={(e) => {
        if (e.target === e.currentTarget) onClose();
      }}
    >
      <div className="w-full max-w-md rounded-[16px] bg-white p-6 shadow-[0_20px_60px_rgba(0,0,0,0.18)]">
        <div className="mb-4 flex items-start justify-between gap-3">
          <div className="min-w-0">
            <div className="font-mono text-[11px] text-[#737373]">{mentee.mentee_number}</div>
            <div className="mt-0.5 truncate text-[18px] font-semibold tracking-[-0.02em] text-[#0A0A0A]">
              {mentee.full_name}
            </div>
            <div className="mt-1 flex flex-wrap items-center gap-1.5">
              <span className="inline-flex items-center gap-1.5 rounded-md bg-[#F5F5F5] px-1.5 py-0.5 text-[11px] font-medium text-[#525252]">
                {orderedStages.find((s) => s.id === mentee.current_stage_id)?.name ?? 'Unassigned'}
              </span>
              {mentee.level && (
                <span
                  className="inline-flex items-center rounded-md px-1.5 py-0.5 text-[11px] font-medium"
                  style={{ backgroundColor: `${mentee.level.color}22`, color: mentee.level.color }}
                >
                  {mentee.level.name}
                </span>
              )}
              {overdue && (
                <span className="inline-flex items-center rounded bg-[#FEE2E2] px-1 py-0.5 text-[9.5px] font-semibold uppercase tracking-wide text-[#B91C1C]">
                  Overdue
                </span>
              )}
            </div>
          </div>
          <button type="button" onClick={onClose} className="rounded-md p-1 text-[#737373] hover:bg-[#F5F5F5] hover:text-[#0A0A0A]" aria-label="Close">
            <X className="h-4 w-4" strokeWidth={2} />
          </button>
        </div>

        {mentee.phone && (
          <div className="mb-4 flex items-center justify-between gap-2 rounded-lg border border-[#EAEAEA] bg-[#FAFAFA] px-3 py-2">
            <div className="min-w-0">
              <div className="text-[10.5px] font-medium uppercase tracking-wide text-[#A3A3A3]">Phone</div>
              <div className="truncate font-mono text-[13px] text-[#0A0A0A]">{mentee.phone}</div>
            </div>
            <div className="flex shrink-0 items-center gap-1.5">
              <a href={`https://wa.me/${toWhatsAppNumber(mentee.phone)}`} target="_blank" rel="noopener noreferrer" className="inline-flex items-center gap-1 rounded-md bg-[#25D366] px-2.5 py-1.5 text-[12px] font-medium text-white hover:bg-[#1FB855]">
                <WhatsAppIcon className="h-3.5 w-3.5" />
                WhatsApp
              </a>
              <a href={`tel:${String(mentee.phone).replace(/[^\d+]/g, '')}`} className="inline-flex items-center gap-1 rounded-md border border-[#EAEAEA] bg-white px-2.5 py-1.5 text-[12px] font-medium text-[#0A0A0A] hover:bg-[#F5F5F5]">
                <Phone className="h-3.5 w-3.5" strokeWidth={2} />
                Call
              </a>
            </div>
          </div>
        )}

        <div className="space-y-4">
          <div>
            <label htmlFor="mentor-assign" className="mb-1 block text-[11.5px] font-medium uppercase tracking-wide text-[#737373]">
              Mentor
            </label>
            <select
              id="mentor-assign"
              value={mentorId}
              onChange={(e) => setMentorId(e.target.value)}
              className="w-full rounded-lg border border-[#EAEAEA] bg-white px-3 py-2 text-[13px] text-[#0A0A0A] focus:outline-none focus:ring-2 focus:ring-[#10B981]/20"
            >
              <option value="">— Use program leader —</option>
              {assignableMentors.map((u) => (
                <option key={u.id} value={u.id}>
                  {u.name}{u.is_top_host_eligible ? ' ★' : ''}
                </option>
              ))}
            </select>
            <div className="mt-1 text-[11px] text-[#A3A3A3]">{leaderHint}</div>
          </div>

          <div>
            <label htmlFor="mentor-due" className="mb-1 block text-[11.5px] font-medium uppercase tracking-wide text-[#737373]">
              Due
            </label>
            <input
              id="mentor-due"
              type="datetime-local"
              value={dueAt}
              onChange={(e) => setDueAt(e.target.value)}
              className="w-full rounded-lg border border-[#EAEAEA] bg-white px-3 py-2 text-[13px] text-[#0A0A0A] focus:outline-none focus:ring-2 focus:ring-[#10B981]/20"
            />
          </div>

          <div>
            <label htmlFor="mentor-notes" className="mb-1 block text-[11.5px] font-medium uppercase tracking-wide text-[#737373]">
              Stage notes
            </label>
            <textarea
              id="mentor-notes"
              rows={3}
              value={stageNotes}
              onChange={(e) => setStageNotes(e.target.value)}
              placeholder="Notes scoped to this stage…"
              className="w-full resize-y rounded-lg border border-[#EAEAEA] bg-white px-3 py-2 text-[13px] text-[#0A0A0A] focus:outline-none focus:ring-2 focus:ring-[#10B981]/20"
            />
            <div className="mt-1 text-[11px] text-[#A3A3A3]">
              {saveState === 'saving' && <span className="inline-flex items-center gap-1"><Loader2 className="h-3 w-3 animate-spin" /> Saving…</span>}
              {saveState === 'saved' && <span className="inline-flex items-center gap-1 text-[#047857]"><Check className="h-3 w-3" strokeWidth={3} /> Saved</span>}
              {saveState === 'error' && <span className="text-[#B91C1C]">Failed to save</span>}
              {saveState === 'idle' && 'Click Save to persist changes for this stage.'}
            </div>
          </div>
        </div>

        <div className="mt-5 flex flex-wrap items-center justify-between gap-2 border-t border-[#F0F0F0] pt-4">
          <Link href={`/livehost/mentoring/mentees/${mentee.id}`} className="text-[12.5px] font-medium text-[#0A0A0A] underline-offset-2 hover:underline">
            Open full profile
          </Link>
          <div className="flex items-center gap-2">
            <button type="button" onClick={drop} className="inline-flex items-center gap-1 rounded-md border border-[#FCA5A5] bg-white px-2.5 py-1.5 text-[12.5px] font-medium text-[#B91C1C] hover:bg-[#FEF2F2]">
              <XCircle className="h-3.5 w-3.5" strokeWidth={2} />
              Drop
            </button>
            <button type="button" onClick={() => save({ thenClose: true })} disabled={saveState === 'saving'} className="inline-flex items-center gap-1 rounded-md border border-[#EAEAEA] bg-white px-2.5 py-1.5 text-[12.5px] font-medium text-[#0A0A0A] hover:bg-[#F5F5F5] disabled:opacity-60">
              {saveState === 'saving' ? <Loader2 className="h-3.5 w-3.5 animate-spin" strokeWidth={2} /> : <Check className="h-3.5 w-3.5" strokeWidth={2.25} />}
              Save
            </button>
            {nextStage && (
              <button type="button" onClick={() => moveTo(nextStage.id)} className="inline-flex items-center gap-1 rounded-md bg-[#0A0A0A] px-2.5 py-1.5 text-[12.5px] font-medium text-white hover:bg-[#262626]">
                Move to {nextStage.name}
                <ArrowRight className="h-3.5 w-3.5" strokeWidth={2.25} />
              </button>
            )}
          </div>
        </div>
      </div>
    </div>
  );
}
