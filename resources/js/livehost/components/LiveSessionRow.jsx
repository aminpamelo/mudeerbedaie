import { cn } from '@/livehost/lib/utils';
import { formatDuration, deriveInitials, secondsSince } from '@/livehost/lib/format';

/**
 * LiveSessionRow — a row in the "On Air now" panel.
 *
 * @typedef {Object} LiveSessionRowProps
 * @property {string|null} [hostName]
 * @property {string} [initials]            derived from hostName if omitted
 * @property {string} [platformAccount]     e.g. "shopee-my-01"
 * @property {string} [platformType]        e.g. "shopee" | "tiktok" | "facebook"
 * @property {string} [platformLabel]       human label shown after dot, e.g. "Shopee Livestream"
 * @property {string} [sessionId]           e.g. "LS-90158"
 * @property {number} [viewers]
 * @property {number|null} [durationSeconds] preferred source for duration display
 * @property {string|null} [startedAt]      ISO timestamp, used when durationSeconds is absent
 * @property {1|2|3|4|5} [thumbColor]       gradient choice (defaults to 1)
 * @property {() => void} [onClick]
 * @property {string} [className]
 */

const THUMB_GRADIENTS = {
  1: 'bg-gradient-to-br from-[#10B981] to-[#059669]',
  2: 'bg-gradient-to-br from-[#F43F5E] to-[#E11D48]',
  3: 'bg-gradient-to-br from-[#8B5CF6] to-[#6D28D9]',
  4: 'bg-gradient-to-br from-[#0EA5E9] to-[#0284C7]',
  5: 'bg-gradient-to-br from-[#F59E0B] to-[#D97706]',
};

const PLATFORM_DOT_COLORS = {
  shopee: 'bg-[#F43F5E]',
  tiktok: 'bg-[#0A0A0A]',
  facebook: 'bg-[#1877F2]',
  instagram: 'bg-[#E1306C]',
  youtube: 'bg-[#FF0000]',
  lazada: 'bg-[#0F146D]',
};

function platformDotClass(type) {
  if (!type) {
    return 'bg-[#F43F5E]';
  }
  return PLATFORM_DOT_COLORS[String(type).toLowerCase()] || 'bg-[#737373]';
}

export default function LiveSessionRow({
  hostName,
  initials,
  platformAccount,
  platformType,
  platformLabel,
  sessionId,
  viewers,
  durationSeconds,
  startedAt,
  thumbColor = 1,
  onClick,
  className,
}) {
  const safeInitials = (initials && initials.trim()) || deriveInitials(hostName);
  const thumbClass = THUMB_GRADIENTS[thumbColor] || THUMB_GRADIENTS[1];

  const resolvedSeconds =
    Number.isFinite(Number(durationSeconds)) && Number(durationSeconds) >= 0
      ? Number(durationSeconds)
      : secondsSince(startedAt);

  const durationLabel = resolvedSeconds !== null ? formatDuration(resolvedSeconds) : '—';

  const viewerCount =
    typeof viewers === 'number' && Number.isFinite(viewers)
      ? viewers.toLocaleString()
      : '—';

  const Tag = onClick ? 'button' : 'div';

  return (
    <Tag
      type={onClick ? 'button' : undefined}
      onClick={onClick}
      className={cn(
        'grid w-full grid-cols-[auto_1fr_auto_auto] items-center gap-3.5 rounded-[10px] border border-[#F0F0F0] bg-white p-3 text-left transition-all duration-150',
        onClick
          ? 'cursor-pointer hover:-translate-y-px hover:border-[#10B981] hover:shadow-[0_0_0_3px_#ECFDF5]'
          : '',
        className
      )}
    >
      <div
        className={cn(
          'relative grid h-11 w-11 shrink-0 place-items-center rounded-lg text-[13px] font-semibold text-white',
          thumbClass
        )}
      >
        {safeInitials}
        <span
          aria-hidden="true"
          className="pulse-dot absolute -top-0.5 -right-0.5 h-2.5 w-2.5 border-2 border-white"
        />
      </div>

      <div className="min-w-0">
        <div className="truncate text-sm font-semibold tracking-[-0.01em] text-[#0A0A0A]">
          {hostName || 'Unassigned host'}
        </div>
        <div className="mt-0.5 flex items-center gap-2 text-[12px] text-[#737373]">
          {platformAccount ? (
            <span className="inline-flex items-center gap-1 font-medium">
              <span className={cn('h-[5px] w-[5px] rounded-full', platformDotClass(platformType))} />
              {platformAccount}
            </span>
          ) : null}
          {platformLabel ? (
            <>
              <span>·</span>
              <span>{platformLabel}</span>
            </>
          ) : null}
          {sessionId ? (
            <>
              <span>·</span>
              <span>#{sessionId}</span>
            </>
          ) : null}
        </div>
      </div>

      <div className="text-right">
        <div className="text-[18px] font-semibold tabular-nums tracking-[-0.02em] text-[#0A0A0A]">
          {viewerCount}
        </div>
        <div className="text-[11px] font-medium text-[#737373]">viewers</div>
      </div>

      <div className="rounded-md bg-[#F5F5F5] px-2 py-1 font-mono text-[12px] font-medium tabular-nums text-[#0A0A0A]">
        {durationLabel}
      </div>
    </Tag>
  );
}
