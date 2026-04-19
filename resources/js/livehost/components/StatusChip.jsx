import { cn } from '@/livehost/lib/utils';

/**
 * StatusChip — compact pill badge for statuses used across the Live Host UI.
 *
 * Variants:
 *   - live       : emerald bg, white text, animated pulse dot
 *   - prep       : amber-soft bg, amber ink
 *   - scheduled  : surface-2 bg, muted text
 *   - done       : blue-soft bg, blue text
 *   - active     : emerald-soft bg, emerald-ink text
 *   - inactive   : surface-2 bg, muted text
 *   - suspended  : rose-soft bg, rose text
 *
 * @typedef {Object} StatusChipProps
 * @property {'live'|'prep'|'scheduled'|'done'|'active'|'inactive'|'suspended'} variant
 * @property {React.ReactNode} [children]    defaults to a humanized label for the variant
 * @property {string} [className]
 */

const VARIANT_CLASSES = {
  live: 'bg-[#10B981] text-white',
  prep: 'bg-[#FFFBEB] text-[#B45309]',
  scheduled: 'bg-[#F5F5F5] text-[#737373]',
  done: 'bg-[#EFF6FF] text-[#1D4ED8]',
  active: 'bg-[#ECFDF5] text-[#059669]',
  inactive: 'bg-[#F5F5F5] text-[#737373]',
  suspended: 'bg-[#FFF1F2] text-[#F43F5E]',
};

const DEFAULT_LABELS = {
  live: 'Live',
  prep: 'Prep',
  scheduled: 'Scheduled',
  done: 'Done',
  active: 'Active',
  inactive: 'Inactive',
  suspended: 'Suspended',
};

export default function StatusChip({ variant = 'scheduled', children, className }) {
  const tone = VARIANT_CLASSES[variant] || VARIANT_CLASSES.scheduled;
  const label = children ?? DEFAULT_LABELS[variant] ?? variant;

  return (
    <span
      className={cn(
        'inline-flex items-center gap-1.5 rounded-full px-2.5 py-[3px] text-[11px] font-medium leading-none tracking-[-0.005em]',
        tone,
        className
      )}
    >
      {variant === 'live' ? (
        <span
          aria-hidden="true"
          className="pulse-dot h-[5px] w-[5px] !bg-white !shadow-[0_0_0_0_rgba(255,255,255,0.6)]"
        />
      ) : null}
      {label}
    </span>
  );
}
