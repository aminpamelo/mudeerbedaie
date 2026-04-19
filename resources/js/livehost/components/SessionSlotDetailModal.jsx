import { useState } from 'react';
import { router } from '@inertiajs/react';
import { Pencil, Trash2 } from 'lucide-react';
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

export default function SessionSlotDetailModal({
  open,
  onOpenChange,
  sessionSlot,
  onEdit,
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
      <DialogContent className="max-h-[90vh] overflow-y-auto border border-[#EAEAEA] bg-white text-[#0A0A0A] sm:max-w-[560px]">
        <DialogHeader className="text-left">
          <DialogTitle className="text-[17px] font-semibold tracking-[-0.02em] text-[#0A0A0A]">
            Session slot details
          </DialogTitle>
          <DialogDescription className="sr-only">
            Assignment, host and schedule info for this session slot.
          </DialogDescription>
        </DialogHeader>

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
              <div className="mt-1 text-[13px] text-[#737373]">
                {sessionSlot.platformAccount ?? 'No platform account'}
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
          <div className="grid grid-cols-2 gap-3">
            <DetailTile
              label="Host"
              value={sessionSlot.hostName ?? 'Unassigned'}
              hint={sessionSlot.hostEmail}
            />
            <DetailTile
              label="Platform account"
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
