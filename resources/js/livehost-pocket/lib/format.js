/**
 * Live Host Pocket — formatting helpers for Sessions + Schedule screens.
 *
 * Kept separate from utils.js so the Today screen's footprint stays
 * minimal — these helpers are only needed once the user navigates into
 * the list views.
 */

const DAY_SHORT_MS = ['Ahd', 'Isn', 'Sel', 'Rab', 'Kha', 'Jum', 'Sab'];
const MONTH_SHORT_MS = ['Jan', 'Feb', 'Mac', 'Apr', 'Mei', 'Jun', 'Jul', 'Ogo', 'Sep', 'Okt', 'Nov', 'Dis'];

/**
 * Short, locale-aware date/time label suitable for a session-card's
 * "scheduled" line. Output is in Bahasa Malaysia.
 *
 * - Today           -> "Hari ini HH:mm"
 * - Yesterday       -> "Semalam HH:mm"
 * - Tomorrow        -> "Esok HH:mm"
 * - Within 6 days   -> "Kha HH:mm"
 * - Older / future  -> "DD Mac HH:mm"
 */
export function formatShortDateTime(value) {
  if (!value) {
    return '—';
  }
  const date = value instanceof Date ? value : new Date(value);
  if (Number.isNaN(date.getTime())) {
    return '—';
  }
  const time = date.toLocaleTimeString('en-GB', {
    hour: '2-digit',
    minute: '2-digit',
    hour12: false,
  });
  const now = new Date();
  const startOfDay = (d) => new Date(d.getFullYear(), d.getMonth(), d.getDate()).getTime();
  const dayDiff = Math.round((startOfDay(date) - startOfDay(now)) / 86_400_000);

  if (dayDiff === 0) {
    return `Hari ini ${time}`;
  }
  if (dayDiff === -1) {
    return `Semalam ${time}`;
  }
  if (dayDiff === 1) {
    return `Esok ${time}`;
  }
  if (Math.abs(dayDiff) < 7) {
    return `${DAY_SHORT_MS[date.getDay()]} ${time}`;
  }
  return `${date.getDate()} ${MONTH_SHORT_MS[date.getMonth()]} ${time}`;
}

/**
 * Format a duration expressed in minutes as "1h 30m" (hours + mins). Falls
 * back to em dash when the value is missing or non-positive.
 */
export function formatDurationHM(minutes) {
  const m = Number(minutes);
  if (!Number.isFinite(m) || m <= 0) {
    return '—';
  }
  if (m < 60) {
    return `${m}m`;
  }
  if (m % 60 === 0) {
    return `${m / 60}j`;
  }
  const hours = Math.floor(m / 60);
  const rest = m % 60;
  return `${hours}j ${rest}m`;
}

/**
 * Build the "Yesterday 09:00 – 11:14 · 2h 14m" schedule line used on ended
 * session cards. Accepts ISO strings (or `null`) and gracefully degrades if
 * the end time is missing.
 */
export function formatSessionScheduleLine({ start, end, durationMinutes }) {
  if (!start) {
    return '—';
  }
  const label = formatShortDateTime(start);
  const endDate = end ? new Date(end) : null;
  if (endDate && !Number.isNaN(endDate.getTime())) {
    const endTime = endDate.toLocaleTimeString('en-GB', {
      hour: '2-digit',
      minute: '2-digit',
      hour12: false,
    });
    const durationPart = formatDurationHM(durationMinutes);
    if (durationPart !== '—') {
      return `${label} \u2013 ${endTime} \u00b7 ${durationPart}`;
    }
    return `${label} \u2013 ${endTime}`;
  }
  const durationPart = formatDurationHM(durationMinutes);
  if (durationPart !== '—') {
    return `${label} \u00b7 ${durationPart}`;
  }
  return label;
}

/**
 * Compact numeric formatter used in session-card metric strips
 * (1284 -> "1,284"). Returns "—" for nullish values.
 */
export function formatCompactNumber(value) {
  if (value === null || value === undefined) {
    return '—';
  }
  const n = Number(value);
  if (!Number.isFinite(n)) {
    return '—';
  }
  return n.toLocaleString('en-GB');
}

/**
 * Gifts column displays an integer Ringgit value (truncated, no cents) with
 * the "RM" prefix styled separately in the UI. Returns the numeric integer
 * part so the component can render the prefix with different typography.
 */
export function formatRinggitInt(value) {
  const n = Number(value);
  if (!Number.isFinite(n)) {
    return '0';
  }
  return Math.trunc(n).toLocaleString('en-GB');
}
