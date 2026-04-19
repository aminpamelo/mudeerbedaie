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

function mapSessionStatus(status) {
  if (status === 'live') {
    return 'live';
  }
  if (status === 'ended') {
    return 'done';
  }
  if (status === 'cancelled') {
    return 'suspended';
  }
  return 'scheduled';
}

function formatDate(iso) {
  if (!iso) {
    return 'Unscheduled';
  }
  try {
    return new Date(iso).toLocaleString();
  } catch {
    return iso;
  }
}

export default function SchedulesShow() {
  const { schedule, recentSessions } = usePage().props;
  const [confirmDelete, setConfirmDelete] = useState(false);
  const [deleting, setDeleting] = useState(false);

  const handleDelete = () => {
    setDeleting(true);
    router.delete(`/livehost/schedules/${schedule.id}`, {
      onFinish: () => {
        setDeleting(false);
        setConfirmDelete(false);
      },
    });
  };

  const dayColor = DAY_BADGE_COLORS[schedule.dayOfWeek] ?? 'bg-[#F5F5F5] text-[#737373]';

  return (
    <>
      <Head title={`Schedule #${schedule.id}`} />
      <TopBar
        breadcrumb={['Live Host Desk', 'Schedules', `#${schedule.id}`]}
        actions={
          <div className="flex gap-2">
            <Link href="/livehost/schedules">
              <Button variant="ghost" className="gap-1.5 text-[#737373] hover:text-[#0A0A0A]">
                <ArrowLeft className="w-3.5 h-3.5" />
                Back
              </Button>
            </Link>
            <Link href={`/livehost/schedules/${schedule.id}/edit`}>
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
              {DAY_NAMES[schedule.dayOfWeek]?.slice(0, 3) ?? '—'}
            </span>
          </span>
          <div className="flex-1 min-w-0">
            <div className="text-2xl font-semibold tracking-[-0.02em] mb-1">
              {DAY_NAMES[schedule.dayOfWeek] ?? 'Unknown day'} ·{' '}
              <span className="tabular-nums">
                {schedule.startTime}–{schedule.endTime}
              </span>
            </div>
            <div className="text-sm text-[#737373]">
              {schedule.hostName ? `Host: ${schedule.hostName}` : 'No host assigned'} ·{' '}
              {schedule.platformAccount ?? 'No platform account'}
              {schedule.platformType ? ` · ${schedule.platformType}` : ''}
            </div>
            {schedule.remarks && (
              <p className="mt-3 text-sm leading-relaxed text-[#404040] whitespace-pre-wrap">
                {schedule.remarks}
              </p>
            )}
          </div>
          <div className="flex flex-col items-end gap-2">
            <StatusChip variant={schedule.isActive ? 'active' : 'inactive'} />
            {schedule.isRecurring && (
              <span className="text-[11px] font-medium uppercase tracking-wide text-[#737373]">
                Weekly
              </span>
            )}
          </div>
        </div>

        {/* Metadata row */}
        <div className="grid grid-cols-3 gap-4">
          <InfoTile label="Host" value={schedule.hostName ?? 'Unassigned'} />
          <InfoTile label="Platform account" value={schedule.platformAccount ?? '—'} />
          <InfoTile
            label="Created by"
            value={schedule.createdByName ?? '—'}
          />
        </div>

        {/* Recent sessions */}
        <div className="bg-white border border-[#EAEAEA] rounded-[16px] shadow-[0_1px_2px_rgba(0,0,0,0.04)] p-5">
          <div className="font-semibold text-[15px] tracking-[-0.015em] mb-3">
            Recent sessions on this platform
            {schedule.hostName ? ` + host` : ''}
          </div>
          {recentSessions.length === 0 ? (
            <div className="text-sm text-[#737373] py-6 text-center">
              No sessions recorded yet for this platform
              {schedule.hostName ? ' + host combination' : ''}.
            </div>
          ) : (
            <ul className="space-y-0">
              {recentSessions.map((s) => (
                <li
                  key={s.id}
                  className="flex items-center justify-between py-2.5 border-b border-[#F0F0F0] last:border-0"
                >
                  <div className="min-w-0">
                    <div className="text-sm font-medium text-[#0A0A0A] truncate">
                      #{s.sessionId}{' '}
                      <span className="text-[#737373] font-normal">
                        on {s.platformAccount ?? '—'}
                      </span>
                    </div>
                    <div className="text-xs text-[#737373] mt-0.5">
                      {s.hostName ? `${s.hostName} · ` : ''}
                      {formatDate(s.scheduledStart)}
                    </div>
                  </div>
                  <StatusChip variant={mapSessionStatus(s.status)} />
                </li>
              ))}
            </ul>
          )}
        </div>
      </div>

      {confirmDelete && (
        <div className="fixed inset-0 bg-black/40 grid place-items-center z-50">
          <div className="bg-white rounded-[16px] p-6 max-w-md shadow-lg">
            <div className="font-semibold text-lg mb-2 tracking-[-0.02em]">Delete schedule?</div>
            <p className="text-sm text-[#737373] mb-4">
              This removes the {DAY_NAMES[schedule.dayOfWeek]} {schedule.startTime}–
              {schedule.endTime} slot. Existing sessions are unaffected.
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

SchedulesShow.layout = (page) => <LiveHostLayout>{page}</LiveHostLayout>;

function InfoTile({ label, value }) {
  return (
    <div className="bg-white border border-[#EAEAEA] rounded-[16px] shadow-[0_1px_2px_rgba(0,0,0,0.04)] p-5">
      <div className="text-[11px] uppercase tracking-wide text-[#737373] font-medium">{label}</div>
      <div className="text-lg font-semibold tracking-[-0.015em] mt-2 truncate">{value}</div>
    </div>
  );
}
