/** Join truthy class fragments. */
export function cn(...parts) {
  return parts.filter(Boolean).join(' ');
}

/** CSRF token from the page meta tag, for fetch()/axios calls. */
export function csrfToken() {
  return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

/** Short human date, e.g. "15 Jul 2026". */
export function formatDate(iso) {
  if (!iso) return '';
  return new Date(iso).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
}

/** Up-to-two-letter initials from a name. */
export function initialsFrom(name) {
  if (!name) return '?';
  return name.trim().split(/\s+/).slice(0, 2).map((w) => w.charAt(0).toUpperCase()).join('');
}

/** Relative time, e.g. "3m", "2j", "5h". Falls back to a short date beyond a week. */
export function timeAgo(iso) {
  if (!iso) return '';
  const secs = Math.round((Date.now() - new Date(iso).getTime()) / 1000);
  if (secs < 60) return 'kini';
  const mins = Math.round(secs / 60);
  if (mins < 60) return `${mins}m`;
  const hours = Math.round(mins / 60);
  if (hours < 24) return `${hours}j`;
  const days = Math.round(hours / 24);
  if (days < 7) return `${days}h`;
  return formatDate(iso);
}

/** Clock time, e.g. "14:05". */
export function clockTime(iso) {
  if (!iso) return '';
  return new Date(iso).toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' });
}

/** Pretty-print a Malaysian-style number, e.g. "60123456789" -> "+60 12-345 6789". */
export function formatPhone(digits) {
  if (!digits) return '';
  const d = String(digits).replace(/[^0-9]/g, '');
  if (d.startsWith('60') && d.length >= 11) {
    return `+60 ${d.slice(2, 4)}-${d.slice(4, 7)} ${d.slice(7)}`;
  }
  return `+${d}`;
}
