/**
 * Minimal className combiner — joins truthy class fragments with a space.
 * Kept local to the CEO bundle so it shares nothing with the livehost app.
 */
export function cn(...parts) {
  return parts.filter(Boolean).join(' ');
}

const TONE_TEXT = {
  positive: 'text-[var(--color-emerald-ink)]',
  warning: 'text-[var(--color-amber-ink)]',
  negative: 'text-[var(--color-rose-ink)]',
  muted: 'text-muted',
};

/** Map a metric tone to a text-color class. Falls back to ink. */
export function toneText(tone) {
  return TONE_TEXT[tone] ?? 'text-ink';
}

const SEVERITY = {
  critical: { dot: 'bg-[var(--color-rose)]', text: 'text-[var(--color-rose-ink)]', soft: 'bg-[var(--color-rose-soft)]' },
  warning: { dot: 'bg-[var(--color-amber)]', text: 'text-[var(--color-amber-ink)]', soft: 'bg-[var(--color-amber-soft)]' },
  info: { dot: 'bg-[var(--color-sky)]', text: 'text-[var(--color-sky-ink)]', soft: 'bg-[var(--color-sky-soft)]' },
};

export function severityStyles(severity) {
  return SEVERITY[severity] ?? SEVERITY.info;
}
