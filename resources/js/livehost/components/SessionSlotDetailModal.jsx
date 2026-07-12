import { useState } from 'react';
import { router } from '@inertiajs/react';
import {
  AlertTriangle,
  ArrowUpRight,
  Paperclip,
  Pencil,
  Radio,
  ShieldCheck,
  Trash2,
  Upload,
} from 'lucide-react';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/livehost/components/ui/dialog';
import { Button } from '@/livehost/components/ui/button';
import StatusChip from '@/livehost/components/StatusChip';
import SessionVerifyLinkPanel from '@/livehost/components/SessionVerifyLinkPanel';

const DAY_NAMES = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
const DAY_BADGE_COLORS = [
  'bg-[#FFF7ED] text-[#B45309]',
  'bg-[#F5F5F5] text-[#404040]',
  'bg-[#FDF2F8] text-[#9D174D]',
  'bg-[#ECFDF5] text-[#065F46]',
  'bg-[#FFFBEB] text-[#92400E]',
  'bg-[#EFF6FF] text-[#1D4ED8]',
  'bg-[#F5F3FF] text-[#5B21B6]',
];

const STATUS_LABELS = {
  scheduled: 'Scheduled',
  confirmed: 'Confirmed',
  in_progress: 'In progress',
  completed: 'Completed',
  cancelled: 'Cancelled',
};

function statusChipVariant(status) {
  switch (status) {
    case 'confirmed':
      return 'active';
    case 'in_progress':
      return 'live';
    case 'completed':
      return 'done';
    case 'cancelled':
      return 'suspended';
    case 'scheduled':
    default:
      return 'scheduled';
  }
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

function formatGmv(value) {
  const num = Number(value);
  if (!Number.isFinite(num)) {
    return '—';
  }
  const hasSen = num % 1 !== 0;
  return `RM ${num.toLocaleString(undefined, {
    minimumFractionDigits: hasSen ? 2 : 0,
    maximumFractionDigits: 2,
  })}`;
}

const SESSION_STATUS_META = {
  scheduled: { label: 'Scheduled', className: 'bg-[#EFF6FF] text-[#1D4ED8]' },
  live: { label: 'Live', className: 'bg-[#DCFCE7] text-[#166534]' },
  ended: { label: 'Ended', className: 'bg-[#F5F5F5] text-[#404040]' },
  completed: { label: 'Completed', className: 'bg-[#F5F5F5] text-[#404040]' },
  cancelled: { label: 'Cancelled', className: 'bg-[#FEE2E2] text-[#991B1B]' },
  missed: { label: 'Missed', className: 'bg-[#FEF3C7] text-[#92400E]' },
};

export default function SessionSlotDetailModal({
  open,
  onOpenChange,
  sessionSlot,
  onEdit,
  onOpenSession,
  onDeleted,
}) {
  const [deleting, setDeleting] = useState(false);

  if (!sessionSlot) {
    return null;
  }

  const dayColor = DAY_BADGE_COLORS[sessionSlot.dayOfWeek] ?? 'bg-[#F5F5F5] text-[#737373]';

  const handleDelete = () => {
    if (!window.confirm(`Delete the ${DAY_NAMES[sessionSlot.dayOfWeek]} ${sessionSlot.timeSlotLabel} assignment?`)) {
      return;
    }
    setDeleting(true);
    router.delete(`/livehost/session-slots/${sessionSlot.id}`, {
      preserveScroll: true,
      onFinish: () => {
        setDeleting(false);
      },
      onSuccess: () => {
        onOpenChange(false);
        if (onDeleted) {
          onDeleted(sessionSlot);
        }
      },
    });
  };

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-h-[90vh] overflow-y-auto border border-[#EAEAEA] bg-white text-[#0A0A0A] sm:max-w-[600px] lg:max-w-[940px]">
        <DialogHeader className="text-left">
          <DialogTitle className="text-[17px] font-semibold tracking-[-0.02em] text-[#0A0A0A]">
            Session slot details
          </DialogTitle>
          <DialogDescription className="sr-only">
            Assignment, host and schedule info for this session slot.
          </DialogDescription>
        </DialogHeader>

        <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
          {/* LEFT — identity, schedule, remarks (read-only context) */}
          <div className="space-y-4">
          {/* Hero */}
          <div className="flex items-start gap-4 rounded-[12px] border border-[#F0F0F0] bg-[#FAFAFA] p-4">
            <span
              className={`grid h-16 w-16 flex-shrink-0 place-items-center rounded-xl ${dayColor}`}
            >
              <span className="text-xl font-bold tracking-[-0.02em]">
                {DAY_NAMES[sessionSlot.dayOfWeek]?.slice(0, 3) ?? '—'}
              </span>
            </span>
            <div className="min-w-0 flex-1">
              <div className="text-[17px] font-semibold tracking-[-0.02em] text-[#0A0A0A]">
                {DAY_NAMES[sessionSlot.dayOfWeek] ?? 'Unknown day'} ·{' '}
                <span className="tabular-nums">{sessionSlot.timeSlotLabel}</span>
              </div>
              <div className="mt-1 text-[13px] font-medium text-[#0A0A0A]">
                {sessionSlot.liveAccountLabel ?? 'No creator account'}
              </div>
              <div className="mt-0.5 text-[12px] text-[#737373]">
                {sessionSlot.platformAccount ?? 'No shop'}
                {sessionSlot.platformType ? ` · ${sessionSlot.platformType}` : ''}
              </div>
              <div className="mt-2 flex items-center gap-2">
                <StatusChip variant={statusChipVariant(sessionSlot.status)}>
                  {STATUS_LABELS[sessionSlot.status] ?? sessionSlot.status}
                </StatusChip>
                <span
                  className={`inline-flex rounded-md px-2 py-0.5 text-[10.5px] font-semibold uppercase tracking-wide ${
                    sessionSlot.isTemplate
                      ? 'bg-[#F5F3FF] text-[#5B21B6]'
                      : 'bg-[#FFF7ED] text-[#B45309]'
                  }`}
                >
                  {sessionSlot.isTemplate ? 'Weekly template' : 'Dated'}
                </span>
              </div>
            </div>
          </div>

          {/* Metadata grid */}
          <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <DetailTile
              label="Creator account"
              value={sessionSlot.liveAccountLabel ?? '—'}
              hint={sessionSlot.creatorUserId ? `ID ${sessionSlot.creatorUserId}` : null}
            />
            <DetailTile
              label="Host"
              value={sessionSlot.hostName ?? 'Unassigned'}
              hint={sessionSlot.hostEmail}
            />
            <DetailTile
              label="Shop (promoted)"
              value={sessionSlot.platformAccount ?? '—'}
              hint={sessionSlot.platformType}
            />
            <DetailTile
              label="Schedule date"
              value={sessionSlot.scheduleDate ?? 'Recurring'}
              hint={sessionSlot.isTemplate ? 'Weekly template' : 'One-off'}
            />
            <DetailTile label="Created by" value={sessionSlot.createdByName ?? '—'} />
            <DetailTile
              label="Last updated"
              value={formatDate(sessionSlot.updatedAt)}
              wide
            />
          </div>

          {sessionSlot.remarks && (
            <div className="rounded-[12px] border border-[#F0F0F0] bg-white p-4">
              <div className="text-[11px] font-medium uppercase tracking-wide text-[#737373]">
                Remarks
              </div>
              <p className="mt-2 whitespace-pre-wrap text-[13px] leading-relaxed text-[#404040]">
                {sessionSlot.remarks}
              </p>
            </div>
          )}
          </div>

          {/* RIGHT — the live session + verification (the actionable side) */}
          <div className="space-y-4">
            <LiveSessionSection
              session={sessionSlot.session}
              onOpenSession={onOpenSession}
              onOpenChange={onOpenChange}
            />
          </div>
        </div>

        <DialogFooter className="justify-between gap-2 sm:justify-between">
          <Button
            type="button"
            onClick={handleDelete}
            disabled={deleting}
            className="gap-1.5 border border-[#F43F5E] bg-transparent text-[#F43F5E] hover:bg-[#FFF1F2]"
          >
            <Trash2 className="h-3.5 w-3.5" strokeWidth={2} />
            {deleting ? 'Deleting…' : 'Delete'}
          </Button>
          <div className="flex items-center gap-2">
            <Button
              type="button"
              variant="ghost"
              onClick={() => onOpenChange(false)}
              className="text-[#737373]"
            >
              Close
            </Button>
            <Button
              type="button"
              onClick={() => {
                onOpenChange(false);
                if (onEdit) {
                  onEdit(sessionSlot);
                }
              }}
              className="gap-1.5"
            >
              <Pencil className="h-3.5 w-3.5" strokeWidth={2} />
              Edit
            </Button>
          </div>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

function LiveSessionSection({ session, onOpenSession, onOpenChange }) {
  if (!session) {
    return (
      <div className="rounded-[12px] border border-dashed border-[#EAEAEA] bg-[#FAFAFA] px-4 py-3 text-[12.5px] text-[#737373]">
        No live session recorded against this slot yet. Once the host goes live and submits a recap, its upload &amp; verification status will show here.
      </div>
    );
  }

  const status =
    SESSION_STATUS_META[session.status] ?? {
      label: session.status ?? '—',
      className: 'bg-[#F5F5F5] text-[#404040]',
    };
  const uploaded = Boolean(session.uploaded);
  const needsUpload = Boolean(session.needsUpload);
  const overdue = Boolean(session.overdue);
  const isPending = (session.verificationStatus ?? 'pending') === 'pending';
  const attachmentCount = session.attachmentCount ?? session.attachments?.length ?? 0;

  const cta = needsUpload
    ? 'Open session · upload proof'
    : isPending
      ? 'Open session · verify'
      : 'Open session';

  return (
    <div className="rounded-[12px] border border-[#EAEAEA] bg-white p-4">
      <div className="flex items-center justify-between gap-3">
        <div className="flex items-center gap-2">
          <Radio className="h-4 w-4 text-[#737373]" strokeWidth={2} />
          <span className="text-[10.5px] font-medium uppercase tracking-wide text-[#A3A3A3]">
            Live session
          </span>
          <span className="font-mono text-[11px] text-[#737373]">{session.sessionId}</span>
        </div>
        <span
          className={`inline-flex rounded-md px-2 py-0.5 text-[10.5px] font-semibold uppercase tracking-wide ${status.className}`}
        >
          {status.label}
        </span>
      </div>

      {needsUpload && (
        <div className="mt-3 flex items-start gap-2 rounded-lg border border-[#FCD34D] bg-[#FEF3C7] px-3 py-2 text-[12px] font-medium text-[#92400E]">
          <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0" strokeWidth={2.2} />
          <span>
            {overdue
              ? 'This slot has passed with no live and no recap uploaded. Follow up with the host — nothing has been submitted.'
              : 'The session ended but the host hasn’t uploaded proof yet. It can’t be verified until a recap is uploaded.'}
          </span>
        </div>
      )}

      <div className="mt-3 grid grid-cols-2 gap-2.5">
        <MiniTile label="GMV (net)" value={formatGmv(session.gmvNet)} />

        <div className="rounded-[10px] border border-[#F0F0F0] bg-[#FAFAFA] p-2.5">
          <div className="text-[9.5px] font-medium uppercase tracking-wide text-[#737373]">
            Upload
          </div>
          <div
            className={`mt-1 inline-flex items-center gap-1 text-[12.5px] font-semibold ${
              needsUpload ? 'text-[#B45309]' : uploaded ? 'text-[#166534]' : 'text-[#525252]'
            }`}
          >
            {needsUpload ? (
              <>
                <Upload className="h-3 w-3" strokeWidth={2.4} />
                {overdue ? 'Overdue' : 'Needs upload'}
              </>
            ) : uploaded ? (
              <>
                <Paperclip className="h-3 w-3" strokeWidth={2.2} />
                Uploaded
              </>
            ) : (
              '—'
            )}
          </div>
          <div className="mt-0.5 text-[10px] text-[#A3A3A3]">
            {attachmentCount} file{attachmentCount === 1 ? '' : 's'}
          </div>
        </div>
      </div>

      {/* Inline TikTok-record verification — pick a record and Verify / Reject
          right here, without opening the full Live Session modal. */}
      <div className="mt-3">
        <SessionVerifyLinkPanel session={session} onDone={() => onOpenChange(false)} />
      </div>

      <button
        type="button"
        onClick={() => {
          onOpenChange(false);
          onOpenSession?.(session);
        }}
        className="mt-3 inline-flex w-full items-center justify-center gap-1.5 rounded-lg bg-ink px-3 py-2 text-[12.5px] font-semibold text-white transition-colors hover:bg-[#262626]"
      >
        <ShieldCheck className="h-3.5 w-3.5" strokeWidth={2.2} />
        {cta}
        <ArrowUpRight className="h-3.5 w-3.5" strokeWidth={2.2} />
      </button>
    </div>
  );
}

function MiniTile({ label, value }) {
  return (
    <div className="rounded-[10px] border border-[#F0F0F0] bg-[#FAFAFA] p-2.5">
      <div className="text-[9.5px] font-medium uppercase tracking-wide text-[#737373]">{label}</div>
      <div className="mt-1 truncate text-[13px] font-bold tabular-nums text-[#0A0A0A]">{value}</div>
    </div>
  );
}

function DetailTile({ label, value, hint, wide = false }) {
  return (
    <div
      className={`rounded-[12px] border border-[#F0F0F0] bg-white p-3.5 ${
        wide ? 'col-span-2' : ''
      }`}
    >
      <div className="text-[10px] font-medium uppercase tracking-wide text-[#737373]">{label}</div>
      <div className="mt-1.5 truncate text-[14px] font-semibold tracking-[-0.01em] text-[#0A0A0A]">
        {value}
      </div>
      {hint && <div className="mt-0.5 truncate text-[11px] text-[#737373]">{hint}</div>}
    </div>
  );
}
