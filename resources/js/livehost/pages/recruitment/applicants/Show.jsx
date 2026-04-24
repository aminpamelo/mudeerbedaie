import { Head, Link, router, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState } from 'react';
import {
  ArrowLeft,
  ArrowRight,
  BadgeCheck,
  Ban,
  Check,
  ChevronDown,
  Loader2,
  Star,
  UserCheck,
  XCircle,
} from 'lucide-react';
import LiveHostLayout, { TopBar } from '@/livehost/layouts/LiveHostLayout';
import { Button } from '@/livehost/components/ui/button';

function StatusBadge({ status }) {
  const map = {
    active: { label: 'Active', tone: 'bg-[#ECFDF5] text-[#047857]' },
    rejected: { label: 'Rejected', tone: 'bg-[#FEE2E2] text-[#B91C1C]' },
    hired: { label: 'Hired', tone: 'bg-[#E0E7FF] text-[#4338CA]' },
    withdrawn: { label: 'Withdrawn', tone: 'bg-[#F5F5F5] text-[#525252]' },
  };
  const entry = map[status] ?? map.active;
  return (
    <span
      className={`inline-flex items-center rounded-full px-2.5 py-1 text-[11.5px] font-medium ${entry.tone}`}
    >
      {entry.label}
    </span>
  );
}

function formatDate(iso) {
  if (!iso) {
    return '—';
  }
  try {
    return new Date(iso).toLocaleString();
  } catch {
    return iso;
  }
}

function actionLabel(action) {
  const map = {
    applied: 'Applied',
    advanced: 'Advanced',
    reverted: 'Reverted',
    rejected: 'Rejected',
    hired: 'Hired',
    note: 'Note',
  };
  return map[action] ?? action;
}

function actionTone(action) {
  const map = {
    applied: 'bg-[#F5F5F5] text-[#525252]',
    advanced: 'bg-[#ECFDF5] text-[#047857]',
    reverted: 'bg-[#FEF3C7] text-[#B45309]',
    rejected: 'bg-[#FEE2E2] text-[#B91C1C]',
    hired: 'bg-[#E0E7FF] text-[#4338CA]',
    note: 'bg-[#F5F5F5] text-[#525252]',
  };
  return map[action] ?? 'bg-[#F5F5F5] text-[#525252]';
}

export default function ApplicantShow() {
  const { applicant, stages, history } = usePage().props;
  const [activeTab, setActiveTab] = useState('application');

  return (
    <>
      <Head title={applicant.full_name} />
      <TopBar
        breadcrumb={['Live Host Desk', 'Recruitment', 'Applicants', applicant.full_name]}
        actions={
          <Link href="/livehost/recruitment/applicants">
            <Button variant="ghost" className="gap-1.5 text-[#737373] hover:text-[#0A0A0A]">
              <ArrowLeft className="h-3.5 w-3.5" />
              Back to board
            </Button>
          </Link>
        }
      />

      <div className="space-y-6 p-8 pb-32">
        {/* Header card */}
        <div className="flex items-start justify-between gap-6 rounded-[16px] border border-[#EAEAEA] bg-white p-6 shadow-[0_1px_2px_rgba(0,0,0,0.04)]">
          <div className="flex items-center gap-4">
            <div className="grid h-16 w-16 place-items-center rounded-xl bg-gradient-to-br from-[#10B981] to-[#059669] text-2xl font-semibold tracking-[-0.02em] text-white">
              {(applicant.full_name ?? '?').slice(0, 1).toUpperCase()}
            </div>
            <div className="min-w-0">
              <div className="flex items-center gap-2">
                <span className="font-mono text-[11.5px] text-[#737373]">
                  {applicant.applicant_number}
                </span>
                <StatusBadge status={applicant.status} />
              </div>
              <div className="mt-1 text-2xl font-semibold tracking-[-0.02em] text-[#0A0A0A]">
                {applicant.full_name}
              </div>
              <div className="mt-0.5 truncate text-sm text-[#737373]">
                {applicant.email} · {applicant.phone}
              </div>
            </div>
          </div>
          <div className="shrink-0 text-right">
            <div className="text-[11px] uppercase tracking-wide text-[#737373]">Current stage</div>
            <div className="mt-1 text-[15px] font-semibold tracking-[-0.01em] text-[#0A0A0A]">
              {applicant.current_stage?.name ?? '—'}
            </div>
            {applicant.current_stage?.is_final && (
              <span className="mt-1 inline-flex items-center gap-1 rounded-full bg-[#ECFDF5] px-2 py-0.5 text-[10.5px] font-medium text-[#047857]">
                <BadgeCheck className="h-3 w-3" strokeWidth={2.5} /> Final stage
              </span>
            )}
            {applicant.rating > 0 && (
              <div className="mt-2 flex justify-end gap-0.5">
                {Array.from({ length: applicant.rating }).map((_, i) => (
                  <Star
                    key={i}
                    className="h-3.5 w-3.5 fill-[#F59E0B] text-[#F59E0B]"
                    strokeWidth={1.5}
                  />
                ))}
              </div>
            )}
            <div className="mt-2 text-[11px] text-[#737373]">
              Applied {applicant.applied_at_human ?? '—'}
            </div>
          </div>
        </div>

        {/* Tabs */}
        <div className="flex items-center gap-6 border-b border-[#EAEAEA]">
          {[
            { id: 'application', label: 'Application' },
            { id: 'activity', label: `Activity · ${history?.length ?? 0}` },
            { id: 'notes', label: 'Notes' },
          ].map((tab) => (
            <button
              key={tab.id}
              type="button"
              onClick={() => setActiveTab(tab.id)}
              className={[
                '-mb-px border-b-2 px-1 pb-3 text-sm font-medium transition-colors',
                activeTab === tab.id
                  ? 'border-[#0A0A0A] text-[#0A0A0A]'
                  : 'border-transparent text-[#737373] hover:text-[#0A0A0A]',
              ].join(' ')}
            >
              {tab.label}
            </button>
          ))}
        </div>

        {activeTab === 'application' && <ApplicationTab applicant={applicant} />}
        {activeTab === 'activity' && <ActivityTab history={history} />}
        {activeTab === 'notes' && <NotesTab applicant={applicant} />}
      </div>

      <ActionBar applicant={applicant} stages={stages} />
    </>
  );
}

ApplicantShow.layout = (page) => <LiveHostLayout>{page}</LiveHostLayout>;

function Field({ label, children }) {
  return (
    <div>
      <div className="mb-1 text-[11px] font-medium uppercase tracking-wide text-[#737373]">
        {label}
      </div>
      <div className="text-[14px] text-[#0A0A0A]">{children || <span className="text-[#A3A3A3]">—</span>}</div>
    </div>
  );
}

function ApplicationTab({ applicant }) {
  return (
    <div className="space-y-6">
      <div className="rounded-[16px] border border-[#EAEAEA] bg-white p-6 shadow-[0_1px_2px_rgba(0,0,0,0.04)]">
        <div className="mb-4 text-[11px] font-semibold uppercase tracking-[0.08em] text-[#737373]">
          Contact
        </div>
        <div className="grid grid-cols-2 gap-5">
          <Field label="Full name">{applicant.full_name}</Field>
          <Field label="Email">{applicant.email}</Field>
          <Field label="Phone">{applicant.phone}</Field>
          <Field label="IC number">{applicant.ic_number}</Field>
          <Field label="Location">{applicant.location}</Field>
          <Field label="Source">{applicant.source}</Field>
        </div>
      </div>

      <div className="rounded-[16px] border border-[#EAEAEA] bg-white p-6 shadow-[0_1px_2px_rgba(0,0,0,0.04)]">
        <div className="mb-4 text-[11px] font-semibold uppercase tracking-[0.08em] text-[#737373]">
          Live streaming profile
        </div>
        <div className="space-y-5">
          <Field label="Platforms">
            {applicant.platforms?.length > 0 ? (
              <div className="flex flex-wrap gap-1.5">
                {applicant.platforms.map((p) => (
                  <span
                    key={p}
                    className="inline-flex items-center rounded-md bg-[#F5F5F5] px-2 py-0.5 text-[11.5px] font-medium uppercase tracking-wide text-[#525252]"
                  >
                    {p}
                  </span>
                ))}
              </div>
            ) : null}
          </Field>
          <Field label="Experience summary">
            {applicant.experience_summary ? (
              <p className="whitespace-pre-wrap text-[13.5px] leading-relaxed text-[#0A0A0A]">
                {applicant.experience_summary}
              </p>
            ) : null}
          </Field>
          <Field label="Motivation">
            {applicant.motivation ? (
              <p className="whitespace-pre-wrap text-[13.5px] leading-relaxed text-[#0A0A0A]">
                {applicant.motivation}
              </p>
            ) : null}
          </Field>
          <Field label="Resume">
            {applicant.resume_path ? (
              <span className="inline-flex items-center gap-1.5 rounded-md bg-[#F5F5F5] px-2 py-1 font-mono text-[11.5px] text-[#525252]">
                {applicant.resume_path}
              </span>
            ) : null}
          </Field>
        </div>
      </div>

      <div className="rounded-[16px] border border-[#EAEAEA] bg-white p-6 shadow-[0_1px_2px_rgba(0,0,0,0.04)]">
        <div className="mb-4 text-[11px] font-semibold uppercase tracking-[0.08em] text-[#737373]">
          Campaign
        </div>
        <div className="grid grid-cols-2 gap-5">
          <Field label="Campaign">{applicant.campaign?.title}</Field>
          <Field label="Applied">{formatDate(applicant.applied_at)}</Field>
        </div>
      </div>
    </div>
  );
}

function ActivityTab({ history }) {
  if (!history || history.length === 0) {
    return (
      <div className="grid place-items-center rounded-[16px] border border-dashed border-[#EAEAEA] bg-white px-8 py-16 text-center">
        <div className="text-sm text-[#737373]">No activity yet.</div>
      </div>
    );
  }
  return (
    <div className="rounded-[16px] border border-[#EAEAEA] bg-white p-6 shadow-[0_1px_2px_rgba(0,0,0,0.04)]">
      <ol className="relative space-y-5 pl-6 before:absolute before:left-[7px] before:top-1 before:bottom-1 before:w-px before:bg-[#EAEAEA]">
        {history.map((event, index) => (
          <li key={event.id} className="relative">
            <span
              className={[
                'absolute -left-6 top-1 h-3.5 w-3.5 rounded-full border-2 bg-white',
                index === 0 ? 'border-[#10B981]' : 'border-[#EAEAEA]',
              ].join(' ')}
            />
            <div className="flex items-baseline justify-between gap-4">
              <div className="min-w-0">
                <div className="flex items-center gap-2">
                  <span
                    className={`inline-flex items-center rounded-md px-1.5 py-0.5 text-[10.5px] font-semibold uppercase tracking-wide ${actionTone(event.action)}`}
                  >
                    {actionLabel(event.action)}
                  </span>
                  <span className="text-[13.5px] font-medium text-[#0A0A0A]">
                    {event.from_stage?.name ?? '—'}
                    <span className="mx-1.5 text-[#A3A3A3]">→</span>
                    {event.to_stage?.name ?? '—'}
                  </span>
                </div>
                {event.notes && (
                  <div className="mt-1 whitespace-pre-wrap text-[13px] text-[#525252]">
                    {event.notes}
                  </div>
                )}
                <div className="mt-1 text-[11px] text-[#A3A3A3]">
                  {event.created_at_human}
                  {event.changed_by?.name ? ` · ${event.changed_by.name}` : ''}
                </div>
              </div>
            </div>
          </li>
        ))}
      </ol>
    </div>
  );
}

function NotesTab({ applicant }) {
  const [notes, setNotes] = useState(applicant.notes ?? '');
  const [saveState, setSaveState] = useState('idle'); // idle | saving | saved | error
  const timerRef = useRef(null);
  const lastSavedRef = useRef(applicant.notes ?? '');

  useEffect(() => {
    setNotes(applicant.notes ?? '');
    lastSavedRef.current = applicant.notes ?? '';
  }, [applicant.id, applicant.notes]);

  const save = (value) => {
    setSaveState('saving');
    router.patch(
      `/livehost/recruitment/applicants/${applicant.id}/notes`,
      { notes: value },
      {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
          lastSavedRef.current = value;
          setSaveState('saved');
          window.setTimeout(() => setSaveState('idle'), 1500);
        },
        onError: () => setSaveState('error'),
      },
    );
  };

  const handleChange = (e) => {
    const value = e.target.value;
    setNotes(value);
    if (timerRef.current) {
      window.clearTimeout(timerRef.current);
    }
    timerRef.current = window.setTimeout(() => {
      if (value !== lastSavedRef.current) {
        save(value);
      }
    }, 500);
  };

  useEffect(() => {
    return () => {
      if (timerRef.current) {
        window.clearTimeout(timerRef.current);
      }
    };
  }, []);

  return (
    <div className="rounded-[16px] border border-[#EAEAEA] bg-white p-6 shadow-[0_1px_2px_rgba(0,0,0,0.04)]">
      <div className="mb-2 flex items-center justify-between">
        <div className="text-[11px] font-semibold uppercase tracking-[0.08em] text-[#737373]">
          Admin notes
        </div>
        <div className="text-[11px] text-[#A3A3A3]">
          {saveState === 'saving' && (
            <span className="inline-flex items-center gap-1">
              <Loader2 className="h-3 w-3 animate-spin" /> Saving…
            </span>
          )}
          {saveState === 'saved' && (
            <span className="inline-flex items-center gap-1 text-[#047857]">
              <Check className="h-3 w-3" strokeWidth={3} /> Saved
            </span>
          )}
          {saveState === 'error' && <span className="text-[#B91C1C]">Failed to save</span>}
          {saveState === 'idle' && 'Auto-saves 500 ms after you stop typing.'}
        </div>
      </div>
      <textarea
        value={notes}
        onChange={handleChange}
        rows={10}
        placeholder="Private notes visible to admins only…"
        className="w-full resize-y rounded-lg border border-[#EAEAEA] bg-white px-3 py-2 text-[13.5px] leading-relaxed text-[#0A0A0A] focus:outline-none focus:ring-2 focus:ring-[#10B981]/20"
      />
    </div>
  );
}

function ActionBar({ applicant, stages }) {
  const [moveMenuOpen, setMoveMenuOpen] = useState(false);
  const [rejectOpen, setRejectOpen] = useState(false);
  const [rejectNotes, setRejectNotes] = useState('');
  const [busy, setBusy] = useState(false);
  const moveMenuRef = useRef(null);

  useEffect(() => {
    const onClick = (e) => {
      if (moveMenuRef.current && !moveMenuRef.current.contains(e.target)) {
        setMoveMenuOpen(false);
      }
    };
    document.addEventListener('mousedown', onClick);
    return () => document.removeEventListener('mousedown', onClick);
  }, []);

  const orderedStages = useMemo(
    () => (stages ?? []).slice().sort((a, b) => Number(a.position) - Number(b.position)),
    [stages],
  );

  const currentIndex = orderedStages.findIndex((s) => s.id === applicant.current_stage_id);
  const nextStage = currentIndex >= 0 ? orderedStages[currentIndex + 1] : null;
  const isFinalStage = Boolean(applicant.current_stage?.is_final);
  const isActive = applicant.status === 'active';

  const moveTo = (stageId) => {
    if (!stageId || busy) {
      return;
    }
    setBusy(true);
    router.patch(
      `/livehost/recruitment/applicants/${applicant.id}/stage`,
      { to_stage_id: stageId },
      {
        preserveScroll: true,
        onFinish: () => {
          setBusy(false);
          setMoveMenuOpen(false);
        },
      },
    );
  };

  const reject = () => {
    setBusy(true);
    router.patch(
      `/livehost/recruitment/applicants/${applicant.id}/reject`,
      { notes: rejectNotes || null },
      {
        preserveScroll: true,
        onFinish: () => {
          setBusy(false);
          setRejectOpen(false);
          setRejectNotes('');
        },
      },
    );
  };

  const hireClick = () => {
    // Milestone 5 — backend not wired yet.
    window.alert('Hire action is not yet available (Milestone 5).');
  };

  return (
    <>
      <div className="fixed bottom-0 left-0 right-0 z-40 border-t border-[#EAEAEA] bg-white/95 backdrop-blur">
        <div className="flex items-center justify-between gap-3 px-8 py-3">
          <div className="text-[12px] text-[#737373]">
            {isActive ? (
              <>
                On <span className="font-semibold text-[#0A0A0A]">{applicant.current_stage?.name ?? '—'}</span>
                {nextStage && (
                  <>
                    {' · next is '}
                    <span className="font-semibold text-[#0A0A0A]">{nextStage.name}</span>
                  </>
                )}
              </>
            ) : (
              <>Applicant is <span className="font-semibold text-[#0A0A0A]">{applicant.status}</span>.</>
            )}
          </div>

          <div className="flex items-center gap-2">
            <Button
              type="button"
              disabled={!isActive || !nextStage || busy}
              onClick={() => nextStage && moveTo(nextStage.id)}
              className="gap-1.5 bg-[#0A0A0A] text-white hover:bg-[#262626] disabled:opacity-50"
            >
              Move to next stage
              <ArrowRight className="h-3.5 w-3.5" strokeWidth={2.25} />
            </Button>

            <div className="relative" ref={moveMenuRef}>
              <Button
                type="button"
                variant="outline"
                disabled={!isActive || busy}
                onClick={() => setMoveMenuOpen((v) => !v)}
                className="gap-1.5"
              >
                Move to…
                <ChevronDown className="h-3.5 w-3.5" />
              </Button>
              {moveMenuOpen && (
                <div className="absolute bottom-full right-0 mb-1.5 w-[220px] overflow-hidden rounded-lg border border-[#EAEAEA] bg-white shadow-[0_10px_30px_rgba(0,0,0,0.1)]">
                  <div className="max-h-[280px] overflow-y-auto p-1">
                    {orderedStages.length === 0 && (
                      <div className="px-2.5 py-3 text-center text-[12px] text-[#737373]">
                        No stages configured.
                      </div>
                    )}
                    {orderedStages.map((stage) => {
                      const isCurrent = stage.id === applicant.current_stage_id;
                      return (
                        <button
                          key={stage.id}
                          type="button"
                          onClick={() => moveTo(stage.id)}
                          disabled={isCurrent}
                          className={[
                            'flex w-full items-center justify-between rounded-md px-2.5 py-2 text-left text-[13px]',
                            isCurrent
                              ? 'cursor-default bg-[#F5F5F5] text-[#737373]'
                              : 'text-[#0A0A0A] hover:bg-[#F5F5F5]',
                          ].join(' ')}
                        >
                          <span>{stage.name}</span>
                          {isCurrent && <Check className="h-3.5 w-3.5 text-[#10B981]" strokeWidth={3} />}
                          {stage.is_final && !isCurrent && (
                            <span className="rounded-full bg-[#ECFDF5] px-1.5 py-0.5 text-[9.5px] font-medium uppercase tracking-wide text-[#047857]">
                              Final
                            </span>
                          )}
                        </button>
                      );
                    })}
                  </div>
                </div>
              )}
            </div>

            <Button
              type="button"
              variant="outline"
              disabled={!isActive || busy}
              onClick={() => setRejectOpen(true)}
              className="gap-1.5 border-[#F43F5E] text-[#F43F5E] hover:bg-[#FFF1F2]"
            >
              <XCircle className="h-3.5 w-3.5" />
              Reject
            </Button>

            <Button
              type="button"
              disabled={!isActive || !isFinalStage}
              onClick={hireClick}
              className="gap-1.5 bg-[#10B981] text-white hover:bg-[#059669] disabled:bg-[#10B981]/40 disabled:text-white/80"
              title={
                isFinalStage
                  ? 'Hire this applicant (Milestone 5)'
                  : 'Move applicant to the final stage before hiring'
              }
            >
              <UserCheck className="h-3.5 w-3.5" />
              Hire
            </Button>
          </div>
        </div>
      </div>

      {rejectOpen && (
        <div className="fixed inset-0 z-50 grid place-items-center bg-black/40 p-4">
          <div className="w-full max-w-md rounded-[16px] bg-white p-6 shadow-lg">
            <div className="mb-1 flex items-center gap-2 text-[18px] font-semibold tracking-[-0.015em] text-[#0A0A0A]">
              <Ban className="h-4 w-4 text-[#F43F5E]" strokeWidth={2.25} />
              Reject {applicant.full_name}?
            </div>
            <p className="mb-4 text-[13px] text-[#737373]">
              The applicant will move out of the active board. You can optionally leave a reason
              for your records.
            </p>
            <textarea
              value={rejectNotes}
              onChange={(e) => setRejectNotes(e.target.value)}
              rows={4}
              placeholder="Reason (optional)"
              className="mb-4 w-full resize-none rounded-lg border border-[#EAEAEA] bg-white px-3 py-2 text-[13.5px] text-[#0A0A0A] focus:outline-none focus:ring-2 focus:ring-[#F43F5E]/25"
            />
            <div className="flex justify-end gap-2">
              <Button
                type="button"
                variant="ghost"
                disabled={busy}
                onClick={() => {
                  setRejectOpen(false);
                  setRejectNotes('');
                }}
              >
                Cancel
              </Button>
              <Button
                type="button"
                disabled={busy}
                onClick={reject}
                className="bg-[#F43F5E] text-white hover:bg-[#E11D48]"
              >
                {busy ? 'Rejecting…' : 'Reject applicant'}
              </Button>
            </div>
          </div>
        </div>
      )}
    </>
  );
}
