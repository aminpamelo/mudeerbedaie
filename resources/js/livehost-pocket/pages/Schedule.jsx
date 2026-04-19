import { Head, usePage } from '@inertiajs/react';
import PocketLayout from '@/livehost-pocket/layouts/PocketLayout';

/**
 * Schedule — weekly read-only roster.
 *
 * Props from {@link \App\Http\Controllers\LiveHostPocket\ScheduleController::index}:
 *   - `days` — array of 7 day buckets (Sunday-first) with nested `schedules`.
 *   - `totalSlots` — total active slots assigned to this host.
 *
 * Self-assign / unassign lives in the Volt `schedule.blade.php` page and
 * is NOT part of Batch 3 — the page shows a muted callout pointing hosts
 * at their PIC for changes.
 */
export default function Schedule() {
  const { days, totalSlots } = usePage().props;
  const buckets = Array.isArray(days) ? days : [];
  const total = Number.isFinite(totalSlots) ? totalSlots : 0;

  return (
    <>
      <Head title="Schedule" />
      <div className="-mx-5 min-h-full bg-[var(--app-bg)] px-4 pt-3 pb-8">
        <div className="px-1 pt-3 pb-4">
          <div className="mb-1 font-mono text-[10px] font-bold uppercase tracking-[0.14em] text-[var(--fg-3)]">
            Weekly roster
          </div>
          <h1 className="font-display text-[22px] font-medium leading-[1.08] tracking-[-0.03em] text-[var(--fg)]">
            Your schedule
          </h1>
          <div className="mt-2 font-mono text-[11px] tracking-[0.02em] text-[var(--fg-2)]">
            {total} {total === 1 ? 'slot' : 'slots'} assigned
          </div>
        </div>

        <div>
          {buckets.map((bucket) => (
            <DayBucket key={bucket.dayOfWeek} bucket={bucket} />
          ))}
        </div>

        <PicCallout />
      </div>
    </>
  );
}

Schedule.layout = (page) => <PocketLayout>{page}</PocketLayout>;

function DayBucket({ bucket }) {
  const hasSchedules = (bucket.schedules?.length ?? 0) > 0;

  return (
    <section className="mb-4">
      <div className="mb-2 flex items-center gap-2 px-1">
        <span className="font-display text-[13px] font-medium tracking-[-0.015em] text-[var(--fg)]">
          {bucket.dayName}
        </span>
        <span
          className="inline-flex items-center rounded-full px-[7px] py-[2px] font-mono text-[8.5px] font-bold uppercase tracking-[0.14em] text-[var(--fg-3)]"
          style={{ backgroundColor: 'var(--hair)' }}
        >
          {bucket.dayShort}
        </span>
      </div>

      {hasSchedules ? (
        <div>
          {bucket.schedules.map((slot) => (
            <SlotCard key={slot.id} slot={slot} />
          ))}
        </div>
      ) : (
        <div className="rounded-[12px] border border-dashed border-[var(--hair-2)] bg-[var(--app-bg-2)] px-3 py-3 text-center font-mono text-[10px] font-bold uppercase tracking-[0.14em] text-[var(--fg-3)]">
          No slots
        </div>
      )}
    </section>
  );
}

function SlotCard({ slot }) {
  const range = `${slot.startTime} \u2013 ${slot.endTime}`;
  const platform = slot.platformAccount ?? slot.platformType ?? 'Platform';

  const dotColor =
    slot.platformType === 'tiktok'
      ? 'var(--fg-1)'
      : slot.platformType === 'facebook'
        ? 'var(--cool)'
        : 'var(--hot)';

  return (
    <div className="mb-[6px] rounded-[14px] border border-[var(--hair)] bg-[var(--app-bg-2)] px-3 py-[10px]">
      <div className="flex items-center justify-between gap-2">
        <span className="inline-flex items-center gap-[5px] font-mono text-[9px] font-bold uppercase tracking-[0.14em] text-[var(--fg-2)]">
          <span
            className="h-1 w-1"
            style={{ backgroundColor: dotColor }}
            aria-hidden="true"
          />
          {platform}
        </span>
        {slot.isRecurring ? (
          <span
            className="inline-flex items-center rounded-full px-[7px] py-[2px] font-mono text-[8.5px] font-bold uppercase tracking-[0.14em] text-[var(--fg-3)]"
            style={{ backgroundColor: 'var(--hair)' }}
          >
            WEEKLY
          </span>
        ) : null}
      </div>
      <div className="mt-1 font-mono text-[13px] font-bold tabular-nums text-[var(--fg)]">
        {range}
      </div>
      {slot.remarks ? (
        <div className="mt-[6px] text-[11px] leading-snug text-[var(--fg-2)]">
          {slot.remarks}
        </div>
      ) : null}
    </div>
  );
}

function PicCallout() {
  return (
    <div className="mt-2 rounded-[12px] border border-[var(--hair)] bg-[var(--app-bg-2)] px-3 py-3 text-center text-[11px] leading-snug text-[var(--fg-2)]">
      To claim or release slots, ask your PIC.
    </div>
  );
}
