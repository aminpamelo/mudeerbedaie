/**
 * Format a positive number of seconds as HH:MM:SS (zero-padded).
 * Returns "—" for invalid / null / negative values.
 *
 * @param {number|string|null|undefined} seconds
 * @returns {string}
 */
export function formatDuration(seconds) {
  const n = Number(seconds);
  if (!Number.isFinite(n) || n < 0) {
    return '—';
  }
  const total = Math.floor(n);
  const h = Math.floor(total / 3600);
  const m = Math.floor((total % 3600) / 60);
  const s = total % 60;
  const pad = (x) => String(x).padStart(2, '0');
  return `${pad(h)}:${pad(m)}:${pad(s)}`;
}

/**
 * Derive initials (up to 2 chars, uppercase) from a name string.
 * Returns '??' when no name is provided.
 *
 * @param {string|null|undefined} name
 * @returns {string}
 */
export function deriveInitials(name) {
  if (!name || typeof name !== 'string') {
    return '??';
  }
  const parts = name.trim().split(/\s+/).filter(Boolean);
  if (parts.length === 0) {
    return '??';
  }
  if (parts.length === 1) {
    return parts[0].slice(0, 2).toUpperCase();
  }
  return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
}

/**
 * Compute seconds elapsed between an ISO timestamp and now.
 * Returns null if the timestamp is invalid.
 *
 * @param {string|null|undefined} iso
 * @returns {number|null}
 */
export function secondsSince(iso) {
  if (!iso) {
    return null;
  }
  const ts = Date.parse(iso);
  if (Number.isNaN(ts)) {
    return null;
  }
  return Math.max(0, Math.floor((Date.now() - ts) / 1000));
}

/**
 * Format a 24-hour "HH:MM" string as 12-hour with AM/PM.
 * Examples: "08:30" → "8:30 AM", "14:00" → "2:00 PM", "00:15" → "12:15 AM".
 * Returns the input unchanged if it can't be parsed.
 *
 * @param {string|null|undefined} hhmm
 * @returns {string}
 */
export function format12Hour(hhmm) {
  if (!hhmm || typeof hhmm !== 'string') {
    return '';
  }
  const match = hhmm.match(/^(\d{1,2}):(\d{2})/);
  if (!match) {
    return hhmm;
  }
  let h = Number(match[1]);
  const m = match[2];
  if (!Number.isFinite(h) || h < 0 || h > 23) {
    return hhmm;
  }
  const period = h >= 12 ? 'PM' : 'AM';
  h = h % 12;
  if (h === 0) {
    h = 12;
  }
  return `${h}:${m} ${period}`;
}

/**
 * Format a 24-hour "HH:MM"–"HH:MM" pair as a 12-hour range with AM/PM.
 * Returns "—" when either endpoint is missing.
 *
 * @param {string|null|undefined} start
 * @param {string|null|undefined} end
 * @returns {string}
 */
export function formatTimeRange12(start, end) {
  if (!start || !end) {
    return '—';
  }
  return `${format12Hour(start)} – ${format12Hour(end)}`;
}
