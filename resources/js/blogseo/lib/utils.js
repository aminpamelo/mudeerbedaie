/** Join truthy class fragments. */
export function cn(...parts) {
  return parts.filter(Boolean).join(' ');
}

/** CSRF token from the page meta tag, for fetch() calls. */
export function csrfToken() {
  return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

/** GET a JSON endpoint in the workspace (session-authed). Throws on failure. */
export async function getJson(path) {
  const res = await fetch(path, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
  if (!res.ok) throw new Error('Request failed');
  return res.json();
}

/**
 * Send a write request. Pass `form: true` with a FormData body for uploads.
 * Throws with the server's message so callers can surface it in a toast.
 */
export async function send(path, { method = 'POST', body = null, form = false } = {}) {
  const headers = { 'X-CSRF-TOKEN': csrfToken(), Accept: 'application/json' };
  let payload = body;
  if (body && !form) {
    headers['Content-Type'] = 'application/json';
    payload = JSON.stringify(body);
  }
  const res = await fetch(path, { method, headers, credentials: 'same-origin', body: payload });
  if (!res.ok) {
    const data = await res.json().catch(() => null);
    throw new Error(data?.message || 'Request failed');
  }
  return res.json().catch(() => ({}));
}

/** Up-to-two-letter initials from a name. */
export function initialsFrom(name) {
  if (!name) return '?';
  return name.trim().split(/\s+/).slice(0, 2).map((w) => w.charAt(0).toUpperCase()).join('');
}

/** Compact integer formatting (1,234). */
export function formatNumber(value) {
  return Number(value || 0).toLocaleString('en-MY');
}

/** Short human date, e.g. "15 Jul 2026". */
export function formatDate(iso) {
  if (!iso) return '';
  return new Date(iso).toLocaleDateString('en-MY', { day: '2-digit', month: 'short', year: 'numeric' });
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

/** Post status → badge palette. */
export function postStatusMeta(status) {
  switch (status) {
    case 'published':
      return { label: 'Published', className: 'bg-emerald-50 text-emerald-700 ring-emerald-600/20' };
    case 'scheduled':
      return { label: 'Scheduled', className: 'bg-sky-50 text-sky-700 ring-sky-600/20' };
    case 'archived':
      return { label: 'Archived', className: 'bg-slate-100 text-slate-500 ring-slate-400/20' };
    default:
      return { label: 'Draft', className: 'bg-amber-50 text-amber-700 ring-amber-600/20' };
  }
}

/**
 * SEO score → grade label plus the colour tokens used by gauges, bars and
 * pills. Thresholds mirror SeoAnalyzer::grade() so the UI never disagrees
 * with the stored score.
 */
export function scoreMeta(score) {
  const n = Number(score || 0);
  if (n >= 85) {
    return { grade: 'Excellent', text: 'text-emerald-600', bg: 'bg-emerald-500', ring: 'stroke-emerald-500', soft: 'bg-emerald-50 text-emerald-700 ring-emerald-600/20' };
  }
  if (n >= 70) {
    return { grade: 'Good', text: 'text-lime-600', bg: 'bg-lime-500', ring: 'stroke-lime-500', soft: 'bg-lime-50 text-lime-700 ring-lime-600/20' };
  }
  if (n >= 50) {
    return { grade: 'Needs work', text: 'text-amber-600', bg: 'bg-amber-500', ring: 'stroke-amber-500', soft: 'bg-amber-50 text-amber-700 ring-amber-600/20' };
  }
  return { grade: 'Poor', text: 'text-rose-600', bg: 'bg-rose-500', ring: 'stroke-rose-500', soft: 'bg-rose-50 text-rose-700 ring-rose-600/20' };
}

/** Issue severity → dot + badge palette. */
export function severityMeta(severity) {
  switch (severity) {
    case 'critical':
      return { label: 'Critical', dot: 'bg-rose-500', badge: 'bg-rose-50 text-rose-700 ring-rose-600/20' };
    case 'warning':
      return { label: 'Warning', dot: 'bg-amber-500', badge: 'bg-amber-50 text-amber-700 ring-amber-600/20' };
    default:
      return { label: 'Notice', dot: 'bg-sky-500', badge: 'bg-sky-50 text-sky-700 ring-sky-600/20' };
  }
}

/** SEO check status → icon tone used by the editor checklist. */
export function checkTone(status) {
  switch (status) {
    case 'pass':
      return 'text-emerald-500';
    case 'warn':
      return 'text-amber-500';
    default:
      return 'text-rose-500';
  }
}
