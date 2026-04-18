import { cn } from '@/livehost/lib/utils';
import StatusChip from '@/livehost/components/StatusChip';

/**
 * AgendaRow — a row in the "Next up" schedule list.
 *
 * @typedef {Object} AgendaRowProps
 * @property {string} [startTime]           "HH:MM" label, e.g. "14:30"
 * @property {string} [durationLabel]       "90m"
 * @property {string} [hostName]
 * @property {string} [meta]                e.g. "shopee-my-04 · product demo"
 * @property {'live'|'prep'|'scheduled'|'done'} [status]
 * @property {string} [className]
 */

export default function AgendaRow({
  startTime,
  durationLabel,
  hostName,
  meta,
  status = 'scheduled',
  className,
}) {
  return (
    <div
      className={cn(
        'grid grid-cols-[auto_1fr_auto] items-center gap-3.5 border-b border-[#F0F0F0] py-3 last:border-b-0',
        className
      )}
    >
      <div className="min-w-[58px] rounded-lg bg-[#F5F5F5] px-2.5 py-1.5 text-center">
        <div className="text-sm font-semibold leading-none tabular-nums tracking-[-0.02em] text-[#0A0A0A]">
          {startTime || '—'}
        </div>
        {durationLabel ? (
          <div className="mt-0.5 text-[10px] font-medium text-[#737373]">{durationLabel}</div>
        ) : null}
      </div>

      <div className="min-w-0">
        <div className="truncate text-[13.5px] font-medium tracking-[-0.01em] text-[#0A0A0A]">
          {hostName || 'Unassigned host'}
        </div>
        {meta ? (
          <div className="mt-0.5 truncate text-[12px] text-[#737373]">{meta}</div>
        ) : null}
      </div>

      <StatusChip variant={status} />
    </div>
  );
}
