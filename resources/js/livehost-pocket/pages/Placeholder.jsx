import { Head, usePage } from '@inertiajs/react';
import PocketLayout from '@/livehost-pocket/layouts/PocketLayout';
import { firstNameFrom, greetingFor } from '@/livehost-pocket/lib/utils';

/**
 * Batch 1 placeholder. Real "Today" screen lands in Batch 2; for now this
 * confirms the Inertia host bundle mounts, the layout renders, and the auth
 * prop is wired end-to-end.
 */
export default function Placeholder() {
  const { props } = usePage();
  const user = props?.auth?.user ?? null;
  const features = props?.features ?? {};
  const greeting = greetingFor(new Date());
  const firstName = firstNameFrom(user?.name);

  return (
    <>
      <Head title="Today" />
      <div className="pt-6">
        <p className="font-mono text-[11px] uppercase tracking-[0.18em] text-[var(--color-pocket-muted)]">
          Live Host Pocket
        </p>
        <h1 className="mt-1 font-display text-[28px] leading-tight tracking-tight text-[var(--color-pocket-ink)]">
          {greeting}, {firstName}
        </h1>
        <p className="mt-2 text-sm text-[var(--color-pocket-muted)]">
          Welcome, {firstName}. The Pocket mobile shell is online. The Today
          screen, Schedule grid, and Upload flow land in Batches 2-4.
        </p>

        <div className="mt-6 rounded-[var(--radius-pocket-card)] border border-[var(--color-pocket-border)] bg-[var(--color-pocket-surface)] p-5 shadow-[var(--shadow-pocket-card)]">
          <p className="font-mono text-[10px] uppercase tracking-[0.2em] text-[var(--color-pocket-muted)]">
            Scaffold status
          </p>
          <ul className="mt-3 space-y-2 text-sm text-[var(--color-pocket-ink-2)]">
            <li>Inertia bundle: livehost-pocket</li>
            <li>Root view: livehost-pocket.app</li>
            <li>
              Allowance feature flag:{' '}
              <span className="font-semibold">
                {features.allowance_enabled ? 'ON' : 'OFF'}
              </span>
            </li>
          </ul>
        </div>
      </div>
    </>
  );
}
