import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { ArrowLeft, Pencil, Trash2 } from 'lucide-react';
import LiveHostLayout, { TopBar } from '@/livehost/layouts/LiveHostLayout';
import StatusChip from '@/livehost/components/StatusChip';
import { Button } from '@/livehost/components/ui/button';

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

export default function SessionSlotsShow() {
  const { sessionSlot } = usePage().props;
  const [confirmDelete, setConfirmDelete] = useState(false);
  const [deleting, setDeleting] = useState(false);

  const handleDelete = () => {
    setDeleting(true);
    router.delete(`/livehost/session-slots/${sessionSlot.id}`, {
      onFinish: () => {
        setDeleting(false);
        setConfirmDelete(false);
      },
    });
  };

  const dayColor = DAY_BADGE_COLORS[sessionSlot.dayOfWeek] ?? 'bg-[#F5F5F5] text-[#737373]';

  return (
    <>
      <Head title={`Session slot #${sessionSlot.id}`} />
      <TopBar
        breadcrumb={['Live Host Desk', 'Session Slots', `#${sessionSlot.id}`]}
        actions={
          <div className="flex gap-2">
            <Link href="/livehost/session-slots">
              <Button variant="ghost" className="gap-1.5 text-[#737373] hover:text-[#0A0A0A]">
                <ArrowLeft className="w-3.5 h-3.5" />
                Back
              </Button>
            </Link>
            <Link href={`/livehost/session-slots/${sessionSlot.id}/edit`}>
              <Button variant="ghost" className="gap-1.5 text-[#0A0A0A]">
                <Pencil className="w-3.5 h-3.5" />
                Edit
              </Button>
            </Link>
            <Button
              onClick={() => setConfirmDelete(true)}
              className="gap-1.5 bg-transparent text-[#F43F5E] border border-[#F43F5E] hover:bg-[#FFF1F2]"
            >
              <Trash2 className="w-3.5 h-3.5" />
              Delete
            </Button>
          </div>
        }
      />

      <div className="p-8 space-y-6">
        {/* Hero block */}
        <div className="bg-white border border-[#EAEAEA] rounded-[16px] shadow-[0_1px_2px_rgba(0,0,0,0.04)] p-6 flex items-start gap-6">
          <span
            className={`grid h-20 w-20 flex-shrink-0 place-items-center rounded-xl text-sm font-semibold uppercase tracking-wide ${dayColor}`}
          >
            <span className="text-2xl font-bold tracking-[-0.02em]">
              {DAY_NAMES[sessionSlot.dayOfWeek]?.slice(0, 3) ?? '—'}
            </span>
          </span>
          <div className="flex-1 min-w-0">
            <div className="text-2xl font-semibold tracking-[-0.02em] mb-1">
              {DAY_NAMES[sessionSlot.dayOfWeek] ?? 'Unknown day'} ·{' '}
              <span className="tabular-nums">{sessionSlot.timeSlotLabel}</span>
            </div>
            <div className="text-sm text-[#737373]">
              {sessionSlot.platformAccount ?? 'No platform account'}
              {sessionSlot.platformType ? ` · ${sessionSlot.platformType}` : ''}
              {' · '}
              {sessionSlot.hostName ? `Host: ${sessionSlot.hostName}` : 'Unassigned'}
            </div>
            {sessionSlot.remarks && (
              <p className="mt-3 text-sm leading-relaxed text-[#404040] whitespace-pre-wrap">
                {sessionSlot.remarks}
              </p>
            )}
          </div>
          <div className="flex flex-col items-end gap-2">
            <StatusChip variant={statusChipVariant(sessionSlot.status)}>
              {STATUS_LABELS[sessionSlot.status] ?? sessionSlot.status}
            </StatusChip>
            <span
              className={`inline-flex rounded-md px-2 py-1 text-[11px] font-semibold uppercase tracking-wide ${
                sessionSlot.isTemplate
                  ? 'bg-[#F5F3FF] text-[#5B21B6]'
                  : 'bg-[#FFF7ED] text-[#B45309]'
              }`}
            >
              {sessionSlot.isTemplate ? 'Weekly template' : 'Dated'}
            </span>
          </div>
        </div>

        {/* Metadata row */}
        <div className="grid grid-cols-3 gap-4">
          <InfoTile label="Host" value={sessionSlot.hostName ?? 'Unassigned'} hint={sessionSlot.hostEmail} />
          <InfoTile
            label="Platform account"
            value={sessionSlot.platformAccount ?? '—'}
            hint={sessionSlot.platformType}
          />
          <InfoTile
            label="Schedule date"
            value={sessionSlot.scheduleDate ?? 'Recurring'}
            hint={sessionSlot.isTemplate ? 'Weekly template' : 'One-off'}
          />
        </div>

        <div className="grid grid-cols-2 gap-4">
          <InfoTile label="Created by" value={sessionSlot.createdByName ?? '—'} />
          <InfoTile
            label="Last updated"
            value={sessionSlot.updatedAt ? formatDate(sessionSlot.updatedAt) : '—'}
          />
        </div>
      </div>

      {confirmDelete && (
        <div className="fixed inset-0 bg-black/40 grid place-items-center z-50">
          <div className="bg-white rounded-[16px] p-6 max-w-md shadow-lg">
            <div className="font-semibold text-lg mb-2 tracking-[-0.02em]">Delete session slot?</div>
            <p className="text-sm text-[#737373] mb-4">
              This removes the {DAY_NAMES[sessionSlot.dayOfWeek]} {sessionSlot.timeSlotLabel}{' '}
              assignment on {sessionSlot.platformAccount ?? 'this platform'}.
            </p>
            <div className="flex justify-end gap-2">
              <Button variant="ghost" onClick={() => setConfirmDelete(false)} disabled={deleting}>
                Cancel
              </Button>
              <Button
                onClick={handleDelete}
                disabled={deleting}
                className="bg-[#F43F5E] text-white hover:bg-[#E11D48]"
              >
                {deleting ? 'Deleting' : 'Confirm delete'}
              </Button>
            </div>
          </div>
        </div>
      )}
    </>
  );
}

SessionSlotsShow.layout = (page) => <LiveHostLayout>{page}</LiveHostLayout>;

function InfoTile({ label, value, hint }) {
  return (
    <div className="bg-white border border-[#EAEAEA] rounded-[16px] shadow-[0_1px_2px_rgba(0,0,0,0.04)] p-5">
      <div className="text-[11px] uppercase tracking-wide text-[#737373] font-medium">{label}</div>
      <div className="text-lg font-semibold tracking-[-0.015em] mt-2 truncate">{value}</div>
      {hint && <div className="mt-1 text-[11px] text-[#737373] truncate">{hint}</div>}
    </div>
  );
}

function formatDate(iso) {
  try {
    return new Date(iso).toLocaleString();
  } catch {
    return iso;
  }
}
