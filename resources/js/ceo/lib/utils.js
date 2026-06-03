/**
 * Minimal className combiner — joins truthy class fragments with a space.
 * Local to the CEO bundle so it shares nothing with the livehost app.
 */
export function cn(...parts) {
  return parts.filter(Boolean).join(' ');
}

/** Map a semantic tone to its vibrant hex (for SVG strokes / inline styles). */
export function toneColor(tone) {
  switch (tone) {
    case 'positive':
      return 'var(--color-emerald)';
    case 'warning':
      return 'var(--color-amber)';
    case 'negative':
      return 'var(--color-rose)';
    case 'info':
      return 'var(--color-sky)';
    case 'muted':
      return 'var(--color-muted-2)';
    default:
      return 'var(--color-ink)';
  }
}

/** Map a metric tone to a text-color class for big numbers. */
export function toneText(tone) {
  switch (tone) {
    case 'positive':
      return 'text-[var(--color-emerald-ink)]';
    case 'warning':
      return 'text-[var(--color-amber-ink)]';
    case 'negative':
      return 'text-[var(--color-rose-ink)]';
    case 'info':
      return 'text-[var(--color-sky-ink)]';
    case 'muted':
      return 'text-muted';
    default:
      return 'text-ink';
  }
}

export function initialsFrom(name) {
  if (!name) return '?';
  return (
    name
      .split(' ')
      .filter(Boolean)
      .slice(0, 2)
      .map((part) => part[0]?.toUpperCase() ?? '')
      .join('') || '?'
  );
}
