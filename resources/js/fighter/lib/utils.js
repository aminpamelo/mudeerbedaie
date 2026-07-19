/** Join truthy class fragments. */
export function cn(...parts) {
  return parts.filter(Boolean).join(' ');
}

/** CSRF token from the page meta tag, for fetch() POSTs. */
export function csrfToken() {
  return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

/**
 * Delete (soft-delete) a funnel the current fighter owns. Ownership is enforced
 * server-side by the funnel.owner middleware. Throws on failure.
 */
export async function deleteFunnel(uuid) {
  const res = await fetch(`/api/v1/funnels/${uuid}`, {
    method: 'DELETE',
    headers: { 'X-CSRF-TOKEN': csrfToken(), Accept: 'application/json' },
    credentials: 'same-origin',
  });
  if (!res.ok) {
    throw new Error('Failed to delete funnel');
  }
}

/**
 * Create a draft funnel (owned by the current fighter) and jump straight into
 * the builder for it — skipping the funnel list. The funnel is named later in
 * the builder. Throws on failure so the caller can surface an error.
 */
export async function createFunnelAndOpen() {
  const res = await fetch('/api/v1/funnels', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-TOKEN': csrfToken(),
      Accept: 'application/json',
    },
    credentials: 'same-origin',
    body: JSON.stringify({ name: 'Untitled funnel' }),
  });
  if (!res.ok) {
    throw new Error('Failed to create funnel');
  }
  const json = await res.json();
  const uuid = json?.data?.uuid;
  if (!uuid) {
    throw new Error('No funnel returned');
  }
  // ?from=fighter tells the builder this is the fighter workspace, so "back"
  // returns to /fighter instead of the admin funnel list.
  window.location.href = `/funnel-builder/${uuid}?from=fighter`;
}

/** GET a JSON endpoint in the fighter app (session-authed). Throws on failure. */
export async function fighterJson(path) {
  const res = await fetch(path, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
  if (!res.ok) {
    throw new Error('Request failed');
  }
  return res.json();
}

/**
 * Send a write request (POST/DELETE) to the fighter app. Pass `form: true` with
 * a FormData body for multipart (file) uploads. Throws with the server message.
 */
export async function fighterSend(path, { method = 'POST', body = null, form = false } = {}) {
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
  return name
    .trim()
    .split(/\s+/)
    .slice(0, 2)
    .map((w) => w.charAt(0).toUpperCase())
    .join('');
}

/** Format a number as Malaysian Ringgit. */
export function formatMoney(value, { withSymbol = true } = {}) {
  const num = Number(value || 0);
  const formatted = num.toLocaleString('en-MY', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });
  return withSymbol ? `RM ${formatted}` : formatted;
}

/** Compact integer formatting (1,234). */
export function formatNumber(value) {
  return Number(value || 0).toLocaleString('en-MY');
}

/** Short human date, e.g. "15 Jul 2026". */
export function formatDate(iso) {
  if (!iso) return '';
  return new Date(iso).toLocaleDateString('en-MY', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
  });
}

/** Relative time, e.g. "3h ago". Falls back to a short date beyond a week. */
export function timeAgo(iso) {
  if (!iso) return '';
  const then = new Date(iso).getTime();
  const secs = Math.round((Date.now() - then) / 1000);
  if (secs < 60) return 'just now';
  const mins = Math.round(secs / 60);
  if (mins < 60) return `${mins}m ago`;
  const hours = Math.round(mins / 60);
  if (hours < 24) return `${hours}h ago`;
  const days = Math.round(hours / 24);
  if (days < 7) return `${days}d ago`;
  return formatDate(iso);
}

/** Badge palette for a funnel status. */
export function statusMeta(status) {
  switch (status) {
    case 'published':
      return { label: 'Published', className: 'bg-emerald-50 text-emerald-700 ring-emerald-600/20' };
    case 'draft':
      return { label: 'Draft', className: 'bg-slate-100 text-slate-600 ring-slate-500/20' };
    case 'archived':
      return { label: 'Archived', className: 'bg-slate-100 text-slate-500 ring-slate-400/20' };
    default:
      return { label: status || 'Unknown', className: 'bg-slate-100 text-slate-600 ring-slate-500/20' };
  }
}
