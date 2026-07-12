import { useEffect, useState } from 'react';
import { router, useForm } from '@inertiajs/react';
import { BadgeCheck, Check, RotateCcw, XCircle } from 'lucide-react';
import { Button } from '@/livehost/components/ui/button';

/**
 * Self-contained "pick a TikTok record → Verify / Reject" panel for a live
 * session. Fetches candidate ActualLiveRecords for the session and drives the
 * same verify-link / verify endpoints the Live Session modal uses (both redirect
 * back(), so this works embedded anywhere — e.g. the Session slot detail modal).
 * On a successful verify/reject it calls onDone so the host can close/refresh,
 * since the parent's session snapshot won't reactively update after the reload.
 */
function formatGmvMyr(value) {
  const num = Number(value);
  // The API doesn't always return a live-attributed GMV breakdown; the sync
  // stores a -1 sentinel. Render that (and any non-finite value) as an em dash.
  if (!Number.isFinite(num) || num < 0) {
    return '—';
  }
  return `RM ${num.toLocaleString('en-MY', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  })}`;
}

function formatDateTime(iso) {
  if (!iso) {
    return '—';
  }
  const date = new Date(iso);
  if (Number.isNaN(date.getTime())) {
    return iso;
  }
  return date.toLocaleString(undefined, {
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
}

function formatTimeOnly(iso) {
  if (!iso) {
    return null;
  }
  const date = new Date(iso);
  if (Number.isNaN(date.getTime())) {
    return null;
  }
  return date.toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit' });
}

// The record's end time: prefer the reported ended_time, else launch + duration.
function recordEndTime(candidate) {
  if (candidate.endedTime) {
    return formatTimeOnly(candidate.endedTime);
  }
  if (candidate.launchedTime && candidate.durationSeconds) {
    return formatTimeOnly(
      new Date(new Date(candidate.launchedTime).getTime() + candidate.durationSeconds * 1000).toISOString(),
    );
  }
  return null;
}

const META = {
  pending: {
    label: 'Pending PIC review',
    badgeClass: 'bg-[#FEF3C7] text-[#92400E] border-[#FDE68A]',
    icon: BadgeCheck,
    iconClass: 'text-[#92400E]',
  },
  verified: {
    label: 'Verified',
    badgeClass: 'bg-[#DCFCE7] text-[#166534] border-[#BBF7D0]',
    icon: Check,
    iconClass: 'text-[#166534]',
  },
  rejected: {
    label: 'Rejected',
    badgeClass: 'bg-[#FEE2E2] text-[#991B1B] border-[#FECACA]',
    icon: XCircle,
    iconClass: 'text-[#991B1B]',
  },
};

export default function SessionVerifyLinkPanel({ session, onDone = null }) {
  const [candidates, setCandidates] = useState(null); // null = not loaded, [] = loaded empty
  // A split live (blip/reconnect) is 2+ records → multi-select.
  const [selectedIds, setSelectedIds] = useState(() => new Set());
  const [candidatesLoading, setCandidatesLoading] = useState(false);
  const [notesOpen, setNotesOpen] = useState(false);

  const toggleSelected = (id) => {
    setSelectedIds((prev) => {
      const next = new Set(prev);
      if (next.has(id)) {
        next.delete(id);
      } else {
        next.add(id);
      }
      return next;
    });
  };

  const verifyForm = useForm({
    verification_status: 'pending',
    verification_notes: '',
  });

  const current = session?.verificationStatus ?? 'pending';
  const isPending = current === 'pending';

  useEffect(() => {
    if (!session) {
      return;
    }
    verifyForm.setData({
      verification_status: session.verificationStatus ?? 'pending',
      verification_notes: session.verificationNotes ?? '',
    });
    setNotesOpen(false);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [session?.id]);

  // Load candidate TikTok records for a pending session.
  useEffect(() => {
    if (!session || current !== 'pending') {
      setCandidates(null);
      return undefined;
    }
    let cancelled = false;
    setCandidatesLoading(true);
    setCandidates(null);
    setSelectedIds(new Set());
    fetch(`/livehost/sessions/${session.id}/candidates`, {
      headers: { Accept: 'application/json' },
      credentials: 'same-origin',
    })
      .then((res) => (res.ok ? res.json() : { candidates: [] }))
      .then((data) => {
        if (cancelled) {
          return;
        }
        const list = data.candidates ?? [];
        setCandidates(list);
        // Pre-select the whole suggested split-live cluster.
        const suggested = list.filter((c) => c.isSuggested).map((c) => c.id);
        setSelectedIds(new Set(suggested));
      })
      .catch(() => {
        if (!cancelled) {
          setCandidates([]);
        }
      })
      .finally(() => {
        if (!cancelled) {
          setCandidatesLoading(false);
        }
      });
    return () => {
      cancelled = true;
    };
  }, [session?.id, current]);

  if (!session) {
    return null;
  }

  const hasCandidates = Array.isArray(candidates) && candidates.length > 0;
  const selectedRecords = hasCandidates ? candidates.filter((c) => selectedIds.has(c.id)) : [];
  const summedGmv = selectedRecords.reduce((sum, c) => {
    const v = Number(c.liveAttributedGmvMyr);
    return sum + (Number.isFinite(v) && v > 0 ? v : 0);
  }, 0);
  const canVerify = isPending && selectedIds.size > 0;
  const processing = verifyForm.processing;
  const { label, badgeClass, icon: Icon, iconClass } = META[current] ?? META.pending;

  const verify = () => {
    if (selectedIds.size === 0) {
      return;
    }
    router.post(
      `/livehost/sessions/${session.id}/verify-link`,
      { actual_live_record_id: [...selectedIds] },
      {
        preserveScroll: true,
        onSuccess: () => {
          setNotesOpen(false);
          onDone?.();
        },
      }
    );
  };

  const setStatus = (nextStatus) => {
    verifyForm.transform((data) => ({
      ...data,
      verification_status: nextStatus,
      verification_notes: data.verification_notes || null,
    }));
    verifyForm.post(`/livehost/sessions/${session.id}/verify`, {
      preserveScroll: true,
      onSuccess: () => {
        setNotesOpen(false);
        onDone?.();
      },
    });
  };

  return (
    <div className="rounded-[12px] border border-[#EAEAEA] bg-white p-4">
      <div className="flex items-center justify-between gap-3">
        <div className="flex items-center gap-2">
          <BadgeCheck className="h-4 w-4 text-[#737373]" strokeWidth={2} />
          <span className="text-[10.5px] font-medium uppercase tracking-wide text-[#A3A3A3]">
            Verification
          </span>
        </div>
        <span
          className={`inline-flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 text-[11.5px] font-medium ${badgeClass}`}
        >
          <Icon className={`h-3 w-3 ${iconClass}`} strokeWidth={2.2} />
          {label}
        </span>
      </div>

      {current !== 'pending' && (
        <div className="mt-3 text-[12px] leading-snug text-[#525252]">
          <div>
            By <span className="font-medium text-[#0A0A0A]">{session.verifiedByName ?? '—'}</span>
            {session.verifiedAt ? ` · ${formatDateTime(session.verifiedAt)}` : ''}
          </div>
          {session.verificationNotes && (
            <div className="mt-1 rounded-md bg-[#FAFAFA] px-2.5 py-1.5 text-[11.5px] text-[#404040]">
              {session.verificationNotes}
            </div>
          )}
        </div>
      )}

      {isPending && (
        <div className="mt-3">
          <div className="mb-1.5 flex items-center justify-between gap-2">
            <span className="text-[11px] font-medium uppercase tracking-wide text-[#737373]">
              Pick the TikTok record(s) to link
            </span>
            <span className="text-[10.5px] text-[#A3A3A3]">Select all segments of a split live</span>
          </div>
          {candidatesLoading && (
            <div className="rounded-md border border-[#EAEAEA] bg-[#FAFAFA] px-3 py-2 text-[12px] text-[#737373]">
              Loading candidates…
            </div>
          )}
          {!candidatesLoading && !hasCandidates && (
            <div className="rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-[12px] text-amber-900">
              No TikTok records found for this day + host. Upload the xlsx report or wait for API
              sync — Verify is blocked until a record is linked.
            </div>
          )}
          {!candidatesLoading && hasCandidates && (
            <div className="flex max-h-[180px] flex-col gap-1.5 overflow-y-auto pr-1">
              {candidates.map((c) => {
                const selected = selectedIds.has(c.id);
                const durationMin = c.durationSeconds ? Math.round(c.durationSeconds / 60) : null;
                const endTime = recordEndTime(c);
                return (
                  <label
                    key={c.id}
                    className={`flex cursor-pointer items-start gap-2 rounded-md border p-2 transition-colors ${
                      selected ? 'border-[#10B981] bg-[#ECFDF5]' : 'border-[#EAEAEA] hover:bg-[#FAFAFA]'
                    }`}
                  >
                    <input
                      type="checkbox"
                      checked={selected}
                      onChange={() => toggleSelected(c.id)}
                      className="mt-0.5 accent-[#10B981]"
                    />
                    <div className="min-w-0 flex-1 text-[12px]">
                      <div className="flex flex-wrap items-center gap-x-1.5 gap-y-0.5 text-[#0A0A0A]">
                        <span className="font-medium">
                          {formatDateTime(c.launchedTime)}
                          {endTime ? ` – ${endTime}` : ''}
                        </span>
                        {durationMin !== null && (
                          <span className="text-[#737373]">· {durationMin}m</span>
                        )}
                        {c.isSuggested && (
                          <span className="rounded-full bg-[#DCFCE7] px-1.5 py-0.5 text-[10px] font-medium text-[#15803D]">
                            Suggested
                          </span>
                        )}
                        <span className="ml-auto rounded bg-[#F5F5F5] px-1.5 py-0.5 text-[10px] font-medium uppercase tracking-wide text-[#737373]">
                          {c.source === 'csv_import' ? 'CSV' : 'API'}
                        </span>
                      </div>
                      <div className="mt-1 flex flex-wrap items-center gap-x-2 gap-y-0.5 text-[11.5px] text-[#737373]">
                        <span className="inline-flex items-baseline gap-1">
                          <span className="text-[10px] uppercase tracking-wide text-[#A3A3A3]">
                            Total GMV
                          </span>
                          <span className="font-semibold text-[#0A0A0A]">
                            {formatGmvMyr(c.gmvMyr)}
                          </span>
                        </span>
                        <span className="text-[#D4D4D4]">·</span>
                        <span className="inline-flex items-baseline gap-1">
                          <span className="text-[10px] uppercase tracking-wide text-[#A3A3A3]">
                            Live-attrib
                          </span>
                          <span className="font-medium">{formatGmvMyr(c.liveAttributedGmvMyr)}</span>
                        </span>
                        <span className="text-[#D4D4D4]">·</span>
                        <span>{c.viewers ?? 0} viewers</span>
                        {c.creatorHandle ? (
                          <>
                            <span className="text-[#D4D4D4]">·</span>
                            <span>{c.creatorHandle}</span>
                          </>
                        ) : null}
                      </div>
                    </div>
                  </label>
                );
              })}
            </div>
          )}
          {!candidatesLoading && hasCandidates && selectedIds.size > 0 && (
            <div className="mt-2 flex items-center justify-between rounded-md border border-[#D1FAE5] bg-[#ECFDF5] px-3 py-2 text-[12px]">
              <span className="text-[#065F46]">
                {selectedIds.size} record{selectedIds.size === 1 ? '' : 's'} selected
                {selectedIds.size > 1 ? ' (split live)' : ''}
              </span>
              <span className="font-semibold text-[#065F46]">Locks {formatGmvMyr(summedGmv)}</span>
            </div>
          )}
        </div>
      )}

      {notesOpen && (
        <div className="mt-3">
          <textarea
            value={verifyForm.data.verification_notes}
            onChange={(e) => verifyForm.setData('verification_notes', e.target.value)}
            rows={2}
            maxLength={1000}
            placeholder="Notes (optional) — visible to the PIC reviewing this session"
            className="w-full resize-none rounded-lg border border-[#EAEAEA] bg-white px-3 py-2 text-[12.5px] text-[#0A0A0A] focus:outline-none focus:ring-2 focus:ring-[#10B981]/20"
          />
          {verifyForm.errors.verification_notes && (
            <p className="mt-1 text-xs text-[#F43F5E]">{verifyForm.errors.verification_notes}</p>
          )}
        </div>
      )}

      <div className="mt-3 flex flex-wrap items-center gap-2">
        <button
          type="button"
          onClick={() => setNotesOpen((v) => !v)}
          className="inline-flex items-center gap-1 rounded-md border border-[#EAEAEA] bg-white px-2.5 py-1 text-[11.5px] font-medium text-[#404040] hover:bg-[#F5F5F5] hover:text-[#0A0A0A]"
        >
          {notesOpen ? 'Hide notes' : 'Add notes'}
        </button>

        <div className="ml-auto flex flex-wrap gap-2">
          {current !== 'pending' && (
            <Button
              type="button"
              variant="ghost"
              onClick={() => setStatus('pending')}
              disabled={processing}
              className="gap-1.5 text-[#737373]"
            >
              <RotateCcw className="h-3.5 w-3.5" strokeWidth={2} />
              Reset
            </Button>
          )}
          {current !== 'rejected' && (
            <Button
              type="button"
              onClick={() => setStatus('rejected')}
              disabled={processing}
              className="gap-1.5 border border-[#FECACA] bg-white text-[#991B1B] hover:bg-[#FEF2F2]"
            >
              <XCircle className="h-3.5 w-3.5" strokeWidth={2} />
              Reject
            </Button>
          )}
          {current !== 'verified' && (
            <Button
              type="button"
              onClick={verify}
              disabled={processing || (isPending && !canVerify)}
              title={
                isPending && !hasCandidates
                  ? 'No TikTok records found — verification blocked'
                  : isPending && selectedIds.size === 0
                    ? 'Select at least one TikTok record first'
                    : undefined
              }
              className="gap-1.5 bg-[#10B981] text-white hover:bg-[#059669] disabled:cursor-not-allowed disabled:opacity-50"
            >
              <Check className="h-3.5 w-3.5" strokeWidth={2.5} />
              Verify
            </Button>
          )}
        </div>
      </div>
    </div>
  );
}
