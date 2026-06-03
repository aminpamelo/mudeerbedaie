import { cn } from '@/ceo/lib/utils';

const LABEL = { green: 'Healthy', amber: 'Watch', red: 'Attention' };

/**
 * Traffic-light signal for a department. Reads its color from the
 * `[data-status]` token so the palette stays centralized in ceo.css.
 */
export default function StatusDot({ status = 'green', showLabel = false, pulse = false }) {
  return (
    <span data-status={status} className="inline-flex items-center gap-1.5">
      <span className="relative inline-flex h-2.5 w-2.5">
        {pulse && status !== 'green' && (
          <span className="absolute inline-flex h-full w-full animate-ping rounded-full opacity-60" style={{ background: 'var(--signal)' }} />
        )}
        <span className="relative inline-flex h-2.5 w-2.5 rounded-full" style={{ background: 'var(--signal)' }} />
      </span>
      {showLabel && (
        <span className={cn('text-[11px] font-medium')} style={{ color: 'var(--signal-ink)' }}>
          {LABEL[status] ?? LABEL.green}
        </span>
      )}
    </span>
  );
}
