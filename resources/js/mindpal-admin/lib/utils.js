/** Join truthy class fragments. */
export function cn(...parts) {
  return parts.filter(Boolean).join(' ');
}

/** CSRF token from the page meta tag, for fetch() calls. */
export function csrfToken() {
  return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

/** Short human date, e.g. "15 Jul 2026". */
export function formatDate(iso) {
  if (!iso) return '';
  return new Date(iso).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
}

/** Relative time, e.g. "3h ago". Falls back to a short date beyond a week. */
export function timeAgo(iso) {
  if (!iso) return '';
  const secs = Math.round((Date.now() - new Date(iso).getTime()) / 1000);
  if (secs < 60) return 'just now';
  const mins = Math.round(secs / 60);
  if (mins < 60) return `${mins}m ago`;
  const hours = Math.round(mins / 60);
  if (hours < 24) return `${hours}h ago`;
  const days = Math.round(hours / 24);
  if (days < 7) return `${days}d ago`;
  return formatDate(iso);
}

/** Compact integer formatting (1,234). */
export function formatNumber(value) {
  return Number(value || 0).toLocaleString('en-MY');
}

/** Format bytes to a human-readable size string. */
export function formatBytes(bytes) {
  if (!bytes) return '0 B';
  const k = 1024;
  const sizes = ['B', 'KB', 'MB', 'GB'];
  const i = Math.floor(Math.log(bytes) / Math.log(k));
  return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
}

/** Up-to-two-letter initials from a name. */
export function initialsFrom(name) {
  if (!name) return '?';
  return name.trim().split(/\s+/).slice(0, 2).map((w) => w.charAt(0).toUpperCase()).join('');
}
