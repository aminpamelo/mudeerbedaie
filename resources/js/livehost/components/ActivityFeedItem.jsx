import { cn } from '@/livehost/lib/utils';

/**
 * ActivityFeedItem — a row in the Recent Activity feed.
 *
 * @typedef {Object} ActivityFeedItemProps
 * @property {React.ComponentType} [icon]                 lucide icon component
 * @property {'emerald'|'amber'|'rose'|'default'} [iconTint]
 * @property {React.ReactNode} [children]                 body text (may include <strong>)
 * @property {string} [timeLabel]                         e.g. "2 min ago · 14:02"
 * @property {string} [className]
 */

const TINT_CLASSES = {
  default: 'bg-[#F5F5F5] text-[#404040]',
  emerald: 'bg-[#ECFDF5] text-[#059669]',
  amber: 'bg-[#FFFBEB] text-[#F59E0B]',
  rose: 'bg-[#FFF1F2] text-[#F43F5E]',
};

export default function ActivityFeedItem({
  icon: Icon,
  iconTint = 'default',
  children,
  timeLabel,
  className,
}) {
  const tint = TINT_CLASSES[iconTint] || TINT_CLASSES.default;

  return (
    <div
      className={cn(
        'flex gap-3 border-b border-[#F0F0F0] py-3 first:pt-1 last:border-b-0',
        className
      )}
    >
      <div className={cn('grid h-7 w-7 shrink-0 place-items-center rounded-full', tint)}>
        {Icon ? <Icon className="h-[13px] w-[13px]" strokeWidth={2.5} /> : null}
      </div>
      <div className="flex-1 text-[13px] leading-[1.5] text-[#404040] [&_strong]:font-semibold [&_strong]:text-[#0A0A0A]">
        {children}
        {timeLabel ? (
          <div className="mt-0.5 text-[11px] text-[#737373]">{timeLabel}</div>
        ) : null}
      </div>
    </div>
  );
}
