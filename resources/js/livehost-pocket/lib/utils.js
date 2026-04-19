import { clsx } from 'clsx';
import { twMerge } from 'tailwind-merge';

/**
 * Merge Tailwind class names while respecting conditional/falsey values.
 * Mirrors the helper used by the Live Host Desk bundle so Pocket pages can
 * share the same authoring idioms.
 */
export function cn(...inputs) {
  return twMerge(clsx(inputs));
}

/**
 * Greeting helper used on the Today screen. Kept here so it is available to
 * both the layout header and the page body without duplicating the logic.
 */
export function greetingFor(date = new Date()) {
  const hour = date.getHours();
  if (hour < 12) {
    return 'Good morning';
  }
  if (hour < 18) {
    return 'Good afternoon';
  }
  return 'Good evening';
}

/**
 * Short time-of-day greeting used on the Pocket Today screen, matching the
 * mockup copy ("Morning, Wan." / "Evening, Wan."). Distinct from
 * {@link greetingFor} which returns the long "Good morning" form used in the
 * Placeholder and other full-width greetings.
 */
export function shortGreetingFor(date = new Date()) {
  const hour = date.getHours();
  if (hour < 5) {
    return 'Night';
  }
  if (hour < 12) {
    return 'Morning';
  }
  if (hour < 18) {
    return 'Afternoon';
  }
  if (hour < 22) {
    return 'Evening';
  }
  return 'Night';
}

/**
 * Format a Date (or ISO string) as 24h HH:MM in the user's local timezone.
 * Used for "ON AIR · HH:MM MYT", the "SINCE" label on the live card, and
 * upcoming row times.
 */
export function formatClockHM(value) {
  const date = value instanceof Date ? value : new Date(value);
  if (Number.isNaN(date.getTime())) {
    return '';
  }
  return date.toLocaleTimeString('en-GB', {
    hour: '2-digit',
    minute: '2-digit',
    hour12: false,
  });
}

/**
 * Return initials (up to two letters) for the avatar gradient circle.
 */
export function initialsFrom(name) {
  if (!name) {
    return 'LH';
  }
  const parts = name
    .split(/\s+/)
    .filter(Boolean)
    .map((p) => p[0]?.toUpperCase() ?? '');
  if (parts.length === 0) {
    return 'LH';
  }
  return (parts[0] + (parts[1] ?? '')).slice(0, 2);
}

/**
 * Format a minute count as a short duration pill: "2h", "90m", "1h 30m".
 * Keeps the footprint small on narrow mobile rows.
 */
export function formatDurationShort(minutes) {
  const m = Number(minutes);
  if (!Number.isFinite(m) || m <= 0) {
    return '—';
  }
  if (m < 60) {
    return `${m}m`;
  }
  if (m % 60 === 0) {
    return `${m / 60}h`;
  }
  const hours = Math.floor(m / 60);
  const rest = m % 60;
  return `${hours}h ${rest}m`;
}

/**
 * Convert a minute count to a decimal-hours label ("3.2h", "0.5h"). Used on
 * the "Watch time today" fallback tile when the allowance feature flag is
 * off.
 */
export function formatHoursDecimal(minutes) {
  const m = Number(minutes);
  if (!Number.isFinite(m) || m <= 0) {
    return '0h';
  }
  const hours = m / 60;
  return `${hours.toFixed(1)}h`;
}

/**
 * Count whole minutes elapsed between `from` (ISO string or Date) and `now`,
 * floored at zero. Used to render the "Elapsed HH:MM" ticker on the live
 * card.
 */
export function minutesSince(from, now = new Date()) {
  if (!from) {
    return 0;
  }
  const start = from instanceof Date ? from : new Date(from);
  if (Number.isNaN(start.getTime())) {
    return 0;
  }
  const diff = Math.floor((now.getTime() - start.getTime()) / 60_000);
  return Math.max(0, diff);
}

/**
 * Format a whole-minute count as HH:MM (24h, with leading zeros). Used for
 * the live-card "Elapsed" value.
 */
export function formatMinutesHM(totalMinutes) {
  const m = Number(totalMinutes);
  if (!Number.isFinite(m) || m < 0) {
    return '00:00';
  }
  const hours = Math.floor(m / 60);
  const mins = m % 60;
  return `${String(hours).padStart(2, '0')}:${String(mins).padStart(2, '0')}`;
}

/**
 * Extract the host's first name for a friendly greeting. Falls back to the
 * whole name if only a single word is provided.
 */
export function firstNameFrom(name) {
  if (!name) {
    return 'there';
  }
  return name.split(/\s+/)[0] || name;
}
