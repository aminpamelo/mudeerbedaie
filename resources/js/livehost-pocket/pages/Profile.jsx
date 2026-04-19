import { Head, router, usePage } from '@inertiajs/react';
import { LogOut } from 'lucide-react';
import PocketLayout from '@/livehost-pocket/layouts/PocketLayout';
import { initialsFrom } from '@/livehost-pocket/lib/utils';

/**
 * Profile — minimal "You" tab.
 *
 * Shows the host's name, email, phone, status, role, with a sign-out action.
 * Deeper profile editing (name/password/appearance) still lives on the main
 * Livewire settings pages; this page keeps the tabbar slot pointed at
 * something useful on the Pocket side without pulling that scope in.
 */
export default function Profile() {
  const { profile } = usePage().props;
  const initials = initialsFrom(profile?.name);

  const handleSignOut = () => {
    if (!window.confirm('Sign out of the Pocket?')) {
      return;
    }
    router.post('/logout');
  };

  return (
    <>
      <Head title="You" />
      <div className="-mx-5 min-h-full bg-[var(--app-bg)] px-4 pt-3 pb-8">
        <div className="px-1 pt-3 pb-4">
          <div className="mb-1 font-mono text-[10px] font-bold uppercase tracking-[0.14em] text-[var(--fg-3)]">
            Profile
          </div>
          <h1 className="font-display text-[22px] font-medium leading-[1.08] tracking-[-0.03em] text-[var(--fg)]">
            You
          </h1>
        </div>

        <div className="mb-4 rounded-[16px] border border-[var(--hair)] bg-[var(--app-bg-2)] p-[16px]">
          <div className="flex items-center gap-[14px]">
            <div
              className="grid h-[56px] w-[56px] place-items-center rounded-full bg-gradient-to-br from-[var(--accent)] to-[var(--hot)] font-display text-[18px] font-bold tracking-[-0.04em] text-white"
              aria-hidden="true"
            >
              {initials}
            </div>
            <div className="min-w-0 flex-1">
              <div className="truncate font-display text-[17px] font-medium tracking-[-0.02em] text-[var(--fg)]">
                {profile?.name ?? 'Live Host'}
              </div>
              <div className="mt-[2px] truncate font-mono text-[11px] text-[var(--fg-2)]">
                {profile?.email ?? '—'}
              </div>
            </div>
          </div>
        </div>

        <InfoCard
          rows={[
            { label: 'Phone', value: profile?.phone },
            { label: 'Status', value: profile?.status, mono: true },
            { label: 'Role', value: profile?.role, mono: true },
          ]}
        />

        <div className="pt-4">
          <button
            type="button"
            onClick={handleSignOut}
            className="flex w-full items-center justify-center gap-[8px] rounded-[12px] border border-[var(--hair)] bg-[var(--app-bg-2)] px-4 py-[13px] font-sans text-[13px] font-bold tracking-[-0.005em] text-[var(--hot)] transition active:scale-[0.98] hover:border-[var(--hot)]"
          >
            <LogOut className="h-[15px] w-[15px]" strokeWidth={2} />
            Sign out
          </button>
        </div>
      </div>
    </>
  );
}

Profile.layout = (page) => <PocketLayout>{page}</PocketLayout>;

function InfoCard({ rows }) {
  return (
    <div className="overflow-hidden rounded-[16px] border border-[var(--hair)] bg-[var(--app-bg-2)]">
      {rows.map((row, idx) => (
        <div
          key={row.label}
          className={
            'flex items-center justify-between px-[14px] py-[12px]' +
            (idx > 0 ? ' border-t border-[var(--hair)]' : '')
          }
        >
          <div className="font-mono text-[10px] font-bold uppercase tracking-[0.14em] text-[var(--fg-3)]">
            {row.label}
          </div>
          <div
            className={
              'truncate pl-2 text-right ' +
              (row.mono
                ? 'font-mono text-[11.5px] font-semibold uppercase tracking-[0.04em] text-[var(--fg)]'
                : 'text-[13px] text-[var(--fg)]')
            }
          >
            {row.value ?? '—'}
          </div>
        </div>
      ))}
    </div>
  );
}
