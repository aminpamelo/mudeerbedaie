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
