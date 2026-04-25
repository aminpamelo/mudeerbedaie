import { Head, Link, router, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState } from 'react';
import {
  ArrowLeft,
  ArrowRight,
  BadgeCheck,
  Ban,
  Check,
  ChevronDown,
  Copy,
  Loader2,
  RotateCcw,
  Star,
  UserCheck,
  UserPlus,
  XCircle,
} from 'lucide-react';
import LiveHostLayout, { TopBar } from '@/livehost/layouts/LiveHostLayout';
import { Button } from '@/livehost/components/ui/button';
import { Input } from '@/livehost/components/ui/input';
import { Label } from '@/livehost/components/ui/label';

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
    restored: 'Restored',
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
    restored: 'bg-[#ECFDF5] text-[#047857]',
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

function renderValue(field, value) {
  if (value === null || value === undefined || value === '') {
    return <span className="text-[#A3A3A3]">—</span>;
  }
  switch (field.type) {
    case 'select':
    case 'radio':
      return (field.options ?? []).find((o) => o.value === value)?.label ?? value;
    case 'checkbox_group': {
      const arr = Array.isArray(value) ? value : [];
      if (arr.length === 0) {
        return <span className="text-[#A3A3A3]">—</span>;
      }
      const labels = arr.map(
        (v) => (field.options ?? []).find((o) => o.value === v)?.label ?? v,
      );
      return (
        <div className="flex flex-wrap gap-1">
          {labels.map((l, i) => (
            <span
              key={i}
              className="inline-flex items-center rounded bg-[#F5F5F5] px-1.5 py-0.5 text-[11px] font-medium text-[#525252]"
            >
              {l}
            </span>
          ))}
        </div>
      );
    }
    case 'file':
      return (
        <a
          href={`/storage/${value}`}
          target="_blank"
          rel="noreferrer"
          className="text-[#0A0A0A] underline"
        >
          Download
        </a>
      );
    case 'date':
      return new Date(value).toLocaleDateString();
    case 'datetime':
      return new Date(value).toLocaleString();
    default:
      return <span className="whitespace-pre-wrap">{String(value)}</span>;
  }
}

function ApplicationTab({ applicant }) {
  const schema = applicant.form_schema_snapshot ?? { pages: [] };
  const data = applicant.form_data ?? {};

  return (
    <div className="space-y-6">
      {(schema.pages ?? []).map((page) => (
        <div key={page.id}>
          <h3 className="mb-3 text-[11px] font-semibold uppercase tracking-[0.12em] text-[#737373]">
            {page.title}
          </h3>
          <dl className="divide-y divide-[#F0F0F0] rounded-[12px] border border-[#EAEAEA] bg-white">
            {(page.fields ?? [])
              .filter((f) => !['heading', 'paragraph'].includes(f.type))
              .map((field) => (
                <div
                  key={field.id}
                  className="grid grid-cols-[160px_1fr] gap-3 px-4 py-3"
                >
                  <dt className="text-[13px] font-medium text-[#525252]">
                    {field.label}
                  </dt>
                  <dd className="text-[13.5px] text-[#0A0A0A]">
                    {renderValue(field, data[field.id])}
                  </dd>
                </div>
              ))}
          </dl>
        </div>
      ))}

      <div className="rounded-[16px] border border-[#EAEAEA] bg-white p-6 shadow-[0_1px_2px_rgba(0,0,0,0.04)]">
        <div className="mb-4 text-[11px] font-semibold uppercase tracking-[0.08em] text-[#737373]">
          Campaign
        </div>
        <div className="grid grid-cols-2 gap-5">
          <div>
            <div className="mb-1 text-[11px] font-medium uppercase tracking-wide text-[#737373]">
              Campaign
            </div>
            <div className="text-[14px] text-[#0A0A0A]">
              {applicant.campaign?.title ?? <span className="text-[#A3A3A3]">—</span>}
            </div>
          </div>
          <div>
            <div className="mb-1 text-[11px] font-medium uppercase tracking-wide text-[#737373]">
              Applied
            </div>
            <div className="text-[14px] text-[#0A0A0A]">{formatDate(applicant.applied_at)}</div>
          </div>
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
  const [hireOpen, setHireOpen] = useState(false);
  const [hireForm, setHireForm] = useState({
    full_name: applicant.full_name ?? '',
    email: applicant.email ?? '',
    phone: applicant.phone ?? '',
  });
  const [hireErrors, setHireErrors] = useState({});
  const [busy, setBusy] = useState(false);
  const moveMenuRef = useRef(null);

  useEffect(() => {
    setHireForm({
      full_name: applicant.full_name ?? '',
      email: applicant.email ?? '',
      phone: applicant.phone ?? '',
    });
    setHireErrors({});
  }, [applicant.id, applicant.full_name, applicant.email, applicant.phone]);

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
  const isHired = applicant.status === 'hired';
  const isRejected = applicant.status === 'rejected';

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

  const restore = () => {
    setBusy(true);
    router.patch(
      `/livehost/recruitment/applicants/${applicant.id}/restore`,
      {},
      {
        preserveScroll: true,
        onFinish: () => setBusy(false),
      },
    );
  };

  const submitHire = () => {
    setBusy(true);
    setHireErrors({});
    router.post(
      `/livehost/recruitment/applicants/${applicant.id}/hire`,
      hireForm,
      {
        preserveScroll: true,
        onSuccess: () => {
          setHireOpen(false);
        },
        onError: (errors) => {
          setHireErrors(errors ?? {});
        },
        onFinish: () => setBusy(false),
      },
    );
  };

  if (isHired) {
    return <HiredPanel applicant={applicant} />;
  }

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
            {isRejected && (
              <Button
                type="button"
                disabled={busy || applicant.current_stage_id === null}
                onClick={restore}
                className="gap-1.5 bg-[#10B981] text-white hover:bg-[#059669] disabled:opacity-50"
                title={
                  applicant.current_stage_id === null
                    ? 'No stage to restore to'
                    : `Restore to ${applicant.current_stage?.name ?? 'previous stage'}`
                }
              >
                <RotateCcw className="h-3.5 w-3.5" strokeWidth={2.25} />
                Restore to {applicant.current_stage?.name ?? 'stage'}
              </Button>
            )}
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
              disabled={!isActive || !isFinalStage || busy}
              onClick={() => setHireOpen(true)}
              className="gap-1.5 bg-[#10B981] text-white hover:bg-[#059669] disabled:bg-[#10B981]/40 disabled:text-white/80"
              title={
                isFinalStage
                  ? 'Hire this applicant'
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

      {hireOpen && (
        <div className="fixed inset-0 z-50 grid place-items-center bg-black/40 p-4">
          <div className="w-full max-w-md rounded-[16px] bg-white p-6 shadow-lg">
            <div className="mb-1 flex items-center gap-2 text-[18px] font-semibold tracking-[-0.015em] text-[#0A0A0A]">
              <UserCheck className="h-4 w-4 text-[#10B981]" strokeWidth={2.25} />
              Hire {applicant.full_name}?
            </div>
            <p className="mb-4 text-[13px] text-[#737373]">
              This creates a new <span className="font-semibold text-[#0A0A0A]">live_host</span> user. You
              can copy a password reset link afterward.
            </p>

            <div className="space-y-3">
              <div className="space-y-1.5">
                <Label className="text-[12px] font-medium text-[#0A0A0A]">Full name</Label>
                <Input
                  value={hireForm.full_name}
                  onChange={(e) => setHireForm((f) => ({ ...f, full_name: e.target.value }))}
                />
                {hireErrors.full_name && (
                  <p className="text-xs text-[#F43F5E]">{hireErrors.full_name}</p>
                )}
              </div>
              <div className="space-y-1.5">
                <Label className="text-[12px] font-medium text-[#0A0A0A]">Email</Label>
                <Input
                  type="email"
                  value={hireForm.email}
                  onChange={(e) => setHireForm((f) => ({ ...f, email: e.target.value }))}
                />
                {hireErrors.email && (
                  <p className="text-xs text-[#F43F5E]">{hireErrors.email}</p>
                )}
              </div>
              <div className="space-y-1.5">
                <Label className="text-[12px] font-medium text-[#0A0A0A]">Phone</Label>
                <Input
                  value={hireForm.phone}
                  onChange={(e) => setHireForm((f) => ({ ...f, phone: e.target.value }))}
                />
                {hireErrors.phone && (
                  <p className="text-xs text-[#F43F5E]">{hireErrors.phone}</p>
                )}
              </div>
            </div>

            <div className="mt-5 flex justify-end gap-2">
              <Button
                type="button"
                variant="ghost"
                disabled={busy}
                onClick={() => setHireOpen(false)}
              >
                Cancel
              </Button>
              <Button
                type="button"
                disabled={busy}
                onClick={submitHire}
                className="bg-[#10B981] text-white hover:bg-[#059669]"
              >
                {busy ? 'Hiring…' : 'Confirm hire'}
              </Button>
            </div>
          </div>
        </div>
      )}
    </>
  );
}

function HiredPanel({ applicant }) {
  const [copyState, setCopyState] = useState('idle'); // idle | loading | copied | error

  const copyResetLink = async () => {
    setCopyState('loading');
    try {
      const csrf = document
        .querySelector('meta[name="csrf-token"]')
        ?.getAttribute('content');

      const response = await fetch(
        `/livehost/recruitment/applicants/${applicant.id}/password-reset-link`,
        {
          method: 'POST',
          credentials: 'same-origin',
          headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            ...(csrf ? { 'X-CSRF-TOKEN': csrf } : {}),
          },
        },
      );

      if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
      }
      const data = await response.json();
      if (!data?.url) {
        throw new Error('Missing URL');
      }
      await navigator.clipboard.writeText(data.url);
      setCopyState('copied');
      window.setTimeout(() => setCopyState('idle'), 2500);
    } catch {
      setCopyState('error');
      window.setTimeout(() => setCopyState('idle'), 3000);
    }
  };

  return (
    <div className="fixed bottom-0 left-0 right-0 z-40 border-t border-[#D1FAE5] bg-[#ECFDF5]/95 backdrop-blur">
      <div className="flex items-center justify-between gap-4 px-8 py-4">
        <div className="flex items-center gap-3">
          <div className="grid h-9 w-9 place-items-center rounded-full bg-[#10B981] text-white">
            <BadgeCheck className="h-5 w-5" strokeWidth={2.25} />
          </div>
          <div>
            <div className="text-[14px] font-semibold tracking-[-0.01em] text-[#065F46]">
              Hired — new user ID: #{applicant.hired_user_id ?? '—'}
            </div>
            <div className="text-[12px] text-[#047857]">
              {applicant.email} · Next, send them a password reset link and create their live host
              profile.
            </div>
          </div>
        </div>

        <div className="flex items-center gap-2">
          <Button
            type="button"
            variant="outline"
            onClick={copyResetLink}
            disabled={copyState === 'loading'}
            className="gap-1.5 border-[#10B981] text-[#047857] hover:bg-white"
          >
            {copyState === 'loading' && <Loader2 className="h-3.5 w-3.5 animate-spin" />}
            {copyState === 'copied' && <Check className="h-3.5 w-3.5" strokeWidth={2.5} />}
            {copyState !== 'loading' && copyState !== 'copied' && (
              <Copy className="h-3.5 w-3.5" />
            )}
            {copyState === 'copied'
              ? 'Copied!'
              : copyState === 'error'
                ? 'Failed, try again'
                : copyState === 'loading'
                  ? 'Generating…'
                  : 'Copy password reset link'}
          </Button>

          {applicant.hired_user_id && (
            <Link
              href={`/livehost/hosts/create?user_id=${applicant.hired_user_id}`}
            >
              <Button
                type="button"
                className="gap-1.5 bg-[#10B981] text-white hover:bg-[#059669]"
              >
                <UserPlus className="h-3.5 w-3.5" />
                Create Live Host profile
                <ArrowRight className="h-3.5 w-3.5" strokeWidth={2.25} />
              </Button>
            </Link>
          )}
        </div>
      </div>
    </div>
  );
}
