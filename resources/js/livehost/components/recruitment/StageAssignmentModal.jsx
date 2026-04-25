import { Link, router } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import { ArrowRight, Check, Loader2, X, XCircle } from 'lucide-react';

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

export default function StageAssignmentModal({
  applicant,
  stages,
  onClose,
}) {
  const initial = applicant.assignment ?? {};
  const [dueAt, setDueAt] = useState(toLocalDateTimeInput(initial.due_at));
  const [stageNotes, setStageNotes] = useState(initial.stage_notes ?? '');
  const [saveState, setSaveState] = useState('idle');

  const orderedStages = useMemo(
    () => (stages ?? []).slice().sort((a, b) => Number(a.position) - Number(b.position)),
    [stages],
  );
  const currentIndex = orderedStages.findIndex((s) => s.id === applicant.current_stage_id);
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
      `/livehost/recruitment/applicants/${applicant.id}/current-stage`,
      {
        assignee_id: null,
        due_at: fromLocalDateTimeInput(dueAt),
        stage_notes: stageNotes || null,
      },
      {
        preserveScroll: true,
        preserveState: true,
        only: ['applicants'],
        onSuccess: () => {
          setSaveState('saved');
          setTimeout(() => setSaveState('idle'), 1500);
          if (thenClose) onClose();
        },
        onError: () => setSaveState('error'),
      },
    );
  };

  const handleDueChange = (e) => {
    setDueAt(e.target.value);
  };

  const handleNotesChange = (e) => {
    setStageNotes(e.target.value);
  };

  const moveTo = (stageId) => {
    router.patch(
      `/livehost/recruitment/applicants/${applicant.id}/stage`,
      { to_stage_id: stageId },
      { preserveScroll: true, onSuccess: () => onClose() },
    );
  };

  const reject = () => {
    router.patch(
      `/livehost/recruitment/applicants/${applicant.id}/reject`,
      { notes: null },
      { preserveScroll: true, onSuccess: () => onClose() },
    );
  };

  const overdue = applicant.assignment?.is_overdue;

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
            <div className="font-mono text-[11px] text-[#737373]">{applicant.applicant_number}</div>
            <div className="mt-0.5 truncate text-[18px] font-semibold tracking-[-0.02em] text-[#0A0A0A]">
              {applicant.full_name}
            </div>
            <div className="mt-1 inline-flex items-center gap-1.5 rounded-md bg-[#F5F5F5] px-1.5 py-0.5 text-[11px] font-medium text-[#525252]">
              {orderedStages.find((s) => s.id === applicant.current_stage_id)?.name ?? 'Unassigned'}
              {overdue && (
                <span className="ml-1 inline-flex items-center rounded bg-[#FEE2E2] px-1 py-0.5 text-[9.5px] font-semibold uppercase tracking-wide text-[#B91C1C]">
                  Overdue
                </span>
              )}
            </div>
          </div>
          <button
            type="button"
            onClick={onClose}
            className="rounded-md p-1 text-[#737373] hover:bg-[#F5F5F5] hover:text-[#0A0A0A]"
            aria-label="Close"
          >
            <X className="h-4 w-4" strokeWidth={2} />
          </button>
        </div>

        <div className="space-y-4">
          <div>
            <label htmlFor="stage-assignment-due" className="mb-1 block text-[11.5px] font-medium uppercase tracking-wide text-[#737373]">
              Due
            </label>
            <input
              id="stage-assignment-due"
              type="datetime-local"
              value={dueAt}
              onChange={handleDueChange}
              className="w-full rounded-lg border border-[#EAEAEA] bg-white px-3 py-2 text-[13px] text-[#0A0A0A] focus:outline-none focus:ring-2 focus:ring-[#10B981]/20"
            />
          </div>

          <div>
            <label htmlFor="stage-assignment-notes" className="mb-1 block text-[11.5px] font-medium uppercase tracking-wide text-[#737373]">
              Stage notes
            </label>
            <textarea
              id="stage-assignment-notes"
              rows={4}
              value={stageNotes}
              onChange={handleNotesChange}
              placeholder="Notes scoped to this stage…"
              className="w-full resize-y rounded-lg border border-[#EAEAEA] bg-white px-3 py-2 text-[13px] text-[#0A0A0A] focus:outline-none focus:ring-2 focus:ring-[#10B981]/20"
            />
            <div className="mt-1 text-[11px] text-[#A3A3A3]">
              {saveState === 'saving' && (
                <span className="inline-flex items-center gap-1"><Loader2 className="h-3 w-3 animate-spin" /> Saving…</span>
              )}
              {saveState === 'saved' && (
                <span className="inline-flex items-center gap-1 text-[#047857]"><Check className="h-3 w-3" strokeWidth={3} /> Saved</span>
              )}
              {saveState === 'error' && <span className="text-[#B91C1C]">Failed to save</span>}
              {saveState === 'idle' && 'Click Save to persist changes for this stage.'}
            </div>
          </div>
        </div>

        <div className="mt-5 flex flex-wrap items-center justify-between gap-2 border-t border-[#F0F0F0] pt-4">
          <Link
            href={`/livehost/recruitment/applicants/${applicant.id}`}
            className="text-[12.5px] font-medium text-[#0A0A0A] underline-offset-2 hover:underline"
          >
            Open full profile
          </Link>
          <div className="flex items-center gap-2">
            <button
              type="button"
              onClick={reject}
              className="inline-flex items-center gap-1 rounded-md border border-[#FCA5A5] bg-white px-2.5 py-1.5 text-[12.5px] font-medium text-[#B91C1C] hover:bg-[#FEF2F2]"
            >
              <XCircle className="h-3.5 w-3.5" strokeWidth={2} />
              Reject
            </button>
            <button
              type="button"
              onClick={() => save({ thenClose: true })}
              disabled={saveState === 'saving'}
              className="inline-flex items-center gap-1 rounded-md border border-[#EAEAEA] bg-white px-2.5 py-1.5 text-[12.5px] font-medium text-[#0A0A0A] hover:bg-[#F5F5F5] disabled:opacity-60"
            >
              {saveState === 'saving' ? (
                <Loader2 className="h-3.5 w-3.5 animate-spin" strokeWidth={2} />
              ) : (
                <Check className="h-3.5 w-3.5" strokeWidth={2.25} />
              )}
              Save
            </button>
            {nextStage && (
              <button
                type="button"
                onClick={() => moveTo(nextStage.id)}
                className="inline-flex items-center gap-1 rounded-md bg-[#0A0A0A] px-2.5 py-1.5 text-[12.5px] font-medium text-white hover:bg-[#262626]"
              >
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
