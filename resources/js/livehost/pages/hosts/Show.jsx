import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { ArrowLeft, Pencil, Trash2 } from 'lucide-react';
import LiveHostLayout, { TopBar } from '@/livehost/layouts/LiveHostLayout';
import StatusChip from '@/livehost/components/StatusChip';
import { Button } from '@/livehost/components/ui/button';

function statusVariant(status) {
  if (status === 'active') {
    return 'active';
  }
  if (status === 'suspended') {
    return 'suspended';
  }
  return 'inactive';
}

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

export default function HostShow() {
  const { host, platformAccounts, recentSessions, stats } = usePage().props;
  const [confirmDelete, setConfirmDelete] = useState(false);
  const [deleting, setDeleting] = useState(false);

  const handleDelete = () => {
    setDeleting(true);
    router.delete(`/livehost/hosts/${host.id}`, {
      onFinish: () => {
        setDeleting(false);
        setConfirmDelete(false);
      },
    });
  };

  return (
    <>
      <Head title={host.name} />
      <TopBar
        breadcrumb={['Live Host Desk', 'Live Hosts', host.name]}
        actions={
          <div className="flex gap-2">
            <Link href="/livehost/hosts">
              <Button variant="ghost" className="gap-1.5 text-[#737373] hover:text-[#0A0A0A]">
                <ArrowLeft className="w-3.5 h-3.5" />
                Back
              </Button>
            </Link>
            <Link href={`/livehost/hosts/${host.id}/edit`}>
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
        <div className="bg-white border border-[#EAEAEA] rounded-[16px] shadow-[0_1px_2px_rgba(0,0,0,0.04)] p-6 flex items-center gap-6">
          <div className="w-20 h-20 rounded-xl bg-gradient-to-br from-[#10B981] to-[#059669] text-white grid place-items-center font-semibold text-2xl tracking-[-0.02em]">
            {host.initials}
          </div>
          <div className="flex-1 min-w-0">
            <div className="text-2xl font-semibold tracking-[-0.02em] mb-1 truncate">{host.name}</div>
            <div className="text-sm text-[#737373] truncate">
              {host.email} · {host.phone ?? 'No phone'} · ID {host.id}
            </div>
          </div>
          <StatusChip variant={statusVariant(host.status || 'inactive')} />
        </div>

        {/* Stats row */}
        <div className="grid grid-cols-3 gap-4">
          <StatTile label="Total sessions" value={stats.totalSessions} />
          <StatTile label="Completed" value={stats.completedSessions} />
          <StatTile label="Platform accounts" value={stats.platformAccounts} />
        </div>

        {/* Grid: platforms + sessions */}
        <div className="grid grid-cols-12 gap-4">
          <div className="col-span-5 bg-white border border-[#EAEAEA] rounded-[16px] shadow-[0_1px_2px_rgba(0,0,0,0.04)] p-5">
            <div className="font-semibold text-[15px] tracking-[-0.015em] mb-3">Platform accounts</div>
            {platformAccounts.length === 0 ? (
              <div className="text-sm text-[#737373] py-6 text-center">No platform accounts assigned.</div>
            ) : (
              <ul className="space-y-0">
                {platformAccounts.map((pa) => (
                  <li
                    key={pa.id}
                    className="flex items-center justify-between py-2.5 border-b border-[#F0F0F0] last:border-0"
                  >
                    <span className="text-sm font-medium text-[#0A0A0A]">{pa.name}</span>
                    <span className="text-xs text-[#737373] uppercase tracking-wide">
                      {pa.platformName ?? pa.platform ?? '—'}
                    </span>
                  </li>
                ))}
              </ul>
            )}
          </div>

          <div className="col-span-7 bg-white border border-[#EAEAEA] rounded-[16px] shadow-[0_1px_2px_rgba(0,0,0,0.04)] p-5">
            <div className="font-semibold text-[15px] tracking-[-0.015em] mb-3">Recent sessions</div>
            {recentSessions.length === 0 ? (
              <div className="text-sm text-[#737373] py-6 text-center">No sessions yet.</div>
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
                        <span className="text-[#737373] font-normal">on {s.platformAccount ?? '—'}</span>
                      </div>
                      <div className="text-xs text-[#737373] mt-0.5">{formatDate(s.scheduledStart)}</div>
                    </div>
                    <StatusChip variant={mapSessionStatus(s.status)} />
                  </li>
                ))}
              </ul>
            )}
          </div>
        </div>
      </div>

      {confirmDelete && (
        <div className="fixed inset-0 bg-black/40 grid place-items-center z-50">
          <div className="bg-white rounded-[16px] p-6 max-w-md shadow-lg">
            <div className="font-semibold text-lg mb-2 tracking-[-0.02em]">Delete {host.name}?</div>
            <p className="text-sm text-[#737373] mb-4">
              This soft-deletes the host. Their record stays in the database but is hidden from this list. This cannot be
              reversed from the dashboard.
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
                {deleting ? 'Deleting…' : 'Yes, delete'}
              </Button>
            </div>
          </div>
        </div>
      )}
    </>
  );
}

HostShow.layout = (page) => <LiveHostLayout>{page}</LiveHostLayout>;

function StatTile({ label, value }) {
  return (
    <div className="bg-white border border-[#EAEAEA] rounded-[16px] shadow-[0_1px_2px_rgba(0,0,0,0.04)] p-5">
      <div className="text-[11px] uppercase tracking-wide text-[#737373] font-medium">{label}</div>
      <div className="text-3xl font-semibold tracking-[-0.03em] mt-2 tabular-nums">{value}</div>
    </div>
  );
}
