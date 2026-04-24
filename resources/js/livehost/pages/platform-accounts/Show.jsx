import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { ArrowLeft, Pencil, Trash2 } from 'lucide-react';
import LiveHostLayout, { TopBar } from '@/livehost/layouts/LiveHostLayout';
import { Button } from '@/livehost/components/ui/button';

const PLATFORM_DOT_COLORS = {
  shopee: 'bg-[#F43F5E]',
  'tiktok-shop': 'bg-[#0A0A0A]',
  tiktok: 'bg-[#0A0A0A]',
  facebook: 'bg-[#1877F2]',
  instagram: 'bg-[#E1306C]',
  youtube: 'bg-[#FF0000]',
  lazada: 'bg-[#0F146D]',
};

function platformDotClass(slug) {
  if (!slug) {
    return 'bg-[#737373]';
  }

  return PLATFORM_DOT_COLORS[String(slug).toLowerCase()] || 'bg-[#737373]';
}

function StatusChip({ active }) {
  return (
    <span
      className={[
        'inline-flex items-center gap-1.5 rounded-full px-2.5 py-[3px] text-[11px] font-medium',
        active ? 'bg-[#ECFDF5] text-[#065F46]' : 'bg-[#F5F5F5] text-[#737373]',
      ].join(' ')}
    >
      <span
        className={['h-[6px] w-[6px] rounded-full', active ? 'bg-[#10B981]' : 'bg-[#A3A3A3]'].join(
          ' '
        )}
        aria-hidden="true"
      />
      {active ? 'Active' : 'Inactive'}
    </span>
  );
}

export default function PlatformAccountShow() {
  const { account, auth } = usePage().props;
  const canManagePlatformAccounts = Boolean(auth?.permissions?.canManagePlatformAccounts);
  const [confirmDelete, setConfirmDelete] = useState(false);
  const [deleting, setDeleting] = useState(false);

  const handleDelete = () => {
    setDeleting(true);
    router.delete(`/livehost/platform-accounts/${account.id}`, {
      onFinish: () => {
        setDeleting(false);
        setConfirmDelete(false);
      },
    });
  };

  return (
    <>
      <Head title={account.name} />
      <TopBar
        breadcrumb={['Live Host Desk', 'Platform Accounts', account.name]}
        actions={
          <div className="flex gap-2">
            <Link href="/livehost/platform-accounts">
              <Button variant="ghost" className="gap-1.5 text-[#737373] hover:text-[#0A0A0A]">
                <ArrowLeft className="w-3.5 h-3.5" />
                Back
              </Button>
            </Link>
            {canManagePlatformAccounts && (
              <Link href={`/livehost/platform-accounts/${account.id}/edit`}>
                <Button variant="ghost" className="gap-1.5 text-[#0A0A0A]">
                  <Pencil className="w-3.5 h-3.5" />
                  Edit
                </Button>
              </Link>
            )}
            {canManagePlatformAccounts && (
              <Button
                onClick={() => setConfirmDelete(true)}
                className="gap-1.5 bg-transparent text-[#F43F5E] border border-[#F43F5E] hover:bg-[#FFF1F2]"
              >
                <Trash2 className="w-3.5 h-3.5" />
                Delete
              </Button>
            )}
          </div>
        }
      />

      <div className="p-8 space-y-6">
        {/* Hero block */}
        <div className="bg-white border border-[#EAEAEA] rounded-[16px] shadow-[0_1px_2px_rgba(0,0,0,0.04)] p-6 flex items-center gap-6">
          <div className="grid h-16 w-16 place-items-center rounded-xl bg-gradient-to-br from-[#10B981] to-[#059669] text-white">
            <span
              className={[
                'h-[14px] w-[14px] rounded-full',
                platformDotClass(account.platform?.slug),
              ].join(' ')}
              aria-hidden="true"
            />
          </div>
          <div className="flex-1 min-w-0">
            <div className="text-2xl font-semibold tracking-[-0.02em] mb-1 truncate">{account.name}</div>
            <div className="text-sm text-[#737373] truncate">
              {account.platform?.displayName ?? account.platform?.name ?? '—'}
              {account.accountId ? ` · ${account.accountId}` : ''}
              {' · ID '}
              {account.id}
            </div>
          </div>
          <StatusChip active={account.isActive} />
        </div>

        {/* Stats row */}
        <div className="grid grid-cols-3 gap-4">
          <StatTile label="Schedules" value={account.schedules} />
          <StatTile label="Session slots" value={account.assignments ?? 0} />
          <StatTile label="Live sessions" value={account.sessions} />
        </div>

        {/* Detail grid */}
        <div className="grid grid-cols-12 gap-4">
          <div className="col-span-7 bg-white border border-[#EAEAEA] rounded-[16px] shadow-[0_1px_2px_rgba(0,0,0,0.04)] p-5">
            <div className="font-semibold text-[15px] tracking-[-0.015em] mb-3">Details</div>
            <dl className="grid grid-cols-2 gap-x-6 gap-y-3 text-sm">
              <DetailRow label="Platform" value={account.platform?.displayName ?? account.platform?.name ?? '—'} />
              <DetailRow label="Owner" value={account.user?.name ?? 'Unassigned'} />
              <DetailRow label="Account ID" value={account.accountId ?? '—'} />
              <DetailRow label="Country" value={account.countryCode ?? '—'} />
              <DetailRow label="Currency" value={account.currency ?? '—'} />
              <DetailRow label="Status" value={account.isActive ? 'Active' : 'Inactive'} />
            </dl>
          </div>
          <div className="col-span-5 bg-white border border-[#EAEAEA] rounded-[16px] shadow-[0_1px_2px_rgba(0,0,0,0.04)] p-5">
            <div className="font-semibold text-[15px] tracking-[-0.015em] mb-3">Description</div>
            {account.description ? (
              <p className="text-sm text-[#0A0A0A] whitespace-pre-wrap leading-relaxed">
                {account.description}
              </p>
            ) : (
              <p className="text-sm text-[#737373]">No description.</p>
            )}
          </div>
        </div>
      </div>

      {confirmDelete && canManagePlatformAccounts && (
        <div className="fixed inset-0 bg-black/40 grid place-items-center z-50">
          <div className="bg-white rounded-[16px] p-6 max-w-md shadow-lg">
            <div className="font-semibold text-lg mb-2 tracking-[-0.02em]">Delete {account.name}?</div>
            <p className="text-sm text-[#737373] mb-4">
              If this account is referenced by any schedule, session slot, or live session, the delete will be
              blocked and you'll be asked to mark it inactive instead.
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

PlatformAccountShow.layout = (page) => <LiveHostLayout>{page}</LiveHostLayout>;

function StatTile({ label, value }) {
  return (
    <div className="bg-white border border-[#EAEAEA] rounded-[16px] shadow-[0_1px_2px_rgba(0,0,0,0.04)] p-5">
      <div className="text-[11px] uppercase tracking-wide text-[#737373] font-medium">{label}</div>
      <div className="text-3xl font-semibold tracking-[-0.03em] mt-2 tabular-nums">{value}</div>
    </div>
  );
}

function DetailRow({ label, value }) {
  return (
    <>
      <dt className="text-[11.5px] uppercase tracking-wide text-[#737373]">{label}</dt>
      <dd className="text-[#0A0A0A]">{value}</dd>
    </>
  );
}
